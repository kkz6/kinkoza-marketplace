<?php

declare(strict_types=1);

test('local reverse proxies preserve secure storefront URLs', function (): void {
    $this->withServerVariables([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_HOST' => 'kinkoza.test',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('/')
        ->assertOk()
        ->assertSee('https://kinkoza.test/cart');
});
