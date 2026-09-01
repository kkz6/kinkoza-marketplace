<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kinkoza\Cart\Actions\AddListingToCart;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Models\OrderItem;
use Kinkoza\Storefront\Actions\GetAccountDashboard;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the account dashboard requires a verified account', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the dashboard action scopes marketplace activity to the account', function (): void {
    $user = User::factory()->verifiedSeller()->create();
    $otherUser = User::factory()->verifiedSeller()->create();

    Listing::factory()->published()->for($user, 'seller')->create(['title' => 'Owned active asset']);
    Listing::factory()->for($user, 'seller')->create([
        'title' => 'Owned pending asset',
        'status' => ListingStatus::PendingReview,
    ]);
    Listing::factory()->published()->for($otherUser, 'seller')->create(['title' => 'Other account asset']);

    $cartListing = Listing::factory()->published()->for($otherUser, 'seller')->create([
        'inventory_quantity' => 5,
    ]);
    $guestToken = strtolower((string) Str::ulid());
    AddListingToCart::run($cartListing, 2, $user, $guestToken);

    $purchase = Order::factory()->create(['buyer_id' => $user->id]);
    Order::factory()->create(['buyer_id' => $otherUser->id]);

    $saleOrder = Order::factory()->create(['buyer_id' => $otherUser->id]);
    OrderItem::factory()->create([
        'order_id' => $saleOrder->id,
        'seller_id' => $user->id,
        'quantity' => 3,
    ]);

    $dashboard = GetAccountDashboard::make()->handle($user, $guestToken);

    expect($dashboard->activeListingCount)->toBe(1)
        ->and($dashboard->totalListingCount)->toBe(2)
        ->and($dashboard->pendingListingCount)->toBe(1)
        ->and($dashboard->cartItemCount)->toBe(2)
        ->and($dashboard->purchaseCount)->toBe(1)
        ->and($dashboard->salesOrderCount)->toBe(1)
        ->and($dashboard->unitsSold)->toBe(3)
        ->and($dashboard->hasBusinessProfile)->toBeTrue()
        ->and($dashboard->isVerifiedSeller)->toBeTrue();

    $this->actingAs($user)
        ->withSession(['storefront.guest_cart_token' => $guestToken])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Owned active asset')
        ->assertSee('Owned pending asset')
        ->assertSee($purchase->number)
        ->assertDontSee('Other account asset');
});
