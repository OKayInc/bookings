@extends('layouts.app')
@section('title','Edit questionnaire question')
@section('content')
<div class="page-heading"><h1>Edit question</h1><p class="muted">{{ $appointmentType->name }}</p></div>
<form method="post" action="{{ route('appointment-types.questions.update',[$appointmentType,$question]) }}" class="form-stack">@csrf @method('PUT')
@include('questionnaire.partials.form')
<div class="actions"><button class="btn btn-primary">Save question</button><a class="btn" href="{{ route('appointment-types.questionnaire.index',$appointmentType) }}">Cancel</a></div>
</form>
@endsection
