<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use App\Models\User;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Services\CartService;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrCreateCart
{
    use AsAction;

    public function __construct(private readonly CartService $carts) {}

    public function handle(?User $buyer, string $guestToken): Cart
    {
        return $this->carts->getOrCreateFor($buyer, $guestToken);
    }
}
