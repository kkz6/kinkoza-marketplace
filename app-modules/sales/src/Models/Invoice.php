<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Models;

use App\Models\Concerns\HasUlidAndSequence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Sales\Database\Factories\InvoiceFactory;
use Kinkoza\Sales\Enums\InvoiceStatus;

#[Fillable([
    'id',
    'sequence',
    'number',
    'order_id',
    'status',
    'currency',
    'subtotal_minor',
    'total_minor',
    'issued_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use HasUlidAndSequence;

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => InvoiceStatus::class,
            'currency' => Currency::class,
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'issued_at' => 'immutable_datetime',
        ];
    }
}
