<?php

namespace App\Domain\Customers;

use App\Enums\MembershipStatus;
use App\Models\Booking;
use App\Models\CustomerReputationSetting;
use App\Notifications\PostAppointmentOutcomeReviewEmail;
use Illuminate\Support\Facades\Notification;

class PostAppointmentReviewService
{
    public function sendDue(): int
    {
        $sent = 0;

        Booking::query()
            ->whereNull('outcome_review_requested_at_utc')
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->whereDoesntHave('outcome')
            ->whereHas('appointment', fn ($query) => $query
                ->where('ends_at_utc', '<=', now('UTC'))
                ->where('ends_at_utc', '>=', now('UTC')->subHours(48)))
            ->with(['appointment', 'appointmentType', 'organization.memberships.person.user'])
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->each(function (Booking $booking) use (&$sent): void {
                $settings = $booking->organization->customerReputationSetting
                    ?? CustomerReputationSetting::defaultsFor($booking->organization);

                if (! $settings->post_appointment_review_enabled) {
                    $booking->forceFill(['outcome_review_requested_at_utc' => now('UTC')])->save();
                    return;
                }

                $roles = $settings->review_roles ?: ['owner', 'administrator', 'manager'];
                $users = $booking->organization->memberships
                    ->filter(fn ($membership) => $membership->status === MembershipStatus::Active)
                    ->filter(fn ($membership) => in_array($membership->role->value, $roles, true))
                    ->map(fn ($membership) => $membership->person?->user)
                    ->filter()
                    ->unique(fn ($user) => $user->getKey());

                foreach ($users as $user) {
                    Notification::send($user, new PostAppointmentOutcomeReviewEmail($booking));
                    $sent++;
                }

                $booking->forceFill(['outcome_review_requested_at_utc' => now('UTC')])->save();
            });

        return $sent;
    }
}
