<?php

namespace Tests\Unit;

use App\Models\AppointmentContractTemplate;
use App\Models\Appointment;
use App\Models\Booking;
use App\Models\BookingAttendee;
use App\Models\BookingContractFile;
use App\Models\BookingContractSubmission;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentQuestionResourceRule;
use App\Models\QuestionOption;
use App\Models\ReusableQuestion;
use App\Models\ReusableQuestionOption;
use App\Models\BookingAnswer;
use App\Models\BookingAnswerFile;
use App\Models\BookingPriceLine;
use App\Models\ResourceConfirmation;
use App\Models\ReminderDelivery;
use App\Models\BookingReschedule;
use App\Models\BookingScheduleProposal;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\AppointmentExternalEvent;
use App\Models\AppointmentType;
use App\Models\AppointmentTypeInvitation;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySchedule;
use App\Models\BookingHold;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Person;
use App\Models\OrganizationPaymentSetting;
use App\Models\PaymentRefund;
use App\Models\PaymentRule;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\BookingResourceDeposit;
use App\Models\Coupon;
use App\Models\CouponOffer;
use App\Models\CouponRedemption;
use App\Models\Resource;
use App\Models\ShortNoticeFeeRule;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelTableNameTest extends TestCase
{
    public static function modelTables(): array
    {
        return [
            [Person::class, 'persons'],
            [User::class, 'users'],
            [Organization::class, 'organizations'],
            [OrganizationContact::class, 'organization_contacts'],
            [OrganizationMembership::class, 'organization_memberships'],
            [OrganizationPaymentSetting::class, 'organization_payment_settings'],
            [PaymentRule::class, 'payment_rules'],
            [PaymentTransaction::class, 'payment_transactions'],
            [PaymentRefund::class, 'payment_refunds'],
            [PaymentWebhookEvent::class, 'payment_webhook_events'],
            [BookingResourceDeposit::class, 'booking_resource_deposits'],
            [CouponOffer::class, 'coupon_offers'],
            [Coupon::class, 'coupons'],
            [CouponRedemption::class, 'coupon_redemptions'],
            [Resource::class, 'resources'],
            [AppointmentType::class, 'appointment_types'],
            [ShortNoticeFeeRule::class, 'short_notice_fee_rules'],
            [AppointmentTypeInvitation::class, 'appointment_type_invitations'],
            [AppointmentContractTemplate::class, 'appointment_contract_templates'],
            [AvailabilitySchedule::class, 'availability_schedules'],
            [AvailabilityRule::class, 'availability_rules'],
            [AvailabilityException::class, 'availability_exceptions'],
            [BookingHold::class, 'booking_holds'],
            [Appointment::class, 'appointments'],
            [Booking::class, 'bookings'],
            [BookingAttendee::class, 'booking_attendees'],
            [BookingContractSubmission::class, 'booking_contract_submissions'],
            [BookingContractFile::class, 'booking_contract_files'],
            [AppointmentQuestion::class, 'appointment_questions'],
            [AppointmentQuestionResourceRule::class, 'appointment_question_resource_rules'],
            [QuestionOption::class, 'question_options'],
            [ReusableQuestion::class, 'reusable_questions'],
            [ReusableQuestionOption::class, 'reusable_question_options'],
            [BookingAnswer::class, 'booking_answers'],
            [BookingAnswerFile::class, 'booking_answer_files'],
            [BookingPriceLine::class, 'booking_price_lines'],
            [ResourceConfirmation::class, 'resource_confirmations'],
            [ReminderDelivery::class, 'reminder_deliveries'],
            [BookingReschedule::class, 'booking_reschedules'],
            [BookingScheduleProposal::class, 'booking_schedule_proposals'],
            [CalendarConnection::class, 'calendar_connections'],
            [ExternalCalendar::class, 'external_calendars'],
            [AppointmentExternalEvent::class, 'appointment_external_events'],
        ];
    }

    #[DataProvider('modelTables')]
    public function test_model_uses_expected_table(string $modelClass, string $expectedTable): void
    {
        $this->assertSame($expectedTable, (new $modelClass)->getTable());
    }
}
