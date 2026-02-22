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
                    <a href="{{ route('member.dashboard') }}" wire:navigate class="{{ request()->routeIs('member.dashboard') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 21v-2h2v2zm-2-2v-5h2v5zm8-3v-4h2v4zm-2-4v-2h2v2zM5 14v-2h2v2zm-2-2v-2h2v2zm9-7V3h2v2zM4.5 7.5h3v-3h-3zM3 9V3h6v6zm1.5 10.5h3v-3h-3zM3 21v-6h6v6zM16.5 7.5h3v-3h-3zM15 9V3h6v6zm2 12v-3h-2v-2h4v3h2v2zm-4-7v-2h4v2zm-4 0v-2H7v-2h6v2h-2v2zm1-5V5h2v2h2v2zM5.25 6.75v-1.5h1.5v1.5zm0 12v-1.5h1.5v1.5zm12-12v-1.5h1.5v1.5z"/></svg>
                    <span class="ms-3">Absensi</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('member.kehadiran.index') }}" wire:navigate class="{{ request()->routeIs('member.kehadiran.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12h1m3-4H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2m0-9v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1m3 5h6m0-5v10a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-1a1 1 0 0 0-1 1m3 1h2a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2m4-4h-1"/></svg>
                    <span class="ms-3">Kehadiran</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('member.membership.index') }}" wire:navigate class="{{ request()->routeIs('member.membership.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 64 64"><path fill="currentColor" d="M55.295.403H8.51C3.925.403.196 4.133.196 8.719v46.78c0 4.586 3.729 8.317 8.314 8.317h46.785c4.584 0 8.314-3.731 8.314-8.317V8.719c0-4.586-3.73-8.316-8.314-8.316M44.666 8.832a3.893 3.893 0 0 1 3.893 3.891c0 2.149-1.375 3.893-3.893 3.893a3.893 3.893 0 1 1 0-7.784m-8.421 12.227c.361-1.259 1.952-3.49 4.708-3.49h7.423c2.757 0 4.35 2.231 4.708 3.49l2.711 9.119h-3.373l-1.985-6.873h-1.306l1.914 6.873H38.284l1.913-6.873h-1.305l-1.982 6.873h-3.373zM18.751 8.796a3.895 3.895 0 1 1 0 7.79a3.895 3.895 0 0 1 0-7.79m.06 19.176c-.122-.215-.494-1.111-.795-1.846l-2.575 10.872l.004 16.843c.005 1.675-1.14 3.04-2.814 3.045c-1.675.007-2.886-1.345-2.899-3.022l-.016-19.882c0-.936.37-3.157.585-3.977l2.415-10.179c.422-1.776 2.206-2.892 3.976-2.455a3.73 3.73 0 0 1 2.618 2.238c.363.905 1.98 4.997 2.358 5.844c.133.292.413.447.47.473c.32.143 1.116.421 2.561 1.072l.815.367l-.707-1.352l-1.137-2.169s-.151-.378.184-.54c.373-.181.575.124.575.124l3.427 6.389s.284.623.122 1.138c-.568-.031-.951-.707-.951-.707l-.062-.119l-.18.373a1.87 1.87 0 0 1-2.136.592c-1.304-.595-4.675-1.99-4.675-1.99c-.585-.275-.871-.624-1.162-1.131zM31 29.976l.986 1.058l-7.586 7.061l-.986-1.057zm24.945 5.055h-21.48c-.733 0-1.129.413-1.362.63c-.946.879-7.244 6.692-7.244 6.692l-1.892-1.866s7.089-6.878 7.973-7.701c.513-.473 1.129-.728 1.779-.728h22.227z"/></svg>
                    <span class="ms-3">Membership</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('member.paket.index') }}" wire:navigate class="{{ request()->routeIs('member.paket.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 16.5l-5-3l5-3l5 3V19l-5 3z"/><path d="M2 13.5V19l5 3m0-5.455l5-3.03m5 2.985l-5-3l5-3l5 3V19l-5 3zM12 19l5 3m0-5.5l5-3m-10 0V8L7 5l5-3l5 3v5.5M7 5.03v5.455M12 8l5-3"/></g></svg>
                    <span class="ms-3">Paket GYM</span>
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
