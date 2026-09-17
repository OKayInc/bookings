@extends('layouts.public')
@section('title', 'Private event admission request')
@section('content')
<div class="page-heading"><h1>Private event admission request</h1><p>{{ $approval->booking->appointmentType->name }} · booking {{ $approval->booking->reference }}</p></div>
<div class="grid">
    <div class="card"><h3>Prospective attendee</h3><p>{{ $approval->booking->first_name }} {{ $approval->booking->last_name }}<br>{{ $approval->booking->email }}@if($approval->booking->phone)<br>{{ $approval->booking->phone }}@endif</p><p>{{ $approval->booking->attendee_count }} ticket(s) requested</p></div>
    <div class="card"><h3>Event</h3><p><strong>Doors open:</strong> {{ $approval->booking->appointment->starts_at_utc->setTimezone($approval->booking->appointment->scheduling_timezone)->format('D, M j Y · g:i A') }}<br><strong>Show starts:</strong> {{ $approval->booking->appointment->show_starts_at_utc->setTimezone($approval->booking->appointment->scheduling_timezone)->format('D, M j Y · g:i A') }}</p><p class="muted">{{ $approval->booking->appointment->scheduling_timezone }}</p></div>
    <div class="card"><h3>Decision</h3><p><span class="badge">{{ $approval->status->label() }}</span></p>@if($approval->responded_at_utc)<p class="muted">Answered {{ $approval->responded_at_utc->format('Y-m-d H:i') }} UTC @if($approval->respondedBy)by {{ $approval->respondedBy->full_name }}@endif</p>@endif</div>
</div>

@if($approval->booking->answers->isNotEmpty())
<div class="card"><h2>Questionnaire answers</h2>
@foreach($approval->booking->answers as $answer)
<div class="answer-block"><strong>{{ $answer->question_label }}</strong>
@php $value = data_get($answer->value_json, 'value'); @endphp
@if($answer->question_type === 'file')
<ul>@foreach($answer->files as $file)<li><a href="{{ route('public.event-admission-approvals.answer-file', [$approval, $token, $file]) }}">{{ $file->original_name }}</a></li>@endforeach</ul>
@elseif($answer->question_type === 'textarea')<div class="rich-text">{!! $answer->safeRichTextValueHtml() !!}</div>
@elseif(is_array($value))<div>{{ collect($value)->map(fn ($item) => is_array($item) ? ($item['label'] ?? json_encode($item)) : $item)->implode(', ') }}</div>
@else<div>{{ $value }}</div>@endif
</div>
@endforeach
</div>
@else
<div class="card"><h2>Questionnaire answers</h2><p class="muted">No questionnaire answers were submitted.</p></div>
@endif

@if($approval->status === \App\Enums\EventAdmissionApprovalStatus::Pending)
<div class="card"><h2>Respond</h2><form method="post" action="{{ route('public.event-admission-approvals.respond', [$approval, $token]) }}">@csrf
<div class="field"><label for="response_note">Internal decision note (optional)</label><textarea id="response_note" name="response_note" maxlength="5000"></textarea></div>
<div class="actions"><button class="btn btn-primary" name="action" value="accepted">Accept and issue tickets</button><button class="btn btn-danger" name="action" value="declined">Decline request</button></div>
</form></div>
@else
<div class="alert alert-info">This request can no longer be answered from this link.</div>
@endif
@endsection
