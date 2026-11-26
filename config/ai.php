<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI helpers
    |--------------------------------------------------------------------------
    |
    | Optional helpers for the admin, such as drafting a product listing. They
    | are off until the Gemini API key is set, and the shop
    | works the same without them.
    |
    */

    'enabled' => (bool) env('GEMINI_API_KEY'),

    'review_summary' => [
        'min_reviews' => (int) env('REVIEW_SUMMARY_MIN_REVIEWS', 3),
        'max_reviews' => 30,
        'max_review_chars' => 400,
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

        'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),

        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
    ],

];
