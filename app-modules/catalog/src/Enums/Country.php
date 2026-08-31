<?php

namespace Kinkoza\Catalog\Enums;

enum Country: string
{
    case France = 'FR';
    case Belgium = 'BE';
    case Luxembourg = 'LU';

    public function label(): string
    {
        return match ($this) {
            self::France => 'France',
            self::Belgium => 'Belgium',
            self::Luxembourg => 'Luxembourg',
        };
    }
}
