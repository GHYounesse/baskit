<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;

/**
 * Builds the SEO of Baskit's PUBLIC pages: the home page, the shop and a product.
 */
final class StoreSeo
{
    /**
     * Picture used in link previews when a page has no photo of its own: the Baskit logo.
     */
    private const DEFAULT_IMAGE = '/assets/images/main-logo-light%20mode.png';

    /**
     * Shop prices are in US dollars (the same currency Stripe is asked to charge in).
     */
    private const CURRENCY = 'USD';

    public static function home(): PageSeo
    {
        return new PageSeo(
            title: 'Home',
            description: 'Browse the catalogue, add what you like and check out in a few minutes. '
                .'Cancel any order before it ships and the money goes straight back to your card.',
            image: self::DEFAULT_IMAGE,
            path: '/',
        );
    }

    /**
     * The catalogue at /shop, or the catalogue of one category
     *

     *
     * @param  int  $page  The page of results being shown (1 is the first).
     * @param  bool  $isSearch  True when the visitor typed a search.
     * @param  bool  $hasResults  False when the page lists no product at all.
     */
    public static function shop(?Category $category = null, int $page = 1, bool $isSearch = false, bool $hasResults = true): PageSeo
    {
        if ($isSearch) {
            return new PageSeo(title: 'Search', noindex: true, followLinks: true);
        }

        if (! $hasResults) {
            return new PageSeo(title: 'Shop', noindex: true, followLinks: true);
        }

        $path = $category ? '/shop?category='.$category->slug : '/shop';
        $title = $category ? $category->name : 'Shop';

        if ($page > 1) {
            $path .= (str_contains($path, '?') ? '&' : '?').'page='.$page;
            $title .= " (page {$page})";
        }

        return new PageSeo(
            title: $title,
            description: $category
                ? "Browse {$category->name} at Baskit: see what is in stock, read the reviews and add what you like to your basket."
                : 'Everything in the Baskit catalogue, with search, categories and sorting by price or rating.',
            image: self::DEFAULT_IMAGE,
            path: $path,
        );
    }

    /**
     * One product page.
     *
     * Besides the usual tags, it carries structured data for Google (JSON-LD, the "schema.org"
     * format): the name, price and whether it is in stock, plus the average rating when there are
     * reviews. That is what lets Google show a price and stars directly in its results.
     *
     * A product that is switched off is still visible to admins, but must not be listed by search
     * engines: it gets "noindex".
     *
     * @param  array{count: int, average: ?float}  $ratings  The result of Product::ratingSummary().
     */
    public static function product(Product $product, array $ratings): PageSeo
    {
        $product->loadMissing('category');

        $url = PageSeo::absolute("/products/{$product->slug}");
        $image = PageSeo::absolute($product->image_url);

        // Google wants the real text of the product; if there is none, write a plain sentence.
        $description = PageSeo::describe($product->description)
            ?? "{$product->name}".($product->category ? " from {$product->category->name}" : '').' at Baskit.';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $description,
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => self::CURRENCY,
                // A string with two decimals, as Google expects: "22.92".
                'price' => number_format($product->price_cents / 100, 2, '.', ''),
                'availability' => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($image) {
            $data['image'] = [$image];
        }

        if ($product->category) {
            $data['category'] = $product->category->name;
        }

        // Only claim a rating when real reviews exist; an empty one would be false information.
        if (($ratings['count'] ?? 0) > 0 && $ratings['average'] !== null) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $ratings['average'],
                'reviewCount' => (int) $ratings['count'],
            ];
        }

        return new PageSeo(
            title: $product->name,
            description: $description,
            image: $product->image_url ?? self::DEFAULT_IMAGE,
            path: "/products/{$product->slug}",
            type: 'product',
            noindex: ! $product->is_active,
            jsonLd: $data,
        );
    }
}
