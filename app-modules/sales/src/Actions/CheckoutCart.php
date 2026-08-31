<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Actions;

use App\Models\User;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Services\CheckoutService;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckoutCart
{
    use AsAction;

    public function __construct(private readonly CheckoutService $checkout) {}

    public function handle(
        Cart $cart,
        User $buyer,
        string $idempotencyKey,
        int $expectedVersion,
    ): Order {
        return $this->checkout->checkout(
            $cart,
            $buyer,
            $idempotencyKey,
            $expectedVersion,
        );
    }
}
