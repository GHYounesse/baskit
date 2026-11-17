<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use SimpleXMLElement;
use Tests\TestCase;

class RobotsAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://shop.example.com', 'app.name' => 'Baskit', 'app.env' => 'production']);
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::factory()->create($attributes + [
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 5,
        ]);
    }

    /** @return list<string> every <loc> of the sitemap */
    private function locations(): array
    {
        $xml = new SimpleXMLElement($this->get('/sitemap.xml')->assertOk()->getContent());

        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
        }

        return $locations;
    }

    // ---- robots.txt ----

    public function test_there_is_no_static_robots_file_hiding_the_generated_one(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_robots_in_production_blocks_private_areas_and_points_to_the_sitemap(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("User-agent: *\n", $body);
        foreach (['/admin', '/checkout', '/orders', '/wishlist', '/profile', '/stripe/', '/assistant/', '/search/suggest'] as $area) {
            $this->assertStringContainsString("Disallow: {$area}\n", $body);
        }
        $this->assertStringContainsString("Sitemap: https://shop.example.com/sitemap.xml\n", $body);
    }

    public function test_robots_leaves_the_public_shop_and_the_pages_that_carry_noindex_open(): void
    {
        $body = $this->get('/robots.txt')->getContent();

        $this->assertStringNotContainsString("Disallow: /\n", $body);
        foreach (['/shop', '/products', '/cart', '/login', '/register', '/build'] as $open) {
            $this->assertStringNotContainsString("Disallow: {$open}", $body, "{$open} must stay crawlable");
        }
    }

    public function test_robots_outside_production_blocks_everything_and_names_no_sitemap(): void
    {
        foreach (['local', 'staging', 'testing'] as $environment) {
            config(['app.env' => $environment]);

            $body = $this->get('/robots.txt')->assertOk()->getContent();

            $this->assertSame("User-agent: *\nDisallow: /\n", $body, $environment);
        }
    }

    public function test_robots_is_plain_text_and_cacheable(): void
    {
        $response = $this->get('/robots.txt');

        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
    }

    public function test_the_sitemap_address_follows_app_url_not_the_request_host(): void
    {
        $body = $this->withHeader('Host', 'evil.example.net')->get('/robots.txt')->getContent();

        $this->assertStringNotContainsString('evil.example.net', $body);
        $this->assertStringContainsString('https://shop.example.com/sitemap.xml', $body);
    }

    // ---- sitemap.xml ----

    public function test_the_sitemap_is_valid_xml_in_the_sitemap_format(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $this->product($books);

        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = new SimpleXMLElement($response->getContent());

        $this->assertSame('urlset', $xml->getName());
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->getNamespaces()[''] ?? null);
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
    }

    public function test_an_empty_store_lists_only_the_home_page(): void
    {
        $this->assertSame(['https://shop.example.com/'], $this->locations());
    }

    public function test_it_lists_home_shop_categories_and_active_products(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $toys = Category::create(['name' => 'Toys', 'slug' => 'toys']);
        $this->product($books, ['slug' => 'novel']);
        $this->product($toys, ['slug' => 'robot']);

        $locations = $this->locations();

        $this->assertSame([
            'https://shop.example.com/',
            'https://shop.example.com/shop',
            'https://shop.example.com/shop?category=books',
            'https://shop.example.com/shop?category=toys',
            'https://shop.example.com/products/novel',
            'https://shop.example.com/products/robot',
        ], $locations);
    }

    public function test_switched_off_products_and_empty_categories_are_left_out(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $hidden = Category::create(['name' => 'Hidden', 'slug' => 'hidden']);
        Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $this->product($books, ['slug' => 'novel']);
        $this->product($hidden, ['slug' => 'secret', 'is_active' => false]);

        $locations = $this->locations();

        $this->assertContains('https://shop.example.com/products/novel', $locations);
        $this->assertNotContains('https://shop.example.com/products/secret', $locations);
        $this->assertNotContains('https://shop.example.com/shop?category=hidden', $locations);
        $this->assertNotContains('https://shop.example.com/shop?category=empty', $locations);
    }

    public function test_a_sold_out_product_stays_because_its_page_still_exists(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $this->product($books, ['slug' => 'sold-out', 'stock' => 0]);

        $this->assertContains('https://shop.example.com/products/sold-out', $this->locations());
    }

    public function test_pages_that_are_not_listed_by_design_never_appear(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $this->product($books);

        $body = $this->get('/sitemap.xml')->getContent();

        foreach (['search=', 'sort=', 'page=', '/cart', '/admin', '/login', '/orders'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, $forbidden);
        }
    }

    public function test_every_entry_is_an_absolute_address_on_the_configured_site(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $this->product($books);

        foreach ($this->locations() as $location) {
            $this->assertStringStartsWith('https://shop.example.com/', $location);
        }
    }

    public function test_products_carry_the_date_they_last_changed_and_the_home_page_none(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        $old = $this->product($books, ['slug' => 'old']);
        $old->forceFill(['updated_at' => Carbon::parse('2026-03-01 10:00:00')])->saveQuietly();
        $new = $this->product($books, ['slug' => 'new']);
        $new->forceFill(['updated_at' => Carbon::parse('2026-09-15 08:30:00')])->saveQuietly();

        $xml = new SimpleXMLElement($this->get('/sitemap.xml')->getContent());
        $byLoc = [];
        foreach ($xml->url as $url) {
            $byLoc[(string) $url->loc] = isset($url->lastmod) ? (string) $url->lastmod : null;
        }

        $this->assertNull($byLoc['https://shop.example.com/']);
        $this->assertStringStartsWith('2026-03-01T10:00:00', $byLoc['https://shop.example.com/products/old']);
        $this->assertStringStartsWith('2026-09-15T08:30:00', $byLoc['https://shop.example.com/products/new']);
        // The shop and the category are as recent as their newest product.
        $this->assertStringStartsWith('2026-09-15T08:30:00', $byLoc['https://shop.example.com/shop']);
        $this->assertStringStartsWith('2026-09-15T08:30:00', $byLoc['https://shop.example.com/shop?category=books']);
    }

    public function test_an_ampersand_in_a_slug_cannot_break_the_xml(): void
    {
        $books = Category::create(['name' => 'Odd', 'slug' => 'tom&jerry']);
        $this->product($books, ['slug' => 'a&b<c']);

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('tom&amp;jerry', $body);
        $this->assertNotFalse(simplexml_load_string($body), 'The sitemap is no longer valid XML');
    }

    // ---- Both files are plain: no session, no cookies ----

    public function test_neither_file_sets_a_cookie_or_starts_a_session(): void
    {
        $this->assertSame([], $this->get('/robots.txt')->headers->getCookies());
        $this->assertSame([], $this->get('/sitemap.xml')->headers->getCookies());
    }

    public function test_both_files_are_open_to_everyone_without_logging_in(): void
    {
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
    }
}
