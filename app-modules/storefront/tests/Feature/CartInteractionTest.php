<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Models\CartItem;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Http\Livewire\CartShow;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a guest can add a published listing through the detail UI', function (): void {
    $listing = Listing::factory()->published()->create([
        'title' => 'Compact Laser Cutting Cell',
        'price_minor' => 425_000,
        'inventory_quantity' => 4,
    ]);

    Livewire::test(ListingShow::class, ['slug' => $listing->slug])
        ->set('quantity', 2)
        ->call('addToCart')
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated', count: 2);

    $cart = Cart::query()->with('items')->sole();
    $item = $cart->items->sole();

    expect($cart->buyer_id)->toBeNull()
        ->and(Str::isUlid((string) $cart->guest_token))->toBeTrue()
        ->and($cart->subtotal_minor)->toBe(850_000)
        ->and($item->listing_id)->toBe($listing->id)
        ->and($item->quantity)->toBe(2)
        ->and($item->title)->toBe($listing->title);
});

test('cart UI updates quantity and removes an item using the current version', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->published()->create([
        'title' => 'Warehouse Pallet Wrapper',
        'currency' => Currency::EUR,
        'price_minor' => 2_500,
        'inventory_quantity' => 8,
    ]);
    $cart = resolve(CartServiceInterface::class)->add(
        $listing,
        1,
        $buyer,
        (string) Str::ulid(),
    );
    $item = $cart->items->sole();

    $component = Livewire::actingAs($buyer)
        ->test(CartShow::class)
        ->assertSet('cartId', $cart->id)
        ->assertSee($listing->title)
        ->assertSee('€25.00');

    $component
        ->call('updateQuantity', $item->id, 3, $cart->version)
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated');

    $updatedCart = $cart->fresh('items');

    expect($updatedCart->subtotal_minor)->toBe(7_500)
        ->and($updatedCart->items->sole()->quantity)->toBe(3);

    $component
        ->call('remove', $item->id, $updatedCart->version)
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated')
        ->assertSee('Your cart is empty');

    expect(CartItem::query()->count())->toBe(0)
        ->and($cart->fresh()->total_minor)->toBe(0);
});

test('unexpected cart failures never expose infrastructure details', function (): void {
    $listing = Listing::factory()->published()->create();
    $service = $this->mock(CartServiceInterface::class);
    $service->shouldReceive('add')
        ->once()
        ->andThrow(new RuntimeException('SQLSTATE secret connection details'));

    Livewire::test(ListingShow::class, ['slug' => $listing->slug])
        ->call('addToCart')
        ->assertHasErrors(['quantity'])
        ->assertSee('We could not add this asset. Please try again.')
        ->assertDontSee('SQLSTATE secret connection details');
});
