<?php

namespace Kinkoza\Catalog\Models;

use App\Models\Concerns\HasUlidAndSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

#[Fillable([
    'seller_id',
    'title',
    'slug',
    'description',
    'category',
    'status',
    'currency',
    'price_minor',
    'country',
    'city',
    'online_at',
    'offline_at',
    'inventory_quantity',
    'image_url',
    'version',
])]
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
