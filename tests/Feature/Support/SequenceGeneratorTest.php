<?php

use App\Models\User;
use App\Support\Database\SequenceGenerator;
use Illuminate\Support\Str;

it('assigns ULID primary keys and independent numeric sequences', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    expect(Str::isUlid($first->id))->toBeTrue()
        ->and($first->sequence)->toBe(1)
        ->and(Str::isUlid($second->id))->toBeTrue()
        ->and($second->sequence)->toBe(2)
        ->and($second->id)->not->toBe($first->id);
});

it('maintains a separate counter for each record type', function () {
    $sequences = resolve(SequenceGenerator::class);

    expect($sequences->next('orders'))->toBe(1)
        ->and($sequences->next('orders'))->toBe(2)
        ->and($sequences->next('invoices'))->toBe(1);
});

it('reserves a contiguous range with one counter update', function () {
    $sequences = resolve(SequenceGenerator::class);

    expect($sequences->reserve('order_items', 4))->toBe([1, 2, 3, 4])
        ->and($sequences->next('order_items'))->toBe(5);
});
