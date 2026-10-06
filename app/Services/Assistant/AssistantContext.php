<?php

namespace App\Services\Assistant;

use Illuminate\Contracts\Session\Session;

/**
 * What the assistant remembers between messages, so "what about in blue?" or
 * "the second one" make sense. It lives in the visitor's own session on the
 * server, never in anything the browser sends, and holds only references
 * (a product slug, a search, an order number), not personal details.
 */
class AssistantContext
{
    private const KEY = 'assistant';

    public function __construct(private Session $session) {}

    /**
     * The last product talked about (its slug).
     */
    public function product(): ?string
    {
        return $this->get('product');
    }

    /**
     * The last product search: query, category, price limits, sort and how far through it we are.
     *
     * @return array<string, mixed>|null
     */
    public function search(): ?array
    {
        return $this->get('search');
    }

    /**
     * Slugs of the products last shown, in order, so "the second one" can be resolved.
     *
     * @return list<string>
     */
    public function shown(): array
    {
        return $this->get('shown', []);
    }

    public function order(): ?int
    {
        return $this->get('order');
    }

    public function rememberProduct(string $slug): void
    {
        $this->put('product', $slug);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $shownSlugs
     */
    public function rememberSearch(array $search, array $shownSlugs): void
    {
        $this->put('search', $search);
        $this->put('shown', $shownSlugs);

        // One result is "the product we're talking about"; several are a list to refine or pick from.
        count($shownSlugs) === 1
            ? $this->put('product', $shownSlugs[0])
            : $this->session->forget(self::KEY.'.product');
    }

    public function rememberOrder(int $orderId): void
    {
        $this->put('order', $orderId);
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get(self::KEY.'.'.$key, $default);
    }

    private function put(string $key, mixed $value): void
    {
        $this->session->put(self::KEY.'.'.$key, $value);
    }
}
