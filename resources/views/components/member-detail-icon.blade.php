@props(['name'])
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('back') <path d="M20 12H4m8-8-8 8 8 8"/> @break
        @case('edit') <path d="m15 4 5 5M4 20l5-1L21 7a2 2 0 0 0-5-5L4 14l-1 7Z"/> @break
        @case('user') <circle cx="12" cy="7" r="4" fill="currentColor" stroke="none"/><path d="M4 22v-3a8 8 0 0 1 16 0v3" fill="currentColor" stroke="none"/> @break
        @case('users') <circle cx="9" cy="7" r="3"/><path d="M16 4a3 3 0 0 1 0 6M2 21v-3a6 6 0 0 1 12 0v3H2Zm15 0h5v-3a6 6 0 0 0-5-5"/> @break
        @case('gym') <path d="M3 9v6m4-10v14m10-14v14m4-10v6M7 12h10" stroke-width="3"/> @break
        @case('wallet') <path d="m4 7 12-4 2 4M20 10V7H4a2 2 0 0 0-2 2v11h18v-5"/><path d="M22 10h-6a2.5 2.5 0 0 0 0 5h6v-5ZM17 12.5h.1"/> @break
        @case('chart') <path d="M5 20v-6m7 6V8m7 12V3" stroke-width="4"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/> @break
        @case('play') <path d="m7 4 13 8L7 20V4Z" stroke-width="3"/> @break
        @case('check') <circle cx="12" cy="12" r="10" fill="currentColor" stroke="none"/><path d="m7 12 3 3 7-7" stroke="#10100a" stroke-width="2"/> @break
        @case('invoice') <path d="M5 2h10l5 5v15H5V2Zm10 0v6h5M9 12h7m-7 4h7m-7 3h4"/> @break
    @endswitch
</svg>
