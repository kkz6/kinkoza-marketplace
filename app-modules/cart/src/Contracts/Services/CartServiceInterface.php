<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Contracts\Services;

use App\Models\User;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Catalog\Models\Listing;

interface CartServiceInterface
{
    public function getOrCreateFor(?User $buyer, string $guestToken): Cart;

    public function add(
        Listing $listing,
        int $quantity,
        ?User $buyer,
        string $guestToken,
        ?int $expectedVersion = null,
    ): Cart;

    public function updateQuantity(
        Cart $cart,
        string $itemId,
        int $quantity,
        int $expectedVersion,
    ): Cart;

    public function remove(Cart $cart, string $itemId, int $expectedVersion): Cart;
}
