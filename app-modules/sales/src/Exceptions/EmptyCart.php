<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Exceptions;

use DomainException;

final class EmptyCart extends DomainException
{
    public static function forCheckout(): self
    {
        return new self((string) __('An empty cart cannot be checked out.'));
    }
}
