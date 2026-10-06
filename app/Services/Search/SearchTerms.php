<?php

namespace App\Services\Search;

use Illuminate\Support\Str;

/**
 * Turns what someone typed into clean search words.
 *
 * Search boxes get punctuation, quotes, operators and accents. Reducing the
 * input to lower-case ASCII letters and digits keeps it safe to hand to any
 * engine (including FTS5, whose query syntax treats characters like `"`, `*`,
 * `-` and `:` specially) and makes "Café" and "cafe" the same search.
 */
final class SearchTerms
{
    /**
     * More words than this and the search is a sentence, not a lookup.
     */
    public const MAX_TERMS = 6;

    /**
     * @return list<string> the distinct words, in the order typed, capped at MAX_TERMS
     */
    public static function from(string $query): array
    {
        return array_slice(self::words($query), 0, self::MAX_TERMS);
    }

    /**
     * Every distinct word, uncapped. Used for building the spelling vocabulary.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', Str::lower(Str::ascii($text)), $matches);

        return array_values(array_unique($matches[0]));
    }
}
