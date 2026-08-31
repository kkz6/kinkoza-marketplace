<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Http\Livewire\ListingsIndex;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 12:00:00'));
});

test('public search shows matching published listings and hides non public records', function (): void {
    $seller = User::factory()->verifiedSeller()->create();

    $matching = Listing::factory()->published()->for($seller, 'seller')->create([
        'title' => 'Precision CNC Machining Centre',
    ]);
    $other = Listing::factory()->published()->for($seller, 'seller')->create([
        'title' => 'Regional Refrigerated Fleet',
    ]);

    Listing::factory()->draft()->for($seller, 'seller')->create([
        'title' => 'Draft CNC Machine',
    ]);
    Listing::factory()->scheduled()->for($seller, 'seller')->create([
        'title' => 'Scheduled CNC Machine',
    ]);
    Listing::factory()->offline()->for($seller, 'seller')->create([
        'title' => 'Offline CNC Machine',
    ]);

    Livewire::test(ListingsIndex::class)
        ->assertSee($matching->title)
        ->assertSee($other->title)
        ->assertDontSee('Draft CNC Machine')
        ->assertDontSee('Scheduled CNC Machine')
        ->assertDontSee('Offline CNC Machine')
        ->set('search', 'CNC')
        ->assertSee($matching->title)
        ->assertDontSee($other->title)
        ->assertDontSee('Draft CNC Machine');
});

test('category country and price filters compose at the public boundary', function (): void {
    $seller = User::factory()->verifiedSeller()->create();

    $match = Listing::factory()->published()->for($seller, 'seller')->create([
        'title' => 'French Milling Cell',
        'category' => ListingCategory::MachineryEquipment,
        'country' => Country::France,
        'price_minor' => 150_000,
    ]);
    $tooExpensive = Listing::factory()->published()->for($seller, 'seller')->create([
        'title' => 'Belgian Milling Cell',
        'category' => ListingCategory::MachineryEquipment,
        'country' => Country::Belgium,
        'price_minor' => 350_000,
    ]);
    $wrongCategory = Listing::factory()->published()->for($seller, 'seller')->create([
        'title' => 'French Retail Unit',
        'category' => ListingCategory::CommercialProperty,
        'country' => Country::France,
        'price_minor' => 180_000,
    ]);

    Livewire::test(ListingsIndex::class)
        ->set('category', ListingCategory::MachineryEquipment->value)
        ->set('country', Country::France->value)
        ->set('minimumPrice', '1000.00')
        ->set('maximumPrice', '2000,00')
        ->assertSee($match->title)
        ->assertDontSee($tooExpensive->title)
        ->assertDontSee($wrongCategory->title);
});
