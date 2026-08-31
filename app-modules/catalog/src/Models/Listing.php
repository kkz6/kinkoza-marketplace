<?php

namespace Kinkoza\Catalog\Models;

use App\Models\Concerns\HasUlidAndSequence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kinkoza\Catalog\Database\Factories\ListingFactory;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;

/**
 * @property string $id
 * @property int $sequence
 * @property string $seller_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property ListingCategory $category
 * @property ListingStatus $status
 * @property Currency $currency
 * @property int $price_minor
 * @property Country $country
 * @property string $city
 * @property CarbonImmutable|null $online_at
 * @property CarbonImmutable|null $offline_at
 * @property int $inventory_quantity
 * @property string|null $image_url
 * @property int $version
 * @property-read User $seller
 */
#[Guarded(['id', 'sequence', 'seller_id', 'slug', 'status', 'version'])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUlidAndSequence;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ListingCategory::class,
            'status' => ListingStatus::class,
            'currency' => Currency::class,
            'country' => Country::class,
            'price_minor' => 'integer',
            'online_at' => 'immutable_datetime',
            'offline_at' => 'immutable_datetime',
            'inventory_quantity' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * Get the seller that owns the listing.
     *
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status !== ListingStatus::Published) {
            return false;
        }

        if (! $this->online_at || $this->online_at->isFuture()) {
            return false;
        }

        return ! $this->offline_at || $this->offline_at->isFuture();
    }

    /** @param Builder<Listing> $query */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query
            ->where('status', ListingStatus::Published->value)
            ->where('online_at', '<=', now())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('offline_at')
                    ->orWhere('offline_at', '>', now());
            });
    }

    /** @param Builder<Listing> $query */
    #[Scope]
    protected function ownedBy(Builder $query, User|string $seller): void
    {
        $sellerId = $seller instanceof User ? $seller->getKey() : $seller;

        $query->where('seller_id', $sellerId);
    }

    protected static function newFactory(): ListingFactory
    {
        return ListingFactory::new();
    }
}
