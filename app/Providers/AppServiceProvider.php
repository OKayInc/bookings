<?php

namespace App\Providers;

use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use App\Enums\MembershipStatus;
use App\Policies\AppointmentTypePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ResourcePolicy;
use App\Support\Organizations\ActiveOrganizationResolver;
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
            $availableOrganizations = collect();

            if (! $organization && $user) {
                $organization = app(ActiveOrganizationResolver::class)->resolve($user, $request);
            }

            if ($user) {
                $availableOrganizations = $user->person->organizations()
                    ->wherePivot('status', MembershipStatus::Active->value)
                    ->orderBy('name')
                    ->get();
            }

            $view->with([
                'activeOrganization' => $organization,
                'availableOrganizations' => $availableOrganizations,
            ]);
        });
    }
}
