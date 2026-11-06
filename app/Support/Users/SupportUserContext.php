<?php

namespace App\Support\Users;

use App\Models\User;
use Illuminate\Http\Request;

class SupportUserContext
{
    public const SESSION_KEY = 'platform_support_user_uuid';

    public function selected(Request $request): ?User
    {
        $uuid = trim((string) $request->session()->get(self::SESSION_KEY, ''));

        return $uuid === '' ? null : User::query()->whereUuid($uuid)->first();
    }
}
