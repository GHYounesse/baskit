<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

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
