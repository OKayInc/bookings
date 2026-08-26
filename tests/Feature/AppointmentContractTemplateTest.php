<?php

namespace Tests\Feature;

use App\Enums\AppointmentVisibility;
use App\Enums\AttendanceMode;
use App\Enums\DurationMode;
use App\Enums\DurationUnit;
use App\Enums\PricingMode;
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

class AppointmentContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_attach_and_privately_download_a_contract_template(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.store'), $this->validAppointmentTypePayload([
                'name' => 'Contract Session',
                'contract_file' => UploadedFile::fake()->create('service-contract.pdf', 100, 'application/pdf'),
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('appointment-types.index'));

        $type = AppointmentType::where('name', 'Contract Session')->firstOrFail();
        $template = $type->contractTemplate()->firstOrFail();

        $this->assertSame('service-contract.pdf', $template->original_name);
        $this->assertSame($organization->getKey(), $template->organization_id);
        $this->assertNotEmpty($template->sha256);
        Storage::disk('local')->assertExists($template->path);

        $download = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('appointment-types.contract-template.download', $type));

        $download->assertOk();
        $this->assertStringContainsString('service-contract.pdf', (string) $download->headers->get('content-disposition'));
    }

    public function test_replacing_a_contract_preserves_the_previous_version_for_audit(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Contract Session',
            'slug' => 'contract-session',
            'visibility' => AppointmentVisibility::Public,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.update', $type), $this->validAppointmentTypePayload([
                'name' => $type->name,
                'slug' => $type->slug,
                'contract_file' => UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.index'));

        $type->refresh();
        $oldPath = $type->contractTemplate()->firstOrFail()->path;
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('appointment-types.update', $type), $this->validAppointmentTypePayload([
                'name' => $type->name,
                'slug' => $type->slug,
                'contract_file' => UploadedFile::fake()->create('replacement.pdf', 50, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appointment-types.index'));

        $type->refresh();
        $newPath = $type->contractTemplate()->firstOrFail()->path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertSame(2, $type->contractTemplates()->count());
        $this->assertSame(1, $type->contractTemplates()->where('is_active', true)->count());
        $this->assertSame(1, $type->contractTemplates()->where('is_active', false)->count());
    }

    /**
     * Return a minimal, valid M2 appointment-type request payload.
     *
     * Contract tests should vary contract behavior without becoming coupled to
     * unrelated required appointment-type fields.
     */
    private function validAppointmentTypePayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Contract Session',
            'visibility' => AppointmentVisibility::Public->value,
            'attendance_mode' => AttendanceMode::Single->value,
            'duration_mode' => DurationMode::Fixed->value,
            'duration_unit' => DurationUnit::Minute->value,
            'duration_value' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => PricingMode::Free->value,
            'is_active' => '1',
        ], $overrides);
    }
}
