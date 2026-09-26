<?php

namespace App\Http\Controllers;

use App\Domain\Galleries\GalleryLimitService;
use App\Domain\Money\PaymentCurrencyCatalog;
use App\Domain\Organizations\GuidedOrganizationSetup;
use App\Domain\Organizations\OrganizationDeletionService;
use App\Domain\Organizations\OrganizationLogoService;
use App\Domain\Plans\PlanLimitService;
use App\Domain\Taxes\TaxRate;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Requests\DeleteOrganizationRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\Organizations\ActiveOrganizationResolver;
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

    public function store(
        StoreOrganizationRequest $request,
        OrganizationLogoService $logos,
        ActiveOrganizationResolver $resolver,
        PlanLimitService $planLimits,
        GuidedOrganizationSetup $guidedSetup,
    ): RedirectResponse
    {
        $data = $request->validated();

        [$organization, $starterAppointmentType] = DB::transaction(function () use ($request, $data, $planLimits, $guidedSetup): array {
            // Lock the account's person row so concurrent create requests cannot
            // both observe capacity and exceed the owned-organization allowance.
            $person = \App\Models\Person::query()
                ->whereKey($request->user()->person_id)
                ->lockForUpdate()
                ->firstOrFail();
            $planLimits->assertCanCreateFreeOrganization($request->user());

            $baseSlug = Str::slug($data['name']) ?: 'organization';
            $slug = $baseSlug;
            $counter = 2;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $collectsTaxes = (bool) ($data['collects_taxes'] ?? false);
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $slug,
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
                'onboarding_completed_at' => now(),
                'google_analytics_measurement_id' => $data['google_analytics_measurement_id'] ?? null,
                'facebook_url' => $data['facebook_url'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null,
                'x_url' => $data['x_url'] ?? null,
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'tiktok_url' => $data['tiktok_url'] ?? null,
                'youtube_url' => $data['youtube_url'] ?? null,
                'collects_taxes' => $collectsTaxes,
                'tax_identifier' => $collectsTaxes ? ($data['tax_identifier'] ?? null) : null,
                'tax_price_mode' => $collectsTaxes ? ($data['tax_price_mode'] ?? null) : null,
            ]);

            $this->replaceTaxes($organization, $collectsTaxes ? ($data['taxes'] ?? []) : []);

            OrganizationMembership::create([
                'organization_id' => $organization->getKey(),
                'person_id' => $request->user()->person_id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);

            $starterAppointmentType = null;
            if ($request->boolean('guided_setup')) {
                $starterAppointmentType = $guidedSetup->create(
                    $organization,
                    $person,
                    [
                        ...$data,
                        'guided_use_owner_resource' => $request->boolean('guided_use_owner_resource'),
                    ],
                );
            }

            return [$organization, $starterAppointmentType];
        });

        if ($request->hasFile('logo_file')) {
            $logos->replace($organization, $request->file('logo_file'));
        }

        $resolver->select($request->user(), $organization, $request);

        if ($starterAppointmentType instanceof AppointmentType) {
            return redirect()
                ->route('appointment-types.edit', $starterAppointmentType)
                ->with('success', 'Your organization and starter appointment are ready. Review any section you want to fine-tune.');
        }

        return redirect()->route('dashboard')->with('success', 'Organization created.');
    }

    public function edit(Organization $organization, GalleryLimitService $galleryLimits): View
    {
        $this->authorize('update', $organization);
        $organization->load(['galleryPhotos', 'taxes']);

        return view('organizations.edit', [
            'organization' => $organization,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => PaymentCurrencyCatalog::options(),
            'galleryLimit' => $galleryLimits->forOrganization($organization),
        ]);
    }

    public function update(StoreOrganizationRequest $request, Organization $organization, OrganizationLogoService $logos): RedirectResponse
    {
        $this->authorize('update', $organization);
        $data = $request->validated();

        DB::transaction(function () use ($data, $organization): void {
            $collectsTaxes = (bool) ($data['collects_taxes'] ?? false);
            $organization->update([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
                'facebook_url' => $data['facebook_url'],
                'google_analytics_measurement_id' => array_key_exists('google_analytics_measurement_id', $data)
                    ? $data['google_analytics_measurement_id']
                    : $organization->google_analytics_measurement_id,
                'instagram_url' => $data['instagram_url'],
                'x_url' => $data['x_url'],
                'linkedin_url' => $data['linkedin_url'],
                'tiktok_url' => $data['tiktok_url'],
                'youtube_url' => $data['youtube_url'],
                'collects_taxes' => $collectsTaxes,
                'tax_identifier' => $collectsTaxes ? $data['tax_identifier'] : null,
                'tax_price_mode' => $collectsTaxes ? $data['tax_price_mode'] : null,
            ]);

            $organization->taxes()->delete();
            $this->replaceTaxes($organization, $collectsTaxes ? ($data['taxes'] ?? []) : []);
        });

        if ($request->hasFile('logo_file')) {
            $logos->replace($organization, $request->file('logo_file'));
        } elseif ($request->boolean('remove_logo')) {
            $logos->remove($organization);
        }

        return redirect()->route('organizations.index')->with('success', 'Organization updated.');
    }

    public function destroy(
        DeleteOrganizationRequest $request,
        Organization $organization,
        OrganizationDeletionService $deletion,
        ActiveOrganizationResolver $resolver,
    ): RedirectResponse {
        $this->authorize('delete', $organization);
        $name = $organization->name;

        $deletion->delete($organization);

        $user = $request->user()->refresh();
        $request->session()->forget('active_organization_uuid');
        $nextOrganization = $resolver->resolve($user, $request);

        $redirect = $nextOrganization
            ? redirect()->route('organizations.index')
            : redirect()->route('organizations.create');

        return $redirect->with('success', $name.' and all of its organization data were permanently deleted.');
    }

    public function switch(Request $request, Organization $organization, ActiveOrganizationResolver $resolver): RedirectResponse
    {
        $allowed = $organization->memberships()
            ->where('person_id', $request->user()->person_id)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        abort_unless($allowed, 403);

        $resolver->select($request->user(), $organization, $request);

        return redirect()->route('dashboard')->with('success', 'Active organization changed.');
    }


    /** @param list<array{name:string,percentage:string|int|float}> $taxes */
    private function replaceTaxes(Organization $organization, array $taxes): void
    {
        foreach (array_values($taxes) as $index => $tax) {
            $organization->taxes()->create([
                'name' => $tax['name'],
                'rate_millionths' => TaxRate::fromPercentage($tax['percentage']),
                'position' => $index + 1,
            ]);
        }
    }
}
