(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (root && root.document) {
        api.install(root);
    }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    function hasIgnoredTarget(element) {
        const target = (element.getAttribute('target') || '').trim().toLowerCase();

        return target !== '' && target !== '_self';
    }

    function isModifiedClick(event) {
        return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
    }

    function shouldLoadForLink(anchor, event, currentLocation) {
        if (!anchor || event.defaultPrevented || isModifiedClick(event)) {
            return false;
        }

        const href = (anchor.getAttribute('href') || '').trim();

        if (
            href === ''
            || href.startsWith('#')
            || anchor.hasAttribute('download')
            || anchor.hasAttribute('data-page-loader-ignore')
            || hasIgnoredTarget(anchor)
        ) {
            return false;
        }

        let destination;

        try {
            destination = new URL(href, currentLocation.href);
        } catch (_error) {
            return false;
        }

        if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== currentLocation.origin) {
            return false;
        }

        return !(
            destination.pathname === currentLocation.pathname
            && destination.search === currentLocation.search
            && destination.hash !== ''
        );
    }

    function shouldLoadForForm(form, event, currentLocation) {
        if (
            !form
            || event.defaultPrevented
            || form.hasAttribute('data-page-loader-ignore')
            || hasIgnoredTarget(form)
            || (form.getAttribute('method') || '').trim().toLowerCase() === 'dialog'
        ) {
            return false;
        }

        let destination;

        try {
            destination = new URL(form.getAttribute('action') || currentLocation.href, currentLocation.href);
        } catch (_error) {
            return false;
        }

        return ['http:', 'https:'].includes(destination.protocol)
            && destination.origin === currentLocation.origin;
    }

    function install(browser) {
        const document = browser.document;
        const loader = document.getElementById('page-loader');

        if (!loader || loader.dataset.pageLoaderInstalled === 'true') {
            return;
        }

        loader.dataset.pageLoaderInstalled = 'true';

        const show = function () {
            loader.hidden = false;
            loader.setAttribute('aria-hidden', 'false');
            document.body?.setAttribute('aria-busy', 'true');
        };
        const hide = function () {
            loader.hidden = true;
            loader.setAttribute('aria-hidden', 'true');
            document.body?.removeAttribute('aria-busy');
        };

        document.addEventListener('click', function (event) {
            const anchor = event.target?.closest?.('a[href]');

            if (shouldLoadForLink(anchor, event, browser.location)) {
                show();
            }
        });

        document.addEventListener('submit', function (event) {
            if (shouldLoadForForm(event.target, event, browser.location)) {
                show();
            }
        });

        // pagehide covers reloads and browser back/forward navigation without
        // registering beforeunload, which would reduce back-forward cache use.
        browser.addEventListener('pagehide', show);
        browser.addEventListener('pageshow', hide);
        browser.addEventListener('load', hide);

        // On a direct visit the server must deliver this markup first, but the
        // overlay can still cover the remaining asset-loading interval.
        if (document.readyState !== 'complete') {
            show();
        } else {
            hide();
        }
    }

    return {
        install,
        shouldLoadForForm,
        shouldLoadForLink,
    };
}));
