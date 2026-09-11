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
        toolbar: 'undo redo | bold italic underline strikethrough | superscript subscript | forecolor | bullist numlist | removeformat',
        formats: {
            bold: {inline: 'strong'},
            italic: {inline: 'em'},
            underline: {inline: 'u'},
            strikethrough: {inline: 's'},
            superscript: {inline: 'sup'},
            subscript: {inline: 'sub'},
            forecolor: {inline: 'span', styles: {color: '%value'}},
        },
        valid_elements: 'p,br,strong/b,em/i,u,s/strike,sub,sup,span[style],ul,ol,li',
        valid_styles: {span: 'color'},
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
