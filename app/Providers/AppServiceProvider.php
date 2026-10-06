<?php

namespace App\Providers;

use App\Services\Payments\PaymentRefunder;
use App\Services\Payments\StripeRefunder;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentRefunder::class, fn () => new StripeRefunder(
            new StripeClient(config('services.stripe.secret')),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // An already-logged-in user hitting a guest-only route (e.g. /login)
        // should land on their own home, same as a fresh login does.
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            $user = $request->user();

            return $user?->isAdmin() ? route('admin.dashboard') : route('shop.index');
        });
    }
}
