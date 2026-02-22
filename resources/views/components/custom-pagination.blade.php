@if ($paginator->hasPages())
    <nav class="flex items-center flex-column flex-wrap md:flex-row justify-between p-4" aria-label="Table navigation">
        <span class="text-sm font-normal text-body mb-4 md:mb-0 block w-full md:inline md:w-auto">
            Showing <span class="font-semibold text-heading">{{ $paginator->firstItem() }}-{{ $paginator->lastItem() }}</span> 
            of <span class="font-semibold text-heading">{{ $paginator->total() }}</span>
        </span>
        
        <ul class="flex -space-x-px text-sm">
            
            @if ($paginator->onFirstPage())
                <li>
                    <span class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium font-medium rounded-s-base text-sm px-3 h-9 opacity-50 cursor-not-allowed">Previous</span>
                </li>
            @else
                <li>
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium rounded-s-base text-sm px-3 h-9 focus:outline-none">Previous</button>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium font-medium text-sm w-9 h-9 opacity-50 cursor-default">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span aria-current="page" class="flex items-center justify-center text-fg-brand bg-brand-softer box-border border border-default-medium font-medium text-sm w-9 h-9 cursor-default">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <button type="button" wire:click="gotoPage({{ $page }})" class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium text-sm w-9 h-9 focus:outline-none">{{ $page }}</button>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li>
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium rounded-e-base text-sm px-3 h-9 focus:outline-none">Next</button>
                </li>
            @else
                <li>
                    <span class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium font-medium rounded-e-base text-sm px-3 h-9 opacity-50 cursor-not-allowed">Next</span>
                </li>
            @endif
            
        </ul>
    </nav>
@endif