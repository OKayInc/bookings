<?php

namespace App\Http\Controllers;

use App\Domain\Appointments\AppointmentTypeSummaryService;
use App\Domain\Bookings\PublicAppointmentAccessService;
use App\Domain\Money\MoneyService;
use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\AppointmentTypeInvitation;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PublicAppointmentTypeController extends Controller
{
    public function index(string $organizationSlug, AppointmentTypeSummaryService $summary): View
    {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
        $appointmentTypes = $organization->appointmentTypes()
            ->with('organization')
            ->where('is_active', true)
            ->where('visibility', AppointmentVisibility::Public->value)
            ->orderBy('name')
            ->get();

        return view('public.appointment-types.index', compact('organization', 'appointmentTypes', 'summary'));
    }

    public function show(
        Request $request,
        string $organizationSlug,
        string $appointmentSlug,
        AppointmentTypeSummaryService $summary,
        MoneyService $money,
        PublicAppointmentAccessService $access,
    ): View {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
        $type = $organization->appointmentTypes()
            ->with(['organization', 'resources'])
            ->where('slug', $appointmentSlug)
            ->where('is_active', true)
            ->firstOrFail();

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
    ): View {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
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
    ): View {
        $organization = Organization::where('slug', $organizationSlug)->firstOrFail();
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
        $exampleMinor = $summary->examplePrice($type);
        $examplePrice = $money->format($exampleMinor, $organization->currency);

        $type->loadMissing(['contractTemplate', 'shortNoticeFeeRules']);
        $timezoneOptions = timezone_identifiers_list();

        return view('public.appointment-types.show', compact(
            'organization',
            'type',
            'summary',
            'accessMode',
            'accessToken',
            'invitation',
            'examplePrice',
            'timezoneOptions',
        ));
    }

}
