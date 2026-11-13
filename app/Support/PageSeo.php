<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * PageSeo = "what should Google, WhatsApp or LinkedIn know about this page?"
 *
 * It is a small box of facts about ONE page: its title, a short description, a picture, its
 * official address, and whether search engines are allowed to list it.
 */
final class PageSeo
{
    /** Google shows roughly 155 characters of description in its results, so we cut there. */
    private const DESCRIPTION_LIMIT = 155;

    /**
     * @param  string|null  $title  The page's own title
     * @param  string|null  $description  A sentence or two about the page.
     * @param  string|null  $image  The picture shown in link previews
     * @param  string|null  $path  The ONE official address of the page
     * @param  string  $type  What kind of page it is for link previews: "website" or "product".
     * @param  bool  $noindex  True means "search engines, do not list this page".
     * @param  bool  $followLinks  Only matters with $noindex. True means "do not list this page, but do follow
     *                             its links"
     * @param  array<string, mixed>|null  $jsonLd  Extra facts for Google in its own format (schema.org),
     *                                             for example a product's price and availability.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?string $path = null,
        public readonly string $type = 'website',
        public readonly bool $noindex = false,
        public readonly bool $followLinks = false,
        public readonly ?array $jsonLd = null,
    ) {}

    /**
     * The default for pages that say nothing: do not list me in search results.
     * (HandleInertiaRequests gives this to every page unless the page provides its own.)
     */
    public static function hidden(): self
    {
        return new self(noindex: true);
    }

    /**
     * The address of this website.
     *
     * It comes from the APP_URL setting and NOT from the request. A visitor can send any "Host"
     * header they like, and if we trusted it, someone could make our pages claim to live on
     * another website.
     */
    public static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Turn a path into a full address, because link previews need full addresses.
     */
    public static function absolute(?string $urlOrPath): ?string
    {
        if ($urlOrPath === null || $urlOrPath === '') {
            return null;
        }

        // Already a full address: nothing to add.
        if (preg_match('#^https?://#i', $urlOrPath)) {
            return $urlOrPath;
        }

        return self::baseUrl().'/'.ltrim($urlOrPath, '/');
    }

    /**
     * Clean a text so it fits in a search result:
     * remove HTML tags, squash line breaks and repeated spaces into single spaces,
     * and cut it at the last whole word before the limit, adding "…".
     * An empty text gives null, so no empty description tag is written.
     */
    public static function describe(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $text))));

        return $text === '' ? null : Str::limit($text, self::DESCRIPTION_LIMIT, '…', preserveWords: true);
    }

    /**
     * The ready-to-use version: a plain array with every value already cleaned and complete.
     * This array is what is sent to the page (as the "seo" prop) and read by the <head> template.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $site = (string) config('app.name');

        // "Blue Lamp" becomes "Blue Lamp - Baskit"; no title gives just "Baskit".
        $title = $this->title !== null && $this->title !== '' ? "{$this->title} - {$site}" : $site;

        return [
            'title' => $title,
            'description' => self::describe($this->description),
            'image' => self::absolute($this->image),
            'canonical' => $this->path !== null ? self::absolute($this->path) : null,
            'type' => $this->type,
            'site_name' => $site,
            // The instruction read by search engines: may they list this page?
            'robots' => match (true) {
                ! $this->noindex => 'index, follow',
                $this->followLinks => 'noindex, follow',
                default => 'noindex, nofollow',
            },
            'json_ld' => $this->jsonLd,
        ];
    }
}
