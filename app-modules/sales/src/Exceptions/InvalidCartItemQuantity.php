<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Exceptions;

use DomainException;

final class InvalidCartItemQuantity extends DomainException
{
    public static function forItem(string $cartItemId): self
    {
        return new self((string) __('Cart item [:item] has an invalid quantity.', [
            'item' => $cartItemId,
        ]));
    }
}
