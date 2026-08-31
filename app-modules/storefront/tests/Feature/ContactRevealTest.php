<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Actions\RevealSellerContact;
use Kinkoza\Storefront\Exceptions\ContactRevealRateLimited;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('contact-reveal:test');
});

test('a guest is redirected to login before contact details are revealed', function (): void {
    $listing = Listing::factory()->published()->create();

    Livewire::test(ListingShow::class, ['slug' => $listing->slug])
        ->call('revealContact')
        ->assertSet('contactRevealed', false)
        ->assertRedirect(route('login'));
});

test('an email-unverified user cannot reveal contact details', function (): void {
    $buyer = User::factory()->unverified()->create();
    $listing = Listing::factory()->published()->create();

    Livewire::actingAs($buyer)
        ->test(ListingShow::class, ['slug' => $listing->slug])
        ->call('revealContact')
        ->assertForbidden();
});

test('a seller cannot use contact reveal on their own listing', function (): void {
    $seller = User::factory()->verifiedSeller()->create();
    $listing = Listing::factory()
        ->published()
        ->for($seller, 'seller')
        ->create();

    Livewire::actingAs($seller)
        ->test(ListingShow::class, ['slug' => $listing->slug])
        ->call('revealContact')
        ->assertForbidden();
});

test('a verified non seller can reveal the protected seller contact', function (): void {
    $seller = User::factory()->verifiedSeller()->create([
        'email' => 'asset-owner@example.com',
        'phone' => '+33123456789',
    ]);
    $buyer = User::factory()->create();
    $listing = Listing::factory()
        ->published()
        ->for($seller, 'seller')
        ->create();

    RateLimiter::clear("contact-reveal:{$buyer->id}");

    Livewire::actingAs($buyer)
        ->test(ListingShow::class, ['slug' => $listing->slug])
        ->assertDontSee($seller->email)
        ->call('revealContact')
        ->assertHasNoErrors()
        ->assertSet('contactRevealed', true)
        ->assertSee($seller->email)
        ->assertSee($seller->phone);
});

test('the contact reveal action rate limits a buyer with a localized error', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->published()->create();

    RateLimiter::clear("contact-reveal:{$buyer->id}");
    App::setLocale('fr');

    foreach (range(1, 5) as $attempt) {
        RevealSellerContact::run($buyer, $listing, '127.0.0.1');
    }

    expect(fn () => RevealSellerContact::run($buyer, $listing, '127.0.0.1'))
        ->toThrow(
            ContactRevealRateLimited::class,
            'Trop de demandes de coordonnées. Veuillez patienter avant de réessayer.',
        );
});

test('contact reveal state cannot be written by the client to expose seller details', function (): void {
    $seller = User::factory()->verifiedSeller()->create([
        'email' => 'private-owner@example.com',
        'phone' => '+35226123456',
    ]);
    $buyer = User::factory()->create();
    $listing = Listing::factory()
        ->published()
        ->for($seller, 'seller')
        ->create();

    $component = Livewire::actingAs($buyer)
        ->test(ListingShow::class, ['slug' => $listing->slug])
        ->assertDontSee($seller->email)
        ->assertDontSee($seller->phone);

    $loadedListing = $component->get('listing');

    expect($loadedListing)->toBeInstanceOf(Listing::class)
        ->and(array_key_exists('email', $loadedListing->seller->getAttributes()))->toBeFalse()
        ->and(array_key_exists('phone', $loadedListing->seller->getAttributes()))->toBeFalse();

    expect(fn () => $component->set('contactRevealed', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $component
        ->assertSet('contactRevealed', false)
        ->assertDontSee($seller->email)
        ->assertDontSee($seller->phone);
});
