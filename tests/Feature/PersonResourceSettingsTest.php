<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonResourceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_create_form_shows_and_enables_person_only_settings(): void
    {
        [$user, $organization] = $this->ownerContext();

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('resources.create'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*id="resource-person-field"[^>]*\bhidden\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*id="resource-person"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*id="resource-timezone-field"[^>]*\bhidden\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*id="resource-timezone"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*id="resource-holiday-field"[^>]*\bhidden\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="enforce_holidays"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*id="resource-holiday-region"[^>]*\bdisabled\b[^>]*>/', $html);
    }

    public function test_person_only_settings_are_still_validated_for_person_resources(): void
    {
        [$user, $organization] = $this->ownerContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('resources.store'), [
                'name' => 'Invalid person',
                'type' => 'person',
                'person_uuid' => 'not-a-uuid',
                'timezone' => 'Mars/Olympus_Mons',
                'default_requirement' => 'required',
                'enforce_holidays' => '1',
                'holiday_region' => 'NOT-A-REGION',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors(['person_uuid', 'timezone', 'holiday_region']);

        $this->assertDatabaseMissing('resources', ['name' => 'Invalid person']);
    }

    public function test_non_person_edit_form_hides_and_disables_person_only_settings(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'name' => 'Meeting room',
            'type' => 'room',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('resources.edit', $resource));

        $response
            ->assertOk()
            ->assertSee("type.addEventListener('change', refreshPersonSettings)", false);
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<div[^>]*id="resource-person-field"[^>]*\bhidden\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*id="resource-person"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<div[^>]*id="resource-timezone-field"[^>]*\bhidden\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*id="resource-timezone"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<div[^>]*id="resource-holiday-field"[^>]*\bhidden\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="enforce_holidays"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*id="resource-holiday-region"[^>]*\bdisabled\b[^>]*>/', $html);
    }

    public function test_forged_person_only_values_are_ignored_when_creating_a_non_person_resource(): void
    {
        [$user, $organization] = $this->ownerContext();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('resources.store'), [
                'name' => 'Studio room',
                'type' => 'room',
                'person_uuid' => 'not-a-uuid',
                'timezone' => 'Mars/Olympus_Mons',
                'default_requirement' => 'required',
                'enforce_holidays' => 'not-a-boolean',
                'holiday_region' => 'NOT-A-REGION',
                'is_active' => '1',
            ])
            ->assertRedirect(route('resources.index'))
            ->assertSessionHasNoErrors();

        $resource = Resource::where('name', 'Studio room')->firstOrFail();
        $this->assertNull($resource->person_id);
        $this->assertNull($resource->timezone);
        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'enforce_holidays' => 0,
            'holiday_region' => null,
        ]);
    }

    public function test_changing_a_person_resource_type_clears_all_person_only_settings(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'name' => 'Photographer',
            'type' => 'person',
            'timezone' => 'America/Toronto',
            'is_active' => true,
        ]);
        $resource->organizations()->updateExistingPivot($organization->getKey(), [
            'enforce_holidays' => true,
            'holiday_region' => 'CA-ON',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('resources.update', $resource), [
                'name' => 'Photography room',
                'type' => 'room',
                'person_uuid' => $user->person->uuid,
                'timezone' => 'America/Vancouver',
                'default_requirement' => 'required',
                'enforce_holidays' => '1',
                'holiday_region' => 'CA-BC',
                'is_active' => '1',
            ])
            ->assertRedirect(route('resources.index'))
            ->assertSessionHasNoErrors();

        $resource->refresh();
        $this->assertSame('room', $resource->type);
        $this->assertNull($resource->person_id);
        $this->assertNull($resource->timezone);
        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'enforce_holidays' => 0,
            'holiday_region' => null,
        ]);
    }

    public function test_shared_non_person_resource_cannot_receive_holiday_settings(): void
    {
        [$user, $owner] = $this->ownerContext();
        $sharedWith = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $sharedWith->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);
        $resource = Resource::create([
            'organization_id' => $owner->getKey(),
            'name' => 'Shared projector',
            'type' => 'equipment',
            'is_active' => true,
        ]);
        $resource->organizations()->attach($sharedWith->getKey(), [
            'is_required_by_default' => true,
            'enforce_holidays' => false,
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $sharedWith->uuid])
            ->get(route('resources.index'))
            ->assertOk()
            ->assertDontSee('name="enforce_holidays"', false)
            ->assertDontSee('name="holiday_region"', false);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $sharedWith->uuid])
            ->patch(route('resources.organization-settings.update', $resource), [
                'default_requirement' => 'optional',
                'enforce_holidays' => 'not-a-boolean',
                'holiday_region' => 'NOT-A-REGION',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $sharedWith->getKey(),
            'resource_id' => $resource->getKey(),
            'is_required_by_default' => 0,
            'enforce_holidays' => 0,
            'holiday_region' => null,
        ]);
    }

    public function test_model_and_migration_clean_existing_non_person_settings(): void
    {
        [$user, $organization] = $this->ownerContext();
        $resource = Resource::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'name' => 'Staff member',
            'type' => 'person',
            'timezone' => 'America/Toronto',
        ]);
        $resource->organizations()->updateExistingPivot($organization->getKey(), [
            'enforce_holidays' => true,
            'holiday_region' => 'CA-ON',
        ]);

        $resource->update(['type' => 'vehicle']);
        $resource->refresh();
        $this->assertNull($resource->person_id);
        $this->assertNull($resource->timezone);
        $this->assertSame(
            ['enforce' => false, 'region' => null],
            $resource->holidaySettingsForOrganization($organization),
        );
        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'enforce_holidays' => 0,
            'holiday_region' => null,
        ]);

        DB::table('resources')->where('id', $resource->getKey())->update([
            'person_id' => $user->person_id,
            'timezone' => 'America/Vancouver',
        ]);
        DB::table('organization_resources')
            ->where('organization_id', $organization->getKey())
            ->where('resource_id', $resource->getKey())
            ->update([
                'enforce_holidays' => true,
                'holiday_region' => 'CA-BC',
            ]);

        $migration = require database_path('migrations/2026_09_14_000073_enforce_person_only_resource_settings.php');
        $migration->up();

        $resource->refresh();
        $this->assertNull($resource->person_id);
        $this->assertNull($resource->timezone);
        $this->assertDatabaseHas('organization_resources', [
            'organization_id' => $organization->getKey(),
            'resource_id' => $resource->getKey(),
            'enforce_holidays' => 0,
            'holiday_region' => null,
        ]);
    }

    private function ownerContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create([
            'timezone' => 'America/Toronto',
            'currency' => 'CAD',
        ]);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
