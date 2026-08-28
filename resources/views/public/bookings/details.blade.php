@extends('layouts.public')
@section('title', 'Complete booking')
@section('content')
<div class="page-heading"><h1>Complete your booking</h1><p class="muted">{{ $type->name }} · {{ $organization->name }}</p></div>

<div class="card">
    <h2>Selected time</h2>
    <p><strong>{{ $hold->starts_at_utc->setTimezone($hold->booking_timezone)->format('l, F j, Y · g:i A') }}</strong> – {{ $hold->ends_at_utc->setTimezone($hold->booking_timezone)->format('g:i A') }} <span class="muted">{{ $hold->booking_timezone }}</span></p>
    @if($hold->booking_timezone !== $organization->timezone)
        <p class="muted">Organization time: {{ $hold->starts_at_utc->setTimezone($organization->timezone)->format('l, F j, Y · g:i A') }} – {{ $hold->ends_at_utc->setTimezone($organization->timezone)->format('g:i A') }} · {{ $organization->timezone }}</p>
    @endif
    <p class="muted">This time is temporarily held until {{ $hold->expires_at_utc->setTimezone($hold->booking_timezone)->format('g:i A') }}.</p>
</div>

@if($hold->contractTemplate)
<div class="card">
    <h2>Contract</h2>
    <p>Download and sign this exact contract version, then upload either one signed PDF or photos/scans of the signed pages.</p>
    <a class="btn" href="{{ route('public.booking-holds.contract', $holdToken) }}">Download {{ $hold->contractTemplate->original_name }}</a>
</div>
@endif

<form method="post" action="{{ route('public.booking-holds.store', $holdToken) }}" enctype="multipart/form-data" class="form-stack">
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

    @if($hold->attendee_count > 1)
    <div class="section-card">
        <h2>Additional attendees</h2>
        <p class="muted">Names and emails for additional attendees are optional in M4. The primary client above counts as attendee 1.</p>
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
@if($type->questions->where('is_active',true)->isNotEmpty() || $type->shortNoticeFeeRules->where('is_active',true)->isNotEmpty())
<script>
(function(){
 const form=document.querySelector('form.form-stack'); const total=document.getElementById('questionnaire-total'); const lines=document.getElementById('questionnaire-price-lines'); let timer;
 async function updateQuote(){
   const source=new FormData(form), body=new FormData(); body.append('_token',source.get('_token'));
   for(const [key,value] of source.entries()) if(key.startsWith('answers[') && !(value instanceof File)) body.append(key,value);
   try { const response=await fetch(@json(route('public.booking-holds.quote',$holdToken)),{method:'POST',headers:{'Accept':'application/json'},body}); const data=await response.json(); if(!response.ok) throw new Error(data.message||'Unable to calculate price.'); total.textContent=data.total_display; lines.innerHTML=data.lines.map(l=>`<div class="price-line"><span>${escapeHtml(l.label)}${l.quantity !== '1.0000' && l.quantity !== '1' ? ' × '+escapeHtml(l.quantity) : ''}</span><strong>${escapeHtml(l.amount_display)}</strong></div>`).join(''); } catch(e){ total.textContent=e.message; }
 }
 function escapeHtml(v){const d=document.createElement('div');d.textContent=String(v);return d.innerHTML;}
 form.addEventListener('change',e=>{if(e.target.name?.startsWith('answers[')){clearTimeout(timer);timer=setTimeout(updateQuote,100);}}); form.addEventListener('input',e=>{if(e.target.type==='number' && e.target.name?.startsWith('answers[')){clearTimeout(timer);timer=setTimeout(updateQuote,250);}}); updateQuote();
})();
</script>
@endif
@endsection
