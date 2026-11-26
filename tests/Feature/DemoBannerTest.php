<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_site_shares_no_demo_details(): void
    {
        config(['demo.enabled' => false]);

        $this->get('/shop')->assertInertia(fn ($page) => $page->where('demo', null));
    }

    public function test_a_demo_site_shares_the_test_card_and_demo_logins(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/shop')->assertInertia(fn ($page) => $page
            ->where('demo.test_card', '4242 4242 4242 4242')
            ->where('demo.accounts.0.email', 'admin@example.com'));
    }
}
