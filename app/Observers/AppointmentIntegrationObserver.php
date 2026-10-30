<?php

namespace App\Observers;

use App\Domain\Calendars\AppointmentIntegrationQueue;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\BookingAnswerFile;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Mark work in the same transaction; never perform provider HTTP from observers. */
class AppointmentIntegrationObserver
{
    public function saved(Model $model): void
    {
        if (! $model->wasRecentlyCreated && ! $model->wasChanged()) {
            return;
        }
        if ($model instanceof CalendarConnection && ! $model->wasRecentlyCreated
            && ! $model->wasChanged(['resource_id', 'organization_id', 'provider', 'external_account_id'])) {
            return; // Token refreshes and error reporting are not event content changes.
        }
        if ($model instanceof ExternalCalendar && ! $model->wasRecentlyCreated
            && ! $model->wasChanged(['is_active', 'is_owned', 'can_write', 'is_default_write', 'external_id'])) {
            return;
        }
        if ($model instanceof Appointment && ! $model->wasRecentlyCreated
            && ! $model->wasChanged(['status', 'starts_at_utc', 'ends_at_utc', 'scheduling_timezone',
                'event_location', 'show_starts_at_utc', 'show_ends_at_utc', 'meeting_join_url', 'meeting_provider'])) {
            return;
        }
        if ($model instanceof Booking && ! $model->wasRecentlyCreated
            && ! $model->wasChanged(['status', 'appointment_id', 'first_name', 'last_name', 'email', 'phone', 'attendee_count'])) {
            return;
        }
        $this->mark($model);
    }

    public function deleting(Model $model): void
    {
        if (! ($model instanceof Appointment || $model instanceof AppointmentType || $model instanceof Organization)) {
            $this->mark($model);
        }
    }

    private function mark(Model $model): void
    {
        $query = Appointment::query();
        if ($model instanceof Appointment) {
            $query->whereKey($model->getKey());
        } elseif ($model instanceof Booking) {
            $query->whereIn('id', array_filter([$model->appointment_id, $model->getRawOriginal('appointment_id')]));
        } elseif ($model instanceof BookingAnswer || $model instanceof BookingAnswerFile) {
            $query->whereIn('id', DB::table('bookings')->where('id', $model->booking_id)->select('appointment_id'));
        } else {
            $organizationId = $model instanceof Organization ? $model->getKey() : $model->organization_id;
            if ($model instanceof ExternalCalendar) {
                $organizationId = DB::table('calendar_connections')->where('id', $model->calendar_connection_id)->value('organization_id');
            }
            if ($organizationId === null) {
                return;
            }
            $query->where('organization_id', $organizationId)
                ->where('status', 'scheduled')
                ->where('ends_at_utc', '>=', now('UTC')->subDays((int) config('calendars.sync_days_back', 2)));
            if ($model instanceof AppointmentType) {
                $query->where('appointment_type_id', $model->getKey());
            }
        }
        $query->select('id')->chunkById(100, function ($appointments): void {
            foreach ($appointments as $appointment) {
                app(AppointmentIntegrationQueue::class)->request($appointment, dispatch: false);
            }
        });
    }
}
