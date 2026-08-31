<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Http\Livewire\CreateListing;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a publication request from a non KYB seller is downgraded to review', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 12:00:00'));

    $seller = User::factory()->create([
        'is_verified_seller' => false,
    ]);

    $component = Livewire::actingAs($seller)
        ->test(CreateListing::class)
        ->set('form.title', 'Automated Bottling Production Line')
        ->set('form.description', 'A complete maintained bottling line with service records and collection support.')
        ->set('form.category', ListingCategory::MachineryEquipment->value)
        ->set('form.price', '125000.50')
        ->set('form.currency', Currency::EUR->value)
        ->set('form.country', Country::France->value)
        ->set('form.city', 'Lyon')
        ->set('form.onlineAt', '2026-08-31T13:00')
        ->set('form.offlineAt', '')
        ->set('form.inventoryQuantity', 2)
        ->set('form.imageUrl', '')
        ->set('form.publish', true)
        ->call('save')
        ->assertHasNoErrors();

    $listing = Listing::query()->sole();

    expect($listing->seller_id)->toBe($seller->id)
        ->and($listing->status)->toBe(ListingStatus::PendingReview)
        ->and($listing->price_minor)->toBe(12_500_050)
        ->and($listing->inventory_quantity)->toBe(2)
        ->and($listing->image_url)->toBeNull();

    $component->assertRedirect(route('storefront.listings.show', [
        'slug' => $listing->slug,
    ]));
});
