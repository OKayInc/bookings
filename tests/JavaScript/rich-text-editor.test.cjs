const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('rich text editor only permits local typographic and structural markup', () => {
    let configuration = null;
    const window = {
        tinymce: {
            init: (value) => { configuration = value; },
        },
    };
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/rich-text-editor.js'), 'utf8');

    const document = {
        currentScript: {dataset: {tinymceBaseUrl: '/vendor/tinymce'}},
    };

    vm.runInNewContext(source, {document, window});

    assert.equal(configuration.selector, 'textarea[data-rich-text-editor]');
    assert.equal(configuration.license_key, 'gpl');
    assert.equal(configuration.base_url, '/vendor/tinymce');
    assert.match(configuration.toolbar, /bold italic underline strikethrough/);
    assert.doesNotMatch(configuration.toolbar, /link|image|media/);
    assert.doesNotMatch(configuration.valid_elements, /\ba\b|href|img|src|style/);
    assert.match(configuration.invalid_elements, /iframe/);
    assert.match(configuration.invalid_elements, /script/);
    assert.equal(configuration.automatic_uploads, false);
    assert.equal(configuration.paste_data_images, false);
    assert.equal(typeof configuration.setup, 'function');

    const editorEvents = {};
    const textareaEvents = {};
    let saveCount = 0;
    let focused = false;
    let notification = null;
    const editor = {
        on: (names, callback) => names.split(' ').forEach((name) => { editorEvents[name] = callback; }),
        save: () => { saveCount += 1; },
        getElement: () => ({addEventListener: (name, callback) => { textareaEvents[name] = callback; }}),
        notificationManager: {open: (value) => { notification = value; }},
        focus: () => { focused = true; },
    };

    configuration.setup(editor);
    editorEvents.input();
    assert.equal(saveCount, 1);
    editorEvents.init();

    let invalidPrevented = false;
    textareaEvents.invalid({preventDefault: () => { invalidPrevented = true; }});
    assert.equal(invalidPrevented, true);
    assert.equal(notification.text, 'This field is required.');
    assert.equal(focused, true);
});
