<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kinkoza\Catalog\Database\Seeders\ListingSeeder;
use Kinkoza\Catalog\Models\Listing;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('seeder creates a believable deterministic catalog for the known seller', function () {
    $seller = User::factory()->verifiedSeller()->create([
        'email' => 'seller@example.com',
    ]);

    $this->seed(ListingSeeder::class);

    $listings = Listing::query()->orderBy('sequence')->get();

    expect($listings)->toHaveCount(12)
        ->and($listings->pluck('seller_id')->unique()->all())->toBe([$seller->id])
        ->and($listings->pluck('image_url')->unique())->toHaveCount(12)
        ->and($listings->every(
            fn (Listing $listing): bool => str_starts_with(
                (string) $listing->image_url,
                'https://picsum.photos/seed/',
            ),
        ))->toBeTrue();
});
