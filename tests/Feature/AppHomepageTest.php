<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppHomepageTest extends TestCase
{
    public function test_homepage_is_public_and_sales_oriented(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Let clients book you without the back-and-forth.')
            ->assertSee('Start free')
            ->assertSee('From setup to booked in three steps')
            ->assertSee('Start free. Upgrade when the business needs more.')
            ->assertSee('Why Appointment.to requests Google user data')
            ->assertSee('does not sell Google user data or use it for advertising or marketing')
            ->assertSee('/pricing', false)
            ->assertSee('/a/privacy.html#google-data', false)
            ->assertSee('/a/terms.html', false)
            ->assertSee('/login', false)
            ->assertSee('/register', false);
    }

    public function test_public_pricing_page_uses_configured_plan_values(): void
    {
        config()->set('plans.business_monthly_price_minor', 900);
        config()->set('plans.business_annual_price_minor', 9000);

        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Start small without painting yourself into a corner.')
            ->assertSee('US$9')
            ->assertSee('US$90/year')
            ->assertSee('Business add-ons')
            ->assertSee('API + outgoing webhooks');
    }

    public function test_homepage_privacy_link_matches_the_configured_public_policy_route(): void
    {
        $this->assertSame('/a/privacy.html', route('legal.privacy', absolute: false));

        $this->get('/')
            ->assertSee('href="'.route('legal.privacy').'#google-data"', false);
    }
}
