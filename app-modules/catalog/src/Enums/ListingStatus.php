<?php

namespace Kinkoza\Catalog\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending-review';
    case Published = 'published';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Published => 'Published',
            self::Expired => 'Expired',
        };
    }
}
