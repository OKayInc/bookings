@switch($network)
    @case('facebook')
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" focusable="false"><path d="M14 8.5V7c0-.8.5-1 1-1h3V2.1C17.5 2 15.7 2 13.7 2 9.8 2 7 4.4 7 8.8V11H3v4h4v7h5v-7h4l.7-4H12V9c0-.4.3-.5.7-.5H14Z"/></svg>
        @break
    @case('instagram')
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.4" cy="6.7" r="1.2"/></svg>
        @break
    @case('x')
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" focusable="false"><path d="M4 3h5.1l3.8 5.2L17.4 3H20l-5.9 7.1L21 21h-5.1l-4.2-5.8L6.6 21H4l6.5-7.8L4 3Zm4 2 9 14h2L10 5H8Z"/></svg>
        @break
    @case('linkedin')
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" focusable="false"><path d="M5.5 8.2A2.6 2.6 0 1 0 5.5 3a2.6 2.6 0 0 0 0 5.2ZM3.3 21h4.4V9.5H3.3V21Zm7 0h4.4v-6.4c0-1.7.3-3.3 2.4-3.3 2 0 2.1 1.9 2.1 3.4V21h4.4v-7.1c0-3.5-.8-6.2-4.9-6.2-2 0-3.3 1.1-3.9 2.1h-.1V8.1h-4.2V21Z"/></svg>
        @break
    @case('tiktok')
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" focusable="false"><path d="M14 3h3.8c.3 2 1.5 3.3 3.2 3.7v3.8a9 9 0 0 1-3.2-1V16a6 6 0 1 1-6-6h.8v3.8a3 3 0 1 0 1.4 2.5V3Z"/></svg>
        @break
@endswitch
