<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Exceptions\InsufficientInventory;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Models\CartItem;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Contracts\Services\CheckoutServiceInterface;
use Kinkoza\Sales\Enums\InvoiceStatus;
use Kinkoza\Sales\Enums\OrderStatus;
use Kinkoza\Sales\Models\Invoice;
use Kinkoza\Sales\Models\InvoiceItem;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Models\OrderItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $listingAttributes
 * @return array{
 *     cart: Cart,
 *     item: CartItem,
 *     listing: Listing,
 *     seller: User
 * }
 */
function salesCheckoutFixture(User $buyer, int $quantity = 1, array $listingAttributes = []): array
{
    $seller = User::factory()->verifiedSeller()->create();
    $listing = Listing::factory()
        ->published()
        ->for($seller, 'seller')
        ->create([
            'price_minor' => 125_000,
            'inventory_quantity' => 10,
            'version' => 1,
            ...$listingAttributes,
        ]);
    $lineTotalMinor = $listing->price_minor * $quantity;
    $cart = Cart::factory()->forBuyer($buyer)->create([
        'currency' => $listing->getRawOriginal('currency'),
        'subtotal_minor' => $lineTotalMinor,
        'total_minor' => $lineTotalMinor,
    ]);
    $item = CartItem::factory()
        ->forCart($cart)
        ->forListing($listing, $quantity)
        ->create();

    return compact('cart', 'item', 'listing', 'seller');
}

test('checkout creates the complete immutable order and invoice graph', function () {
    $buyer = User::factory()->create();
    $fixture = salesCheckoutFixture($buyer, 2, [
        'title' => 'Five-axis machining centre',
        'price_minor' => 375_000,
    ]);
    $idempotencyKey = (string) Str::ulid();

    $order = resolve(CheckoutServiceInterface::class)->checkout(
        $fixture['cart'],
        $buyer,
        $idempotencyKey,
    );

    expect(Str::isUlid((string) $order->getKey()))->toBeTrue()
        ->and($order->number)->toMatch('/^ORD-\d{8}$/')
        ->and($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->buyer_id)->toBe($buyer->getKey())
        ->and($order->cart_id)->toBe($fixture['cart']->getKey())
        ->and($order->idempotency_key)->toBe($idempotencyKey)
        ->and($order->subtotal_minor)->toBe(750_000)
        ->and($order->total_minor)->toBe(750_000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->invoice)->not->toBeNull()
        ->and($order->invoice->number)->toMatch('/^INV-\d{8}$/')
        ->and($order->invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($order->invoice->items)->toHaveCount(1);

    $orderItem = $order->items->sole();
    $invoiceItem = $order->invoice->items->sole();

    expect(Str::isUlid((string) $orderItem->getKey()))->toBeTrue()
        ->and(Str::isUlid((string) $invoiceItem->getKey()))->toBeTrue()
        ->and($orderItem->listing_id)->toBe($fixture['listing']->getKey())
        ->and($orderItem->seller_id)->toBe($fixture['seller']->getKey())
        ->and($orderItem->title)->toBe('Five-axis machining centre')
        ->and($orderItem->unit_price_minor)->toBe(375_000)
        ->and($orderItem->quantity)->toBe(2)
        ->and($orderItem->line_total_minor)->toBe(750_000)
        ->and($invoiceItem->order_item_id)->toBe($orderItem->getKey())
        ->and($invoiceItem->listing_id)->toBe($orderItem->listing_id)
        ->and($invoiceItem->title)->toBe($orderItem->title)
        ->and($invoiceItem->unit_price_minor)->toBe($orderItem->unit_price_minor)
        ->and($invoiceItem->quantity)->toBe($orderItem->quantity)
        ->and($invoiceItem->line_total_minor)->toBe($orderItem->line_total_minor);

    $fixture['listing']->forceFill([
        'title' => 'Changed after checkout',
        'price_minor' => 999_999,
    ])->save();

    expect($orderItem->fresh()->title)->toBe('Five-axis machining centre')
        ->and($orderItem->fresh()->unit_price_minor)->toBe(375_000)
        ->and($fixture['cart']->fresh()->status)->toBe(CartStatus::Converted)
        ->and($fixture['cart']->fresh()->active_key)->toBeNull()
        ->and($fixture['cart']->fresh()->converted_at)->not->toBeNull();
});

test('checkout decrements inventory and advances the listing version', function () {
    $buyer = User::factory()->create();
    $fixture = salesCheckoutFixture($buyer, 3, [
        'inventory_quantity' => 7,
        'version' => 4,
    ]);

    resolve(CheckoutServiceInterface::class)->checkout(
        $fixture['cart'],
        $buyer,
        (string) Str::ulid(),
    );

    expect($fixture['listing']->fresh()->inventory_quantity)->toBe(4)
        ->and($fixture['listing']->fresh()->version)->toBe(5);
});

test('checkout rejects a cart owned by another buyer', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $fixture = salesCheckoutFixture($owner);

    expect(fn (): Order => resolve(CheckoutServiceInterface::class)->checkout(
        $fixture['cart'],
        $intruder,
        (string) Str::ulid(),
    ))->toThrow(DomainException::class, 'The cart does not belong to this buyer.');

    expect(Order::query()->count())->toBe(0)
        ->and($fixture['cart']->fresh()->status)->toBe(CartStatus::Active)
        ->and($fixture['listing']->fresh()->inventory_quantity)->toBe(10);
});

test('checkout is idempotent by key and cart', function () {
    $buyer = User::factory()->create();
    $fixture = salesCheckoutFixture($buyer);
    $idempotencyKey = (string) Str::ulid();
    $service = resolve(CheckoutServiceInterface::class);

    $firstOrder = $service->checkout($fixture['cart'], $buyer, $idempotencyKey);
    $sameKeyOrder = $service->checkout($fixture['cart'], $buyer, $idempotencyKey);
    $sameCartOrder = $service->checkout($fixture['cart'], $buyer, (string) Str::ulid());

    expect($sameKeyOrder->is($firstOrder))->toBeTrue()
        ->and($sameCartOrder->is($firstOrder))->toBeTrue()
        ->and(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1)
        ->and(InvoiceItem::query()->count())->toBe(1)
        ->and($fixture['listing']->fresh()->inventory_quantity)->toBe(9);
});

test('insufficient inventory rolls back the entire checkout', function () {
    $buyer = User::factory()->create();
    $fixture = salesCheckoutFixture($buyer, 2, [
        'inventory_quantity' => 1,
        'version' => 8,
    ]);
    $activeKey = $fixture['cart']->active_key;

    expect(fn (): Order => resolve(CheckoutServiceInterface::class)->checkout(
        $fixture['cart'],
        $buyer,
        (string) Str::ulid(),
    ))->toThrow(InsufficientInventory::class);

    expect(Order::query()->count())->toBe(0)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(InvoiceItem::query()->count())->toBe(0)
        ->and($fixture['listing']->fresh()->inventory_quantity)->toBe(1)
        ->and($fixture['listing']->fresh()->version)->toBe(8)
        ->and($fixture['cart']->fresh()->status)->toBe(CartStatus::Active)
        ->and($fixture['cart']->fresh()->active_key)->toBe($activeKey);
});

test('checkout revalidates that every listing is still published', function () {
    $buyer = User::factory()->create();
    $fixture = salesCheckoutFixture($buyer);

    $fixture['listing']->forceFill(['offline_at' => now()->subSecond()])->save();

    expect(fn (): Order => resolve(CheckoutServiceInterface::class)->checkout(
        $fixture['cart'],
        $buyer,
        (string) Str::ulid(),
    ))->toThrow(ListingUnavailable::class);

    expect(Order::query()->count())->toBe(0)
        ->and($fixture['cart']->fresh()->status)->toBe(CartStatus::Active);
});
