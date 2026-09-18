<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentVisibility;
use App\Http\Requests\StoreAppointmentTypeInvitationRequest;
use App\Models\AppointmentType;
use App\Models\AppointmentTypeInvitation;
use App\Support\Organizations\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class AppointmentTypeInvitationController extends Controller
{
    public function store(
        StoreAppointmentTypeInvitationRequest $request,
        AppointmentType $appointmentType,
        OrganizationContext $context,
    ): RedirectResponse {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);

        abort_unless($appointmentType->visibility === AppointmentVisibility::InviteOnly, 422, 'Invitations can only be created for invite-only appointment types.');

        $token = Str::random(64);
        $data = $request->validated();

        $appointmentType->invitations()->create([
            'organization_id' => $appointmentType->organization_id,
            'created_by_person_id' => $request->user()->person_id,
            'token_hash' => hash('sha256', $token),
            'recipient_email' => isset($data['recipient_email']) ? strtolower($data['recipient_email']) : null,
            'expires_at' => empty($data['expires_at'])
                ? null
                : CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $data['expires_at'], $context->organization()->timezone)->utc(),
            'max_uses' => $data['max_uses'] ?? null,
            'uses_count' => 0,
            'is_active' => true,
        ]);

        $url = route('public.appointment-types.invited', [
            'organizationSlug' => $context->organization()->slug,
            'token' => $token,
        ]);

        return back()
            ->with('success', 'Invitation created. Copy the link now; the raw token is not stored and cannot be recovered later.')
            ->with('invitation_url', $url);
    }

    public function destroy(
        AppointmentType $appointmentType,
        AppointmentTypeInvitation $invitation,
        OrganizationContext $context,
    ): RedirectResponse {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);
        abort_unless(hash_equals($invitation->appointment_type_id, $appointmentType->getKey()), 404);

        $invitation->update(['is_active' => false]);

        return back()->with('success', 'Invitation revoked.');
    }

    private function ensureSameOrganization(AppointmentType $appointmentType, OrganizationContext $context): void
    {
        abort_unless(hash_equals($appointmentType->organization_id, $context->organization()->getKey()), 404);
    }
}
