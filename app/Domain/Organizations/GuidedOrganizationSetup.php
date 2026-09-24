<?php

namespace App\Domain\Organizations;

use App\Domain\Money\MoneyService;
use App\Domain\Plans\PlanLimitService;
use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Resource;
use Illuminate\Support\Str;

class GuidedOrganizationSetup
{
    public function __construct(
        private readonly MoneyService $money,
        private readonly PlanLimitService $planLimits,
    ) {
    }

    public function create(Organization $organization, Person $person, array $data): AppointmentType
    {
        $pricingMode = (string) ($data['guided_pricing_mode'] ?? 'free');
        $isOnline = ($data['guided_location_mode'] ?? 'in_person') === 'online';
        $attendanceMode = (string) ($data['guided_attendance_mode'] ?? 'single');
        $name = trim((string) $data['guided_appointment_name']);

        $this->planLimits->assertCanActivateAppointmentType($organization);

        $appointmentType = $organization->appointmentTypes()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($organization, $name),
            'visibility' => 'public',
            'attendance_mode' => $attendanceMode,
            'capacity' => $attendanceMode === 'group' ? (int) ($data['guided_capacity'] ?? 10) : 1,
            'is_online' => $isOnline,
            'meeting_provider' => $isOnline ? 'jitsi' : null,
            'duration_mode' => 'fixed',
            'duration_unit' => 'minute',
            'duration_value' => (int) $data['guided_duration_minutes'],
            'start_interval_minutes' => 15,
            'booking_notice_value' => (int) ($data['guided_booking_notice_hours'] ?? 24),
            'booking_notice_unit' => 'hour',
            'maximum_booking_notice_value' => 365,
            'maximum_booking_notice_unit' => 'day',
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => $pricingMode,
            'fixed_price_minor' => $pricingMode === 'fixed'
                ? $this->money->parse((string) $data['guided_fixed_price'], $organization->currency)
                : null,
            'requires_resource_confirmation' => false,
            'show_resources_to_clients' => true,
            'email_verification_mode' => 'before_confirmation',
            'is_active' => true,
        ]);

        if ((bool) ($data['guided_use_owner_resource'] ?? false)) {
            $this->planLimits->assertCanActivateResource($organization, true);

            $resource = Resource::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $person->getKey(),
                'type' => 'person',
                'name' => $person->full_name !== '' ? $person->full_name : 'Owner',
                'timezone' => $organization->timezone,
                'is_active' => true,
                'is_required_by_default' => true,
            ]);

            $appointmentType->resources()->syncWithoutDetaching([
                $resource->getKey() => [
                    'is_required' => true,
                    'requirement_mode' => 'required',
                ],
            ]);
        }

        $schedule = $organization->availabilitySchedules()->create([
            'scope_type' => AvailabilityScope::Organization->value,
            'scope_id' => $organization->getKey(),
            'timezone' => $organization->timezone,
            'is_active' => true,
        ]);

        $weekdays = array_map('intval', $data['guided_weekdays'] ?? []);
        sort($weekdays);
        foreach ($weekdays as $index => $weekday) {
            $schedule->rules()->create([
                'weekday' => $weekday,
                'start_time' => $data['guided_start_time'],
                'end_time' => $data['guided_end_time'],
                'sort_order' => $index,
            ]);
        }

        return $appointmentType;
    }

    private function uniqueSlug(Organization $organization, string $name): string
    {
        $base = Str::slug($name) ?: 'appointment';
        $slug = $base;
        $counter = 2;

        while ($organization->appointmentTypes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
