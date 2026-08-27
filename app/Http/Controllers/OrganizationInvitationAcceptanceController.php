<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMemberInvitation;
use App\Models\OrganizationMembership;
use App\Models\Person;
use App\Models\User;
use App\Rules\IanaTimezone;
use App\Support\Organizations\ActiveOrganizationResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationInvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->activeInvitation($token);
        $user = $request->user();

        if ($user !== null && ! hash_equals(Str::lower($user->email), $invitation->email_normalized)) {
            abort(403, 'This invitation was issued to a different email address.');
        }

        $existingAccount = User::query()->where('email', $invitation->email_normalized)->exists();
        if ($user === null && $existingAccount) {
            $request->session()->put('url.intended', route('organization-invitations.show', $token));
        }

        return view('organization-invitations.show', [
            'invitation' => $invitation->loadMissing(['organization', 'invitedBy']),
            'token' => $token,
            'existingAccount' => $existingAccount,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function accept(Request $request, string $token, ActiveOrganizationResolver $resolver): RedirectResponse
    {
        $invitation = $this->activeInvitation($token);
        $authenticatedUser = $request->user();

        if ($authenticatedUser !== null && ! hash_equals(Str::lower($authenticatedUser->email), $invitation->email_normalized)) {
            abort(403, 'This invitation was issued to a different email address.');
        }

        $data = [];
        if ($authenticatedUser === null) {
            if (User::query()->where('email', $invitation->email_normalized)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account already exists for this email address. Log in before accepting the invitation.',
                ]);
            }

            $data = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
                'timezone' => ['required', new IanaTimezone],
            ]);
        }

        [$user, $organization, $created] = DB::transaction(function () use ($token, $authenticatedUser, $data): array {
            $locked = OrganizationMemberInvitation::query()
                ->where('token_hash', hash('sha256', $token, true))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                abort(410, 'This invitation is no longer available.');
            }

            $created = false;
            $user = $authenticatedUser;
            if ($user === null) {
                if (User::query()->where('email', $locked->email_normalized)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'An account was created for this email address. Log in before accepting the invitation.',
                    ]);
                }

                $person = Person::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'primary_email' => $locked->email_normalized,
                    'timezone' => $data['timezone'],
                    'locale' => app()->getLocale(),
                ]);
                $user = User::create([
                    'person_id' => $person->getKey(),
                    'email' => $locked->email_normalized,
                    'password' => $data['password'],
                ]);
                $created = true;
            }

            $membership = OrganizationMembership::query()
                ->where('organization_id', $locked->organization_id)
                ->where('person_id', $user->person_id)
                ->lockForUpdate()
                ->first();

            if ($membership !== null) {
                throw ValidationException::withMessages([
                    'email' => 'This account already belongs to the organization.',
                ]);
            }

            OrganizationMembership::create([
                'organization_id' => $locked->organization_id,
                'person_id' => $user->person_id,
                'role' => $locked->role,
                'status' => MembershipStatus::Active,
            ]);

            $locked->update([
                'accepted_by_person_id' => $user->person_id,
                'accepted_at_utc' => now('UTC'),
                'token_hash' => hash('sha256', Str::random(64), true),
            ]);
            $user->forceFill(['active_organization_id' => $locked->organization_id])->save();

            return [$user, $locked->organization()->firstOrFail(), $created];
        }, 3);

        if ($created) {
            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
        }

        $resolver->select($user, $organization, $request, saveUser: false);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('success', 'Invitation accepted. Verify your email address to access '.$organization->name.'.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'You joined '.$organization->name.'.');
    }

    private function activeInvitation(string $token): OrganizationMemberInvitation
    {
        $invitation = OrganizationMemberInvitation::query()
            ->where('token_hash', hash('sha256', $token, true))
            ->firstOrFail();

        abort_unless($invitation->isPending(), 410, 'This invitation is no longer available.');

        return $invitation;
    }
}
