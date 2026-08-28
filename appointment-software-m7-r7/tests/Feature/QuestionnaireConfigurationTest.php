<?php
namespace Tests\Feature;
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
use Tests\TestCase;
class QuestionnaireConfigurationTest extends TestCase {
 use RefreshDatabase;
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
 private function context(): array { $user=User::factory()->create(); $org=Organization::factory()->create(['currency'=>'CAD']); OrganizationMembership::create(['organization_id'=>$org->getKey(),'person_id'=>$user->person_id,'role'=>MembershipRole::Owner,'status'=>MembershipStatus::Active]); $type=AppointmentType::create(['organization_id'=>$org->getKey(),'name'=>'Questionnaire Test','slug'=>'questionnaire-test','visibility'=>'public','attendance_mode'=>'single','capacity'=>1,'duration_mode'=>'fixed','duration_unit'=>'minute','duration_value'=>60,'buffer_before_minutes'=>0,'buffer_after_minutes'=>0,'pricing_mode'=>'fixed','fixed_price_minor'=>10000,'email_verification_mode'=>'none','is_active'=>true]); return [$user,$org,$type]; }
}
