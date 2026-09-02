@extends('layouts.app')
@section('title','Add questionnaire question')
@section('content')
<div class="page-heading"><h1>Add or reuse question</h1><p class="muted">{{ $appointmentType->name }}</p></div>

<div class="section-card">
 <h2>Reuse an existing question</h2>
 <p class="muted">These questions are available to every appointment type in {{ $organization->name }}. Attaching one creates an independent copy, including its choices, validation and pricing settings.</p>
 @if($reusableQuestions->isNotEmpty())
 <div class="field"><label for="question-library-filter">Search available questions</label><input id="question-library-filter" type="search" placeholder="Search by question or type"></div>
 <div class="table-scroll"><table class="table table-hover align-middle">
  <thead><tr><th>Question</th><th>Type</th><th>Default</th><th></th></tr></thead>
  <tbody id="question-library-rows">
  @foreach($reusableQuestions as $reusableQuestion)
  @php
      $alreadyAttached = $attachedReusableQuestionIds->containsStrict($reusableQuestion->getKey());
  @endphp
  <tr data-library-row data-search="{{ \Illuminate\Support\Str::lower($reusableQuestion->label.' '.$reusableQuestion->type->label()) }}">
   <td><strong>{{ $reusableQuestion->label }}</strong>@if($reusableQuestion->description)<div class="muted">{{ \Illuminate\Support\Str::limit($reusableQuestion->description,90) }}</div>@endif</td>
   <td>{{ $reusableQuestion->type->label() }}@if($reusableQuestion->type->hasOptions())<div class="muted">{{ $reusableQuestion->options_count }} option(s)</div>@endif</td>
   <td>{{ $reusableQuestion->default_is_required ? 'Required' : 'Optional' }}</td>
   <td>
    @if($alreadyAttached)
    <span class="muted">Already attached</span>
    @else
    <form method="post" action="{{ route('appointment-types.questions.attach',[$appointmentType,$reusableQuestion]) }}">@csrf<button class="btn btn-primary">Attach</button></form>
    @endif
   </td>
  </tr>
  @endforeach
  </tbody>
 </table></div>
 <p id="question-library-empty" class="muted" style="display:none">No reusable questions match your search.</p>
 @else
 <p class="muted">No reusable questions exist yet. Create the first one below.</p>
 @endif
</div>

<div class="page-heading"><h2>Create a new reusable question</h2><p class="muted">The new question will be attached here and added to the organization's reusable library.</p></div>
<form method="post" action="{{ route('appointment-types.questions.store',$appointmentType) }}" class="form-stack">@csrf
@include('questionnaire.partials.form')
<div class="actions"><button class="btn btn-primary">Create and attach question</button><a class="btn" href="{{ route('appointment-types.questionnaire.index',$appointmentType) }}">Cancel</a></div>
</form>

@if($reusableQuestions->isNotEmpty())
<script>
(function(){
 const filter=document.getElementById('question-library-filter');
 const rows=Array.from(document.querySelectorAll('[data-library-row]'));
 const empty=document.getElementById('question-library-empty');
 filter.addEventListener('input',()=>{const query=filter.value.trim().toLowerCase();let visible=0;rows.forEach(row=>{const show=row.dataset.search.includes(query);row.style.display=show?'':'none';if(show)visible++;});empty.style.display=visible===0?'block':'none';});
})();
</script>
@endif
@endsection
