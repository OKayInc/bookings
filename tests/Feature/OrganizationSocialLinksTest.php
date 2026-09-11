<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_edit_organization_social_links(): void
    {
        [$user, $organization] = $this->ownedOrganization();

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('organizations.edit', $organization))
            ->assertOk()
            ->assertSee('name="facebook_url"', false)
            ->assertSee('name="instagram_url"', false)
            ->assertSee('name="x_url"', false)
            ->assertSee('name="linkedin_url"', false)
            ->assertSee('name="tiktok_url"', false);

        $socials = [
            'facebook_url' => 'https://www.facebook.com/example',
            'instagram_url' => 'https://www.instagram.com/example',
            'x_url' => 'https://x.com/example',
            'linkedin_url' => 'https://www.linkedin.com/company/example',
            'tiktok_url' => 'https://www.tiktok.com/@example',
        ];

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                ...$socials,
            ])
            ->assertRedirect(route('organizations.index'));

        $organization->refresh();
        foreach ($socials as $field => $url) {
            $this->assertSame($url, $organization->{$field});
        }
    }

    public function test_social_links_must_be_http_or_https_urls_and_blanks_are_stored_as_null(): void
    {
        [$user, $organization] = $this->ownedOrganization([
            'instagram_url' => 'https://www.instagram.com/old-profile',
        ]);

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'facebook_url' => 'javascript:alert(1)',
                'instagram_url' => '   ',
            ])
            ->assertSessionHasErrors('facebook_url');

        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->put(route('organizations.update', $organization), [
                'name' => $organization->name,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'instagram_url' => '   ',
            ])
            ->assertRedirect(route('organizations.index'));

        $this->assertNull($organization->fresh()->instagram_url);
    }

    public function test_public_organization_page_only_displays_configured_social_icons(): void
    {
        $organization = Organization::factory()->create([
            'facebook_url' => 'https://www.facebook.com/example',
            'instagram_url' => null,
            'x_url' => null,
            'linkedin_url' => 'https://www.linkedin.com/company/example',
            'tiktok_url' => null,
        ]);

        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()
            ->assertSee('href="https://www.facebook.com/example"', false)
            ->assertSee('aria-label="Facebook"', false)
            ->assertSee('href="https://www.linkedin.com/company/example"', false)
            ->assertSee('aria-label="LinkedIn"', false)
            ->assertSee('<svg aria-hidden="true" width="16" height="16"', false)
            ->assertDontSee('aria-label="Instagram"', false)
            ->assertDontSee('aria-label="X"', false)
            ->assertDontSee('aria-label="TikTok"', false);
    }

    private function ownedOrganization(array $attributes = []): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create($attributes);

        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }
}
