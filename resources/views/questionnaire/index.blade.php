@extends('layouts.app')
@section('title', 'Questionnaire · '.$appointmentType->name)
@section('content')
<div class="page-heading actions" style="justify-content:space-between">
 <div><h1>Questionnaire</h1><p class="muted">{{ $appointmentType->name }} · unlimited booking questions and price modifiers.</p></div>
 <div class="actions"><a class="btn" href="{{ route('appointment-types.edit',$appointmentType) }}">Back to appointment type</a><a class="btn btn-primary" href="{{ route('appointment-types.questions.create',$appointmentType) }}">Add or reuse question</a></div>
</div>
<div class="card table-scroll"><table class="table table-hover align-middle">
<thead><tr><th>#</th><th>Question</th><th>Type</th><th>Required</th><th>Pricing</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($appointmentType->questions as $question)
<tr>
 <td>{{ $question->position }}</td><td><strong>{{ $question->label }}</strong>@if($question->reusableQuestion)<div class="muted">Reusable question</div>@endif @if($question->description)<div class="muted">{{ \Illuminate\Support\Str::limit($question->description,90) }}</div>@endif</td>
 <td>{{ $question->type->label() }}@if($question->type->hasOptions())<div class="muted">{{ $question->options->count() }} option(s)</div>@endif</td>
 <td>{{ $question->is_required ? 'Yes' : 'No' }}</td>
 <td>
 @if($question->pricing_adjustment_type->value !== 'none'){{ ucfirst(str_replace('_',' ',$question->pricing_adjustment_type->value)) }} {{ str_replace('_',' ',$question->pricing_application_mode->value) }}
 @elseif($question->options->contains(fn($o)=>$o->pricing_adjustment_type->value!=='none'))Option charges
 @else None @endif
 </td><td>{{ $question->is_active ? 'Active' : 'Disabled' }}</td>
 <td><div class="actions"><a class="btn" href="{{ route('appointment-types.questions.edit',[$appointmentType,$question]) }}">Edit</a><form method="post" action="{{ route('appointment-types.questions.destroy',[$appointmentType,$question]) }}" onsubmit="return confirm('Remove this question from the appointment type? If historical answers exist it will be disabled instead. The reusable template will remain available.')">@csrf @method('DELETE')<button class="btn btn-danger">Remove / disable</button></form></div></td>
</tr>
@empty<tr><td colspan="7">No questionnaire questions yet.</td></tr>@endforelse
</tbody></table></div>
@endsection
