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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class QuestionnaireBookingFlowTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void { parent::setUp(); CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 14:00:00','UTC')); Cache::flush(); config(['questionnaire.email_dns_validation'=>false]); }
 protected function tearDown(): void { CarbonImmutable::setTestNow(); parent::tearDown(); }
 public function test_numeric_constraints_are_enforced_on_quote_and_final_booking_without_javascript(): void {
  [, $type]=$this->type();
  $source=$type->questions()->create(['type'=>'number','label'=>'Q1 minimum','is_required'=>true,'is_active'=>true,'position'=>1]);
  $target=$type->questions()->create(['type'=>'number','label'=>'Q2 answer','is_required'=>true,'is_active'=>true,'position'=>2]);
  $constraint=$target->numericConstraints()->create(['source_question_id'=>$source->getKey(),'comparison_operator'=>'>=','boolean_operator'=>'and','position'=>1]);
  $slots=$this->getJson(route('public.booking.slots',$type).'?'.http_build_query(['access_mode'=>'direct','timezone'=>'America/Toronto','date'=>'2026-08-31','duration_value'=>60,'attendee_count'=>1]))->assertOk();
  $hold=$this->postJson(route('public.booking.holds.store',$type),['access_mode'=>'direct','timezone'=>'America/Toronto','starts_at_utc'=>$slots->json('slots.0.starts_at_utc'),'duration_value'=>60,'attendee_count'=>1])->assertOk();
  $token=basename((string)parse_url($hold->json('continue_url'),PHP_URL_PATH));
  $this->get(route('public.booking-holds.edit',$token))->assertOk()->assertSee('numeric-question-constraints.js',false)->assertSee('Q1 minimum')->assertSee('data-numeric-constraints',false);
  $invalid=[$source->uuid=>'5',$target->uuid=>'4'];
  $this->postJson(route('public.booking-holds.quote',$token),['answers'=>$invalid])->assertUnprocessable()->assertSee('Q2 answer');
  $client=['first_name'=>'Numeric','last_name'=>'Client','email'=>'numeric@example.test'];
  $this->postJson(route('public.booking-holds.store',$token),[...$client,'answers'=>$invalid])->assertUnprocessable()->assertJsonValidationErrors('answers.'.$target->uuid);
  $this->assertDatabaseCount('bookings',0);
  $this->assertDatabaseCount('booking_answers',0);

  $equal=[$source->uuid=>'5',$target->uuid=>'5.0'];
  $this->postJson(route('public.booking-holds.quote',$token),['answers'=>$equal])->assertOk()->assertJsonPath('total_minor',10000);
  // Current configuration is authoritative, even if the guest already received a quote.
  $constraint->update(['comparison_operator'=>'>']);
  $this->postJson(route('public.booking-holds.store',$token),[...$client,'answers'=>$equal])->assertUnprocessable()->assertJsonValidationErrors('answers.'.$target->uuid);
  $this->assertDatabaseCount('bookings',0);
  $response=$this->post(route('public.booking-holds.store',$token),[...$client,'answers'=>[$source->uuid=>'5',$target->uuid=>'6']]);
  $booking=Booking::firstOrFail();
  $response->assertRedirect(route('public.bookings.received',$booking->reference));
  $this->assertDatabaseCount('booking_answers',2);
  $this->assertSame(10000,$booking->price_minor);
 }
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
 public function test_unmatched_address_distance_uses_fallback_in_live_quote_and_booking_snapshot(): void {
  [$org,$type]=$this->type();
  $origin='100 Private Origin Road, Ottawa, ON, Canada';
  $question=$type->questions()->create([
   'type'=>'address','label'=>'Service address','is_required'=>true,'is_active'=>true,'position'=>1,
   'configuration'=>['region'=>'CA','distance_pricing'=>['enabled'=>true,'origin_address'=>$origin,'unit'=>'kilometer','mode'=>'range','ranges'=>[
    ['minimum'=>0,'maximum'=>10,'amount_minor'=>1000],['minimum'=>20,'maximum'=>null,'amount_minor'=>5000],
   ],'fallback'=>['increment'=>5,'amount_minor'=>1000]]],
  ]);
  config(['questionnaire.google.api_key'=>'address-key','questionnaire.google.routes_api_key'=>'routes-key']);
  Http::fake([
   'https://addressvalidation.googleapis.com/*'=>Http::response(['result'=>[
    'verdict'=>['addressComplete'=>true],
    'address'=>['formattedAddress'=>'200 Client Street, Ottawa, ON, Canada'],
    'geocode'=>['placeId'=>'client-place','location'=>['latitude'=>45.4,'longitude'=>-75.7]],
   ]]),
   'https://routes.googleapis.com/*'=>Http::response(['routes'=>[['distanceMeters'=>12500]]]),
  ]);

  $slots=$this->getJson(route('public.booking.slots',$type).'?'.http_build_query(['access_mode'=>'direct','timezone'=>'America/Toronto','date'=>'2026-08-31','duration_value'=>60,'attendee_count'=>1]))->assertOk();
  $hold=$this->postJson(route('public.booking.holds.store',$type),['access_mode'=>'direct','timezone'=>'America/Toronto','starts_at_utc'=>$slots->json('slots.0.starts_at_utc'),'duration_value'=>60,'attendee_count'=>1])->assertOk();
  $token=basename((string)parse_url($hold->json('continue_url'),PHP_URL_PATH));
  $this->get(route('public.booking-holds.edit',$token))->assertOk()->assertDontSee($origin);

  $quote=$this->postJson(route('public.booking-holds.quote',$token),['answers'=>[$question->uuid=>'200 Client St, Ottawa, ON']])->assertOk();
  $quote->assertJsonPath('total_minor',13000)->assertJsonPath('lines.1.amount_minor',3000)->assertJsonPath('lines.1.quantity','3');
  $this->assertStringContainsString('12.5 km',(string)$quote->json('lines.1.label'));
  $this->assertStringNotContainsString($origin,$quote->getContent());

  $response=$this->post(route('public.booking-holds.store',$token),['first_name'=>'Distance','last_name'=>'Client','email'=>'distance@example.test','answers'=>[$question->uuid=>'200 Client St, Ottawa, ON']]);
  $booking=Booking::query()->with(['answers','priceLines'])->firstOrFail();
  $response->assertRedirect(route('public.bookings.received',$booking->reference));
  $this->assertSame(13000,$booking->price_minor);
  $this->assertSame(12500,data_get($booking->answers->first()->normalized_json,'driving_distance.meters'));
  $this->assertSame('kilometer',data_get($booking->answers->first()->normalized_json,'driving_distance.unit'));
  $distanceLine=$booking->priceLines->firstWhere('source_type','question_distance');
  $this->assertNotNull($distanceLine);
  $this->assertSame(3000,$distanceLine->amount_minor);
  $this->assertSame('distance_fallback',$distanceLine->line_type);
  $this->assertSame(3.0,(float)$distanceLine->quantity);
  $this->assertSame(5.0,(float)data_get($distanceLine->metadata,'fallback_increment'));
  $this->assertSame(3,(int)data_get($distanceLine->metadata,'fallback_blocks'));
  $this->assertStringNotContainsString($origin,json_encode($booking->answers->first()->normalized_json,JSON_THROW_ON_ERROR));
  $this->assertStringNotContainsString($origin,json_encode($distanceLine->metadata,JSON_THROW_ON_ERROR));
 }
 public function test_unroutable_distance_priced_address_cannot_be_booked_without_its_fee(): void {
  [$org,$type]=$this->type();
  $question=$type->questions()->create([
   'type'=>'address','label'=>'Service address','is_required'=>true,'is_active'=>true,'position'=>1,
   'configuration'=>['region'=>'CA','distance_pricing'=>['enabled'=>true,'origin_address'=>'100 Origin Road, Ottawa, ON','unit'=>'mile','mode'=>'fixed','fixed_amount_minor'=>3000]],
  ]);
  config(['questionnaire.google.api_key'=>'address-key','questionnaire.google.routes_api_key'=>'routes-key']);
  Http::fake([
   'https://addressvalidation.googleapis.com/*'=>Http::response(['result'=>[
    'verdict'=>['addressComplete'=>true],
    'address'=>['formattedAddress'=>'Unroutable Island, Canada'],
    'geocode'=>['placeId'=>'island-place','location'=>['latitude'=>45.0,'longitude'=>-75.0]],
   ]]),
   'https://routes.googleapis.com/*'=>Http::response(['routes'=>[]]),
  ]);

  $slots=$this->getJson(route('public.booking.slots',$type).'?'.http_build_query(['access_mode'=>'direct','timezone'=>'America/Toronto','date'=>'2026-08-31','duration_value'=>60,'attendee_count'=>1]))->assertOk();
  $hold=$this->postJson(route('public.booking.holds.store',$type),['access_mode'=>'direct','timezone'=>'America/Toronto','starts_at_utc'=>$slots->json('slots.0.starts_at_utc'),'duration_value'=>60,'attendee_count'=>1])->assertOk();
  $token=basename((string)parse_url($hold->json('continue_url'),PHP_URL_PATH));

  $response=$this->post(route('public.booking-holds.store',$token),['first_name'=>'Route','last_name'=>'Failure','email'=>'route.failure@example.test','answers'=>[$question->uuid=>'Unroutable Island, Canada']]);

  $response->assertSessionHasErrors('answers.'.$question->uuid);
  $this->assertDatabaseCount('bookings',0);
 }
 private function type(): array { $org=Organization::factory()->create(['slug'=>'qdemo','timezone'=>'America/Toronto','currency'=>'CAD']); $type=AppointmentType::create(['organization_id'=>$org->getKey(),'name'=>'Questionnaire Session','slug'=>'questionnaire-session','visibility'=>'public','attendance_mode'=>'single','capacity'=>1,'duration_mode'=>'fixed','duration_unit'=>'minute','duration_value'=>60,'start_interval_minutes'=>60,'buffer_before_minutes'=>0,'buffer_after_minutes'=>0,'pricing_mode'=>'fixed','fixed_price_minor'=>10000,'email_verification_mode'=>'none','is_active'=>true]); app(AvailabilityScheduleService::class)->save($org,AvailabilityScope::Organization,$org,'America/Toronto',true,[['weekday'=>1,'start_time'=>'09:00','end_time'=>'12:00']]); return [$org,$type->fresh(['organization'])]; }
}
