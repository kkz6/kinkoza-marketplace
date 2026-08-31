<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Actions\AddListingToCart;
use Kinkoza\Cart\Actions\GetOrCreateCart;
use Kinkoza\Cart\Actions\RemoveCartItem;
use Kinkoza\Cart\Actions\UpdateCartItemQuantity;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Exceptions\InsufficientInventory;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Exceptions\SelfPurchaseNotAllowed;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Catalog\Models\Listing;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->guestToken = strtolower((string) Str::ulid());
});

test('the add action increments one cart item when a listing is added twice', function (): void {
    $listing = Listing::factory()->create([
        'price_minor' => 2_500,
        'currency' => 'EUR',
        'inventory_quantity' => 10,
    ]);

    $cart = AddListingToCart::run($listing, 2, null, $this->guestToken);
    $cart = AddListingToCart::run($listing, 1, null, $this->guestToken, $cart->version);

    expect($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->quantity)->toBe(3)
        ->and($cart->items->first()->unit_price_minor)->toBe(2_500)
        ->and($cart->items->first()->line_total_minor)->toBe(7_500)
        ->and($cart->version)->toBe(3);
});

test('totals are recomputed from item snapshot values', function (): void {
    $firstListing = Listing::factory()->create([
        'price_minor' => 1_250,
        'currency' => 'EUR',
        'inventory_quantity' => 10,
    ]);
    $secondListing = Listing::factory()->create([
        'price_minor' => 200,
        'currency' => 'EUR',
        'inventory_quantity' => 10,
    ]);

    $cart = AddListingToCart::run($firstListing, 2, null, $this->guestToken);
    $cart = AddListingToCart::run($secondListing, 3, null, $this->guestToken, $cart->version);

    expect($cart->subtotal_minor)->toBe(3_100)
        ->and($cart->total_minor)->toBe(3_100)
        ->and($cart->items)->toHaveCount(2);
});

test('quantity cannot exceed locked listing inventory', function (): void {
    $listing = Listing::factory()->create([
        'inventory_quantity' => 2,
    ]);

    expect(fn () => AddListingToCart::run($listing, 3, null, $this->guestToken))
        ->toThrow(InsufficientInventory::class);

    expect(Cart::query()->count())->toBe(0);
});

test('an authenticated seller cannot add their own listing', function (): void {
    $seller = User::factory()->verifiedSeller()->create();
    $listing = Listing::factory()
        ->for($seller, 'seller')
        ->create();

    expect(fn () => AddListingToCart::run($listing, 1, $seller, $this->guestToken))
        ->toThrow(SelfPurchaseNotAllowed::class, 'You cannot purchase your own listing.');

    expect(Cart::query()->count())->toBe(0);
});

test('stale cart versions are rejected without changing an item', function (): void {
    $listing = Listing::factory()->create([
        'inventory_quantity' => 10,
    ]);
    $cart = AddListingToCart::run($listing, 1, null, $this->guestToken);
    $item = $cart->items->sole();

    expect(fn () => UpdateCartItemQuantity::run($cart, $item->id, 2, 1))
        ->toThrow(StaleCartVersion::class);

    $cart->refresh()->load('items');

    expect($cart->version)->toBe(2)
        ->and($cart->items->sole()->quantity)->toBe(1);
});

test('buyer and guest identities receive isolated active carts', function (): void {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $otherGuestToken = strtolower((string) Str::ulid());

    $buyerCart = GetOrCreateCart::run($buyer, $this->guestToken);
    $sameBuyerCart = GetOrCreateCart::run($buyer, $otherGuestToken);
    $otherBuyerCart = GetOrCreateCart::run($otherBuyer, $this->guestToken);
    $guestCart = GetOrCreateCart::run(null, $this->guestToken);
    $sameGuestCart = GetOrCreateCart::run(null, $this->guestToken);
    $otherGuestCart = GetOrCreateCart::run(null, $otherGuestToken);

    expect($sameBuyerCart->is($buyerCart))->toBeTrue()
        ->and($buyerCart->buyer_id)->toBe($buyer->id)
        ->and($buyerCart->guest_token)->toBeNull()
        ->and($otherBuyerCart->isNot($buyerCart))->toBeTrue()
        ->and($sameGuestCart->is($guestCart))->toBeTrue()
        ->and($guestCart->buyer_id)->toBeNull()
        ->and($guestCart->guest_token)->toBe($this->guestToken)
        ->and($otherGuestCart->isNot($guestCart))->toBeTrue()
        ->and($guestCart->isNot($buyerCart))->toBeTrue();

    expect(Cart::query()->whereNotNull('active_key')->count())->toBe(4);
});

test('a guest cart is adopted when the guest signs in', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create([
        'currency' => 'EUR',
        'price_minor' => 4_500,
        'inventory_quantity' => 5,
    ]);
    $guestCart = AddListingToCart::run($listing, 2, null, $this->guestToken);

    $claimedCart = GetOrCreateCart::run($buyer, $this->guestToken);

    expect($claimedCart->is($guestCart))->toBeTrue()
        ->and($claimedCart->buyer_id)->toBe($buyer->id)
        ->and($claimedCart->guest_token)->toBeNull()
        ->and($claimedCart->active_key)->toBe("buyer:{$buyer->id}")
        ->and($claimedCart->items)->toHaveCount(1)
        ->and($claimedCart->items->sole()->quantity)->toBe(2);
});

test('guest items merge into an existing buyer cart', function (): void {
    $buyer = User::factory()->create();
    $firstListing = Listing::factory()->create([
        'currency' => 'EUR',
        'price_minor' => 1_000,
        'inventory_quantity' => 10,
    ]);
    $secondListing = Listing::factory()->create([
        'currency' => 'EUR',
        'price_minor' => 2_000,
        'inventory_quantity' => 10,
    ]);
    $buyerCart = AddListingToCart::run($firstListing, 1, $buyer, (string) Str::ulid());
    $guestCart = AddListingToCart::run($firstListing, 2, null, $this->guestToken);
    $guestCart = AddListingToCart::run($secondListing, 1, null, $this->guestToken, $guestCart->version);

    $mergedCart = GetOrCreateCart::run($buyer, $this->guestToken);

    expect($mergedCart->is($buyerCart))->toBeTrue()
        ->and($mergedCart->items)->toHaveCount(2)
        ->and($mergedCart->items->firstWhere('listing_id', $firstListing->id)?->quantity)->toBe(3)
        ->and($mergedCart->total_minor)->toBe(5_000)
        ->and($guestCart->fresh()->status)->toBe(CartStatus::Abandoned)
        ->and($guestCart->fresh()->active_key)->toBeNull();
});

test('different currency carts remain intact when a guest signs in', function (): void {
    $buyer = User::factory()->create();
    $euroListing = Listing::factory()->create([
        'currency' => 'EUR',
        'price_minor' => 1_000,
        'inventory_quantity' => 10,
    ]);
    $sterlingListing = Listing::factory()->create([
        'currency' => 'GBP',
        'price_minor' => 2_000,
        'inventory_quantity' => 10,
    ]);
    $buyerCart = AddListingToCart::run($euroListing, 1, $buyer, (string) Str::ulid());
    $guestCart = AddListingToCart::run($sterlingListing, 2, null, $this->guestToken);

    $restoredCart = GetOrCreateCart::run($buyer, $this->guestToken);

    expect($restoredCart->is($buyerCart))->toBeTrue()
        ->and($buyerCart->fresh()->status)->toBe(CartStatus::Active)
        ->and($buyerCart->fresh()->items)->toHaveCount(1)
        ->and($guestCart->fresh()->status)->toBe(CartStatus::Active)
        ->and($guestCart->fresh()->items)->toHaveCount(1);
});

test('an overstocked guest merge leaves both carts intact', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create([
        'currency' => 'EUR',
        'price_minor' => 1_000,
        'inventory_quantity' => 3,
    ]);
    $buyerCart = AddListingToCart::run($listing, 2, $buyer, (string) Str::ulid());
    $guestCart = AddListingToCart::run($listing, 2, null, $this->guestToken);

    $restoredCart = GetOrCreateCart::run($buyer, $this->guestToken);

    expect($restoredCart->is($buyerCart))->toBeTrue()
        ->and($buyerCart->fresh()->items->sole()->quantity)->toBe(2)
        ->and($guestCart->fresh()->status)->toBe(CartStatus::Active)
        ->and($guestCart->fresh()->items->sole()->quantity)->toBe(2);
});

test('quantity updates and removal recompute totals and versions', function (): void {
    $listing = Listing::factory()->create([
        'price_minor' => 750,
        'currency' => 'EUR',
        'inventory_quantity' => 10,
    ]);
    $cart = AddListingToCart::run($listing, 1, null, $this->guestToken);
    $item = $cart->items->sole();

    $cart = UpdateCartItemQuantity::run($cart, $item->id, 4, $cart->version);

    expect($cart->subtotal_minor)->toBe(3_000)
        ->and($cart->total_minor)->toBe(3_000)
        ->and($cart->version)->toBe(3);

    $cart = RemoveCartItem::run($cart, $item->id, $cart->version);

    expect($cart->items)->toBeEmpty()
        ->and($cart->subtotal_minor)->toBe(0)
        ->and($cart->total_minor)->toBe(0)
        ->and($cart->version)->toBe(4);
});

test('unpublished listings cannot be added', function (): void {
    $listing = Listing::factory()->draft()->create();

    expect(fn () => AddListingToCart::run($listing, 1, null, $this->guestToken))
        ->toThrow(ListingUnavailable::class);
});
