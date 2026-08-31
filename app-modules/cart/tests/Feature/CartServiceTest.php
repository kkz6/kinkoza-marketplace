<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Exceptions\InsufficientInventory;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Catalog\Models\Listing;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->cartService = app(CartServiceInterface::class);
    $this->guestToken = strtolower((string) Str::ulid());
});

test('adding a listing twice increments one cart item', function (): void {
    $listing = Listing::factory()->create([
        'price_minor' => 2_500,
        'currency' => 'EUR',
        'inventory_quantity' => 10,
    ]);

    $cart = $this->cartService->add($listing, 2, null, $this->guestToken);
    $cart = $this->cartService->add($listing, 1, null, $this->guestToken, $cart->version);

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

    $cart = $this->cartService->add($firstListing, 2, null, $this->guestToken);
    $cart = $this->cartService->add($secondListing, 3, null, $this->guestToken, $cart->version);

    expect($cart->subtotal_minor)->toBe(3_100)
        ->and($cart->total_minor)->toBe(3_100)
        ->and($cart->items)->toHaveCount(2);
});

test('quantity cannot exceed locked listing inventory', function (): void {
    $listing = Listing::factory()->create([
        'inventory_quantity' => 2,
    ]);

    expect(fn () => $this->cartService->add($listing, 3, null, $this->guestToken))
        ->toThrow(InsufficientInventory::class);

    expect(Cart::query()->count())->toBe(0);
});

test('stale cart versions are rejected without changing an item', function (): void {
    $listing = Listing::factory()->create([
        'inventory_quantity' => 10,
    ]);
    $cart = $this->cartService->add($listing, 1, null, $this->guestToken);
    $item = $cart->items->sole();

    expect(fn () => $this->cartService->updateQuantity($cart, $item->id, 2, 1))
        ->toThrow(StaleCartVersion::class);

    $cart->refresh()->load('items');

    expect($cart->version)->toBe(2)
        ->and($cart->items->sole()->quantity)->toBe(1);
});

test('buyer and guest identities receive isolated active carts', function (): void {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $otherGuestToken = strtolower((string) Str::ulid());

    $buyerCart = $this->cartService->getOrCreateFor($buyer, $this->guestToken);
    $sameBuyerCart = $this->cartService->getOrCreateFor($buyer, $otherGuestToken);
    $otherBuyerCart = $this->cartService->getOrCreateFor($otherBuyer, $this->guestToken);
    $guestCart = $this->cartService->getOrCreateFor(null, $this->guestToken);
    $sameGuestCart = $this->cartService->getOrCreateFor(null, $this->guestToken);
    $otherGuestCart = $this->cartService->getOrCreateFor(null, $otherGuestToken);

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

test('quantity updates and removal recompute totals and versions', function (): void {
    $listing = Listing::factory()->create([
        'price_minor' => 750,
        'currency' => 'EUR',
        'inventory_quantity' => 10,
    ]);
    $cart = $this->cartService->add($listing, 1, null, $this->guestToken);
    $item = $cart->items->sole();

    $cart = $this->cartService->updateQuantity($cart, $item->id, 4, $cart->version);

    expect($cart->subtotal_minor)->toBe(3_000)
        ->and($cart->total_minor)->toBe(3_000)
        ->and($cart->version)->toBe(3);

    $cart = $this->cartService->remove($cart, $item->id, $cart->version);

    expect($cart->items)->toBeEmpty()
        ->and($cart->subtotal_minor)->toBe(0)
        ->and($cart->total_minor)->toBe(0)
        ->and($cart->version)->toBe(4);
});

test('unpublished listings cannot be added', function (): void {
    $listing = Listing::factory()->draft()->create();

    expect(fn () => $this->cartService->add($listing, 1, null, $this->guestToken))
        ->toThrow(ListingUnavailable::class);
});
