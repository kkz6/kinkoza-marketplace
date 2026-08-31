<?php

declare(strict_types=1);

use Kinkoza\Cart\Actions\AddListingToCart;
use Kinkoza\Cart\Actions\GetOrCreateCart;
use Kinkoza\Cart\Actions\RemoveCartItem;
use Kinkoza\Cart\Actions\UpdateCartItemQuantity;
use Kinkoza\Sales\Actions\CheckoutCart;
use Lorisleiva\Actions\Concerns\AsAction;

test('commerce workflows live in Laravel actions without service wrappers', function (): void {
    expect(glob(base_path('app-modules/*/src/Services/*.php')))->toBe([]);

    foreach ([
        AddListingToCart::class,
        CheckoutCart::class,
        GetOrCreateCart::class,
        RemoveCartItem::class,
        UpdateCartItemQuantity::class,
    ] as $action) {
        expect(class_uses_recursive($action))->toContain(AsAction::class);
    }
});
