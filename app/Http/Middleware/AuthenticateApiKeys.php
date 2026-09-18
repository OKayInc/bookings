<?php

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationPlanTier;
use App\Models\Organization;
use App\Models\User;
use App\Support\Organizations\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKeys
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->header('X-CLIENT-API-KEY', '');
        $tenant = $request->header('X-ORGANIZATION-API-KEY', '');
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $client) && preg_match('/^[a-f0-9]{64}$/D', $tenant), 401, 'Invalid API credentials.');
        $user = User::where('api_key_hash', hash('sha256', $client))->first();
        $organization = Organization::where('api_key_hash', hash('sha256', $tenant))->first();
        abort_unless($user && $organization, 401, 'Invalid API credentials.');
        abort_unless($user->hasVerifiedEmail(), 403, 'Verify your email before using the API.');
        $membership = $organization->memberships()->where('person_id', $user->person_id)
            ->where('status', MembershipStatus::Active->value)->first();
        abort_unless($membership, 403, 'Active organization membership is required.');
        abort_unless($organization->plan_tier === OrganizationPlanTier::Paid, 403, 'API access requires a paid organization plan.');
        // Never derive API tenancy from the browser session or active organization.
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_membership', $membership);
        app(OrganizationContext::class)->set($organization);
        return $next($request)->header('Cache-Control', 'no-store, private');
    }
}
