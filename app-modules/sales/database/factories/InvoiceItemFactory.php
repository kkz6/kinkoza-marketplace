<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Database\Factories;

use App\Support\Database\SequenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kinkoza\Sales\Models\Invoice;
use Kinkoza\Sales\Models\InvoiceItem;
use Kinkoza\Sales\Models\OrderItem;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPriceMinor = fake()->numberBetween(1_000, 500_000);

        return [
            'sequence' => resolve(SequenceGenerator::class)->next('invoice_items'),
            'invoice_id' => Invoice::factory(),
            'order_item_id' => OrderItem::factory(),
            'listing_id' => (string) Str::ulid(),
            'title' => fake()->words(4, true),
            'currency' => 'EUR',
            'unit_price_minor' => $unitPriceMinor,
            'quantity' => $quantity,
            'line_total_minor' => $unitPriceMinor * $quantity,
        ];
    }
}
