<?php

namespace App\Http\Middleware;

use App\Support\Organizations\ActiveOrganizationResolver;
use App\Support\Organizations\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOrganization
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly ActiveOrganizationResolver $resolver,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $organization = $this->resolver->resolve($user, $request);

        if (! $organization) {
            return redirect()->route('organizations.create')
                ->with('error', 'Create an organization before using the scheduling backend.');
        }

        $this->context->set($organization);

        return $next($request);
    }
}
