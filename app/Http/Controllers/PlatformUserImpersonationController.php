<?php

namespace App\Http\Controllers;

use App\Domain\Plans\PlatformOwnerService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformUserImpersonationController extends Controller
{
    public const OWNER_SESSION_KEY = 'platform_impersonation_owner_uuid';

    public function store(Request $request, PlatformOwnerService $platformOwner): RedirectResponse
    {
        $owner = $this->authorizedOwner($request, $platformOwner);
        abort_unless($owner, 403);

        $data = $request->validate([
            'user_uuid' => ['required', 'uuid'],
        ]);

        $target = User::query()->whereUuid($data['user_uuid'])->firstOrFail();

        if ($target->is($owner)) {
            return $this->restoreOwner($request, $owner);
        }

        $request->session()->put(self::OWNER_SESSION_KEY, $owner->uuid);
        $request->session()->forget('active_organization_uuid');
        Auth::login($target);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'You are now signed in as '.$this->label($target).'.');
    }

    public function destroy(Request $request, PlatformOwnerService $platformOwner): RedirectResponse
    {
        $owner = $this->authorizedOwner($request, $platformOwner);
        abort_unless($owner, 403);

        return $this->restoreOwner($request, $owner);
    }

    private function authorizedOwner(Request $request, PlatformOwnerService $platformOwner): ?User
    {
        $current = $request->user();

        if ($platformOwner->isOwner($current)) {
            return $current;
        }

        $uuid = trim((string) $request->session()->get(self::OWNER_SESSION_KEY, ''));
        if ($uuid === '') {
            return null;
        }

        $owner = User::query()->whereUuid($uuid)->first();

        return $platformOwner->isOwner($owner) ? $owner : null;
    }

    private function restoreOwner(Request $request, User $owner): RedirectResponse
    {
        Auth::login($owner);
        $request->session()->forget(self::OWNER_SESSION_KEY);
        $request->session()->forget('active_organization_uuid');
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Returned to the platform owner account.');
    }

    private function label(User $user): string
    {
        $user->loadMissing('person');

        return $user->person?->full_name ?: $user->email;
    }
}
