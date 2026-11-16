<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini through the Google AI Studio API (generateContent).
 */
class GeminiClient implements LlmClient
{
    public function complete(string $system, string $prompt, ?string $imageData = null): string
    {
        $config = config('ai.gemini');

        if (! config('ai.enabled') || empty($config['key'])) {
            throw new LlmException('AI is not set up. Add GEMINI_API_KEY to .env.');
        }

        $parts = [['text' => $prompt]];

        if ($imageData !== null) {
            if (! preg_match('#^data:([\w.+-]+/[\w.+-]+);base64,(.+)$#s', $imageData, $match)) {
                throw new LlmException('That image could not be read. Try another photo.');
            }
            $parts[] = ['inline_data' => ['mime_type' => $match[1], 'data' => $match[2]]];
        }

        try {
            // The key goes in a header, never in the URL, so it can't end up in logs.
            $response = Http::withHeaders(['x-goog-api-key' => $config['key']])
                ->timeout($config['timeout'])
                ->acceptJson()
                ->post(rtrim($config['base_url'], '/')."/models/{$config['model']}:generateContent", [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => ['temperature' => 0.4],
                ]);
        } catch (ConnectionException $e) {
            throw new LlmException('The AI service did not answer in time. Try again.', 0, $e);
        }

        if ($response->failed()) {
            Log::warning('Gemini request failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new LlmException($response->status() === 429
                ? 'The AI service is busy right now. Try again in a minute.'
                : 'The AI service could not answer. Try again, or fill the form by hand.');
        }

        // A blocked or cut-off answer has no text part.
        $text = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter()
            ->implode('');

        if (trim($text) === '') {
            throw new LlmException('The AI service sent an empty answer. Try again.');
        }

        return $text;
    }
}
