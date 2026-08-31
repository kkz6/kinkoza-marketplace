<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use RuntimeException;

class StaleCartVersion extends RuntimeException
{
    public static function forVersions(int $expectedVersion, int $actualVersion): self
    {
        return new self("Cart version [{$expectedVersion}] is stale; current version is [{$actualVersion}].");
    }
}
