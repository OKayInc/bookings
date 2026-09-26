<?php

namespace App\Support\Analytics;

use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Http\Request;

class GoogleAnalytics
{
    public const MEASUREMENT_ID_PATTERN = '/\AG-[A-Z0-9]+\z/';

    public static function normalizeId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return strlen($value) <= 64 && preg_match(self::MEASUREMENT_ID_PATTERN, $value) === 1
            ? $value
            : null;
    }

    /** @return list<string> */
    public function measurementIds(Request $request, ?Organization $organization = null, ?AppointmentType $type = null): array
    {
        $marketingPage = $request->routeIs('home', 'pricing', 'legal.privacy', 'legal.terms');
        $organizationPage = $organization !== null && (
            $request->routeIs('public.appointment-types.index', 'public.coupons.index')
            || ($request->routeIs('public.appointment-types.show')
                && $type?->visibility === AppointmentVisibility::Public
                && $type->organization_id === $organization->getKey())
        );

        // Never load third-party analytics on private/token-bearing booking,
        // questionnaire, payment, invitation, account or administration pages.
        if (! $marketingPage && ! $organizationPage) {
            return [];
        }

        return array_values(array_unique(array_filter([
            self::normalizeId(config('analytics.google_measurement_id')),
            $organizationPage ? self::normalizeId($organization->google_analytics_measurement_id) : null,
        ])));
    }
}
