<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Contracts\Services\CheckoutServiceInterface;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Storefront\Http\Livewire\CheckoutShow;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('checkout UI creates an order and invoice for the authenticated cart owner', function (): void {
    $buyer = User::factory()->create();
    $seller = User::factory()->verifiedSeller()->create();
    $listing = Listing::factory()
        ->published()
        ->for($seller, 'seller')
        ->create([
            'title' => 'Industrial Air Compressor',
            'price_minor' => 62_500,
            'inventory_quantity' => 5,
        ]);
    $cart = resolve(CartServiceInterface::class)->add(
        $listing,
        2,
        $buyer,
        (string) Str::ulid(),
    );

    $component = Livewire::actingAs($buyer)
        ->test(CheckoutShow::class)
        ->assertSet('cartId', $cart->id)
        ->assertSee($listing->title)
        ->call('placeOrder')
        ->assertHasNoErrors();

    $order = Order::query()->with(['items', 'invoice.items'])->sole();

    expect($order->buyer_id)->toBe($buyer->id)
        ->and($order->cart_id)->toBe($cart->id)
        ->and($order->total_minor)->toBe(125_000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->invoice)->not->toBeNull()
        ->and($order->invoice->items)->toHaveCount(1)
        ->and($cart->fresh()->status)->toBe(CartStatus::Converted)
        ->and($listing->fresh()->inventory_quantity)->toBe(3);

    $component->assertRedirect(route('storefront.orders.show', [
        'order' => $order->id,
    ]));
});

test('only the buyer can open an order confirmation', function (): void {
    $buyer = User::factory()->create();
    $intruder = User::factory()->create();
    $listing = Listing::factory()->published()->create();
    $cart = resolve(CartServiceInterface::class)->add(
        $listing,
        1,
        $buyer,
        (string) Str::ulid(),
    );
    $order = resolve(CheckoutServiceInterface::class)
        ->checkout($cart, $buyer, (string) Str::ulid(), $cart->version);
    $url = route('storefront.orders.show', ['order' => $order->id]);

    $this->actingAs($buyer)
        ->get($url)
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee($order->invoice->number);

    $this->actingAs($intruder)
        ->get($url)
        ->assertNotFound();
});
