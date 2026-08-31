<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Models;

use App\Models\Concerns\HasUlidAndSequence;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Sales\Database\Factories\InvoiceItemFactory;

#[Guarded(['*'])]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    use HasUlidAndSequence;

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'currency' => Currency::class,
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }
}
