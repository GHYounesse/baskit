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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

        // "-latest" aliases follow Google's current Flash model, which accepts images.
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),

        'timeout' => (int) env('GEMINI_TIMEOUT', 45),
    ],

];
