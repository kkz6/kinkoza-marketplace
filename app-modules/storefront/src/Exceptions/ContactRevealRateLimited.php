<?php

declare(strict_types=1);

namespace Kinkoza\Storefront\Exceptions;

use RuntimeException;

final class ContactRevealRateLimited extends RuntimeException
{
    public static function forBuyer(): self
    {
        return new self((string) __('Too many contact requests. Please wait before trying again.'));
    }
}
