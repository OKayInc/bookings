<?php

namespace App\Models;

use App\Enums\AppointmentVisibility;
use App\Enums\AttendanceMode;
use App\Enums\AttendeePricingMode;
use App\Enums\BookingNoticeUnit;
use App\Enums\DurationMode;
use App\Enums\DurationUnit;
use App\Enums\EmailVerificationMode;
use App\Enums\ConferenceProvider;
use App\Enums\PricingMode;
use App\Enums\SeasonRecurrence;
use App\Enums\TicketSeatingScheme;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class AppointmentType extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'logo_path',
        'visibility',
        'access_password',
        'public_token',
        'attendance_mode',
        'ticketing_enabled',
        'show_start_offset_minutes',
        'show_end_offset_minutes',
        'ticket_seating_scheme',
        'ticket_seat_optional',
        'ticket_seat_blocks',
        'is_online',
        'meeting_provider',
        'capacity',
        'duration_mode',
        'duration_unit',
        'duration_value',
        'minimum_duration_value',
        'maximum_duration_value',
        'duration_increment_value',
        'buffer_before_minutes',
        'start_interval_minutes',
        'booking_notice_value',
        'booking_notice_unit',
        'maximum_booking_notice_value',
        'maximum_booking_notice_unit',
        'seasonal_availability_enabled',
        'season_start_date',
        'season_end_date',
        'season_recurrence',
        'buffer_after_minutes',
        'pricing_mode',
        'fixed_price_minor',
        'attendee_price_minor',
        'attendee_pricing_mode',
        'attendee_price_ranges',
        'rate_amount_minor',
        'rate_unit',
        'requires_resource_confirmation',
        'email_verification_mode',
        'redirect_url',
        'is_active',
        'cancellation_allowed', 'cancellation_notice_value', 'cancellation_notice_unit', 'cancellation_policy_text',
        'rescheduling_allowed', 'rescheduling_notice_value', 'rescheduling_notice_unit', 'rescheduling_max_count', 'rescheduling_policy_text',
        'reminder_enabled', 'reminder_threshold_basis', 'reminder_threshold_days', 'reminder_before_value',
        'reminder_before_unit', 'reminder_clients', 'reminder_resources',
    ];

    protected $hidden = ['id', 'access_password', 'public_token'];

    protected $appends = ['uuid', 'logo_url'];

    protected function casts(): array
    {
        return [
            'visibility' => AppointmentVisibility::class,
            'attendance_mode' => AttendanceMode::class,
            'ticketing_enabled' => 'boolean',
            'show_start_offset_minutes' => 'integer',
            'show_end_offset_minutes' => 'integer',
            'ticket_seating_scheme' => TicketSeatingScheme::class,
            'ticket_seat_optional' => 'boolean',
            'ticket_seat_blocks' => 'array',
            'is_online' => 'boolean',
            'meeting_provider' => ConferenceProvider::class,
            'capacity' => 'integer',
            'duration_mode' => DurationMode::class,
            'duration_unit' => DurationUnit::class,
            'duration_value' => 'integer',
            'minimum_duration_value' => 'integer',
            'maximum_duration_value' => 'integer',
            'duration_increment_value' => 'integer',
            'buffer_before_minutes' => 'integer',
            'start_interval_minutes' => 'integer',
            'booking_notice_value' => 'integer',
            'booking_notice_unit' => BookingNoticeUnit::class,
            'maximum_booking_notice_value' => 'integer',
            'maximum_booking_notice_unit' => BookingNoticeUnit::class,
            'seasonal_availability_enabled' => 'boolean',
            'season_start_date' => 'immutable_date',
            'season_end_date' => 'immutable_date',
            'season_recurrence' => SeasonRecurrence::class,
            'buffer_after_minutes' => 'integer',
            'pricing_mode' => PricingMode::class,
            'fixed_price_minor' => 'integer',
            'attendee_price_minor' => 'integer',
            'attendee_pricing_mode' => AttendeePricingMode::class,
            'attendee_price_ranges' => 'array',
            'rate_amount_minor' => 'integer',
            'rate_unit' => DurationUnit::class,
            'requires_resource_confirmation' => 'boolean',
            'email_verification_mode' => EmailVerificationMode::class,
            'is_active' => 'boolean',
            'cancellation_allowed' => 'boolean',
            'cancellation_notice_value' => 'integer',
            'cancellation_notice_unit' => BookingNoticeUnit::class,
            'rescheduling_allowed' => 'boolean',
            'rescheduling_notice_value' => 'integer',
            'rescheduling_notice_unit' => BookingNoticeUnit::class,
            'rescheduling_max_count' => 'integer',
            'reminder_enabled' => 'boolean',
            'reminder_threshold_basis' => \App\Enums\ReminderThresholdBasis::class,
            'reminder_threshold_days' => 'integer',
            'reminder_before_value' => 'integer',
            'reminder_before_unit' => BookingNoticeUnit::class,
            'reminder_clients' => 'boolean',
            'reminder_resources' => 'boolean',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk((string) config('appointment-types.logo_disk', 'public'))->url($this->logo_path);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'appointment_type_resources')
            ->withPivot('is_required', 'requirement_mode', 'replacement_group')
            ->withTimestamps();
    }

    public function contractTemplates(): HasMany
    {
        return $this->hasMany(AppointmentContractTemplate::class);
    }

    public function contractTemplate(): HasOne
    {
        return $this->hasOne(AppointmentContractTemplate::class)->where('is_active', true);
    }

    public function availabilitySchedules(): HasMany
    {
        return $this->hasMany(AvailabilitySchedule::class, 'scope_id', 'id')
            ->where('scope_type', \App\Enums\AvailabilityScope::AppointmentType->value);
    }

    public function bookingHolds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AppointmentQuestion::class)->orderBy('position');
    }

    public function shortNoticeFeeRules(): HasMany
    {
        return $this->hasMany(ShortNoticeFeeRule::class)->orderBy('position');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AppointmentTypeInvitation::class);
    }

    public function externalCalendars(): BelongsToMany
    {
        return $this->belongsToMany(ExternalCalendar::class, 'appointment_type_calendars')
            ->withPivot('check_availability', 'create_event')
            ->withTimestamps();
    }
}
