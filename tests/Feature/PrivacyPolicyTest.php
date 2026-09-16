<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_is_publicly_available_at_the_required_path(): void
    {
        $this->get('/a/privacy.html')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Appointment.to is an online booking service of OKay Inc')
            ->assertSee("OKay Inc's product news or offers", false)
            ->assertSee('Appointment.to, a service of OKay Inc, is based in Canada')
            ->assertSee('Privacy Officer of OKay Inc')
            ->assertSee('Google Workspace scopes')
            ->assertSee('Limited Use requirements')
            ->assertSee('Microsoft API Data')
            ->assertSee('privacy@appointment.to')
            ->assertDontSee('[LEGAL OPERATOR NAME]')
            ->assertDontSee('[MAILING ADDRESS]')
            ->assertDontSee('[PRIVACY EMAIL]');
    }

    public function test_public_and_backend_layouts_link_to_the_privacy_policy(): void
    {
        foreach ([
            resource_path('views/layouts/public.blade.php'),
            resource_path('views/layouts/app.blade.php'),
        ] as $layout) {
            $this->assertStringContainsString("route('legal.privacy')", file_get_contents($layout));
        }
    }
}
