<?php

namespace App\Domain\Organizations;

use App\Domain\Bookings\BookingPriceVisibilityService;
use App\Domain\Conferences\ConferenceProviderCatalog;
use App\Domain\Resources\ResourceDepositService;
use App\Domain\Resources\ResourceRequirementService;
use App\Enums\ConferenceProvider;
use App\Enums\PaymentProvider;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Models\Resource;
use Illuminate\Support\Collection;

class OrganizationStartupChecklist
{
    public function __construct(
        private readonly ResourceRequirementService $requirements,
        private readonly BookingPriceVisibilityService $prices,
        private readonly ResourceDepositService $deposits,
        private readonly ConferenceProviderCatalog $conferences,
    ) {}

    public function forOrganization(Organization $organization, bool $canAdminister): array
    {
        // Read current configuration on every visit, including after deletion or disabling.
        // Do not store completion flags or depend on how the organization was created.
        $organization->load(['paymentSettings', 'conferenceSettings']);
        $resources = $organization->resources()->get();
        $types = $organization->appointmentTypes()->where('is_active', true)->orderBy('name')
            ->with([
                'resources', 'questions.options', 'questions.resourceRequirementRule.resources',
                'questions.resourceRequirementRule.triggerOption', 'shortNoticeFeeRules',
            ])
            ->withExists(['eventOccurrences as has_future_event' => fn ($query) => $query
                ->where('is_active', true)->where('starts_at_utc', '>', now('UTC'))])
            ->get();
        $types->each(fn (AppointmentType $type) => $type->setRelation('organization', $organization));
        $schedules = $organization->availabilitySchedules()
            ->withExists('rules')
            ->withExists(['exceptions as has_future_opening' => fn ($query) => $query
                ->where('mode', 'available')->where('ends_at_utc', '>', now('UTC'))])
            ->get()->keyBy(fn (AvailabilitySchedule $schedule) => $schedule->scope_type->value.'|'.$schedule->scope_uuid);
        $defaultSchedule = $schedules->get('organization|'.$organization->uuid);
        $missingHours = $types->filter(fn (AppointmentType $type) => ! $this->hasHours(
            $schedules->get('appointment_type|'.$type->uuid) ?? $defaultSchedule,
        ));
        $badResources = $types->filter(fn (AppointmentType $type) => ! $this->resourcesReady($type, $resources, $schedules, $defaultSchedule));
        $missingDates = $types->filter(fn (AppointmentType $type) => $type->ticketing_enabled && ! $type->has_future_event);
        $missingLocations = $types->filter(fn (AppointmentType $type) => $type->ticketing_enabled && ! $type->is_online && blank($type->event_location));
        $missingMeetings = $types->filter(fn (AppointmentType $type) => $type->is_online
            && ! $this->conferences->isConfigured($organization, $type->meeting_provider ?? ConferenceProvider::Jitsi));
        $paymentNeeded = $types->contains(fn (AppointmentType $type) => $this->mayCharge($type));
        $settings = $organization->paymentSettings;
        $liveProviders = collect(PaymentProvider::cases())->filter(fn (PaymentProvider $provider) => $settings?->isConfigured($provider)
            && ! $this->testMode($settings, $provider));
        $testProviders = collect(PaymentProvider::cases())->filter(fn (PaymentProvider $provider) => $settings?->isConfigured($provider)
            && $this->testMode($settings, $provider));
        $typeUrl = route('appointment-types.index');
        $editFirst = fn (Collection $items) => $items->isNotEmpty() ? route('appointment-types.edit', $items->first()) : $typeUrl;
        $adminUrl = fn (string $url) => $canAdminister ? $url : null;

        $steps = [
            $this->step('organization', 'Check your business details',
                filled($organization->name) && filled($organization->slug) && filled($organization->timezone) && filled($organization->currency),
                true, 'Your business name, booking address, main timezone and currency are set. Review them before sharing your page.',
                $adminUrl(route('organizations.edit', $organization)), 'Review details'),
            $this->step('resources', 'Add people, rooms or equipment', $resources->contains('is_active', true), false,
                'Add the people or items customers will book, then assign them to an appointment type. Optional if your appointments do not need resources.',
                route('resources.index'), 'Manage resources'),
            $this->step('availability', 'Set your available hours',
                $types->isEmpty() ? $this->hasHours($defaultSchedule) : $missingHours->isEmpty(), true,
                $missingHours->isNotEmpty() ? 'Missing active hours: '.$this->names($missingHours).'.'
                    : 'Use business hours or custom appointment hours. A future opening exception also counts; empty or disabled schedules do not.',
                $missingHours->isNotEmpty() ? route('availability.appointment-types.edit', $missingHours->first())
                    : route($types->isEmpty() ? 'availability.organization.edit' : 'availability.index'), 'Set hours'),
            $this->step('appointment-types', 'Create and enable an appointment type', $types->isNotEmpty(), true,
                'Set what customers can book, its duration, capacity, price and who can access it. Private and unlisted appointments count too.',
                $types->isEmpty() ? route('appointment-types.create') : $typeUrl, 'Manage appointment types'),
            $this->step('assignments', 'Check assigned resources and their hours', $badResources->isEmpty(), $types->contains(fn ($type) => $type->resources->isNotEmpty()),
                $badResources->isEmpty() ? 'Required resources must be active and have hours and enough stock. Each replacement group needs at least one usable choice. Appointments without resources are supported.'
                    : 'Check required resources, stock and hours for: '.$this->names($badResources).'.',
                $editFirst($badResources), 'Review assignments', ! $types->contains(fn ($type) => $type->resources->isNotEmpty())),
        ];

        if ($types->contains('ticketing_enabled', true)) {
            $steps[] = $this->step('event-dates', 'Set upcoming event dates', $missingDates->isEmpty(), true,
                $missingDates->isEmpty() ? 'Your ticketed events have active future dates.' : 'Add a future date or disable the finished event: '.$this->names($missingDates).'.',
                $editFirst($missingDates), 'Set event dates');
            $steps[] = $this->step('event-locations', 'Add event locations', $missingLocations->isEmpty(), true,
                $missingLocations->isEmpty() ? 'In-person events have a location. Your location disclosure settings still control when guests see it.'
                    : 'Add the venue or address for: '.$this->names($missingLocations).'.',
                $editFirst($missingLocations), 'Review locations');
        }
        if ($types->contains('is_online', true)) {
            $steps[] = $this->step('online-meetings', 'Set up online meeting links', $missingMeetings->isEmpty(), true,
                $missingMeetings->isEmpty() ? 'Your selected meeting providers are configured. Jitsi works without a separate account.'
                    : 'Configure the meeting provider for: '.$this->names($missingMeetings).'.',
                $adminUrl(route('settings.edit')), 'Set up meeting links');
        }

        $steps[] = $this->step('taxes', 'Set your tax preferences', ! $organization->collects_taxes || $organization->taxes()->exists(),
            (bool) $organization->collects_taxes,
            $organization->collects_taxes ? 'Tax collection is enabled. Add at least one tax rate and review whether prices include tax.' : 'You have chosen not to collect taxes. Change this if your business needs to charge tax.',
            $adminUrl(route('organizations.edit', $organization)), 'Review taxes', ! $organization->collects_taxes);
        $steps[] = $this->step('payments', 'Connect Stripe or PayPal', $liveProviders->isNotEmpty(), $paymentNeeded,
            $paymentNeeded
                ? ($liveProviders->isNotEmpty() ? 'At least one live payment provider is configured. Check your provider account and try checkout before accepting payments.'
                    : 'Your appointments can charge money, including extras or deposits. Enable Stripe or PayPal with its credentials and webhook settings in live mode.')
                : 'Not needed for your current free appointments. Set up either provider when you add prices, paid extras or deposits.',
            $adminUrl(route('payment-settings.edit')), 'Set up payments', ! $paymentNeeded && $liveProviders->isEmpty());
        if ($testProviders->isNotEmpty()) {
            $steps[array_key_last($steps)]['description'] .= ' Test mode is enabled for '.$testProviders->map(fn ($provider) => $provider->value)->implode(', ').'; it does not collect real payments.';
        }

        $extras = [
            $this->step('branding', 'Add your logo', filled($organization->logo_path), false, 'Help customers recognize your business.', $adminUrl(route('organizations.edit', $organization)), 'Edit branding'),
            $this->step('questions', 'Add booking questions', $types->contains(fn ($type) => $type->questions->contains('is_active', true)), false,
                'Collect any extra information you need from customers.', $types->isNotEmpty() ? route('appointment-types.questionnaire.index', $types->first()) : $typeUrl, 'Edit questions'),
            $this->step('policies', 'Add cancellation and rescheduling policies', $types->isNotEmpty() && $types->every(fn ($type) => filled($type->cancellation_policy_text) && filled($type->rescheduling_policy_text)), false,
                'Explain your rules, notice periods and refunds in the appointment editor.', $editFirst($types->filter(fn ($type) => blank($type->cancellation_policy_text) || blank($type->rescheduling_policy_text))), 'Review policies'),
            $this->step('calendars', 'Connect an external calendar', CalendarConnection::query()->where('organization_id', $organization->getKey())->where('status', 'active')->exists(), false,
                'Connect Google or Outlook, then choose which calendars each appointment type should use.', route('calendar-connections.index'), 'Manage calendars'),
            $this->step('team', 'Invite your team', $organization->memberships()->where('status', 'active')->count() > 1, false,
                'Give colleagues access if you work with a team.', $adminUrl(route('organization-members.index')), 'Manage team'),
        ];
        $required = collect($steps)->where('required', true);

        return [
            'steps' => $steps,
            'extras' => $extras,
            'completed' => $required->where('complete', true)->count(),
            'total' => $required->count(),
            'ready' => $required->every('complete'),
            'next' => $required->firstWhere('complete', false),
        ];
    }

    private function hasHours(?AvailabilitySchedule $schedule): bool
    {
        return $schedule !== null && $schedule->is_active && ($schedule->rules_exists || $schedule->has_future_opening);
    }

    private function testMode(OrganizationPaymentSetting $settings, PaymentProvider $provider): bool
    {
        if ($provider === PaymentProvider::PayPal) {
            return $settings->paypal_sandbox;
        }

        return $settings->stripe_test_mode
            || str_starts_with((string) $settings->stripe_secret_key, 'sk_test_')
            || str_starts_with((string) $settings->stripe_secret_key, 'rk_test_');
    }

    private function resourcesReady(AppointmentType $type, Collection $resources, Collection $schedules, ?AvailabilitySchedule $default): bool
    {
        $usable = fn (Resource $resource) => $resource->is_active
            && $resources->contains(fn (Resource $available) => $available->is($resource))
            && (! $resource->usesQuantityInventory() || $resource->inventory_quantity >= max(1, (int) $resource->pivot->quantity_required))
            && $this->hasHours($schedules->get('resource|'.$resource->uuid) ?? $default);

        return $this->requirements->requiredResources($type)->every($usable)
            && $this->requirements->replacementGroups($type)->every(fn (Collection $group) => $group->contains($usable));
    }

    private function mayCharge(AppointmentType $type): bool
    {
        if ($this->prices->shouldShow($type, 0) || $this->deposits->total($type) > 0
            || $type->shortNoticeFeeRules->contains(fn ($rule) => $rule->is_active && ($rule->fixed_amount_minor > 0 || $rule->percentage_bps > 0))
            || $type->resources->contains(fn ($resource) => $resource->type === 'equipment' && ($resource->pivot->equipment_pricing_mode ?? 'free') !== 'free')
            || ($type->ticketing_enabled && collect($type->ticket_seat_blocks ?? [])->contains(fn ($block) => (int) ($block['seat_fee_minor'] ?? 0) > 0))) {
            return true;
        }

        // A free base price can still collect a deposit when an answer adds a rental.
        foreach ($type->questions->where('is_active', true) as $question) {
            $rule = $question->resourceRequirementRule;
            if ($rule?->triggerOption && $this->deposits->total($type, [$question->uuid => $rule->triggerOption->uuid]) > 0) {
                return true;
            }
        }

        return false;
    }

    private function names(Collection $items): string
    {
        return $items->take(3)->pluck('name')->implode(', ').($items->count() > 3 ? ' and '.($items->count() - 3).' more' : '');
    }

    private function step(string $id, string $title, bool $complete, bool $required, string $description, ?string $url, string $action, bool $notNeeded = false): array
    {
        return compact('id', 'title', 'complete', 'required', 'description', 'url', 'action', 'notNeeded');
    }
}
