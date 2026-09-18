const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('gallery opens full photos and supports next, previous, and close controls', () => {
    const events = {};
    const dialogEvents = {};
    const enlarged = {src: '', alt: '', removeAttribute: name => { if (name === 'src') enlarged.src = ''; }};
    const counter = {textContent: ''};
    const close = {addEventListener: (name, callback) => { events.close = callback; }};
    const previous = {addEventListener: (name, callback) => { events.previous = callback; }};
    const next = {addEventListener: (name, callback) => { events.next = callback; }};
    const dialog = {
        open: false,
        querySelector: selector => ({
            '[data-gallery-enlarged]': enlarged,
            '[data-gallery-counter]': counter,
            '[data-gallery-close]': close,
            '[data-gallery-previous]': previous,
            '[data-gallery-next]': next,
        })[selector] || null,
        addEventListener: (name, callback) => { dialogEvents[name] = callback; },
        showModal: () => { dialog.open = true; },
        close: () => { dialog.open = false; },
    };
    const photos = [
        {dataset: {fullSrc: '/one.webp', alt: 'One'}, addEventListener: (name, callback) => { events.photoOne = callback; }},
        {dataset: {fullSrc: '/two.webp', alt: 'Two'}, addEventListener: (name, callback) => { events.photoTwo = callback; }},
    ];
    const gallery = {
        querySelectorAll: selector => selector === '[data-gallery-photo]' ? photos : [],
        querySelector: selector => ({
            '.photo-gallery-lightbox': dialog,
            '[data-gallery-enlarged]': enlarged,
            '[data-gallery-counter]': counter,
        })[selector] || null,
    };
    const document = {querySelectorAll: selector => selector === '[data-photo-gallery]' ? [gallery] : []};
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/gallery.js'), 'utf8');

    vm.runInNewContext(source, {document});

    events.photoOne();
    assert.equal(dialog.open, true);
    assert.equal(enlarged.src, '/one.webp');
    assert.equal(enlarged.alt, 'One');
    assert.equal(counter.textContent, '1 / 2');

    events.next();
    assert.equal(enlarged.src, '/two.webp');
    assert.equal(counter.textContent, '2 / 2');

    dialogEvents.keydown({key: 'ArrowLeft'});
    assert.equal(enlarged.src, '/one.webp');

    events.close();
    assert.equal(dialog.open, false);
    assert.equal(enlarged.src, '');
});
