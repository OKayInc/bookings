const { test } = require('node:test');
const assert = require('node:assert/strict');
const loader = require('../../public/js/page-loader.js');

const location = new URL('https://appointment.test/dashboard?range=week');

function element(attributes = {}) {
    return {
        getAttribute(name) {
            return Object.hasOwn(attributes, name) ? attributes[name] : null;
        },
        hasAttribute(name) {
            return Object.hasOwn(attributes, name);
        },
    };
}

function click(overrides = {}) {
    return {
        altKey: false,
        button: 0,
        ctrlKey: false,
        defaultPrevented: false,
        metaKey: false,
        shiftKey: false,
        ...overrides,
    };
}

test('normal same-origin links activate the page loader', () => {
    assert.equal(
        loader.shouldLoadForLink(element({ href: '/bookings' }), click(), location),
        true,
    );
});

test('same-page fragments, downloads and ignored links do not activate it', () => {
    for (const anchor of [
        element({ href: '#filters' }),
        element({ href: '/contracts/example', download: '' }),
        element({ href: '/bookings', 'data-page-loader-ignore': '' }),
        element({ href: '/bookings', target: '_blank' }),
    ]) {
        assert.equal(loader.shouldLoadForLink(anchor, click(), location), false);
    }
});

test('external, non-web and modified-click links do not activate it', () => {
    assert.equal(loader.shouldLoadForLink(element({ href: 'https://example.com/' }), click(), location), false);
    assert.equal(loader.shouldLoadForLink(element({ href: 'mailto:test@example.test' }), click(), location), false);
    assert.equal(loader.shouldLoadForLink(element({ href: '/bookings' }), click({ ctrlKey: true }), location), false);
    assert.equal(loader.shouldLoadForLink(element({ href: '/bookings' }), click({ button: 1 }), location), false);
    assert.equal(loader.shouldLoadForLink(element({ href: '/bookings' }), click({ defaultPrevented: true }), location), false);
});

test('same-origin forms activate the loader unless cancelled or explicitly excluded', () => {
    assert.equal(
        loader.shouldLoadForForm(element({ action: '/bookings', method: 'post' }), { defaultPrevented: false }, location),
        true,
    );
    assert.equal(
        loader.shouldLoadForForm(element({ action: '/bookings' }), { defaultPrevented: true }, location),
        false,
    );
    assert.equal(
        loader.shouldLoadForForm(element({ action: 'https://example.com/pay' }), { defaultPrevented: false }, location),
        false,
    );
    assert.equal(
        loader.shouldLoadForForm(element({ action: '/bookings', target: '_blank' }), { defaultPrevented: false }, location),
        false,
    );
    assert.equal(
        loader.shouldLoadForForm(element({ method: 'dialog' }), { defaultPrevented: false }, location),
        false,
    );
});

test('installation shows, restores and hides the accessible overlay', () => {
    const documentListeners = {};
    const browserListeners = {};
    const bodyAttributes = {};
    const overlayAttributes = { 'aria-hidden': 'true' };
    const overlay = {
        dataset: {},
        hidden: true,
        setAttribute(name, value) { overlayAttributes[name] = value; },
    };
    const browser = {
        location,
        document: {
            readyState: 'loading',
            body: {
                setAttribute(name, value) { bodyAttributes[name] = value; },
                removeAttribute(name) { delete bodyAttributes[name]; },
            },
            getElementById(id) { return id === 'page-loader' ? overlay : null; },
            addEventListener(name, listener) { documentListeners[name] = listener; },
        },
        addEventListener(name, listener) { browserListeners[name] = listener; },
    };

    loader.install(browser);
    assert.equal(overlay.hidden, false);
    assert.equal(overlayAttributes['aria-hidden'], 'false');
    assert.equal(bodyAttributes['aria-busy'], 'true');

    browserListeners.load();
    assert.equal(overlay.hidden, true);
    assert.equal(overlayAttributes['aria-hidden'], 'true');
    assert.equal(bodyAttributes['aria-busy'], undefined);

    documentListeners.click({
        ...click(),
        target: { closest: () => element({ href: '/bookings' }) },
    });
    assert.equal(overlay.hidden, false);

    browserListeners.pageshow();
    assert.equal(overlay.hidden, true);
});
