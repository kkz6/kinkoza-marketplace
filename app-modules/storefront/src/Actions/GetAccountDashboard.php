<?php

declare(strict_types=1);

namespace Kinkoza\Storefront\Actions;

use App\Models\User;
use Kinkoza\Cart\Actions\GetCartItemCount;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Models\OrderItem;
use Kinkoza\Storefront\Data\AccountDashboardData;
use Lorisleiva\Actions\Concerns\AsAction;
use UnexpectedValueException;

class GetAccountDashboard
{
    use AsAction;

    public function handle(User $user, string $guestToken): AccountDashboardData
    {
        $listings = Listing::query()->where('seller_id', $user->id);
        $orders = Order::query()->where('buyer_id', $user->id);
        $sales = OrderItem::query()->where('seller_id', $user->id);

        return new AccountDashboardData(
            activeListingCount: (clone $listings)->published()->count(),
            totalListingCount: (clone $listings)->count(),
            pendingListingCount: (clone $listings)
                ->where('status', ListingStatus::PendingReview->value)
                ->count(),
            cartItemCount: GetCartItemCount::make()->handle($user, $guestToken),
            purchaseCount: (clone $orders)->count(),
            salesOrderCount: (clone $sales)->distinct()->count('order_id'),
            unitsSold: $this->integerAggregate((clone $sales)->sum('quantity')),
            hasBusinessProfile: $this->hasBusinessProfile($user),
            isVerifiedSeller: $user->getAttribute('is_verified_seller') === true,
            recentListings: (clone $listings)
                ->latest()
                ->limit(5)
                ->get(),
            recentOrders: (clone $orders)
                ->latest('placed_at')
                ->limit(5)
                ->get(),
        );
    }

    private function hasBusinessProfile(User $user): bool
    {
        return filled($user->getAttribute('company_name'))
            && filled($user->getAttribute('registration_number'))
            && filled($user->getAttribute('phone'))
            && filled($user->getAttribute('country'));
    }

    private function integerAggregate(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException('Dashboard aggregate must be a non-negative integer.');
    }
}
