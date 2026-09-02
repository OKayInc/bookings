<?php
namespace Tests\Feature;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Domain\Questionnaires\QuestionnaireSubmissionService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\AppointmentStatus;
use App\Enums\BookingStatus;
use App\Models\Appointment;
use App\Models\AppointmentQuestion;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\OrganizationContact;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
class QuestionnaireConfigurationTest extends TestCase {
 use RefreshDatabase;
 public function test_numeric_answer_rate_is_persisted_and_multiplied_by_the_answer(): void {
  [$user,$org,$type]=$this->context();
  $response=$this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])->post(route('appointment-types.questions.store',$type),[
   'type'=>'number','label'=>'Additional hours','is_required'=>'1','is_active'=>'1','position'=>1,
   'number_min'=>'0','number_step'=>'0.5','pricing_adjustment_type'=>'rate','pricing_amount'=>'2.50','pricing_included_units'=>1,
  ]);

  $response->assertSessionHasNoErrors()->assertRedirect(route('appointment-types.questionnaire.index',$type));
  $question=AppointmentQuestion::firstOrFail();
  $this->assertSame('rate',$question->pricing_adjustment_type->value);
  $this->assertSame('per_unit',$question->pricing_application_mode->value);
  $this->assertSame(250,$question->pricing_amount_minor);

  $quote=app(QuestionnairePricingService::class)->quote($type->fresh(),60,[$question->uuid=>'4.5']);
  $this->assertSame(10875,$quote->totalMinor);
  $line=collect($quote->lines)->firstWhere('sourceUuid',$question->uuid);
  $this->assertSame('rate',$line->lineType);
  $this->assertSame('3.5',$line->quantity);
  $this->assertSame(875,$line->amountMinor);
  $this->assertSame('4.5',$line->metadata['entered_answer']);
  $this->assertSame(250,$line->metadata['rate_amount_minor']);

  $submission=app(QuestionnaireSubmissionService::class)->validateForBooking(
   Request::create('/','POST',['answers'=>[$question->uuid=>'4.5']]),$type->fresh(),60,
  );
  $this->assertSame(10875,$submission->quote->totalMinor);

  $this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])
   ->get(route('appointment-types.questions.edit',[$type,$question]))
   ->assertOk()->assertSee('Answer × rate')->assertSee('Rate per answer unit');
 }
 public function test_numeric_answer_rate_must_be_greater_than_zero(): void {
  [$user,$org,$type]=$this->context();
  $this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])->post(route('appointment-types.questions.store',$type),[
   'type'=>'number','label'=>'Additional hours','is_active'=>'1','pricing_adjustment_type'=>'rate','pricing_amount'=>'0.00',
  ])->assertSessionHasErrors('pricing_amount');
  $this->assertDatabaseCount('appointment_questions',0);
 }
 public function test_owner_can_add_select_question_with_fixed_and_percentage_option_charges(): void {
  [$user,$org,$type]=$this->context();
  $response=$this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])->post(route('appointment-types.questions.store',$type),[
    'type'=>'select','label'=>'Choose package','is_required'=>'1','is_active'=>'1','position'=>1,
    'options'=>[
      ['label'=>'Album','pricing_adjustment_type'=>'fixed','pricing_amount'=>'125.00','pricing_percentage_basis'=>'base_price'],
      ['label'=>'Rush delivery','pricing_adjustment_type'=>'percentage','pricing_percentage'=>'20','pricing_percentage_basis'=>'base_price'],
    ],
  ]);
  $response->assertSessionHasNoErrors()->assertRedirect(route('appointment-types.questionnaire.index',$type));
  $q=AppointmentQuestion::firstOrFail(); $this->assertSame('select',$q->type->value); $this->assertCount(2,$q->options);
  $this->assertSame(12500,$q->options[0]->pricing_amount_minor); $this->assertSame(2000,$q->options[1]->pricing_percentage_bps);
 }
 public function test_question_with_historical_answers_will_be_disabled_instead_of_deleted(): void {
  [$user,$org,$type]=$this->context();
  $question=$type->questions()->create([
   'type'=>'text','label'=>'Historical question','is_required'=>false,'is_active'=>true,'position'=>1,
  ]);
  $appointment=Appointment::create([
   'organization_id'=>$org->getKey(),'appointment_type_id'=>$type->getKey(),
   'starts_at_utc'=>'2026-08-31 13:00:00','ends_at_utc'=>'2026-08-31 14:00:00',
   'blocked_starts_at_utc'=>'2026-08-31 13:00:00','blocked_ends_at_utc'=>'2026-08-31 14:00:00',
   'scheduling_timezone'=>'America/Toronto','duration_value'=>60,'capacity'=>1,'status'=>AppointmentStatus::Scheduled,
  ]);
  $contact=OrganizationContact::factory()->create([
   'organization_id'=>$org->getKey(),'email'=>'history@example.test',
  ]);
  $booking=Booking::create([
   'organization_id'=>$org->getKey(),'appointment_id'=>$appointment->getKey(),'appointment_type_id'=>$type->getKey(),
   'organization_contact_id'=>$contact->getKey(),'reference'=>'HISTQ0000001','status'=>BookingStatus::Confirmed,
   'attendee_count'=>1,'booking_timezone'=>'America/Toronto','base_price_minor'=>10000,'price_minor'=>10000,'currency'=>'CAD',
   'first_name'=>'History','last_name'=>'Client','email'=>'history@example.test','email_normalized'=>'history@example.test',
   'manage_token_hash'=>hash('sha256','historical-question-test',true),
  ]);
  BookingAnswer::create([
   'booking_id'=>$booking->getKey(),'appointment_question_id'=>$question->getKey(),'question_uuid_snapshot'=>$question->uuid,
   'question_label'=>$question->label,'question_type'=>'text','value_json'=>['value'=>'Keep me'],'position'=>1,
  ]);

  $response=$this->actingAs($user)
   ->withSession(['active_organization_uuid'=>$org->uuid])
   ->delete(route('appointment-types.questions.destroy',[$type,$question]));

  $response->assertSessionHas('success','Question disabled because historical booking answers exist.');
  $question->refresh();
  $this->assertFalse($question->is_active);
  $this->assertSame(1,$question->answers()->count());
 }
 public function test_owner_can_configure_private_origin_and_non_overlapping_distance_rates(): void {
  [$user,$org,$type]=$this->context();
  $origin='100 Private Origin Road, Ottawa, ON, Canada';

  $response=$this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])->post(route('appointment-types.questions.store',$type),[
   'type'=>'address','label'=>'Service address','is_required'=>'1','is_active'=>'1','position'=>1,'address_region'=>'CA',
   'distance_pricing_enabled'=>'1','distance_origin_address'=>$origin,'distance_unit'=>'kilometer','distance_pricing_mode'=>'range',
   'distance_ranges'=>[
    ['minimum'=>'0','maximum'=>'20','amount'=>'0.00'],
    ['minimum'=>'20','maximum'=>'50','amount'=>'35.00'],
    ['minimum'=>'50','maximum'=>'','amount'=>'75.00'],
   ],
   'distance_fallback_increment'=>'5','distance_fallback_amount'=>'12.50',
  ]);

  $response->assertSessionHasNoErrors()->assertRedirect(route('appointment-types.questionnaire.index',$type));
  $question=AppointmentQuestion::query()->with('reusableQuestion')->firstOrFail();
  $this->assertSame('CA',data_get($question->configuration,'region'));
  $this->assertSame($origin,data_get($question->configuration,'distance_pricing.origin_address'));
  $this->assertSame('range',data_get($question->configuration,'distance_pricing.mode'));
  $this->assertSame(3500,data_get($question->configuration,'distance_pricing.ranges.1.amount_minor'));
  $this->assertSame(5.0,(float)data_get($question->configuration,'distance_pricing.fallback.increment'));
  $this->assertSame(1250,data_get($question->configuration,'distance_pricing.fallback.amount_minor'));
  $this->assertSame($question->configuration,$question->reusableQuestion->configuration);

  $this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])
   ->get(route('appointment-types.questions.edit',[$type,$question]))
   ->assertOk()->assertSee($origin)->assertSee('75.00')->assertSee('12.50');
 }
 public function test_overlapping_distance_ranges_are_rejected(): void {
  [$user,$org,$type]=$this->context();
  $response=$this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])->post(route('appointment-types.questions.store',$type),[
   'type'=>'address','label'=>'Service address','is_active'=>'1','distance_pricing_enabled'=>'1',
   'distance_origin_address'=>'100 Origin Road, Ottawa, ON','distance_unit'=>'mile','distance_pricing_mode'=>'range',
   'distance_ranges'=>[
    ['minimum'=>'0','maximum'=>'20','amount'=>'10.00'],
    ['minimum'=>'10','maximum'=>'30','amount'=>'20.00'],
   ],
   'distance_fallback_increment'=>'5','distance_fallback_amount'=>'10.00',
  ]);

  $response->assertSessionHasErrors('distance_ranges.1.minimum');
  $this->assertDatabaseCount('appointment_questions',0);
 }
 public function test_range_distance_pricing_requires_a_positive_per_distance_fallback(): void {
  [$user,$org,$type]=$this->context();
  $response=$this->actingAs($user)->withSession(['active_organization_uuid'=>$org->uuid])->post(route('appointment-types.questions.store',$type),[
   'type'=>'address','label'=>'Service address','is_active'=>'1','distance_pricing_enabled'=>'1',
   'distance_origin_address'=>'100 Origin Road, Ottawa, ON','distance_unit'=>'kilometer','distance_pricing_mode'=>'range',
   'distance_ranges'=>[['minimum'=>'0','maximum'=>'20','amount'=>'10.00']],
   'distance_fallback_increment'=>'0','distance_fallback_amount'=>'0.00',
  ]);

  $response->assertSessionHasErrors(['distance_fallback_increment','distance_fallback_amount']);
  $this->assertDatabaseCount('appointment_questions',0);
 }
 private function context(): array { $user=User::factory()->create(); $org=Organization::factory()->create(['currency'=>'CAD']); OrganizationMembership::create(['organization_id'=>$org->getKey(),'person_id'=>$user->person_id,'role'=>MembershipRole::Owner,'status'=>MembershipStatus::Active]); $type=AppointmentType::create(['organization_id'=>$org->getKey(),'name'=>'Questionnaire Test','slug'=>'questionnaire-test','visibility'=>'public','attendance_mode'=>'single','capacity'=>1,'duration_mode'=>'fixed','duration_unit'=>'minute','duration_value'=>60,'buffer_before_minutes'=>0,'buffer_after_minutes'=>0,'pricing_mode'=>'fixed','fixed_price_minor'=>10000,'email_verification_mode'=>'none','is_active'=>true]); return [$user,$org,$type]; }
}
