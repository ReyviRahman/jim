@props(['href', 'title', 'description', 'active' => false])

<a href="{{ $href }}" wire:navigate {{ $attributes->class(['gym-sidebar-item', 'is-active' => $active]) }} @if($active) aria-current="page" @endif>
    <span class="gym-sidebar-icon" aria-hidden="true">{{ $slot }}</span>
    <span class="gym-sidebar-copy">
        <span class="gym-sidebar-title">{{ $title }}</span>
        <span class="gym-sidebar-description">{{ $description }}</span>
    </span>
    <svg class="gym-sidebar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
</a>
