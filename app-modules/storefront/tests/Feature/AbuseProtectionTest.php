<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('public cart creation is rate limited per visitor identity', function (): void {
    foreach (range(1, 30) as $attempt) {
        $this->get(route('storefront.cart.show'))->assertOk();
    }

    $this->get(route('storefront.cart.show'))->assertTooManyRequests();
});
