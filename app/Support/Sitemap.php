<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Carbon;
use XMLWriter;

/**
 * The sitemap: the list of every page Google should know about, served at /sitemap.xml.
 *
 * It follows the same rules as the page tags (see StoreSeo):
 *  - the home page is always listed;
 *  - the shop and a category are listed only when they have active products;
 *  - every ACTIVE product is listed (a sold-out one stays: the page still exists);
 *  - searches, sorted pages and page 2, 3... are NOT listed (robots find them through links).
 *
 * Every address is absolute and built from APP_URL, like the canonical tags.
 */
final class Sitemap
{
    /** The sitemap format allows at most 50,000 addresses per file. */
    private const MAX_URLS = 50000;

    /** Kept free for the home page, the shop and the categories, so the total never goes over the limit. */
    private const ROOM_FOR_PAGES = 1000;

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    public function entries(): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(self::MAX_URLS - self::ROOM_FOR_PAGES)
            ->get(['id', 'slug', 'category_id', 'updated_at']);

        $entries = [['loc' => PageSeo::absolute('/'), 'lastmod' => null]];

        if ($products->isEmpty()) {
            return $entries;
        }

        $entries[] = ['loc' => PageSeo::absolute('/shop'), 'lastmod' => $this->lastmod($products->max('updated_at'))];

        // A category counts only through its active products, and is as recent as its newest one.
        $newestByCategory = $products->whereNotNull('category_id')->groupBy('category_id')->map->max('updated_at');

        $categories = Category::query()->whereIn('id', $newestByCategory->keys())->orderBy('slug')->get(['id', 'slug']);

        foreach ($categories as $category) {
            $entries[] = [
                'loc' => PageSeo::absolute('/shop?category='.$category->slug),
                'lastmod' => $this->lastmod($newestByCategory[$category->id]),
            ];
        }

        foreach ($products as $product) {
            $entries[] = [
                'loc' => PageSeo::absolute('/products/'.$product->slug),
                'lastmod' => $this->lastmod($product->updated_at),
            ];
        }

        return $entries;
    }

    public function toXml(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($this->entries() as $entry) {
            $xml->startElement('url');
            // writeElement escapes "&", "<" and ">", so a strange slug can never break the file.
            $xml->writeElement('loc', $entry['loc']);

            if ($entry['lastmod'] !== null) {
                $xml->writeElement('lastmod', $entry['lastmod']);
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** The date format sitemaps expect, e.g. "2026-11-17T09:30:00+00:00". */
    private function lastmod(Carbon|string|null $date): ?string
    {
        return $date === null ? null : Carbon::parse($date)->toAtomString();
    }
}
