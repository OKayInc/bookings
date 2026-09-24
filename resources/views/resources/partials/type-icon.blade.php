@php
    $labels = [
        'person' => 'Person',
        'room' => 'Room',
        'equipment' => 'Equipment',
        'vehicle' => 'Vehicle',
        'other' => 'Other',
    ];
    $label = $labels[$type] ?? ucfirst((string) $type);
@endphp

@switch($type)
    @case('person')
        <span class="d-inline-flex align-items-center justify-content-center" title="{{ $label }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <circle cx="12" cy="8" r="3"></circle>
                <path d="M5.5 20c.8-4 3-6 6.5-6s5.7 2 6.5 6"></path>
            </svg>
            <span class="visually-hidden">{{ $label }}</span>
        </span>
        @break

    @case('room')
        <span class="d-inline-flex align-items-center justify-content-center" title="{{ $label }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M5 21V4h12v17"></path>
                <path d="M9 21V8h8v13"></path>
                <circle cx="14" cy="14.5" r=".7" fill="currentColor" stroke="none"></circle>
                <path d="M3 21h18"></path>
            </svg>
            <span class="visually-hidden">{{ $label }}</span>
        </span>
        @break

    @case('equipment')
        <span class="d-inline-flex align-items-center justify-content-center" title="{{ $label }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M4 9h16v10H4z"></path>
                <path d="M9 9V6h6v3"></path>
                <path d="M4 13h16"></path>
                <path d="M10 13v2h4v-2"></path>
            </svg>
            <span class="visually-hidden">{{ $label }}</span>
        </span>
        @break

    @case('vehicle')
        <span class="d-inline-flex align-items-center justify-content-center" title="{{ $label }}">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M4 15.5V13l2-5h12l2 5v2.5"></path>
                <path d="M3 15.5h18v3H3z"></path>
                <circle cx="7" cy="18.5" r="1.5"></circle>
                <circle cx="17" cy="18.5" r="1.5"></circle>
                <path d="M6 13h12"></path>
            </svg>
            <span class="visually-hidden">{{ $label }}</span>
        </span>
        @break

    @default
        <span>{{ $label }}</span>
@endswitch
