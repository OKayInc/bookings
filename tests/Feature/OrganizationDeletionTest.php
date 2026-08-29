<?php

namespace Tests\Feature;

use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Appointment;
use App\Models\AppointmentContractTemplate;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\BookingAnswerFile;
use App\Models\BookingContractFile;
use App\Models\BookingContractSubmission;
use App\Models\CalendarConnection;
use App\Models\CalendarOauthState;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_owner_can_see_and_use_the_delete_action(): void
    {
        $owner = User::factory()->create();
        $administrator = User::factory()->create();
        $organization = $this->organizationFor($owner, MembershipRole::Owner);
        $this->membership($administrator, $organization, MembershipRole::Administrator);

        $this->actingAs($owner)
            ->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertSee('Delete organization permanently');

        $this->actingAs($administrator)
            ->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertDontSee('Delete organization permanently');

        $this->actingAs($administrator)
            ->delete(route('organizations.destroy', $organization), [
                'confirmation_name' => $organization->name,
                'current_password' => 'Password12345',
            ])
            ->assertForbidden();

        $this->assertTrue(Organization::whereUuid($organization->uuid)->exists());
    }

    public function test_exact_name_and_current_password_are_required(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationFor($owner, MembershipRole::Owner, 'Protected Organization');

        $this->actingAs($owner)
            ->from(route('organizations.edit', $organization))
            ->delete(route('organizations.destroy', $organization), [
                'confirmation_name' => 'protected organization',
                'current_password' => 'Password12345',
            ])
            ->assertRedirect(route('organizations.edit', $organization))
            ->assertSessionHasErrors('confirmation_name');

        $this->actingAs($owner)
            ->from(route('organizations.edit', $organization))
            ->delete(route('organizations.destroy', $organization), [
                'confirmation_name' => $organization->name,
                'current_password' => 'not-the-password',
            ])
            ->assertRedirect(route('organizations.edit', $organization))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Organization::whereUuid($organization->uuid)->exists());
    }

    public function test_delete_removes_organization_data_and_files_then_selects_another_organization(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $organization = $this->organizationFor($owner, MembershipRole::Owner, 'Organization to Delete');
        $survivor = $this->organizationFor($owner, MembershipRole::Owner, 'Surviving Organization');
        $owner->forceFill(['active_organization_id' => $organization->getKey()])->save();

        $organization->update(['logo_path' => 'organization-logos/delete/logo.png']);
        Storage::disk('public')->put($organization->logo_path, 'organization-logo');

        $type = $this->appointmentType($organization, 'delete-session', 'appointment-type-logos/delete/logo.png');
        Storage::disk('public')->put($type->logo_path, 'appointment-type-logo');
        $templatePath = 'contract-templates/delete/template.pdf';
        Storage::disk('local')->put($templatePath, 'contract');
        $template = AppointmentContractTemplate::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'uploaded_by_person_id' => $owner->person_id,
            'disk' => 'local',
            'path' => $templatePath,
            'original_name' => 'template.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
            'sha256' => hash('sha256', 'contract'),
            'is_active' => true,
            'active_slot' => 1,
        ]);

        $contact = OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Client',
            'last_name' => 'Example',
            'email' => 'client@example.test',
        ]);
        $appointment = Appointment::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => now('UTC')->addDay(),
            'ends_at_utc' => now('UTC')->addDay()->addHour(),
            'blocked_starts_at_utc' => now('UTC')->addDay(),
            'blocked_ends_at_utc' => now('UTC')->addDay()->addHour(),
            'scheduling_timezone' => 'America/Toronto',
            'duration_value' => 60,
            'capacity' => 1,
            'status' => 'scheduled',
        ]);
        $booking = Booking::create([
            'organization_id' => $organization->getKey(),
            'appointment_id' => $appointment->getKey(),
            'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(),
            'reference' => 'ORGDEL000001',
            'status' => 'confirmed',
            'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto',
            'price_minor' => 0,
            'currency' => 'CAD',
            'first_name' => 'Client',
            'last_name' => 'Example',
            'email' => 'client@example.test',
            'email_normalized' => 'client@example.test',
            'manage_token_hash' => hash('sha256', 'manage-token', true),
        ]);

        $answerPath = 'questionnaire/delete/answer.pdf';
        Storage::disk('local')->put($answerPath, 'questionnaire-answer');
        $answer = BookingAnswer::create([
            'booking_id' => $booking->getKey(),
            'question_uuid_snapshot' => '00000000-0000-7000-8000-000000000001',
            'question_label' => 'Supporting file',
            'question_type' => 'file',
            'value_json' => ['files' => 1],
            'position' => 1,
        ]);
        BookingAnswerFile::create([
            'booking_answer_id' => $answer->getKey(),
            'booking_id' => $booking->getKey(),
            'disk' => 'local',
            'path' => $answerPath,
            'original_name' => 'answer.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'sha256' => hash('sha256', 'questionnaire-answer', true),
            'position' => 1,
        ]);

        $signedPath = 'signed-contracts/delete/signed.pdf';
        Storage::disk('local')->put($signedPath, 'signed-contract');
        $submission = BookingContractSubmission::create([
            'organization_id' => $organization->getKey(),
            'booking_id' => $booking->getKey(),
            'contract_template_id' => $template->getKey(),
            'status' => 'pending',
            'submitted_at_utc' => now('UTC'),
        ]);
        BookingContractFile::create([
            'booking_contract_submission_id' => $submission->getKey(),
            'position' => 1,
            'disk' => 'local',
            'path' => $signedPath,
            'original_name' => 'signed.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 15,
            'sha256' => hash('sha256', 'signed-contract'),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('organizations.destroy', $organization), [
                'confirmation_name' => $organization->name,
                'current_password' => 'Password12345',
            ]);

        $response->assertRedirect(route('organizations.index'));
        $response->assertSessionHas('success');
        $this->assertFalse(Organization::whereUuid($organization->uuid)->exists());
        $this->assertTrue(Organization::whereUuid($survivor->uuid)->exists());
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_types', 0);
        $this->assertDatabaseCount('organization_contacts', 0);
        $this->assertDatabaseCount('appointment_contract_templates', 0);
        Storage::disk('public')->assertMissing('organization-logos/delete/logo.png');
        Storage::disk('public')->assertMissing('appointment-type-logos/delete/logo.png');
        Storage::disk('local')->assertMissing($templatePath);
        Storage::disk('local')->assertMissing($answerPath);
        Storage::disk('local')->assertMissing($signedPath);

        $owner->refresh();
        $this->assertTrue(hash_equals($survivor->getKey(), $owner->active_organization_id));
        $this->assertSame($survivor->uuid, session('active_organization_uuid'));
    }

    public function test_shared_resources_are_unshared_in_both_directions_before_owned_resources_are_deleted(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationFor($owner, MembershipRole::Owner, 'Resource Owner to Delete');
        $survivor = $this->organizationFor($owner, MembershipRole::Owner, 'Resource Survivor');

        $outgoing = $this->resource($organization, 'Outgoing resource');
        $outgoing->organizations()->syncWithoutDetaching([
            $survivor->getKey() => ['is_required_by_default' => true],
        ]);
        $incoming = $this->resource($survivor, 'Incoming resource');
        $incoming->organizations()->syncWithoutDetaching([
            $organization->getKey() => ['is_required_by_default' => false],
        ]);

        $survivingType = $this->appointmentType($survivor, 'surviving-session');
        $survivingType->resources()->attach($outgoing->getKey(), [
            'is_required' => true,
            'requirement_mode' => 'required',
        ]);
        $externalSchedule = AvailabilitySchedule::create([
            'organization_id' => $survivor->getKey(),
            'scope_type' => AvailabilityScope::Resource->value,
            'scope_id' => $outgoing->getKey(),
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);
        $externalConnection = CalendarConnection::create([
            'organization_id' => $survivor->getKey(),
            'resource_id' => $outgoing->getKey(),
            'provider' => 'google',
            'external_account_id' => 'shared-resource-account',
            'access_token' => 'encrypted-by-model-cast',
            'status' => 'active',
        ]);
        $externalOauthState = CalendarOauthState::create([
            'user_id' => $owner->getKey(),
            'organization_id' => $survivor->getKey(),
            'resource_id' => $outgoing->getKey(),
            'provider' => 'microsoft',
            'state_hash' => hash('sha256', 'organization-delete-oauth-state'),
            'expires_at_utc' => now('UTC')->addMinutes(10),
        ]);

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('organizations.destroy', $organization), [
                'confirmation_name' => $organization->name,
                'current_password' => 'Password12345',
            ])
            ->assertRedirect(route('organizations.index'));

        $this->assertFalse(Resource::whereUuid($outgoing->uuid)->exists());
        $this->assertFalse(AvailabilitySchedule::whereUuid($externalSchedule->uuid)->exists());
        $this->assertFalse(CalendarConnection::whereUuid($externalConnection->uuid)->exists());
        $this->assertFalse(CalendarOauthState::whereUuid($externalOauthState->uuid)->exists());
        $this->assertFalse($survivingType->resources()->whereKey($outgoing->getKey())->exists());

        $incoming->refresh();
        $this->assertTrue(hash_equals($survivor->getKey(), $incoming->organization_id));
        $this->assertTrue($incoming->organizations()->whereKey($survivor->getKey())->exists());
        $this->assertFalse($incoming->organizations()->whereKey($organization->getKey())->exists());
    }

    public function test_deleting_the_last_organization_redirects_to_create(): void
    {
        $owner = User::factory()->create();
        $organization = $this->organizationFor($owner, MembershipRole::Owner, 'Only Organization');

        $this->actingAs($owner)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->delete(route('organizations.destroy', $organization), [
                'confirmation_name' => $organization->name,
                'current_password' => 'Password12345',
            ])
            ->assertRedirect(route('organizations.create'));

        $owner->refresh();
        $this->assertNull($owner->active_organization_id);
        $this->assertNull(session('active_organization_uuid'));
    }

    private function organizationFor(
        User $user,
        MembershipRole $role,
        ?string $name = null,
    ): Organization {
        $organization = Organization::factory()->create(array_filter([
            'name' => $name,
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ], fn ($value): bool => $value !== null));
        $this->membership($user, $organization, $role);

        return $organization;
    }

    private function membership(User $user, Organization $organization, MembershipRole $role): void
    {
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => $role,
            'status' => MembershipStatus::Active,
        ]);
    }

    private function appointmentType(
        Organization $organization,
        string $slug,
        ?string $logoPath = null,
    ): AppointmentType {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'logo_path' => $logoPath,
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ]);
    }

    private function resource(Organization $organization, string $name): Resource
    {
        return Resource::create([
            'organization_id' => $organization->getKey(),
            'type' => 'person',
            'name' => $name,
            'timezone' => 'America/Toronto',
            'is_active' => true,
            'is_required_by_default' => true,
        ]);
    }
}
