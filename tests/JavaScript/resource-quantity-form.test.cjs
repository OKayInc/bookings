const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function control(value = '') {
    return {
        value, checked: false, disabled: false, required: false, listeners: {},
        addEventListener(name, listener) { this.listeners[name] = listener; },
        dispatch(name) { this.listeners[name]?.({ type: name }); },
    };
}

test('equipment quantity fields require the explicit tracking checkbox', () => {
    const type = control('equipment');
    const enabled = control();
    const inventory = control('20');
    const inventoryField = { hidden: false };
    const quantityField = { hidden: false };
    const elements = {
        'resource-type': type,
        'equipment-inventory-field': inventoryField,
        quantity_enabled: enabled,
        'equipment-inventory-quantity-field': quantityField,
        inventory_quantity: inventory,
    };
    const document = { getElementById(id) { return elements[id] ?? null; } };
    const source = fs.readFileSync(
        path.join(__dirname, '../../resources/views/resources/partials/form.blade.php'),
        'utf8',
    );
    const script = [...source.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)][0][1];

    vm.runInNewContext(script, { document });
    assert.equal(inventoryField.hidden, false);
    assert.equal(quantityField.hidden, true);
    assert.equal(inventory.disabled, true);

    enabled.checked = true;
    enabled.dispatch('change');
    assert.equal(quantityField.hidden, false);
    assert.equal(inventory.disabled, false);
    assert.equal(inventory.required, true);

    type.value = 'person';
    type.dispatch('change');
    assert.equal(inventoryField.hidden, true);
    assert.equal(enabled.disabled, true);
    assert.equal(inventory.disabled, true);
});
