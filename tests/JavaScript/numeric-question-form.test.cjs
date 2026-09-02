const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const engine = require('../../public/js/numeric-question-constraints.js');
const visibility = require('../../public/js/question-visibility.js');

// Exercise the actual checkout event handlers with a small DOM stub. This is
// deliberately not a replacement for a rendered-browser accessibility test.
function checkout({ sourceConditional = false, targetConditional = false, attendeeCount = '1', attendeeRule = false } = {}) {
    const makeControl = (uuid, value, type = 'number') => ({
        type, name: `answers[${uuid}]`, value, checked: false, disabled: false, required: false,
        dataset: {}, attributes: {}, validationMessage: '',
        setCustomValidity(message) { this.validationMessage = message; },
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
    });
    const makeQuestion = (uuid, value, constraints = [], conditional = false, type = 'number') => {
        const control = makeControl(uuid, value, type), error = { textContent: '' };
        return {
            control, error, hidden: false, attributes: {},
            dataset: {
                questionUuid: uuid, questionType: type,
                visibilityConditions: JSON.stringify(conditional ? [{ boolean_operator: 'and', source_question_uuid: 'choice', question_option_uuid: 'yes' }] : []),
                numericConstraints: JSON.stringify(constraints), numericMessage: 'Q2 must be at least Q1.',
            },
            querySelectorAll() { return [control]; },
            querySelector(selector) { return selector === '.numeric-constraint-error' ? error : control; },
            setAttribute(key, value) { this.attributes[key] = value; },
        };
    };
    const choice = makeQuestion('choice', 'yes', [], false, 'radio');
    const source = makeQuestion('q1', '5', [], sourceConditional);
    const target = makeQuestion('q2', '4', [{ boolean_operator: 'and', comparison_operator: '>=', operand_type: attendeeRule ? 'attendee_count' : 'question', source_question_uuid: 'q1' }], targetConditional);
    const elements = [choice, source, target], handlers = {};
    const form = { dataset: { attendeeCount }, addEventListener(name, fn) { handlers[name] = fn; } };
    const total = {}, lines = {};
    const document = {
        querySelector() { return form; }, querySelectorAll() { return elements; },
        getElementById(id) { return id === 'questionnaire-total' ? total : lines; },
        createElement() { return { textContent: '', get innerHTML() { return this.textContent; } }; },
    };
    class FormData {
        constructor() { this.values = [['_token', 'test']]; }
        append(key, value) { this.values.push([key, value]); }
        entries() { return this.values; }
        get(key) { return this.values.find(row => row[0] === key)?.[1]; }
    }
    const file = fs.readFileSync(path.join(__dirname, '../../resources/views/public/bookings/details.blade.php'), 'utf8');
    const script = file.match(/<script>\s*([\s\S]*?)<\/script>/)[1]
        .replace(/@json\(route\('public\.booking-holds\.quote',\$holdToken\)\)/g, '"/quote"');
    vm.runInNewContext(script, {
        document, FormData, File: class {}, NumericQuestionConstraints: engine, QuestionVisibility: visibility,
        fetch: async () => ({ ok: true, json: async () => ({ total_display: '$100', lines: [] }) }),
        setTimeout() { return 1; }, clearTimeout() {},
    });
    return { choice, source, target, event: (name, control) => handlers[name]({ target: control }) };
}

test('checkout revalidates Q2 when Q1 or Q2 changes and exposes inline feedback', () => {
    const { source, target, event } = checkout();
    assert.match(target.control.validationMessage, /at least Q1/);
    assert.equal(target.error.textContent, target.control.validationMessage);
    assert.equal(target.control.attributes['aria-invalid'], 'true');
    target.control.value = '6'; event('input', target.control);
    assert.equal(target.control.validationMessage, '');
    source.control.value = '7'; event('input', source.control);
    assert.match(target.control.validationMessage, /at least Q1/);
    source.control.value = ''; event('input', source.control);
    assert.match(target.control.validationMessage, /at least Q1/);
    target.control.value = ''; event('input', target.control);
    assert.equal(target.control.validationMessage, '');
});

test('hidden source answers cannot satisfy constraints and becoming visible revalidates', () => {
    const { choice, source, target, event } = checkout({ sourceConditional: true });
    assert.equal(source.hidden, true);
    assert.equal(source.control.value, '');
    assert.match(target.control.validationMessage, /at least Q1/);
    choice.control.checked = true; event('change', choice.control);
    source.control.value = '3'; event('input', source.control);
    assert.equal(target.control.validationMessage, '');
    choice.control.checked = false; event('change', choice.control);
    assert.match(target.control.validationMessage, /at least Q1/);
});

test('hiding a constrained target clears its custom error and prevents submission of stale answers', () => {
    const { choice, target, event } = checkout({ targetConditional: true });
    assert.equal(target.hidden, true);
    assert.equal(target.control.disabled, true);
    assert.equal(target.control.validationMessage, '');
    choice.control.checked = true; event('change', choice.control);
    target.control.value = '4'; event('input', target.control);
    assert.match(target.control.validationMessage, /at least Q1/);
    choice.control.checked = false; event('change', choice.control);
    assert.equal(target.control.value, '');
    assert.equal(target.control.validationMessage, '');
    assert.equal(target.error.textContent, '');
});

test('checkout uses the rendered held attendee count instead of another answer', () => {
    const { source, target, event } = checkout({ attendeeCount: '3', attendeeRule: true });
    assert.equal(target.control.validationMessage, ''); // 4 >= 3, even though Q1 is 5.
    source.control.value = '100'; event('input', source.control);
    assert.equal(target.control.validationMessage, '');
    target.control.value = '2'; event('input', target.control);
    assert.notEqual(target.control.validationMessage, '');
    target.control.value = '3'; event('input', target.control);
    assert.equal(target.control.validationMessage, '');
});

test('hidden attendee-constrained target clears validity errors', () => {
    const { choice, target, event } = checkout({ attendeeCount: '3', attendeeRule: true, targetConditional: true });
    assert.equal(target.control.validationMessage, '');
    choice.control.checked = true; event('change', choice.control);
    target.control.value = '2'; event('input', target.control);
    assert.notEqual(target.control.validationMessage, '');
    choice.control.checked = false; event('change', choice.control);
    assert.equal(target.control.validationMessage, '');
    assert.equal(target.control.disabled, true);
});
