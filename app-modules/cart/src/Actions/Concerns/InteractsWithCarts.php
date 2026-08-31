<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions\Concerns;

use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Cache;
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
use UnexpectedValueException;

trait InteractsWithCarts
{
    private const int LOCK_SECONDS = 10;

    private const int LOCK_WAIT_SECONDS = 5;

    private const int TRANSACTION_ATTEMPTS = 5;

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
            throw new InvalidArgumentException((string) __('A valid guest ULID is required when no buyer is present.'));
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

        $cart = Cart::unguarded(
            fn (): Cart => Cart::query()->createOrFirst(
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
            ),
        );

        $this->assertActive($cart);

        return $cart;
    }

    private function findOrClaimBuyerCart(User $buyer, string $guestToken): Cart
    {
        $guestToken = strtolower(trim($guestToken));

        if (! Str::isUlid($guestToken)) {
            throw new InvalidArgumentException((string) __('A valid guest ULID is required to restore a cart.'));
        }

        $buyerId = (string) $buyer->getKey();
        $buyerKey = "buyer:{$buyerId}";
        $guestKey = "guest:{$guestToken}";

        return Cache::lock($this->identityLockKey($buyerKey), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => Cache::lock(
                $this->identityLockKey($guestKey),
                self::LOCK_SECONDS,
            )->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(
                fn (): Cart => $this->claimGuestCart($buyerId, $guestToken, $buyerKey, $guestKey),
                self::TRANSACTION_ATTEMPTS,
            )));
    }

    private function claimGuestCart(
        string $buyerId,
        string $guestToken,
        string $buyerKey,
        string $guestKey,
    ): Cart {
        $carts = Cart::query()
            ->whereIn('active_key', [$buyerKey, $guestKey])
            ->where('status', CartStatus::Active->value)
            ->orderBy('active_key')
            ->lockForUpdate()
            ->get()
            ->keyBy('active_key');

        $buyerCart = $carts->get($buyerKey);
        $guestCart = $carts->get($guestKey);

        if (! $guestCart instanceof Cart) {
            return $buyerCart instanceof Cart
                ? $buyerCart
                : $this->findOrCreateActiveCart($buyerId, null, $buyerKey);
        }

        if (! $buyerCart instanceof Cart) {
            return $this->adoptGuestCart($guestCart, $buyerId, $buyerKey);
        }

        $buyerItemReferences = CartItem::query()
            ->where('cart_id', $buyerCart->getKey())
            ->orderBy('id')
            ->get();
        $guestItemReferences = CartItem::query()
            ->where('cart_id', $guestCart->getKey())
            ->orderBy('id')
            ->get();

        if ($guestItemReferences->isEmpty()) {
            $this->abandon($guestCart);

            return $buyerCart;
        }

        if ($buyerItemReferences->isEmpty()) {
            $this->abandon($buyerCart);

            return $this->adoptGuestCart($guestCart, $buyerId, $buyerKey);
        }

        if ($buyerCart->currency !== $guestCart->currency) {
            return $buyerCart;
        }

        $listingIds = $buyerItemReferences
            ->concat($guestItemReferences)
            ->pluck('listing_id')
            ->filter()
            ->map(fn (mixed $listingId): string => (string) $listingId)
            ->unique()
            ->sort()
            ->values();
        $listings = Listing::query()
            ->published()
            ->whereIn('id', $listingIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($listings->count() !== $listingIds->count()) {
            return $buyerCart;
        }

        $buyerItemsByListing = $buyerItemReferences->keyBy('listing_id');

        foreach ($guestItemReferences as $guestItem) {
            $buyerItem = $buyerItemsByListing->get($guestItem->listing_id);
            $quantity = $guestItem->quantity;

            if ($buyerItem instanceof CartItem) {
                $quantity += $buyerItem->quantity;
            }

            $listing = $listings->get($guestItem->listing_id);

            if (! $listing instanceof Listing || $quantity > $this->listingInventoryQuantity($listing)) {
                return $buyerCart;
            }
        }

        $buyerItems = CartItem::query()
            ->where('cart_id', $buyerCart->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $guestItems = CartItem::query()
            ->where('cart_id', $guestCart->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $buyerItemsByListing = $buyerItems->keyBy('listing_id');

        foreach ($guestItems as $guestItem) {
            $buyerItem = $buyerItemsByListing->get($guestItem->listing_id);

            if (! $buyerItem instanceof CartItem) {
                $guestItem->forceFill(['cart_id' => $buyerCart->getKey()])->save();

                continue;
            }

            $quantity = $buyerItem->quantity + $guestItem->quantity;

            $buyerItem->forceFill([
                'title' => $guestItem->title,
                'currency' => $guestItem->currency,
                'unit_price_minor' => $guestItem->unit_price_minor,
                'quantity' => $quantity,
                'line_total_minor' => $guestItem->unit_price_minor * $quantity,
            ])->save();
        }

        $this->abandon($guestCart);

        return $this->recalculate($buyerCart);
    }

    private function adoptGuestCart(Cart $guestCart, string $buyerId, string $buyerKey): Cart
    {
        $guestCart->forceFill([
            'buyer_id' => $buyerId,
            'guest_token' => null,
            'active_key' => $buyerKey,
            'version' => $guestCart->version + 1,
        ])->save();

        return $guestCart->fresh(['items']);
    }

    private function abandon(Cart $cart): void
    {
        $cart->forceFill([
            'active_key' => null,
            'status' => CartStatus::Abandoned,
            'version' => $cart->version + 1,
        ])->save();
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

    private function assertListingNotOwnedByBuyer(Listing $listing, ?string $buyerId): void
    {
        if ($buyerId === null || (string) $listing->getAttribute('seller_id') !== $buyerId) {
            return;
        }

        throw SelfPurchaseNotAllowed::forBuyer();
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

        throw new InvalidArgumentException((string) __('Cart quantity must be greater than zero.'));
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
            throw new UnexpectedValueException((string) __('Listing currency must be a string-backed value.'));
        }

        return strtoupper($currency);
    }

    private function listingTitle(Listing $listing): string
    {
        $title = $listing->getAttribute('title');

        if (! is_string($title)) {
            throw new UnexpectedValueException((string) __('Listing title must be a string.'));
        }

        return $title;
    }

    private function listingPriceMinor(Listing $listing): int
    {
        $priceMinor = $listing->getAttribute('price_minor');

        if (! is_int($priceMinor)) {
            throw new UnexpectedValueException((string) __('Listing price must be an integer minor-unit amount.'));
        }

        return $priceMinor;
    }

    private function listingInventoryQuantity(Listing $listing): int
    {
        $inventoryQuantity = $listing->getAttribute('inventory_quantity');

        if (! is_int($inventoryQuantity)) {
            throw new UnexpectedValueException((string) __('Listing inventory quantity must be an integer.'));
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
