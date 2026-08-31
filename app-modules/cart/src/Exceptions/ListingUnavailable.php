<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use DomainException;

class ListingUnavailable extends DomainException
{
    public static function forListing(string $listingId): self
    {
        return new self("Listing [{$listingId}] is unavailable.");
    }
}
