<?php

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use App\Support\Organizations\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOrganization
{
    public function __construct(private readonly OrganizationContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $memberships = $user->person
            ->memberships()
            ->with('organization')
            ->where('status', MembershipStatus::Active->value)
            ->get();

        if ($memberships->isEmpty()) {
            return redirect()->route('organizations.create')
                ->with('error', 'Create an organization before using the scheduling backend.');
        }

        $requestedUuid = $request->session()->get('active_organization_uuid');
        $membership = $requestedUuid
            ? $memberships->first(fn ($item) => $item->organization->uuid === $requestedUuid)
            : null;

        $membership ??= $memberships->first();

        $this->context->set($membership->organization);
        $request->session()->put('active_organization_uuid', $membership->organization->uuid);

        return $next($request);
    }
}
