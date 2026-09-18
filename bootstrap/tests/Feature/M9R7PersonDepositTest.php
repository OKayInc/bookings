<?php

namespace Tests\Feature;

use App\Domain\Availability\AvailabilityScheduleService;
use App\Domain\Bookings\BookingCreationService;
use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Coupons\CouponRedemptionService;
use App\Domain\Bookings\PublicBookingHoldService;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Domain\Payments\PaymentRefundService;
use App\Domain\Resources\ResourceDepositService;
use App\Enums\AvailabilityScope;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\BookingStatus;
use App\Enums\CouponDiscountType;
use App\Enums\CouponSource;
use App\Enums\CouponStatus;
use App\Enums\PaymentRefundType;
use App\Models\Appointment;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class M9R7PersonDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_create_and_update_ignore_forged_deposits(): void
    {
        [$user, $organization] = $this->organizationContext();
        $this->actingAs($user)->withSession(['active_organization_uuid' => $organization->uuid]);
        $data = ['name' => 'Staff', 'type' => 'person', 'default_deposit' => 'invalid', 'default_requirement' => 'required'];
        $this->post(route('resources.store'), $data)->assertSessionHasNoErrors();
        $resource = Resource::where('name', 'Staff')->firstOrFail();
        $this->assertSame(0, $resource->deposit_amount_minor);
        $resource->update(['type' => 'equipment', 'deposit_amount_minor' => 1200]);
        $this->assertSame(1200, $resource->fresh()->deposit_amount_minor);
        $data['default_deposit'] = '99.00';
        $this->put(route('resources.update', $resource), $data)->assertSessionHasNoErrors();
        $this->assertSame(0, $resource->fresh()->deposit_amount_minor);
    }

    public function test_model_saves_clear_person_deposits_and_type_changes(): void
    {
        $organization = Organization::factory()->create();
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'name' => 'Staff', 'type' => 'person', 'deposit_amount_minor' => 100]);
        $this->assertSame(0, $resource->fresh()->deposit_amount_minor);
        $resource->update(['type' => 'room', 'deposit_amount_minor' => 400]);
        $this->assertSame(400, $resource->fresh()->deposit_amount_minor);
        $resource->update(['type' => 'person']);
        $this->assertSame(0, $resource->fresh()->deposit_amount_minor);
    }

    public function test_migration_clears_existing_person_values(): void
    {
        $organization = Organization::factory()->create();
        $resource = Resource::create(['organization_id' => $organization->getKey(), 'name' => 'Staff', 'type' => 'person']);
        \Illuminate\Support\Facades\DB::table('resources')->where('id', $resource->getKey())->update(['deposit_amount_minor' => 1500]);
        $migration = require database_path('migrations/2026_09_06_000069_zero_person_resource_deposits.php');
        $migration->up();
        $this->assertSame(0, $resource->fresh()->deposit_amount_minor);
    }

    private function organizationContext(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['timezone' => 'UTC', 'currency' => 'CAD']);
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

}
