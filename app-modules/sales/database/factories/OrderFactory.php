<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Database\Factories;

use App\Models\User;
use App\Support\Database\SequenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Enums\OrderStatus;
use Kinkoza\Sales\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $sequence = resolve(SequenceGenerator::class)->next('orders');
        $subtotalMinor = fake()->numberBetween(1_000, 1_000_000);

        return [
            'sequence' => $sequence,
            'number' => sprintf('ORD-%08d', $sequence),
            'buyer_id' => User::factory(),
            'cart_id' => Cart::factory(),
            'idempotency_key' => (string) Str::ulid(),
            'status' => OrderStatus::Confirmed,
            'currency' => 'EUR',
            'subtotal_minor' => $subtotalMinor,
            'total_minor' => $subtotalMinor,
            'placed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Pending,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
