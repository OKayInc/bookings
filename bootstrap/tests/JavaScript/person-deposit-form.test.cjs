const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
for (const initial of ['person', 'equipment', 'room', 'vehicle', 'other']) {
    test(`deposit visibility and clearing from ${initial}`, () => {
        const listeners = [];
        const type = { value: initial, addEventListener: (_, fn) => listeners.push(fn) };
        const field = {};
        const deposit = { value: '125.00' };
        const elements = { 'resource-type': type, 'resource-deposit-field': field, default_deposit: deposit };
        const source = fs.readFileSync(path.join(__dirname, '../../resources/views/resources/partials/form.blade.php'), 'utf8');
        const script = [...source.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)][0][1];
        vm.runInNewContext(script, { document: { getElementById: id => elements[id] ?? null } });
        assert.equal(field.hidden, initial === 'person');
        assert.equal(deposit.disabled, initial === 'person');
        type.value = 'person'; listeners.forEach(fn => fn());
        assert.equal(field.hidden, true);
        assert.equal(deposit.disabled, true);
        assert.equal(deposit.value, '0');
        type.value = 'equipment'; listeners.forEach(fn => fn());
        assert.equal(field.hidden, false);
        assert.equal(deposit.disabled, false);
        assert.equal(deposit.value, '0');
    });
}
