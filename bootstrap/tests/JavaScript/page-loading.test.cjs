const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('page loading overlay hides when ready and appears for navigation and form submission', () => {
    const listeners = {};
    const windowListeners = {};
    const classes = new Set();
    const label = { textContent: 'Loading…' };
    const overlay = { hidden: false, querySelector: () => label };

    class Element {
        closest() { return null; }
    }
    class HTMLFormElement extends Element {
        constructor() {
            super();
            this.target = '';
        }
    }

    const document = {
        body: {
            classList: {
                add: (name) => classes.add(name),
                remove: (name) => classes.delete(name),
            },
        },
        getElementById: (id) => id === 'page-loading-overlay' ? overlay : null,
        addEventListener: (name, callback) => { listeners[name] = callback; },
    };
    const window = {
        location: {href: 'https://example.test/dashboard', origin: 'https://example.test'},
        addEventListener: (name, callback) => { windowListeners[name] = callback; },
    };
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/page-loading.js'), 'utf8');
    vm.runInNewContext(source, {document, window, URL, Element, HTMLFormElement});

    listeners.DOMContentLoaded();
    assert.equal(overlay.hidden, true);

    listeners.submit({target: new HTMLFormElement()});
    assert.equal(overlay.hidden, false);
    assert.equal(label.textContent, 'Processing…');
    assert.equal(classes.has('page-is-loading'), true);

    windowListeners.pageshow();
    assert.equal(overlay.hidden, true);

    const link = {href: 'https://example.test/bookings', target: '', hasAttribute: () => false};
    const target = new Element();
    target.closest = () => link;
    listeners.click({
        target,
        defaultPrevented: false,
        button: 0,
        metaKey: false,
        ctrlKey: false,
        shiftKey: false,
        altKey: false,
    });
    assert.equal(overlay.hidden, false);
    assert.equal(label.textContent, 'Loading…');
});
