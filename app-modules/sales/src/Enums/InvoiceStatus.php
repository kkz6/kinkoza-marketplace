<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
}
