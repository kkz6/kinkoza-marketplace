<?php

declare(strict_types=1);

namespace Kinkoza\Storefront\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Exceptions\ContactRevealRateLimited;
use Lorisleiva\Actions\Concerns\AsAction;

class RevealSellerContact
{
    use AsAction;

    public function handle(User $buyer, Listing $listing, ?string $ipAddress): void
    {
        Gate::forUser($buyer)->authorize('revealContact', $listing);

        $key = "contact-reveal:{$buyer->getAuthIdentifier()}";

        if (! RateLimiter::attempt($key, 5, static fn (): bool => true, 60)) {
            throw ContactRevealRateLimited::forBuyer();
        }

        Log::notice('Seller contact details revealed.', [
            'buyer_id' => $buyer->getAuthIdentifier(),
            'listing_id' => $listing->getKey(),
            'ip' => $ipAddress,
        ]);
    }
}
