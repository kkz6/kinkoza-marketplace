<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Services;

use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Exceptions\CartNotActive;
use Kinkoza\Cart\Exceptions\CurrencyMismatch;
use Kinkoza\Cart\Exceptions\InsufficientInventory;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Models\CartItem;
use Kinkoza\Catalog\Models\Listing;
use UnexpectedValueException;

class CartService implements CartServiceInterface
{
    private const int LOCK_SECONDS = 10;

    private const int LOCK_WAIT_SECONDS = 5;

    private const int TRANSACTION_ATTEMPTS = 5;

    public function getOrCreateFor(?User $buyer, string $guestToken): Cart
    {
        [$buyerId, $normalizedGuestToken, $activeKey] = $this->identity($buyer, $guestToken);

        return Cache::lock($this->identityLockKey($activeKey), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(
                fn (): Cart => $this->findOrCreateActiveCart($buyerId, $normalizedGuestToken, $activeKey),
                self::TRANSACTION_ATTEMPTS,
            ));
    }

    public function add(
        Listing $listing,
        int $quantity,
        ?User $buyer,
        string $guestToken,
        ?int $expectedVersion = null,
    ): Cart {
        $this->assertPositiveQuantity($quantity);

        [$buyerId, $normalizedGuestToken, $activeKey] = $this->identity($buyer, $guestToken);

        return Cache::lock($this->identityLockKey($activeKey), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(function () use (
                $activeKey,
                $buyerId,
                $expectedVersion,
                $listing,
                $normalizedGuestToken,
                $quantity,
            ): Cart {
                $cart = $this->findOrCreateActiveCart($buyerId, $normalizedGuestToken, $activeKey);
                $cart = $this->lockCart((string) $cart->getKey());

                $this->assertActive($cart);
                $this->assertVersion($cart, $expectedVersion);

                $lockedListing = $this->lockPublishedListing((string) $listing->getKey());
                $listingCurrency = $this->currencyOf($lockedListing);
                $listingPriceMinor = $this->listingPriceMinor($lockedListing);

                $item = CartItem::query()
                    ->where('cart_id', $cart->getKey())
                    ->where('listing_id', $lockedListing->getKey())
                    ->lockForUpdate()
                    ->first();

                $newQuantity = $quantity;

                if ($item) {
                    $newQuantity += $item->quantity;
                }

                $this->assertInventory($lockedListing, $newQuantity);
                $this->prepareCurrency($cart, $listingCurrency);

                if ($item) {
                    $item->forceFill([
                        'quantity' => $newQuantity,
                        'line_total_minor' => $item->unit_price_minor * $newQuantity,
                    ])->save();
                }

                if (! $item) {
                    CartItem::query()->create([
                        'cart_id' => $cart->getKey(),
                        'listing_id' => $lockedListing->getKey(),
                        'sku' => (string) $lockedListing->getKey(),
                        'title' => $this->listingTitle($lockedListing),
                        'currency' => $listingCurrency,
                        'unit_price_minor' => $listingPriceMinor,
                        'line_total_minor' => $listingPriceMinor * $quantity,
                        'quantity' => $quantity,
                    ]);
                }

                return $this->recalculate($cart);
            }, self::TRANSACTION_ATTEMPTS));
    }

    public function updateQuantity(
        Cart $cart,
        string $itemId,
        int $quantity,
        int $expectedVersion,
    ): Cart {
        $this->assertPositiveQuantity($quantity);

        return Cache::lock($this->cartLockKey((string) $cart->getKey()), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(function () use (
                $cart,
                $expectedVersion,
                $itemId,
                $quantity,
            ): Cart {
                $lockedCart = $this->lockCart((string) $cart->getKey());

                $this->assertActive($lockedCart);
                $this->assertVersion($lockedCart, $expectedVersion);

                $itemReference = $this->findItem($lockedCart, $itemId);

                if (! $itemReference->listing_id) {
                    throw ListingUnavailable::forListing($itemId);
                }

                $listing = $this->lockPublishedListing((string) $itemReference->listing_id);
                $item = $this->lockItem($lockedCart, $itemId);

                $this->assertInventory($listing, $quantity);

                $item->forceFill([
                    'quantity' => $quantity,
                    'line_total_minor' => $item->unit_price_minor * $quantity,
                ])->save();

                return $this->recalculate($lockedCart);
            }, self::TRANSACTION_ATTEMPTS));
    }

    public function remove(Cart $cart, string $itemId, int $expectedVersion): Cart
    {
        return Cache::lock($this->cartLockKey((string) $cart->getKey()), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(function () use (
                $cart,
                $expectedVersion,
                $itemId,
            ): Cart {
                $lockedCart = $this->lockCart((string) $cart->getKey());

                $this->assertActive($lockedCart);
                $this->assertVersion($lockedCart, $expectedVersion);

                $itemReference = $this->findItem($lockedCart, $itemId);

                if ($itemReference->listing_id) {
                    Listing::query()
                        ->whereKey($itemReference->listing_id)
                        ->lockForUpdate()
                        ->first();
                }

                $item = $this->lockItem($lockedCart, $itemId);
                $item->delete();

                return $this->recalculate($lockedCart);
            }, self::TRANSACTION_ATTEMPTS));
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    private function identity(?User $buyer, string $guestToken): array
    {
        if ($buyer) {
            $buyerId = (string) $buyer->getKey();

            return [$buyerId, null, "buyer:{$buyerId}"];
        }

        $guestToken = strtolower(trim($guestToken));

        if (! Str::isUlid($guestToken)) {
            throw new InvalidArgumentException('A valid guest ULID is required when no buyer is present.');
        }

        return [null, $guestToken, "guest:{$guestToken}"];
    }

    private function findOrCreateActiveCart(
        ?string $buyerId,
        ?string $guestToken,
        string $activeKey,
    ): Cart {
        $cart = Cart::query()
            ->where('active_key', $activeKey)
            ->where('status', CartStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if ($cart) {
            return $cart;
        }

        $cart = Cart::query()->createOrFirst(
            ['active_key' => $activeKey],
            [
                'buyer_id' => $buyerId,
                'guest_token' => $guestToken,
                'currency' => $this->defaultCurrency(),
                'status' => CartStatus::Active,
                'subtotal_minor' => 0,
                'total_minor' => 0,
                'version' => 1,
            ],
        );

        $this->assertActive($cart);

        return $cart;
    }

    private function lockCart(string $cartId): Cart
    {
        return Cart::query()->whereKey($cartId)->lockForUpdate()->firstOrFail();
    }

    private function lockItem(Cart $cart, string $itemId): CartItem
    {
        return CartItem::query()
            ->whereKey($itemId)
            ->where('cart_id', $cart->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function findItem(Cart $cart, string $itemId): CartItem
    {
        return CartItem::query()
            ->whereKey($itemId)
            ->where('cart_id', $cart->getKey())
            ->firstOrFail();
    }

    private function lockPublishedListing(string $listingId): Listing
    {
        $listing = Listing::query()
            ->published()
            ->whereKey($listingId)
            ->lockForUpdate()
            ->first();

        if (! $listing) {
            throw ListingUnavailable::forListing($listingId);
        }

        return $listing;
    }

    private function prepareCurrency(Cart $cart, string $listingCurrency): void
    {
        if (! $cart->items()->exists()) {
            $cart->forceFill(['currency' => $listingCurrency]);

            return;
        }

        if ($cart->currency !== $listingCurrency) {
            throw CurrencyMismatch::forCurrencies($cart->currency, $listingCurrency);
        }
    }

    private function assertInventory(Listing $listing, int $requestedQuantity): void
    {
        $availableQuantity = $this->listingInventoryQuantity($listing);

        if ($requestedQuantity <= $availableQuantity) {
            return;
        }

        throw InsufficientInventory::forListing(
            (string) $listing->getKey(),
            $requestedQuantity,
            $availableQuantity,
        );
    }

    private function assertVersion(Cart $cart, ?int $expectedVersion): void
    {
        if ($expectedVersion === null || $cart->version === $expectedVersion) {
            return;
        }

        throw StaleCartVersion::forVersions($expectedVersion, $cart->version);
    }

    private function assertActive(Cart $cart): void
    {
        if ($cart->status === CartStatus::Active && $cart->active_key !== null) {
            return;
        }

        throw CartNotActive::forCart((string) $cart->getKey());
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity > 0) {
            return;
        }

        throw new InvalidArgumentException('Cart quantity must be greater than zero.');
    }

    private function recalculate(Cart $cart): Cart
    {
        $subtotal = (int) $cart->items()->sum('line_total_minor');

        $cart->forceFill([
            'subtotal_minor' => $subtotal,
            'total_minor' => $subtotal,
            'version' => $cart->version + 1,
        ])->save();

        return $cart->fresh(['items']);
    }

    private function currencyOf(Listing $listing): string
    {
        $currency = $listing->getAttribute('currency');

        if ($currency instanceof BackedEnum) {
            $currency = $currency->value;
        }

        if (! is_string($currency)) {
            throw new UnexpectedValueException('Listing currency must be a string-backed value.');
        }

        return strtoupper($currency);
    }

    private function listingTitle(Listing $listing): string
    {
        $title = $listing->getAttribute('title');

        if (! is_string($title)) {
            throw new UnexpectedValueException('Listing title must be a string.');
        }

        return $title;
    }

    private function listingPriceMinor(Listing $listing): int
    {
        $priceMinor = $listing->getAttribute('price_minor');

        if (! is_int($priceMinor)) {
            throw new UnexpectedValueException('Listing price must be an integer minor-unit amount.');
        }

        return $priceMinor;
    }

    private function listingInventoryQuantity(Listing $listing): int
    {
        $inventoryQuantity = $listing->getAttribute('inventory_quantity');

        if (! is_int($inventoryQuantity)) {
            throw new UnexpectedValueException('Listing inventory quantity must be an integer.');
        }

        return $inventoryQuantity;
    }

    private function defaultCurrency(): string
    {
        return strtoupper((string) config('app.currency', 'EUR'));
    }

    private function identityLockKey(string $activeKey): string
    {
        return 'cart:identity:'.hash('sha256', $activeKey);
    }

    private function cartLockKey(string $cartId): string
    {
        return "cart:{$cartId}";
    }
}
