<?php
namespace App\Domain\Questionnaires;
use App\Domain\Appointments\AppointmentTypePricingService;
use App\Domain\Appointments\AttendeePricingService;
use App\Domain\Money\MoneyService;
use App\Domain\Bookings\ShortNoticeFeeService;
use App\Domain\Tickets\TicketSeatPricingService;
use App\Domain\Resources\EquipmentPricingService;
use App\Domain\Resources\ResourceDepositService;
use App\Enums\PricingAdjustmentType;
use App\Enums\PricingMode;
use App\Enums\AttendeePricingMode;
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
   private AttendeePricingService $attendeePricing,
   private TicketSeatPricingService $ticketSeatPricing,
   private EquipmentPricingService $equipmentPricing,
   private ResourceDepositService $resourceDeposits,
   private MoneyService $money,
 ) {}
 public function quote(
   AppointmentType $type,
   ?int $durationValue,
   array $answers,
   ?CarbonImmutable $startsAtUtc = null,
   ?CarbonImmutable $nowUtc = null,
   array $drivingDistancesMeters = [],
   int $attendeeCount = 1,
   array $ticketSeats = [],
   ?array $equipmentResourceQuantities = null,
 ): QuestionnaireQuote {
   $visibleQuestions=$this->visibility->visibleQuestions($type,$answers);
   $base=$this->basePricing->priceForBooking($type,$durationValue,$type->duration_unit,$attendeeCount,$ticketSeats); $total=$base;
   $lines=[new QuestionnairePriceLine('appointment_type',$type->uuid,'Base appointment price','base','1',$base)];
   if ($type->pricing_mode===PricingMode::PerAttendee) {
     $mode=$type->attendee_pricing_mode??AttendeePricingMode::Flat;
     $lines=[];
     foreach ($this->attendeePricing->breakdown($type,$attendeeCount) as $line) {
       $label=$mode===AttendeePricingMode::Flat?'Per-attendee price':
         ($mode===AttendeePricingMode::Absolute?'Absolute range ':'Attendees ').$line['min_attendees'].'–'.$line['max_attendees'];
       $label.=' ('.$this->money->format($line['unit_amount_minor'],$type->organization->currency).' each)';
       $lines[]=new QuestionnairePriceLine('appointment_type',$type->uuid,$label,'base',(string)$line['quantity'],$line['amount_minor'],[
         'pricing_mode'=>'per_attendee','attendee_pricing_mode'=>$mode->value,'attendee_count'=>$attendeeCount,
         'unit_amount_minor'=>$line['unit_amount_minor'],'min_attendees'=>$line['min_attendees'],'max_attendees'=>$line['max_attendees'],
       ]);
     }
   }
   if ($type->ticketing_enabled) {
     foreach ($this->ticketSeatPricing->breakdown($ticketSeats) as $line) {
       $label='Seating fee: '.$line['label'].' ('.$this->money->format($line['unit_amount_minor'],$type->organization->currency).' each)';
       $lines[]=new QuestionnairePriceLine('ticket_seating',$type->uuid,$label,'seat_fee',(string)$line['quantity'],$line['amount_minor'],[
         'unit_amount_minor'=>$line['unit_amount_minor'],
       ]);
     }
   }
   foreach ($this->equipmentPricing->charges($type, $equipmentResourceQuantities) as $charge) {
     $total=$this->safeAdd($total,$charge->amountMinor);
     $label='Equipment rental: '.$charge->resourceName;
     if ($charge->mode===\App\Enums\EquipmentPricingMode::PerUnit) {
       $label.=' ('.$this->money->format((int)$charge->metadata['unit_amount_minor'],$type->organization->currency).' each)';
     }
     $lines[]=new QuestionnairePriceLine(
       'equipment_resource',$charge->resourceUuid,$label,'equipment_'.$charge->mode->value,(string)$charge->quantity,$charge->amountMinor,$charge->metadata,
     );
   }
   foreach ($visibleQuestions as $q) {
     $raw=$answers[$q->uuid] ?? null;
     if ($q->type->hasOptions()) {
       $ids=$q->type===QuestionType::Checkboxes ? (array)$raw : ($raw ? [(string)$raw] : []);
       foreach ($q->options->where('is_active',true)->filter(fn ($o) => in_array($o->uuid, $ids, true))->sort(fn ($a,$b): int => ($a->position <=> $b->position) ?: strcasecmp($a->label,$b->label) ?: strcmp($a->label,$b->label)) as $opt) {
         $amount=$this->adjustment($opt->pricing_adjustment_type,$opt->pricing_amount_minor,$opt->pricing_percentage_bps,$opt->pricing_percentage_basis,$base,$total,1);
         if ($amount>0) { $total=$this->safeAdd($total,$amount); $lines[]=new QuestionnairePriceLine('question_option',$opt->uuid,$q->label.': '.$opt->label,$opt->pricing_adjustment_type->value,'1',$amount,['question_uuid'=>$q->uuid]); }
       }
     } elseif ($q->type===QuestionType::Number && $q->pricing_adjustment_type!==PricingAdjustmentType::None && $raw!==null && $raw!=='') {
       if ($q->pricing_adjustment_type===PricingAdjustmentType::Rate) {
         [$amount,$billable]=$this->rateAdjustment((int)$q->pricing_amount_minor,$raw,(int)$q->pricing_included_units);
         $metadata=['entered_answer'=>(string)$raw,'included_units'=>$q->pricing_included_units,'rate_amount_minor'=>(int)$q->pricing_amount_minor];
       } else {
         $qty=(int)$raw; $billable=max(0,$qty-(int)$q->pricing_included_units); if ($q->pricing_application_mode->value==='once') $billable=$billable>0?1:0;
         $amount=$this->adjustment($q->pricing_adjustment_type,$q->pricing_amount_minor,$q->pricing_percentage_bps,$q->pricing_percentage_basis,$base,$total,$billable);
         $metadata=['entered_quantity'=>$qty,'included_units'=>$q->pricing_included_units];
       }
       if ($amount>0) { $total=$this->safeAdd($total,$amount); $lines[]=new QuestionnairePriceLine('question',$q->uuid,$q->label,$q->pricing_adjustment_type->value,(string)$billable,$amount,$metadata); }
     } elseif ($q->type===QuestionType::Address && array_key_exists($q->uuid,$drivingDistancesMeters)) {
       $charge=$this->drivingDistancePricing->charge($q,(int)$drivingDistancesMeters[$q->uuid]);
       if ($charge!==null && $charge->amountMinor>0) {
         $total=$this->safeAdd($total,$charge->amountMinor);
         $quantity=$charge->lineType==='distance_fallback'?(string)($charge->metadata['fallback_blocks']??1):'1';
         $lines[]=new QuestionnairePriceLine('question_distance',$q->uuid,$q->label.': driving distance ('.$charge->distanceLabel.')',$charge->lineType,$quantity,$charge->amountMinor,$charge->metadata);
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
   // Deposits are refundable security, not service revenue. Add them only
   // after every service-price adjustment so percentage and short-notice fees
   // never use a deposit as their basis.
   foreach ($this->resourceDeposits->charges($type, $answers, $equipmentResourceQuantities) as $charge) {
     $total=$this->safeAdd($total,$charge->amountMinor);
     $label='Refundable deposit: '.$charge->resourceName;
     if ($charge->quantity > 1) {
       $label.=' ('.$this->money->format($charge->unitAmountMinor,$type->organization->currency).' each)';
     }
     $lines[]=new QuestionnairePriceLine(
       'resource_deposit',$charge->resourceUuid,$label,'resource_deposit',(string)$charge->quantity,$charge->amountMinor,[
         'resource_name'=>$charge->resourceName,
         'unit_amount_minor'=>$charge->unitAmountMinor,
         'configuration_source'=>$charge->configurationSource,
         'question_uuid'=>$charge->questionUuid,
         'question_label'=>$charge->questionLabel,
         'refundable'=>true,
       ],
     );
   }
   return new QuestionnaireQuote($base,$total,$lines);
 }
 private function adjustment(PricingAdjustmentType $type, ?int $fixed, ?int $bps, PricingPercentageBasis $basis, int $base, int $subtotal, int $qty): int {
   if ($qty<=0 || $type===PricingAdjustmentType::None) return 0;
   if ($type===PricingAdjustmentType::Rate) throw new \InvalidArgumentException('Answer rates are available only on numeric questions.');
   if ($type===PricingAdjustmentType::Fixed) { $v=(int)$fixed; if ($v>0 && $qty>intdiv(PHP_INT_MAX,$v)) throw new \InvalidArgumentException('Questionnaire price is too large.'); return $v*$qty; }
   $basisAmount=$basis===PricingPercentageBasis::CurrentSubtotal?$subtotal:$base; $p=(int)$bps;
   if ($p > 0 && $basisAmount > intdiv(PHP_INT_MAX - 5000, $p)) throw new \InvalidArgumentException('Questionnaire percentage price is too large.');
   $one=intdiv(($basisAmount*$p)+5000,10000); if ($one>0 && $qty>intdiv(PHP_INT_MAX,$one)) throw new \InvalidArgumentException('Questionnaire price is too large.'); return $one*$qty;
 }
 private function rateAdjustment(int $rateMinor, mixed $raw, int $includedUnits): array {
   if (!is_numeric($raw)) throw new \InvalidArgumentException('The numeric pricing answer is invalid.');
   $answer=(float)$raw;
   if (!is_finite($answer)) throw new \InvalidArgumentException('The numeric pricing answer is invalid.');
   $billable=max(0.0,$answer-$includedUnits);
   if ($rateMinor<=0 || $billable<=0) return [0,$billable];
   if ($billable>PHP_INT_MAX/$rateMinor) throw new \InvalidArgumentException('Questionnaire price is too large.');
   return [(int)round($billable*$rateMinor,0,PHP_ROUND_HALF_UP),$billable];
 }
 private function safeAdd(int $a,int $b): int { if ($b>PHP_INT_MAX-$a) throw new \InvalidArgumentException('Questionnaire price is too large.'); return $a+$b; }
}
