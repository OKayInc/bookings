<?php
namespace App\Domain\Questionnaires;
use App\Domain\Appointments\AppointmentTypePricingService;
use App\Domain\Bookings\ShortNoticeFeeService;
use App\Enums\PricingAdjustmentType;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Models\AppointmentType;
use Carbon\CarbonImmutable;
class QuestionnairePricingService {
 public function __construct(
   private AppointmentTypePricingService $basePricing,
   private DrivingDistancePricingService $drivingDistancePricing,
   private ShortNoticeFeeService $shortNoticeFees,
   private QuestionVisibilityService $visibility,
 ) {}
 public function quote(
   AppointmentType $type,
   ?int $durationValue,
   array $answers,
   ?CarbonImmutable $startsAtUtc = null,
   ?CarbonImmutable $nowUtc = null,
   array $drivingDistancesMeters = [],
 ): QuestionnaireQuote {
   $visibleQuestions=$this->visibility->visibleQuestions($type,$answers);
   $base=$this->basePricing->priceForDuration($type,$durationValue,$type->duration_unit); $total=$base;
   $lines=[new QuestionnairePriceLine('appointment_type',$type->uuid,'Base appointment price','base','1',$base)];
   foreach ($visibleQuestions as $q) {
     $raw=$answers[$q->uuid] ?? null;
     if ($q->type->hasOptions()) {
       $ids=$q->type===QuestionType::Checkboxes ? (array)$raw : ($raw ? [(string)$raw] : []);
       foreach ($q->options->where('is_active',true)->filter(fn ($o) => in_array($o->uuid, $ids, true))->sortBy('position') as $opt) {
         $amount=$this->adjustment($opt->pricing_adjustment_type,$opt->pricing_amount_minor,$opt->pricing_percentage_bps,$opt->pricing_percentage_basis,$base,$total,1);
         if ($amount>0) { $total=$this->safeAdd($total,$amount); $lines[]=new QuestionnairePriceLine('question_option',$opt->uuid,$q->label.': '.$opt->label,$opt->pricing_adjustment_type->value,'1',$amount,['question_uuid'=>$q->uuid]); }
       }
     } elseif ($q->type===QuestionType::Number && $q->pricing_adjustment_type!==PricingAdjustmentType::None && $raw!==null && $raw!=='') {
       $qty=(int)$raw; $billable=max(0,$qty-(int)$q->pricing_included_units); if ($q->pricing_application_mode->value==='once') $billable=$billable>0?1:0;
       $amount=$this->adjustment($q->pricing_adjustment_type,$q->pricing_amount_minor,$q->pricing_percentage_bps,$q->pricing_percentage_basis,$base,$total,$billable);
       if ($amount>0) { $total=$this->safeAdd($total,$amount); $lines[]=new QuestionnairePriceLine('question',$q->uuid,$q->label,$q->pricing_adjustment_type->value,(string)$billable,$amount,['entered_quantity'=>$qty,'included_units'=>$q->pricing_included_units]); }
     } elseif ($q->type===QuestionType::Address && array_key_exists($q->uuid,$drivingDistancesMeters)) {
       $charge=$this->drivingDistancePricing->charge($q,(int)$drivingDistancesMeters[$q->uuid]);
       if ($charge!==null && $charge->amountMinor>0) {
         $total=$this->safeAdd($total,$charge->amountMinor);
         $lines[]=new QuestionnairePriceLine('question_distance',$q->uuid,$q->label.': driving distance ('.$charge->distanceLabel.')',$charge->lineType,'1',$charge->amountMinor,$charge->metadata);
       }
     }
   }
   if ($startsAtUtc !== null) {
     $charge=$this->shortNoticeFees->charge($type,$startsAtUtc,$total,$nowUtc);
     if ($charge !== null) {
       $total=$this->safeAdd($total,$charge->amountMinor);
       $lines[]=new QuestionnairePriceLine(
         'short_notice_fee',$charge->ruleUuid,$charge->label,$charge->lineType,'1',$charge->amountMinor,$charge->metadata,
       );
     }
   }
   return new QuestionnaireQuote($base,$total,$lines);
 }
 private function adjustment(PricingAdjustmentType $type, ?int $fixed, ?int $bps, PricingPercentageBasis $basis, int $base, int $subtotal, int $qty): int {
   if ($qty<=0 || $type===PricingAdjustmentType::None) return 0;
   if ($type===PricingAdjustmentType::Fixed) { $v=(int)$fixed; if ($v>0 && $qty>intdiv(PHP_INT_MAX,$v)) throw new \InvalidArgumentException('Questionnaire price is too large.'); return $v*$qty; }
   $basisAmount=$basis===PricingPercentageBasis::CurrentSubtotal?$subtotal:$base; $p=(int)$bps;
   if ($p > 0 && $basisAmount > intdiv(PHP_INT_MAX - 5000, $p)) throw new \InvalidArgumentException('Questionnaire percentage price is too large.');
   $one=intdiv(($basisAmount*$p)+5000,10000); if ($one>0 && $qty>intdiv(PHP_INT_MAX,$one)) throw new \InvalidArgumentException('Questionnaire price is too large.'); return $one*$qty;
 }
 private function safeAdd(int $a,int $b): int { if ($b>PHP_INT_MAX-$a) throw new \InvalidArgumentException('Questionnaire price is too large.'); return $a+$b; }
}
