<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Database\Factories;

use BackedEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Cart\Models\CartItem;
use Kinkoza\Catalog\Models\Listing;
use UnexpectedValueException;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'listing_id' => Listing::factory(),
            'sku' => strtolower((string) Str::ulid()),
            'title' => fake()->sentence(3),
            'currency' => 'EUR',
            'unit_price_minor' => 1_000,
            'line_total_minor' => 1_000,
            'quantity' => 1,
        ];
    }

    public function forCart(Cart $cart): static
    {
        return $this->state(fn (): array => [
            'cart_id' => $cart->getKey(),
            'currency' => $cart->currency,
        ]);
    }

    public function forListing(Listing $listing, int $quantity = 1): static
    {
        $currency = $listing->getAttribute('currency');
        $title = $listing->getAttribute('title');
        $priceMinor = $listing->getAttribute('price_minor');

        if ($currency instanceof BackedEnum) {
            $currency = $currency->value;
        }

        if (! is_string($currency) || ! is_string($title) || ! is_int($priceMinor)) {
            throw new UnexpectedValueException('Listing snapshot attributes are invalid.');
        }

        return $this->state(fn (): array => [
            'listing_id' => $listing->getKey(),
            'sku' => (string) $listing->getKey(),
            'title' => $title,
            'currency' => $currency,
            'unit_price_minor' => $priceMinor,
            'line_total_minor' => $priceMinor * $quantity,
            'quantity' => $quantity,
        ]);
    }
}
