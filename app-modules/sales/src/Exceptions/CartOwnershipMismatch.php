<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Exceptions;

use DomainException;

final class CartOwnershipMismatch extends DomainException
{
    public static function forBuyer(): self
    {
        return new self((string) __('The cart does not belong to this buyer.'));
    }
}
