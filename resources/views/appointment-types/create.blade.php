@extends('layouts.app')
@section('title', 'Add appointment type')
@section('content')
<div class="page-heading">
    <div><h1>Add appointment type</h1><p class="muted">Start with the essentials in Simple view. Switch to Advanced whenever you need the full set of appointment options.</p></div>
</div>

<div class="appointment-editor-toolbar" data-appointment-editor-toolbar data-default-mode="simple">
    <div class="appointment-editor-toolbar-copy">
        <strong>Appointment configuration</strong>
        <div class="muted" data-appointment-mode-description>Choose Simple for the settings most businesses need, or Advanced for every option.</div>
    </div>
    <div class="appointment-editor-toolbar-controls">
        <div class="appointment-editor-mode-switch" role="group" aria-label="Appointment configuration view">
            <button class="btn" type="button" data-appointment-mode="simple" aria-pressed="false">Simple</button>
            <button class="btn" type="button" data-appointment-mode="advanced" aria-pressed="false">Advanced</button>
        </div>
        <div class="actions appointment-editor-section-actions">
            <button class="btn" type="button" data-appointment-sections="expand">Expand all</button>
            <button class="btn" type="button" data-appointment-sections="collapse">Collapse all</button>
        </div>
    </div>
</div>

<div class="appointment-editor-simple-note" data-appointment-simple-note hidden>
    <div>
        <strong>Advanced settings are hidden, not disabled.</strong>
        <div class="muted">Switching views only changes what is shown on this page. It never changes appointment settings by itself.</div>
    </div>
    <button class="btn" type="button" data-appointment-mode="advanced">View advanced settings</button>
</div>

<form method="post" enctype="multipart/form-data" action="{{ route('appointment-types.store') }}" class="form-stack" id="appointment-type-editor">
    @csrf
    @include('appointment-types.partials.form', ['appointmentType' => null])
    <div class="sticky-actions"><button class="btn btn-primary" type="submit">Create appointment type</button></div>
</form>

<script>
window.appointmentTypeEditorErrors = @json(array_keys($errors->toArray()));
</script>
<script src="{{ asset('js/appointment-type-editor.js') }}" defer></script>
@endsection
