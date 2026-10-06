<?php

namespace App\Services\Assistant;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Search\SearchTerms;
use App\Services\Search\StoreSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Everything the assistant knows about the shop's products. It only reads:
 * searching, availability, categories, best sellers, and the small "card"
 * shape of a product that the chat shows.
 */
class AssistantCatalog
{
    public function __construct(private StoreSearch $search) {}

    /**
     * @return list<array{name: string, slug: string, products: int}>
     */
    public function categories(): array
    {
        return Cache::remember('assistant.categories', 60, fn () => Category::query()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug, 'products' => $c->products_count])
            ->all());
    }

    /**
     * Colours that at least one variant comes in, as written (e.g. "Navy").
     *
     * @return list<string>
     */
    public function knownColors(): array
    {
        return Cache::remember('assistant.colors', 300, fn () => ProductVariant::query()
            ->where('color', '!=', '')
            ->distinct()
            ->orderBy('color')
            ->pluck('color')
            ->all());
    }

    /**
     * Search with optional filters.
     *
     * Every word has to match, so if nothing does, the last words are dropped
     * one at a time ("smartwatch waterproof" falls back to "smartwatch"), and
     * failing that a corrected spelling is tried. The result says which
     * happened so the reply can be honest about it.
     *
     * @param  array{query?: ?string, category?: ?string, min_price_cents?: ?int, max_price_cents?: ?int, sort?: ?string, size?: ?string, color?: ?string}  $criteria
     * @return array{products: Collection<int, Product>, total: int, query: string, asked: string, corrected: bool, relaxed: bool}
     */
    public function search(array $criteria, int $limit = 4, int $offset = 0): array
    {
        $asked = trim((string) ($criteria['query'] ?? ''));
        $used = $asked;
        $ids = null;
        $corrected = false;
        $relaxed = false;

        if ($asked !== '') {
            [$ids, $used, $relaxed] = $this->rankedIds($asked);

            if ($ids === [] && ($fix = $this->search->correction($asked))) {
                [$ids, $used, $relaxed] = $this->rankedIds($fix);
                $corrected = $ids !== [];
            }
        }

        $products = $this->base()
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('products.id', $ids ?: [0]))
            ->when($criteria['category'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($criteria['min_price_cents'] ?? null, fn (Builder $q, int $min) => $q->where('price_cents', '>=', $min))
            ->when($criteria['max_price_cents'] ?? null, fn (Builder $q, int $max) => $q->where('price_cents', '<=', $max))
            ->when($criteria['color'] ?? null, fn (Builder $q, string $color) => $q->whereHas('variants', fn ($v) => $v->whereRaw('LOWER(color) = ?', [mb_strtolower($color)])->where('stock', '>', 0)))
            ->when($criteria['size'] ?? null, fn (Builder $q, string $size) => $q->whereHas('variants', fn ($v) => $v->whereRaw('LOWER(size) = ?', [mb_strtolower($size)])->where('stock', '>', 0)))
            ->get();

        $products = $this->sorted($products, $criteria['sort'] ?? null, $ids ?? [])
            // Things you can actually buy come first (the sort above is kept within each group).
            ->sortBy(fn (Product $p) => $p->stock > 0 ? 0 : 1)
            ->values();

        return [
            'products' => $products->slice($offset, $limit)->values(),
            'total' => $products->count(),
            'query' => $used,
            'asked' => $asked,
            'corrected' => $corrected,
            'relaxed' => $relaxed,
        ];
    }

    /**
     * The one product best matching what someone typed, or null.
     */
    public function find(string $text): ?Product
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        [$ids] = $this->rankedIds($text);

        if ($ids === [] && ($fix = $this->search->correction($text))) {
            [$ids] = $this->rankedIds($fix);
        }

        if ($ids === []) {
            return null;
        }

        // The best few matches; of those, a product named exactly this (or starting with it) wins.
        $candidates = $this->base()->whereIn('products.id', array_slice($ids, 0, 5))->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
            ->values();

        $wanted = implode(' ', SearchTerms::words($text));
        $words = fn (Product $p) => implode(' ', SearchTerms::words($p->name));

        return $candidates->first(fn (Product $p) => $words($p) === $wanted)
            ?? $candidates->first(fn (Product $p) => str_starts_with($words($p), $wanted))
            ?? $candidates->first();
    }

    public function bySlug(string $slug): ?Product
    {
        return $this->base()->where('slug', $slug)->first();
    }

    /**
     * Products people have bought most in the last 90 days, falling back to
     * the best rated when there are no recent sales.
     *
     * @return Collection<int, Product>
     */
    public function bestSellers(int $limit = 4): Collection
    {
        $ids = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', Order::RETURNED_STATUSES)
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit(30)
            ->pluck('order_items.product_id')
            ->all();

        $products = $this->base()->whereIn('products.id', $ids ?: [0])->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
            ->filter(fn (Product $p) => $p->stock > 0)
            ->values();

        if ($products->isNotEmpty()) {
            return $products->take($limit)->values();
        }

        $rated = $this->base()->where('stock', '>', 0)->whereHas('reviews')->orderByDesc('reviews_avg_rating')->limit($limit)->get();

        return $rated->isNotEmpty() ? $rated : $this->newest($limit);
    }

    /**
     * @return Collection<int, Product>
     */
    public function newest(int $limit = 4): Collection
    {
        return $this->base()->where('stock', '>', 0)->latest()->limit($limit)->get();
    }

    /**
     * How a product looks in the chat.
     *
     * @return array<string, mixed>
     */
    public function card(Product $product): array
    {
        $variants = $product->variants;
        $sizes = $variants->pluck('size')->filter()->unique();
        $colors = $variants->pluck('color')->filter()->unique();

        $options = collect([
            $sizes->isNotEmpty() ? $sizes->count().($sizes->count() === 1 ? ' size' : ' sizes') : null,
            $colors->isNotEmpty() ? $colors->count().($colors->count() === 1 ? ' colour' : ' colours') : null,
        ])->filter()->implode(' · ');

        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => "/products/{$product->slug}",
            'price_cents' => $product->price_cents,
            'image_url' => $product->image_url,
            'category' => $product->category?->name,
            'rating' => $product->reviews_avg_rating !== null ? round((float) $product->reviews_avg_rating, 1) : null,
            'reviews' => (int) ($product->reviews_count ?? 0),
            'in_stock' => $product->stock > 0,
            'options' => $options !== '' ? $options : null,
        ];
    }

    /**
     * Ranked IDs for a query, dropping trailing words until something matches.
     *
     * @return array{0: list<int>, 1: string, 2: bool} IDs, the words actually used, and whether any were dropped
     */
    private function rankedIds(string $query): array
    {
        $terms = SearchTerms::from($query);

        for ($count = count($terms); $count >= 1; $count--) {
            $attempt = implode(' ', array_slice($terms, 0, $count));
            $ids = $this->search->ids($attempt, 100);

            if ($ids !== []) {
                return [$ids, $attempt, $count < count($terms)];
            }
        }

        return [[], $query, false];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<int>  $rank
     * @return Collection<int, Product>
     */
    private function sorted(Collection $products, ?string $sort, array $rank): Collection
    {
        return match ($sort) {
            'price_asc' => $products->sortBy('price_cents'),
            'price_desc' => $products->sortByDesc('price_cents'),
            'rating' => $products->sortByDesc(fn (Product $p) => [(float) $p->reviews_avg_rating, (int) $p->reviews_count]),
            'newest' => $products->sortByDesc('created_at'),
            default => $rank !== []
                ? $products->sortBy(fn (Product $p) => array_search($p->id, $rank, true))
                : $products->sortByDesc('created_at'),
        };
    }

    private function base(): Builder
    {
        return Product::query()
            ->with('category', 'variants')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true);
    }
}
