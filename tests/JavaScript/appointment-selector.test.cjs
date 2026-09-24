const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('switching appointments updates the visible panel, URL, and browser history', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/appointment-selector.js'), 'utf8');
    let address = 'https://appointment.to/o/demo?type=alpha';
    let clickHandler;
    let popstateHandler;
    const links = ['alpha', 'bravo'].map(slug => ({
        dataset: {appointmentType: slug},
        href: `https://appointment.to/o/demo?type=${slug}`,
        textContent: slug,
        current: slug === 'alpha',
        setAttribute(key) { if (key === 'aria-current') this.current = true; },
        removeAttribute(key) { if (key === 'aria-current') this.current = false; },
        getAttribute(key) { return key === 'aria-current' && this.current ? 'true' : null; },
    }));
    const panels = ['alpha', 'bravo'].map(slug => ({dataset: {appointmentPanel: slug}, hidden: slug !== 'alpha'}));
    const announcement = {textContent: ''};
    const selector = {
        querySelectorAll: () => links,
        addEventListener: (_name, fn) => { clickHandler = fn; },
        contains: link => links.includes(link),
    };
    const context = {
        document: {
            querySelector: key => key === '[data-appointment-selector]' ? selector : announcement,
            querySelectorAll: () => panels,
        },
        window: {addEventListener: (_name, fn) => { popstateHandler = fn; }},
        history: {pushState: (_state, _unused, url) => { address = url; }},
        location: {get href() { return address; }},
        URL,
        Set,
    };
    vm.runInNewContext(source, context);

    let prevented = false;
    const click = (link, extra = {}) => clickHandler({
        target: {closest: () => link}, button: 0, defaultPrevented: false,
        preventDefault: () => { prevented = true; }, ...extra,
    });
    click(links[1]);
    assert.equal(prevented, true);
    assert.equal(address, links[1].href);
    assert.deepEqual(panels.map(panel => panel.hidden), [true, false]);
    assert.deepEqual(links.map(link => link.current), [false, true]);
    assert.equal(announcement.textContent, 'Showing bravo');

    address = links[0].href;
    popstateHandler();
    assert.deepEqual(panels.map(panel => panel.hidden), [false, true]);

    prevented = false;
    click(links[1], {ctrlKey: true});
    assert.equal(prevented, false);
    assert.equal(address, links[0].href);
});
