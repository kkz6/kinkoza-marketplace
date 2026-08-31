<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Converted = 'converted';
    case Abandoned = 'abandoned';
}
