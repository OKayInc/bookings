(() => {
    if (typeof window.tinymce === 'undefined') {
        return;
    }

    const baseUrl = document.currentScript?.dataset.tinymceBaseUrl;

    window.tinymce.init({
        selector: 'textarea[data-rich-text-editor]',
        ...(baseUrl ? {base_url: baseUrl} : {}),
        license_key: 'gpl',
        plugins: 'lists wordcount',
        menubar: false,
        promotion: false,
        branding: false,
        height: 360,
        toolbar: [
            'undo redo | blocks',
            'bold italic underline strikethrough | superscript subscript',
            'bullist numlist blockquote | removeformat',
        ].join(' | '),
        block_formats: 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4',
        valid_elements: 'p,br,strong/b,em/i,u,s/strike,sub,sup,h2,h3,h4,blockquote,ul,ol,li',
        invalid_elements: 'a,area,audio,base,embed,form,iframe,img,input,link,math,meta,object,script,source,style,svg,template,track,video',
        allow_html_in_comments: false,
        automatic_uploads: false,
        paste_data_images: false,
        object_resizing: false,
        content_style: 'body { font-family: system-ui, sans-serif; font-size: 16px; }',
        setup: (editor) => {
            const save = () => editor.save();

            editor.on('input change undo redo', save);
            editor.on('init', () => {
                const textarea = editor.getElement();

                textarea.addEventListener('invalid', (event) => {
                    event.preventDefault();
                    editor.notificationManager.open({
                        text: 'This field is required.',
                        type: 'error',
                    });
                    editor.focus();
                });
            });
        },
    });
})();
