<?php
namespace App\Http\Controllers;
use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\PercentageService;
use App\Domain\Questionnaires\PhoneValidationService;
use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Http\Requests\StoreAppointmentQuestionRequest;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\QuestionOption;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
class AppointmentQuestionController extends Controller {
 public function index(AppointmentType $appointmentType, OrganizationContext $context): View { $this->guard($appointmentType,$context); $appointmentType->load(['questions.options']); return view('questionnaire.index',compact('appointmentType')); }
 public function create(AppointmentType $appointmentType, OrganizationContext $context, PhoneValidationService $phones): View { $this->guard($appointmentType,$context); return view('questionnaire.create',$this->formData($appointmentType,null,$context,$phones)); }
 public function store(StoreAppointmentQuestionRequest $request, AppointmentType $appointmentType, OrganizationContext $context, MoneyService $money, PercentageService $percent): RedirectResponse {
  $this->guard($appointmentType,$context); $data=$request->validated(); try { DB::transaction(function () use ($appointmentType,$data,$request,$context,$money,$percent): void { $q=$appointmentType->questions()->create($this->questionData($data,$request,$context,$money,$percent,$appointmentType)); $this->syncOptions($q,$data,$context,$money,$percent); }); } catch (InvalidArgumentException $e) { throw ValidationException::withMessages(['pricing'=>$e->getMessage()]); } return redirect()->route('appointment-types.questionnaire.index',$appointmentType)->with('success','Question added.');
 }
 public function edit(AppointmentType $appointmentType, AppointmentQuestion $question, OrganizationContext $context, PhoneValidationService $phones): View { $this->guardQuestion($appointmentType,$question,$context); $question->load('options'); return view('questionnaire.edit',$this->formData($appointmentType,$question,$context,$phones)); }
 public function update(StoreAppointmentQuestionRequest $request, AppointmentType $appointmentType, AppointmentQuestion $question, OrganizationContext $context, MoneyService $money, PercentageService $percent): RedirectResponse {
  $this->guardQuestion($appointmentType,$question,$context); $data=$request->validated(); try { DB::transaction(function () use ($question,$data,$request,$context,$money,$percent,$appointmentType): void { $question->update($this->questionData($data,$request,$context,$money,$percent,$appointmentType)); $this->syncOptions($question,$data,$context,$money,$percent); }); } catch (InvalidArgumentException $e) { throw ValidationException::withMessages(['pricing'=>$e->getMessage()]); } return redirect()->route('appointment-types.questionnaire.index',$appointmentType)->with('success','Question updated.');
 }
 public function destroy(AppointmentType $appointmentType, AppointmentQuestion $question, OrganizationContext $context): RedirectResponse {
  $this->guardQuestion($appointmentType,$question,$context); if ($question->answers()->exists()) { $question->update(['is_active'=>false]); return back()->with('success','Question disabled because historical booking answers exist.'); } $question->delete(); return back()->with('success','Question deleted.');
 }
 private function questionData(array $d,$request,OrganizationContext $context,MoneyService $money,PercentageService $percent,AppointmentType $type): array {
   $qt=QuestionType::from($d['type']); $pricing=PricingAdjustmentType::tryFrom($d['pricing_adjustment_type']??'none')??PricingAdjustmentType::None;
   if ($qt!==QuestionType::Number) $pricing=PricingAdjustmentType::None;
   $config=[];
   foreach (['number_min','number_max','number_step'] as $f) if (($d[$f]??'')!=='') $config[str_replace('number_','',$f)]=$d[$f];
   if ($qt===QuestionType::File) { $ext=array_values(array_filter(array_map(fn($x)=>strtolower(trim($x)),explode(',',$d['file_extensions']??implode(',',config('questionnaire.file_extensions')))), fn ($x) => preg_match('/^[a-z0-9]{1,12}$/', $x))); if ($ext === []) $ext = config('questionnaire.file_extensions'); $config['extensions']=$ext; $config['max_count']=(int)($d['file_max_count']??config('questionnaire.max_files_per_question',20)); $config['max_kilobytes']=(int)($d['file_max_kilobytes']??config('questionnaire.max_file_kilobytes',20480)); }
   if ($qt===QuestionType::Telephone) $config['region']=strtoupper($d['phone_region']??config('questionnaire.default_phone_region','CA'));
   if ($qt===QuestionType::Address && !empty($d['address_region'])) $config['region']=strtoupper($d['address_region']);
   return ['type'=>$qt->value,'label'=>$d['label'],'description'=>$d['description']??null,'placeholder'=>$d['placeholder']??null,'is_required'=>$request->boolean('is_required'),'is_active'=>$request->boolean('is_active'),'position'=>(int)($d['position']??($type->questions()->max('position')+1 ?: 1)),'configuration'=>$config ?: null,
   'pricing_adjustment_type'=>$pricing->value,'pricing_application_mode'=>($d['pricing_application_mode']??PricingApplicationMode::PerUnit->value),'pricing_amount_minor'=>$pricing===PricingAdjustmentType::Fixed?$money->parse($d['pricing_amount']??'0',$context->organization()->currency):null,'pricing_percentage_bps'=>$pricing===PricingAdjustmentType::Percentage?$percent->parseToBasisPoints($d['pricing_percentage']??'0'):null,'pricing_percentage_basis'=>$d['pricing_percentage_basis']??PricingPercentageBasis::BasePrice->value,'pricing_included_units'=>(int)($d['pricing_included_units']??0)];
 }
 private function syncOptions(AppointmentQuestion $q,array $d,OrganizationContext $context,MoneyService $money,PercentageService $percent): void {
   if (!$q->type->hasOptions()) { $q->options()->delete(); return; }
   $q->options()->delete(); $used=[];
   foreach (array_values($d['options']??[]) as $i=>$o) { $label=trim($o['label']); $base=Str::slug($o['value']??$label,'_') ?: 'option_'.($i+1); $value=$base; $n=2; while(in_array($value,$used,true))$value=$base.'_'.$n++; $used[]=$value; $pt=PricingAdjustmentType::tryFrom($o['pricing_adjustment_type']??'none')??PricingAdjustmentType::None;
     $q->options()->create(['label'=>$label,'value'=>$value,'position'=>$i+1,'is_active'=>true,'pricing_adjustment_type'=>$pt->value,'pricing_amount_minor'=>$pt===PricingAdjustmentType::Fixed?$money->parse($o['pricing_amount']??'0',$context->organization()->currency):null,'pricing_percentage_bps'=>$pt===PricingAdjustmentType::Percentage?$percent->parseToBasisPoints($o['pricing_percentage']??'0'):null,'pricing_percentage_basis'=>$o['pricing_percentage_basis']??PricingPercentageBasis::BasePrice->value]);
   }
 }
 private function formData(AppointmentType $type,?AppointmentQuestion $question,OrganizationContext $context,PhoneValidationService $phones): array { return ['appointmentType'=>$type,'question'=>$question,'questionTypes'=>QuestionType::cases(),'pricingTypes'=>PricingAdjustmentType::cases(),'pricingModes'=>PricingApplicationMode::cases(),'percentageBases'=>PricingPercentageBasis::cases(),'organization'=>$context->organization(),'phoneRegions'=>$phones->supportedRegions()]; }
 private function guard(AppointmentType $t,OrganizationContext $c): void { abort_unless(hash_equals($t->organization_id,$c->organization()->getKey()),404); $this->authorize('manage',$t); }
 private function guardQuestion(AppointmentType $t,AppointmentQuestion $q,OrganizationContext $c): void { $this->guard($t,$c); abort_unless(hash_equals($q->appointment_type_id,$t->getKey()),404); }
}
