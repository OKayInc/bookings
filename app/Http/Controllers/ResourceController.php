<?php

namespace App\Http\Controllers;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Requests\StoreResourceRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();

        return view('resources.index', [
            'organization' => $organization,
            'resources' => $organization->resources()->with(['person', 'organization'])->orderBy('name')->get(),
        ]);
    }

    public function create(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);

        return view('resources.create', $this->formData($organization, null));
    }

    public function store(StoreResourceRequest $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $personKey = $this->personKey($data, $organization);

        DB::transaction(function () use ($request, $organization, $data, $personKey): void {
            $resource = Resource::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $personKey,
                'name' => $data['name'],
                'type' => $data['type'],
                'timezone' => $data['timezone'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'is_required_by_default' => ($data['default_requirement'] ?? 'required') === 'required',
            ]);

            $this->syncSharedOrganizations($request, $resource, $organization);
        });

        return redirect()->route('resources.index')->with('success', 'Resource created.');
    }

    public function updateOrganizationSettings(Resource $resource, OrganizationContext $context, \Illuminate\Http\Request $request): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        abort_unless($resource->isAvailableToOrganization($organization), 404);

        $data = $request->validate([
            'default_requirement' => ['required', 'in:required,optional'],
        ]);
        $required = $data['default_requirement'] === 'required';

        $resource->organizations()->updateExistingPivot($organization->getKey(), [
            'is_required_by_default' => $required,
            'updated_at' => now(),
        ]);

        $resource->appointmentTypes()
            ->where('appointment_types.organization_id', $organization->getKey())
            ->wherePivot('requirement_mode', 'inherit')
            ->get()
            ->each(fn ($type) => $resource->appointmentTypes()->updateExistingPivot($type->getKey(), [
                'is_required' => $required,
                'updated_at' => now(),
            ]));

        if (hash_equals($resource->organization_id, $organization->getKey())) {
            $resource->forceFill(['is_required_by_default' => $required])->save();
        }

        return back()->with('success', 'Resource defaults updated for '.$organization->name.'.');
    }

    public function edit(Resource $resource, OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->ensureOwned($resource, $organization);
        $this->authorize('manage', $resource);

        return view('resources.edit', $this->formData($organization, $resource));
    }

    public function update(StoreResourceRequest $request, Resource $resource, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->ensureOwned($resource, $organization);
        $this->authorize('manage', $resource);
        $data = $request->validated();
        $personKey = $this->personKey($data, $organization);
        $defaultRequired = ($data['default_requirement'] ?? 'required') === 'required';

        DB::transaction(function () use ($request, $resource, $organization, $data, $personKey, $defaultRequired): void {
            $resource->update([
                'person_id' => $personKey,
                'name' => $data['name'],
                'type' => $data['type'],
                'timezone' => $data['timezone'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'is_required_by_default' => $defaultRequired,
            ]);

            $resource->organizations()->updateExistingPivot($organization->getKey(), [
                'is_required_by_default' => $defaultRequired,
                'updated_at' => now(),
            ]);
            $this->syncSharedOrganizations($request, $resource, $organization);

            $resource->appointmentTypes()
                ->where('appointment_types.organization_id', $organization->getKey())
                ->wherePivot('requirement_mode', 'inherit')
                ->get()
                ->each(fn ($type) => $resource->appointmentTypes()->updateExistingPivot($type->getKey(), [
                    'is_required' => $defaultRequired,
                    'updated_at' => now(),
                ]));
        });

        return redirect()->route('resources.index')->with('success', 'Resource updated.');
    }

    private function formData(Organization $organization, ?Resource $resource): array
    {
        return [
            'resource' => $resource,
            'members' => $organization->people()
                ->wherePivot('status', MembershipStatus::Active->value)
                ->orderBy('first_name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'shareableOrganizations' => $this->ownedOrganizations($organization),
            'sharedOrganizationKeys' => $resource
                ? $resource->organizations()->where('organizations.id', '!=', $organization->getKey())->pluck('organizations.id')->all()
                : [],
        ];
    }

    private function ownedOrganizations(Organization $current): \Illuminate\Support\Collection
    {
        if (! $this->userOwnsOrganization($current)) {
            return collect();
        }

        return auth()->user()->person->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->wherePivot('role', MembershipRole::Owner->value)
            ->where('organizations.id', '!=', $current->getKey())
            ->orderBy('name')
            ->get();
    }

    private function userOwnsOrganization(Organization $organization): bool
    {
        return $organization->memberships()
            ->where('person_id', auth()->user()->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->where('role', MembershipRole::Owner->value)
            ->exists();
    }

    private function syncSharedOrganizations(StoreResourceRequest $request, Resource $resource, Organization $owner): void
    {
        if (! $this->userOwnsOrganization($owner)) {
            return;
        }

        $allowed = $this->ownedOrganizations($owner)->keyBy('uuid');
        $requested = collect((array) $request->input('shared_organization_uuids', []))
            ->filter(fn ($uuid) => is_string($uuid) && $allowed->has($uuid));

        $sync = [
            $owner->getKey() => ['is_required_by_default' => (bool) $resource->is_required_by_default],
        ];
        foreach ($requested as $uuid) {
            $organization = $allowed->get($uuid);
            $existing = $resource->organizations()->whereKey($organization->getKey())->first();
            $sync[$organization->getKey()] = [
                'is_required_by_default' => (bool) ($existing?->pivot?->is_required_by_default ?? $resource->is_required_by_default),
            ];
        }

        $resource->organizations()->sync($sync);
    }

    private function personKey(array $data, Organization $organization): mixed
    {
        if (empty($data['person_uuid'])) {
            return null;
        }

        $person = Person::whereUuid($data['person_uuid'])->firstOrFail();
        abort_unless(
            $organization->memberships()->where('person_id', $person->getKey())->where('status', MembershipStatus::Active->value)->exists(),
            422,
            'The selected person is not an active member of this organization.'
        );

        return $person->getKey();
    }

    private function ensureOwned(Resource $resource, Organization $organization): void
    {
        abort_unless(hash_equals($resource->organization_id, $organization->getKey()), 404);
    }
}
