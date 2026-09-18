<?php

namespace App\Domain\Bookings;

use App\Enums\AppointmentVisibility;
use App\Models\AppointmentType;
use App\Models\AppointmentTypeInvitation;
use Illuminate\Http\Request;

class PublicAppointmentAccessService
{
    public function resolve(
        AppointmentType $type,
        Request $request,
        string $accessMode = 'direct',
        ?string $accessToken = null,
    ): ?AppointmentTypeInvitation {
        abort_unless($type->is_active, 404);

        if ($accessMode === 'direct') {
            if ($type->visibility === AppointmentVisibility::Public) {
                return null;
            }

            if ($type->visibility === AppointmentVisibility::PasswordProtected
                && $request->session()->get($this->passwordSessionKey($type)) === true) {
                return null;
            }

            abort(404);
        }

        if ($accessMode === 'unlisted') {
            abort_unless($type->visibility === AppointmentVisibility::Unlisted, 404);
            abort_unless(is_string($accessToken) && $type->public_token !== null && hash_equals($type->public_token, $accessToken), 404);

            return null;
        }

        if ($accessMode === 'invitation') {
            abort_unless($type->visibility === AppointmentVisibility::InviteOnly, 404);
            abort_unless(is_string($accessToken) && $accessToken !== '', 404);

            $invitation = AppointmentTypeInvitation::query()
                ->where('appointment_type_id', $type->getKey())
                ->where('organization_id', $type->organization_id)
                ->where('token_hash', hash('sha256', $accessToken))
                ->firstOrFail();

            abort_unless($invitation->isUsable(), 404);

            return $invitation;
        }

        abort(404);
    }

    public function passwordSessionKey(AppointmentType $type): string
    {
        $passwordVersion = substr(hash('sha256', (string) $type->access_password), 0, 16);

        return 'appointment_type_password_access.'.$type->uuid.'.'.$passwordVersion;
    }
}
