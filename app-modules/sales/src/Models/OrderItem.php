<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Models;

use App\Models\Concerns\HasUlidAndSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Sales\Database\Factories\OrderItemFactory;

#[Fillable([
    'id',
    'sequence',
    'order_id',
    'listing_id',
    'seller_id',
    'title',
    'currency',
    'unit_price_minor',
    'quantity',
    'line_total_minor',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    use HasUlidAndSequence;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasOne<InvoiceItem, $this> */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
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
