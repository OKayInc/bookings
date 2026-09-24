<?php

namespace Tests\Feature;

use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAppointmentTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_public_types_are_listed_publicly(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);

        foreach ([
            ['Public Session', AppointmentVisibility::Public],
            ['Secret Link', AppointmentVisibility::Unlisted],
            ['Invite Session', AppointmentVisibility::InviteOnly],
            ['Password Session', AppointmentVisibility::PasswordProtected],
        ] as [$name, $visibility]) {
            AppointmentType::create([
                'organization_id' => $organization->getKey(),
                'name' => $name,
                'slug' => (string) str($name)->slug(),
                'visibility' => $visibility,
                'is_active' => true,
            ]);
        }

        $response = $this->get(route('public.appointment-types.index', 'demo'));
        $response->assertOk()
            ->assertSee('Public Session')
            ->assertDontSee('Secret Link')
            ->assertDontSee('Invite Session')
            ->assertDontSee('Password Session');
    }

    public function test_public_type_selection_supports_direct_links_and_falls_back_to_first_available(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            AppointmentType::create([
                'organization_id' => $organization->getKey(),
                'name' => $name,
                'slug' => strtolower($name),
                'visibility' => AppointmentVisibility::Public,
                'is_active' => true,
            ]);
        }
        AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Private',
            'slug' => 'private',
            'visibility' => AppointmentVisibility::Unlisted,
            'is_active' => true,
        ]);

        $url = route('public.appointment-types.index', 'demo');
        $selected = $this->get($url.'?type=bravo')->assertOk()
            ->assertSee('data-appointment-type="bravo"', false)
            ->assertSee('id="appointment-panel-bravo" data-appointment-panel="bravo"', false)
            ->assertDontSee('data-appointment-type="private"', false);
        $this->assertMatchesRegularExpression('/data-appointment-panel="alpha"\s+hidden/', $selected->getContent());
        $this->assertMatchesRegularExpression('/data-appointment-type="bravo"[^>]*aria-current="true"/', $selected->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-appointment-panel="bravo"\s+hidden/', $selected->getContent());

        $fallback = $this->get($url.'?type=private')->assertOk();
        $this->assertMatchesRegularExpression('/data-appointment-panel="bravo"\s+hidden/', $fallback->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-appointment-panel="alpha"\s+hidden/', $fallback->getContent());
    }

    public function test_single_public_type_displays_directly_without_selector(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Portraits',
            'slug' => 'portraits',
            'visibility' => AppointmentVisibility::Public,
            'is_active' => true,
        ]);

        $this->get(route('public.appointment-types.index', 'demo'))
            ->assertOk()
            ->assertSee('id="appointment-panel-portraits"', false)
            ->assertDontSee('data-appointment-selector', false);
    }
}
