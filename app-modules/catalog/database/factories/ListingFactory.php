<?php

namespace Kinkoza\Catalog\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true));
        $slug = Str::slug($title).'-'.Str::lower(Str::random(6));

        $location = fake()->randomElement([
            ['country' => Country::France, 'city' => 'Lyon'],
            ['country' => Country::France, 'city' => 'Paris'],
            ['country' => Country::Belgium, 'city' => 'Brussels'],
            ['country' => Country::Belgium, 'city' => 'Antwerp'],
            ['country' => Country::Luxembourg, 'city' => 'Luxembourg'],
        ]);

        return [
            'seller_id' => User::factory(),
            'title' => $title,
            'slug' => $slug,
            'description' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(ListingCategory::cases()),
            'status' => ListingStatus::Published,
            'currency' => fake()->randomElement(Currency::cases()),
            'price_minor' => fake()->numberBetween(75_000, 75_000_000),
            'country' => $location['country'],
            'city' => $location['city'],
            'online_at' => now()->subDays(fake()->numberBetween(1, 90)),
            'offline_at' => null,
            'inventory_quantity' => fake()->numberBetween(1, 8),
            'image_url' => "https://picsum.photos/seed/{$slug}/900/900",
            'version' => 1,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::Draft,
            'online_at' => now(),
            'offline_at' => null,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::PendingReview,
            'online_at' => now(),
            'offline_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::Published,
            'online_at' => now()->subDay(),
            'offline_at' => now()->addMonth(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::Published,
            'online_at' => now()->addDay(),
            'offline_at' => null,
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::Published,
            'online_at' => now()->subMonth(),
            'offline_at' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => ListingStatus::Expired,
            'online_at' => now()->subMonths(2),
            'offline_at' => now()->subMonth(),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => [
            'inventory_quantity' => 0,
        ]);
    }
}
