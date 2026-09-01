<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kinkoza\Cart\Enums\CartStatus;
use Kinkoza\Cart\Models\Cart;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $guestToken = strtolower((string) Str::ulid());

        return [
            'buyer_id' => null,
            'guest_token' => $guestToken,
            'active_key' => "guest:{$guestToken}",
            'currency' => 'EUR',
            'status' => CartStatus::Active,
            'subtotal_minor' => 0,
            'total_minor' => 0,
            'version' => 1,
            'converted_at' => null,
        ];
    }

    public function forBuyer(User $buyer): static
    {
        return $this->state(fn (): array => [
            'buyer_id' => $buyer->id,
            'guest_token' => null,
            'active_key' => "buyer:{$buyer->id}",
        ]);
    }

    public function forGuest(?string $guestToken = null): static
    {
        $guestToken ??= strtolower((string) Str::ulid());
        $guestToken = strtolower($guestToken);

        return $this->state(fn (): array => [
            'buyer_id' => null,
            'guest_token' => $guestToken,
            'active_key' => "guest:{$guestToken}",
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (): array => [
            'active_key' => null,
            'status' => CartStatus::Converted,
            'converted_at' => now(),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (): array => [
            'active_key' => null,
            'status' => CartStatus::Abandoned,
        ]);
    }
}
