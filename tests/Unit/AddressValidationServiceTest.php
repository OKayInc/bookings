<?php

namespace Tests\Unit;

use App\Domain\Questionnaires\AddressValidationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressValidationServiceTest extends TestCase
{
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
}
