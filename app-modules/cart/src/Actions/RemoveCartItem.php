<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Services\CartService;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveCartItem
{
    use AsAction;

    public function __construct(private readonly CartService $carts) {}

    public function handle(Cart $cart, string $itemId, int $expectedVersion): Cart
    {
        return $this->carts->remove($cart, $itemId, $expectedVersion);
    }
}
