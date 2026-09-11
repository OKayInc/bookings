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

    vm.runInNewContext(source, {window});

    assert.equal(configuration.selector, 'textarea[data-rich-text-editor]');
    assert.equal(configuration.license_key, 'gpl');
    assert.match(configuration.toolbar, /bold italic underline strikethrough/);
    assert.doesNotMatch(configuration.toolbar, /link|image|media/);
    assert.doesNotMatch(configuration.valid_elements, /\ba\b|href|img|src|style/);
    assert.match(configuration.invalid_elements, /iframe/);
    assert.match(configuration.invalid_elements, /script/);
    assert.equal(configuration.automatic_uploads, false);
    assert.equal(configuration.paste_data_images, false);
});
