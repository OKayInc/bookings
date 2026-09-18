(() => {
    document.querySelectorAll('[data-photo-gallery]').forEach(gallery => {
        const photos = [...gallery.querySelectorAll('[data-gallery-photo]')];
        const dialog = gallery.querySelector('.photo-gallery-lightbox');
        const enlarged = gallery.querySelector('[data-gallery-enlarged]');
        const counter = gallery.querySelector('[data-gallery-counter]');
        let currentIndex = 0;

        if (!dialog || !enlarged || photos.length === 0) return;

        const show = index => {
            currentIndex = (index + photos.length) % photos.length;
            const selected = photos[currentIndex];
            enlarged.src = selected.dataset.fullSrc;
            enlarged.alt = selected.dataset.alt || '';
            if (counter) counter.textContent = `${currentIndex + 1} / ${photos.length}`;
        };

        const open = index => {
            show(index);
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', '');
        };

        const close = () => {
            if (typeof dialog.close === 'function') dialog.close();
            else dialog.removeAttribute('open');
            enlarged.removeAttribute('src');
        };

        photos.forEach((photo, index) => photo.addEventListener('click', () => open(index)));
        dialog.querySelector('[data-gallery-close]')?.addEventListener('click', close);
        dialog.querySelector('[data-gallery-previous]')?.addEventListener('click', () => show(currentIndex - 1));
        dialog.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(currentIndex + 1));
        dialog.addEventListener('click', event => {
            if (event.target === dialog) close();
        });
        dialog.addEventListener('close', () => enlarged.removeAttribute('src'));
        dialog.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft') show(currentIndex - 1);
            if (event.key === 'ArrowRight') show(currentIndex + 1);
        });
    });
})();
