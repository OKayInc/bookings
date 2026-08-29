<?php
namespace Tests\Unit;
use App\Domain\Questionnaires\QuestionnairePricingService;
use App\Models\AppointmentType;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class QuestionnairePricingServiceTest extends TestCase {
 use RefreshDatabase;
 public function test_percentage_current_subtotal_is_applied_in_question_order(): void {
  $org=Organization::factory()->create(['currency'=>'CAD']); $type=AppointmentType::create(['organization_id'=>$org->getKey(),'name'=>'Pricing','slug'=>'pricing','visibility'=>'public','attendance_mode'=>'single','capacity'=>1,'duration_mode'=>'fixed','duration_unit'=>'hour','duration_value'=>1,'buffer_before_minutes'=>0,'buffer_after_minutes'=>0,'pricing_mode'=>'fixed','fixed_price_minor'=>10000,'is_active'=>true]);
  $q=$type->questions()->create(['type'=>'checkboxes','label'=>'Extras','is_active'=>true,'position'=>1]);
  $a=$q->options()->create(['label'=>'A','value'=>'a','position'=>1,'is_active'=>true,'pricing_adjustment_type'=>'percentage','pricing_percentage_bps'=>2000,'pricing_percentage_basis'=>'base_price']);
  $b=$q->options()->create(['label'=>'B','value'=>'b','position'=>2,'is_active'=>true,'pricing_adjustment_type'=>'percentage','pricing_percentage_bps'=>1000,'pricing_percentage_basis'=>'current_subtotal']);
  $quote=app(QuestionnairePricingService::class)->quote($type,1,[$q->uuid=>[$a->uuid,$b->uuid]]);
  $this->assertSame(13200,$quote->totalMinor); // 100 + 20 + 10% of 120
 }
 public function test_fixed_driving_distance_fee_is_added_to_questionnaire_quote(): void {
  $org=Organization::factory()->create(['currency'=>'CAD']); $type=AppointmentType::create(['organization_id'=>$org->getKey(),'name'=>'Distance pricing','slug'=>'distance-pricing','visibility'=>'public','attendance_mode'=>'single','capacity'=>1,'duration_mode'=>'fixed','duration_unit'=>'hour','duration_value'=>1,'buffer_before_minutes'=>0,'buffer_after_minutes'=>0,'pricing_mode'=>'fixed','fixed_price_minor'=>10000,'is_active'=>true]);
  $question=$type->questions()->create(['type'=>'address','label'=>'Service address','is_active'=>true,'position'=>1,'configuration'=>['distance_pricing'=>['enabled'=>true,'origin_address'=>'Private origin','unit'=>'kilometer','mode'=>'fixed','fixed_amount_minor'=>2500]]]);

  $quote=app(QuestionnairePricingService::class)->quote($type,1,[$question->uuid=>'Destination'],null,null,[$question->uuid=>12345]);

  $this->assertSame(12500,$quote->totalMinor);
  $this->assertSame('question_distance',$quote->lines[1]->sourceType);
  $this->assertSame('distance_fixed',$quote->lines[1]->lineType);
  $this->assertSame(12345,$quote->lines[1]->metadata['distance_meters']);
 }
}
