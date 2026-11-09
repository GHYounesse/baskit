<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page tests check the Inertia props, not the compiled assets, so they must not
        // depend on `npm run build` having created public/build/manifest.json.
        $this->withoutVite();
    }
}
