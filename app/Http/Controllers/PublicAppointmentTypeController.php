<?php

namespace App\Http\Controllers;

use App\Domain\Appointments\AppointmentTypeSummaryService;
use App\Domain\Configuration\ConfigurationCache;
use App\Domain\Availability\AppointmentTypeSeasonService;
use App\Domain\Bookings\PublicAppointmentAccessService;
use App\Domain\Money\MoneyService;
use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\AppointmentTypeInvitation;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PublicAppointmentTypeController extends Controller
{
    public function index(
        Request $request,
        string $organizationSlug,
        AppointmentTypeSummaryService $summary,
        AppointmentTypeSeasonService $seasons,
        ConfigurationCache $configuration,
    ): View
    {
        $organization = $configuration->organization($organizationSlug);
        $organization->load('galleryPhotos');
        $appointmentTypes = $configuration->publicTypes($organization)
            ->filter(fn (AppointmentType $type): bool => $seasons->isOpenAt($type, CarbonImmutable::now('UTC')))
            ->values();
        $requestedType = $request->query('type');
        $selectedTypeSlug = $appointmentTypes->firstWhere('slug', is_string($requestedType) ? $requestedType : null)?->slug
            ?? $appointmentTypes->first()?->slug;
        $hasCouponOffers = $organization->couponOffers()
            ->where('is_public', true)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', now($organization->timezone)->toDateString()))
            ->exists();

        return view('public.appointment-types.index', [
            'organization' => $organization,
            'appointmentTypes' => $appointmentTypes,
            'selectedTypeSlug' => $selectedTypeSlug,
            'summary' => $summary,
            'hasCouponOffers' => $hasCouponOffers,
            'allowPlanAdvertising' => true,
        ]);
    }

    public function show(
        Request $request,
        string $organizationSlug,
        string $appointmentSlug,
        AppointmentTypeSummaryService $summary,
        MoneyService $money,
        PublicAppointmentAccessService $access,
        ConfigurationCache $configuration,
    ): View {
        $organization = $configuration->organization($organizationSlug);
        $type = $configuration->publicType($organization, $appointmentSlug);

        abort_if(in_array($type->visibility, [AppointmentVisibility::Unlisted, AppointmentVisibility::InviteOnly], true), 404);

        if ($type->visibility === AppointmentVisibility::PasswordProtected && $request->session()->get($access->passwordSessionKey($type)) !== true) {
            return view('public.appointment-types.password', compact('organization', 'type'));
        }

        return $this->detailView($organization, $type, $summary, $money, 'direct', null, null);
    }

    public function unlockPassword(Request $request, string $organizationSlug, string $appointmentSlug, PublicAppointmentAccessService $access): RedirectResponse
    {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
        $type = $organization->appointmentTypes()
            ->where('slug', $appointmentSlug)
            ->where('visibility', AppointmentVisibility::PasswordProtected->value)
            ->where('is_active', true)
            ->firstOrFail();

        $request->validate(['access_password' => ['required', 'string', 'max:200']]);

        if (! $type->access_password || ! Hash::check($request->string('access_password')->toString(), $type->access_password)) {
            return back()->withErrors(['access_password' => 'The appointment password is incorrect.']);
        }

        $request->session()->put($access->passwordSessionKey($type), true);

        return redirect()->route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]);
    }

    public function showUnlisted(
        string $organizationSlug,
        string $token,
        AppointmentTypeSummaryService $summary,
        MoneyService $money,
        ConfigurationCache $configuration,
    ): View {
        $organization = $configuration->organization($organizationSlug);
        $type = $organization->appointmentTypes()
            ->with(['organization', 'resources'])
            ->where('visibility', AppointmentVisibility::Unlisted->value)
            ->where('is_active', true)
            ->where('public_token', $token)
            ->firstOrFail();

        return $this->detailView($organization, $type, $summary, $money, 'unlisted', null, $token);
    }

    public function showInvited(
        string $organizationSlug,
        string $token,
        AppointmentTypeSummaryService $summary,
        MoneyService $money,
        ConfigurationCache $configuration,
    ): View {
        $organization = $configuration->organization($organizationSlug);
        $invitation = AppointmentTypeInvitation::query()
            ->where('organization_id', $organization->getKey())
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_unless($invitation->isUsable(), 404);

        $type = $invitation->appointmentType()
            ->with(['organization', 'resources'])
            ->where('visibility', AppointmentVisibility::InviteOnly->value)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->detailView($organization, $type, $summary, $money, 'invitation', $invitation, $token);
    }

    private function detailView(
        Organization $organization,
        AppointmentType $type,
        AppointmentTypeSummaryService $summary,
        MoneyService $money,
        string $accessMode,
        ?AppointmentTypeInvitation $invitation = null,
        ?string $accessToken = null,
    ): View {
        app(ConfigurationCache::class)->prime($type);
        $exampleMinor = $summary->examplePrice($type);
        $examplePrice = $money->format($exampleMinor, $organization->currency);

        $type->loadMissing(['contractTemplate', 'shortNoticeFeeRules', 'galleryPhotos']);
        $timezoneOptions = timezone_identifiers_list();

        return view('public.appointment-types.show', [
            'organization' => $organization,
            'type' => $type,
            'summary' => $summary,
            'accessMode' => $accessMode,
            'accessToken' => $accessToken,
            'invitation' => $invitation,
            'examplePrice' => $examplePrice,
            'timezoneOptions' => $timezoneOptions,
            'allowPlanAdvertising' => $accessMode === 'direct'
                && $type->visibility === AppointmentVisibility::Public,
        ]);
    }

}
