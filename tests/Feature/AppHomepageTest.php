<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppHomepageTest extends TestCase
{
    public function test_homepage_is_public_and_describes_the_service(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Make scheduling easier with Appointment.to')
            ->assertSee('Appointment.to is a booking and scheduling service of OKay Inc.')
            ->assertSee('What Appointment.to does')
            ->assertSee('Why Appointment.to requests Google user data')
            ->assertSee('does not sell Google user data or use it for advertising or marketing')
            ->assertSee('/a/privacy.html#google-data', false)
            ->assertSee('/a/terms.html', false)
            ->assertSee('/login', false)
            ->assertSee('/register', false);
    }

    public function test_homepage_privacy_link_matches_the_configured_public_policy_route(): void
    {
        $this->assertSame('/a/privacy.html', route('legal.privacy', absolute: false));

        $this->get('/')
            ->assertSee('href="'.route('legal.privacy').'"', false);
    }
}
