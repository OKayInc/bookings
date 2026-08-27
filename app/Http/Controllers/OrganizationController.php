<?php

namespace App\Http\Controllers;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Domain\Organizations\OrganizationLogoService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $organizations = $request->user()->person->organizations()
            ->withPivot(['role', 'status'])
            ->orderBy('name')
            ->get();

        return view('organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        return view('organizations.create', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => PaymentCurrencyCatalog::options(),
        ]);
    }

    public function store(StoreOrganizationRequest $request, OrganizationLogoService $logos): RedirectResponse
    {
        $data = $request->validated();

        $organization = DB::transaction(function () use ($request, $data): Organization {
            $baseSlug = Str::slug($data['name']) ?: 'organization';
            $slug = $baseSlug;
            $counter = 2;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $slug,
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
            ]);

            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $request->user()->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);

            return $organization;
        });

        if ($request->hasFile('logo_file')) {
            $logos->replace($organization, $request->file('logo_file'));
        }

        $request->session()->put('active_organization_uuid', $organization->uuid);

        return redirect()->route('dashboard')->with('success', 'Organization created.');
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('organizations.edit', [
            'organization' => $organization,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => PaymentCurrencyCatalog::options(),
        ]);
    }

    public function update(StoreOrganizationRequest $request, Organization $organization, OrganizationLogoService $logos): RedirectResponse
    {
        $this->authorize('update', $organization);
        $data = $request->validated();

        $organization->update([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'currency' => strtoupper($data['currency']),
        ]);

        if ($request->hasFile('logo_file')) {
            $logos->replace($organization, $request->file('logo_file'));
        } elseif ($request->boolean('remove_logo')) {
            $logos->remove($organization);
        }

        return redirect()->route('organizations.index')->with('success', 'Organization updated.');
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        $allowed = $organization->memberships()
            ->where('person_id', $request->user()->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        abort_unless($allowed, 403);

        $request->session()->put('active_organization_uuid', $organization->uuid);

        return redirect()->route('dashboard')->with('success', 'Active organization changed.');
    }
}
