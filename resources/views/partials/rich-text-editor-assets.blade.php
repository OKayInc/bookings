@once
    @push('scripts')
        <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}?v=8.9.1"></script>
        <script src="{{ asset('js/rich-text-editor.js') }}?v=4" data-tinymce-base-url="{{ asset('vendor/tinymce') }}"></script>
    @endpush
@endonce
