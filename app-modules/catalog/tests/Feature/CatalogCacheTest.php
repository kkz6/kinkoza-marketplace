<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Catalog\Support\CatalogCache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('featured listings use a versioned flexible cache', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 12:00:00'));

    $first = Listing::factory()->published()->create([
        'online_at' => now()->subDays(2),
    ]);

    Listing::factory()->scheduled()->create();
    Listing::factory()->published()->outOfStock()->create();

    $cache = app(CatalogCache::class);
    $initialVersion = $cache->version();

    expect($cache->featured()->pluck('id')->all())->toBe([$first->id]);

    $newest = Listing::factory()->published()->create([
        'online_at' => now()->subDay(),
    ]);

    expect($cache->featured()->pluck('id')->all())
        ->toBe([$first->id]);

    $cache->invalidate();

    expect($cache->version())->toBe($initialVersion + 1)
        ->and($cache->featured()->pluck('id')->all())
        ->toBe([$newest->id, $first->id]);
});
