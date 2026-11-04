<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The critical-path tests (checkout, stock, cart, roles) are written in Pest
| and live in tests/Feature/Critical. The older class-based tests keep
| working unchanged, since Pest runs PHPUnit test cases as they are.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Critical');
