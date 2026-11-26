<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page tests check the Inertia props, not the compiled assets, so they must not
        // depend on `npm run build` having created public/build/manifest.json.
        $this->withoutVite();

        // A test must never reach a real service (Stripe, Gemini). Use FakeStripe, Http::fake() or
        // a fake LlmClient; anything else fails here instead of calling out.
        Http::preventStrayRequests();
    }
}
