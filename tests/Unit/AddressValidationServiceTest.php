<?php

namespace Tests\Unit;

use App\Domain\Questionnaires\AddressValidationService;
use App\Models\Organization;
use App\Models\OrganizationConferenceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_address_response_is_normalized(): void
    {
        config(['questionnaire.google.api_key' => 'test-key']);

        Http::fake([
            'https://addressvalidation.googleapis.com/*' => Http::response([
                'result' => [
                    'verdict' => [
                        'addressComplete' => true,
                    ],
                    'address' => [
                        'formattedAddress' => '1730 Vincent Massey Dr, Cornwall, ON, Canada',
                    ],
                    // Google Address Validation returns geocode as a sibling of
                    // address under result, not nested inside address.
                    'geocode' => [
                        'placeId' => 'place-123',
                        'location' => [
                            'latitude' => 45.0,
                            'longitude' => -74.7,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(AddressValidationService::class)
            ->validate('1730 Vincent Massey Dr, Cornwall', 'CA');

        $this->assertSame('1730 Vincent Massey Dr, Cornwall, ON, Canada', $result['formatted_address']);
        $this->assertSame('place-123', $result['place_id']);
        $this->assertIsFloat($result['latitude']);
        $this->assertIsFloat($result['longitude']);
        $this->assertSame(45.0, $result['latitude']);
        $this->assertSame(-74.7, $result['longitude']);
        $this->assertTrue($result['verdict']['addressComplete']);
    }

    public function test_organization_address_key_overrides_deployment_key(): void
    {
        config(['questionnaire.google.api_key' => 'deployment-key']);
        $organization = Organization::factory()->create();
        OrganizationConferenceSetting::create([
            'organization_id' => $organization->getKey(),
            'google_maps_api_key' => 'organization-key',
        ]);
        Http::fake([
            '*' => Http::response(['result' => [
                'verdict' => ['addressComplete' => true],
                'address' => ['formattedAddress' => '1 Main Street, Ottawa, ON, Canada'],
                'geocode' => ['placeId' => 'place-organization'],
            ]]),
        ]);

        app(AddressValidationService::class)->validate('1 Main Street', 'CA', $organization);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'key=organization-key'));
    }
}
