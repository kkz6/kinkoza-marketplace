<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Exceptions;

use RuntimeException;

class StaleCartVersion extends RuntimeException
{
    public static function forVersions(int $expectedVersion, int $actualVersion): self
    {
        return new self((string) __('Cart version [:expected] is stale; current version is [:actual].', [
            'actual' => $actualVersion,
            'expected' => $expectedVersion,
        ]));
    }
}
