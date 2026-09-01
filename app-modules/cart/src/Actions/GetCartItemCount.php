<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Models\CartItem;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCartItemCount
{
    use AsAction;

    public function handle(?User $buyer, string $guestToken): int
    {
        $normalizedGuestToken = strtolower(trim($guestToken));

        if (! $buyer && ! Str::isUlid($normalizedGuestToken)) {
            throw new InvalidArgumentException((string) __('A valid guest ULID is required when no buyer is present.'));
        }

        $buyerId = $buyer?->getKey();

        return (int) CartItem::query()
            ->whereHas('cart', function (Builder $query) use ($buyerId, $normalizedGuestToken): void {
                $query->where('status', CartStatus::Active->value);

                if ($buyerId !== null) {
                    $query->where('buyer_id', $buyerId);

                    return;
                }

                $query
                    ->whereNull('buyer_id')
                    ->where('guest_token', $normalizedGuestToken);
            })
            ->sum('quantity');
    }
}
