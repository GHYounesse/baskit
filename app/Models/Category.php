<?php

namespace App\Models;

use App\Services\Search\ProductSearch;
use App\Services\Search\StoreSearch;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        // A product is searchable by its category's name, so renaming a
        // category refreshes the products in it.
        static::saved(function (Category $category) {
            if ($category->wasChanged('name')) {
                $search = app(ProductSearch::class);
                $category->products()->with('category')->each(fn (Product $product) => $search->index($product));
            }

            StoreSearch::forgetVocabulary();
        });

        static::deleted(fn () => StoreSearch::forgetVocabulary());
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
