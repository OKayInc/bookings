(() => {
    'use strict';
    document.querySelectorAll('[data-gallery-upload]').forEach(form => {
        const input = form.querySelector('input[type="file"]');
        const status = form.querySelector('[data-upload-status]');
        const refresh = form.querySelector('[data-upload-refresh]');
        let busy = false;
        let needsRefresh = false;
        refresh.addEventListener('click', event => {
            event.preventDefault();
            window.location.reload();
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || needsRefresh) return;
            const files = [...input.files];
            const maxFiles = Number(form.dataset.maxFiles);
            const maxBytes = Number(form.dataset.maxBytes);
            if (!files.length || files.length > maxFiles) {
                status.textContent = `Select between 1 and ${maxFiles} photos.`;
                return;
            }
            const tooLarge = files.find(file => file.size > maxBytes);
            if (tooLarge) {
                status.textContent = `${tooLarge.name} is too large. The current limit is ${(maxBytes / 1048576).toFixed(1)} MB per photo. Choose a smaller image or ask the administrator to raise the upload limits.`;
                return;
            }
            // Snapshot the placement/token before disabling controls.
            const token = form.querySelector('[name="_token"]').value;
            const placement = form.querySelector('[name="placement"]').value;
            const controls = [...form.querySelectorAll('input, select, button')];
            const disabled = controls.map(control => control.disabled);
            busy = true;
            controls.forEach(control => { control.disabled = true; });
            let completed = 0;
            try {
                for (const file of files) {
                    status.textContent = `Uploading ${completed + 1} of ${files.length}: ${file.name}…`;
                    const body = new FormData();
                    body.append('_token', token);
                    body.append('placement', placement);
                    body.append('photos[]', file, file.name);
                    const response = await fetch(form.action, {
                        method: 'POST', body, credentials: 'same-origin',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || response.redirected || !data || data.count !== 1) {
                        const errors = data?.errors ? Object.values(data.errors).flat().join(' ') : null;
                        const fallback = response.status === 413
                            ? 'The server rejected the file size. Choose a smaller image or increase the server upload limits.'
                            : response.status === 419 || response.redirected
                                ? 'Your session may have expired. Refresh this page before trying again.'
                                : 'The upload could not be confirmed. Refresh to check which photos were saved before retrying.';
                        throw new Error(`${file.name}: ${errors || data?.message || fallback}`);
                    }
                    completed += 1;
                }
                status.textContent = `${completed} photos uploaded. Refreshing…`;
                window.location.reload();
            } catch (error) {
                needsRefresh = true;
                status.textContent = `${completed} of ${files.length} uploads confirmed. ${error.message} Successfully uploaded photos are kept. Refresh before retrying.`;
                refresh.hidden = false;
            } finally {
                busy = false;
                controls.forEach((control, i) => { control.disabled = disabled[i]; });
                // Require a fresh count and selection after a partial batch.
                if (needsRefresh || completed > 0) form.querySelector('button[type="submit"]').disabled = true;
            }
        });
    });
})();
