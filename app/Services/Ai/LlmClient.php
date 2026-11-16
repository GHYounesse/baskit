<?php

namespace App\Services\Ai;

/**
 * Something that can answer a prompt with text.
 *
 * Kept deliberately small so the provider can change (Gemini today)
 * without touching the features built on it, and so tests can use a fake.
 */
interface LlmClient
{
    /**
     * @param  string  $system  Instructions for the model.
     * @param  string  $prompt  The task itself.
     * @param  string|null  $imageData  A data: URL of an image to look at, if any.
     * @return string The model's reply text.
     *
     * @throws LlmException
     */
    public function complete(string $system, string $prompt, ?string $imageData = null): string;
}
