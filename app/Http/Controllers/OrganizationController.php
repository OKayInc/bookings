<?php

namespace App\Http\Controllers;

use App\Domain\Galleries\GalleryLimitService;
use App\Domain\Money\PaymentCurrencyCatalog;
use App\Domain\Money\MoneyService;
use App\Domain\Organizations\OrganizationDeletionService;
use App\Domain\Organizations\OrganizationLogoService;
use App\Domain\Plans\PlanLimitService;
use App\Domain\Taxes\TaxRate;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Http\Requests\DeleteOrganizationRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Resource;
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
        MoneyService $money,
    ): RedirectResponse
    {
        $data = $request->validated();

        [$organization, $starterAppointmentType] = DB::transaction(function () use ($request, $data, $planLimits, $money): array {
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
                $starterAppointmentType = $this->createGuidedStartingPoint(
                    $organization,
                    $person,
                    $data,
                    $request,
                    $money,
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


    /**
     * Build a normal Appointment.To starting configuration from plain-language
     * onboarding answers. Nothing created here is special-cased: the owner can
     * edit every generated value later in the standard editors.
     */
    private function createGuidedStartingPoint(
        Organization $organization,
        \App\Models\Person $person,
        array $data,
        StoreOrganizationRequest $request,
        MoneyService $money,
    ): AppointmentType {
        $pricingMode = (string) ($data['guided_pricing_mode'] ?? 'free');
        $isOnline = ($data['guided_location_mode'] ?? 'in_person') === 'online';
        $attendanceMode = (string) ($data['guided_attendance_mode'] ?? 'single');
        $name = trim((string) $data['guided_appointment_name']);

        $appointmentType = $organization->appointmentTypes()->create([
            'name' => $name,
            'slug' => Str::slug($name) ?: 'appointment',
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
                ? $money->parse((string) $data['guided_fixed_price'], $organization->currency)
                : null,
            'requires_resource_confirmation' => false,
            'show_resources_to_clients' => true,
            'email_verification_mode' => 'before_confirmation',
            'is_active' => true,
        ]);

        if ($request->boolean('guided_use_owner_resource')) {
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
