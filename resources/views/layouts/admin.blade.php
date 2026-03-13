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
        <div class="h-full py-4 overflow-y-auto bg-[#34342F] border-e border-default">
            <a href="https://flowbite.com/" class="flex items-center ps-2.5 mb-5">
                <img src="https://flowbite.com/docs/images/logo.svg" class="h-6 me-3" alt="Flowbite Logo" />
                <span class="self-center text-lg text-heading font-semibold whitespace-nowrap">Flowbite</span>
            </a>
            <ul class="space-y-2 font-medium px-3">
                {{-- <li>
                    <a href="{{ route('admin.absensi.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.absensi.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 9V3h8v6zM3 13V3h8v10zm10 8V11h8v10zM3 21v-6h8v6z"/></svg>
                        
                        <span class="ms-3">Dashboard</span>
                    </a>
                </li> --}}
                <li>
                    <a href="{{ route('admin.absensi.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.absensi.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 21v-2h2v2zm-2-2v-5h2v5zm8-3v-4h2v4zm-2-4v-2h2v2zM5 14v-2h2v2zm-2-2v-2h2v2zm9-7V3h2v2zM4.5 7.5h3v-3h-3zM3 9V3h6v6zm1.5 10.5h3v-3h-3zM3 21v-6h6v6zM16.5 7.5h3v-3h-3zM15 9V3h6v6zm2 12v-3h-2v-2h4v3h2v2zm-4-7v-2h4v2zm-4 0v-2H7v-2h6v2h-2v2zm1-5V5h2v2h2v2zM5.25 6.75v-1.5h1.5v1.5zm0 12v-1.5h1.5v1.5zm12-12v-1.5h1.5v1.5z"/></svg>
                        
                        <span class="ms-3">Absensi</span>
                    </a>
                </li>
            </ul>
            @if (auth()->check() && auth()->user()->role === 'admin')
                <div class="bg-white px-4 py-1 my-2">
                    <h1 class="font-bold">MASTER</h1>
                </div>
                <ul class="space-y-2 font-medium px-3">
                    <li>
                        <a href="{{ route('admin.packages.index') }}" wire:navigate 
                            class="{{ request()->routeIs('admin.packages.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                                
                            <svg class="w-5 h-5 transition duration-75 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6.025A7.5 7.5 0 1 0 17.975 14H10V6.025Z"/>
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 3c-.169 0-.334.014-.5.025V11h7.975c.011-.166.025-.331.025-.5A7.5 7.5 0 0 0 13.5 3Z"/>
                            </svg>
                            
                            <span class="ms-3">Paket</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('admin.akun.member.index') }}" wire:navigate 
                            class="{{ request()->routeIs('admin.akun.member.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="16.271186440677965" viewBox="0 0 472 384"><path fill="currentColor" d="M427 43h42v298h-42zm-86 298V43h43v298zM277 43q9 0 15.5 6t6.5 15v256q0 9-6.5 15t-15.5 6H21q-8 0-14.5-6T0 320V64q0-9 6.5-15T21 43zm-128 58q-20 0-34 14t-14 34t14 34t34 14t34-14t14-34t-14-34t-34-14m96 198v-16q0-22-33-35t-63-13t-63 13t-33 35v16z"/></svg>
                            <span class="ms-3">Member</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.akun.admin.index') }}" wire:navigate 
                            class="{{ request()->routeIs('admin.akun.admin.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 23C6.443 21.765 2 16.522 2 11V5l10-4l10 4v6c0 5.524-4.443 10.765-10 12M4 6v5a10.58 10.58 0 0 0 8 10a10.58 10.58 0 0 0 8-10V6l-8-3Z"/><circle cx="12" cy="8.5" r="2.5" fill="currentColor"/><path fill="currentColor" d="M7 15a5.78 5.78 0 0 0 5 3a5.78 5.78 0 0 0 5-3c-.025-1.896-3.342-3-5-3c-1.667 0-4.975 1.104-5 3"/></svg>
                            <span class="ms-3">Admin</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('admin.akun.trainer.index') }}" wire:navigate 
                            class="{{ request()->routeIs('admin.akun.trainer.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512"><path fill="currentColor" d="M165.906 18.688C15.593 59.28-42.187 198.55 92.72 245.375h-1.095c.635.086 1.274.186 1.906.28c8.985 3.077 18.83 5.733 29.532 7.94C173.36 273.35 209.74 321.22 212.69 368c-33.514 23.096-59.47 62.844-59.47 62.844l26.28 38.686L138.28 493h81.97c-40.425-40.435-11.76-85.906 36.125-85.906c48.54 0 73.945 48.112 36.156 85.906h81.126l-40.375-23.47l26.283-38.686s-26.376-40.4-60.282-63.406c3.204-46.602 39.5-94.167 89.595-113.844c10.706-2.207 20.546-4.86 29.53-7.938c.633-.095 1.273-.195 1.908-.28h-1.125c134.927-46.82 77.163-186.094-73.157-226.69c-40.722 39.37 6.54 101.683 43.626 56.877c36.9 69.08 8.603 127.587-72.28 83.406c-11.88 24.492-34.213 41.374-60.688 41.374c-26.703 0-49.168-17.167-60.97-42c-81.774 45.38-110.512-13.372-73.437-82.78c37.09 44.805 84.35-17.508 43.626-56.876zm90.79 35.92c-27.388 0-51.33 27.556-51.33 63.61c0 36.056 23.942 62.995 51.33 62.995s51.327-26.94 51.327-62.994c0-36.058-23.94-63.61-51.328-63.61z"/></svg>
                            <span class="ms-3">Trainer</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.akun.sales.index') }}" wire:navigate 
                            class="{{ request()->routeIs('admin.akun.sales.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 32 32"><path fill="currentColor" d="M30 6V4h-3V2h-2v2h-1c-1.103 0-2 .898-2 2v2c0 1.103.897 2 2 2h4v2h-6v2h3v2h2v-2h1c1.103 0 2-.897 2-2v-2c0-1.102-.897-2-2-2h-4V6zm-6 14v2h2.586L23 25.586l-2.292-2.293a1 1 0 0 0-.706-.293H20a1 1 0 0 0-.706.293L14 28.586L15.414 30l4.587-4.586l2.292 2.293a1 1 0 0 0 1.414 0L28 23.414V26h2v-6zM4 30H2v-5c0-3.86 3.14-7 7-7h6c1.989 0 3.89.85 5.217 2.333l-1.49 1.334A5 5 0 0 0 15 20H9c-2.757 0-5 2.243-5 5zm8-14a7 7 0 1 0 0-14a7 7 0 0 0 0 14m0-12a5 5 0 1 1 0 10a5 5 0 0 1 0-10"/></svg>
                            <span class="ms-3">Sales</span>
                        </a>
                    </li>
                </ul>
            @endif

            @if (auth()->check() && auth()->user()->role === 'kasir_gym')
                <div class="bg-white px-4 py-1 my-2">
                    <h1 class="font-bold">MASTER</h1>
                </div>
                <ul class="space-y-2 font-medium px-3">
                    <li>
                        <a href="{{ route('admin.akun.member.index') }}" wire:navigate 
                            class="{{ request()->routeIs('admin.akun.member.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="16.271186440677965" viewBox="0 0 472 384"><path fill="currentColor" d="M427 43h42v298h-42zm-86 298V43h43v298zM277 43q9 0 15.5 6t6.5 15v256q0 9-6.5 15t-15.5 6H21q-8 0-14.5-6T0 320V64q0-9 6.5-15T21 43zm-128 58q-20 0-34 14t-14 34t14 34t34 14t34-14t14-34t-14-34t-34-14m96 198v-16q0-22-33-35t-63-13t-63 13t-33 35v16z"/></svg>
                            <span class="ms-3">Member</span>
                        </a>
                    </li>
                </ul>
            @endif

            <div class="bg-white px-4 py-1 my-2">
                <h1 class="font-bold">TRANSAKSI GYM</h1>
            </div>
            <ul class="space-y-2 font-medium px-3">
                <li>
                    <a href="{{ route('admin.penjualan.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.penjualan.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><defs><path id="IconifyId19cd1360bf6bf2f9f1" d="M21.5 11v10h-19V11z"/></defs><g fill="none"><use href="#IconifyId19cd1360bf6bf2f9f1"/><path d="M12 13.5a2.5 2.5 0 1 1 0 5a2.5 2.5 0 0 1 0-5m5.136-7.209L19 5.67l1.824 5.333H3.002L3 11.004L14.146 2.1z"/><path stroke="currentColor" stroke-linecap="square" stroke-width="2" d="M21 11.003h-.176L19.001 5.67L3.354 11.003L3 11m-.5.004H3L14.146 2.1l2.817 3.95"/><g stroke="currentColor" stroke-linecap="square" stroke-width="2"><path d="M14.5 16a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0Z"/><use href="#IconifyId19cd1360bf6bf2f9f1"/><path d="M2.5 11h2a2 2 0 0 1-2 2zm19 0h-2a2 2 0 0 0 2 2zm-19 10h2.002A2 2 0 0 0 2.5 18.998zm19 0h-2a2 2 0 0 1 2-2z"/></g></g></svg>
                        
                        <span class="ms-3">Penjualan</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.pengeluaran.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.pengeluaran.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16"><path fill="currentColor" d="M3 2.75V3h.5a.5.5 0 0 0 .5-.5V2h-.25a.75.75 0 0 0-.75.75M3.75 1h4.5C9.216 1 10 1.784 10 2.75v.543l2.975 2.975A3.5 3.5 0 0 1 14 8.743V14.5a.5.5 0 0 1-1 0V8.743a2.5 2.5 0 0 0-.732-1.768L10 4.707v2.585l.854.854a.5.5 0 0 1-.707.708l-.983-.984l-.034-.034l-1.224-1.224a.738.738 0 1 0-1.043 1.044l1.491 1.49a.5.5 0 0 1 .147.354v1a1 1 0 0 0 .999 1a.5.5 0 0 1 .5.5v1.25A1.75 1.75 0 0 1 8.25 15h-4.5A1.75 1.75 0 0 1 2 13.25V2.75C2 1.784 2.784 1 3.75 1M8 14h.25a.75.75 0 0 0 .75-.75V13h-.5a.5.5 0 0 0-.5.5zm.21-1.972a2 2 0 0 1-.71-1.527v-.794l-.193-.193A2 2 0 1 1 6.066 6q.12-.14.276-.257a1.74 1.74 0 0 1 2.271.161L9 6.292V4h-.5A1.5 1.5 0 0 1 7 2.5V2H5v.5A1.5 1.5 0 0 1 3.5 4H3v8h.5A1.5 1.5 0 0 1 5 13.5v.5h2v-.5a1.5 1.5 0 0 1 1.21-1.472M8.5 3H9v-.25A.75.75 0 0 0 8.25 2H8v.5a.5.5 0 0 0 .5.5M3 13v.25c0 .414.336.75.75.75H4v-.5a.5.5 0 0 0-.5-.5zm3.596-4.197l-.44-.44a1.73 1.73 0 0 1-.508-1.3a1 1 0 1 0 .948 1.74"/></svg>
                        
                        <span class="ms-3">Pengeluaran</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.membership.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.membership.index') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 64 64"><path fill="currentColor" d="M55.295.403H8.51C3.925.403.196 4.133.196 8.719v46.78c0 4.586 3.729 8.317 8.314 8.317h46.785c4.584 0 8.314-3.731 8.314-8.317V8.719c0-4.586-3.73-8.316-8.314-8.316M44.666 8.832a3.893 3.893 0 0 1 3.893 3.891c0 2.149-1.375 3.893-3.893 3.893a3.893 3.893 0 1 1 0-7.784m-8.421 12.227c.361-1.259 1.952-3.49 4.708-3.49h7.423c2.757 0 4.35 2.231 4.708 3.49l2.711 9.119h-3.373l-1.985-6.873h-1.306l1.914 6.873H38.284l1.913-6.873h-1.305l-1.982 6.873h-3.373zM18.751 8.796a3.895 3.895 0 1 1 0 7.79a3.895 3.895 0 0 1 0-7.79m.06 19.176c-.122-.215-.494-1.111-.795-1.846l-2.575 10.872l.004 16.843c.005 1.675-1.14 3.04-2.814 3.045c-1.675.007-2.886-1.345-2.899-3.022l-.016-19.882c0-.936.37-3.157.585-3.977l2.415-10.179c.422-1.776 2.206-2.892 3.976-2.455a3.73 3.73 0 0 1 2.618 2.238c.363.905 1.98 4.997 2.358 5.844c.133.292.413.447.47.473c.32.143 1.116.421 2.561 1.072l.815.367l-.707-1.352l-1.137-2.169s-.151-.378.184-.54c.373-.181.575.124.575.124l3.427 6.389s.284.623.122 1.138c-.568-.031-.951-.707-.951-.707l-.062-.119l-.18.373a1.87 1.87 0 0 1-2.136.592c-1.304-.595-4.675-1.99-4.675-1.99c-.585-.275-.871-.624-1.162-1.131zM31 29.976l.986 1.058l-7.586 7.061l-.986-1.057zm24.945 5.055h-21.48c-.733 0-1.129.413-1.362.63c-.946.879-7.244 6.692-7.244 6.692l-1.892-1.866s7.089-6.878 7.973-7.701c.513-.473 1.129-.728 1.779-.728h22.227z"/></svg>
                        
                        <span class="ms-3">Member Aktif</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.membership.gabung') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.membership.gabung') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 15V9a6 6 0 0 1 6-6h10a6 6 0 0 1 6 6v6a6 6 0 0 1-6 6H7a6 6 0 0 1-6-6Z"/><path d="M7 9a3 3 0 1 1 0 6a3 3 0 0 1 0-6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15V9h3m2 6V9h3m-8 3h2.572M17 12h2.572"/></g></svg>
                        
                        <span class="ms-3">Member Tidak Aktif</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.cicilan.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.cicilan.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><path fill="currentColor" fill-rule="evenodd" d="M41.35 21.506c-1.995-.9-7.334-2.534-16.85-.074c-8.126 2.101-13.747 1.544-17.187.583c-.485-.135-.925-.07-1.187.06a.6.6 0 0 0-.219.17a.43.43 0 0 0-.075.225c-.179 1.799-.333 4.506-.333 8.486c0 5.898.338 8.993.592 10.477c.064.376.268.618.545.74c1.998.882 7.343 2.476 16.863.015c8.079-2.09 13.685-1.526 17.134-.55c.49.138.946.074 1.223-.064a.6.6 0 0 0 .236-.182a.45.45 0 0 0 .079-.233c.177-1.803.329-4.493.329-8.416c0-5.874-.34-8.978-.594-10.471c-.067-.39-.277-.64-.556-.766m1.645-3.646c1.571.71 2.579 2.126 2.854 3.74c.305 1.79.65 5.135.65 11.143c0 4.02-.154 6.846-.347 8.808c-.17 1.721-1.217 2.962-2.52 3.608c-1.253.62-2.74.708-4.087.328c-2.712-.767-7.594-1.353-15.045.573c-10.34 2.674-16.61 1.04-19.48-.228c-1.574-.695-2.595-2.105-2.872-3.726c-.305-1.783-.649-5.12-.649-11.15c0-4.078.158-6.921.353-8.882c.17-1.703 1.199-2.935 2.492-3.579c1.241-.618 2.712-.704 4.044-.332c2.706.755 7.608 1.336 15.111-.604c10.35-2.676 16.627-.994 19.496.3M26 24.63v-.058a2 2 0 0 0-4 0v.13a6.1 6.1 0 0 0-1.65.828c-1.026.733-1.968 1.953-1.968 3.567c0 .811.194 1.578.617 2.251c.415.662.974 1.113 1.52 1.425c.955.546 2.128.797 2.94.97l.121.026c.997.214 1.56.355 1.914.557a.7.7 0 0 1 .114.077a.6.6 0 0 1 .01.12c-.008.03-.055.15-.294.32a2.37 2.37 0 0 1-1.324.404a4.3 4.3 0 0 1-2.376-.733l-.007-.005a2 2 0 0 0-2.489 3.132l1.254-1.559a283 283 0 0 0-1.253 1.56h.001l.003.002l.005.005l.013.01l.031.024l.09.067q.11.08.287.196c.237.152.568.345.982.536c.403.185.894.372 1.459.515v.062a2 2 0 0 0 4 0v-.134a6.1 6.1 0 0 0 1.65-.828c1.026-.733 1.968-1.954 1.968-3.567c0-.811-.194-1.578-.617-2.251c-.415-.662-.975-1.113-1.52-1.425c-.956-.547-2.129-.797-2.94-.97l-.122-.026c-.996-.214-1.56-.355-1.913-.557a.7.7 0 0 1-.114-.077a.6.6 0 0 1-.01-.12c.007-.03.054-.15.294-.32a2.36 2.36 0 0 1 1.41-.403a4.3 4.3 0 0 1 2.29.731l.007.006a2 2 0 0 0 2.489-3.132l-1.254 1.559a197 197 0 0 0 1.252-1.56l-.003-.003l-.006-.004l-.012-.01l-.032-.024l-.09-.067a6 6 0 0 0-.287-.196a8 8 0 0 0-.982-.536A8.5 8.5 0 0 0 26 24.631m-.395 9.76l-.002-.004v-.002l-.001-.001zm-3.226-5.179l.002.003zm13.335 4.604a2 2 0 0 1 0-4h.976a2 2 0 1 1 0 4zm-21.428-2a2 2 0 0 1-2 2h-.976a2 2 0 0 1 0-4h.976a2 2 0 0 1 2 2M22 10.176a2 2 0 0 0 4 0V2.5a2 2 0 1 0-4 0zm-5.809 6.262a2 2 0 0 1-2-2V6.762a2 2 0 0 1 4 0v7.676a2 2 0 0 1-2 2m13.619-4.555a2 2 0 0 0 4 0V4.207a2 2 0 1 0-4 0z" clip-rule="evenodd"/></svg>
                        
                        <span class="ms-3">Member Cicilan</span>
                    </a>
                </li>
                {{-- <li>
                    <a href="{{ route('admin.renew.index') }}" wire:navigate 
                        class="{{ request()->routeIs('admin.renew.*') ? 'text-[#34342F] bg-brand' : 'text-white' }} flex items-center px-2 py-1.5 rounded-md hover:bg-brand hover:text-[#34342F] group transition-colors">
                        
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 26 26"><path fill="currentColor" d="M4 0v19c0 .555-.445 1-1 1s-1-.445-1-1V7h1V5H0v14c0 1.645 1.355 3 3 3h10c-.2-.6-.313-1.3-.313-2H5.814c.114-.316.187-.647.187-1V2h16v11c.7.2 1.4.5 2 1V0zm4 4v4h12V4zm0 6v2h5v-2zm7 0v2h5v-2zm-7 3v2h5v-2zm12 1c-3.324 0-6 2.676-6 6s2.676 6 6 6v-2c-2.276 0-4-1.724-4-4s1.724-4 4-4s4 1.724 4 4c0 .868-.247 1.67-.688 2.313L22 21l-.5 4.5L26 25l-1.25-1.25C25.581 22.706 26 21.377 26 20c0-3.324-2.676-6-6-6M8 16v2h5v-2z"/></svg>
                        
                        <span class="ms-3">Renew</span>
                    </a>
                </li> --}}
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
