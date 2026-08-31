<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a seller can open their own draft listing', function (): void {
    $seller = User::factory()->verifiedSeller()->create();
    $draft = Listing::factory()
        ->draft()
        ->for($seller, 'seller')
        ->create(['title' => 'Owner-only draft asset']);

    $this->actingAs($seller)
        ->get(route('storefront.listings.show', ['slug' => $draft->slug]))
        ->assertOk()
        ->assertSee($draft->title)
        ->assertSee('You own this listing');
});

test('a draft listing returns 404 to guests and other users', function (): void {
    $seller = User::factory()->verifiedSeller()->create();
    $otherUser = User::factory()->create();
    $draft = Listing::factory()->draft()->for($seller, 'seller')->create();
    $url = route('storefront.listings.show', ['slug' => $draft->slug]);

    $this->get($url)->assertNotFound();
    $this->actingAs($otherUser)->get($url)->assertNotFound();
});

test('a published listing is visible without authentication', function (): void {
    $listing = Listing::factory()->published()->create([
        'title' => 'Publicly available asset',
    ]);

    $this->get(route('storefront.listings.show', ['slug' => $listing->slug]))
        ->assertOk()
        ->assertSee($listing->title);
});

test('a listing that leaves publication is hidden on the next Livewire request', function (): void {
    $listing = Listing::factory()->published()->create();
    $component = Livewire::test(ListingShow::class, ['slug' => $listing->slug]);

    $listing->forceFill(['offline_at' => now()->subSecond()])->save();

    expect(fn () => $component->call('$refresh'))
        ->toThrow(ModelNotFoundException::class);
});
