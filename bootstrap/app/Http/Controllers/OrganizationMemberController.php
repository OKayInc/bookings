<?php

namespace App\Http\Controllers;

use App\Enums\MembershipRole;
use App\Models\OrganizationMemberInvitation;
use App\Models\Person;
use App\Notifications\OrganizationMemberInvitationEmail;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationMemberController extends Controller
{
    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);

        return view('organization-members.index', [
            'organization' => $organization,
            'memberships' => $organization->memberships()
                ->with(['person.user'])
                ->orderBy('created_at')
                ->get(),
            'invitations' => $organization->memberInvitations()
                ->with('invitedBy')
                ->whereNull('accepted_at_utc')
                ->whereNull('revoked_at_utc')
                ->where('expires_at_utc', '>', now('UTC'))
                ->latest()
                ->get(),
            'roles' => [
                MembershipRole::Administrator,
                MembershipRole::Manager,
                MembershipRole::Employee,
            ],
        ]);
    }

    public function store(Request $request, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::enum(MembershipRole::class)->only([
                MembershipRole::Administrator,
                MembershipRole::Manager,
                MembershipRole::Employee,
            ])],
        ]);
        $email = trim((string) $data['email']);
        $normalized = Str::lower($email);

        $alreadyMember = Person::query()
            ->whereHas('memberships', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->where(function ($query) use ($normalized): void {
                $query->where('primary_email', $normalized)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', $normalized));
            })
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the organization.',
            ]);
        }

        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($organization, $request, $data, $email, $normalized, $token): OrganizationMemberInvitation {
            $invitation = OrganizationMemberInvitation::query()
                ->where('organization_id', $organization->getKey())
                ->where('email_normalized', $normalized)
                ->lockForUpdate()
                ->first();

            $values = [
                'organization_id' => $organization->getKey(),
                'invited_by_person_id' => $request->user()->person_id,
                'accepted_by_person_id' => null,
                'email' => $email,
                'email_normalized' => $normalized,
                'role' => $data['role'],
                'token_hash' => hash('sha256', $token, true),
                'expires_at_utc' => now('UTC')->addDays(max(1, (int) config('organizations.member_invitation_ttl_days', 7))),
                'accepted_at_utc' => null,
                'revoked_at_utc' => null,
            ];

            if ($invitation) {
                $invitation->update($values);

                return $invitation->fresh();
            }

            return OrganizationMemberInvitation::create($values);
        }, 3);

        Notification::route('mail', $normalized)
            ->notify(new OrganizationMemberInvitationEmail($invitation, $token));

        return back()->with('success', 'Invitation sent to '.$email.'.');
    }

    public function destroy(OrganizationMemberInvitation $invitation, OrganizationContext $context): RedirectResponse
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        abort_unless(hash_equals((string) $invitation->organization_id, (string) $organization->getKey()), 404);

        if ($invitation->accepted_at_utc === null) {
            $invitation->update([
                'revoked_at_utc' => now('UTC'),
                'token_hash' => hash('sha256', Str::random(64), true),
            ]);
        }

        return back()->with('success', 'Invitation revoked.');
    }
}
