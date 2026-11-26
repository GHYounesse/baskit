<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The demo data: accounts, the catalogue, variants, coupons and reviews. Needs
 * no Faker, so `php artisan db:seed --force` runs inside the production image.
 *
 * Model events stay on (no WithoutModelEvents): they write each product's
 * stock ledger and keep the search index in step.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            VariantSeeder::class,
            CouponSeeder::class,
            ReviewSeeder::class,
        ]);
    }
}
