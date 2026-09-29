(() => {
    document.querySelectorAll('[data-password-fields]').forEach(fields => {
        const password = fields.querySelector('[data-new-password]');
        const confirmation = fields.querySelector('[data-password-confirmation]');
        const requirements = [...fields.querySelectorAll('[data-password-rule]')];
        const match = fields.querySelector('[data-password-match]');

        // Match Laravel's Unicode character classes and character (not byte) count.
        const checks = {
            length: value => [...value].length >= Number(fields.dataset.minLength),
            maximum: value => [...value].length <= Number(fields.dataset.maxLength),
            lowercase: value => /\p{Ll}/u.test(value),
            uppercase: value => /\p{Lu}/u.test(value),
            letters: value => /\p{L}/u.test(value),
            number: value => /\p{N}/u.test(value),
            symbol: value => /[\p{Z}\p{S}\p{P}]/u.test(value),
        };

        function showState(row, met) {
            const state = met ? 'met' : 'unmet';
            if (row.dataset.state === state) return;

            row.dataset.state = state;
            row.classList.toggle('text-success', met);
            row.classList.toggle('text-body-secondary', !met);
            row.querySelector('[data-rule-icon]').textContent = met ? '✓' : '×';
            row.querySelector('[data-rule-status]').textContent = met ? 'Met: ' : 'Not met: ';
        }

        function update() {
            const value = password.value;
            requirements.forEach(row => showState(row, checks[row.dataset.passwordRule](value)));
            showState(match, value.length > 0 && value === confirmation.value);
        }

        [password, confirmation].forEach(input => {
            input.addEventListener('input', update);
            input.addEventListener('change', update);
            input.addEventListener('focus', update);
        });
        password.form?.addEventListener('reset', () => setTimeout(update, 0));
        window.addEventListener('pageshow', update);
        update();
    });
})();
