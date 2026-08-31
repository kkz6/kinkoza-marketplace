<?php

namespace Kinkoza\Catalog\Data;

use Carbon\CarbonImmutable;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;

final readonly class CreateListingData
{
    public function __construct(
        public string $title,
        public string $description,
        public ListingCategory $category,
        public ListingStatus $status,
        public Currency $currency,
        public int $priceMinor,
        public Country $country,
        public string $city,
        public CarbonImmutable $onlineAt,
        public ?CarbonImmutable $offlineAt = null,
        public int $inventoryQuantity = 1,
        public ?string $imageUrl = null,
    ) {}
}
