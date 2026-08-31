<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Database\Factories;

use App\Models\User;
use App\Support\Database\SequenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Models\OrderItem;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPriceMinor = fake()->numberBetween(1_000, 500_000);

        return [
            'sequence' => resolve(SequenceGenerator::class)->next('order_items'),
            'order_id' => Order::factory(),
            'listing_id' => null,
            'seller_id' => User::factory(),
            'title' => fake()->words(4, true),
            'currency' => 'EUR',
            'unit_price_minor' => $unitPriceMinor,
            'quantity' => $quantity,
            'line_total_minor' => $unitPriceMinor * $quantity,
        ];
    }
}
