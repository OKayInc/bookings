@extends('layouts.public')
@section('title', 'Complete booking')
@section('content')
<div class="page-heading"><h1>Complete your booking</h1><p class="muted">{{ $type->name }} · {{ $organization->name }}</p></div>

<div class="card">
    <h2>{{ $eventTiming ? 'Selected event' : 'Selected time' }}</h2>
    @if($eventTiming)
        <p><strong>Doors open:</strong> {{ $hold->starts_at_utc->setTimezone($hold->booking_timezone)->format('l, F j, Y · g:i A') }}<br><strong>Show starts:</strong> {{ $eventTiming['show_starts_at_utc']->setTimezone($hold->booking_timezone)->format('l, F j, Y · g:i A') }}@if($eventTiming['show_ends_at_utc'])<br><strong>Show ends:</strong> {{ $eventTiming['show_ends_at_utc']->setTimezone($hold->booking_timezone)->format('l, F j, Y · g:i A') }}@endif<br><span class="muted">Resources remain booked until {{ $hold->ends_at_utc->setTimezone($hold->booking_timezone)->format('g:i A') }} · {{ $hold->booking_timezone }}</span></p>
    @else
        <p><strong>{{ $hold->starts_at_utc->setTimezone($hold->booking_timezone)->format('l, F j, Y · g:i A') }}</strong> – {{ $hold->ends_at_utc->setTimezone($hold->booking_timezone)->format('g:i A') }} <span class="muted">{{ $hold->booking_timezone }}</span></p>
    @endif
    @if($hold->booking_timezone !== $organization->timezone)
        <p class="muted">Organization time: {{ $hold->starts_at_utc->setTimezone($organization->timezone)->format('l, F j, Y · g:i A') }} – {{ $hold->ends_at_utc->setTimezone($organization->timezone)->format('g:i A') }} · {{ $organization->timezone }}</p>
    @endif
    <p class="muted">This time is temporarily held until {{ $hold->expires_at_utc->setTimezone($hold->booking_timezone)->format('g:i A') }}.</p>
    <p>{{ $hold->attendee_count }} {{ $eventTiming ? 'ticket(s)' : 'attendee(s)' }} reserved for this booking, including the primary client.</p>
    @if($eventTiming && ! empty($hold->ticket_seats))
        @php
            $ticketSeating = app(\App\Domain\Tickets\TicketSeatingService::class);
            $money = app(\App\Domain\Money\MoneyService::class);
        @endphp
        <h3>Held admission</h3>
        <ul>
            @foreach(array_slice($hold->ticket_seats, 0, 100) as $seat)
                @php
                    $seatFeeMinor = (int) ($seat['seat_fee_minor'] ?? 0);
                @endphp
                <li>
                    {{ $ticketSeating->display($seat) }}
                    {{ $seatFeeMinor > 0 ? ' · '.$money->format($seatFeeMinor, $organization->currency).' seating fee' : '' }}
                </li>
            @endforeach
        </ul>
        @if(count($hold->ticket_seats) > 100)
            <p class="muted">Plus {{ count($hold->ticket_seats) - 100 }} additional held tickets.</p>
        @endif
    @endif
</div>

@php $quantityEquipment = $hold->resources->filter(fn ($resource) => $resource->usesQuantityInventory()); @endphp
@if($quantityEquipment->isNotEmpty())
<div class="card">
    <h2>Equipment reserved</h2>
    <ul>
        @foreach($quantityEquipment as $resource)
            <li>{{ $resource->name }}: {{ (int) ($resource->pivot->quantity_reserved ?? 1) }} piece(s)</li>
        @endforeach
    </ul>
    <p class="muted">These quantities are held for this time while you finish checkout.</p>
</div>
@endif

@if($hold->contractTemplate)
<div class="card">
    <h2>Contract</h2>
    <p>Download and sign this exact contract version, then upload either one signed PDF or photos/scans of the signed pages.</p>
    <a class="btn" href="{{ route('public.booking-holds.contract', $holdToken) }}">Download {{ $hold->contractTemplate->original_name }}</a>
</div>
@endif

<form method="post" action="{{ route('public.booking-holds.store', $holdToken) }}" enctype="multipart/form-data" class="form-stack" data-attendee-count="{{ (int) $hold->attendee_count }}">
    @csrf
    <div class="section-card">
        <h2>Your information</h2>
        <div class="row">
            <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="120"></div>
            <div class="field"><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="120"></div>
        </div>
        <div class="row">
            <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $hold->invitation?->recipient_email) }}" @readonly($hold->invitation?->recipient_email) required maxlength="254"></div>
            <div class="field"><label for="phone">Telephone <span class="muted">optional</span></label><input id="phone" name="phone" value="{{ old('phone') }}" maxlength="64"></div>
        </div>
        <p class="muted">No account will be created. Your email is used to identify this booking and send secure booking-management links.</p>
    </div>

    @include('public.bookings.partials.questionnaire')

    <div class="section-card">
        <h2>Gift card or coupon</h2>
        <div class="field"><label for="coupon_code">Code <span class="muted">optional</span></label><input id="coupon_code" name="coupon_code" value="{{ old('coupon_code') }}" maxlength="80" autocomplete="off" placeholder="ABCD-EFGH-IJKL"></div>
        <p class="muted">The discount is verified again when the booking is submitted. Fixed-value cards retain any unused balance.</p>
    </div>

    @if($hold->attendee_count > 1)
    <div class="section-card">
        <h2>{{ $eventTiming ? 'Additional ticket holders' : 'Additional attendees' }}</h2>
        <p class="muted">Names and emails for additional {{ $eventTiming ? 'ticket holders' : 'attendees' }} are optional. The primary client above counts as attendee 1.</p>
        @for($i = 0; $i < $hold->attendee_count - 1; $i++)
            <div class="card compact">
                <strong>Attendee {{ $i + 2 }}</strong>
                <div class="row three">
                    <div class="field"><label>First name</label><input name="attendees[{{ $i }}][first_name]" value="{{ old("attendees.$i.first_name") }}"></div>
                    <div class="field"><label>Last name</label><input name="attendees[{{ $i }}][last_name]" value="{{ old("attendees.$i.last_name") }}"></div>
                    <div class="field"><label>Email</label><input type="email" name="attendees[{{ $i }}][email]" value="{{ old("attendees.$i.email") }}"></div>
                </div>
            </div>
        @endfor
    </div>
    @endif

    @if($hold->contractTemplate)
    <div class="section-card">
        <h2>Upload signed contract</h2>
        <div class="field">
            <label for="contract_files">Signed PDF or page images</label>
            <input id="contract_files" type="file" name="contract_files[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple required>
            <div class="muted">Upload one PDF, or multiple JPG/PNG/WebP page images. Do not mix a PDF with images.</div>
        </div>
    </div>
    @endif

    <div class="actions"><button class="btn btn-primary" type="submit">Submit booking</button><a class="btn" href="javascript:history.back()">Choose another time</a></div>
</form>
<script src="{{ asset('js/numeric-question-constraints.js') }}?v=m7-r21"></script>
<script src="{{ asset('js/question-visibility.js') }}?v=m9-r2"></script>
<script>
(function(){
 const form=document.querySelector('form.form-stack'); const total=document.getElementById('questionnaire-total'); const lines=document.getElementById('questionnaire-price-lines'); const questionElements=Array.from(document.querySelectorAll('.questionnaire-question')); let timer;
 const questions=new Map(questionElements.map(element=>[element.dataset.questionUuid,element]));
 questionElements.forEach(element=>{element._visibilityConditions=JSON.parse(element.dataset.visibilityConditions||'[]');element.querySelectorAll('input,select,textarea').forEach(control=>{control.dataset.visibilityRequired=control.required?'1':'0';});});
 questionElements.forEach(element=>{element._numericConstraints=JSON.parse(element.dataset.numericConstraints||'[]');});
 function readNumericAnswer(uuid){const element=questions.get(uuid);if(!element||element.hidden||element.dataset.questionType!=='number')return null;const control=element.querySelector('input[type="number"]');return control&&!control.disabled?control.value:null;}
 function refreshNumericConstraints(){
   questionElements.forEach(element=>{
     if(!element._numericConstraints.length)return;
     const control=element.querySelector('input[type="number"]');if(!control)return;
     const valid=element.hidden||control.value===''||NumericQuestionConstraints.evaluate(control.value,element._numericConstraints,readNumericAnswer,form.dataset.attendeeCount);
     const message=valid?'':element.dataset.numericMessage;
     control.setCustomValidity(message);
     control.setAttribute('aria-describedby',`numeric_help_${element.dataset.questionUuid} numeric_error_${element.dataset.questionUuid}`);
     if(valid)control.removeAttribute('aria-invalid');else control.setAttribute('aria-invalid','true');
     element.querySelector('.numeric-constraint-error').textContent=message;
   });
 }
 function hasAnswer(questionUuid,optionUuid){const source=questions.get(questionUuid);if(!source||source.hidden)return false;return Array.from(source.querySelectorAll('input,select,textarea')).some(control=>!control.disabled&&control.value===optionUuid&&(!['checkbox','radio'].includes(control.type)||control.checked));}
 function expressionMatches(conditions){return QuestionVisibility.expressionMatches(conditions,hasAnswer);}
 function clearControl(control){if(control.type==='checkbox'||control.type==='radio')control.checked=false;else if(control.type==='file')control.value='';else control.value='';}
 function refreshVisibility(){questionElements.forEach(element=>{const show=expressionMatches(element._visibilityConditions);const wasHidden=element.hidden;if(!show&&!wasHidden)element.querySelectorAll('input,select,textarea').forEach(clearControl);element.hidden=!show;element.setAttribute('aria-hidden',show?'false':'true');element.querySelectorAll('input,select,textarea').forEach(control=>{control.disabled=!show;control.required=show&&control.dataset.visibilityRequired==='1';});});}
 async function updateQuote(){
   const source=new FormData(form), body=new FormData(); body.append('_token',source.get('_token'));
   for(const [key,value] of source.entries()) if((key.startsWith('answers[')||key==='coupon_code') && !(value instanceof File)) body.append(key,value);
   try { const response=await fetch(@json(route('public.booking-holds.quote',$holdToken)),{method:'POST',headers:{'Accept':'application/json'},body}); const data=await response.json(); if(!response.ok) throw new Error(data.message||'Unable to calculate price.'); total.textContent=data.total_display; lines.innerHTML=data.lines.map(l=>`<div class="price-line"><span>${escapeHtml(l.label)}${l.quantity !== '1.0000' && l.quantity !== '1' ? ' × '+escapeHtml(l.quantity) : ''}</span><strong>${escapeHtml(l.amount_display)}</strong></div>`).join(''); } catch(e){ total.textContent=e.message; }
 }
 function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v);return d.innerHTML;}
 form.addEventListener('change',e=>{if(e.target.name?.startsWith('answers[')||e.target.name==='coupon_code'){refreshVisibility();refreshNumericConstraints();clearTimeout(timer);timer=setTimeout(updateQuote,100);}}); form.addEventListener('input',e=>{if((e.target.type==='number'&&e.target.name?.startsWith('answers['))||e.target.name==='coupon_code'){refreshNumericConstraints();clearTimeout(timer);timer=setTimeout(updateQuote,350);}}); refreshVisibility(); refreshNumericConstraints(); updateQuote();
})();
</script>
@endsection
