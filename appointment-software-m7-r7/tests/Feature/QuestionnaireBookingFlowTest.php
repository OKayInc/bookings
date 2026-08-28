<?php
namespace Tests\Feature;
use App\Domain\Availability\AvailabilityScheduleService;
use App\Enums\AvailabilityScope;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\BookingPriceLine;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class QuestionnaireBookingFlowTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void { parent::setUp(); CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00','UTC')); config(['questionnaire.email_dns_validation'=>false]); }
 protected function tearDown(): void { CarbonImmutable::setTestNow(); parent::tearDown(); }
 public function test_guest_answers_questionnaire_and_server_calculates_option_and_number_charges(): void {
  [$org,$type]=$this->type();
  $choice=$type->questions()->create(['type'=>'checkboxes','label'=>'Extras','is_required'=>false,'is_active'=>true,'position'=>1]);
  $album=$choice->options()->create(['label'=>'Album','value'=>'album','position'=>1,'is_active'=>true,'pricing_adjustment_type'=>'fixed','pricing_amount_minor'=>5000,'pricing_percentage_basis'=>'base_price']);
  $number=$type->questions()->create(['type'=>'number','label'=>'Extra people','is_required'=>true,'is_active'=>true,'position'=>2,'configuration'=>['min'=>0,'max'=>10,'step'=>1],'pricing_adjustment_type'=>'fixed','pricing_application_mode'=>'per_unit','pricing_amount_minor'=>2500,'pricing_percentage_basis'=>'base_price','pricing_included_units'=>1]);
  $slots=$this->getJson(route('public.booking.slots',$type).'?'.http_build_query(['access_mode'=>'direct','timezone'=>'America/Toronto','date'=>'2026-08-31','duration_value'=>60,'attendee_count'=>1]))->assertOk();
  $start=$slots->json('slots.0.starts_at_utc');
  $hold=$this->postJson(route('public.booking.holds.store',$type),['access_mode'=>'direct','timezone'=>'America/Toronto','starts_at_utc'=>$start,'duration_value'=>60,'attendee_count'=>1])->assertOk();
  $token=basename((string)parse_url($hold->json('continue_url'),PHP_URL_PATH));
  $quote=$this->postJson(route('public.booking-holds.quote',$token),['answers'=>[$choice->uuid=>[$album->uuid],$number->uuid=>3]])->assertOk();
  $quote->assertJsonPath('total_minor',20000); // 100 + 50 + (3-1)*25
  $response=$this->post(route('public.booking-holds.store',$token),['first_name'=>'Guest','last_name'=>'Client','email'=>'guest@example.test','answers'=>[$choice->uuid=>[$album->uuid],$number->uuid=>3]]);
  $booking=Booking::firstOrFail(); $response->assertRedirect(route('public.bookings.received',$booking->reference));
  $this->assertSame(10000,$booking->base_price_minor); $this->assertSame(20000,$booking->price_minor); $this->assertSame(2,BookingAnswer::count()); $this->assertSame(3,BookingPriceLine::count());
  $this->assertSame('Album',data_get($booking->answers()->where('question_type','checkboxes')->firstOrFail()->value_json,'value.0.label'));
 }
 private function type(): array { $org=Organization::factory()->create(['slug'=>'qdemo','timezone'=>'America/Toronto','currency'=>'CAD']); $type=AppointmentType::create(['organization_id'=>$org->getKey(),'name'=>'Questionnaire Session','slug'=>'questionnaire-session','visibility'=>'public','attendance_mode'=>'single','capacity'=>1,'duration_mode'=>'fixed','duration_unit'=>'minute','duration_value'=>60,'start_interval_minutes'=>60,'buffer_before_minutes'=>0,'buffer_after_minutes'=>0,'pricing_mode'=>'fixed','fixed_price_minor'=>10000,'email_verification_mode'=>'none','is_active'=>true]); app(AvailabilityScheduleService::class)->save($org,AvailabilityScope::Organization,$org,'America/Toronto',true,[['weekday'=>1,'start_time'=>'09:00','end_time'=>'12:00']]); return [$org,$type->fresh(['organization'])]; }
}
