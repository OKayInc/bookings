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
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            return URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->uuid,
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });

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
