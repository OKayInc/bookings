<?php

namespace App\Domain\Bookings;

use App\Enums\BookingNoticeUnit;
use App\Enums\BookingStatus;
use App\Enums\ReminderThresholdBasis;
use App\Models\Booking;
use App\Models\ReminderDelivery;
use App\Notifications\BookingReminderEmail;
use App\Notifications\ResourceReminderEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

class BookingReminderService
{
    public function sendDue(?CarbonImmutable $nowUtc = null): int
    {
        $nowUtc ??= CarbonImmutable::now('UTC');
        $count = 0;
        $statuses = [
            BookingStatus::PendingContractReview->value,
            BookingStatus::PendingStaffConfirmation->value,
            BookingStatus::PendingPayment->value,
            BookingStatus::Confirmed->value,
        ];

        Booking::query()
            ->with(['organization', 'appointmentType', 'appointment.resources.person', 'attendees'])
            ->whereIn('status', $statuses)
            ->whereHas('appointmentType', fn ($q) => $q->where('reminder_enabled', true))
            ->orderBy('id')
            ->chunk(100, function ($bookings) use ($nowUtc, &$count): void {
                foreach ($bookings as $booking) {
                    if (! $this->isDue($booking, $nowUtc)) {
                        continue;
                    }

                    $type = $booking->appointmentType;
                    if ($type->reminder_clients) {
                        $emails = collect([$booking->email])
                            ->merge($booking->attendees->pluck('email'))
                            ->filter()
                            ->map(fn ($email) => strtolower(trim((string) $email)))
                            ->unique()
                            ->values();
                        foreach ($emails as $email) {
                            $key = 'client:'.$booking->uuid.':'.sha1($email);
                            if (ReminderDelivery::where('delivery_key', $key)->exists()) {
                                continue;
                            }
                            Notification::route('mail', $email)->notify(new BookingReminderEmail($booking));
                            $this->record($key, 'client', $booking, null, $email, $nowUtc);
                            $count++;
                        }
                    }

                    if ($type->reminder_resources) {
                        foreach ($booking->appointment->resources as $resource) {
                            $email = $resource->person?->primary_email;
                            if (! $email) {
                                continue;
                            }
                            $key = 'resource:'.$booking->appointment->uuid.':'.$resource->uuid;
                            if (ReminderDelivery::where('delivery_key', $key)->exists()) {
                                continue;
                            }
                            Notification::route('mail', $email)->notify(new ResourceReminderEmail($booking, $resource));
                            $this->record($key, 'resource', $booking, $resource, $email, $nowUtc);
                            $count++;
                        }
                    }
                }
            });

        return $count;
    }

    public function isDue(Booking $booking, ?CarbonImmutable $nowUtc = null): bool
    {
        $booking->loadMissing(['organization', 'appointmentType', 'appointment']);
        $nowUtc ??= CarbonImmutable::now('UTC');
        $type = $booking->appointmentType;
        if (! $type->reminder_enabled || $booking->appointment->starts_at_utc->lte($nowUtc)) {
            return false;
        }

        $thresholdDays = max(0, (int) $type->reminder_threshold_days);
        $basis = $type->reminder_threshold_basis instanceof ReminderThresholdBasis
            ? $type->reminder_threshold_basis
            : ReminderThresholdBasis::tryFrom((string) $type->reminder_threshold_basis) ?? ReminderThresholdBasis::LeadTime;
        $qualifies = match ($basis) {
            ReminderThresholdBasis::LeadTime => CarbonImmutable::instance($booking->created_at)->diffInSeconds(CarbonImmutable::instance($booking->appointment->starts_at_utc), false) >= $thresholdDays * 86400,
            ReminderThresholdBasis::Duration => CarbonImmutable::instance($booking->appointment->starts_at_utc)->diffInSeconds(CarbonImmutable::instance($booking->appointment->ends_at_utc), false) >= $thresholdDays * 86400,
        };
        if (! $qualifies) {
            return false;
        }

        $startLocal = CarbonImmutable::instance($booking->appointment->starts_at_utc)->setTimezone($booking->organization->timezone);
        $value = max(1, (int) $type->reminder_before_value);
        $unit = $type->reminder_before_unit instanceof BookingNoticeUnit
            ? $type->reminder_before_unit
            : BookingNoticeUnit::tryFrom((string) $type->reminder_before_unit) ?? BookingNoticeUnit::Day;
        $dueLocal = match ($unit) {
            BookingNoticeUnit::Minute => $startLocal->subMinutes($value),
            BookingNoticeUnit::Hour => $startLocal->subHours($value),
            BookingNoticeUnit::Day => $startLocal->subDays($value),
            BookingNoticeUnit::Week => $startLocal->subWeeks($value),
            BookingNoticeUnit::Month => $startLocal->subMonthsNoOverflow($value),
        };

        return $nowUtc->gte($dueLocal->utc()) && $nowUtc->lt(CarbonImmutable::instance($booking->appointment->starts_at_utc)->utc());
    }

    private function record(string $key, string $kind, Booking $booking, $resource, string $email, CarbonImmutable $sentAt): void
    {
        ReminderDelivery::create([
            'organization_id' => $booking->organization_id,
            'booking_id' => $kind === 'client' ? $booking->getKey() : null,
            'appointment_id' => $booking->appointment_id,
            'resource_id' => $resource?->getKey(),
            'delivery_key' => $key,
            'recipient_kind' => $kind,
            'recipient_email' => $email,
            'sent_at_utc' => $sentAt,
        ]);
    }
}
