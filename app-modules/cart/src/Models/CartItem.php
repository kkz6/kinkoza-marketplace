<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Models;

use App\Models\Concerns\HasUlidAndSequence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Kinkoza\Cart\Database\Factories\CartItemFactory;
use Kinkoza\Catalog\Models\Listing;

/**
 * @property string $id
 * @property int $sequence
 * @property string $cart_id
 * @property string|null $listing_id
 * @property string $sku
 * @property string $title
 * @property string $currency
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Cart $cart
 * @property-read Listing|null $listing
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory, HasUlidAndSequence;

    /** @var list<string> */
    protected $fillable = [
        'cart_id',
        'listing_id',
        'sku',
        'title',
        'currency',
        'unit_price_minor',
        'line_total_minor',
        'quantity',
    ];

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'quantity' => 'integer',
        ];
    }
}
