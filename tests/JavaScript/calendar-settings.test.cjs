const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/appointment-types/calendars/edit.blade.php'), 'utf8');
const script = [...view.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)].map(match => match[1]).join('\n');

function control(properties) {
    const listeners = {};
    return {
        ...properties,
        addEventListener: (event, listener) => { listeners[event] = listener; },
        change() { listeners.change?.(); },
    };
}

function member(mode = 'default') {
    const modeSelect = control({ value: mode });
    const choices = [
        control({ name: 'check_calendars[]', checked: true, dataset: { defaultChecked: '1' } }),
        control({ name: 'check_calendars[]', checked: false, dataset: { defaultChecked: '0' } }),
        control({ name: 'write_calendar[member]', checked: true, dataset: { defaultChecked: '1' } }),
        control({ name: 'write_calendar[member]', checked: false, dataset: { defaultChecked: '0' } }),
        control({ name: 'write_calendar[member]', checked: false, dataset: { defaultChecked: '0' } }),
    ];
    const status = { textContent: '' };
    return {
        modeSelect, choices, status,
        querySelector: selector => ({ '[data-calendar-mode]': modeSelect, '[data-calendar-status]': status })[selector],
        querySelectorAll: selector => selector === '[data-calendar-choice]' ? choices : [],
    };
}

function mount(...members) {
    vm.runInNewContext(script, {
        document: { querySelectorAll: selector => selector === '[data-calendar-settings]' ? members : [] },
    });
}

for (const [label, index] of [['availability checkbox', 1], ['writing calendar', 3], ['no external event', 4]]) {
    test(`editing the ${label} switches only that member to custom settings`, () => {
        const edited = member();
        const coworker = member();
        mount(edited, coworker);
        edited.choices[index].checked = true;
        edited.choices[index].change();
        assert.equal(edited.modeSelect.value, 'custom');
        assert.equal(edited.choices[index].checked, true);
        assert.equal(coworker.modeSelect.value, 'default');
        assert.match(edited.status.textContent, /custom/i);
    });
}

test('clearing the last availability checkbox keeps an intentional empty custom selection', () => {
    const edited = member();
    mount(edited);
    edited.choices[0].checked = false;
    edited.choices[0].change();
    assert.equal(edited.modeSelect.value, 'custom');
    assert.equal(edited.choices[0].checked, false);
    assert.equal(edited.choices[1].checked, false);
});

test('returning to member defaults restores the displayed availability and writing choices', () => {
    const edited = member('custom');
    edited.choices.forEach(choice => { choice.checked = choice.dataset.defaultChecked === '0'; });
    mount(edited);
    assert.equal(edited.choices[0].checked, false, 'initial custom values must be preserved');
    edited.modeSelect.value = 'default';
    edited.modeSelect.change();
    assert.deepEqual(edited.choices.map(choice => choice.checked), [true, false, true, false, false]);
    assert.match(edited.status.textContent, /defaults/i);
});

test('member defaults can restore the no-external-event radio', () => {
    const edited = member('custom');
    edited.choices[2].dataset.defaultChecked = '0';
    edited.choices[4].dataset.defaultChecked = '1';
    mount(edited);
    edited.modeSelect.value = 'default';
    edited.modeSelect.change();
    assert.deepEqual(edited.choices.slice(2).map(choice => choice.checked), [false, false, true]);
});
