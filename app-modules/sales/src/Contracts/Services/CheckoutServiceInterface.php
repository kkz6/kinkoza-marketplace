<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Contracts\Services;

use App\Models\User;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Models\Order;

interface CheckoutServiceInterface
{
    public function checkout(Cart $cart, User $buyer, string $idempotencyKey): Order;
}
