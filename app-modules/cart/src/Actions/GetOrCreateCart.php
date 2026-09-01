<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kinkoza\Cart\Actions\Concerns\InteractsWithCarts;
use Kinkoza\Cart\Models\Cart;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrCreateCart
{
    use AsAction;
    use InteractsWithCarts;

    public function handle(?User $buyer, string $guestToken): Cart
    {
        if ($buyer) {
            return $this->findOrClaimBuyerCart($buyer, $guestToken);
        }

        [$buyerId, $normalizedGuestToken, $activeKey] = $this->identity($buyer, $guestToken);

        $cart = Cache::lock($this->identityLockKey($activeKey), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, fn (): Cart => DB::transaction(
                fn (): Cart => $this->findOrCreateActiveCart($buyerId, $normalizedGuestToken, $activeKey),
                self::TRANSACTION_ATTEMPTS,
            ));

        return $this->ensureCart($cart);
    }
}
