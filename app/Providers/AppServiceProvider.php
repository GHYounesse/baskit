<?php

namespace App\Providers;

use App\Services\Assistant\ConversationEngine;
use App\Services\Assistant\RuleBasedEngine;
use App\Services\Payments\PaymentRefunder;
use App\Services\Payments\StripeRefunder;
use App\Services\Search\FtsProductSearch;
use App\Services\Search\LikeProductSearch;
use App\Services\Search\ProductSearch;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        // Use the full-text index when this database has one (SQLite, via the
        // product_search migration); otherwise search the products table directly.
        // Who answers the shopping assistant's questions. The built-in engine reads the
        // store's own data. A language-model engine would implement the same interface.
        $this->app->bind(ConversationEngine::class, RuleBasedEngine::class);

        $this->app->singleton(ProductSearch::class, fn () => DB::getDriverName() === 'sqlite' && Schema::hasTable('product_search')
            ? new FtsProductSearch
            : new LikeProductSearch);

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

        // Chat messages per minute, by customer (or by address for guests).
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute((int) config('assistant.rate_limit_per_minute', 30))
            ->by($request->user()?->id ?: $request->ip()));

        // An already-logged-in user hitting a guest-only route (e.g. /login)
        // should land on their own home, same as a fresh login does.
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            $user = $request->user();

            return $user?->isAdmin() ? route('admin.dashboard') : route('shop.index');
        });
    }
}
