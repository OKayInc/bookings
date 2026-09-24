(() => {
    const editor = document.getElementById('appointment-type-editor');
    if (!editor) return;

    const toolbar = document.querySelector('[data-appointment-editor-toolbar]');
    const validationErrors = Array.isArray(window.appointmentTypeEditorErrors)
        ? window.appointmentTypeEditorErrors
        : [];

    const sections = Array.from(editor.querySelectorAll(':scope > .section-card'))
        .filter((section) => section.querySelector(':scope > h2'));

    const normalizeName = (name) => String(name || '')
        .replace(/\[([^\]]+)\]/g, '.$1')
        .replace(/^\./, '');

    const hasValidationError = (section) => {
        if (!validationErrors.length) return false;
        const controls = Array.from(section.querySelectorAll('[name]'));
        return controls.some((control) => {
            const name = normalizeName(control.name);
            return validationErrors.some((error) =>
                error === name ||
                error.startsWith(name + '.') ||
                name.startsWith(error + '.')
            );
        });
    };

    const selectedText = (selector) => {
        const select = editor.querySelector(selector);
        if (!select) return '';
        return select.options?.[select.selectedIndex]?.text?.trim() || select.value || '';
    };

    const value = (selector) => editor.querySelector(selector)?.value?.trim() || '';
    const checked = (selector) => Boolean(editor.querySelector(selector)?.checked);
    const money = (amount) => amount === '' ? '' : amount;

    const summaryFor = (title, section) => {
        switch (title) {
            case 'Basics':
                return value('#name') || 'Name and description';
            case 'Access':
                return selectedText('#visibility') || 'Public access';
            case 'Attendance': {
                const mode = selectedText('#attendance_mode') || 'Single';
                return mode.toLowerCase().includes('group') ? `${mode} · capacity ${value('#capacity') || '—'}` : mode;
            }
            case 'Location':
                return checked('#is_online')
                    ? `Online · ${selectedText('#meeting_provider') || 'meeting provider'}`
                    : 'In person / physical location';
            case 'Duration': {
                const mode = value('#duration_mode');
                if (mode === 'variable') {
                    return `${value('#minimum_duration_value') || '—'}–${value('#maximum_duration_value') || '—'} ${selectedText('#duration_unit').toLowerCase()}`;
                }
                return `${value('#duration_value') || '—'} ${selectedText('#duration_unit').toLowerCase()}`;
            }
            case 'Start-time interval':
                return `Every ${value('#start_interval_minutes') || '—'} minutes`;
            case 'Event tickets':
                return checked('#ticketing_enabled') ? 'Enabled' : 'Disabled';
            case 'Booking notice': {
                const min = value('[name="booking_notice_value"]') || '0';
                const unit = selectedText('[name="booking_notice_unit"]').toLowerCase();
                const max = value('[name="maximum_booking_notice_value"]') || '0';
                const maxUnit = selectedText('[name="maximum_booking_notice_unit"]').toLowerCase();
                return `Min ${min} ${unit} · Max ${max} ${maxUnit}`;
            }
            case 'Booking season':
                return checked('#seasonal_availability_enabled') ? 'Seasonal availability' : 'Year-round';
            case 'Short-notice fees':
                return `${section.querySelectorAll('[name^="short_notice_fees["][name$="[threshold_value]"]').length} rule(s)`;
            case 'Rest / buffer time':
                return `${value('[name="buffer_before_minutes"]') || '0'} min before · ${value('[name="buffer_after_minutes"]') || '0'} min after`;
            case 'Pricing': {
                const mode = value('#pricing_mode') || 'free';
                if (mode === 'fixed') return `Fixed · ${money(value('[name="fixed_price"]')) || '—'}`;
                if (mode === 'rate') return `Rate · ${money(value('[name="rate_amount"]')) || '—'}`;
                if (mode === 'per_attendee') return 'Per attendee';
                return 'Free';
            }
            case 'Refundable resource deposit':
                return checked('#override_global_deposit') ? `Override · ${money(value('#deposit_override')) || '0'}` : 'Automatic';
            case 'Payment collection and refunds':
                return selectedText('#payment_collection_mode') || 'Full payment';
            case 'Client access and email verification':
                return selectedText('#email_verification_mode') || 'Default verification';
            case 'Resources and confirmation': {
                const count = section.querySelectorAll('input[name="resource_uuids[]"]:checked').length;
                return count ? `${count} resource(s) selected` : 'No resources selected';
            }
            case 'Cancellation policy':
                return checked('#cancellation_allowed') ? 'Allowed' : 'Not allowed';
            case 'Rescheduling policy':
                return checked('#rescheduling_allowed') ? 'Allowed' : 'Not allowed';
            case 'Reminders':
                return checked('#reminder_enabled') ? 'Enabled' : 'Disabled';
            case 'Contract':
                return section.querySelector('a[href*="contract-template"]') ? 'Contract configured' : 'Optional';
            case 'After booking':
                return value('#redirect_url') ? 'Custom redirect configured' : 'Default confirmation page';
            case 'Status':
                return checked('#is_active') ? 'Active' : 'Disabled';
            default:
                return 'Click to configure';
        }
    };

    const setOpen = (section, open) => {
        const body = section.querySelector(':scope > .appointment-editor-section-body');
        const toggle = section.querySelector(':scope > .appointment-editor-section-header .appointment-editor-section-toggle');
        if (!body || !toggle) return;
        section.classList.toggle('is-open', open);
        body.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        const title = section.dataset.sectionTitle || 'section';
        toggle.setAttribute('aria-label', `${open ? 'Collapse' : 'Expand'} ${title}`);
    };

    const refreshSummary = (section) => {
        const summary = section.querySelector(':scope > .appointment-editor-section-header .appointment-editor-section-summary');
        if (!summary) return;
        summary.textContent = summaryFor(section.dataset.sectionTitle || '', section);
    };

    sections.forEach((section, index) => {
        const heading = section.querySelector(':scope > h2');
        const title = heading.textContent.trim();
        section.dataset.sectionTitle = title;
        section.classList.add('appointment-editor-section');

        const header = document.createElement('div');
        header.className = 'appointment-editor-section-header';

        const headingWrap = document.createElement('div');
        headingWrap.className = 'appointment-editor-section-heading';
        headingWrap.appendChild(heading);

        const summary = document.createElement('div');
        summary.className = 'appointment-editor-section-summary muted';
        headingWrap.appendChild(summary);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'appointment-editor-section-toggle';
        toggle.innerHTML = '<span aria-hidden="true">⌄</span>';

        header.appendChild(headingWrap);
        header.appendChild(toggle);

        const body = document.createElement('div');
        body.className = 'appointment-editor-section-body';
        while (section.firstChild) {
            body.appendChild(section.firstChild);
        }
        section.appendChild(header);
        section.appendChild(body);

        const shouldOpen = hasValidationError(section) || title === 'Basics' || title === 'Duration';
        setOpen(section, shouldOpen);
        refreshSummary(section);

        const toggleSection = (event) => {
            if (event.target.closest('a, input, select, textarea, label')) return;
            setOpen(section, !section.classList.contains('is-open'));
        };

        header.addEventListener('click', toggleSection);
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            setOpen(section, !section.classList.contains('is-open'));
        });

        body.addEventListener('input', () => refreshSummary(section));
        body.addEventListener('change', () => refreshSummary(section));
    });

    toolbar?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-appointment-sections]');
        if (!button) return;
        const open = button.dataset.appointmentSections === 'expand';
        sections.forEach((section) => setOpen(section, open));
    });

    const firstErrorSection = sections.find(hasValidationError);
    if (firstErrorSection) {
        requestAnimationFrame(() => firstErrorSection.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    }
})();
