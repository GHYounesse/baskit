<?php

namespace Tests\Feature;

use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class SeoFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://shop.example.com/', 'app.name' => 'Baskit']);
    }

    // ---- The value object ----

    public function test_a_page_with_nothing_set_is_hidden_from_search_engines(): void
    {
        $seo = PageSeo::hidden()->toArray();

        $this->assertSame('noindex, nofollow', $seo['robots']);
        $this->assertSame('Baskit', $seo['title']);
        $this->assertNull($seo['canonical']);
    }

    public function test_a_public_page_gets_the_site_name_in_its_title_and_is_indexable(): void
    {
        $seo = (new PageSeo(title: 'Wireless Headphones', path: '/products/wireless-headphones'))->toArray();

        $this->assertSame('Wireless Headphones - Baskit', $seo['title']);
        $this->assertSame('index, follow', $seo['robots']);
        $this->assertSame('https://shop.example.com/products/wireless-headphones', $seo['canonical']);
    }

    public function test_relative_images_become_absolute_and_full_urls_are_kept(): void
    {
        $this->assertSame('https://shop.example.com/storage/products/a.jpg', PageSeo::absolute('/storage/products/a.jpg'));
        $this->assertSame('https://images.example.org/p.jpg', PageSeo::absolute('https://images.example.org/p.jpg'));
        $this->assertNull(PageSeo::absolute(null));
        $this->assertNull(PageSeo::absolute(''));
    }

    public function test_the_canonical_address_comes_from_app_url_never_from_the_request_host(): void
    {
        $response = $this->withHeader('Host', 'evil.example.net')->get('/login');

        $response->assertOk();
        $this->assertStringNotContainsString('evil.example.net', $response->getContent());
    }

    public function test_descriptions_are_plain_one_line_and_short(): void
    {
        $text = "<p>Great   <b>sound</b>\nfor everyone.</p> ".str_repeat('Long words keep coming. ', 20);

        $description = PageSeo::describe($text);

        $this->assertStringStartsWith('Great sound for everyone.', $description);
        $this->assertStringNotContainsString('<', $description);
        $this->assertStringNotContainsString("\n", $description);
        $this->assertLessThanOrEqual(160, mb_strlen($description));
        $this->assertStringEndsWith('…', $description);
    }

    public function test_an_empty_description_is_omitted(): void
    {
        $this->assertNull(PageSeo::describe('   '));
        $this->assertNull(PageSeo::describe(null));
    }

    // ---- The <head> that visitors and crawlers receive ----

    public function test_pages_that_set_nothing_are_served_with_noindex_and_the_site_title(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('<meta property="og:site_name" content="Baskit">', $html);
        $this->assertStringContainsString('<title inertia>Baskit</title>', $html);
    }

    public function test_the_head_tags_are_written_from_the_seo_data(): void
    {
        $seo = (new PageSeo(
            title: 'Lamp',
            description: 'A lamp.',
            image: '/storage/lamp.jpg',
            path: '/products/lamp',
            type: 'product',
            jsonLd: ['@type' => 'Product', 'name' => 'Lamp'],
        ))->toArray();

        $html = View::make('partials.seo', ['seo' => $seo])->render();

        $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html);
        $this->assertStringContainsString('<meta name="description" content="A lamp.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/products/lamp">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://shop.example.com/storage/lamp.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('"@type":"Product"', $html);
    }

    public function test_product_text_can_never_break_out_of_the_json_ld_script_tag(): void
    {
        $seo = (new PageSeo(
            title: 'Evil',
            jsonLd: ['@type' => 'Product', 'name' => '</script><script>alert(1)</script>'],
        ))->toArray();

        $html = View::make('partials.seo', ['seo' => $seo])->render();

        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertSame(1, substr_count($html, '</script>'));
    }

    public function test_attributes_are_escaped(): void
    {
        $seo = (new PageSeo(title: 'x', description: '"><script>alert(1)</script>'))->toArray();

        $html = View::make('partials.seo', ['seo' => $seo])->render();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }
}
