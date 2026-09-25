<?php

namespace Tests\Unit;

use App\Domain\Questionnaires\DrivingDistanceService;
use App\Models\Organization;
use App\Models\OrganizationConferenceSetting;
use App\Models\PlanUsageMonth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DrivingDistanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['questionnaire.google.routes_api_key' => 'routes-test-key']);
    }

    public function test_google_driving_distance_is_requested_with_a_narrow_field_mask_and_cached(): void
    {
        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [['distanceMeters' => 24140]],
            ]),
        ]);

        $service = app(DrivingDistanceService::class);
        $this->assertSame(24140, $service->between('100 Origin St, Ottawa, ON', '200 Client St, Gatineau, QC'));
        $this->assertSame(24140, $service->between('100 Origin St, Ottawa, ON', '200 Client St, Gatineau, QC'));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://routes.googleapis.com/directions/v2:computeRoutes'
                && $request->hasHeader('X-Goog-Api-Key', 'routes-test-key')
                && $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters')
                && $request['origin']['address'] === '100 Origin St, Ottawa, ON'
                && $request['destination']['address'] === '200 Client St, Gatineau, QC'
                && $request['travelMode'] === 'DRIVE'
                && $request['routingPreference'] === 'TRAFFIC_UNAWARE';
        });
    }

    public function test_missing_route_is_rejected_instead_of_silently_omitting_the_fee(): void
    {
        Http::fake([
            'https://routes.googleapis.com/*' => Http::response(['routes' => []]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A driving route could not be found');

        app(DrivingDistanceService::class)->between('Origin', 'Unroutable destination');
    }

    public function test_organization_routes_key_overrides_deployment_key(): void
    {
        $organization = Organization::factory()->create();
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'google_routes_api_key' => 'organization-routes-key',
        ]);
        Http::fake(['*' => Http::response(['routes' => [['distanceMeters' => 1000]]])]);

        app(DrivingDistanceService::class)->between('Origin', 'Destination', $organization);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'X-Goog-Api-Key',
            'organization-routes-key',
        ));
    }

    public function test_failed_routes_response_releases_quota_and_does_not_cache_failure(): void
    {
        $organization = Organization::factory()->create();
        Http::fake(['https://routes.googleapis.com/*' => Http::sequence()
            ->push([], 503)
            ->push(['routes' => [['distanceMeters' => 1000]]])]);

        $service = app(DrivingDistanceService::class);
        try {
            $service->between('Origin', 'Destination', $organization);
            $this->fail('The provider error should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('temporarily unavailable', $e->getMessage());
        }

        $this->assertSame(0, PlanUsageMonth::query()->where('organization_id', $organization->getKey())->firstOrFail()->distance_lookup_count);
        $this->assertSame(1000, $service->between('Origin', 'Destination', $organization));
        $this->assertSame(1, PlanUsageMonth::query()->where('organization_id', $organization->getKey())->firstOrFail()->distance_lookup_count);
        Http::assertSentCount(2);
    }

    public function test_connection_failure_reports_provider_error_without_using_quota(): void
    {
        $organization = Organization::factory()->create();
        Http::fake(function (): never {
            throw new ConnectionException('Routes connection timed out.');
        });

        try {
            app(DrivingDistanceService::class)->between('Origin', 'Destination', $organization);
            $this->fail('The connection error should be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame('The driving distance service is temporarily unavailable.', $e->getMessage());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }

        $this->assertSame(0, PlanUsageMonth::query()->where('organization_id', $organization->getKey())->firstOrFail()->distance_lookup_count);
    }
}
