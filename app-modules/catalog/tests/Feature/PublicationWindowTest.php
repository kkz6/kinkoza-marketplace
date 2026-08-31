<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kinkoza\Catalog\Models\Listing;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('published scope returns only listings inside their public window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 12:00:00'));

    $seller = User::factory()->create();

    $published = Listing::factory()
        ->for($seller, 'seller')
        ->published()
        ->create();

    Listing::factory()->for($seller, 'seller')->scheduled()->create();
    Listing::factory()->for($seller, 'seller')->offline()->create();
    Listing::factory()->for($seller, 'seller')->draft()->create();
    Listing::factory()->for($seller, 'seller')->expired()->create();

    expect(Listing::query()->published()->pluck('id')->all())
        ->toBe([$published->id]);
});

test('owned by scope accepts a seller model or seller id', function () {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();

    $owned = Listing::factory()->for($seller, 'seller')->create();
    Listing::factory()->for($otherSeller, 'seller')->create();

    expect(Listing::query()->ownedBy($seller)->pluck('id')->all())
        ->toBe([$owned->id])
        ->and(Listing::query()->ownedBy($seller->id)->pluck('id')->all())
        ->toBe([$owned->id]);
});
