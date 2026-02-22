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
        <livewire:dashboard.navbar />
        <aside id="top-bar-sidebar" class="fixed top-0 left-0 z-40 w-64 h-full transition-transform -translate-x-full sm:translate-x-0" aria-label="Sidebar">
        <div class="h-full px-3 py-4 overflow-y-auto bg-[#34342F] border-e border-default">
            <a href="https://flowbite.com/" class="flex items-center ps-2.5 mb-5">
                <img src="https://flowbite.com/docs/images/logo.svg" class="h-6 me-3" alt="Flowbite Logo" />
                <span class="self-center text-lg text-heading font-semibold whitespace-nowrap">Flowbite</span>
            </a>
            <ul class="space-y-2 font-medium">
                <li>
                    <a href="{{ route('pt.absensi') }}" wire:navigate class="{{ request()->routeIs('pt.absensi') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 21v-2h2v2zm-2-2v-5h2v5zm8-3v-4h2v4zm-2-4v-2h2v2zM5 14v-2h2v2zm-2-2v-2h2v2zm9-7V3h2v2zM4.5 7.5h3v-3h-3zM3 9V3h6v6zm1.5 10.5h3v-3h-3zM3 21v-6h6v6zM16.5 7.5h3v-3h-3zM15 9V3h6v6zm2 12v-3h-2v-2h4v3h2v2zm-4-7v-2h4v2zm-4 0v-2H7v-2h6v2h-2v2zm1-5V5h2v2h2v2zM5.25 6.75v-1.5h1.5v1.5zm0 12v-1.5h1.5v1.5zm12-12v-1.5h1.5v1.5z"/></svg>
                    <span class="ms-3">Absensi</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('pt.kehadiran.index') }}" wire:navigate class="{{ request()->routeIs('pt.kehadiran.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12h1m3-4H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2m0-9v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1m3 5h6m0-5v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1m3 1h2a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2m4-4h-1"/></svg>
                    <span class="ms-3">Kehadiran</span>
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
        <script>
            // Event ini dipicu setiap kali Livewire selesai navigasi (wire:navigate)
            document.addEventListener('livewire:navigated', () => { 
                initFlowbite();
            });
        </script>
        @livewireScripts
    </body>
</html>
