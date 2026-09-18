const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function editor(operand = 'attendee_count') {
    const controls = Object.fromEntries(Object.entries({
        boolean_operator: 'and', comparison_operator: '<=', operand_type: operand,
        source_question_uuid: 'q1', comparison_value: '99',
    }).map(([name, value]) => [name, { value, style: {}, disabled: false, dataset: { numericField: name } }]));
    const sourceField = { style: {} }, valueField = { style: {} }, joinLabel = {};
    const row = {
        querySelectorAll() { return Object.values(controls); },
        querySelector(selector) {
            const field = selector.match(/data-numeric-field="([^"]+)"/);
            if (field) return controls[field[1]];
            return { '.numeric-source-field': sourceField, '.numeric-value-field': valueField, '.numeric-join-label': joinLabel }[selector];
        },
    };
    const events = {}, rows = { querySelectorAll() { return [row]; }, addEventListener(name, fn) { events[name] = fn; } };
    const add = { addEventListener() {} }, type = { value: 'number' }, preview = {};
    const document = {
        getElementById(id) { return { 'numeric-constraint-rows': rows, 'add-numeric-constraint': add, 'question-type': type, 'numeric-constraint-preview': preview }[id]; },
        addEventListener(name, fn) { events[name] = fn; },
    };
    const file = fs.readFileSync(path.join(__dirname, '../../resources/views/questionnaire/partials/numeric-constraints.blade.php'), 'utf8');
    const script = file.match(/<script>\s*([\s\S]*?)<\/script>/)[1]
        .replace('@json($numericSources)', JSON.stringify([{ uuid: 'q1', label: 'Minimum', position: 1 }]))
        .replace('@json($numericOperators)', '[]');
    vm.runInNewContext(script, { document });
    return { controls, sourceField, valueField, type, preview, event: name => events[name]() };
}

test('attendee operand hides and disables both stale operand inputs', () => {
    const { controls, sourceField, valueField, preview } = editor();
    assert.equal(controls.source_question_uuid.disabled, true);
    assert.equal(controls.comparison_value.disabled, true);
    assert.equal(sourceField.style.display, 'none');
    assert.equal(valueField.style.display, 'none');
    assert.equal(preview.textContent, 'Required: (this answer <= number of attendees)');
    assert.equal(controls.operand_type.name, 'numeric_constraints[0][operand_type]');
});

test('switching among operands and question types preserves correct enabled fields', () => {
    const { controls, type, event } = editor();
    controls.operand_type.value = 'question'; event('change');
    assert.equal(controls.source_question_uuid.disabled, false);
    assert.equal(controls.comparison_value.disabled, true);
    controls.operand_type.value = 'value'; event('change');
    assert.equal(controls.source_question_uuid.disabled, true);
    assert.equal(controls.comparison_value.disabled, false);
    controls.operand_type.value = 'attendee_count'; event('change');
    type.value = 'text'; event('question-type-toggled');
    assert.equal(controls.operand_type.disabled, true);
    type.value = 'number'; event('question-type-toggled');
    assert.equal(controls.operand_type.disabled, false);
    assert.equal(controls.source_question_uuid.disabled, true);
    assert.equal(controls.comparison_value.disabled, true);
});
