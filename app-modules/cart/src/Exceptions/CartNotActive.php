<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use DomainException;

class CartNotActive extends DomainException
{
    public static function forCart(string $cartId): self
    {
        return new self("Cart [{$cartId}] is not active.");
    }
}
