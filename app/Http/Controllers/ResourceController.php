<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Requests\StoreResourceRequest;
use App\Models\Person;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        return view('resources.index', [
            'resources' => $context->organization()->resources()->with('person')->orderBy('name')->get(),
        ]);
    }

    public function create(OrganizationContext $context): View
    {
        $this->authorize('manageScheduling', $context->organization());

        return view('resources.create', [
            'members' => $context->organization()->people()
                ->wherePivot('status', MembershipStatus::Active->value)
                ->orderBy('first_name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(StoreResourceRequest $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('manageScheduling', $organization);
        $data = $request->validated();
        $personKey = null;

        if (! empty($data['person_uuid'])) {
            $person = Person::whereUuid($data['person_uuid'])->firstOrFail();
            abort_unless(
                $organization->memberships()->where('person_id', $person->getKey())->where('status', MembershipStatus::Active->value)->exists(),
                422,
                'The selected person is not an active member of this organization.'
            );
            $personKey = $person->getKey();
        }

        $organization->resources()->create([
            'person_id' => $personKey,
            'name' => $data['name'],
            'type' => $data['type'],
            'timezone' => $data['timezone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_required_by_default' => ($data['default_requirement'] ?? 'required') === 'required',
        ]);

        return redirect()->route('resources.index')->with('success', 'Resource created.');
    }

    public function edit(Resource $resource, OrganizationContext $context): View
    {
        $this->ensureSameOrganization($resource, $context);
        $this->authorize('manage', $resource);

        return view('resources.edit', [
            'resource' => $resource,
            'members' => $context->organization()->people()
                ->wherePivot('status', MembershipStatus::Active->value)
                ->orderBy('first_name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(StoreResourceRequest $request, Resource $resource, OrganizationContext $context): RedirectResponse
    {
        $this->ensureSameOrganization($resource, $context);
        $this->authorize('manage', $resource);
        $data = $request->validated();
        $personKey = null;

        if (! empty($data['person_uuid'])) {
            $person = Person::whereUuid($data['person_uuid'])->firstOrFail();
            abort_unless($context->organization()->memberships()->where('person_id', $person->getKey())->exists(), 422);
            $personKey = $person->getKey();
        }

        $resource->update([
            'person_id' => $personKey,
            'name' => $data['name'],
            'type' => $data['type'],
            'timezone' => $data['timezone'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_required_by_default' => ($data['default_requirement'] ?? 'required') === 'required',
        ]);

        $resource->appointmentTypes()->newPivotQuery()
            ->where('resource_id', $resource->getKey())
            ->where('requirement_mode', 'inherit')
            ->update([
                'is_required' => $resource->is_required_by_default,
                'updated_at' => now(),
            ]);

        return redirect()->route('resources.index')->with('success', 'Resource updated.');
    }

    private function ensureSameOrganization(Resource $resource, OrganizationContext $context): void
    {
        abort_unless(hash_equals($resource->organization_id, $context->organization()->getKey()), 404);
    }
}
