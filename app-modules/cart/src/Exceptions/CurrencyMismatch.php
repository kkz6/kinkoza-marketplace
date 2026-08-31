<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use DomainException;

class CurrencyMismatch extends DomainException
{
    public static function forCurrencies(string $cartCurrency, string $listingCurrency): self
    {
        return new self(
            "Cannot add a [{$listingCurrency}] listing to a [{$cartCurrency}] cart."
        );
    }
}
