<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use DomainException;

class CurrencyMismatch extends DomainException
{
    public static function forCurrencies(string $cartCurrency, string $listingCurrency): self
    {
        return new self(
            (string) __('Cannot add a [:listing_currency] listing to a [:cart_currency] cart.', [
                'cart_currency' => $cartCurrency,
                'listing_currency' => $listingCurrency,
            ]),
        );
    }
}
