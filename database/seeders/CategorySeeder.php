<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Safe to run repeatedly: a category is matched by its slug and never
     * overwritten.
     */
    public function run(): void
    {
        foreach (require database_path('seeders/data/catalogue.php') as $slug => $category) {
            Category::firstOrCreate(['slug' => $slug], ['name' => $category['name']]);
        }
    }
}
