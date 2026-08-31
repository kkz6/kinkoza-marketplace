<?php

namespace Kinkoza\Catalog\Policies;

use App\Models\User;
use Kinkoza\Catalog\Models\Listing;

class ListingPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Listing $listing): bool
    {
        if ($user?->getKey() === $listing->seller_id) {
            return true;
        }

        return $listing->isPubliclyVisible();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->getKey() === $listing->seller_id;
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $user->getKey() === $listing->seller_id;
    }

    public function revealContact(?User $user, Listing $listing): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->getKey() === $listing->seller_id) {
            return false;
        }

        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $listing->isPubliclyVisible();
    }
}
