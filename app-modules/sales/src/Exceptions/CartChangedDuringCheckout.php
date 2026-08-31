<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Exceptions;

use DomainException;

final class CartChangedDuringCheckout extends DomainException
{
    public static function beforeSequenceReservation(): self
    {
        return new self((string) __('The cart changed before its sequence range was reserved.'));
    }
}
