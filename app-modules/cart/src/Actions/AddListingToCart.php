<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use App\Models\User;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Services\CartService;
use Kinkoza\Catalog\Models\Listing;
use Lorisleiva\Actions\Concerns\AsAction;

class AddListingToCart
{
    use AsAction;

    public function __construct(private readonly CartService $carts) {}

    public function handle(
        Listing $listing,
        int $quantity,
        ?User $buyer,
        string $guestToken,
        ?int $expectedVersion = null,
    ): Cart {
        return $this->carts->add(
            $listing,
            $quantity,
            $buyer,
            $guestToken,
            $expectedVersion,
        );
    }
}
