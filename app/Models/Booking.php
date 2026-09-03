<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingPaymentStatus;
use App\Enums\PaymentCollectionMode;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'appointment_id', 'appointment_type_id', 'organization_contact_id',
        'appointment_type_invitation_id', 'contract_template_id', 'reference', 'status',
        'attendee_count', 'booking_timezone', 'base_price_minor', 'price_minor', 'currency',
        'payment_collection_mode', 'initial_payment_due_minor', 'balance_due_at_utc',
        'client_refund_percentage_bps', 'staff_refund_percentage_bps', 'payment_exempt',
        'payment_rule_id', 'payment_status', 'paid_minor', 'refunded_minor', 'first_name',
        'last_name', 'email', 'email_normalized', 'phone', 'email_verified_at',
        'email_verification_token_hash', 'email_verification_expires_at_utc',
        'manage_token_hash', 'expires_at_utc',
        'requires_resource_confirmation', 'cancellation_allowed', 'cancellation_notice_value', 'cancellation_notice_unit', 'cancellation_policy_text',
        'cancelled_at_utc', 'cancellation_reason', 'cancellation_origin', 'rescheduling_allowed', 'rescheduling_notice_value',
        'rescheduling_notice_unit', 'rescheduling_max_count', 'reschedule_count', 'rescheduling_policy_text',
    ];

    protected $hidden = [
        'id', 'organization_id', 'appointment_id', 'appointment_type_id', 'organization_contact_id',
        'appointment_type_invitation_id', 'contract_template_id', 'email_verification_token_hash',
        'manage_token_hash', 'payment_rule_id',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'attendee_count' => 'integer',
            'base_price_minor' => 'integer',
            'price_minor' => 'integer',
            'payment_collection_mode' => PaymentCollectionMode::class,
            'initial_payment_due_minor' => 'integer',
            'balance_due_at_utc' => 'immutable_datetime',
            'client_refund_percentage_bps' => 'integer',
            'staff_refund_percentage_bps' => 'integer',
            'payment_exempt' => 'boolean',
            'payment_status' => BookingPaymentStatus::class,
            'paid_minor' => 'integer',
            'refunded_minor' => 'integer',
            'email_verified_at' => 'immutable_datetime',
            'email_verification_expires_at_utc' => 'immutable_datetime',
            'expires_at_utc' => 'immutable_datetime',
            'requires_resource_confirmation' => 'boolean',
            'cancellation_allowed' => 'boolean',
            'cancellation_notice_value' => 'integer',
            'cancellation_notice_unit' => \App\Enums\BookingNoticeUnit::class,
            'cancelled_at_utc' => 'immutable_datetime',
            'rescheduling_allowed' => 'boolean',
            'rescheduling_notice_value' => 'integer',
            'rescheduling_notice_unit' => \App\Enums\BookingNoticeUnit::class,
            'rescheduling_max_count' => 'integer',
            'reschedule_count' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OrganizationContact::class, 'organization_contact_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(AppointmentTypeInvitation::class, 'appointment_type_invitation_id');
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(AppointmentContractTemplate::class, 'contract_template_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(BookingAttendee::class)->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(BookingAnswer::class)->orderBy('position');
    }

    public function priceLines(): HasMany
    {
        return $this->hasMany(BookingPriceLine::class)->orderBy('position');
    }

    public function contractSubmissions(): HasMany
    {
        return $this->hasMany(BookingContractSubmission::class)->latest('submitted_at_utc');
    }


    public function resourceConfirmations(): HasMany
    {
        return $this->hasMany(ResourceConfirmation::class)->orderByDesc('is_required')->orderBy('created_at');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(BookingReschedule::class)->latest();
    }

    public function scheduleProposals(): HasMany
    {
        return $this->hasMany(BookingScheduleProposal::class)->latest();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('created_at');
    }

    public function paymentRule(): BelongsTo
    {
        return $this->belongsTo(PaymentRule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class)->latest();
    }

    public function couponRedemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function netPaidMinor(): int
    {
        return max(0, (int) $this->paid_minor - (int) $this->refunded_minor);
    }

    public function outstandingMinor(): int
    {
        return max(0, (int) $this->price_minor - $this->netPaidMinor());
    }

    public function cancellationScheduleProposal(): BelongsTo
    {
        return $this->belongsTo(BookingScheduleProposal::class);
    }

    public function latestContractSubmission(): ?BookingContractSubmission
    {
        return $this->contractSubmissions()->first();
    }
}
