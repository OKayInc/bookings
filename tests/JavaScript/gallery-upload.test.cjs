const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/gallery-upload.js'), 'utf8');
function setup(files, replies = []) {
    const status = {}, refresh = {hidden: true, addEventListener() {}}, button = {disabled: false};
    const input = {files, disabled: false}, token = {value: 'csrf'}, placement = {value: 'above'};
    const calls = []; let submit, reloads = 0;
    const form = {
        dataset: {maxFiles: '6', maxBytes: '5000'}, action: '/upload',
        querySelector: s => ({'input[type="file"]': input, '[data-upload-status]': status,
            '[data-upload-refresh]': refresh, '[name="_token"]': token, '[name="placement"]': placement,
            'button[type="submit"]': button})[s],
        querySelectorAll: () => [input, button], addEventListener: (_, fn) => {submit = fn;},
    };
    class Body {constructor() {this.fields = [];} append(...args) {this.fields.push(args);}}
    vm.runInNewContext(source, {
        document: {querySelectorAll: () => [form]}, FormData: Body,
        window: {location: {reload() {reloads++;}}},
        fetch: async (_, options) => {
            calls.push(options);
            const reply = replies[calls.length - 1] || {count: 1};
            return {ok: !reply.errors, status: reply.errors ? 422 : 200, json: async () => reply};
        },
    });
    return {submit: () => submit({preventDefault() {}}), calls, status, refresh, button, reloads: () => reloads};
}
test('six photos are sent in six separate requests in selection order', async () => {
    const files = Array.from({length: 6}, (_, i) => ({name: `${i}.jpg`, size: 4000}));
    const app = setup(files); await app.submit();
    assert.equal(app.calls.length, 6);
    app.calls.forEach((call, i) => {
        const photos = call.body.fields.filter(field => field[0] === 'photos[]');
        assert.equal(photos.length, 1); assert.equal(photos[0][1], files[i]);
    });
    assert.equal(app.reloads(), 1);
});
test('a partial failure stops the queue and retains confirmed progress', async () => {
    const app = setup(Array.from({length: 3}, (_, i) => ({name: `${i}.jpg`, size: 100})),
        [{count: 1}, {errors: {photos: ['Gallery full.']}}]);
    await app.submit();
    assert.equal(app.calls.length, 2); assert.equal(app.reloads(), 0);
    assert.match(app.status.textContent, /1 of 3 uploads confirmed/);
    assert.match(app.status.textContent, /Gallery full/);
    assert.equal(app.refresh.hidden, false); assert.equal(app.button.disabled, true);
});
test('oversized individual files are rejected before any upload', async () => {
    const app = setup([{name: 'large.jpg', size: 5001}]); await app.submit();
    assert.equal(app.calls.length, 0); assert.match(app.status.textContent, /large.jpg is too large/);
});
test('selection count is checked before uploading', async () => {
    const app = setup(Array.from({length: 7}, () => ({name: 'a.jpg', size: 100})));
    await app.submit(); assert.equal(app.calls.length, 0);
    assert.match(app.status.textContent, /between 1 and 6/);
});
