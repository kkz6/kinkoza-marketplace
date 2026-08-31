<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Kinkoza\Catalog\Database\Seeders\ListingSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seller = User::query()->firstOrNew(['email' => 'seller@example.com']);
        $seller->forceFill([
            'name' => 'Sophie Laurent',
            'password' => 'password',
            'email_verified_at' => now(),
            'company_name' => 'Atelier Verne SAS',
            'registration_number' => 'FR552110998',
            'phone' => '+33472123456',
            'country' => 'FR',
            'locale' => 'fr',
            'is_verified_seller' => true,
        ])->save();

        $buyer = User::query()->firstOrNew(['email' => 'buyer@example.com']);
        $buyer->forceFill([
            'name' => 'Marc Dubois',
            'password' => 'password',
            'email_verified_at' => now(),
            'company_name' => 'Dubois Procurement SARL',
            'registration_number' => 'BE0744887766',
            'phone' => '+3225550198',
            'country' => 'BE',
            'locale' => 'en',
            'is_verified_seller' => false,
        ])->save();

        $this->call(ListingSeeder::class);
    }
}
