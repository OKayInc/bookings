(() => {
    const selector = document.querySelector('[data-appointment-selector]');
    if (!selector) return;

    const links = [...selector.querySelectorAll('[data-appointment-type]')];
    const panels = [...document.querySelectorAll('[data-appointment-panel]')];
    const announcement = document.querySelector('[data-appointment-announcement]');
    const available = new Set(links.map(link => link.dataset.appointmentType));
    const first = links[0]?.dataset.appointmentType;

    function select(slug, announce = false) {
        const chosen = available.has(slug) ? slug : first;
        if (!chosen) return;

        links.forEach(link => {
            if (link.dataset.appointmentType === chosen) {
                link.setAttribute('aria-current', 'true');
                if (announce) announcement.textContent = `Showing ${link.textContent.trim()}`;
            } else {
                link.removeAttribute('aria-current');
            }
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.appointmentPanel !== chosen; });
    }

    selector.addEventListener('click', event => {
        const link = event.target.closest('[data-appointment-type]');
        if (!link || !selector.contains(link) || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        if (link.getAttribute('aria-current') === 'true') return;
        select(link.dataset.appointmentType, true);
        history.pushState(null, '', link.href);
    });

    window.addEventListener('popstate', () => {
        select(new URL(location.href).searchParams.get('type'));
    });
})();
