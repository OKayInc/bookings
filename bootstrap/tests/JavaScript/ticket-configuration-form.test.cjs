const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function ticketForm({ enabled = true, attendance = 'single', duration = 'variable', pricing = 'fixed' } = {}) {
    const control = (value = '') => ({
        value, checked: false, disabled: false, hidden: false, listeners: {},
        addEventListener(name, listener) { this.listeners[name] = listener; },
        dispatchEvent(event) { this.listeners[event.type]?.(event); },
    });
    const section = (controls = []) => ({
        style: {},
        querySelectorAll() { return controls; },
    });

    const ticketing = control(); ticketing.checked = enabled;
    const attendanceControl = control(attendance);
    const durationControl = control(duration);
    const pricingControl = control(pricing);
    const options = {
        single: control('single'), variable: control('variable'),
        fixed: control('fixed'), rate: control('rate'),
    };
    attendanceControl.querySelector = () => options.single;
    durationControl.querySelector = () => options.variable;
    pricingControl.querySelector = selector => selector.includes('fixed') ? options.fixed : options.rate;

    const scheme = control('section_seat');
    const optional = control(); optional.checked = false;
    const feeInput = control('12.00');
    const firstInput = control('1');
    const lastInput = control('10');
    const feeField = section([feeInput]);
    const firstField = section([firstInput]);
    const lastField = section([lastInput]);
    const sectionInput = control();
    const rowInput = control();
    const sectionField = section([sectionInput]);
    const rowField = section([rowInput]);
    const remove = control();
    const row = {
        dataset: { index: '0' },
        querySelector(selector) {
            return {
                '[data-ticket-section-field]': sectionField,
                '[data-ticket-row-field]': rowField,
                '[data-ticket-section-field] input': sectionInput,
                '[data-ticket-row-field] input': rowInput,
                '[data-ticket-quantity-field]': section(),
                '[data-ticket-seat-fee-field]': feeField,
                '[data-ticket-first-seat-field] input': firstInput,
                '[data-ticket-last-seat-field] input': lastInput,
                '[data-ticket-first-seat-field]': firstField,
                '[data-ticket-last-seat-field]': lastField,
                '[data-remove-ticket-seat-block]': remove,
            }[selector];
        },
    };
    const list = {
        children: [row],
        querySelectorAll() { return [row]; },
        appendChild(child) { this.children.push(child); },
    };
    const add = control();
    const elements = {
        ticketing_enabled: ticketing,
        'ticketing-fields': section(),
        ticket_seating_scheme: scheme,
        ticket_seat_optional: optional,
        'ticket-seat-optional-field': section([optional]),
        'ticket-seat-block-fields': section(),
        'ticket-consecutive-help': { style: {} },
        'ticket-seat-block-list': list,
        'ticket-seat-block-template': { innerHTML: '' },
        'add-ticket-seat-block': add,
        attendance_mode: attendanceControl,
        duration_mode: durationControl,
        pricing_mode: pricingControl,
    };
    const document = {
        getElementById(id) { return elements[id]; },
        createElement() { return { innerHTML: '', firstElementChild: row }; },
    };
    class Event { constructor(type) { this.type = type; } }

    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/appointment-types/partials/form.blade.php'), 'utf8');
    const script = [...source.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)]
        .map(match => match[1])
        .find(candidate => candidate.includes("document.getElementById('ticketing_enabled')"));
    vm.runInNewContext(script, { document, Event });

    return {
        ticketing, attendance: attendanceControl, duration: durationControl, pricing: pricingControl,
        options, feeField, feeInput,
    };
}

test('ticketing forces valid modes and hides incompatible options until unchecked', () => {
    const form = ticketForm();

    assert.equal(form.attendance.value, 'group');
    assert.equal(form.duration.value, 'fixed');
    assert.equal(form.pricing.value, 'per_attendee');
    for (const option of Object.values(form.options)) {
        assert.equal(option.hidden, true);
        assert.equal(option.disabled, true);
    }

    form.ticketing.checked = false;
    form.ticketing.dispatchEvent(new Event('change'));
    for (const option of Object.values(form.options)) {
        assert.equal(option.hidden, false);
        assert.equal(option.disabled, false);
    }
});

test('seating fee is editable only for paid per-attendee tickets', () => {
    const form = ticketForm({ attendance: 'group', duration: 'fixed', pricing: 'per_attendee' });
    assert.equal(form.feeField.style.display, 'block');
    assert.equal(form.feeInput.disabled, false);

    form.pricing.value = 'free';
    form.pricing.dispatchEvent(new Event('change'));
    assert.equal(form.feeField.style.display, 'none');
    assert.equal(form.feeInput.disabled, true);
});
