<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Stripe\PaymentIntent;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cartFor(User $user, int $stock = 5, int $quantity = 2): Product
    {
        $product = Product::factory()->create(['price_cents' => 1000, 'stock' => $stock]);
        Cart::create(['user_id' => $user->id])->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return $product;
    }

    private function intent(User $user, int $amount = 2000, string $status = 'succeeded'): PaymentIntent
    {
        return PaymentIntent::constructFrom([
            'id' => 'pi_test_123',
            'status' => $status,
            'amount_received' => $amount,
            'metadata' => ['user_id' => $user->id],
        ]);
    }

    public function test_creates_order_decrements_stock_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->cartFor($user);

        $order = app(OrderService::class)->createFromPaymentIntent($this->intent($user), ['city' => 'Casablanca']);

        $this->assertSame('paid', $order->status);
        $this->assertSame(2000, $order->total_cents);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertCount(1, $order->items);
        $this->assertSame(0, Cart::where('user_id', $user->id)->first()->items()->count());
    }

    public function test_is_idempotent_for_the_same_payment_intent(): void
    {
        $user = User::factory()->create();
        $product = $this->cartFor($user);
        $service = app(OrderService::class);

        $first = $service->createFromPaymentIntent($this->intent($user));
        $second = $service->createFromPaymentIntent($this->intent($user));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Order::count());
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_rejects_unsucceeded_payment(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);

        $this->expectException(RuntimeException::class);
        app(OrderService::class)->createFromPaymentIntent($this->intent($user, 2000, 'requires_payment_method'));
    }

    public function test_rejects_amount_mismatch(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);

        $this->expectException(RuntimeException::class);
        app(OrderService::class)->createFromPaymentIntent($this->intent($user, 1));
    }

    public function test_rolls_back_when_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $product = $this->cartFor($user, stock: 1, quantity: 2);

        try {
            app(OrderService::class)->createFromPaymentIntent($this->intent($user));
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
            $this->assertSame(0, Order::count());
            $this->assertSame(1, $product->fresh()->stock);
        }
    }

    public function test_sends_confirmation_email_once_for_a_new_order(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->cartFor($user);

        app(OrderService::class)->createFromPaymentIntent($this->intent($user));

        Mail::assertSent(OrderConfirmation::class, 1);
        Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_does_not_resend_confirmation_email_for_the_same_intent(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->cartFor($user);
        $service = app(OrderService::class);

        $service->createFromPaymentIntent($this->intent($user));
        $service->createFromPaymentIntent($this->intent($user));

        Mail::assertSent(OrderConfirmation::class, 1);
    }
}
