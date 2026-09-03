@php
$money=app(\App\Domain\Money\MoneyService::class); $pct=app(\App\Domain\Questionnaires\PercentageService::class);
$qType=old('type',$question?->type?->value ?? 'text');
@endphp
<div class="section-card"><h2>Question</h2>
<div class="row"><div class="field"><label>Type</label><select id="question-type" name="type" required>@foreach($questionTypes as $type)<option value="{{ $type->value }}" @selected($qType===$type->value)>{{ $type->label() }}</option>@endforeach</select></div><div class="field"><label>Position</label><input type="number" min="1" name="position" value="{{ old('position',$question?->position) }}" placeholder="Automatic"></div></div>
<div class="field"><label>Question / label</label><input name="label" required maxlength="255" value="{{ old('label',$question?->label) }}"></div>
<div class="field"><label>Description / help text</label><textarea name="description">{{ old('description',$question?->description) }}</textarea></div>
<div class="field"><label>Placeholder (optional)</label><input name="placeholder" maxlength="255" value="{{ old('placeholder',$question?->placeholder) }}"></div>
<div class="row"><label class="inline-check"><input type="checkbox" name="is_required" value="1" @checked(old('is_required',$question?->is_required ?? false))> Required</label><label class="inline-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$question?->is_active ?? true))> Active</label></div>
@if($question?->reusableQuestion)
<div class="field"><label class="inline-check"><input type="checkbox" name="update_reusable_question" value="1" @checked(old('update_reusable_question',false))> Update the reusable template for future attachments</label><div class="muted">Existing copies on other appointment types will not change.</div></div>
@endif
</div>

@php
    $storedVisibilityConditions = $question?->visibilityConditions?->map(fn ($condition): array => [
        'boolean_operator' => $condition->boolean_operator,
        'source_question_uuid' => $condition->sourceQuestion?->uuid,
        'question_option_uuids' => $condition->expectedOptionUuids(),
    ])->values()->all() ?? [];
    $visibilityRows = old('visibility_conditions', $storedVisibilityConditions);
    $dependencyPayload = $dependencyQuestions->map(fn ($candidate): array => [
        'uuid' => $candidate->uuid,
        'label' => $candidate->label,
        'position' => $candidate->position,
        'accepts_multiple_answers' => $candidate->type->acceptsMultipleAnswers(),
        'options' => $candidate->options->where('is_active', true)->map(fn ($option): array => [
            'uuid' => $option->uuid,
            'label' => $option->label,
        ])->values()->all(),
    ])->values()->all();
@endphp
<div class="section-card">
<h2>Display dependencies</h2>
<p class="muted">Optional. Show this question only when the selected answers match. A checkbox condition can accept several answers and matches when any one is selected. AND conditions stay in the same group; OR starts an alternative group. Only earlier checkbox, radio, and select questions can be used, which keeps chained questionnaires predictable and cycle-free.</p>
<div id="visibility-condition-rows">
@foreach($visibilityRows as $index => $condition)
@php
    $sourceUuid = $condition['source_question_uuid'] ?? '';
    $optionUuids = array_values((array) ($condition['question_option_uuids'] ?? array_filter([$condition['question_option_uuid'] ?? null])));
    $sourcePayload = collect($dependencyPayload)->firstWhere('uuid', $sourceUuid);
    $acceptsMultipleAnswers = (bool) ($sourcePayload['accepts_multiple_answers'] ?? false);
@endphp
<div class="card compact visibility-condition-row">
 <div class="row three">
  <div class="field visibility-connector-field"><label class="visibility-connector-label">Join with</label><select class="visibility-operator" name="visibility_conditions[{{ $index }}][boolean_operator]"><option value="and" @selected(($condition['boolean_operator'] ?? 'and') === 'and')>AND</option><option value="or" @selected(($condition['boolean_operator'] ?? 'and') === 'or')>OR</option></select></div>
  <div class="field"><label>Earlier question</label><select class="visibility-source" name="visibility_conditions[{{ $index }}][source_question_uuid]" required><option value="">Choose a question…</option>@foreach($dependencyPayload as $candidate)<option value="{{ $candidate['uuid'] }}" @selected($sourceUuid === $candidate['uuid'])>#{{ $candidate['position'] }} · {{ $candidate['label'] }}</option>@endforeach</select></div>
  <div class="field"><label class="visibility-answer-label">{{ $acceptsMultipleAnswers ? 'Has any answer' : 'Has answer' }}</label><select class="visibility-option" name="visibility_conditions[{{ $index }}][question_option_uuids][]" @if($acceptsMultipleAnswers) multiple size="{{ min(8, max(2, count((array) ($sourcePayload['options'] ?? [])))) }}" @endif required>@unless($acceptsMultipleAnswers)<option value="">Choose an answer…</option>@endunless @foreach((array)($sourcePayload['options'] ?? []) as $candidateOption)<option value="{{ $candidateOption['uuid'] }}" @selected(in_array($candidateOption['uuid'], $optionUuids, true))>{{ $candidateOption['label'] }}</option>@endforeach</select><div class="muted visibility-answer-help" @if(!$acceptsMultipleAnswers) hidden @endif>Select one or more answers. Matching any selected answer shows this condition.</div></div>
 </div>
 <button type="button" class="btn btn-danger remove-visibility-condition">Remove condition</button>
</div>
@endforeach
</div>
@if($dependencyPayload !== [])
<button type="button" id="add-visibility-condition" class="btn">Add display condition</button>
@else
<p class="muted">Add an earlier checkbox, radio, or select question before configuring a dependency.</p>
@endif
</div>

<div class="section-card conditional" data-types="number"><h2>Number settings</h2><div class="row three"><div class="field"><label>Minimum</label><input type="number" step="any" name="number_min" value="{{ old('number_min',data_get($question?->configuration,'min')) }}"></div><div class="field"><label>Maximum</label><input type="number" step="any" name="number_max" value="{{ old('number_max',data_get($question?->configuration,'max')) }}"></div><div class="field"><label>Step</label><input type="number" step="any" min="0.0001" name="number_step" value="{{ old('number_step',data_get($question?->configuration,'step',1)) }}"></div></div></div>
<div class="section-card conditional" data-types="file"><h2>File settings</h2><div class="row three"><div class="field"><label>Extensions, comma separated</label><input name="file_extensions" value="{{ old('file_extensions',implode(',',data_get($question?->configuration,'extensions',config('questionnaire.file_extensions')))) }}"></div><div class="field"><label>Maximum files</label><input type="number" min="1" name="file_max_count" value="{{ old('file_max_count',data_get($question?->configuration,'max_count',config('questionnaire.max_files_per_question'))) }}"></div><div class="field"><label>Maximum KiB per file</label><input type="number" min="1" name="file_max_kilobytes" value="{{ old('file_max_kilobytes',data_get($question?->configuration,'max_kilobytes',config('questionnaire.max_file_kilobytes'))) }}"></div></div></div>
<div class="section-card conditional" data-types="telephone"><h2>Telephone validation</h2><div class="field"><label>Default country/region</label><select name="phone_region">@foreach($phoneRegions as $region)<option value="{{ $region }}" @selected(old('phone_region',data_get($question?->configuration,'region',config('questionnaire.default_phone_region'))) === $region)>{{ $region }}</option>@endforeach</select><div class="muted">International +country-code numbers work regardless of this default.</div></div></div>
<div class="section-card conditional" data-types="address"><h2>Address validation</h2><div class="field"><label>Country/region code (optional)</label><input name="address_region" maxlength="2" value="{{ old('address_region',data_get($question?->configuration,'region')) }}" placeholder="CA"><div class="muted">Validated server-side with Google Address Validation API.</div></div></div>

@php
    $distanceConfiguration = (array) data_get($question?->configuration, 'distance_pricing', []);
    $distancePricingEnabled = old('distance_pricing_enabled', $distanceConfiguration['enabled'] ?? false);
    $distancePricingMode = old('distance_pricing_mode', $distanceConfiguration['mode'] ?? 'fixed');
    $distanceUnit = old('distance_unit', $distanceConfiguration['unit'] ?? 'kilometer');
    $storedDistanceRanges = [];
    foreach ((array) ($distanceConfiguration['ranges'] ?? []) as $range) {
        $storedDistanceRanges[] = [
            'minimum' => $range['minimum'] ?? 0,
            'maximum' => $range['maximum'] ?? '',
            'amount' => $money->decimal((int) ($range['amount_minor'] ?? 0), $organization->currency),
        ];
    }
    $distanceRangeRows = old('distance_ranges', $storedDistanceRanges ?: [['minimum' => 0, 'maximum' => '', 'amount' => '']]);
    $distanceFixedAmount = old(
        'distance_fixed_amount',
        isset($distanceConfiguration['fixed_amount_minor'])
            ? $money->decimal((int) $distanceConfiguration['fixed_amount_minor'], $organization->currency)
            : '',
    );
    $distanceFallbackIncrement = old(
        'distance_fallback_increment',
        data_get($distanceConfiguration, 'fallback.increment', ''),
    );
    $distanceFallbackAmount = old(
        'distance_fallback_amount',
        data_get($distanceConfiguration, 'fallback.amount_minor') === null
            ? ''
            : $money->decimal((int) data_get($distanceConfiguration, 'fallback.amount_minor'), $organization->currency),
    );
@endphp
<div class="section-card conditional" data-types="address">
<h2>Driving-distance pricing</h2>
<label class="inline-check"><input id="distance-pricing-enabled" type="checkbox" name="distance_pricing_enabled" value="1" @checked($distancePricingEnabled)> Add a fee calculated from the answer's driving distance</label>
<div class="muted">Point 0 is private configuration. It is used by the server for routing and is never shown to the client.</div>
<div id="distance-pricing-fields" style="margin-top: 1rem;">
<div class="field"><label for="distance-origin-address">Private point 0 / origin address</label><input id="distance-origin-address" name="distance_origin_address" maxlength="1000" value="{{ old('distance_origin_address', $distanceConfiguration['origin_address'] ?? '') }}" placeholder="Full street address, city, region, postal code, country"><div class="muted">Google Routes calculates a driving route from this origin to the client's validated answer.</div></div>
<div class="row"><div class="field"><label for="distance-unit">Distance unit</label><select id="distance-unit" name="distance_unit"><option value="kilometer" @selected($distanceUnit === 'kilometer')>Kilometers</option><option value="mile" @selected($distanceUnit === 'mile')>Miles</option></select></div><div class="field"><label for="distance-pricing-mode">Fee method</label><select id="distance-pricing-mode" name="distance_pricing_mode"><option value="fixed" @selected($distancePricingMode === 'fixed')>Fixed fee for any route</option><option value="range" @selected($distancePricingMode === 'range')>Fee by distance range with per-distance fallback</option></select></div></div>
<div id="distance-fixed-fields" class="field"><label for="distance-fixed-amount">Fixed fee ({{ $organization->currency }})</label><input id="distance-fixed-amount" inputmode="decimal" name="distance_fixed_amount" value="{{ $distanceFixedAmount }}" placeholder="25.00"></div>
<div id="distance-range-fields">
<p class="muted">The minimum is inclusive and the maximum is exclusive. Leave maximum blank for an open-ended final range. Ranges cannot overlap. Any gap or uncovered distance uses the required fallback below.</p>
<div id="distance-range-rows">
@foreach($distanceRangeRows as $index => $range)
<div class="card compact distance-range-row" data-index="{{ $index }}"><div class="row three"><div class="field"><label>Minimum</label><input type="number" min="0" step="any" name="distance_ranges[{{ $index }}][minimum]" value="{{ $range['minimum'] ?? 0 }}"></div><div class="field"><label>Maximum (optional)</label><input type="number" min="0" step="any" name="distance_ranges[{{ $index }}][maximum]" value="{{ $range['maximum'] ?? '' }}"></div><div class="field"><label>Fee ({{ $organization->currency }})</label><input inputmode="decimal" name="distance_ranges[{{ $index }}][amount]" value="{{ $range['amount'] ?? '' }}"></div></div><button type="button" class="btn btn-danger remove-distance-range">Remove range</button></div>
@endforeach
</div>
<button type="button" id="add-distance-range" class="btn">Add distance range</button>
<div class="card compact" style="margin-top: 1rem;"><h3>Unmatched-distance fallback</h3><p class="muted">For a distance not covered by a range, charge the fee for every started increment. Example: 12 <span data-distance-unit-short>{{ $distanceUnit === 'mile' ? 'mi' : 'km' }}</span> with a 5 <span data-distance-unit-short>{{ $distanceUnit === 'mile' ? 'mi' : 'km' }}</span> increment uses 3 increments.</p><div class="row"><div class="field"><label for="distance-fallback-increment">Distance per increment (<span data-distance-unit-short>{{ $distanceUnit === 'mile' ? 'mi' : 'km' }}</span>)</label><input id="distance-fallback-increment" type="number" min="0.001" step="any" name="distance_fallback_increment" value="{{ $distanceFallbackIncrement }}" placeholder="5"></div><div class="field"><label for="distance-fallback-amount">Fee per increment ({{ $organization->currency }})</label><input id="distance-fallback-amount" inputmode="decimal" name="distance_fallback_amount" value="{{ $distanceFallbackAmount }}" placeholder="10.00"></div></div></div>
</div>
</div>
</div>

@include('questionnaire.partials.numeric-constraints')

<div class="section-card conditional" data-types="number"><h2>Number-field extra charge</h2>
<div class="row three"><div class="field"><label>Charge type</label><select id="question-pricing-type" name="pricing_adjustment_type">@foreach($pricingTypes as $pt)<option value="{{ $pt->value }}" @selected(old('pricing_adjustment_type',$question?->pricing_adjustment_type?->value ?? 'none')===$pt->value)>{{ $pt->label() }}</option>@endforeach</select></div><div class="field" id="question-pricing-application-field"><label>Apply</label><select id="question-pricing-application" name="pricing_application_mode">@foreach($pricingModes as $pm)<option value="{{ $pm->value }}" @selected(old('pricing_application_mode',$question?->pricing_application_mode?->value ?? 'per_unit')===$pm->value)>{{ ucwords(str_replace('_',' ',$pm->value)) }}</option>@endforeach</select></div><div class="field"><label>Included units</label><input type="number" min="0" name="pricing_included_units" value="{{ old('pricing_included_units',$question?->pricing_included_units ?? 0) }}"></div></div>
<div class="row"><div class="field" id="question-pricing-amount-field"><label id="question-pricing-amount-label">Fixed charge ({{ $organization->currency }})</label><input name="pricing_amount" inputmode="decimal" value="{{ old('pricing_amount',$question?->pricing_amount_minor===null?'':$money->decimal($question->pricing_amount_minor,$organization->currency)) }}"></div><div class="field" id="question-pricing-percentage-field"><label>Percentage</label><input name="pricing_percentage" value="{{ old('pricing_percentage',$pct->display($question?->pricing_percentage_bps)) }}" placeholder="25"><label>Basis</label><select name="pricing_percentage_basis">@foreach($percentageBases as $pb)<option value="{{ $pb->value }}" @selected(old('pricing_percentage_basis',$question?->pricing_percentage_basis?->value ?? 'base_price')===$pb->value)>{{ ucwords(str_replace('_',' ',$pb->value)) }}</option>@endforeach</select></div></div>
<div class="muted">Answer × rate calculates <code>max(0, answer − included units) × rate</code>. Decimal answers are supported and the result is rounded to the nearest currency minor unit.</div></div>

<div class="section-card conditional" data-types="checkboxes,radio,select"><h2>Choices and option charges</h2><p class="muted">Each choice can add a fixed amount or percentage. Percentage basis can be the original appointment price or the running subtotal.</p>
<div id="option-rows">
@php $oldOptions=old('options'); $rows=$oldOptions!==null?$oldOptions:($question?->options?->map(fn($o)=>['uuid'=>$o->uuid,'label'=>$o->label,'value'=>$o->value,'position'=>$o->position,'pricing_adjustment_type'=>$o->pricing_adjustment_type->value,'pricing_amount'=>$o->pricing_amount_minor===null?'':$money->decimal($o->pricing_amount_minor,$organization->currency),'pricing_percentage'=>$pct->display($o->pricing_percentage_bps),'pricing_percentage_basis'=>$o->pricing_percentage_basis->value])->all() ?? []); @endphp
@foreach($rows as $i=>$o)<div class="card compact option-row">@if(!empty($o['uuid']))<input type="hidden" name="options[{{ $i }}][uuid]" value="{{ $o['uuid'] }}">@endif<div class="row three"><div class="field"><label>Label</label><input name="options[{{ $i }}][label]" value="{{ $o['label'] ?? '' }}"></div><div class="field"><label>Value (optional)</label><input name="options[{{ $i }}][value]" value="{{ $o['value'] ?? '' }}"></div><div class="field"><label>Order</label><input type="number" min="0" max="100000" name="options[{{ $i }}][position]" value="{{ $o['position'] ?? '' }}" placeholder="0"><div class="muted">Blank is 0; ties use label order.</div></div></div><div class="row three"><div class="field"><label>Charge type</label><select name="options[{{ $i }}][pricing_adjustment_type]">@foreach($optionPricingTypes as $pt)<option value="{{ $pt->value }}" @selected(($o['pricing_adjustment_type']??'none')===$pt->value)>{{ $pt->label() }}</option>@endforeach</select></div><div class="field"><label>Fixed {{ $organization->currency }}</label><input name="options[{{ $i }}][pricing_amount]" value="{{ $o['pricing_amount']??'' }}"></div><div class="field"><label>Percentage / basis</label><input name="options[{{ $i }}][pricing_percentage]" value="{{ $o['pricing_percentage']??'' }}"><select name="options[{{ $i }}][pricing_percentage_basis]">@foreach($percentageBases as $pb)<option value="{{ $pb->value }}" @selected(($o['pricing_percentage_basis']??'base_price')===$pb->value)>{{ ucwords(str_replace('_',' ',$pb->value)) }}</option>@endforeach</select></div></div><button type="button" class="btn btn-danger remove-option">Remove option</button></div>@endforeach
</div><button type="button" id="add-option" class="btn">Add option</button></div>

<script>
(function(){
 const dependencyQuestions=@json($dependencyPayload);
 const type=document.getElementById('question-type');
 const pricingType=document.getElementById('question-pricing-type'); const pricingApplicationField=document.getElementById('question-pricing-application-field'); const pricingApplication=document.getElementById('question-pricing-application'); const pricingAmountField=document.getElementById('question-pricing-amount-field'); const pricingAmountLabel=document.getElementById('question-pricing-amount-label'); const pricingPercentageField=document.getElementById('question-pricing-percentage-field');
 const distanceEnabled=document.getElementById('distance-pricing-enabled'); const distanceFields=document.getElementById('distance-pricing-fields'); const distanceMode=document.getElementById('distance-pricing-mode'); const distanceUnit=document.getElementById('distance-unit'); const distanceFixed=document.getElementById('distance-fixed-fields'); const distanceRanges=document.getElementById('distance-range-fields');
 function toggleDistancePricing(){
   if(!distanceEnabled) return;
   const enabled=type.value==='address' && distanceEnabled.checked; const rangeMode=enabled && distanceMode.value==='range';
   distanceFields.style.display=enabled?'block':'none'; distanceFixed.style.display=enabled&&!rangeMode?'block':'none'; distanceRanges.style.display=rangeMode?'block':'none';
   distanceFields.querySelectorAll('input,select,textarea').forEach(c=>{c.disabled=!enabled;});
   if(enabled){distanceFixed.querySelectorAll('input,select,textarea').forEach(c=>{c.disabled=rangeMode;});distanceRanges.querySelectorAll('input,select,textarea').forEach(c=>{c.disabled=!rangeMode;});}
 }
 function updateDistanceUnitLabels(){document.querySelectorAll('[data-distance-unit-short]').forEach(label=>{label.textContent=distanceUnit?.value==='mile'?'mi':'km';});}
 function toggleQuestionPricing(){
   if(!pricingType) return;
   const enabled=type.value==='number'; const mode=pricingType.value; const usesAmount=enabled&&(mode==='fixed'||mode==='rate'); const usesPercentage=enabled&&mode==='percentage'; const choosesApplication=enabled&&(mode==='fixed'||mode==='percentage');
   pricingAmountField.style.display=usesAmount?'':'none'; pricingAmountField.querySelectorAll('input,select').forEach(control=>{control.disabled=!usesAmount;});
   pricingPercentageField.style.display=usesPercentage?'':'none'; pricingPercentageField.querySelectorAll('input,select').forEach(control=>{control.disabled=!usesPercentage;});
   pricingApplicationField.style.display=choosesApplication?'':'none'; pricingApplication.disabled=!choosesApplication;
   if(mode==='rate'){pricingApplication.value='per_unit';pricingAmountLabel.textContent='Rate per answer unit ({{ $organization->currency }})';}else{pricingAmountLabel.textContent='Fixed charge ({{ $organization->currency }})';}
 }
 function toggle(){ document.querySelectorAll('.conditional').forEach(s=>{const show=s.dataset.types.split(',').includes(type.value);s.style.display=show?'block':'none';s.querySelectorAll('input,select,textarea').forEach(c=>c.disabled=!show);}); toggleDistancePricing(); toggleQuestionPricing(); document.dispatchEvent(new Event('question-type-toggled')); }
 type.addEventListener('change',toggle); toggle();
 if(pricingType) pricingType.addEventListener('change',toggleQuestionPricing);
 if(distanceEnabled) distanceEnabled.addEventListener('change',toggleDistancePricing); if(distanceMode) distanceMode.addEventListener('change',toggleDistancePricing); if(distanceUnit) distanceUnit.addEventListener('change',updateDistanceUnitLabels); updateDistanceUnitLabels();
 let index=document.querySelectorAll('.option-row').length;
 const rows=document.getElementById('option-rows'); const add=document.getElementById('add-option');
 if(add) add.addEventListener('click',()=>{const i=index++; const d=document.createElement('div'); d.className='card compact option-row'; d.innerHTML=`<div class="row three"><div class="field"><label>Label</label><input name="options[${i}][label]" required></div><div class="field"><label>Value (optional)</label><input name="options[${i}][value]"></div><div class="field"><label>Order</label><input type="number" min="0" max="100000" name="options[${i}][position]" placeholder="0"><div class="muted">Blank is 0; ties use label order.</div></div></div><div class="row three"><div class="field"><label>Charge type</label><select name="options[${i}][pricing_adjustment_type]"><option value="none">None</option><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select></div><div class="field"><label>Fixed {{ $organization->currency }}</label><input name="options[${i}][pricing_amount]"></div><div class="field"><label>Percentage / basis</label><input name="options[${i}][pricing_percentage]"><select name="options[${i}][pricing_percentage_basis]"><option value="base_price">Base Price</option><option value="current_subtotal">Current Subtotal</option></select></div></div><button type="button" class="btn btn-danger remove-option">Remove option</button>`; rows.appendChild(d);});
 let distanceIndex=document.querySelectorAll('.distance-range-row').length; const distanceRows=document.getElementById('distance-range-rows'); const addDistance=document.getElementById('add-distance-range');
 if(addDistance) addDistance.addEventListener('click',()=>{const i=distanceIndex++; const d=document.createElement('div'); d.className='card compact distance-range-row'; d.dataset.index=i; d.innerHTML=`<div class="row three"><div class="field"><label>Minimum</label><input type="number" min="0" step="any" name="distance_ranges[${i}][minimum]" value="0"></div><div class="field"><label>Maximum (optional)</label><input type="number" min="0" step="any" name="distance_ranges[${i}][maximum]"></div><div class="field"><label>Fee ({{ $organization->currency }})</label><input inputmode="decimal" name="distance_ranges[${i}][amount]"></div></div><button type="button" class="btn btn-danger remove-distance-range">Remove range</button>`; distanceRows.appendChild(d);});
 const conditionRows=document.getElementById('visibility-condition-rows'); const addCondition=document.getElementById('add-visibility-condition');
 function escape(value){const d=document.createElement('div');d.textContent=String(value);return d.innerHTML;}
 function sourceOptions(selected=''){return `<option value="">Choose a question…</option>`+dependencyQuestions.map(q=>`<option value="${escape(q.uuid)}" ${q.uuid===selected?'selected':''}>#${escape(q.position)} · ${escape(q.label)}</option>`).join('');}
 function configureAnswerSelect(row,sourceUuid,selected=[]){const source=dependencyQuestions.find(q=>q.uuid===sourceUuid);const multiple=Boolean(source?.accepts_multiple_answers);const option=row.querySelector('.visibility-option');const selectedValues=Array.isArray(selected)?selected:[selected];option.multiple=multiple;if(multiple)option.setAttribute('size',String(Math.min(8,Math.max(2,(source?.options||[]).length))));else option.removeAttribute('size');option.innerHTML=(multiple?'':'<option value="">Choose an answer…</option>')+(source?.options||[]).map(o=>`<option value="${escape(o.uuid)}" ${selectedValues.includes(o.uuid)?'selected':''}>${escape(o.label)}</option>`).join('');row.querySelector('.visibility-answer-label').textContent=multiple?'Has any answer':'Has answer';row.querySelector('.visibility-answer-help').hidden=!multiple;}
 function refreshConditionRows(){Array.from(conditionRows.querySelectorAll('.visibility-condition-row')).forEach((row,i)=>{const op=row.querySelector('.visibility-operator');const source=row.querySelector('.visibility-source');const option=row.querySelector('.visibility-option');op.name=`visibility_conditions[${i}][boolean_operator]`;source.name=`visibility_conditions[${i}][source_question_uuid]`;option.name=`visibility_conditions[${i}][question_option_uuids][]`;row.querySelector('.visibility-connector-label').textContent=i===0?'Show when':'Join with';op.style.display=i===0?'none':'';if(i===0)op.value='and';});}
 if(addCondition) addCondition.addEventListener('click',()=>{const d=document.createElement('div');d.className='card compact visibility-condition-row';d.innerHTML=`<div class="row three"><div class="field visibility-connector-field"><label class="visibility-connector-label">Join with</label><select class="visibility-operator"><option value="and">AND</option><option value="or">OR</option></select></div><div class="field"><label>Earlier question</label><select class="visibility-source" required>${sourceOptions()}</select></div><div class="field"><label class="visibility-answer-label">Has answer</label><select class="visibility-option" required><option value="">Choose an answer…</option></select><div class="muted visibility-answer-help" hidden>Select one or more answers. Matching any selected answer shows this condition.</div></div></div><button type="button" class="btn btn-danger remove-visibility-condition">Remove condition</button>`;conditionRows.appendChild(d);refreshConditionRows();});
 conditionRows.addEventListener('change',e=>{if(e.target.classList.contains('visibility-source')){const row=e.target.closest('.visibility-condition-row');configureAnswerSelect(row,e.target.value);}});
 refreshConditionRows();
 document.addEventListener('click',e=>{if(e.target.classList.contains('remove-option'))e.target.closest('.option-row').remove();if(e.target.classList.contains('remove-distance-range'))e.target.closest('.distance-range-row').remove();if(e.target.classList.contains('remove-visibility-condition')){e.target.closest('.visibility-condition-row').remove();refreshConditionRows();}});
})();
</script>
