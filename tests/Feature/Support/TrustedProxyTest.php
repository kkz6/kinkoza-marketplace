<?php

declare(strict_types=1);

test('local reverse proxies preserve secure storefront URLs', function (): void {
    $this->withServerVariables([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('http://kinkoza.test/')
        ->assertOk()
        ->assertSee('https://kinkoza.test/cart');
});
