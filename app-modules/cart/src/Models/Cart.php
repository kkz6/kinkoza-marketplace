<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Models;

use App\Models\Concerns\HasUlidAndSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Kinkoza\Cart\Database\Factories\CartFactory;
use Kinkoza\Cart\Enums\CartStatus;

/**
 * @property string $id
 * @property int $sequence
 * @property string|null $buyer_id
 * @property string|null $guest_token
 * @property string|null $active_key
 * @property string $currency
 * @property CartStatus $status
 * @property int $subtotal_minor
 * @property int $total_minor
 * @property int $version
 * @property Carbon|null $converted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $buyer
 * @property-read Collection<int, CartItem> $items
 */
#[Guarded(['*'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory, HasUlidAndSequence;

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'version' => 'integer',
            'converted_at' => 'datetime',
        ];
    }
}
