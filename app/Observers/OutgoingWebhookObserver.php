<?php
namespace App\Observers;

use App\Domain\Webhooks\WebhookPublisher;
use App\Models\Booking;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Ticket;
use App\Support\Uuid\UuidBinary;
use Illuminate\Database\Eloquent\Model;

class OutgoingWebhookObserver
{
    public function created(Model $model): void { $this->record($model, true); }
    public function updated(Model $model): void { $this->record($model, false); }

    private function record(Model $model, bool $created): void
    {
        $events = [];
        $status = $model->status instanceof \BackedEnum ? $model->status->value : $model->status;
        if ($model instanceof Booking) {
            if ($created) { $events[] = 'booking.created'; }
            if (($created || $model->wasChanged('status')) && in_array($status, ['confirmed', 'cancelled', 'declined'], true)) { $events[] = 'booking.'.$status; }
            if (! $created && $model->wasChanged('appointment_id')) { $events[] = 'booking.rescheduled'; }
        } elseif (($created || $model->wasChanged('status')) && $model instanceof PaymentTransaction && $status === 'succeeded') {
            $events[] = 'payment.succeeded';
        } elseif (($created || $model->wasChanged('status')) && $model instanceof PaymentRefund && $status === 'succeeded') {
            $events[] = 'refund.succeeded';
        } elseif (($created || $model->wasChanged('status')) && $model instanceof Ticket && $status === 'checked_in') {
            $events[] = 'ticket.checked_in';
        }
        if (! $events) { return; }
        // Fresh organization avoids stale membership/plan snapshots held by a long-running process.
        $organization = \App\Models\Organization::whereKey($model->organization_id)->first();
        if (! $organization || ! app(\App\Domain\Plans\PlanEntitlementService::class)->hasBusinessFeatures($organization)) { return; }
        $data = ['id' => $model->uuid, 'status' => $status];
        if ($model instanceof Booking) {
            $appointment = $model->appointment()->first();
            $data += ['reference' => $model->reference, 'appointment_type_id' => UuidBinary::fromBytes($model->appointment_type_id),
                'appointment_id' => UuidBinary::fromBytes($model->appointment_id), 'attendee_count' => $model->attendee_count,
                'starts_at_utc' => $appointment?->starts_at_utc?->toIso8601String(), 'ends_at_utc' => $appointment?->ends_at_utc?->toIso8601String(),
                'price_minor' => $model->price_minor, 'currency' => $model->currency];
        } else {
            $data['booking_id'] = $model->booking_id ? UuidBinary::fromBytes($model->booking_id) : null;
            if ($model instanceof Ticket) { $data['checked_in_at_utc'] = $model->checked_in_at_utc?->toIso8601String(); }
            else { $data += ['amount_minor' => $model->amount_minor, 'currency' => $model->currency,
                'coupon_id' => $model->coupon_id ? UuidBinary::fromBytes($model->coupon_id) : null]; }
        }
        foreach ($events as $event) { app(WebhookPublisher::class)->publish($organization, $event, $data); }
    }
}
