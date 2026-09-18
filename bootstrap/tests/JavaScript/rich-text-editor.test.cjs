const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('rich text editor permits safe local formatting, colours, and lists', () => {
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
    assert.match(configuration.toolbar, /forecolor/);
    assert.match(configuration.toolbar, /bullist numlist/);
    assert.doesNotMatch(configuration.toolbar, /link|image|media|blocks|blockquote/);
    assert.match(configuration.plugins, /lists/);
    assert.equal(configuration.formats.bold.inline, 'strong');
    assert.equal(configuration.formats.italic.inline, 'em');
    assert.equal(configuration.formats.underline.inline, 'u');
    assert.equal(configuration.formats.strikethrough.inline, 's');
    assert.equal(configuration.formats.superscript.inline, 'sup');
    assert.equal(configuration.formats.subscript.inline, 'sub');
    assert.equal(configuration.formats.forecolor.inline, 'span');
    assert.equal(configuration.formats.forecolor.styles.color, '%value');
    assert.match(configuration.valid_elements, /span\[style\]/);
    assert.match(configuration.valid_elements, /ul,ol,li/);
    assert.equal(configuration.valid_styles.span, 'color');
    assert.doesNotMatch(configuration.valid_elements, /\ba\b|href|img|src|h[1-6]|blockquote/);
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
