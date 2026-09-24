<?php

namespace App\Support\Seo;

use App\Enums\AppointmentVisibility;
use App\Enums\LocationDisclosureMode;
use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicSeo
{
    public function defaultImageUrl(): string
    {
        $baseUrl = rtrim((string) config('filesystems.public_asset_url', config('app.url')), '/');

        return $baseUrl.'/images/appointment-to-logo.png';
    }

    public function home(): array
    {
        $url = route('home');
        $description = 'Appointment.to helps businesses accept appointments, events and payments online while coordinating staff, resources and calendars. Start free and grow when you need more.';
        $organizationId = $url.'#organization';

        return [
            'indexable' => true,
            'title' => 'Appointment.to | Simple online scheduling for real-world businesses',
            'description' => $description,
            'canonical' => $url,
            'type' => 'website',
            'image' => $this->defaultImageUrl(),
            'icon' => $this->defaultImageUrl(),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $organizationId,
                        'name' => 'Appointment.to',
                        'url' => $url,
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $url.'#website',
                        'url' => $url,
                        'name' => 'Appointment.to',
                        'publisher' => ['@id' => $organizationId],
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => 'Appointment.to',
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                        'url' => $url,
                        'description' => $description,
                        'publisher' => ['@id' => $organizationId],
                    ],
                ],
            ],
        ];
    }

    public function pricing(): array
    {
        $url = route('pricing');
        $description = 'Compare Appointment.to Free and Business plans, included capacity and optional Business add-ons.';

        return [
            'indexable' => true,
            'title' => 'Pricing | Appointment.to',
            'description' => $description,
            'canonical' => $url,
            'type' => 'website',
            'image' => $this->defaultImageUrl(),
            'icon' => $this->defaultImageUrl(),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Appointment.to pricing',
                'url' => $url,
                'description' => $description,
            ],
        ];
    }

    public function organization(Organization $organization, Collection $visibleAppointmentTypes): array
    {
        $url = route('public.appointment-types.index', $organization->slug);
        $hasPublicTypes = $organization->appointmentTypes()
            ->where('is_active', true)
            ->where('visibility', AppointmentVisibility::Public->value)
            ->exists();

        $names = $visibleAppointmentTypes
            ->take(3)
            ->pluck('name')
            ->filter()
            ->implode(', ');

        $description = $names !== ''
            ? sprintf('Book appointments with %s online. Choose from %s and view availability, pricing and booking details.', $organization->name, $names)
            : sprintf('Book appointments with %s online. View available services, booking details and appointment times.', $organization->name);

        $description = $this->limit($description);
        $sameAs = collect([
            $organization->facebook_url,
            $organization->instagram_url,
            $organization->x_url,
            $organization->linkedin_url,
            $organization->tiktok_url,
            $organization->youtube_url,
        ])->filter(fn (mixed $url): bool => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false)
            ->values()
            ->all();

        $image = $organization->logo_url ?? $this->defaultImageUrl();

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $organization->name,
            'url' => $url,
            'description' => $description,
        ];

        if ($organization->logo_url) {
            $schema['logo'] = $organization->logo_url;
        }

        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }

        return [
            'indexable' => $hasPublicTypes,
            'title' => $organization->name.' appointments | Appointment.to',
            'description' => $description,
            'canonical' => $url,
            'type' => 'website',
            'image' => $image,
            'icon' => $image,
            'jsonLd' => $schema,
        ];
    }

    public function appointment(Organization $organization, AppointmentType $type, string $accessMode): array
    {
        $indexable = $accessMode === 'direct' && $type->visibility === AppointmentVisibility::Public;
        $url = route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]);

        $description = $this->plainText($type->safeDescriptionHtml());
        if ($description === '') {
            $description = sprintf(
                'Book %s with %s. View duration, pricing, location and available appointment times online.',
                $type->name,
                $organization->name,
            );
        } else {
            $description = $description.' Book online with '.$organization->name.'.';
        }
        $description = $this->limit($description);

        $image = $type->galleryPhotos->first()?->url
            ?? $type->logo_url
            ?? $organization->logo_url
            ?? $this->defaultImageUrl();

        $canPublishEventSchema = $type->ticketing_enabled
            && $type->currentEventOccurrence() !== null
            && ($type->is_online
                || ($type->location_disclosure_mode === LocationDisclosureMode::Public
                    && filled($type->event_location)));

        return [
            'indexable' => $indexable,
            'title' => $type->name.' | '.$organization->name.' | Appointment.to',
            'description' => $description,
            'canonical' => $url,
            'type' => $canPublishEventSchema ? 'event' : 'website',
            'image' => $image,
            'icon' => $image,
            'jsonLd' => $indexable
                ? ($canPublishEventSchema
                    ? $this->eventSchema($organization, $type, $url, $description, $image)
                    : $this->serviceSchema($organization, $type, $url, $description, $image))
                : null,
        ];
    }

    private function serviceSchema(
        Organization $organization,
        AppointmentType $type,
        string $url,
        string $description,
        ?string $image,
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $type->name,
            'url' => $url,
            'description' => $description,
            'provider' => [
                '@type' => 'Organization',
                'name' => $organization->name,
                'url' => route('public.appointment-types.index', $organization->slug),
            ],
        ];

        if ($image) {
            $schema['image'] = $image;
        }

        return $schema;
    }

    private function eventSchema(
        Organization $organization,
        AppointmentType $type,
        string $url,
        string $description,
        ?string $image,
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $type->name,
            'url' => $url,
            'description' => $description,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $type->is_online
                ? 'https://schema.org/OnlineEventAttendanceMode'
                : 'https://schema.org/OfflineEventAttendanceMode',
            'organizer' => [
                '@type' => 'Organization',
                'name' => $organization->name,
                'url' => route('public.appointment-types.index', $organization->slug),
            ],
        ];

        $event = $type->currentEventOccurrence();
        if ($event) {
            $doors = $event->starts_at_utc->setTimezone($organization->timezone);
            $schema['doorTime'] = $doors->toIso8601String();
            $schema['startDate'] = $doors->addMinutes((int) $type->show_start_offset_minutes)->toIso8601String();

            if ($type->show_end_offset_minutes !== null) {
                $schema['endDate'] = $doors->addMinutes((int) $type->show_end_offset_minutes)->toIso8601String();
            }
        }

        if ($type->is_online) {
            $schema['location'] = [
                '@type' => 'VirtualLocation',
                'url' => $url,
            ];
        } elseif ($type->location_disclosure_mode === LocationDisclosureMode::Public
            && filled($type->event_location)) {
            $schema['location'] = [
                '@type' => 'Place',
                'name' => $type->event_location,
            ];
        }

        if ($image) {
            $schema['image'] = [$image];
        }

        return $schema;
    }

    private function plainText(?string $html): string
    {
        if (! is_string($html) || $html === '') {
            return '';
        }

        return Str::squish(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function limit(string $value): string
    {
        return Str::limit(Str::squish($value), 155, '');
    }
}
