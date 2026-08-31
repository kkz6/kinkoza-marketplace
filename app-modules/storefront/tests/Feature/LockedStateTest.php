<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Contracts\Services\CheckoutServiceInterface;
use Kinkoza\Storefront\Http\Livewire\CartShow;
use Kinkoza\Storefront\Http\Livewire\CheckoutShow;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Kinkoza\Storefront\Http\Livewire\OrderConfirmation;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a listing identifier cannot be replaced by the client', function (): void {
    $listing = Listing::factory()->published()->create();
    $component = Livewire::test(ListingShow::class, ['slug' => $listing->slug]);

    expect(fn () => $component->set('listingId', (string) Str::ulid()))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('a cart identifier cannot be replaced by the client', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->published()->create();
    $cart = resolve(CartServiceInterface::class)->add(
        $listing,
        1,
        $buyer,
        (string) Str::ulid(),
    );
    $component = Livewire::actingAs($buyer)->test(CartShow::class);

    expect($component->get('cartId'))->toBe($cart->id)
        ->and(fn () => $component->set('cartId', (string) Str::ulid()))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('checkout identity and concurrency tokens cannot be replaced by the client', function (string $property, mixed $value): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->published()->create();
    resolve(CartServiceInterface::class)->add(
        $listing,
        1,
        $buyer,
        (string) Str::ulid(),
    );
    $component = Livewire::actingAs($buyer)->test(CheckoutShow::class);

    expect(fn () => $component->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'cart identifier' => ['cartId', '01m1ba00000000000000000000'],
    'reviewed cart version' => ['cartVersion', 999],
    'idempotency key' => ['idempotencyKey', 'forged-checkout-key'],
]);

test('an order identifier cannot be replaced by the client', function (): void {
    $buyer = User::factory()->create();
    $seller = User::factory()->verifiedSeller()->create();
    $listing = Listing::factory()
        ->published()
        ->for($seller, 'seller')
        ->create();
    $cart = resolve(CartServiceInterface::class)->add(
        $listing,
        1,
        $buyer,
        (string) Str::ulid(),
    );
    $order = resolve(CheckoutServiceInterface::class)->checkout(
        $cart,
        $buyer,
        (string) Str::ulid(),
        $cart->version,
    );
    $component = Livewire::actingAs($buyer)
        ->test(OrderConfirmation::class, ['order' => $order->id]);

    expect(fn () => $component->set('orderId', (string) Str::ulid()))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
