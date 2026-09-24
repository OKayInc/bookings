<?php

namespace Tests\Feature;

use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_indexable_metadata_canonical_and_structured_data(): void
    {
        config(['filesystems.public_asset_url' => 'https://images.appointment.to']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('<meta property="og:image" content="https://images.appointment.to/images/appointment-to-logo.png">', false)
            ->assertSee('<link rel="icon" href="https://images.appointment.to/images/appointment-to-logo.png">', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"SoftwareApplication"', false);
    }

    public function test_backend_and_authentication_pages_are_noindex(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
    }

    public function test_public_organization_and_appointment_pages_have_dynamic_seo(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'More Than Photos',
            'slug' => 'more-than-photos',
        ]);
        $type = $this->type($organization, 'Family Photo Session', AppointmentVisibility::Public, [
            'description' => '<p>Relaxed family photography in Cornwall.</p>',
        ]);

        $organizationUrl = route('public.appointment-types.index', $organization->slug);
        $this->get($organizationUrl.'?type='.$type->slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$organizationUrl.'">', false)
            ->assertSee('More Than Photos appointments | Appointment.to')
            ->assertSee('<meta name="robots" content="index,follow,max-image-preview:large">', false);

        $typeUrl = route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]);
        $this->get($typeUrl)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$typeUrl.'">', false)
            ->assertSee('Family Photo Session | More Than Photos | Appointment.to')
            ->assertSee('Relaxed family photography in Cornwall. Book online with More Than Photos.')
            ->assertSee('"Service"', false);
    }

    public function test_public_social_images_follow_appointment_organization_and_platform_fallbacks(): void
    {
        // Storage::fake() builds and caches the fake disk from the current
        // filesystem configuration, so set the CDN URL before faking it.
        config([
            'filesystems.disks.public.url' => 'https://images.appointment.to/storage',
            'filesystems.public_asset_url' => 'https://images.appointment.to',
            'organizations.logo_disk' => 'public',
            'appointment-types.logo_disk' => 'public',
        ]);
        Storage::fake('public');

        $organization = Organization::factory()->create([
            'name' => 'More Than Photos',
            'slug' => 'more-than-photos',
            'logo_path' => 'organizations/logos/mtp.png',
        ]);
        Storage::disk('public')->put($organization->logo_path, 'organization-logo');

        $type = $this->type($organization, 'Family Session', AppointmentVisibility::Public, [
            'logo_path' => 'appointment-types/logos/family.png',
        ]);
        Storage::disk('public')->put($type->logo_path, 'appointment-logo');

        $organizationUrl = route('public.appointment-types.index', $organization->slug);
        $this->get($organizationUrl)
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://images.appointment.to/storage/organizations/logos/mtp.png">', false)
            ->assertSee('<link rel="icon" href="https://images.appointment.to/storage/organizations/logos/mtp.png">', false);

        $typeUrl = route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]);
        $this->get($typeUrl)
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://images.appointment.to/storage/appointment-types/logos/family.png">', false)
            ->assertSee('<link rel="icon" href="https://images.appointment.to/storage/appointment-types/logos/family.png">', false);

        $type->update(['logo_path' => null]);
        $this->get($typeUrl)
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://images.appointment.to/storage/organizations/logos/mtp.png">', false);

        $organization->update(['logo_path' => null]);
        $this->get($organizationUrl)
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://images.appointment.to/images/appointment-to-logo.png">', false)
            ->assertSee('<link rel="icon" href="https://images.appointment.to/images/appointment-to-logo.png">', false);
    }

    public function test_secret_and_password_protected_appointment_pages_are_noindex(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        $unlisted = $this->type($organization, 'Secret Session', AppointmentVisibility::Unlisted, [
            'public_token' => 'secret-token-123',
        ]);
        $protected = $this->type($organization, 'Protected Session', AppointmentVisibility::PasswordProtected, [
            'access_password' => bcrypt('secret'),
        ]);

        $this->get(route('public.appointment-types.unlisted', [
            'organizationSlug' => $organization->slug,
            'token' => $unlisted->public_token,
        ]))->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false)
            ->assertDontSee('application/ld+json', false);

        $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $protected->slug,
        ]))->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
    }

    public function test_sitemap_contains_only_indexable_public_appointment_pages(): void
    {
        $organization = Organization::factory()->create(['slug' => 'demo']);
        $public = $this->type($organization, 'Public Session', AppointmentVisibility::Public);
        $unlisted = $this->type($organization, 'Secret Session', AppointmentVisibility::Unlisted, [
            'public_token' => 'secret-token',
        ]);
        $this->type($organization, 'Protected Session', AppointmentVisibility::PasswordProtected, [
            'access_password' => bcrypt('secret'),
        ]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('pricing'), false)
            ->assertSee(route('public.appointment-types.index', $organization->slug), false)
            ->assertSee(route('public.appointment-types.show', [
                'organizationSlug' => $organization->slug,
                'appointmentSlug' => $public->slug,
            ]), false);

        $response->assertDontSee($unlisted->slug, false)
            ->assertDontSee('protected-session', false);
    }

    public function test_robots_file_points_to_dynamic_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Sitemap: '.route('seo.sitemap'));
    }

    private function type(
        Organization $organization,
        string $name,
        AppointmentVisibility $visibility,
        array $extra = [],
    ): AppointmentType {
        return AppointmentType::create(array_merge([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => (string) str($name)->slug(),
            'visibility' => $visibility,
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'is_active' => true,
        ], $extra));
    }
}
