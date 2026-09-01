<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Models;

use App\Models\Concerns\HasUlidAndSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Sales\Database\Factories\OrderFactory;
use Kinkoza\Sales\Enums\OrderStatus;

/**
 * @property string $id
 * @property string $number
 * @property string $buyer_id
 * @property string $cart_id
 * @property string $idempotency_key
 */
#[Guarded(['*'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUlidAndSequence;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => OrderStatus::class,
            'currency' => Currency::class,
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'placed_at' => 'immutable_datetime',
        ];
    }
}
