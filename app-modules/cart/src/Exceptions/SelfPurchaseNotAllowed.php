<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use DomainException;

final class SelfPurchaseNotAllowed extends DomainException
{
    public static function forBuyer(): self
    {
        return new self((string) __('You cannot purchase your own listing.'));
    }
}
