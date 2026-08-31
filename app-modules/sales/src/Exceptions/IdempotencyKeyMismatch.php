<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Exceptions;

use DomainException;

final class IdempotencyKeyMismatch extends DomainException
{
    public static function forCart(): self
    {
        return new self((string) __('The idempotency key does not match this cart.'));
    }
}
