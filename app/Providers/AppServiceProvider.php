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
        $this->app->scoped(OrganizationContext::class, fn () => new OrganizationContext());
    }

    public function boot(): void
    {
        foreach ([\App\Models\Booking::class, \App\Models\PaymentTransaction::class, \App\Models\PaymentRefund::class, \App\Models\Ticket::class] as $model) {
            $model::observe(\App\Observers\OutgoingWebhookObserver::class);
        }

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
        Gate::define('manage-platform', fn (\App\Models\User $user): bool => app(\App\Domain\Plans\PlatformOwnerService::class)->isOwner($user));

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
                'activePlanEntitlement' => $organization
                    ? app(\App\Domain\Plans\PlanEntitlementService::class)->for($organization)
                    : null,
            ]);
        });
    }
}
