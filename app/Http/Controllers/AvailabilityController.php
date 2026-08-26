<?php

namespace App\Http\Controllers;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Enums\AvailabilityScope;
use App\Http\Requests\StoreAvailabilityScheduleRequest;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use DateTimeZone;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);

        $schedules = $organization->availabilitySchedules()->get()->keyBy(
            fn (AvailabilitySchedule $schedule): string => $schedule->scope_type->value.'|'.$schedule->scope_uuid,
        );

        return view('availability.index', [
            'organization' => $organization,
            'organizationSchedule' => $schedules->get(AvailabilityScope::Organization->value.'|'.$organization->uuid),
            'resources' => $organization->resources()->orderBy('name')->get(),
            'appointmentTypes' => $organization->appointmentTypes()->orderBy('name')->get(),
            'schedules' => $schedules,
        ]);
    }

    public function editOrganization(OrganizationContext $context, AvailabilityScheduleService $service): View
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);

        return $this->editView($organization, AvailabilityScope::Organization, $organization, $service->find($organization, AvailabilityScope::Organization, $organization), 'Organization default hours');
    }

    public function updateOrganization(StoreAvailabilityScheduleRequest $request, OrganizationContext $context, AvailabilityScheduleService $service): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $service->save($organization, AvailabilityScope::Organization, $organization, $data['timezone'], $request->boolean('is_active'), $data['rules'] ?? []);

        return redirect()->route('availability.index')->with('success', 'Organization availability updated.');
    }

    public function editResource(Resource $resource, OrganizationContext $context, AvailabilityScheduleService $service): View
    {
        $organization = $context->organization();
        $this->ensureOwned($resource->organization_id, $organization->getKey());
        $this->authorize('manageScheduling', $organization);

        return $this->editView($organization, AvailabilityScope::Resource, $resource, $service->find($organization, AvailabilityScope::Resource, $resource), 'Resource: '.$resource->name);
    }

    public function updateResource(StoreAvailabilityScheduleRequest $request, Resource $resource, OrganizationContext $context, AvailabilityScheduleService $service): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($resource->organization_id, $organization->getKey());
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $service->save($organization, AvailabilityScope::Resource, $resource, $data['timezone'], $request->boolean('is_active'), $data['rules'] ?? []);

        return redirect()->route('availability.index')->with('success', 'Resource availability updated.');
    }

    public function resetResource(Resource $resource, OrganizationContext $context, AvailabilityScheduleService $service): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($resource->organization_id, $organization->getKey());
        $this->authorize('manageScheduling', $organization);
        $service->removeCustom($organization, AvailabilityScope::Resource, $resource);

        return redirect()->route('availability.index')->with('success', 'Resource now inherits the organization schedule.');
    }

    public function editAppointmentType(AppointmentType $appointmentType, OrganizationContext $context, AvailabilityScheduleService $service): View
    {
        $organization = $context->organization();
        $this->ensureOwned($appointmentType->organization_id, $organization->getKey());
        $this->authorize('manageScheduling', $organization);

        return $this->editView($organization, AvailabilityScope::AppointmentType, $appointmentType, $service->find($organization, AvailabilityScope::AppointmentType, $appointmentType), 'Appointment type: '.$appointmentType->name);
    }

    public function updateAppointmentType(StoreAvailabilityScheduleRequest $request, AppointmentType $appointmentType, OrganizationContext $context, AvailabilityScheduleService $service): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($appointmentType->organization_id, $organization->getKey());
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $service->save($organization, AvailabilityScope::AppointmentType, $appointmentType, $data['timezone'], $request->boolean('is_active'), $data['rules'] ?? []);

        return redirect()->route('availability.index')->with('success', 'Appointment-type availability updated.');
    }

    public function resetAppointmentType(AppointmentType $appointmentType, OrganizationContext $context, AvailabilityScheduleService $service): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($appointmentType->organization_id, $organization->getKey());
        $this->authorize('manageScheduling', $organization);
        $service->removeCustom($organization, AvailabilityScope::AppointmentType, $appointmentType);

        return redirect()->route('availability.index')->with('success', 'Appointment type now inherits the organization schedule.');
    }

    private function editView($organization, AvailabilityScope $scope, $owner, ?AvailabilitySchedule $schedule, string $title): View
    {
        $schedule?->load(['rules', 'exceptions']);

        return view('availability.edit', [
            'organization' => $organization,
            'scope' => $scope,
            'owner' => $owner,
            'schedule' => $schedule,
            'title' => $title,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    private function ensureOwned(mixed $candidate, mixed $organization): void
    {
        abort_unless(hash_equals((string) $candidate, (string) $organization), 404);
    }
}
