(() => {
    'use strict';

    const overlay = () => document.getElementById('page-loading-overlay');
    const show = (message = 'Loading…') => {
        const element = overlay();
        if (!element) return;
        const label = element.querySelector('[data-page-loading-message]');
        if (label) label.textContent = message;
        element.hidden = false;
        document.body.classList.add('page-is-loading');
    };
    const hide = () => {
        const element = overlay();
        if (element) element.hidden = true;
        document.body.classList.remove('page-is-loading');
    };

    document.addEventListener('DOMContentLoaded', () => {
        hide();

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.target === '_blank') return;
            show('Processing…');
        });

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (!(event.target instanceof Element)) return;
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download')) return;

            let destination;
            try {
                destination = new URL(link.href, window.location.href);
            } catch (_) {
                return;
            }
            if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== window.location.origin) return;
            if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;
            show('Loading…');
        });
    }, {once: true});

    window.addEventListener('pageshow', hide);
    window.addEventListener('load', hide);
})();
