<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

class VerifyBackendEmailRequest extends EmailVerificationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! hash_equals((string) $user->uuid, (string) $this->route('id'))) {
            return false;
        }

        return hash_equals(
            sha1($user->getEmailForVerification()),
            (string) $this->route('hash')
        );
    }
}
