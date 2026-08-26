<?php

namespace App\Providers;

use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use App\Enums\MembershipStatus;
use App\Policies\AppointmentTypePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ResourcePolicy;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrganizationContext::class, fn () => new OrganizationContext());
    }

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Resource::class, ResourcePolicy::class);
        Gate::policy(AppointmentType::class, AppointmentTypePolicy::class);

        View::composer('layouts.app', function ($view): void {
            $organization = app(OrganizationContext::class)->get();
            $request = request();
            $user = $request->user();

            if (! $organization && $user) {
                $memberships = $user->person
                    ->memberships()
                    ->with('organization')
                    ->where('status', MembershipStatus::Active->value)
                    ->get();

                $requestedUuid = $request->session()->get('active_organization_uuid');

                if ($requestedUuid) {
                    $membership = $memberships->first(
                        fn ($item) => $item->organization && hash_equals($item->organization->uuid, (string) $requestedUuid)
                    );
                    $organization = $membership?->organization;
                }

                $organization ??= $memberships->first()?->organization;
            }

            $view->with('activeOrganization', $organization);
        });
    }
}
