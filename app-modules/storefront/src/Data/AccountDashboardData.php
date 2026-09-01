<?php

declare(strict_types=1);

namespace Kinkoza\Storefront\Data;

use Illuminate\Database\Eloquent\Collection;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Models\Order;

final readonly class AccountDashboardData
{
    /**
     * @param  Collection<int, Listing>  $recentListings
     * @param  Collection<int, Order>  $recentOrders
     */
    public function __construct(
        public int $activeListingCount,
        public int $totalListingCount,
        public int $pendingListingCount,
        public int $cartItemCount,
        public int $purchaseCount,
        public int $salesOrderCount,
        public int $unitsSold,
        public bool $hasBusinessProfile,
        public bool $isVerifiedSeller,
        public Collection $recentListings,
        public Collection $recentOrders,
    ) {}
}
