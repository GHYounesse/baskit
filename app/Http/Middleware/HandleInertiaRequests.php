<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'assistant' => [
                'enabled' => (bool) config('assistant.enabled'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'cartCount' => fn () => app(CartService::class)->count($request),
            // IDs of the signed-in user's saved products, so any page can draw
            // a filled or empty heart without its own query.
            'wishlistIds' => fn () => $request->user()
                ? $request->user()->wishlist()->pluck('products.id')->all()
                : [],
        ];
    }
}
