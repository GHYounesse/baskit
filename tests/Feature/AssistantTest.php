<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Assistant\AssistantContext;
use App\Services\Assistant\IntentClassifier;
use App\Services\Assistant\RuleBasedEngine;
use App\Services\VariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private Store $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new Store('test', new ArraySessionHandler(10));
        $this->session->start();
        $this->app->instance(AssistantContext::class, new AssistantContext($this->session));
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::factory()->create($attributes + ['name' => $name, 'stock' => 10, 'price_cents' => 2000, 'category_id' => null]);
    }

    private function variant(Product $product, string $size, string $color, int $stock): void
    {
        app(VariantService::class)->create($product, ['size' => $size, 'color' => $color, 'stock' => $stock]);
    }

    private function ask(string $message, ?User $as = null): array
    {
        return app(RuleBasedEngine::class)->respond($message, $as)->toArray();
    }

    private function text(array $reply): string
    {
        return collect($reply['parts'])->where('type', 'text')->pluck('text')->implode("\n");
    }

    /** @return list<string> */
    private function productNames(array $reply): array
    {
        return collect($reply['parts'])->where('type', 'products')->flatMap(fn ($p) => array_column($p['products'], 'name'))->all();
    }

    private function intent(string $message): string
    {
        return app(IntentClassifier::class)->understand($message, app(AssistantContext::class))->intent;
    }

    private function entities(string $message): array
    {
        return app(IntentClassifier::class)->understand($message, app(AssistantContext::class))->entities;
    }

    private function orderFor(User $user, string $status = 'paid', array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'user_id' => $user->id, 'status' => $status, 'total_cents' => 4000,
            'stripe_payment_intent_id' => 'pi_'.uniqid(),
        ]);
        $order->items()->create(['product_id' => null, 'product_name' => 'Wireless Headphones', 'unit_price_cents' => 4000, 'quantity' => 1]);

        return $order;
    }

    // ---- Understanding what was asked ----

    /**
     * @return array<string, array{string, string}>
     */
    public static function intents(): array
    {
        return [
            'greeting' => ['hi', 'greeting'],
            'greeting 2' => ['Hello there!', 'greeting'],
            'thanks' => ['thanks a lot', 'thanks'],
            'bye' => ['bye', 'bye'],
            'help' => ['what can you do?', 'help'],
            'where is my order' => ["Where's my order?", 'order_status'],
            'track' => ['can you track my package', 'order_status'],
            'order number' => ['order #12', 'order_status'],
            'my orders' => ['show my orders', 'order_status'],
            'cancel' => ['I want to cancel my order', 'order_cancel'],
            'refund' => ['how do I get a refund', 'order_cancel'],
            'return' => ['can I return something', 'order_cancel'],
            'coupon' => ['do you have any discount codes', 'coupons'],
            'promo' => ['where do I enter a promo code', 'coupons'],
            'payment' => ['how do I pay', 'payment'],
            'paypal' => ['do you take PayPal', 'payment'],
            'shipping' => ['how long does shipping take', 'shipping'],
            'human' => ['can I talk to a human', 'human'],
            'wishlist' => ['how do I save things to my wishlist', 'wishlist'],
            'review howto' => ['how can I leave a review', 'reviews_howto'],
            'account' => ['how do I create an account', 'account'],
            'categories' => ['what categories do you have', 'categories'],
            'popular' => ["what's popular right now", 'best_sellers'],
            'new' => ['any new arrivals', 'new_arrivals'],
            'search' => ['find me wireless headphones', 'product_search'],
            'bare product' => ['headphones', 'product_search'],
            'price search' => ['a gift under $50', 'product_search'],
            'category search' => ['show me books', 'product_search'],
            'details' => ['tell me about the cotton t-shirt', 'product_details'],
            'price question' => ['how much is the smartwatch', 'product_details'],
            'availability' => ['is the smartwatch in stock', 'availability'],
            'gibberish' => ['', 'help'],
        ];
    }

    #[DataProvider('intents')]
    public function test_it_understands_what_was_asked(string $message, string $expected): void
    {
        $this->product('Wireless Headphones');
        $this->product('Smartwatch');
        $this->product('Cotton T-Shirt');
        Category::create(['name' => 'Books', 'slug' => 'books']);

        $this->assertSame($expected, $this->intent($message), $message);
    }

    public function test_cancelling_beats_tracking_when_both_words_appear(): void
    {
        $this->assertSame('order_cancel', $this->intent('cancel my order, where is it'));
    }

    public function test_odd_input_never_breaks_it(): void
    {
        $this->product('Mug');

        foreach (['<script>alert(1)</script>', '%%%', "'; DROP TABLE products; --", '🙂🙂🙂', str_repeat('a', 5000), "\n\n\t", '12345678901234567890', '#', '$', 'order #'] as $nasty) {
            $reply = $this->ask($nasty);

            $this->assertNotEmpty($reply['parts'], $nasty);
        }
    }

    // ---- Details found in a message ----

    public function test_it_reads_prices(): void
    {
        $this->assertSame(5000, $this->entities('gifts under $50')['max_price_cents']);
        $this->assertSame(5000, $this->entities('less than 50 dollars')['max_price_cents']);
        $this->assertSame(120000, $this->entities('laptops below $1,200')['max_price_cents']);
        $this->assertSame(2550, $this->entities('under $25.50')['max_price_cents']);
        $this->assertSame(10000, $this->entities('over $100')['min_price_cents']);

        $between = $this->entities('between $20 and $40');
        $this->assertSame([2000, 4000], [$between['min_price_cents'], $between['max_price_cents']]);

        $this->assertArrayNotHasKey('max_price_cents', $this->entities('wireless headphones'));
    }

    public function test_it_reads_sorting_words(): void
    {
        $this->assertSame('price_asc', $this->entities('cheapest headphones')['sort']);
        $this->assertSame('price_desc', $this->entities('most expensive watch')['sort']);
        $this->assertSame('rating', $this->entities('best rated speakers')['sort']);
        $this->assertSame('newest', $this->entities('newest shoes')['sort']);
    }

    public function test_it_reads_sizes_colours_categories_and_order_numbers(): void
    {
        $this->variant($this->product('Tee'), 'M', 'Navy', 3);
        Category::create(['name' => 'Home & Kitchen', 'slug' => 'home-kitchen']);

        $this->assertSame('M', $this->entities('the tee in medium')['size']);
        $this->assertSame('XL', $this->entities('do you have it in size xl')['size']);
        $this->assertSame('42', $this->entities('shoes size 42')['size']);
        $this->assertSame('XXL', $this->entities('hoodie xxl')['size']);
        $this->assertSame('Navy', $this->entities('anything in navy')['color']);
        $this->assertSame('home-kitchen', $this->entities('something for the kitchen')['category']);
        $this->assertSame(12, $this->entities('where is order number 12')['order_id']);
        $this->assertSame(7, $this->entities('status of #7')['order_id']);
        $this->assertSame(1, $this->entities('what about the second one')['ordinal']);
    }

    public function test_what_is_left_after_the_filler_is_the_thing_asked_about(): void
    {
        $this->assertSame('wireless headphones', $this->entities('can you find me some wireless headphones under $100 please')['query']);

        $this->variant($this->product('Tee'), 'M', 'Blue', 3);   // so "blue" is a colour the shop sells
        $this->assertSame('', $this->entities('what about in blue?')['query']);
    }

    // ---- Finding products ----

    public function test_it_finds_products_with_their_details(): void
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $headphones = $this->product('Wireless Headphones', ['category_id' => $category->id, 'price_cents' => 14999, 'image_path' => 'https://example.test/h.jpg']);
        Review::factory()->create(['product_id' => $headphones->id, 'rating' => 5]);
        $this->product('Cotton T-Shirt');

        $reply = $this->ask('wireless headphones');

        $card = $reply['parts'][1]['products'][0];
        $this->assertSame('Wireless Headphones', $card['name']);
        $this->assertSame("/products/{$headphones->slug}", $card['url']);
        $this->assertSame([14999, 'Electronics', 5.0, 1, true], [$card['price_cents'], $card['category'], $card['rating'], $card['reviews'], $card['in_stock']]);
        $this->assertSame(['Wireless Headphones'], $this->productNames($reply));
    }

    public function test_it_applies_price_and_category_filters(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->product('Cheap Speaker', ['category_id' => $audio->id, 'price_cents' => 2000]);
        $this->product('Pricey Speaker', ['category_id' => $audio->id, 'price_cents' => 9000]);
        $this->product('Toy Speaker', ['price_cents' => 1000]);

        $this->assertEqualsCanonicalizing(['Cheap Speaker', 'Toy Speaker'], $this->productNames($this->ask('speakers under $50')));
        $this->assertEqualsCanonicalizing(['Cheap Speaker', 'Pricey Speaker'], $this->productNames($this->ask('speakers in audio')));
        $this->assertSame(['Pricey Speaker'], $this->productNames($this->ask('speaker over $50')));
    }

    public function test_it_sorts_when_asked(): void
    {
        $this->product('Alpha Lamp', ['price_cents' => 5000]);
        $this->product('Beta Lamp', ['price_cents' => 1000]);
        $this->product('Gamma Lamp', ['price_cents' => 3000]);

        $this->assertSame(['Beta Lamp', 'Gamma Lamp', 'Alpha Lamp'], $this->productNames($this->ask('cheapest lamps')));
        $this->assertSame(['Alpha Lamp', 'Gamma Lamp', 'Beta Lamp'], $this->productNames($this->ask('most expensive lamps')));
    }

    public function test_it_never_shows_hidden_products(): void
    {
        $this->product('Secret Gadget', ['is_active' => false]);
        $this->product('Public Gadget');

        $this->assertSame(['Public Gadget'], $this->productNames($this->ask('gadget')));
    }

    public function test_things_in_stock_come_before_sold_out_ones(): void
    {
        $this->product('Aardvark Lamp', ['stock' => 0]);
        $this->product('Zebra Lamp', ['stock' => 5]);

        $this->assertSame(['Zebra Lamp', 'Aardvark Lamp'], $this->productNames($this->ask('lamp')));
    }

    public function test_a_misspelling_is_corrected_and_says_so(): void
    {
        $this->product('Wireless Headphones');

        $reply = $this->ask('headphnes');

        $this->assertSame(['Wireless Headphones'], $this->productNames($reply));
        $this->assertStringContainsString('instead of “headphnes”', $this->text($reply));
    }

    public function test_extra_words_that_match_nothing_are_dropped_honestly(): void
    {
        $this->product('Smartwatch');

        $reply = $this->ask('smartwatch waterproof');

        $this->assertSame(['Smartwatch'], $this->productNames($reply));
        $this->assertStringContainsString('couldn\'t match “smartwatch waterproof” exactly', $this->text($reply));
    }

    public function test_when_nothing_matches_it_says_so_and_offers_a_way_forward(): void
    {
        $this->product('Mug');

        $reply = $this->ask('do you have a unicorn costume');

        $this->assertSame([], $this->productNames($reply));
        $this->assertStringContainsString('couldn\'t find anything for “unicorn costume”', $this->text($reply));
        $this->assertNotEmpty($reply['suggestions']);
    }

    public function test_long_results_are_paged_with_show_more(): void
    {
        foreach (range(1, 9) as $i) {
            $this->product("Gadget {$i}", ['price_cents' => 1000 + $i]);
        }

        $first = $this->ask('gadgets');
        $this->assertCount(4, $this->productNames($first));
        $this->assertStringContainsString('Showing 4 of 9', $this->text($first));
        $this->assertContains('Show more', $first['suggestions']);

        $second = $this->ask('show more');
        $third = $this->ask('more');
        $done = $this->ask('more');

        $all = [...$this->productNames($first), ...$this->productNames($second), ...$this->productNames($third)];
        $this->assertCount(9, array_unique($all));
        $this->assertStringContainsString("That's everything", $this->text($done));
    }

    // ---- Follow-ups remember what came before ----

    public function test_a_bare_filter_refines_the_last_search_but_an_explicit_request_starts_over(): void
    {
        $this->product('Cheap Lamp', ['price_cents' => 1500]);
        $this->product('Pricey Lamp', ['price_cents' => 9000]);
        $this->product('Toy Car', ['price_cents' => 800]);

        $this->ask('lamps');

        $this->assertSame(['Cheap Lamp'], $this->productNames($this->ask('under $20')));           // still lamps
        $this->assertEqualsCanonicalizing(['Toy Car', 'Cheap Lamp'], $this->productNames($this->ask('find me something under $20')));   // a fresh search
    }

    public function test_cheaper_ones_re_sorts_the_last_search(): void
    {
        $this->product('Alpha Lamp', ['price_cents' => 5000]);
        $this->product('Beta Lamp', ['price_cents' => 1000]);

        $this->ask('lamps');

        $this->assertSame(['Beta Lamp', 'Alpha Lamp'], $this->productNames($this->ask('cheaper ones')));
    }

    public function test_a_colour_follow_up_filters_the_last_search(): void
    {
        $tee = $this->product('Cotton Tee');
        $this->variant($tee, 'M', 'Blue', 3);
        $hat = $this->product('Cotton Hat');
        $this->variant($hat, '', 'Red', 3);

        $this->ask('cotton');

        $this->assertSame(['Cotton Tee'], $this->productNames($this->ask('what about in blue?')));
    }

    public function test_the_second_one_means_the_second_product_just_shown(): void
    {
        $this->product('Alpha Lamp', ['price_cents' => 1000]);
        $this->product('Beta Lamp', ['price_cents' => 2000]);
        $this->ask('cheapest lamps');

        $this->assertSame(['Beta Lamp'], $this->productNames($this->ask('tell me about the second one')));
        $this->assertSame(['Beta Lamp'], $this->productNames($this->ask('the second one')));
    }

    public function test_it_in_stock_means_the_product_we_were_just_talking_about(): void
    {
        $this->product('Smartwatch', ['stock' => 3]);
        $this->ask('tell me about the smartwatch');

        $reply = $this->ask('is it in stock?');

        $this->assertSame(['Smartwatch'], $this->productNames($reply));
        $this->assertStringContainsString('only 3 are left', $this->text($reply));
    }

    public function test_a_new_chat_forgets_the_context(): void
    {
        $this->product('Alpha Lamp');
        $this->ask('lamps');

        app(RuleBasedEngine::class)->reset();

        $this->assertStringContainsString('nothing to show more of', $this->text($this->ask('more')));
    }

    // ---- Stock and variants ----

    public function test_it_answers_stock_questions_for_a_simple_product(): void
    {
        $this->product('Mug', ['stock' => 20]);
        $this->product('Gone Mug', ['stock' => 0]);

        $this->assertStringContainsString('Mug is in stock', $this->text($this->ask('is the mug available')));
        $this->assertStringContainsString('sold out', $this->text($this->ask('is the gone mug in stock')));
    }

    public function test_it_answers_size_and_colour_questions_from_the_variants(): void
    {
        $tee = $this->product('Cotton Tee');
        $this->variant($tee, 'S', 'Blue', 5);
        $this->variant($tee, 'M', 'Blue', 0);
        $this->variant($tee, 'M', 'Red', 2);

        $yes = $this->text($this->ask('is the cotton tee available in small'));
        $this->assertStringContainsString('Yes, Cotton Tee is in stock in size S', $yes);

        $soldOut = $this->text($this->ask('do you have the cotton tee in medium blue'));
        $this->assertStringContainsString('size M', $soldOut);
        $this->assertStringContainsString('Blue', $soldOut);

        $this->variant($this->product('Other Tee'), 'M', 'Green', 3);   // so "green" is a colour the shop sells

        $missing = $this->text($this->ask('is the cotton tee in stock in green'));
        $this->assertStringContainsString("doesn't come in", $missing);
        $this->assertStringContainsString('It comes in sizes S, M and colours Blue, Red', $missing);
    }

    public function test_a_colour_question_about_a_product_without_options_is_answered_honestly(): void
    {
        $this->variant($this->product('Other Tee'), 'M', 'Blue', 3);   // so "blue" is a colour the shop knows
        $this->product('Plain Mug');

        $this->assertStringContainsString("doesn't come in different sizes or colours", $this->text($this->ask('is the plain mug available in blue')));
    }

    public function test_options_are_summarised_on_a_product_card(): void
    {
        $tee = $this->product('Cotton Tee');
        $this->variant($tee, 'S', 'Blue', 5);
        $this->variant($tee, 'M', 'Blue', 5);

        $card = $this->ask('cotton tee')['parts'][1]['products'][0];

        $this->assertSame('2 sizes · 1 colour', $card['options']);
    }

    // ---- Browsing ----

    public function test_it_lists_categories_with_counts(): void
    {
        $books = Category::create(['name' => 'Books', 'slug' => 'books']);
        Category::create(['name' => 'Empty', 'slug' => 'empty']);
        $this->product('Novel', ['category_id' => $books->id]);

        $reply = $this->ask('what categories do you have');

        $this->assertStringContainsString('Books (1)', $this->text($reply));
        $this->assertStringNotContainsString('Empty', $this->text($reply));
        $this->assertContains('Show me Books', $reply['suggestions']);
    }

    public function test_it_shows_new_arrivals_and_best_sellers(): void
    {
        $this->product('Older Lamp');
        $this->travel(1)->hour();
        $this->product('Newer Lamp');

        $this->assertSame(['Newer Lamp', 'Older Lamp'], $this->productNames($this->ask('any new arrivals')));
        $this->assertNotEmpty($this->productNames($this->ask("what's popular right now")));
    }

    // ---- Store information ----

    public function test_it_explains_how_the_store_works_from_its_configured_wording(): void
    {
        $this->assertStringContainsString('Stripe', $this->text($this->ask('how do I pay')));
        $this->assertStringContainsString('entered in your cart', $this->text($this->ask('do you have discount codes')));
        $this->assertStringContainsString("aren't listed", $this->text($this->ask('how long does delivery take')));
        $this->assertStringContainsString('no live chat', $this->text($this->ask('I want to talk to a person')));
        $this->assertStringContainsString('heart', $this->text($this->ask('how do I use the wishlist')));
        $this->assertStringContainsString('once you have bought it', $this->text($this->ask('how do I leave a review')));
    }

    public function test_it_does_not_reveal_or_check_discount_codes(): void
    {
        $text = $this->text($this->ask('is the code WELCOME10 valid'));

        $this->assertStringContainsString("can't look up or list codes", $text);
    }

    public function test_it_says_it_does_not_know_instead_of_guessing(): void
    {
        $reply = $this->ask('what is the airspeed velocity of an unladen swallow');

        $this->assertStringContainsString("I'm not sure I understood", $this->text($reply));
        $this->assertNotEmpty($reply['suggestions']);
    }

    public function test_every_suggested_reply_is_understood(): void
    {
        $this->product('Mug');
        $user = User::factory()->create();

        foreach (['hi', "what's popular right now", 'what categories do you have', 'nothing to see here', 'mug'] as $message) {
            foreach ($this->ask($message, $user)['suggestions'] as $chip) {
                $this->assertNotSame('unknown', $this->intent($chip), "Suggestion “{$chip}” is not understood");
            }
        }
    }

    // ---- Orders ----

    public function test_guests_are_asked_to_sign_in_before_orders_are_shown(): void
    {
        $reply = $this->ask('where is order #1');

        $this->assertStringContainsString('signed in', $this->text($reply));
        $this->assertSame([], collect($reply['parts'])->where('type', 'orders')->all());
        $this->assertSame('/login', collect($reply['parts'])->firstWhere('type', 'links')['links'][0]['href']);
    }

    public function test_a_customer_sees_their_order_with_tracking_and_timeline(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user, 'shipped', ['tracking_carrier' => 'UPS', 'tracking_number' => '1Z999']);
        $order->events()->create(['type' => 'shipped', 'message' => 'Shipped with UPS.']);

        $reply = $this->ask("where is order #{$order->id}", $user);

        $text = $this->text($reply);
        $this->assertStringContainsString("Order #{$order->id} is on its way", $text);
        $this->assertStringContainsString('with UPS, tracking number 1Z999', $text);

        $card = collect($reply['parts'])->firstWhere('type', 'orders')['orders'][0];
        $this->assertSame([$order->id, 'shipped', ['carrier' => 'UPS', 'number' => '1Z999'], false], [$card['id'], $card['status'], $card['tracking'], $card['can_cancel']]);
        $this->assertSame(['Order placed', 'Shipped'], array_column($card['timeline'], 'title'));
        $this->assertSame([['name' => 'Wireless Headphones', 'quantity' => 1]], $card['items']);
    }

    public function test_nobody_can_see_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = $this->orderFor($owner, 'shipped', ['tracking_number' => 'SECRET-TRACK']);

        foreach (["where is order #{$order->id}", "cancel order {$order->id}", "track order {$order->id}"] as $message) {
            $reply = $this->ask($message, $stranger);
            $json = json_encode($reply);

            $this->assertStringContainsString("couldn't find order #{$order->id}", $this->text($reply), $message);
            $this->assertStringNotContainsString('SECRET-TRACK', $json);
            $this->assertSame([], collect($reply['parts'])->where('type', 'orders')->all());
        }
    }

    public function test_a_missing_order_looks_the_same_as_someone_elses(): void
    {
        $user = User::factory()->create();
        $theirs = $this->orderFor(User::factory()->create());

        $this->assertSame(
            str_replace((string) $theirs->id, 'N', $this->text($this->ask("where is order #{$theirs->id}", $user))),
            str_replace('99999', 'N', $this->text($this->ask('where is order #99999', $user))),
        );
    }

    public function test_without_a_number_it_lists_the_recent_orders_or_shows_the_only_one(): void
    {
        $user = User::factory()->create();

        $this->assertStringContainsString("don't have any orders", $this->text($this->ask("where's my order", $user)));

        $only = $this->orderFor($user);
        $one = $this->ask("where's my order", $user);
        $this->assertStringContainsString("Order #{$only->id}", $this->text($one));

        foreach (range(1, 4) as $i) {
            $this->orderFor($user);
        }
        $many = $this->ask("where's my order", $user);
        $this->assertCount(3, collect($many['parts'])->firstWhere('type', 'orders')['orders']);
    }

    public function test_it_that_follows_an_order_means_that_order(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user, 'paid');
        $this->orderFor($user, 'delivered');

        $this->ask("where is order #{$order->id}", $user);
        $reply = $this->ask('can I cancel it?', $user);

        $this->assertStringContainsString("Order #{$order->id} hasn't shipped yet", $this->text($reply));
    }

    public function test_cancelling_depends_on_where_the_order_is(): void
    {
        $user = User::factory()->create();
        $paid = $this->orderFor($user, 'paid');
        $shipped = $this->orderFor($user, 'shipped');
        $cancelled = $this->orderFor($user, 'cancelled');
        $pending = $this->orderFor($user, 'pending', ['stripe_payment_intent_id' => null]);

        $paidReply = $this->ask("cancel order {$paid->id}", $user);
        $this->assertStringContainsString("hasn't shipped yet", $this->text($paidReply));
        $card = collect($paidReply['parts'])->firstWhere('type', 'orders')['orders'][0];
        $this->assertSame(["/orders/{$paid->id}", true], [$card['url'], $card['can_cancel']]);

        $this->assertStringContainsString("can't be cancelled online", $this->text($this->ask("cancel order {$shipped->id}", $user)));
        $this->assertStringContainsString('already cancelled and refunded', $this->text($this->ask("cancel order {$cancelled->id}", $user)));
        $this->assertStringContainsString('waiting for payment', $this->text($this->ask("cancel order {$pending->id}", $user)));
    }

    public function test_the_assistant_only_reads_and_never_changes_an_order(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user, 'paid');

        $this->ask("please cancel order {$order->id} right now", $user);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(['placed'], $order->fresh()->events->pluck('type')->all());
    }

    public function test_general_cancellation_help_works_for_guests(): void
    {
        $text = $this->text($this->ask('how do I cancel an order'));

        $this->assertStringContainsString('cancel an order yourself', $text);
        $this->assertStringContainsString("can't be cancelled online", $text);
    }
}
