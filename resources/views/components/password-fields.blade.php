@props(['id' => 'password', 'label' => 'Password'])
@php
    $passwordRule = \App\Support\Auth\AccountPasswordPolicy::rule();
    $passwordRules = $passwordRule->appliedRules();
@endphp
<div class="mb-3" data-password-fields data-min-length="{{ $passwordRules['min'] }}" data-max-length="{{ $passwordRules['max'] }}">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="{{ $id }}">{{ $label }}</label>
            <input class="form-control" id="{{ $id }}" type="password" name="password" required
                   autocomplete="new-password" minlength="{{ $passwordRules['min'] }}"
                   passwordrules="{{ $passwordRule->toPasswordRulesString() }}"
                   aria-describedby="{{ $id }}-requirements" data-new-password>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="{{ $id }}-confirmation">Confirm password</label>
            <input class="form-control" id="{{ $id }}-confirmation" type="password" name="password_confirmation" required
                   autocomplete="new-password" aria-describedby="{{ $id }}-match" data-password-confirmation>
            <p class="small text-body-secondary mt-2 mb-0" id="{{ $id }}-match" data-password-match aria-live="polite">
                <span aria-hidden="true" data-rule-icon>×</span>
                <span class="visually-hidden" data-rule-status>Not met: </span>Passwords match
            </p>
        </div>
    </div>
    <div class="rounded border bg-body-tertiary p-3 mt-3 small" id="{{ $id }}-requirements">
        <p class="fw-semibold mb-2">Your password needs:</p>
        <ul class="list-unstyled mb-0" aria-live="polite" aria-atomic="false">
            @foreach(\App\Support\Auth\AccountPasswordPolicy::checklist() as $key => $requirement)
                <li class="text-body-secondary mb-1" data-password-rule="{{ $key }}">
                    <span aria-hidden="true" data-rule-icon>×</span>
                    <span class="visually-hidden" data-rule-status>Not met: </span>{{ $requirement }}
                </li>
            @endforeach
        </ul>
        @unless($passwordRules['symbols'])
            <p class="text-body-secondary mb-0 mt-2">Symbols (such as ! @ # $) are optional; none are required.</p>
        @endunless
        <noscript><p class="mb-0 mt-2">These requirements will be checked when you submit the form.</p></noscript>
    </div>
</div>
@pushOnce('scripts', 'password-checklist')
    <script src="{{ asset('js/password-checklist.js') }}" defer></script>
@endPushOnce
