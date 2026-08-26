<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_email_can_exist_independently_in_different_organizations(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();

        $first = OrganizationContact::create([
            'organization_id' => $a->getKey(),
            'first_name' => 'Luis',
            'email' => '  CLIENT@Example.Test ',
        ]);

        $second = OrganizationContact::create([
            'organization_id' => $b->getKey(),
            'first_name' => 'Different Organization',
            'email' => 'client@example.test',
        ]);

        $this->assertSame('client@example.test', $first->email_normalized);
        $this->assertSame('client@example.test', $second->email_normalized);
        $this->assertNotSame($first->organization_id, $second->organization_id);
        $this->assertSame(2, OrganizationContact::count());
    }

    public function test_duplicate_normalized_email_is_rejected_within_same_organization(): void
    {
        $organization = Organization::factory()->create();

        OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'email' => 'client@example.test',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        OrganizationContact::create([
            'organization_id' => $organization->getKey(),
            'email' => ' CLIENT@example.test ',
        ]);
    }
}
