const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '../../resources/views/questionnaire/partials/form.blade.php'),
    'utf8',
);
const script = [...source.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)]
    .map(match => match[1])
    .find(candidate => candidate.includes('function toggleQuestionPricing'));

function section(controls = []) {
    return {
        style: {},
        querySelectorAll() { return controls; },
    };
}

test('questionnaire editor inline script remains valid JavaScript', () => {
    assert.doesNotThrow(() => new vm.Script(script.replace('@json($dependencyPayload)', '[]')));
});

test('answer-rate mode forces per-unit pricing and exposes the rate input', () => {
    const functionSource = script.match(/function toggleQuestionPricing\(\)\{[\s\S]*?\n \}/)[0];
    const amount = { disabled: false };
    const percentage = { disabled: false };
    const pricingType = { value: 'rate' };
    const pricingApplication = { value: 'once', disabled: false };
    const pricingAmountField = section([amount]);
    const pricingPercentageField = section([percentage]);
    const pricingApplicationField = section();
    const pricingAmountLabel = { textContent: '' };
    const context = {
        type: { value: 'number' },
        pricingType,
        pricingApplication,
        pricingAmountField,
        pricingPercentageField,
        pricingApplicationField,
        pricingAmountLabel,
    };

    vm.runInNewContext(`${functionSource}; toggleQuestionPricing();`, context);

    assert.equal(pricingApplication.value, 'per_unit');
    assert.equal(pricingApplication.disabled, true);
    assert.equal(pricingApplicationField.style.display, 'none');
    assert.equal(pricingAmountField.style.display, '');
    assert.equal(amount.disabled, false);
    assert.equal(pricingPercentageField.style.display, 'none');
    assert.equal(percentage.disabled, true);
    assert.match(pricingAmountLabel.textContent, /Rate per answer unit/);
});
