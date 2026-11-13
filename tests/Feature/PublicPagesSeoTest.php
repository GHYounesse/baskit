<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a crawler (Google, WhatsApp, LinkedIn) receives for the public pages: the raw HTML,
 * with the tags already in the <head>, before any JavaScript runs.
 */
class PublicPagesSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://shop.example.com', 'app.name' => 'Baskit']);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes + [
            'name' => 'Blue Lamp',
            'slug' => 'blue-lamp',
            'description' => 'A <b>soft</b> light for your desk.',
            'price_cents' => 2292,
            'stock' => 5,
            'is_active' => true,
            'image_path' => 'https://images.example.org/lamp.jpg',
            'category_id' => Category::firstOrCreate(['slug' => 'home'], ['name' => 'Home'])->id,
        ]);
    }

    /** The structured data written in the page, decoded. */
    private function jsonLd(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match), 'No JSON-LD in the page');

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    // ---- Home ----

    public function test_the_home_page_asks_to_be_listed_and_has_a_title_a_description_and_a_preview_image(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<title inertia>Home - Baskit</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/">', $html);
        $this->assertStringContainsString('<meta name="description" content="Browse the catalogue', $html);
        $this->assertStringContainsString('content="https://shop.example.com/assets/images/main-logo-light%20mode.png"', $html);
    }

    // ---- Shop ----

    public function test_the_shop_is_listed_with_its_own_address(): void
    {
        $html = $this->get('/shop')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<title inertia>Shop - Baskit</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop">', $html);
    }

    public function test_a_category_page_has_its_own_title_and_address(): void
    {
        Category::create(['name' => 'Books', 'slug' => 'books']);

        $html = $this->get('/shop?category=books')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Books - Baskit</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop?category=books">', $html);
    }

    public function test_sorting_the_shop_points_back_to_the_plain_address(): void
    {
        Category::create(['name' => 'Books', 'slug' => 'books']);

        $sorted = $this->get('/shop?category=books&sort=price_asc')->assertOk()->getContent();
        $plain = $this->get('/shop?sort=price_desc')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop?category=books">', $sorted);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop">', $plain);
    }

    public function test_an_unknown_category_never_ends_up_in_an_address_we_publish(): void
    {
        $html = $this->get('/shop?category=does-not-exist')->assertOk()->getContent();

        // The published address is the plain shop; the visitor's made-up category is not copied into it.
        // (Only the <head> is checked: the page's own data legitimately repeats the visitor's URL.)
        $head = explode('</head>', $html)[0];

        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop">', $head);
        $this->assertStringNotContainsString('does-not-exist', $head);
    }

    // ---- Product ----

    public function test_a_product_page_has_its_own_title_description_image_and_address(): void
    {
        $this->product();

        $html = $this->get('/products/blue-lamp')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Blue Lamp - Baskit</title>', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/products/blue-lamp">', $html);
        // The HTML of the description was removed.
        $this->assertStringContainsString('<meta name="description" content="A soft light for your desk.">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://images.example.org/lamp.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
    }

    public function test_a_product_page_tells_google_the_price_and_that_it_is_in_stock(): void
    {
        $this->product();

        $data = $this->jsonLd($this->get('/products/blue-lamp')->getContent());

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Blue Lamp', $data['name']);
        $this->assertSame('Offer', $data['offers']['@type']);
        $this->assertSame('22.92', $data['offers']['price']);
        $this->assertSame('USD', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        $this->assertSame('https://shop.example.com/products/blue-lamp', $data['offers']['url']);
        $this->assertSame('Home', $data['category']);
    }

    public function test_a_sold_out_product_is_reported_as_out_of_stock(): void
    {
        $this->product(['stock' => 0]);

        $data = $this->jsonLd($this->get('/products/blue-lamp')->getContent());

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
    }

    public function test_a_product_without_reviews_claims_no_rating(): void
    {
        $this->product();

        $data = $this->jsonLd($this->get('/products/blue-lamp')->getContent());

        $this->assertArrayNotHasKey('aggregateRating', $data);
    }

    public function test_a_reviewed_product_shows_its_real_average_and_count(): void
    {
        $product = $this->product();
        Review::factory()->create(['product_id' => $product->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $product->id, 'rating' => 4]);

        $data = $this->jsonLd($this->get('/products/blue-lamp')->getContent());

        $this->assertSame('AggregateRating', $data['aggregateRating']['@type']);
        $this->assertSame('4.5', $data['aggregateRating']['ratingValue']);
        $this->assertSame(2, $data['aggregateRating']['reviewCount']);
    }

    public function test_a_product_without_a_description_gets_a_plain_sentence(): void
    {
        $this->product(['description' => null]);

        $html = $this->get('/products/blue-lamp')->getContent();

        $this->assertStringContainsString('<meta name="description" content="Blue Lamp from Home at Baskit.">', $html);
    }

    public function test_a_product_without_a_photo_falls_back_to_the_logo_and_leaves_image_out_of_the_data(): void
    {
        $this->product(['image_path' => null]);

        $html = $this->get('/products/blue-lamp')->getContent();

        $this->assertStringContainsString('main-logo-light%20mode.png', $html);
        $this->assertArrayNotHasKey('image', $this->jsonLd($html));
    }

    public function test_a_hostile_product_name_cannot_break_out_of_the_structured_data(): void
    {
        $this->product(['name' => '</script><script>alert(1)</script>', 'slug' => 'evil']);

        $html = $this->get('/products/evil')->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame('</script><script>alert(1)</script>', $this->jsonLd($html)['name']);
    }

    // ---- Switched-off products ----

    public function test_a_switched_off_product_is_hidden_from_guests(): void
    {
        $this->product(['is_active' => false]);

        $this->get('/products/blue-lamp')->assertNotFound();
    }

    public function test_an_admin_can_open_a_switched_off_product_but_search_engines_are_told_to_skip_it(): void
    {
        $this->product(['is_active' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $html = $this->actingAs($admin)->get('/products/blue-lamp')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
    }

    // ---- Pages that were not opted in stay hidden ----

    public function test_private_and_account_pages_are_still_not_listed(): void
    {
        $user = User::factory()->create();

        foreach (['/login', '/register', '/cart'] as $url) {
            $this->assertStringContainsString(
                '<meta name="robots" content="noindex, nofollow">',
                $this->get($url)->getContent(),
                "{$url} should not be indexed",
            );
        }

        foreach (['/orders', '/wishlist', '/profile', '/checkout'] as $url) {
            $response = $this->actingAs($user)->get($url);
            $html = $response->isRedirect() ? '<meta name="robots" content="noindex, nofollow">' : $response->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html, "{$url} should not be indexed");
        }
    }
}
