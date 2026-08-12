<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Electronics', 'Clothing', 'Home & Kitchen', 'Books', 'Toys'] as $name) {
            Category::create(['name' => $name, 'slug' => Str::slug($name)]);
        }
    }
}
