<?php

namespace Tests\Feature;

use Tests\TestCase;

class TermsAndConditionsTest extends TestCase
{
    public function test_terms_are_publicly_available_at_the_required_path(): void
    {
        $this->get('/a/terms.html')
            ->assertOk()
            ->assertSee('Terms and Conditions')
            ->assertSee('Voluntary Use and Assumption of Risk')
            ->assertSee('Limitation of Liability')
            ->assertSee('Indemnification')
            ->assertSee('/a/privacy.html', false)
            ->assertSee('privacy@appointment.to')
            ->assertDontSee('[LEGAL OPERATOR NAME]')
            ->assertDontSee('[MAILING ADDRESS]')
            ->assertDontSee('[LEGAL EMAIL]');
    }

    public function test_public_and_backend_layouts_link_to_the_terms(): void
    {
        foreach ([
            resource_path('views/layouts/public.blade.php'),
            resource_path('views/layouts/app.blade.php'),
        ] as $layout) {
            $this->assertStringContainsString("route('legal.terms')", file_get_contents($layout));
        }
    }
}
