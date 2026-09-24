@props(['name'])
<svg {{ $attributes->class(['shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('search') <circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/> @break
        @case('users') <circle cx="9" cy="7" r="3"/><path d="M16 4a3 3 0 0 1 0 6M2 21v-3a6 6 0 0 1 12 0v3H2Zm15 0h5v-3a6 6 0 0 0-5-5"/> @break
        @case('add') <circle cx="10" cy="7" r="3"/><path d="M3 21v-2a7 7 0 0 1 14 0v2H3ZM20 7v6m-3-3h6"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 6 9 7 9-7"/> @break
        @case('phone') <path d="M8 3H4a1 1 0 0 0-1 1c0 9 8 17 17 17a1 1 0 0 0 1-1v-4l-5-2-2 3a14 14 0 0 1-7-7l3-2-2-5Z"/> @break
        @case('eye') <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/> @break
    @endswitch
</svg>
