<?php

namespace App\Domain\Questionnaires;

use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DrivingDistanceService
{
    public function between(string $originAddress, string $destinationAddress, ?Organization $organization = null): int
    {
        $originAddress = trim($originAddress);
        $destinationAddress = trim($destinationAddress);

        if ($originAddress === '' || $destinationAddress === '') {
            throw new RuntimeException('A complete origin and destination are required to calculate driving distance.');
        }

        $organization?->loadMissing('conferenceSettings');
        $settings = $organization?->conferenceSettings;
        $key = (string) ($settings?->google_routes_api_key
            ?: $settings?->google_maps_api_key
            ?: config('questionnaire.google.routes_api_key'));
        if ($key === '') {
            throw new RuntimeException('Driving distance pricing is not configured. Add a Google Routes API key in Organization > Settings or set GOOGLE_ROUTES_API_KEY.');
        }

        $cacheKey = 'questionnaire:driving-distance:'.hash(
            'sha256',
            ($organization === null ? 'platform' : bin2hex($organization->getKey()))
                .'|'.strtolower($originAddress).'|'.strtolower($destinationAddress),
        );

        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('questionnaire.google.routes_cache_seconds', 900)),
            function () use ($key, $originAddress, $destinationAddress): int {
                $response = Http::timeout((int) config('questionnaire.google.routes_timeout_seconds', 8))
                    ->acceptJson()
                    ->asJson()
                    ->withHeaders([
                        'X-Goog-Api-Key' => $key,
                        'X-Goog-FieldMask' => 'routes.distanceMeters',
                    ])
                    ->post((string) config('questionnaire.google.routes_url'), [
                        'origin' => ['address' => $originAddress],
                        'destination' => ['address' => $destinationAddress],
                        'travelMode' => 'DRIVE',
                        'routingPreference' => 'TRAFFIC_UNAWARE',
                        'computeAlternativeRoutes' => false,
                        'units' => 'METRIC',
                    ]);

                if (! $response->successful()) {
                    throw new RuntimeException('The driving distance service is temporarily unavailable.');
                }

                $meters = $response->json('routes.0.distanceMeters');
                if (! is_int($meters) && ! (is_string($meters) && ctype_digit($meters))) {
                    throw new RuntimeException('A driving route could not be found for this address.');
                }

                $meters = (int) $meters;
                if ($meters < 0) {
                    throw new RuntimeException('A driving route could not be found for this address.');
                }

                return $meters;
            },
        );
    }
}
