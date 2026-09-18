const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function control(required = false) {
    return {
        checked: false,
        disabled: false,
        required: false,
        value: '',
        listeners: {},
        addEventListener(name, listener) { this.listeners[name] = listener; },
        dispatch(name) { this.listeners[name]?.({ type: name }); },
        hasAttribute(name) { return name === 'data-tax-required' && required; },
    };
}

function taxEditor() {
    const enabled = control();
    const taxId = control(true);
    const mode = control(true);
    const name = control(true);
    const percentage = control(true);
    const configuration = {
        hidden: false,
        querySelectorAll() { return [taxId, mode, name, percentage]; },
    };
    const rows = {
        dataset: { nextIndex: '1' },
        listeners: {},
        addEventListener(event, listener) { this.listeners[event] = listener; },
        querySelectorAll() { return [{}]; },
        appendChild() {},
    };
    const add = control();
    const template = { innerHTML: '' };
    const elements = {
        collects_taxes: enabled,
        'tax-configuration': configuration,
        'organization-tax-rows': rows,
        'add-organization-tax': add,
        'organization-tax-row-template': template,
    };
    const document = {
        getElementById(id) { return elements[id] ?? null; },
        createElement() { return { innerHTML: '', firstElementChild: {} }; },
    };
    const source = fs.readFileSync(path.join(__dirname, '../../resources/views/organizations/partials/form.blade.php'), 'utf8');
    const script = [...source.matchAll(/<script>\s*([\s\S]*?)<\/script>/g)]
        .map(match => match[1])
        .find(candidate => candidate.includes("document.getElementById('collects_taxes')"));
    vm.runInNewContext(script, { document });

    return { enabled, configuration, controls: [taxId, mode, name, percentage] };
}

test('tax details are required and enabled only while tax collection is selected', () => {
    const editor = taxEditor();

    assert.equal(editor.configuration.hidden, true);
    for (const control of editor.controls) {
        assert.equal(control.disabled, true);
        assert.equal(control.required, false);
    }

    editor.enabled.checked = true;
    editor.enabled.dispatch('change');

    assert.equal(editor.configuration.hidden, false);
    for (const control of editor.controls) {
        assert.equal(control.disabled, false);
        assert.equal(control.required, true);
    }
});
