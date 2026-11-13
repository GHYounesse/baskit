<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\PageSeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which shop pages Google may list, and under which address (see StoreSeo::shop()).
 */
class IndexingRulesTest extends TestCase
{
    use RefreshDatabase;

    private const NOINDEX_FOLLOW = '<meta name="robots" content="noindex, follow">';

    private const INDEX = '<meta name="robots" content="index, follow">';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://shop.example.com', 'app.name' => 'Baskit']);
    }

    /** @return Category the category holding the products */
    private function shelf(int $products = 3, string $slug = 'books', string $name = 'Books'): Category
    {
        $category = Category::firstOrCreate(['slug' => $slug], ['name' => $name]);
        Product::factory()->count($products)->create([
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 5,
        ]);

        return $category;
    }

    /** Only the <head>: the page's own data legitimately repeats the visitor's URL. */
    private function headOf(string $url): string
    {
        return explode('</head>', $this->get($url)->assertOk()->getContent())[0];
    }

    // ---- Searches ----

    public function test_a_search_is_not_listed_but_its_links_may_be_followed(): void
    {
        $this->shelf();
        Product::factory()->create(['name' => 'Walnut Desk', 'is_active' => true]);

        $head = $this->headOf('/shop?search=walnut');

        $this->assertStringContainsString(self::NOINDEX_FOLLOW, $head);
    }

    public function test_a_search_has_no_canonical_address(): void
    {
        $this->shelf();

        $head = $this->headOf('/shop?search=anything');

        $this->assertStringNotContainsString('rel="canonical"', $head);
        $this->assertStringNotContainsString('og:url', $head);
    }

    public function test_a_search_inside_a_category_is_not_listed_either(): void
    {
        $this->shelf();

        $this->assertStringContainsString(self::NOINDEX_FOLLOW, $this->headOf('/shop?category=books&search=lamp'));
    }

    public function test_a_blank_search_is_the_normal_shop(): void
    {
        $this->shelf();

        $head = $this->headOf('/shop?search=%20%20');

        $this->assertStringContainsString(self::INDEX, $head);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop">', $head);
    }

    // ---- Pages with nothing to show ----

    public function test_an_unknown_category_is_not_listed_and_is_never_copied_into_a_published_address(): void
    {
        $this->shelf();

        $head = $this->headOf('/shop?category=does-not-exist');

        $this->assertStringContainsString(self::NOINDEX_FOLLOW, $head);
        $this->assertStringNotContainsString('does-not-exist', $head);
        $this->assertStringNotContainsString('rel="canonical"', $head);
    }

    public function test_an_empty_shop_is_not_listed(): void
    {
        $this->assertStringContainsString(self::NOINDEX_FOLLOW, $this->headOf('/shop'));
    }

    public function test_a_category_with_only_switched_off_products_is_not_listed(): void
    {
        $category = Category::create(['name' => 'Hidden', 'slug' => 'hidden']);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);

        $this->assertStringContainsString(self::NOINDEX_FOLLOW, $this->headOf('/shop?category=hidden'));
    }

    public function test_a_page_past_the_last_one_is_not_listed(): void
    {
        $this->shelf(3);

        $head = $this->headOf('/shop?page=99');

        $this->assertStringContainsString(self::NOINDEX_FOLLOW, $head);
        $this->assertStringNotContainsString('rel="canonical"', $head);
    }

    // ---- Pagination ----

    public function test_page_one_has_the_plain_address_even_when_asked_for_explicitly(): void
    {
        $this->shelf(14);

        $head = $this->headOf('/shop?page=1');

        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop">', $head);
        $this->assertStringContainsString('<title inertia>Shop - Baskit</title>', $head);
    }

    public function test_page_two_is_listed_under_its_own_address_and_title(): void
    {
        $this->shelf(14);

        $head = $this->headOf('/shop?page=2');

        $this->assertStringContainsString(self::INDEX, $head);
        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop?page=2">', $head);
        $this->assertStringContainsString('<title inertia>Shop (page 2) - Baskit</title>', $head);
    }

    public function test_page_two_of_a_category_keeps_the_category_in_its_address(): void
    {
        $this->shelf(14);

        $head = $this->headOf('/shop?category=books&page=2');

        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop?category=books&amp;page=2">', $head);
        $this->assertStringContainsString('<title inertia>Books (page 2) - Baskit</title>', $head);
    }

    public function test_sorting_a_later_page_does_not_change_its_address(): void
    {
        $this->shelf(14);

        $head = $this->headOf('/shop?page=2&sort=price_desc');

        $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop?page=2">', $head);
    }

    public function test_a_nonsense_page_number_falls_back_to_page_one(): void
    {
        $this->shelf(14);

        foreach (['abc', '0', '-5', '1.5'] as $page) {
            $head = $this->headOf('/shop?page='.$page);

            $this->assertStringContainsString('<link rel="canonical" href="https://shop.example.com/shop">', $head, "page={$page}");
            $this->assertStringNotContainsString('(page', $head, "page={$page}");
        }
    }

    // ---- The robots wording itself ----

    public function test_the_three_robot_instructions(): void
    {
        $this->assertSame('index, follow', (new PageSeo(title: 'x'))->toArray()['robots']);
        $this->assertSame('noindex, follow', (new PageSeo(noindex: true, followLinks: true))->toArray()['robots']);
        $this->assertSame('noindex, nofollow', (new PageSeo(noindex: true))->toArray()['robots']);
        $this->assertSame('noindex, nofollow', PageSeo::hidden()->toArray()['robots']);
    }

    public function test_the_follow_setting_changes_nothing_for_a_listed_page(): void
    {
        $this->assertSame('index, follow', (new PageSeo(title: 'x', followLinks: false))->toArray()['robots']);
    }
}
