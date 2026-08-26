<?php
namespace App\Domain\Questionnaires;
use App\Enums\QuestionType;
use App\Models\AppointmentType;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
class QuestionnaireSubmissionService {
 public function __construct(private QuestionnairePricingService $pricing, private EmailDomainValidator $emails, private PhoneValidationService $phones, private AddressValidationService $addresses) {}
 public function quote(AppointmentType $type, ?int $duration, array $answers): QuestionnaireQuote { return $this->pricing->quote($type,$duration,$answers); }
 public function validateForBooking(Request $request, AppointmentType $type, ?int $duration): QuestionnaireSubmission {
   $type->loadMissing(['questions.options']); $rules=[];
   foreach ($type->questions->where('is_active',true) as $q) {
     $key='answers.'.$q->uuid; $required=$q->is_required?'required':'nullable';
     $rules[$key]=match($q->type) {
       QuestionType::Text,QuestionType::Textarea => [$required,'string','max:'.config('questionnaire.max_text_length',20000)],
       QuestionType::Email => [$required,'email:rfc','max:254'],
       QuestionType::Telephone,QuestionType::Address => [$required,'string','max:1000'],
       QuestionType::Date => [$required,'date_format:Y-m-d'], QuestionType::Time => [$required,'date_format:H:i'], QuestionType::DateTime => [$required,'date_format:Y-m-d\\TH:i'],
       QuestionType::Number => array_values(array_filter([$required,$q->pricing_adjustment_type->value!=='none'?'integer':'numeric',isset($q->configuration['min'])?'min:'.$q->configuration['min']:null,isset($q->configuration['max'])?'max:'.$q->configuration['max']:null])),
       QuestionType::Radio,QuestionType::Select => [$required,Rule::in($q->options->where('is_active',true)->pluck('uuid')->all())],
       QuestionType::Checkboxes => [$required,'array',...($q->is_required?['min:1']:[])],
       QuestionType::File => ['nullable'],
     };
     if ($q->type===QuestionType::Checkboxes) $rules[$key.'.*']=['string','distinct',Rule::in($q->options->where('is_active',true)->pluck('uuid')->all())];
     if ($q->type===QuestionType::File) {
       $fk='answer_files.'.$q->uuid; $max=(int)data_get($q->configuration,'max_count',config('questionnaire.max_files_per_question',20)); $ext=(array)data_get($q->configuration,'extensions',config('questionnaire.file_extensions')); $kb=(int)data_get($q->configuration,'max_kilobytes',config('questionnaire.max_file_kilobytes'));
       $rules[$fk]=[$q->is_required?'required':'nullable','array','max:'.$max,...($q->is_required?['min:1']:[])]; $rules[$fk.'.*']=['file','mimes:'.implode(',',$ext),'max:'.$kb];
     }
   }
   $validated=Validator::make($request->all(),$rules)->validate(); $raw=(array)($validated['answers']??[]); $fileBag=$request->file('answer_files',[]); $answers=[];
   foreach ($type->questions->where('is_active',true)->sortBy('position') as $q) {
     $value=$raw[$q->uuid]??null; $normalized=null; $files=[];
     if ($q->type->hasOptions()) {
       $ids=$q->type===QuestionType::Checkboxes?(array)$value:($value?[(string)$value]:[]); $selected=$q->options->where('is_active',true)->filter(fn ($o) => in_array($o->uuid, $ids, true))->sortBy('position');
       $value=$q->type===QuestionType::Checkboxes?$selected->map(fn($o)=>['uuid'=>$o->uuid,'value'=>$o->value,'label'=>$o->label])->values()->all():($selected->first()?['uuid'=>$selected->first()->uuid,'value'=>$selected->first()->value,'label'=>$selected->first()->label]:null);
     } elseif ($q->type===QuestionType::Email && $value) { if(!$this->emails->exists($value)) throw ValidationException::withMessages(['answers.'.$q->uuid=>'The email domain does not appear to exist.']); $normalized=['email'=>strtolower(trim($value))]; }
     elseif ($q->type===QuestionType::Telephone && $value) { try{$normalized=['e164'=>$this->phones->validateAndNormalize($value,data_get($q->configuration,'region'))];}catch(RuntimeException $e){throw ValidationException::withMessages(['answers.'.$q->uuid=>$e->getMessage()]);} }
     elseif ($q->type===QuestionType::Address && $value) { try{$normalized=$this->addresses->validate($value,data_get($q->configuration,'region'));}catch(RuntimeException $e){throw ValidationException::withMessages(['answers.'.$q->uuid=>$e->getMessage()]);} }
     elseif ($q->type===QuestionType::File) { $files=array_values((array)($fileBag[$q->uuid]??[])); $value=array_map(fn(UploadedFile $f)=>$f->getClientOriginalName(),$files); }
     $answers[]=['question'=>$q,'value'=>$value,'normalized'=>$normalized,'files'=>$files];
   }
   // Pricing uses raw option UUIDs / number values rather than display snapshots.
   return new QuestionnaireSubmission($answers,$this->pricing->quote($type,$duration,$raw));
 }
}
