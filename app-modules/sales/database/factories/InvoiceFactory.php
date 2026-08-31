<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Database\Factories;

use App\Support\Database\SequenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Kinkoza\Sales\Enums\InvoiceStatus;
use Kinkoza\Sales\Models\Invoice;
use Kinkoza\Sales\Models\Order;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $sequence = resolve(SequenceGenerator::class)->next('invoices');
        $subtotalMinor = fake()->numberBetween(1_000, 1_000_000);

        return [
            'sequence' => $sequence,
            'number' => sprintf('INV-%08d', $sequence),
            'order_id' => Order::factory(),
            'status' => InvoiceStatus::Issued,
            'currency' => 'EUR',
            'subtotal_minor' => $subtotalMinor,
            'total_minor' => $subtotalMinor,
            'issued_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Paid,
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Void,
        ]);
    }
}
