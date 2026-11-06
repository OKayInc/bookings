<?php

namespace App\Providers;

use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use App\Policies\AppointmentTypePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ResourcePolicy;
use App\Support\Organizations\ActiveOrganizationResolver;
use App\Support\Organizations\ActiveMemberships;
use App\Support\Organizations\OrganizationContext;
use App\Support\PageReadCache;
use App\Support\RequestTimings;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class, fn () => new OrganizationContext());
        $this->app->scoped(\App\Domain\Availability\ResourceAvailabilityCache::class);
        $this->app->bind(\App\Domain\Availability\AvailabilityService::class, \App\Domain\Availability\CachedAvailabilityService::class);
        $this->app->singleton(\App\Observers\ResourceAvailabilityObserver::class);
    }

    public function boot(): void
    {
        // Include bulk/query-builder writes, which do not fire model observers.
        DB::listen(function (QueryExecuted $query): void {
            $timings = app()->bound('request') ? request()->attributes->get(RequestTimings::ATTRIBUTE) : null;
            if ($timings instanceof RequestTimings) {
                $timings->recordQuery((float) $query->time);
            }
            if (! preg_match('/^\s*(select|show|describe|explain)\b/i', $query->sql)) {
                app(PageReadCache::class)->clear();
            }
        });
        Event::listen([TransactionBeginning::class, TransactionCommitted::class, TransactionRolledBack::class],
            fn () => app(PageReadCache::class)->clear());

        foreach ([
            \App\Models\Appointment::class, \App\Models\Booking::class,
            \App\Models\BookingAnswer::class, \App\Models\BookingAnswerFile::class,
            \App\Models\AppointmentType::class, \App\Models\Organization::class,
            \App\Models\CalendarConnection::class, \App\Models\ExternalCalendar::class,
            \App\Models\OrganizationPlanSubscription::class, \App\Models\OrganizationPlanGrant::class,
        ] as $model) {
            $model::observe(\App\Observers\AppointmentIntegrationObserver::class);
        }

        foreach ([
            \App\Models\Organization::class,
            \App\Models\AppointmentType::class,
            \App\Models\Resource::class,
            \App\Models\OrganizationTax::class,
            \App\Models\OrganizationHoliday::class,
            \App\Models\GalleryPhoto::class,
            \App\Models\CouponOffer::class,
            \App\Models\AppointmentContractTemplate::class,
            \App\Models\ReusableQuestion::class,
            \App\Models\ReusableQuestionOption::class,
            \App\Models\AppointmentQuestion::class,
            \App\Models\QuestionOption::class,
            \App\Models\AppointmentQuestionVisibilityCondition::class,
            \App\Models\AppointmentQuestionNumericConstraint::class,
            \App\Models\AppointmentQuestionResourceRule::class,
            \App\Models\ShortNoticeFeeRule::class,
        ] as $model) {
            $model::observe(\App\Observers\ConfigurationObserver::class);
        }

        foreach ([\App\Models\Booking::class, \App\Models\PaymentTransaction::class, \App\Models\PaymentRefund::class, \App\Models\Ticket::class] as $model) {
            $model::observe(\App\Observers\OutgoingWebhookObserver::class);
        }

        foreach ([
            \App\Models\Organization::class,
            \App\Models\AppointmentType::class,
            \App\Models\Resource::class,
            \App\Models\Appointment::class,
            \App\Models\Booking::class,
            \App\Models\BookingHold::class,
            \App\Models\AvailabilitySchedule::class,
            \App\Models\AvailabilityRule::class,
            \App\Models\AvailabilityException::class,
            \App\Models\CalendarConnection::class,
            \App\Models\ExternalCalendar::class,
            \App\Models\AppointmentExternalEvent::class,
        ] as $model) {
            $model::observe(\App\Observers\ResourceAvailabilityObserver::class);
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
                $availableOrganizations = app(ActiveMemberships::class)->forUser($user)
                    ->pluck('organization')->filter()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
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
