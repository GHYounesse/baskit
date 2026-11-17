<?php

namespace App\Http\Controllers;

use App\Support\PageSeo;
use App\Support\Sitemap;
use Illuminate\Http\Response;

/**
 * The two files crawlers ask for first: /robots.txt and /sitemap.xml.
 */
class SeoController extends Controller
{
    /**
     * Areas that must never be visited: they need a login or are used only by the site's own code.
     *
     * Pages that carry a "noindex" tag (login, register, cart) are deliberately NOT listed here.
     * A robot must be allowed to open a page to read its "noindex"; if it is blocked here, Google
     * may keep the bare address in its results instead.
     */
    private const PRIVATE_AREAS = [
        '/admin',
        '/checkout',
        '/orders',
        '/wishlist',
        '/profile',
        '/dashboard',
        '/stripe/',
        '/assistant/',
        '/search/suggest',
    ];

    private const CACHE_HEADER = 'public, max-age=3600';

    /**
     * Generated (not a static file) so the sitemap address always matches APP_URL.
     * Anywhere other than production (a test copy of the site), everything is blocked, so a
     * staging site can never end up in search results.
     */
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (config('app.env') !== 'production') {
            $lines[] = 'Disallow: /';
        } else {
            foreach (self::PRIVATE_AREAS as $area) {
                $lines[] = "Disallow: {$area}";
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.PageSeo::absolute('/sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => self::CACHE_HEADER,
        ]);
    }

    public function sitemap(Sitemap $sitemap): Response
    {
        return response($sitemap->toXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => self::CACHE_HEADER,
        ]);
    }
}
