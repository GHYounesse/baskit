<?php

namespace App\Services\Assistant;

/**
 * Works out what a message is asking for.
 *
 * It is a set of rules, checked in order, so the same message always gets the
 * same answer and every rule can be tested. More specific requests come first
 * ("cancel my order" before "order"), and anything that matches nothing but
 * looks like a product name is treated as a product search.
 */
class IntentClassifier
{
    public const GREETING = 'greeting';

    public const THANKS = 'thanks';

    public const BYE = 'bye';

    public const HELP = 'help';

    public const ORDER_CANCEL = 'order_cancel';

    public const ORDER_STATUS = 'order_status';

    public const COUPONS = 'coupons';

    public const PAYMENT = 'payment';

    public const SHIPPING = 'shipping';

    public const HUMAN = 'human';

    public const WISHLIST = 'wishlist';

    public const REVIEWS_HOWTO = 'reviews_howto';

    public const ACCOUNT = 'account';

    public const CATEGORIES = 'categories';

    public const BEST_SELLERS = 'best_sellers';

    public const NEW_ARRIVALS = 'new_arrivals';

    public const MORE = 'more';

    public const AVAILABILITY = 'availability';

    public const PRODUCT_DETAILS = 'product_details';

    public const PRODUCT_SEARCH = 'product_search';

    public const UNKNOWN = 'unknown';

    public function __construct(private EntityExtractor $entities) {}

    public function understand(string $message, AssistantContext $context): Understanding
    {
        $text = $this->normalize($message);
        $entities = $this->entities->extract($text);
        $words = str_word_count($text);

        $has = fn (string $pattern) => preg_match($pattern, $text) === 1;
        $hasQuery = ($entities['query'] ?? '') !== '';
        $hasOrderId = isset($entities['order_id']);
        // ("delivery" alone is a question about the store's shipping; "my delivery" is about an order.)
        $talksAboutOrder = $has('/\b(order|orders|package|parcel|purchase|shipment)\b|\bmy (delivery|shipment)\b/') || $hasOrderId;

        $intent = match (true) {
            $text === '' => self::HELP,

            // Short social messages.
            $words <= 4 && $has('/^(hi|hello|hey|hiya|yo|salam|bonjour|good (morning|afternoon|evening))\b/') => self::GREETING,
            $has('/\b(thanks|thank you|thx|cheers|appreciate it)\b/') && $words <= 6 => self::THANKS,
            $words <= 5 && $has('/^(bye|goodbye|see you|cya|that\'?s all|no thanks|nothing else)\b/') => self::BYE,
            $words <= 6 && $has('/\b(what can you do|how do you work|what do you do|can you help|help me|help)\b/') && ! $hasQuery => self::HELP,

            // Orders: cancelling or refunding comes before tracking.
            $has('/\b(cancel|cancellation|refund|refunds|money back|return|returns|exchange|send (it )?back)\b/') => self::ORDER_CANCEL,
            $has('/\b(track|tracking|where is|where\'?s|status of|shipped|has it shipped|when will|eta|arrive|arrival|my orders?|order history|past orders|recent orders|order status)\b/') && $talksAboutOrder => self::ORDER_STATUS,
            $hasOrderId => self::ORDER_STATUS,
            $has('/\b(my orders?|order history|past orders|recent orders)\b/') => self::ORDER_STATUS,

            // Store information.
            $has('/\b(coupons?|promo(?: ?codes?)?|discounts?|vouchers?|codes?|offers?|deals?)\b/') => self::COUPONS,
            $has('/\b(how (do|can|should) i pay|how to pay|payments?|pay with|payment methods?|accept (cards?|credit|visa|paypal)|paypal|stripe|visa|mastercard|apple pay|cash on delivery|credit card|debit card)\b/') => self::PAYMENT,
            $has('/\b(shipping|delivery|deliver|ship to|international|worldwide)\b/') && ! $talksAboutOrder => self::SHIPPING,
            $has('/\b(human|real person|live (chat|agent)|customer (service|support|care)|talk to (someone|a person|an agent)|speak (to|with)|contact (you|us|support|the store)|phone number|call you|support team)\b/') => self::HUMAN,
            $has('/\b(wish ?list|save (it |items? |products? )?for later|save (items|products)|favou?rites?|saved items)\b/') => self::WISHLIST,
            $has('/\b(leave|write|post|add|submit)\b.*\breviews?\b|\bcan i review\b|\bhow (do|can) i review\b/') => self::REVIEWS_HOWTO,
            $has('/\b(create|make|open)\b.*\baccount\b|\bsign ?up\b|\bregister\b|\bhow (do|can) i (log ?in|sign in)\b/') => self::ACCOUNT,

            // Browsing.
            $has('/\b(categories|category list|product range|catalogue|catalog)\b|\bwhat (do you|can i)( buy| sell| get)?( sell| offer| have)\b|\bwhat (kinds?|types?) of (products|things|items)\b|^(show|list)( me)?( all)? (the )?(products|categories)$/') => self::CATEGORIES,
            $has('/\b(best[- ]?sellers?|most popular|popular|trending|top sellers?|bestselling|most bought)\b/') => self::BEST_SELLERS,
            $has('/\b(new arrivals?|what\'?s new|just in|recently added|new products?|new in)\b/') => self::NEW_ARRIVALS,
            $has('/^(more|show more|see more|next|more results|others|other options|anything else|another)\b/') && $words <= 4 => self::MORE,

            // "the second one" picks from the list just shown.
            isset($entities['ordinal']) && $context->shown() !== [] && (isset($entities['size']) || isset($entities['color']) || $has('/\b(in stock|available|availability|sold out)\b/')) => self::AVAILABILITY,
            isset($entities['ordinal']) && $context->shown() !== [] && $words <= 8 => self::PRODUCT_DETAILS,

            // About a product.
            $has('/\b(in stock|available|availability|sold out|out of stock|restock|re-stock|stock)\b/') && ($hasQuery || $context->product() !== null) => self::AVAILABILITY,
            (isset($entities['size']) || isset($entities['color'])) && $has('/\b(in|size|colou?r)\b/') && ($hasQuery || $context->product() !== null || isset($entities['pronoun'])) => self::AVAILABILITY,
            ($has('/\b(tell me (more )?about|details?|describe|description|info(?:rmation)?|how much|price|cost|rating|reviews? (of|for)|worth|is it good)\b/') || ($words <= 6 && $has('/\b(what is|what\'?s)\b/'))) && ($hasQuery || $context->product() !== null || isset($entities['ordinal'])) => self::PRODUCT_DETAILS,

            // Looking for something.
            $has('/\b(find|show|search|looking for|look for|i need|i want|i\'?d like|recommend|suggest|gift|something|anything|got any|do you (have|sell|carry)|have you got|any)\b/') => self::PRODUCT_SEARCH,
            isset($entities['category']) || isset($entities['max_price_cents']) || isset($entities['min_price_cents']) || isset($entities['sort']) || isset($entities['color']) || isset($entities['size']) => self::PRODUCT_SEARCH,

            // A bare word or two is most likely a product name.
            $hasQuery && $words <= 8 => self::PRODUCT_SEARCH,

            default => self::UNKNOWN,
        };

        // "cheaper ones" / "show cheaper" refine the last search instead of starting a new one.
        if ($intent !== self::MORE && $context->search() !== null && $has('/\b(cheaper|less expensive|lower price|cheapest)\b/') && ! $hasQuery) {
            return new Understanding(self::MORE, [...$entities, 'sort' => 'price_asc', 'refine' => true]);
        }

        return new Understanding($intent, $entities);
    }

    /**
     * Lower-case, straight apostrophes, one space between words, and no
     * stretch of repeated punctuation.
     */
    public function normalize(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $text = str_replace(['’', '‘', '`', '´'], "'", $text);
        $text = preg_replace('/[?!.,;:]+(\s|$)/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
