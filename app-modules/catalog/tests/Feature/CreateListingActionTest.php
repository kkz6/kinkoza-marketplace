<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Kinkoza\Catalog\Actions\CreateListing;
use Kinkoza\Catalog\Data\CreateListingData;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Support\CatalogCache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('a verified seller can publish a listing with a unique slug', function () {
    $seller = User::factory()->verifiedSeller()->create();
    $cache = app(CatalogCache::class);
    $version = $cache->version();

    $listing = CreateListing::run($seller, new CreateListingData(
        title: 'Five Axis CNC Machine',
        description: 'A maintained production machine with service history.',
        category: ListingCategory::MachineryEquipment,
        status: ListingStatus::Published,
        currency: Currency::EUR,
        priceMinor: 12_500_000,
        country: Country::France,
        city: 'Lyon',
        onlineAt: CarbonImmutable::parse('2026-08-31 10:00:00'),
    ));

    expect($listing->slug)->toBe('five-axis-cnc-machine-1')
        ->and($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->currency)->toBe(Currency::EUR)
        ->and($listing->price_minor)->toBe(12_500_000)
        ->and($listing->seller->is($seller))->toBeTrue()
        ->and($listing->image_url)->toBeNull()
        ->and($cache->version())->toBe($version + 1);

    $duplicate = CreateListing::run($seller, new CreateListingData(
        title: 'Five Axis CNC Machine',
        description: 'A second machine from the same production site.',
        category: ListingCategory::MachineryEquipment,
        status: ListingStatus::Draft,
        currency: Currency::EUR,
        priceMinor: 11_750_000,
        country: Country::France,
        city: 'Lyon',
        onlineAt: CarbonImmutable::parse('2026-09-01 10:00:00'),
    ));

    expect($duplicate->slug)->toBe('five-axis-cnc-machine-2');
});

test('an unverified seller publication request is held for review', function () {
    $seller = User::factory()->create([
        'is_verified_seller' => false,
    ]);

    $listing = CreateListing::run(
        $seller,
        new CreateListingData(
            title: 'Regional Delivery Fleet',
            description: 'Ten maintained delivery vehicles ready for transfer.',
            category: ListingCategory::VehiclesFleet,
            status: ListingStatus::Published,
            currency: Currency::GBP,
            priceMinor: 8_400_000,
            country: Country::Belgium,
            city: 'Brussels',
            onlineAt: CarbonImmutable::parse('2026-08-31 10:00:00'),
        ),
    );

    expect($listing->status)->toBe(ListingStatus::PendingReview);
});

test('currency exposes stable formatting metadata', function () {
    expect(Currency::EUR->label())->toBe('Euro')
        ->and(Currency::EUR->formattingMetadata())->toBe([
            'symbol' => '€',
            'decimal_places' => 2,
            'symbol_position' => 'before',
        ])
        ->and(Currency::GBP->format(123_456))->toBe('£1,234.56');
});
