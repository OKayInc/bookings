<?php

namespace Tests\Feature;

use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConfigurationCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_question_and_tax_edits_rotate_the_organization_cache_generation(): void
    {
        $organization = Organization::factory()->create();
        $type = AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Portrait',
            'slug' => 'portrait', 'is_active' => true,
        ]);

        $before = $this->generation($organization);
        $question = $type->questions()->create(['type' => 'radio', 'label' => 'First question']);
        $this->assertNotSame($before, $this->generation($organization));

        $before = $this->generation($organization);
        $question->update(['label' => 'Revised question']);
        $this->assertNotSame($before, $this->generation($organization));

        $before = $this->generation($organization);
        $question->options()->create(['label' => 'Yes', 'value' => 'yes', 'position' => 1]);
        $this->assertNotSame($before, $this->generation($organization));

        $before = $this->generation($organization);
        $organization->taxes()->create(['name' => 'HST', 'rate_millionths' => 13000000]);
        $this->assertNotSame($before, $this->generation($organization));
    }

    public function test_shared_resource_change_invalidates_both_organizations(): void
    {
        $owner = Organization::factory()->create();
        $shared = Organization::factory()->create();
        $resource = Resource::create([
            'organization_id' => $owner->getKey(), 'type' => 'equipment',
            'name' => 'Projector', 'is_active' => true,
        ]);
        $resource->organizations()->syncWithoutDetaching([$shared->getKey() => ['is_required_by_default' => false]]);

        $beforeOwner = $this->generation($owner);
        $beforeShared = $this->generation($shared);
        $resource->update(['name' => 'Cinema projector']);

        $this->assertNotSame($beforeOwner, $this->generation($owner));
        $this->assertNotSame($beforeShared, $this->generation($shared));
    }

    private function generation(Organization $organization): ?string
    {
        return Cache::store('array')->get('configuration:generation:'.bin2hex($organization->getKey()));
    }
}
