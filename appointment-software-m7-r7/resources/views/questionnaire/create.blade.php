@extends('layouts.app')
@section('title','Add questionnaire question')
@section('content')
<div class="page-heading"><h1>Add question</h1><p class="muted">{{ $appointmentType->name }}</p></div>
<form method="post" action="{{ route('appointment-types.questions.store',$appointmentType) }}" class="form-stack">@csrf
@include('questionnaire.partials.form')
<div class="actions"><button class="btn btn-primary">Add question</button><a class="btn" href="{{ route('appointment-types.questionnaire.index',$appointmentType) }}">Cancel</a></div>
</form>
@endsection
