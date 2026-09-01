<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kinkoza\Cart\Actions\Concerns\InteractsWithCarts;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Catalog\Models\Listing;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveCartItem
{
    use AsAction;
    use InteractsWithCarts;

    public function handle(Cart $cart, string $itemId, int $expectedVersion): Cart
    {
        $updatedCart = Cache::lock($this->cartLockKey($cart->id), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(function () use (
                $cart,
                $expectedVersion,
                $itemId,
            ): Cart {
                $lockedCart = $this->lockCart($cart->id);

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

        return $this->ensureCart($updatedCart);
    }
}
