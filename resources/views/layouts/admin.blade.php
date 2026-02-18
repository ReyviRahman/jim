<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="icon" href="{{ asset('icon.png') }}" type="image/png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body>
        <nav class="fixed top-0 z-50 w-full bg-neutral-primary-soft border-b border-default">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start rtl:justify-end">
                    <button data-drawer-target="top-bar-sidebar" data-drawer-toggle="top-bar-sidebar" aria-controls="top-bar-sidebar" type="button" class="sm:hidden text-heading bg-transparent box-border border border-transparent hover:bg-neutral-secondary-medium focus:ring-4 focus:ring-neutral-tertiary font-medium leading-5 rounded-md text-sm p-2 focus:outline-none">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h10"/>
                        </svg>
                    </button>
                    <a href="/" class="flex ms-2 md:me-24" wire:navigate>
                    <img src={{ asset('icon.png') }} class="h-6 me-3" alt="Frans GYM Logo" />
                    <span class="self-center text-lg font-semibold whitespace-nowrap dark:text-white">Frans GYM</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center ms-3">
                        <div>
                        <button type="button" class="flex text-sm bg-gray-800 rounded-full focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600" aria-expanded="false" data-dropdown-toggle="dropdown-user">
                            <span class="sr-only">Open user menu</span>
                            <img class="w-8 h-8 rounded-full" src="https://flowbite.com/docs/images/people/profile-picture-5.jpg" alt="user photo">
                        </button>
                        </div>
                        <div class="z-50 hidden bg-neutral-primary-medium border border-default-medium rounded-md shadow-lg w-44" id="dropdown-user">
                        <div class="px-4 py-3 border-b border-default-medium" role="none">
                            <p class="text-sm font-medium text-heading" role="none">
                            Neil Sims
                            </p>
                            <p class="text-sm text-body truncate" role="none">
                            neil.sims@flowbite.com
                            </p>
                        </div>
                        <ul class="p-2 text-sm text-body font-medium" role="none">
                            <li>
                            <a href="#" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Dashboard</a>
                            </li>
                            <li>
                            <a href="#" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Settings</a>
                            </li>
                            <li>
                            <a href="#" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Earnings</a>
                            </li>
                            <li>
                            <a href="#" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Sign out</a>
                            </li>
                        </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </nav>
        <aside id="top-bar-sidebar" class="fixed top-0 left-0 z-40 w-64 h-full transition-transform -translate-x-full sm:translate-x-0" aria-label="Sidebar">
        <div class="h-full px-3 py-4 overflow-y-auto bg-neutral-primary-soft border-e border-default">
            <a href="https://flowbite.com/" class="flex items-center ps-2.5 mb-5">
                <img src="https://flowbite.com/docs/images/logo.svg" class="h-6 me-3" alt="Flowbite Logo" />
                <span class="self-center text-lg text-heading font-semibold whitespace-nowrap">Flowbite</span>
            </a>
            <ul class="space-y-2 font-medium">
                <li>
                    <a href="{{ route('admin.packages.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.packages.*') ? 'text-fg-brand bg-neutral-tertiary' : 'text-body' }} flex items-center px-2 py-1.5 rounded-md hover:bg-neutral-tertiary hover:text-fg-brand group transition-colors">
                            
                        <svg class="w-5 h-5 transition duration-75 {{ request()->routeIs('admin.packages.*') ? 'text-fg-brand' : 'group-hover:text-fg-brand text-gray-500' }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6.025A7.5 7.5 0 1 0 17.975 14H10V6.025Z"/>
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 3c-.169 0-.334.014-.5.025V11h7.975c.011-.166.025-.331.025-.5A7.5 7.5 0 0 0 13.5 3Z"/>
                        </svg>
                        
                        <span class="ms-3">Paket</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.membership.create') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.membership.*') ? 'text-fg-brand bg-neutral-tertiary' : 'text-body' }} flex items-center px-2 py-1.5 rounded-md hover:bg-neutral-tertiary hover:text-fg-brand group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 64 64"><path fill="currentColor" d="M55.295.403H8.51C3.925.403.196 4.133.196 8.719v46.78c0 4.586 3.729 8.317 8.314 8.317h46.785c4.584 0 8.314-3.731 8.314-8.317V8.719c0-4.586-3.73-8.316-8.314-8.316M44.666 8.832a3.893 3.893 0 0 1 3.893 3.891c0 2.149-1.375 3.893-3.893 3.893a3.893 3.893 0 1 1 0-7.784m-8.421 12.227c.361-1.259 1.952-3.49 4.708-3.49h7.423c2.757 0 4.35 2.231 4.708 3.49l2.711 9.119h-3.373l-1.985-6.873h-1.306l1.914 6.873H38.284l1.913-6.873h-1.305l-1.982 6.873h-3.373zM18.751 8.796a3.895 3.895 0 1 1 0 7.79a3.895 3.895 0 0 1 0-7.79m.06 19.176c-.122-.215-.494-1.111-.795-1.846l-2.575 10.872l.004 16.843c.005 1.675-1.14 3.04-2.814 3.045c-1.675.007-2.886-1.345-2.899-3.022l-.016-19.882c0-.936.37-3.157.585-3.977l2.415-10.179c.422-1.776 2.206-2.892 3.976-2.455a3.73 3.73 0 0 1 2.618 2.238c.363.905 1.98 4.997 2.358 5.844c.133.292.413.447.47.473c.32.143 1.116.421 2.561 1.072l.815.367l-.707-1.352l-1.137-2.169s-.151-.378.184-.54c.373-.181.575.124.575.124l3.427 6.389s.284.623.122 1.138c-.568-.031-.951-.707-.951-.707l-.062-.119l-.18.373a1.87 1.87 0 0 1-2.136.592c-1.304-.595-4.675-1.99-4.675-1.99c-.585-.275-.871-.624-1.162-1.131zM31 29.976l.986 1.058l-7.586 7.061l-.986-1.057zm24.945 5.055h-21.48c-.733 0-1.129.413-1.362.63c-.946.879-7.244 6.692-7.244 6.692l-1.892-1.866s7.089-6.878 7.973-7.701c.513-.473 1.129-.728 1.779-.728h22.227z"/></svg>
                        
                        <span class="ms-3">Membership</span>
                    </a>
                </li>
                
                
                
                
                
            </ul>
        </div>
        </aside>

        <div class="p-4 sm:ml-64 mt-14">
            <div class="p-4 border-1 border-default rounded-md">
                {{ $slot }}
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
        @livewireScripts
    </body>
</html>
