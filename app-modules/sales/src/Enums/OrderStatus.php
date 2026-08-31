<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
