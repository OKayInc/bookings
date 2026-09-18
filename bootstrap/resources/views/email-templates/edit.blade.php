@extends('layouts.app')
@section('title', 'Attendee email templates')
@section('content')
<div class="page-heading"><h1>Attendee email templates</h1><p>Customize emails for {{ $organization->name }}. Templates apply to this organization's appointments and events.</p></div>
<form method="get" action="{{ route('email-templates.edit') }}" class="section-card">
    <label for="kind">Email type</label>
    <select id="kind" name="kind">
        @foreach(\App\Domain\Email\AttendeeEmailTemplates::KINDS as $key => $label)
        <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Open template</button>
</form>
<form method="post" action="{{ route('email-templates.update') }}" class="section-card" id="email-template-form">
    @csrf @method('PUT')
    <input type="hidden" name="kind" value="{{ $kind }}">
    <p>{{ $template ? 'Using a custom template.' : 'Using the system default. Save below to customize it.' }}</p>
    <div class="field"><label for="format">Delivery format</label>
    <select id="format" name="format">
        <option value="text" @selected(old('format', $template?->format ?? 'text') === 'text')>Plain text</option>
        <option value="html" @selected(old('format', $template?->format) === 'html')>HTML</option>
    </select></div>
    <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="255" required value="{{ old('subject', $template?->subject ?? $defaultSubject) }}"></div>
    <div class="field"><label for="body">Email body</label><textarea id="body" name="body" rows="16" required>{{ old('body', $template?->body ?? $defaultBody) }}</textarea></div>
    <p>HTML supports bold, italic, underline, colours and lists. Switching format keeps your draft; check the preview before saving.</p>
    <p>Include <code>@{{message}}</code> for the system's essential details. Secure action links and attachments are included automatically. Hidden locations remain hidden until disclosure.</p>
    <p>Available placeholders:</p>
    <ul>@foreach($placeholders as $placeholder)<li><code>{{ $placeholder }}</code></li>@endforeach</ul>
    <button class="btn btn-primary" type="submit">Save template</button>
    <button class="btn btn-secondary" type="button" id="preview-template">Preview draft</button>
    <p class="muted">Preview uses sample details and does not send email.</p>
    <h2 id="preview-subject"></h2>
    <iframe id="email-preview" title="Sample email preview" sandbox="" style="width:100%;height:300px;border:1px solid #ccc" hidden></iframe>
</form>
<form method="post" action="{{ route('email-templates.destroy') }}" class="section-card">
    @csrf @method('DELETE') <input type="hidden" name="kind" value="{{ $kind }}">
    <button class="btn btn-secondary" type="submit">Restore system default</button>
</form>
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script src="{{ asset('js/attendee-email-editor.js') }}" data-base-url="{{ asset('vendor/tinymce') }}"></script>
@endsection
