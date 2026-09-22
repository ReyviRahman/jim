<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="icon" href="{{ asset('icon.png') }}" type="image/png">

        <x-app-fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body @class(["member-pt-page" => request()->routeIs("member.jadwal-pt.*"), "member-attendance-page" => request()->routeIs("member.kehadiran.*")]) @if(request()->routeIs('member.kehadiran.*')) style="--attendance-background: url('{{ asset('member-attendance-gym-v2.png') }}')" @endif>
        <livewire:dashboard.navbar />
        <aside id="top-bar-sidebar" class="fixed top-0 left-0 z-40 w-64 h-full -translate-x-full transition-transform duration-300 ease-in-out" aria-label="Sidebar" aria-hidden="true">
        <div class="h-full px-3 py-4 overflow-y-auto bg-[#34342F] border-e border-default">
            <a href="https://flowbite.com/" class="flex items-center ps-2.5 mb-5">
                <img src="https://flowbite.com/docs/images/logo.svg" class="h-6 me-3" alt="Flowbite Logo" />
                <span class="self-center text-lg text-heading font-semibold whitespace-nowrap">Flowbite</span>
            </a>
            <ul class="space-y-2 font-medium">
                <li>
                    <a href="{{ route('member.dashboard') }}" wire:navigate class="{{ request()->routeIs('member.dashboard') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M3 11.5L12 4l9 7.5v8a.5.5 0 0 1-.5.5H15v-6H9v6H3.5a.5.5 0 0 1-.5-.5z"/></svg>
                    <span class="ms-3">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('member.absensi') }}" wire:navigate class="{{ request()->routeIs('member.absensi') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
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
                @if ($hasPtMembership)
                    <li>
                        <a href="{{ route('member.jadwal-pt.index') }}" wire:navigate class="{{ request()->routeIs('member.jadwal-pt.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="ms-3">Jadwal PT</span>
                        </a>
                    </li>
                @endif
                {{-- <li>
                    <a href="{{ route('member.paket.index') }}" wire:navigate class="{{ request()->routeIs('member.paket.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 16.5l-5-3l5-3l5 3V19l-5 3z"/><path d="M2 13.5V19l5 3m0-5.455l5-3.03m5 2.985l-5-3l5-3l5 3V19l-5 3zM12 19l5 3m0-5.5l5-3m-10 0V8L7 5l5-3l5 3v5.5M7 5.03v5.455M12 8l5-3"/></g></svg>
                    <span class="ms-3">Paket GYM</span>
                    </a>
                </li> --}}
            </ul>
        </div>
        </aside>

        <div id="dashboard-content" @class(["sm:ml-64 mt-14 transition-[margin] duration-300 ease-in-out", "bg-[#f7f7f7] sm:p-6" => request()->routeIs("member.dashboard"), "p-4" => ! request()->routeIs("member.dashboard", "member.jadwal-pt.*", "member.kehadiran.*", "member.absensi"), "sm:p-6" => request()->routeIs("member.jadwal-pt.*")])>
            <x-impersonation-banner />
            <div @class(["p-4 border-1 border-default rounded-md" => ! request()->routeIs("member.dashboard", "member.jadwal-pt.*", "member.kehadiran.*", "member.absensi")])>
                {{ $slot }}
            </div>
        </div>
        
        @livewireScripts
    </body>
</html>
