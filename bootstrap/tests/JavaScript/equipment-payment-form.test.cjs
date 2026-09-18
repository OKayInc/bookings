const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function control(value = '') {
    return {
        value, checked: false, disabled: false, listeners: {},
        addEventListener(name, listener) { this.listeners[name] = listener; },
        dispatch(name) { this.listeners[name]?.({ type: name }); },
    };
}

function section(controls = []) {
    return {
        style: {},
        querySelectorAll() { return controls; },
    };
}

function paymentEditor() {
    const basePricing = control('free');
    const collection = control('full');
    const retainerType = control('fixed');
    const retainerAmount = control('');
    const retainerPercentage = control('');
    const equipmentPricing = control('bundles');
    const equipmentSelected = control();
    equipmentSelected.checked = true;
    const card = { querySelector() { return equipmentSelected; } };
    equipmentPricing.closest = () => card;
    const policy = section();
    const freeHelp = section();
    const retainer = section([retainerType, retainerAmount, retainerPercentage]);
    const fixed = section([retainerAmount]);
    const percentage = section([retainerPercentage]);
    const elements = {
        pricing_mode: basePricing,
        'payment-policy-fields': policy,
        'free-payment-help': freeHelp,
        payment_collection_mode: collection,
        'retainer-fields': retainer,
        retainer_type: retainerType,
        'retainer-fixed-fields': fixed,
        'retainer-percentage-fields': percentage,
        retainer_amount: retainerAmount,
        retainer_percentage: retainerPercentage,
    };
    const document = {
        getElementById(id) { return elements[id]; },
        querySelectorAll(selector) { return selector === '[data-equipment-pricing-mode]' ? [equipmentPricing] : []; },
    };
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/appointment-types/partials/form.blade.php'), 'utf8');
    const script = [...source.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)]
        .map(match => match[1])
        .find(candidate => candidate.includes('equipmentPricing = Array.from'));
    vm.runInNewContext(script, { document });

    return { policy, freeHelp, equipmentSelected };
}

test('paid equipment exposes payment collection even when the appointment base is free', () => {
    const editor = paymentEditor();
    assert.equal(editor.policy.style.display, 'block');
    assert.equal(editor.freeHelp.style.display, 'none');

    editor.equipmentSelected.checked = false;
    editor.equipmentSelected.dispatch('change');
    assert.equal(editor.policy.style.display, 'none');
    assert.equal(editor.freeHelp.style.display, 'block');
});
