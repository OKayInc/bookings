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
}
