<?php

namespace App\Services\Assistant;

/**
 * An answer: a few parts to show in order (text, product cards, order
 * cards, links) and some quick replies the visitor can tap next.
 *
 * Text is always plain text. The browser renders it as text, never as
 * markup, so nothing a visitor types (or a product name contains) can
 * turn into HTML.
 */
final class Reply
{
    /** @var list<array<string, mixed>> */
    private array $parts = [];

    /** @var list<string> */
    private array $suggestions = [];

    public static function say(string $text): self
    {
        return (new self)->text($text);
    }

    public function text(string $text): self
    {
        $this->parts[] = ['type' => 'text', 'text' => $text];

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    public function products(array $cards): self
    {
        if ($cards !== []) {
            $this->parts[] = ['type' => 'products', 'products' => $cards];
        }

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    public function orders(array $cards): self
    {
        if ($cards !== []) {
            $this->parts[] = ['type' => 'orders', 'orders' => $cards];
        }

        return $this;
    }

    /**
     * @param  list<array{label: string, href: string}>  $links
     */
    public function links(array $links): self
    {
        if ($links !== []) {
            $this->parts[] = ['type' => 'links', 'links' => $links];
        }

        return $this;
    }

    public function suggest(string ...$suggestions): self
    {
        $this->suggestions = array_values(array_unique([...$this->suggestions, ...$suggestions]));

        return $this;
    }

    /**
     * @return array{parts: list<array<string, mixed>>, suggestions: list<string>}
     */
    public function toArray(): array
    {
        return ['parts' => $this->parts, 'suggestions' => array_slice($this->suggestions, 0, 4)];
    }

    /**
     * The words of the reply, for tests and logs.
     */
    public function plainText(): string
    {
        return collect($this->parts)->where('type', 'text')->pluck('text')->implode("\n");
    }
}
