<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Actions;

use App\Models\User;
use App\Support\Database\SequenceGenerator;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Exceptions\CartNotActive;
use Kinkoza\Cart\Exceptions\CurrencyMismatch;
use Kinkoza\Cart\Exceptions\InsufficientInventory;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Exceptions\SelfPurchaseNotAllowed;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Models\CartItem;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Enums\InvoiceStatus;
use Kinkoza\Sales\Enums\OrderStatus;
use Kinkoza\Sales\Events\OrderPlaced;
use Kinkoza\Sales\Exceptions\CartChangedDuringCheckout;
use Kinkoza\Sales\Exceptions\CartOwnershipMismatch;
use Kinkoza\Sales\Exceptions\EmptyCart;
use Kinkoza\Sales\Exceptions\IdempotencyKeyMismatch;
use Kinkoza\Sales\Exceptions\InvalidCartItemQuantity;
use Kinkoza\Sales\Models\Invoice;
use Kinkoza\Sales\Models\InvoiceItem;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Models\OrderItem;
use Lorisleiva\Actions\Concerns\AsAction;
use UnexpectedValueException;

class CheckoutCart
{
    use AsAction;

    private const int TRANSACTION_ATTEMPTS = 5;

    public function __construct(private readonly SequenceGenerator $sequences) {}

    public function handle(
        Cart $cart,
        User $buyer,
        string $idempotencyKey,
        int $expectedVersion,
    ): Order {
        $idempotencyKey = trim($idempotencyKey);

        $this->assertValidIdempotencyKey($idempotencyKey);

        $existingOrder = Order::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOrder) {
            return $this->assertOrderMatchesRequest($existingOrder, $buyer, $cart);
        }

        $existingOrder = Order::query()
            ->where('cart_id', $cart->getKey())
            ->first();

        if ($existingOrder) {
            return $this->assertOrderMatchesRequest($existingOrder, $buyer, $cart);
        }

        $preflightCart = Cart::query()
            ->whereKey($cart->getKey())
            ->firstOrFail();

        $this->assertCartOwnedByBuyer($preflightCart, $buyer);
        $this->assertCartActive($preflightCart);
        $this->assertCartVersion($preflightCart, $expectedVersion);

        $sequenceAllocation = $this->reserveSequences($preflightCart);

        try {
            return DB::transaction(function () use ($buyer, $cart, $expectedVersion, $idempotencyKey, $sequenceAllocation): Order {
                $lockedCart = Cart::query()
                    ->whereKey($cart->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingOrder = Order::query()
                    ->where('buyer_id', $buyer->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder) {
                    return $this->assertOrderMatchesRequest($existingOrder, $buyer, $lockedCart);
                }

                $existingOrder = Order::query()
                    ->where('cart_id', $lockedCart->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder) {
                    return $this->assertOrderMatchesRequest($existingOrder, $buyer, $lockedCart);
                }

                $this->assertCartOwnedByBuyer($lockedCart, $buyer);
                $this->assertCartActive($lockedCart);
                $this->assertCartVersion($lockedCart, $expectedVersion);

                $itemReferences = CartItem::query()
                    ->where('cart_id', $lockedCart->getKey())
                    ->orderBy('id')
                    ->get();

                if ($itemReferences->isEmpty()) {
                    throw EmptyCart::forCheckout();
                }

                $listings = $this->lockPublishedListings($itemReferences);
                $this->assertListingsNotOwnedByBuyer($listings, $buyer);
                $cartItems = CartItem::query()
                    ->where('cart_id', $lockedCart->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw EmptyCart::forCheckout();
                }

                $lines = $this->allocateLineGraph(
                    $cartItems,
                    $listings,
                    $lockedCart,
                    $sequenceAllocation['order_items'],
                    $sequenceAllocation['invoice_items'],
                );
                $subtotalMinor = array_sum(array_column($lines, 'line_total_minor'));
                $placedAt = now();
                $orderId = (string) Str::ulid();
                $invoiceId = (string) Str::ulid();
                $orderSequence = $sequenceAllocation['order'];
                $invoiceSequence = $sequenceAllocation['invoice'];

                $order = Order::query()->forceCreate([
                    'id' => $orderId,
                    'sequence' => $orderSequence,
                    'number' => sprintf('ORD-%08d', $orderSequence),
                    'buyer_id' => $buyer->getKey(),
                    'cart_id' => $lockedCart->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'status' => OrderStatus::Confirmed,
                    'currency' => $this->currencyOf($lockedCart->currency),
                    'subtotal_minor' => $subtotalMinor,
                    'total_minor' => $subtotalMinor,
                    'placed_at' => $placedAt,
                ]);

                foreach ($lines as $line) {
                    OrderItem::query()->forceCreate([
                        'id' => $line['order_item_id'],
                        'sequence' => $line['order_item_sequence'],
                        'order_id' => $orderId,
                        'listing_id' => $line['listing_id'],
                        'seller_id' => $line['seller_id'],
                        'title' => $line['title'],
                        'currency' => $line['currency'],
                        'unit_price_minor' => $line['unit_price_minor'],
                        'quantity' => $line['quantity'],
                        'line_total_minor' => $line['line_total_minor'],
                    ]);
                }

                $invoice = Invoice::query()->forceCreate([
                    'id' => $invoiceId,
                    'sequence' => $invoiceSequence,
                    'number' => sprintf('INV-%08d', $invoiceSequence),
                    'order_id' => $orderId,
                    'status' => InvoiceStatus::Issued,
                    'currency' => $this->currencyOf($lockedCart->currency),
                    'subtotal_minor' => $subtotalMinor,
                    'total_minor' => $subtotalMinor,
                    'issued_at' => $placedAt,
                ]);

                foreach ($lines as $line) {
                    InvoiceItem::query()->forceCreate([
                        'id' => $line['invoice_item_id'],
                        'sequence' => $line['invoice_item_sequence'],
                        'invoice_id' => $invoiceId,
                        'order_item_id' => $line['order_item_id'],
                        'listing_id' => $line['listing_id'],
                        'title' => $line['title'],
                        'currency' => $line['currency'],
                        'unit_price_minor' => $line['unit_price_minor'],
                        'quantity' => $line['quantity'],
                        'line_total_minor' => $line['line_total_minor'],
                    ]);
                }

                $this->decrementInventory($lines, $listings);

                $lockedCart->forceFill([
                    'active_key' => null,
                    'status' => CartStatus::Converted,
                    'converted_at' => $placedAt,
                    'version' => $lockedCart->version + 1,
                ])->save();

                OrderPlaced::dispatch($order);

                return $order->load(['items', 'invoice.items']);
            }, self::TRANSACTION_ATTEMPTS);
        } catch (UniqueConstraintViolationException $exception) {
            $existingOrder = Order::query()
                ->where(function ($query) use ($buyer, $idempotencyKey): void {
                    $query
                        ->where('buyer_id', $buyer->getKey())
                        ->where('idempotency_key', $idempotencyKey);
                })
                ->orWhere('cart_id', $cart->getKey())
                ->first();

            if (! $existingOrder) {
                throw $exception;
            }

            return $this->assertOrderMatchesRequest($existingOrder, $buyer, $cart);
        }
    }

    /**
     * @param  EloquentCollection<int, CartItem>  $cartItems
     * @return EloquentCollection<string, Listing>
     */
    private function lockPublishedListings(EloquentCollection $cartItems): EloquentCollection
    {
        $listingIds = $cartItems
            ->flatMap(fn (CartItem $item): array => $item->listing_id === null ? [] : [$item->listing_id])
            ->unique()
            ->sort()
            ->values();

        if ($listingIds->count() !== $cartItems->count()) {
            throw ListingUnavailable::forListing('deleted');
        }

        $listings = Listing::query()
            ->published()
            ->whereIn('id', $listingIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Listing $listing): string => $listing->id);

        if ($listings->count() !== $listingIds->count()) {
            $missingListingId = $listingIds
                ->first(fn (string $listingId): bool => ! $listings->has($listingId));

            if (! is_string($missingListingId)) {
                throw ListingUnavailable::forListing('deleted');
            }

            throw ListingUnavailable::forListing($missingListingId);
        }

        return $listings;
    }

    /**
     * @param  EloquentCollection<int, CartItem>  $cartItems
     * @param  EloquentCollection<string, Listing>  $listings
     * @param  list<int>  $orderItemSequences
     * @param  list<int>  $invoiceItemSequences
     * @return list<array{
     *     order_item_id: string,
     *     order_item_sequence: int,
     *     invoice_item_id: string,
     *     invoice_item_sequence: int,
     *     listing_id: string,
     *     seller_id: string,
     *     title: string,
     *     currency: string,
     *     unit_price_minor: int,
     *     quantity: int,
     *     line_total_minor: int
     * }>
     */
    private function allocateLineGraph(
        EloquentCollection $cartItems,
        EloquentCollection $listings,
        Cart $cart,
        array $orderItemSequences,
        array $invoiceItemSequences,
    ): array {
        $cartCurrency = $this->currencyOf($cart->currency);

        if (
            count($orderItemSequences) !== $cartItems->count()
            || count($invoiceItemSequences) !== $cartItems->count()
        ) {
            throw CartChangedDuringCheckout::beforeSequenceReservation();
        }

        $lines = [];

        foreach ($cartItems->values() as $index => $cartItem) {
            $listingId = $cartItem->listing_id;

            if ($listingId === null) {
                throw ListingUnavailable::forListing('deleted');
            }

            $listing = $listings->get($listingId);

            if (! $listing) {
                throw ListingUnavailable::forListing($listingId);
            }

            $quantity = $cartItem->quantity;
            $listingCurrency = $this->listingCurrency($listing);
            $lineCurrency = $this->currencyOf($cartItem->getAttribute('currency'));
            $availableQuantity = $this->listingInventoryQuantity($listing);

            if ($quantity < 1) {
                throw InvalidCartItemQuantity::forItem($cartItem->id);
            }

            if ($listingCurrency !== $cartCurrency) {
                throw CurrencyMismatch::forCurrencies($cartCurrency, $listingCurrency);
            }

            if ($lineCurrency !== $cartCurrency) {
                throw CurrencyMismatch::forCurrencies($cartCurrency, $lineCurrency);
            }

            if ($quantity > $availableQuantity) {
                throw InsufficientInventory::forListing($listingId, $quantity, $availableQuantity);
            }

            $unitPriceMinor = $this->cartItemUnitPriceMinor($cartItem);

            $lines[] = [
                'order_item_id' => (string) Str::ulid(),
                'order_item_sequence' => $orderItemSequences[$index],
                'invoice_item_id' => (string) Str::ulid(),
                'invoice_item_sequence' => $invoiceItemSequences[$index],
                'listing_id' => $listingId,
                'seller_id' => $this->listingSellerId($listing),
                'title' => $this->cartItemTitle($cartItem),
                'currency' => $lineCurrency,
                'unit_price_minor' => $unitPriceMinor,
                'quantity' => $quantity,
                'line_total_minor' => $unitPriceMinor * $quantity,
            ];
        }

        return $lines;
    }

    /**
     * @param list<array{
     *     listing_id: string,
     *     quantity: int
     * }> $lines
     * @param  EloquentCollection<string, Listing>  $listings
     */
    private function decrementInventory(array $lines, EloquentCollection $listings): void
    {
        foreach ($lines as $line) {
            $listing = $listings->get($line['listing_id']);

            if (! $listing) {
                throw ListingUnavailable::forListing($line['listing_id']);
            }

            $updated = Listing::query()
                ->whereKey($listing->getKey())
                ->where('inventory_quantity', '>=', $line['quantity'])
                ->decrement('inventory_quantity', $line['quantity'], [
                    'version' => DB::raw('version + 1'),
                ]);

            if ($updated === 1) {
                continue;
            }

            throw InsufficientInventory::forListing(
                $line['listing_id'],
                $line['quantity'],
                $this->listingInventoryQuantity($listing),
            );
        }
    }

    private function assertOrderMatchesRequest(Order $order, User $buyer, Cart $cart): Order
    {
        if (
            $this->orderBuyerId($order) === $buyer->id
            && $order->cart_id === $cart->id
        ) {
            return $order->loadMissing(['items', 'invoice.items']);
        }

        throw IdempotencyKeyMismatch::forCart();
    }

    private function assertCartOwnedByBuyer(Cart $cart, User $buyer): void
    {
        if ($cart->buyer_id === $buyer->id) {
            return;
        }

        throw CartOwnershipMismatch::forBuyer();
    }

    private function assertCartActive(Cart $cart): void
    {
        if ($cart->status === CartStatus::Active && $cart->active_key !== null) {
            return;
        }

        throw CartNotActive::forCart($cart->id);
    }

    private function assertCartVersion(Cart $cart, int $expectedVersion): void
    {
        if ($cart->version === $expectedVersion) {
            return;
        }

        throw StaleCartVersion::forVersions($expectedVersion, $cart->version);
    }

    /** @param  EloquentCollection<string, Listing>  $listings */
    private function assertListingsNotOwnedByBuyer(EloquentCollection $listings, User $buyer): void
    {
        $buyerId = $buyer->id;
        $containsOwnListing = $listings->contains(
            fn (Listing $listing): bool => $listing->seller_id === $buyerId,
        );

        if (! $containsOwnListing) {
            return;
        }

        throw SelfPurchaseNotAllowed::forBuyer();
    }

    /**
     * @return array{
     *     order: int,
     *     invoice: int,
     *     order_items: list<int>,
     *     invoice_items: list<int>
     * }
     */
    private function reserveSequences(Cart $cart): array
    {
        $lineCount = CartItem::query()
            ->where('cart_id', $cart->getKey())
            ->count();

        if ($lineCount < 1) {
            throw EmptyCart::forCheckout();
        }

        return [
            'order' => $this->sequences->next('orders'),
            'invoice' => $this->sequences->next('invoices'),
            'order_items' => $this->sequences->reserve('order_items', $lineCount),
            'invoice_items' => $this->sequences->reserve('invoice_items', $lineCount),
        ];
    }

    private function assertValidIdempotencyKey(string $idempotencyKey): void
    {
        if ($idempotencyKey !== '' && mb_strlen($idempotencyKey) <= 64) {
            return;
        }

        throw new InvalidArgumentException((string) __('The idempotency key must contain between 1 and 64 characters.'));
    }

    private function currencyOf(mixed $currency): string
    {
        if ($currency instanceof BackedEnum) {
            $currency = $currency->value;
        }

        if (! is_string($currency)) {
            throw new UnexpectedValueException((string) __('Currency must be a string-backed value.'));
        }

        return strtoupper($currency);
    }

    private function listingCurrency(Listing $listing): string
    {
        return $this->currencyOf($listing->getAttribute('currency'));
    }

    private function listingInventoryQuantity(Listing $listing): int
    {
        $inventoryQuantity = $listing->getAttribute('inventory_quantity');

        if (! is_int($inventoryQuantity)) {
            throw new UnexpectedValueException((string) __('Listing inventory quantity must be an integer.'));
        }

        return $inventoryQuantity;
    }

    private function cartItemUnitPriceMinor(CartItem $cartItem): int
    {
        $priceMinor = $cartItem->getAttribute('unit_price_minor');

        if (! is_int($priceMinor)) {
            throw new UnexpectedValueException((string) __('Cart item price must be an integer minor-unit amount.'));
        }

        return $priceMinor;
    }

    private function listingSellerId(Listing $listing): string
    {
        $sellerId = $listing->getAttribute('seller_id');

        if (! is_string($sellerId)) {
            throw new UnexpectedValueException((string) __('Listing seller ID must be a string.'));
        }

        return $sellerId;
    }

    private function cartItemTitle(CartItem $cartItem): string
    {
        $title = $cartItem->getAttribute('title');

        if (! is_string($title)) {
            throw new UnexpectedValueException((string) __('Cart item title must be a string.'));
        }

        return $title;
    }

    private function orderBuyerId(Order $order): string
    {
        $buyerId = $order->getAttribute('buyer_id');

        if (! is_string($buyerId)) {
            throw new UnexpectedValueException((string) __('Order buyer ID must be a string.'));
        }

        return $buyerId;
    }
}
