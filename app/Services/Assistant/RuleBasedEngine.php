<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * The built-in assistant. It works out what a message is asking for with
 * IntentClassifier, then answers from the shop's own data: products through
 * ProductReplies, a customer's orders through OrderReplies, and how the
 * store works from the wording in config/assistant.php.
 *
 * It never makes up a price, a stock level or a policy. When it doesn't
 * know, it says so and offers what it can do.
 */
class RuleBasedEngine implements ConversationEngine
{
    public function __construct(
        private IntentClassifier $classifier,
        private ProductReplies $products,
        private OrderReplies $orders,
        private AssistantContext $context,
    ) {}

    public function respond(string $message, ?User $user): Reply
    {
        $message = mb_substr(trim($message), 0, (int) config('assistant.max_message_length', 300));
        $understood = $this->classifier->understand($message, $this->context);
        $policy = config('assistant.policies');

        return match ($understood->intent) {
            IntentClassifier::GREETING => $this->greeting(),
            IntentClassifier::THANKS => Reply::say("You're welcome! Anything else I can help with?")->suggest(...$this->menu()),
            IntentClassifier::BYE => Reply::say('Happy shopping! I\'m here if you need anything else.'),
            IntentClassifier::HELP => $this->help(),

            IntentClassifier::ORDER_STATUS => $this->orders->status($understood, $user),
            IntentClassifier::ORDER_CANCEL => $this->orders->cancel($understood, $user),

            IntentClassifier::COUPONS => Reply::say($policy['coupons'])->links([['label' => 'Go to my cart', 'href' => '/cart']]),
            IntentClassifier::PAYMENT => Reply::say($policy['payment']),
            IntentClassifier::SHIPPING => Reply::say($policy['shipping'])->suggest("Where's my order?"),
            IntentClassifier::HUMAN => Reply::say($policy['human']),
            IntentClassifier::WISHLIST => Reply::say($policy['wishlist'])->links([['label' => 'Open my wishlist', 'href' => '/wishlist']]),
            IntentClassifier::REVIEWS_HOWTO => Reply::say($policy['reviews']),
            IntentClassifier::ACCOUNT => $this->account($user),

            IntentClassifier::CATEGORIES => $this->products->categories(),
            IntentClassifier::BEST_SELLERS => $this->products->bestSellers(),
            IntentClassifier::NEW_ARRIVALS => $this->products->newArrivals(),
            IntentClassifier::MORE => $this->products->more($understood),
            IntentClassifier::AVAILABILITY => $this->products->availability($understood),
            IntentClassifier::PRODUCT_DETAILS => $this->products->details($understood),
            IntentClassifier::PRODUCT_SEARCH => $this->products->search($understood),

            default => $this->unknown(),
        };
    }

    public function reset(): void
    {
        $this->context->forget();
    }

    private function greeting(): Reply
    {
        $store = config('app.name');

        return Reply::say("Hi! I'm the {$store} assistant. I can help you find products, check stock and sizes, and track or cancel your orders. What are you looking for?")
            ->suggest(...$this->menu());
    }

    private function help(): Reply
    {
        return Reply::say("Here's what I can do:\n• Find products by name, category or budget\n• Check if something is in stock, and in which size or colour\n• Show what's popular or new\n• Track your orders and explain how to cancel one\n• Answer questions about discount codes and payment\n\nI read live store data, so prices and stock are always current.")
            ->suggest(...$this->menu());
    }

    private function account(?User $user): Reply
    {
        $reply = Reply::say(config('assistant.policies.account'));

        return $user ? $reply : $reply->links([['label' => 'Sign up', 'href' => '/register'], ['label' => 'Log in', 'href' => '/login']]);
    }

    private function unknown(): Reply
    {
        return Reply::say("I'm not sure I understood that. I'm best at finding products, checking stock and sizes, and tracking orders. For example, try “wireless headphones under \$150”, or “where's my order?”.")
            ->suggest(...$this->menu());
    }

    /**
     * @return list<string>
     */
    private function menu(): array
    {
        return ['Find a gift under $50', "What's popular right now?", "Where's my order?", 'How do I use a discount code?'];
    }
}
