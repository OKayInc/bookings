<?php

namespace Tests\Feature;

use App\Domain\Bookings\PublicAppointmentAccessService;
use App\Enums\AppointmentVisibility;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Analytics\GoogleAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['analytics.google_measurement_id' => null]);
    }

    public function test_tracking_is_absent_when_neither_id_is_configured(): void
    {
        $organization = Organization::factory()->create();

        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com');
        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()->assertDontSee('googletagmanager.com');
    }

    public function test_public_organization_and_appointment_pages_configure_both_properties_with_one_loader(): void
    {
        config(['analytics.google_measurement_id' => 'G-PLATFORM1']);
        $organization = Organization::factory()->create(['google_analytics_measurement_id' => 'G-CUSTOMER1']);
        $type = $this->type($organization);

        foreach ([
            route('public.appointment-types.index', $organization->slug),
            route('public.appointment-types.show', [$organization->slug, $type->slug]),
            route('public.coupons.index', $organization->slug),
        ] as $url) {
            $response = $this->get($url)->assertOk()
                ->assertSee("gtag('config', 'G-PLATFORM1');", false)
                ->assertSee("gtag('config', 'G-CUSTOMER1');", false)
                ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-PLATFORM1', false);

            $this->assertSame(1, substr_count($response->getContent(), 'googletagmanager.com/gtag/js'));
            $this->assertSame(2, substr_count($response->getContent(), "gtag('config',"));
        }
    }

    public function test_platform_pages_never_use_the_logged_in_members_customer_property(): void
    {
        config(['analytics.google_measurement_id' => 'G-PLATFORM1']);
        [$user, $organization] = $this->ownedOrganization();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);

        foreach (['/', '/pricing', '/a/privacy.html', '/a/terms.html'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee("gtag('config', 'G-PLATFORM1');", false)
                ->assertDontSee('G-CUSTOMER1');
        }
    }

    public function test_either_property_works_alone_and_duplicate_ids_are_configured_once(): void
    {
        $organization = Organization::factory()->create(['google_analytics_measurement_id' => 'G-CUSTOMER1']);
        $url = route('public.appointment-types.index', $organization->slug);

        $this->get($url)->assertOk()
            ->assertSee('gtag/js?id=G-CUSTOMER1', false)
            ->assertSee("gtag('config', 'G-CUSTOMER1');", false);

        config(['analytics.google_measurement_id' => ' g-customer1 ']);
        $response = $this->get($url)->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), "gtag('config',"));

        $organization->update(['google_analytics_measurement_id' => null]);
        $this->get($url)->assertOk()->assertSee("gtag('config', 'G-CUSTOMER1');", false);
    }

    public function test_another_organization_never_receives_the_active_organizations_id(): void
    {
        [$user, $organization] = $this->ownedOrganization();
        $other = Organization::factory()->create(['google_analytics_measurement_id' => 'G-OTHER1234']);

        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('public.appointment-types.index', $other->slug))
            ->assertOk()->assertSee("gtag('config', 'G-OTHER1234');", false)
            ->assertDontSee('G-CUSTOMER1');
    }

    public function test_invalid_configured_ids_never_render_as_scripts(): void
    {
        config(['analytics.google_measurement_id' => 'G-X\');alert(1);//']);
        $organization = Organization::factory()->create(['google_analytics_measurement_id' => '<script>alert(2)</script>']);

        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()->assertDontSee('googletagmanager.com')->assertDontSee('alert(2)');
    }

    public function test_page_locations_exclude_query_strings_and_referrers_are_reduced_to_origins(): void
    {
        config(['analytics.google_measurement_id' => 'G-PLATFORM1']);

        $this->get('/?email=private@example.test&token=private-secret')
            ->assertOk()
            ->assertSee("gtag('set', 'page_location', 'http:\\/\\/localhost');", false)
            ->assertSee("new URL(document.referrer).origin + '/'", false)
            ->assertDontSee('private@example.test')->assertDontSee('private-secret');
    }

    public function test_private_appointment_pages_and_account_pages_do_not_load_analytics(): void
    {
        config(['analytics.google_measurement_id' => 'G-PLATFORM1']);
        $organization = Organization::factory()->create(['google_analytics_measurement_id' => 'G-CUSTOMER1']);
        $passwordType = $this->type($organization, ['visibility' => AppointmentVisibility::PasswordProtected]);
        $url = route('public.appointment-types.show', [$organization->slug, $passwordType->slug]);

        $this->get($url)->assertOk()->assertDontSee('googletagmanager.com');
        $this->withSession([app(PublicAppointmentAccessService::class)->passwordSessionKey($passwordType) => true])
            ->get($url)->assertOk()->assertDontSee('googletagmanager.com');

        $unlisted = $this->type($organization, [
            'slug' => 'secret', 'visibility' => AppointmentVisibility::Unlisted, 'public_token' => 'private-link-token',
        ]);
        $this->get(route('public.appointment-types.unlisted', [$organization->slug, $unlisted->public_token]))
            ->assertOk()->assertDontSee('googletagmanager.com');

        foreach (['/login', '/register'] as $accountUrl) {
            $this->get($accountUrl)->assertOk()->assertDontSee('googletagmanager.com');
        }
    }

    public function test_sensitive_routes_are_excluded_even_when_an_organization_and_public_type_are_present(): void
    {
        config(['analytics.google_measurement_id' => 'G-PLATFORM1']);
        $organization = Organization::factory()->create(['google_analytics_measurement_id' => 'G-CUSTOMER1']);
        $type = $this->type($organization);

        foreach ([
            'public.booking-holds.edit', 'public.bookings.received', 'public.bookings.manage',
            'public.payments.stripe.return', 'public.appointment-types.invited',
            'public.coupons.show', 'public.coupons.view', 'dashboard', 'organizations.edit',
        ] as $routeName) {
            $request = Request::create('/private/token');
            $route = (new Route('GET', '/private/token', fn () => null))->name($routeName);
            $request->setRouteResolver(fn () => $route);
            $this->assertSame([], app(GoogleAnalytics::class)->measurementIds($request, $organization, $type), $routeName);
        }
    }

    public function test_owner_can_save_normalize_and_clear_the_id_and_invalidate_cached_configuration(): void
    {
        config(['analytics.google_measurement_id' => 'G-PLATFORM1']);
        [$user, $organization] = $this->ownedOrganization();
        $cache = Cache::store('array');
        $cacheKey = 'organization:'.hash('sha256', $organization->slug);
        $cache->forever($cacheKey, $organization);

        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid])
            ->get(route('organizations.edit', $organization))->assertOk()
            ->assertSee('name="google_analytics_measurement_id"', false)
            ->assertDontSee('googletagmanager.com');

        $this->put(route('organizations.update', $organization), [
            ...$this->organizationData($organization), 'google_analytics_measurement_id' => ' g-updated123 ',
        ])->assertSessionHasNoErrors()->assertRedirect(route('organizations.index'));
        $this->assertSame('G-UPDATED123', $organization->fresh()->google_analytics_measurement_id);
        $this->assertFalse($cache->has($cacheKey));
        $this->get(route('public.appointment-types.index', $organization->slug))->assertOk()
            ->assertSee("gtag('config', 'G-UPDATED123');", false)->assertDontSee('G-CUSTOMER1');

        $this->put(route('organizations.update', $organization), [
            ...$this->organizationData($organization), 'google_analytics_measurement_id' => ' ',
        ])->assertSessionHasNoErrors();
        $this->assertNull($organization->fresh()->google_analytics_measurement_id);
        $this->get(route('public.appointment-types.index', $organization->slug))->assertOk()
            ->assertSee("gtag('config', 'G-PLATFORM1');", false)->assertDontSee('G-UPDATED123');
    }

    public function test_omitting_the_id_during_other_updates_preserves_it(): void
    {
        [$user, $organization] = $this->ownedOrganization();
        $this->actingAs($user)->put(route('organizations.update', $organization), $this->organizationData($organization))
            ->assertSessionHasNoErrors();
        $this->assertSame('G-CUSTOMER1', $organization->fresh()->google_analytics_measurement_id);
    }

    public function test_invalid_ids_are_rejected_and_other_members_cannot_change_them(): void
    {
        [$user, $organization] = $this->ownedOrganization();
        $this->actingAs($user);

        foreach (['UA-12345-1', 'GTM-ABC123', 'https://example.test', '<script>alert(1)</script>', 'G-X\');alert(1);//', ['G-ARRAY'], 'G-'.str_repeat('A', 63)] as $invalidId) {
            $this->put(route('organizations.update', $organization), [
                ...$this->organizationData($organization), 'google_analytics_measurement_id' => $invalidId,
            ])->assertSessionHasErrors('google_analytics_measurement_id');
            $this->assertSame('G-CUSTOMER1', $organization->fresh()->google_analytics_measurement_id);
        }

        $otherUser = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $otherUser->person_id,
            'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active,
        ]);
        $this->actingAs($otherUser)->put(route('organizations.update', $organization), [
            ...$this->organizationData($organization), 'google_analytics_measurement_id' => 'G-UNAUTHORIZED',
        ])->assertForbidden();
        $this->assertSame('G-CUSTOMER1', $organization->fresh()->google_analytics_measurement_id);
    }

    public function test_an_id_can_be_saved_when_creating_an_organization(): void
    {
        $this->actingAs(User::factory()->create())->post(route('organizations.store'), [
            'name' => 'Analytics Example', 'timezone' => 'America/Toronto', 'currency' => 'CAD',
            'google_analytics_measurement_id' => ' g-new123456 ',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('G-NEW123456', Organization::where('slug', 'analytics-example')->firstOrFail()->google_analytics_measurement_id);
    }

    private function ownedOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['google_analytics_measurement_id' => 'G-CUSTOMER1']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(), 'person_id' => $user->person_id,
            'role' => MembershipRole::Owner, 'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

    private function organizationData(Organization $organization): array
    {
        return ['name' => $organization->name, 'timezone' => $organization->timezone, 'currency' => $organization->currency];
    }

    private function type(Organization $organization, array $attributes = []): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(), 'name' => 'Public session', 'slug' => 'public-session',
            'visibility' => AppointmentVisibility::Public, 'is_active' => true, ...$attributes,
        ]);
    }
}
