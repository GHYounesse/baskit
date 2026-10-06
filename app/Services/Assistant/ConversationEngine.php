<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * Something that can answer a shopper's message.
 *
 * The built-in RuleBasedEngine answers from the store's own data and needs
 * nothing external. A language-model engine (a free hosted model such as
 * Groq or Gemini, or a local one through Ollama) would implement this same
 * interface, calling the same read-only AssistantCatalog and order lookups
 * as its tools, and be chosen in AppServiceProvider.
 */
interface ConversationEngine
{
    public function respond(string $message, ?User $user): Reply;

    /**
     * Forget what was said so far (a "new chat").
     */
    public function reset(): void;
}
