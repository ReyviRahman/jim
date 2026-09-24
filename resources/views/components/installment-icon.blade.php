@props(['name'])
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('search') <circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/> @break
        @case('user') <circle cx="12" cy="7" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/> @break
        @case('package') <rect x="3" y="6" width="18" height="15" rx="3"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M3 12h18m-9-2v4M7 17h2m6 0h2"/> @break
        @case('money') <circle cx="12" cy="12" r="9"/><path d="M15 8.5c-1-2-6-2-6 1 0 3 6 1 6 4 0 3-5 3-6 1M12 5v14"/> @break
        @case('card') <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M6 15h3m3 0h2"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/> @break
        @case('chevron') <path d="m9 5 7 7-7 7"/> @break
    @endswitch
</svg>
