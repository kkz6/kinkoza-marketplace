<?php

namespace Kinkoza\Catalog\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;

class ListingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seller = User::query()
            ->where('email', 'seller@example.com')
            ->firstOrFail();

        $listings = [
            ['Five-axis CNC machining centre', ListingCategory::MachineryEquipment, Currency::EUR, 18_500_000, Country::France, 'Lyon'],
            ['Low-hours hydraulic excavator', ListingCategory::MachineryEquipment, Currency::EUR, 9_750_000, Country::Belgium, 'Liège'],
            ['Automated packaging production line', ListingCategory::MachineryEquipment, Currency::EUR, 24_000_000, Country::Luxembourg, 'Esch-sur-Alzette'],
            ['Refrigerated delivery fleet', ListingCategory::VehiclesFleet, Currency::EUR, 12_800_000, Country::France, 'Rungis'],
            ['Six electric last-mile vans', ListingCategory::VehiclesFleet, Currency::EUR, 31_500_000, Country::Belgium, 'Brussels'],
            ['Executive vehicle fleet', ListingCategory::VehiclesFleet, Currency::GBP, 8_900_000, Country::Luxembourg, 'Luxembourg'],
            ['Logistics warehouse near motorway', ListingCategory::CommercialProperty, Currency::EUR, 145_000_000, Country::France, 'Lille'],
            ['Prime city-centre retail unit', ListingCategory::CommercialProperty, Currency::EUR, 82_000_000, Country::Belgium, 'Antwerp'],
            ['Fitted professional office floor', ListingCategory::CommercialProperty, Currency::EUR, 67_500_000, Country::Luxembourg, 'Kirchberg'],
            ['Registered industrial design portfolio', ListingCategory::IntangibleAssets, Currency::EUR, 4_250_000, Country::France, 'Paris'],
            ['Established regional ecommerce brand', ListingCategory::IntangibleAssets, Currency::GBP, 16_000_000, Country::Belgium, 'Ghent'],
            ['B2B logistics software licence portfolio', ListingCategory::IntangibleAssets, Currency::EUR, 28_000_000, Country::Luxembourg, 'Luxembourg'],
        ];

        foreach ($listings as $index => [$title, $category, $currency, $priceMinor, $country, $city]) {
            $slug = Str::slug($title);

            Listing::factory()->create([
                'seller_id' => $seller->getKey(),
                'title' => $title,
                'slug' => $slug,
                'description' => "A verified business asset opportunity in {$city}, with due-diligence materials available to qualified buyers.",
                'category' => $category,
                'status' => match ($index) {
                    10 => ListingStatus::PendingReview,
                    11 => ListingStatus::Draft,
                    default => ListingStatus::Published,
                },
                'currency' => $currency,
                'price_minor' => $priceMinor,
                'country' => $country,
                'city' => $city,
                'online_at' => now()->subDays($index + 1),
                'offline_at' => null,
                'inventory_quantity' => $index % 4 === 0 ? 2 : 1,
                'image_url' => "https://picsum.photos/seed/{$slug}/900/900",
            ]);
        }
    }
}
