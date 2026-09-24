<?php

namespace App\Domain\Customers;

use App\Models\Booking;
use App\Models\BookingOutcome;
use App\Models\CustomerAccessEntry;
use App\Models\CustomerAccessEvent;
use App\Models\CustomerReputationSetting;
use App\Models\OrganizationContact;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerReputationService
{
    public function recordOutcome(Booking $booking, string $outcome, ?Person $actor = null, string $source = 'dashboard'): BookingOutcome
    {
        abort_unless(in_array($outcome, ['successful', 'no_show'], true), 422, 'Invalid appointment outcome.');
        abort_if(in_array($booking->status->value, ['cancelled', 'declined'], true), 422, 'Cancelled or declined bookings cannot have an attendance outcome.');

        return DB::transaction(function () use ($booking, $outcome, $actor, $source): BookingOutcome {
            $record = BookingOutcome::query()->updateOrCreate(
                ['booking_id' => $booking->getKey()],
                [
                    'organization_id' => $booking->organization_id,
                    'outcome' => $outcome,
                    'source' => $source,
                    'recorded_by_person_id' => $actor?->getKey(),
                    'recorded_at_utc' => now('UTC'),
                ],
            );

            $this->evaluate($booking->contact()->firstOrFail());

            return $record;
        });
    }

    public function evaluate(OrganizationContact $contact): void
    {
        $contact->loadMissing('organization');
        $settings = $contact->organization->customerReputationSetting
            ?? CustomerReputationSetting::defaultsFor($contact->organization);

        $reviewed = $contact->bookings()
            ->whereHas('outcome')
            ->with(['outcome', 'appointment'])
            ->get();

        if ($reviewed->count() < (int) $settings->minimum_reviewed_appointments) {
            return;
        }

        $now = Carbon::now('UTC');
        $blacklistBookings = $reviewed->filter(function (Booking $booking) use ($settings, $now): bool {
            if (! $settings->blacklist_window_days) {
                return true;
            }
            return $booking->appointment?->ends_at_utc?->gte($now->copy()->subDays((int) $settings->blacklist_window_days)) ?? false;
        });
        $noShows = $blacklistBookings->where('outcome.outcome', 'no_show')->count();

        if ($settings->blacklist_mode !== 'disabled' && $noShows >= (int) $settings->blacklist_no_show_threshold) {
            $this->resolvePolicy($contact, 'whitelist_good_history', 'Superseded by the blacklist policy.');
            $this->applyPolicy(
                $contact,
                'blacklist',
                'blacklist_no_shows',
                $settings->blacklist_mode,
                "{$noShows} no-shows within the configured review window.",
                ['no_shows' => $noShows, 'window_days' => $settings->blacklist_window_days],
                $settings,
            );
            return;
        }

        $this->resolvePolicy($contact, 'blacklist_no_shows', 'The customer no longer meets the blacklist policy.');

        $whiteBookings = $reviewed->filter(function (Booking $booking) use ($settings, $now): bool {
            if (! $settings->whitelist_window_days) {
                return true;
            }
            return $booking->appointment?->ends_at_utc?->gte($now->copy()->subDays((int) $settings->whitelist_window_days)) ?? false;
        });
        $successes = $whiteBookings->where('outcome.outcome', 'successful')->count();
        $whiteNoShows = $whiteBookings->where('outcome.outcome', 'no_show')->count();
        $revenue = $whiteBookings->sum(fn (Booking $booking): int => $booking->netPaidMinor());

        $qualifiesForWhitelist = $settings->whitelist_mode !== 'disabled'
            && $successes >= (int) $settings->whitelist_success_threshold
            && $whiteNoShows <= (int) $settings->whitelist_max_no_shows
            && $revenue >= (int) $settings->whitelist_min_revenue_minor;

        if ($qualifiesForWhitelist) {
            $this->applyPolicy(
                $contact,
                'whitelist',
                'whitelist_good_history',
                $settings->whitelist_mode,
                "{$successes} successful appointments, {$whiteNoShows} no-shows, and qualifying revenue.",
                ['successes' => $successes, 'no_shows' => $whiteNoShows, 'revenue_minor' => $revenue, 'window_days' => $settings->whitelist_window_days],
                $settings,
            );
            return;
        }

        $this->resolvePolicy($contact, 'whitelist_good_history', 'The customer no longer meets the whitelist policy.');
    }

    public function addManual(OrganizationContact $contact, string $listType, ?Person $actor, ?string $reason = null): CustomerAccessEntry
    {
        abort_unless(in_array($listType, ['whitelist', 'blacklist'], true), 422);

        return DB::transaction(function () use ($contact, $listType, $actor, $reason): CustomerAccessEntry {
            CustomerAccessEntry::query()
                ->where('organization_contact_id', $contact->getKey())
                ->whereIn('status', ['active', 'suggested'])
                ->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);

            $entry = CustomerAccessEntry::create([
                'organization_id' => $contact->organization_id,
                'organization_contact_id' => $contact->getKey(),
                'list_type' => $listType,
                'status' => 'active',
                'source' => 'manual',
                'reason' => $reason,
                'created_by_person_id' => $actor?->getKey(),
            ]);

            $this->log($entry, 'added', 'manual', $reason, $actor);

            return $entry;
        });
    }

    public function approveSuggestion(CustomerAccessEntry $entry, ?Person $actor): void
    {
        abort_unless($entry->status === 'suggested' && $entry->source === 'policy', 422, 'This entry is not a policy suggestion.');

        DB::transaction(function () use ($entry, $actor): void {
            CustomerAccessEntry::query()
                ->where('organization_contact_id', $entry->organization_contact_id)
                ->where('status', 'active')
                ->whereKeyNot($entry->getKey())
                ->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);

            $entry->update(['status' => 'active']);
            $this->log($entry, 'policy_accepted', 'manual', 'Policy suggestion accepted by staff.', $actor);
        });
    }

    public function resolve(CustomerAccessEntry $entry, ?Person $actor): void
    {
        $entry->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);
        $this->log($entry, 'resolved', 'manual', 'Removed manually.', $actor);
    }

    private function applyPolicy(
        OrganizationContact $contact,
        string $listType,
        string $policyKey,
        string $mode,
        string $reason,
        array $snapshot,
        CustomerReputationSetting $settings,
    ): void {
        $status = $mode === 'automatic' ? 'active' : 'suggested';

        $manual = CustomerAccessEntry::query()
            ->where('organization_contact_id', $contact->getKey())
            ->where('status', 'active')
            ->where('source', 'manual')
            ->first();

        if ($manual) {
            return;
        }

        $existing = CustomerAccessEntry::query()
            ->where('organization_contact_id', $contact->getKey())
            ->where('policy_key', $policyKey)
            ->whereIn('status', ['active', 'suggested'])
            ->first();

        if ($existing) {
            $changes = ['reason' => $reason, 'policy_snapshot' => $snapshot];

            if ($status === 'active' && $existing->status === 'suggested') {
                CustomerAccessEntry::query()
                    ->where('organization_contact_id', $contact->getKey())
                    ->where('status', 'active')
                    ->where('source', 'policy')
                    ->whereKeyNot($existing->getKey())
                    ->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);
                $changes['status'] = 'active';
                $existing->update($changes);
                $this->log($existing, 'policy_applied', 'policy', $reason, null, $snapshot);
                return;
            }

            $existing->update($changes);
            return;
        }

        if ($status === 'active') {
            CustomerAccessEntry::query()
                ->where('organization_contact_id', $contact->getKey())
                ->where('status', 'active')
                ->where('source', 'policy')
                ->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);
        }

        $entry = CustomerAccessEntry::create([
            'organization_id' => $contact->organization_id,
            'organization_contact_id' => $contact->getKey(),
            'list_type' => $listType,
            'status' => $status,
            'source' => 'policy',
            'policy_key' => $policyKey,
            'reason' => $reason,
            'policy_snapshot' => $snapshot,
            'expires_at_utc' => $settings->policy_entry_expiration_days
                ? now('UTC')->addDays((int) $settings->policy_entry_expiration_days)
                : null,
        ]);

        $this->log($entry, $status === 'active' ? 'policy_applied' : 'policy_suggested', 'policy', $reason, null, $snapshot);
    }

    private function resolvePolicy(OrganizationContact $contact, string $policyKey, string $reason): void
    {
        $entries = CustomerAccessEntry::query()
            ->where('organization_contact_id', $contact->getKey())
            ->where('source', 'policy')
            ->where('policy_key', $policyKey)
            ->whereIn('status', ['active', 'suggested'])
            ->get();

        foreach ($entries as $entry) {
            $entry->update(['status' => 'resolved', 'resolved_at_utc' => now('UTC')]);
            $this->log($entry, 'policy_revoked', 'policy', $reason, null);
        }
    }

    private function log(CustomerAccessEntry $entry, string $event, string $source, ?string $reason, ?Person $actor, array $metadata = []): void
    {
        CustomerAccessEvent::create([
            'organization_id' => $entry->organization_id,
            'organization_contact_id' => $entry->organization_contact_id,
            'customer_access_entry_id' => $entry->getKey(),
            'event_type' => $event,
            'source' => $source,
            'reason' => $reason,
            'metadata' => $metadata,
            'actor_person_id' => $actor?->getKey(),
            'occurred_at_utc' => now('UTC'),
        ]);
    }
}
