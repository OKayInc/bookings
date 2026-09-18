<?php

namespace App\Http\Controllers\Api;

use App\Domain\Bookings\PublicBookingAvailabilityService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Rules\IanaTimezone;
use App\Support\Organizations\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SchedulingController extends Controller
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function me(Request $request)
    {
        $org = $this->context->organization();
        return response()->json(['data' => ['user_uuid' => $request->user()->uuid, 'organization_uuid' => $org->uuid,
            'organization_name' => $org->name, 'role' => $request->attributes->get('api_membership')->role->value]]);
    }

    private function authorizeScheduling(): void
    {
        $this->authorize('manageScheduling', $this->context->organization());
    }

    private function page(Request $request, $query, callable $transform)
    {
        $data = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $page = $query->paginate($data['per_page'] ?? 25);
        return response()->json(['data' => $page->getCollection()->map($transform), 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
        ]]);
    }

    public function resources(Request $request)
    {
        $this->authorizeScheduling();
        return $this->page($request, $this->context->organization()->resources()->orderBy('resources.id'),
            fn ($r) => ['uuid' => $r->uuid, 'name' => $r->name, 'type' => $r->type, 'is_active' => $r->is_active,
                'quantity_enabled' => $r->quantity_enabled, 'inventory_quantity' => $r->inventory_quantity]);
    }

    public function types(Request $request)
    {
        $this->authorizeScheduling();
        return $this->page($request, $this->context->organization()->appointmentTypes()->orderBy('id'),
            fn ($t) => ['uuid' => $t->uuid, 'name' => $t->name, 'slug' => $t->slug, 'is_active' => $t->is_active,
                'capacity' => $t->capacity, 'ticketing_enabled' => $t->ticketing_enabled]);
    }

    public function disable(string $uuid)
    {
        $this->authorizeScheduling();
        $type = $this->context->organization()->appointmentTypes()->whereUuid($uuid)->firstOrFail();
        \Illuminate\Support\Facades\DB::transaction(function () use ($type): void {
            $type->update(['is_active' => false]);
            $type->bookingHolds()->where('status', \App\Enums\BookingHoldStatus::Active->value)
                ->update(['status' => \App\Enums\BookingHoldStatus::Released->value, 'updated_at' => now()]);
        });
        return response()->json(['data' => ['uuid' => $type->uuid, 'is_active' => false]]);
    }

    public function availability(Request $request, string $uuid, PublicBookingAvailabilityService $availability)
    {
        $this->authorizeScheduling();
        $type = $this->context->organization()->appointmentTypes()->whereUuid($uuid)->firstOrFail();
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'timezone' => ['sometimes', new IanaTimezone],
            'duration_value' => ['nullable', 'integer', 'min:1'], 'attendee_count' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $timezone = $data['timezone'] ?? $this->context->organization()->timezone;
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], $timezone);
        $count = (int) ($data['attendee_count'] ?? 1);
        if (! $type->is_active || $count > $type->capacity) {
            return response()->json(['data' => []]);
        }
        try {
            $slots = $availability->slots($type, $start->utc(), $start->addDay()->utc(), $data['duration_value'] ?? null, $timezone, $count);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['duration_value' => $e->getMessage()]);
        }
        return response()->json(['data' => collect($slots)->filter(fn ($s) => $s->remainingCapacity >= $count)->values()->map(fn ($s) => [
            'starts_at_utc' => $s->startsAtUtc->toIso8601String(), 'ends_at_utc' => $s->endsAtUtc->toIso8601String(),
            'remaining_capacity' => $s->remainingCapacity,
        ])]);
    }

    public function bookings(Request $request)
    {
        $this->authorizeScheduling();
        return $this->page($request, $this->context->organization()->bookings()->with(['appointment', 'appointmentType'])->orderBy('id'), $this->bookingData(...));
    }

    public function booking(string $uuid)
    {
        $this->authorizeScheduling();
        $booking = $this->context->organization()->bookings()->whereUuid($uuid)->with(['appointment', 'appointmentType'])->firstOrFail();
        return response()->json(['data' => $this->bookingData($booking)]);
    }

    private function bookingData(Booking $b): array
    {
        return ['uuid' => $b->uuid, 'reference' => $b->reference, 'status' => $b->status->value,
            'appointment_type_uuid' => $b->appointmentType?->uuid, 'attendee_count' => $b->attendee_count,
            'starts_at_utc' => $b->appointment?->starts_at_utc?->toIso8601String(), 'ends_at_utc' => $b->appointment?->ends_at_utc?->toIso8601String(),
            'first_name' => $b->first_name, 'last_name' => $b->last_name, 'email' => $b->email,
            'price_minor' => $b->price_minor, 'currency' => $b->currency, 'payment_status' => $b->payment_status?->value];
    }
}
