@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-6']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('tag')
            <path d="m3 12 9-9h8l1 8-9 10-9-9Z"/><circle cx="16.5" cy="7.5" r="1"/>
            @break
        @case('payment')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h5"/>
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/>
            @break
        @case('person')
            <circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
            @break
        @case('chart')
            <path d="M3 21h18M6 17v-6m6 6V6m6 11V2" stroke-width="3"/>
            @break
        @case('chevron')
            <path d="m9 5 7 7-7 7"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>
            @break
        @default
            <path d="M7 12h10M3 9H1v6h2m18-6h2v6h-2"/><rect x="3" y="5" width="4" height="14" rx="1"/><rect x="17" y="5" width="4" height="14" rx="1"/>
    @endswitch
</svg>
