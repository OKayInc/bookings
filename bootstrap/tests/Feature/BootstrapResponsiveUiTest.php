<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapResponsiveUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_uses_bootstrap_and_collapsible_responsive_navigation(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Responsive Organization']);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('bootstrap@5.3.8/dist/css/bootstrap.min.css', false)
            ->assertSee('bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js', false)
            ->assertSee('navbar navbar-expand-lg', false)
            ->assertSee('data-bs-target="#backendNavbar"', false)
            ->assertSee('Scheduling')
            ->assertSee('Organization:')
            ->assertSee('Responsive Organization')
            ->assertSee('Log out');
    }

    public function test_public_layout_uses_bootstrap_without_backend_authentication_navigation(): void
    {
        $organization = Organization::factory()->create(['slug' => 'responsive-public']);
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Responsive Public Session',
            'slug' => 'responsive-public-session',
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'before_confirmation',
            'is_active' => true,
        ]);

        $response = $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]));

        $response->assertOk()
            ->assertSee('bootstrap@5.3.8/dist/css/bootstrap.min.css', false)
            ->assertSee('container-xl public-container', false)
            ->assertDontSee('Log out')
            ->assertDontSee('Register');
    }

    public function test_multi_organization_switcher_is_right_aligned_on_desktop_and_inside_collapsed_mobile_navigation(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create(['name' => 'First Organization']);
        $second = Organization::factory()->create(['name' => 'Second Organization']);

        foreach ([$first, $second] as $organization) {
            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $user->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);
        }

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $first->uuid])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('organization-switcher', false)
            ->assertSee('dropdown-menu dropdown-menu-lg-end', false)
            ->assertSee('d-flex flex-column flex-lg-row align-items-lg-center', false)
            ->assertSee('First Organization')
            ->assertSee('Second Organization');
    }
}
