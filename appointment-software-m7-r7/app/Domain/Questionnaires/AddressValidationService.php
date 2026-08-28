<?php
namespace App\Domain\Questionnaires;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class AddressValidationService {
    public function validate(string $address, ?string $regionCode=null): array {
        $key=(string)config('questionnaire.google.api_key');
        if ($key==='') throw new RuntimeException('Address validation is not configured. Set GOOGLE_MAPS_API_KEY and enable Google Address Validation API.');
        $payload=['address'=>['addressLines'=>[trim($address)]]];
        if ($regionCode) $payload['address']['regionCode']=strtoupper($regionCode);
        $response=Http::timeout((int)config('questionnaire.google.timeout_seconds',8))
            ->acceptJson()->post(config('questionnaire.google.address_validation_url').'?key='.urlencode($key),$payload);
        if (!$response->successful()) throw new RuntimeException('The address validation service is temporarily unavailable.');
        $result=$response->json('result');
        $placeId=data_get($result,'geocode.placeId');
        $formatted=data_get($result,'address.formattedAddress');
        $unconfirmed=(bool)data_get($result,'verdict.hasUnconfirmedComponents',false);
        if (!$placeId || !$formatted || $unconfirmed) throw new RuntimeException('The address could not be verified. Please enter a complete existing address.');
        $latitude = data_get($result, 'geocode.location.latitude');
        $longitude = data_get($result, 'geocode.location.longitude');

        return [
            'formatted_address' => $formatted,
            'place_id' => $placeId,
            'latitude' => $latitude === null ? null : (float) $latitude,
            'longitude' => $longitude === null ? null : (float) $longitude,
            'verdict' => data_get($result, 'verdict', []),
        ];
    }
}
