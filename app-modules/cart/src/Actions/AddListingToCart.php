<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kinkoza\Cart\Actions\Concerns\InteractsWithCarts;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Models\CartItem;
use Kinkoza\Catalog\Models\Listing;
use Lorisleiva\Actions\Concerns\AsAction;

class AddListingToCart
{
    use AsAction;
    use InteractsWithCarts;

    public function handle(
        Listing $listing,
        int $quantity,
        ?User $buyer,
        string $guestToken,
        ?int $expectedVersion = null,
    ): Cart {
        $this->assertPositiveQuantity($quantity);

        if ($buyer) {
            $this->assertListingNotOwnedByBuyer($listing, $buyer->id);
            $this->findOrClaimBuyerCart($buyer, $guestToken);
        }

        [$buyerId, $normalizedGuestToken, $activeKey] = $this->identity($buyer, $guestToken);

        $cart = Cache::lock($this->identityLockKey($activeKey), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(function () use (
                $activeKey,
                $buyerId,
                $expectedVersion,
                $listing,
                $normalizedGuestToken,
                $quantity,
            ): Cart {
                $cart = $this->findOrCreateActiveCart($buyerId, $normalizedGuestToken, $activeKey);
                $cart = $this->lockCart($cart->id);

                $this->assertActive($cart);
                $this->assertVersion($cart, $expectedVersion);

                $lockedListing = $this->lockPublishedListing($listing->id);
                $this->assertListingNotOwnedByBuyer($lockedListing, $buyerId);
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
                    CartItem::query()->forceCreate([
                        'cart_id' => $cart->getKey(),
                        'listing_id' => $lockedListing->getKey(),
                        'sku' => $lockedListing->id,
                        'title' => $this->listingTitle($lockedListing),
                        'currency' => $listingCurrency,
                        'unit_price_minor' => $listingPriceMinor,
                        'line_total_minor' => $listingPriceMinor * $quantity,
                        'quantity' => $quantity,
                    ]);
                }

                return $this->recalculate($cart);
            }, self::TRANSACTION_ATTEMPTS));

        return $this->ensureCart($cart);
    }
}
