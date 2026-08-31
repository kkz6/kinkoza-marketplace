<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use DomainException;

class InsufficientInventory extends DomainException
{
    public static function forListing(
        string $listingId,
        int $requestedQuantity,
        int $availableQuantity,
    ): self {
        return new self(
            (string) __('Listing [:listing] only has [:available] units available; [:requested] requested.', [
                'available' => $availableQuantity,
                'listing' => $listingId,
                'requested' => $requestedQuantity,
            ]),
        );
    }
}
