<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function buy(User $user, Product $product, string $status = 'paid'): void
    {
        $order = Order::create(['user_id' => $user->id, 'status' => $status, 'total_cents' => $product->price_cents]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price_cents' => $product->price_cents,
            'quantity' => 1,
        ]);
    }

    private function props(Product $product, ?User $as = null): array
    {
        $request = $as ? $this->actingAs($as) : $this;

        return $request->get("/products/{$product->slug}")->assertOk()->viewData('page')['props'];
    }

    public function test_a_customer_who_bought_the_product_can_review_it(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        $product = Product::factory()->create();
        $this->buy($user, $product);

        $this->actingAs($user)
            ->post("/products/{$product->slug}/reviews", ['rating' => 5, 'body' => 'Great.'])
            ->assertSessionHasNoErrors();

        $review = Review::firstOrFail();
        $this->assertSame($user->id, $review->user_id);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Jane D.', $review->author);
    }

    public function test_author_names_are_shortened_and_titles_dropped(): void
    {
        $cases = [
            'Jane Doe' => 'Jane D.',
            'Dr. Jane Doe' => 'Jane D.',
            'Prof. Fisher' => 'Fisher',
            'Cher' => 'Cher',
            'Mary Ann van der Berg' => 'Mary B.',
        ];

        foreach ($cases as $name => $expected) {
            $review = Review::factory()->create(['user_id' => User::factory()->create(['name' => $name])->id]);

            $this->assertSame($expected, $review->author, $name);
        }
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $product = Product::factory()->create();

        $this->post("/products/{$product->slug}/reviews", ['rating' => 5])->assertRedirect('/login');
    }

    public function test_someone_who_never_bought_it_cannot_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 5])->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_a_cancelled_order_does_not_count_as_a_purchase(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->buy($user, $product, 'cancelled');

        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 5])->assertForbidden();
    }

    public function test_buying_a_different_product_does_not_qualify(): void
    {
        $user = User::factory()->create();
        $this->buy($user, Product::factory()->create());
        $other = Product::factory()->create();

        $this->actingAs($user)->post("/products/{$other->slug}/reviews", ['rating' => 5])->assertForbidden();
    }

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->buy($user, $product);
        Review::factory()->create(['user_id' => $user->id, 'product_id' => $product->id, 'rating' => 4]);

        $this->actingAs($user)
            ->post("/products/{$product->slug}/reviews", ['rating' => 1])
            ->assertSessionHasErrors('rating');

        $this->assertSame(1, Review::count());
        $this->assertSame(4, Review::first()->rating);
    }

    public function test_rating_is_required_and_between_one_and_five(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->buy($user, $product);

        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['body' => 'x'])->assertSessionHasErrors('rating');
        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 6])->assertSessionHasErrors('rating');
        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 0])->assertSessionHasErrors('rating');
        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 3])->assertSessionHasNoErrors();
    }

    public function test_the_comment_is_optional(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->buy($user, $product);

        $this->actingAs($user)->post("/products/{$product->slug}/reviews", ['rating' => 4])->assertSessionHasNoErrors();

        $this->assertNull(Review::first()->body);
    }

    public function test_authors_can_edit_and_delete_their_own_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id, 'rating' => 2]);

        $this->actingAs($user)->patch("/reviews/{$review->id}", ['rating' => 5, 'body' => 'Grew on me.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $review->fresh()->rating);

        $this->actingAs($user)->delete("/reviews/{$review->id}");
        $this->assertModelMissing($review);
    }

    public function test_other_customers_cannot_edit_or_delete_a_review(): void
    {
        $review = Review::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->patch("/reviews/{$review->id}", ['rating' => 1])->assertForbidden();
        $this->actingAs($stranger)->delete("/reviews/{$review->id}")->assertForbidden();
        $this->assertSame(1, Review::count());
    }

    public function test_admins_can_delete_any_review_but_not_edit_it(): void
    {
        $review = Review::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch("/reviews/{$review->id}", ['rating' => 1])->assertForbidden();
        $this->actingAs($admin)->delete("/reviews/{$review->id}");

        $this->assertModelMissing($review);
    }

    public function test_product_page_summarises_ratings(): void
    {
        $product = Product::factory()->create();
        foreach ([5, 5, 4, 1] as $rating) {
            Review::factory()->create(['product_id' => $product->id, 'rating' => $rating]);
        }

        $ratings = $this->props($product)['ratings'];

        $this->assertSame(4, $ratings['count']);
        $this->assertSame(3.8, $ratings['average']);
        $this->assertSame([5 => 2, 4 => 1, 3 => 0, 2 => 0, 1 => 1], $ratings['distribution']);
    }

    public function test_unreviewed_product_has_no_average(): void
    {
        $ratings = $this->props(Product::factory()->create())['ratings'];

        $this->assertSame(0, $ratings['count']);
        $this->assertNull($ratings['average']);
    }

    public function test_reviews_show_a_short_author_name_and_never_the_email(): void
    {
        $product = Product::factory()->create();
        $reviewer = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@secret.test']);
        Review::factory()->create(['product_id' => $product->id, 'user_id' => $reviewer->id]);

        $page = $this->get("/products/{$product->slug}");
        $page->assertDontSee('jane@secret.test');

        $this->assertSame('Jane D.', $page->viewData('page')['props']['reviews']['data'][0]['author']);
    }

    public function test_page_tells_the_signed_in_user_whether_they_can_review(): void
    {
        $product = Product::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $this->buy($buyer, $product);

        $this->assertTrue($this->props($product, $buyer)['canReview']);
        $this->assertFalse($this->props($product, $stranger)['canReview']);
        $this->assertFalse($this->props($product)['canReview']);

        Review::factory()->create(['product_id' => $product->id, 'user_id' => $buyer->id]);
        $props = $this->props($product, $buyer);
        $this->assertFalse($props['canReview']);
        $this->assertSame($buyer->id, $props['myReview']['user_id']);
        $this->assertCount(0, $props['reviews']['data']); // their own is shown separately
    }

    public function test_shop_lists_average_rating_and_can_sort_by_it(): void
    {
        $low = Product::factory()->create(['name' => 'Low']);
        $high = Product::factory()->create(['name' => 'High']);
        Product::factory()->create(['name' => 'Unrated']);
        Review::factory()->create(['product_id' => $low->id, 'rating' => 2]);
        Review::factory()->create(['product_id' => $high->id, 'rating' => 5]);

        $products = $this->get('/?sort=rating')->viewData('page')['props']['products']['data'];

        $this->assertSame(['High', 'Low', 'Unrated'], array_column($products, 'name'));
        $this->assertEquals(5, $products[0]['reviews_avg_rating']);
        $this->assertSame(1, $products[0]['reviews_count']);
    }
}
