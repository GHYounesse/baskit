<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Search\FtsProductSearch;
use App\Services\Search\LikeProductSearch;
use App\Services\Search\ProductSearch;
use App\Services\Search\SearchTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The two engines (SQLite full-text and the portable LIKE one) follow the
 * same rules, so every case here runs against both.
 */
class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<ProductSearch>}>
     */
    public static function engines(): array
    {
        return [
            'FTS5' => [FtsProductSearch::class],
            'LIKE' => [LikeProductSearch::class],
        ];
    }

    private function product(string $name, array $attributes = []): Product
    {
        // category_id defaults to null so a product only has a category when a test gives it one.
        return Product::factory()->create($attributes + ['name' => $name, 'description' => 'A plain description.', 'category_id' => null]);
    }

    private function engine(string $class): ProductSearch
    {
        return new $class;
    }

    public function test_the_full_text_engine_is_used_on_sqlite(): void
    {
        $this->assertInstanceOf(FtsProductSearch::class, app(ProductSearch::class));
    }

    #[DataProvider('engines')]
    public function test_it_finds_products_by_a_word_in_the_name(string $class): void
    {
        $headphones = $this->product('Wireless Headphones');
        $this->product('Cotton T-Shirt');

        $this->assertSame([$headphones->id], $this->engine($class)->ids('headphones'));
    }

    #[DataProvider('engines')]
    public function test_it_matches_the_start_of_a_word_so_it_works_while_typing(string $class): void
    {
        $headphones = $this->product('Wireless Headphones');

        $this->assertSame([$headphones->id], $this->engine($class)->ids('head'));
        $this->assertSame([$headphones->id], $this->engine($class)->ids('wireless head'));
    }

    #[DataProvider('engines')]
    public function test_every_word_must_match(string $class): void
    {
        $this->product('Wireless Headphones');
        $this->product('Wireless Mouse');

        $this->assertCount(2, $this->engine($class)->ids('wireless'));
        $this->assertCount(1, $this->engine($class)->ids('wireless mouse'));
        $this->assertSame([], $this->engine($class)->ids('wireless banana'));
    }

    #[DataProvider('engines')]
    public function test_a_name_match_outranks_a_description_mention(string $class): void
    {
        $mention = $this->product('Travel Case', ['description' => 'Fits a speaker, a cable and more.']);
        $named = $this->product('Bluetooth Speaker', ['description' => 'Loud and portable.']);

        $this->assertSame([$named->id, $mention->id], $this->engine($class)->ids('speaker'));
    }

    #[DataProvider('engines')]
    public function test_it_also_searches_the_category_name(string $class): void
    {
        $kitchen = Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);
        $inKitchen = $this->product('Bamboo Cutting Board', ['category_id' => $kitchen->id]);
        $this->product('Cotton T-Shirt');

        $this->assertSame([$inKitchen->id], $this->engine($class)->ids('kitchen'));
    }

    #[DataProvider('engines')]
    public function test_it_ignores_case(string $class): void
    {
        $product = $this->product('Wireless Headphones');

        $this->assertSame([$product->id], $this->engine($class)->ids('WIRELESS'));
        $this->assertSame([$product->id], $this->engine($class)->ids('HeadPhones'));
    }

    public function test_the_full_text_engine_ignores_accents_both_ways(): void
    {
        // (The portable engine leaves accents to the database's collation, e.g.
        // MySQL's utf8mb4_unicode_ci; SQLite's LIKE does not fold them.)
        $cafe = $this->product('Café Espresso Maker');
        $engine = new FtsProductSearch;

        $this->assertSame([$cafe->id], $engine->ids('cafe'));
        $this->assertSame([$cafe->id], $engine->ids('CAFÉ'));
    }

    #[DataProvider('engines')]
    public function test_inactive_products_are_never_found(string $class): void
    {
        $this->product('Hidden Gadget', ['is_active' => false]);
        $visible = $this->product('Visible Gadget');

        $this->assertSame([$visible->id], $this->engine($class)->ids('gadget'));
    }

    #[DataProvider('engines')]
    public function test_odd_input_is_harmless(string $class): void
    {
        $product = $this->product('Wireless Headphones');
        $engine = $this->engine($class);

        // Characters that mean something in search syntax, plus SQL-looking text.
        foreach (['"', '*', '-', 'OR', 'NEAR(', 'name:', '(((', "'; DROP TABLE products; --", '%', '_', '\\', '   '] as $nasty) {
            $this->assertIsArray($engine->ids($nasty), "Input: {$nasty}");
        }

        $this->assertSame([$product->id], $engine->ids('"wireless" -- headphones*'));
        $this->assertSame([], $engine->ids(''));
        $this->assertSame([], $engine->ids('!!!'));
    }

    #[DataProvider('engines')]
    public function test_the_limit_caps_the_results(string $class): void
    {
        foreach (range(1, 5) as $i) {
            $this->product("Gadget {$i}");
        }

        $this->assertCount(3, $this->engine($class)->ids('gadget', 3));
    }

    public function test_the_index_follows_changes_to_a_product(): void
    {
        $product = $this->product('Plain Mug');
        $engine = app(ProductSearch::class);

        $this->assertSame([], $engine->ids('thermos'));

        $product->update(['name' => 'Steel Thermos']);
        $this->assertSame([$product->id], $engine->ids('thermos'));
        $this->assertSame([], $engine->ids('mug'));

        $product->delete();
        $this->assertSame([], $engine->ids('thermos'));
    }

    public function test_renaming_a_category_updates_the_search_for_its_products(): void
    {
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $product = $this->product('Pocket Torch', ['category_id' => $category->id]);
        $engine = app(ProductSearch::class);

        $this->assertSame([$product->id], $engine->ids('gadgets'));

        $category->update(['name' => 'Outdoors']);

        $this->assertSame([], $engine->ids('gadgets'));
        $this->assertSame([$product->id], $engine->ids('outdoors'));
    }

    public function test_stock_changes_do_not_touch_the_index(): void
    {
        $product = $this->product('Desk Lamp', ['stock' => 5]);

        $product->decrement('stock', 2);

        $this->assertSame([$product->id], app(ProductSearch::class)->ids('lamp'));
    }

    public function test_the_reindex_command_rebuilds_the_index(): void
    {
        $product = $this->product('Desk Lamp');
        \DB::delete('DELETE FROM product_search');
        $this->assertSame([], app(ProductSearch::class)->ids('lamp'));

        $this->artisan('search:reindex')->expectsOutputToContain('Indexed 1 products')->assertSuccessful();

        $this->assertSame([$product->id], app(ProductSearch::class)->ids('lamp'));
    }

    public function test_search_terms_are_cleaned_and_capped(): void
    {
        $this->assertSame(['cafe', 'latte', '2'], SearchTerms::from('  Café, LATTE! #2 '));
        $this->assertSame(['a', 'b'], SearchTerms::from('a a b'));
        $this->assertCount(6, SearchTerms::from('one two three four five six seven eight'));
        $this->assertSame([], SearchTerms::from('***'));
    }
}
