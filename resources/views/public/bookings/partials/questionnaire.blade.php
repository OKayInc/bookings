@php
$money=app(\App\Domain\Money\MoneyService::class);
$hasQuestions=$type->questions->where('is_active',true)->isNotEmpty();
$hasShortNoticeFees=$type->shortNoticeFeeRules->where('is_active',true)->isNotEmpty();
@endphp
@if($hasQuestions)
<div class="section-card" id="questionnaire-section">
<h2>Questionnaire</h2><p class="muted">Please provide the information requested for this appointment. Verified fields are checked by the server when you submit.</p>
@foreach($type->questions->where('is_active',true)->sortBy('position') as $question)
@php
$key='answers['.$question->uuid.']'; $oldKey='answers.'.$question->uuid; $cfg=$question->configuration ?? [];
$optionCharge=function($o) use($money,$organization){ if($o->pricing_adjustment_type->value==='fixed') return ' +'.$money->format((int)$o->pricing_amount_minor,$organization->currency); if($o->pricing_adjustment_type->value==='percentage') return ' +'.rtrim(rtrim(number_format($o->pricing_percentage_bps/100,2,'.',''),'0'),'.').'%'; return ''; };
@endphp
<div class="field questionnaire-question" data-question-type="{{ $question->type->value }}">
<label for="q_{{ $question->uuid }}">{{ $question->label }} @if($question->is_required)<span aria-label="required">*</span>@endif</label>
@if($question->description)<div class="muted">{{ $question->description }}</div>@endif
@switch($question->type->value)
@case('text') <input id="q_{{ $question->uuid }}" name="{{ $key }}" value="{{ old($oldKey) }}" placeholder="{{ $question->placeholder }}" @required($question->is_required)> @break
@case('textarea') <textarea id="q_{{ $question->uuid }}" name="{{ $key }}" placeholder="{{ $question->placeholder }}" @required($question->is_required)>{{ old($oldKey) }}</textarea> @break
@case('email') <input id="q_{{ $question->uuid }}" type="email" name="{{ $key }}" value="{{ old($oldKey) }}" placeholder="{{ $question->placeholder }}" @required($question->is_required)><div class="muted">The email domain will be verified.</div> @break
@case('telephone') <input id="q_{{ $question->uuid }}" type="tel" name="{{ $key }}" value="{{ old($oldKey) }}" placeholder="{{ $question->placeholder }}" @required($question->is_required)><div class="muted">Validated and stored in normalized international format.</div> @break
@case('address') <input id="q_{{ $question->uuid }}" name="{{ $key }}" value="{{ old($oldKey) }}" placeholder="{{ $question->placeholder ?: 'Street address, city, region, postal code, country' }}" @required($question->is_required)><div class="muted">Validated with Google Address Validation.</div> @break
@case('number') <input id="q_{{ $question->uuid }}" type="number" name="{{ $key }}" value="{{ old($oldKey) }}" @if(isset($cfg['min'])) min="{{ $cfg['min'] }}" @endif @if(isset($cfg['max'])) max="{{ $cfg['max'] }}" @endif step="{{ $cfg['step'] ?? ($question->pricing_adjustment_type->value==='none' ? 'any' : '1') }}" @required($question->is_required)>
@if($question->pricing_adjustment_type->value!=='none')<div class="muted">This answer affects the price. {{ $question->pricing_included_units }} unit(s) included.</div>@endif @break
@case('date') <input id="q_{{ $question->uuid }}" type="date" name="{{ $key }}" value="{{ old($oldKey) }}" @required($question->is_required)> @break
@case('time') <input id="q_{{ $question->uuid }}" type="time" name="{{ $key }}" value="{{ old($oldKey) }}" @required($question->is_required)> @break
@case('datetime') <input id="q_{{ $question->uuid }}" type="datetime-local" name="{{ $key }}" value="{{ old($oldKey) }}" @required($question->is_required)> @break
@case('select') <select id="q_{{ $question->uuid }}" name="{{ $key }}" @required($question->is_required)><option value="">Choose…</option>@foreach($question->options->where('is_active',true) as $option)<option value="{{ $option->uuid }}" @selected(old($oldKey)===$option->uuid)>{{ $option->label }}{{ $optionCharge($option) }}</option>@endforeach</select> @break
@case('radio') @foreach($question->options->where('is_active',true) as $option)<label class="inline-check"><input type="radio" name="{{ $key }}" value="{{ $option->uuid }}" @checked(old($oldKey)===$option->uuid) @required($question->is_required)> {{ $option->label }}{{ $optionCharge($option) }}</label>@endforeach @break
@case('checkboxes') @foreach($question->options->where('is_active',true) as $option)<label class="inline-check"><input type="checkbox" name="answers[{{ $question->uuid }}][]" value="{{ $option->uuid }}" @checked(in_array($option->uuid,(array)old($oldKey,[]),true))> {{ $option->label }}{{ $optionCharge($option) }}</label>@endforeach @break
@case('file') @php $ext=(array)data_get($cfg,'extensions',config('questionnaire.file_extensions')); @endphp <input id="q_{{ $question->uuid }}" type="file" name="answer_files[{{ $question->uuid }}][]" accept="{{ collect($ext)->map(fn($x)=>'.'.$x)->implode(',') }}" multiple @required($question->is_required)><div class="muted">Up to {{ data_get($cfg,'max_count',config('questionnaire.max_files_per_question')) }} file(s).</div> @break
@endswitch
</div>
@endforeach
</div>
@endif

@if($hasQuestions || $hasShortNoticeFees)
<div class="section-card" id="questionnaire-price-card">
<h2>Price</h2><div id="questionnaire-price-lines"></div><p><strong>Total: <span id="questionnaire-total">Calculating…</span></strong></p><p class="muted">The server recalculates the total when the booking is submitted.</p>
</div>
@endif
