<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Catalog\Policies\ListingPolicy;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('only listings in the publication window are publicly viewable', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31 12:00:00'));

    $policy = new ListingPolicy;
    $published = Listing::factory()->published()->create();
    $draft = Listing::factory()->draft()->create();
    $scheduled = Listing::factory()->scheduled()->create();
    $offline = Listing::factory()->offline()->create();

    expect($policy->view(null, $published))->toBeTrue()
        ->and($policy->view(null, $draft))->toBeFalse()
        ->and($policy->view(null, $scheduled))->toBeFalse()
        ->and($policy->view(null, $offline))->toBeFalse();
});

test('a seller can view their own non public listing', function () {
    $policy = new ListingPolicy;
    $seller = User::factory()->create();
    $otherUser = User::factory()->create();
    $draft = Listing::factory()
        ->for($seller, 'seller')
        ->draft()
        ->create();

    expect($policy->view($seller, $draft))->toBeTrue()
        ->and($policy->view($otherUser, $draft))->toBeFalse();
});

test('only the seller can update or delete a listing', function () {
    $policy = new ListingPolicy;
    $seller = User::factory()->create();
    $otherUser = User::factory()->create();
    $listing = Listing::factory()->for($seller, 'seller')->create();

    expect($policy->create($seller))->toBeTrue()
        ->and($policy->update($seller, $listing))->toBeTrue()
        ->and($policy->delete($seller, $listing))->toBeTrue()
        ->and($policy->update($otherUser, $listing))->toBeFalse()
        ->and($policy->delete($otherUser, $listing))->toBeFalse();
});

test('contact details require a verified authenticated non seller', function () {
    $policy = new ListingPolicy;
    $seller = User::factory()->create();
    $verifiedBuyer = User::factory()->create();
    $unverifiedBuyer = User::factory()->unverified()->create();
    $listing = Listing::factory()
        ->for($seller, 'seller')
        ->published()
        ->create();

    expect($policy->revealContact(null, $listing))->toBeFalse()
        ->and($policy->revealContact($seller, $listing))->toBeFalse()
        ->and($policy->revealContact($unverifiedBuyer, $listing))->toBeFalse()
        ->and($policy->revealContact($verifiedBuyer, $listing))->toBeTrue();
});
