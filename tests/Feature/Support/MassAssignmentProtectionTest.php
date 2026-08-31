<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\MassAssignmentException;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Models\Order;

test('aggregate accounting state cannot be mass assigned', function (): void {
    expect(fn (): Order => Order::query()->create([
        'buyer_id' => '01m1ba00000000000000000000',
        'status' => 'confirmed',
        'total_minor' => 1,
    ]))->toThrow(MassAssignmentException::class)
        ->and(fn (): Cart => Cart::query()->create([
            'active_key' => 'buyer:01m1ba00000000000000000000',
            'status' => 'converted',
            'total_minor' => 1,
        ]))->toThrow(MassAssignmentException::class);
});

test('listing ownership and workflow state cannot be mass assigned', function (): void {
    expect(fn (): Listing => Listing::query()->create([
        'seller_id' => '01m1ba00000000000000000000',
        'title' => 'Attempted listing',
        'slug' => 'forged-slug',
        'status' => 'published',
        'version' => 99,
    ]))->toThrow(MassAssignmentException::class);
});
