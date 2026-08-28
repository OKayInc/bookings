<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_and_remove_organization_logo(): void
    {
        Storage::fake('public');
        config(['organizations.logo_disk' => 'public']);

        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'logo_file' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            ])->assertRedirect(route('organizations.index'));

        $organization->refresh();
        $this->assertNotNull($organization->logo_path);
        Storage::disk('public')->assertExists($organization->logo_path);
        $oldPath = $organization->logo_path;

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'remove_logo' => '1',
            ])->assertRedirect(route('organizations.index'));

        $organization->refresh();
        $this->assertNull($organization->logo_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_public_appointment_uses_organization_logo_when_type_has_none(): void
    {
        Storage::fake('public');
        config(['organizations.logo_disk' => 'public']);

        $organization = Organization::factory()->create(['logo_path' => 'organizations/logos/example/logo.png']);
        Storage::disk('public')->put($organization->logo_path, 'logo');
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Logo Fallback Session',
            'slug' => 'logo-fallback-session',
            'logo_path' => null,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);

        $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]))->assertOk()->assertSee($organization->logo_url, false);
    }
}
