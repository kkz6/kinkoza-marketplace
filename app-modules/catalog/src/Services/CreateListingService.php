<?php

namespace Kinkoza\Catalog\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kinkoza\Catalog\Data\CreateListingData;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Catalog\Support\CatalogCache;

final readonly class CreateListingService
{
    public function __construct(
        private CatalogCache $cache,
    ) {}

    public function create(User $seller, CreateListingData $data): Listing
    {
        $listing = DB::transaction(function () use ($seller, $data): Listing {
            $status = $data->status;

            if (
                $status === ListingStatus::Published
                && ! $seller->is_verified_seller
            ) {
                $status = ListingStatus::PendingReview;
            }

            return Listing::query()->create([
                'seller_id' => $seller->getKey(),
                'title' => $data->title,
                'slug' => $this->uniqueSlug($data->title),
                'description' => $data->description,
                'category' => $data->category,
                'status' => $status,
                'currency' => $data->currency,
                'price_minor' => $data->priceMinor,
                'country' => $data->country,
                'city' => $data->city,
                'online_at' => $data->onlineAt,
                'offline_at' => $data->offlineAt,
                'inventory_quantity' => $data->inventoryQuantity,
                'image_url' => $data->imageUrl,
            ]);
        }, attempts: 3);

        $this->cache->invalidate();

        return $listing;
    }

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'listing';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Listing::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
