(() => {
    const format = document.getElementById('format');
    const body = document.getElementById('body');
    const base = document.currentScript.dataset.baseUrl;
    const configure = () => {
        if (!window.tinymce) return;
        const editor = tinymce.get('body');
        if (format.value === 'text') {
            if (editor) { editor.save(); editor.remove(); }
            return;
        }
        if (editor) return;
        tinymce.init({
            target: body, base_url: base, license_key: 'gpl', height: 360,
            plugins: 'lists', menubar: false, promotion: false, branding: false,
            toolbar: 'undo redo | bold italic underline strikethrough | forecolor | bullist numlist | removeformat',
            valid_elements: 'p,br,strong/b,em/i,u,s/strike,sub,sup,span[style],ul,ol,li',
            valid_styles: {span: 'color'}, paste_data_images: false,
            setup: editor => editor.on('change input undo redo', () => editor.save()),
        });
    };
    format.addEventListener('change', configure);
    configure();
    document.getElementById('email-template-form').addEventListener('submit', () => window.tinymce?.triggerSave());
    document.getElementById('preview-template').addEventListener('click', () => {
        window.tinymce?.triggerSave();
        const samples = {subject: 'Your booking ABC123', greeting: 'Hello Alex,', first_name: 'Alex', organization_name: 'Example organization', event_name: 'Example event', reference: 'ABC123', status: 'Confirmed', message: 'Booking: Example event\nStatus: Confirmed\nReference: ABC123\nEvent details appear here according to the email type.'};
        const escape = value => value.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const render = (value, html) => value.replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (_, key) => html ? escape(samples[key] || '').replace(/\n/g, '<br>') : samples[key] || '');
        document.getElementById('preview-subject').textContent = render(document.getElementById('subject').value, false);
        const preview = document.getElementById('email-preview');
        const html = format.value === 'html';
        // No network, scripts, forms or navigation in the draft preview.
        preview.srcdoc = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'"><body>' + (html ? render(body.value, true) : '<pre style="white-space:pre-wrap">' + escape(render(body.value, false)) + '</pre>') + '<p>Secure action link (when applicable)</p></body>';
        preview.hidden = false;
    });
})();
