<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kinkoza\Cart\Actions\Concerns\InteractsWithCarts;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Models\Cart;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCartItemQuantity
{
    use AsAction;
    use InteractsWithCarts;

    public function handle(
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
}
