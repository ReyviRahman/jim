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
    <body @class(['coach-directory-layout' => request()->routeIs('admin.sesi-pt.index'), 'pt-installments-layout' => request()->routeIs('admin.pt-cicilan.index', 'admin.cicilan.index'), 'member-detail-layout' => request()->routeIs('admin.riwayat.detail')])>
        <livewire:dashboard.navbar />
        <aside id="top-bar-sidebar" class="fixed top-0 left-0 z-40 w-64 h-full -translate-x-full transition-transform duration-300 ease-in-out" aria-label="Sidebar" aria-hidden="true">
        <div class="gym-sidebar flex h-full flex-col overflow-y-auto [&>*]:shrink-0">
            <x-sidebar-brand />

            @php
                $user = auth()->user();
                $userRole = $user?->role ?? '';
                $isAdmin = $userRole === 'admin';
                $isKasir = $userRole === 'kasir_gym';
                $isHeadCoach = $user?->isHeadCoach() ?? false;
                $isKasirMinum = $userRole === 'kasir_minum';
                $isAdminOrKasir = $isAdmin || $isKasir;
            @endphp

            <ul class="gym-sidebar-menu">
                @if($isAdminOrKasir)
                <li>
                    <x-sidebar-item :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" title="Dashboard" description="Ringkasan aktivitas gym">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.absensi.index')" :active="request()->routeIs('admin.absensi.*')" title="Absensi" description="Scan QR / check-in gym">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 21v-2h2v2zm-2-2v-5h2v5zm8-3v-4h2v4zm-2-4v-2h2v2zM5 14v-2h2v2zm-2-2v-2h2v2zm9-7V3h2v2zM4.5 7.5h3v-3h-3zM3 9V3h6v6zm1.5 10.5h3v-3h-3zM3 21v-6h6v6zM16.5 7.5h3v-3h-3zM15 9V3h6v6zm2 12v-3h-2v-2h4v3h2v2zm-4-7v-2h4v2zm-4 0v-2H7v-2h6v2h-2v2zm1-5V5h2v2h2v2zM5.25 6.75v-1.5h1.5v1.5zm0 12v-1.5h1.5v1.5zm12-12v-1.5h1.5v1.5z"/></svg>
                        </x-sidebar-item>
                </li>
                @endif
                @can('view-employee-attendance')
                <li>
                    <x-sidebar-item :href="route('admin.absensi-karyawan.index')" :active="request()->routeIs('admin.absensi-karyawan.*')" title="Absensi Karyawan" description="Scan QR / kehadiran staff">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 21v-2h2v2zm-2-2v-5h2v5zm8-3v-4h2v4zm-2-4v-2h2v2zM5 14v-2h2v2zm-2-2v-2h2v2zm9-7V3h2v2zM4.5 7.5h3v-3h-3zM3 9V3h6v6zm1.5 10.5h3v-3h-3zM3 21v-6h6v6zM16.5 7.5h3v-3h-3zM15 9V3h6v6zm2 12v-3h-2v-2h4v3h2v2zm-4-7v-2h4v2zm-4 0v-2H7v-2h6v2h-2v2zm1-5V5h2v2h2v2zM5.25 6.75v-1.5h1.5v1.5zm0 12v-1.5h1.5v1.5zm12-12v-1.5h1.5v1.5z"/></svg>
                        </x-sidebar-item>
                </li>
                @endcan
            </ul>

            @if ($isAdmin)
                <x-sidebar-category>MASTER</x-sidebar-category>
                <ul class="gym-sidebar-menu">
                    <li>
                        <x-sidebar-item :href="route('admin.shift-absen.index')" :active="request()->routeIs('admin.shift-absen.*')" title="Shift Absen" description="Atur jadwal kehadiran staff">
                            <svg class="size-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                        </x-sidebar-item>
                    </li>
                    <li>
                        <x-sidebar-item :href="route('admin.packages.index')" :active="request()->routeIs('admin.packages.*')" title="Paket" description="Pilihan paket dan harga">
                            <svg class="w-5 h-5 transition duration-75 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6.025A7.5 7.5 0 1 0 17.975 14H10V6.025Z"/>
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 3c-.169 0-.334.014-.5.025V11h7.975c.011-.166.025-.331.025-.5A7.5 7.5 0 0 0 13.5 3Z"/>
                            </svg>
                        </x-sidebar-item>
                    </li>

                    <li>
                        <x-sidebar-item :href="route('admin.akun.member.index')" :active="request()->routeIs('admin.akun.member.*')" title="Member" description="Kelola akun member">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="16.271186440677965" viewBox="0 0 472 384"><path fill="currentColor" d="M427 43h42v298h-42zm-86 298V43h43v298zM277 43q9 0 15.5 6t6.5 15v256q0 9-6.5 15t-15.5 6H21q-8 0-14.5-6T0 320V64q0-9 6.5-15T21 43zm-128 58q-20 0-34 14t-14 34t14 34t34 14t34-14t14-34t-14-34t-34-14m96 198v-16q0-22-33-35t-63-13t-63 13t-33 35v16z"/></svg>
                        </x-sidebar-item>
                    </li>
                    
                    <li>
                        <x-sidebar-item :href="route('admin.akun.admin.index')" :active="request()->routeIs('admin.akun.admin.*')" title="Admin" description="Kelola akun admin">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 23C6.443 21.765 2 16.522 2 11V5l10-4l10 4v6c0 5.524-4.443 10.765-10 12M4 6v5a10.58 10.58 0 0 0 8 10a10.58 10.58 0 0 0 8-10V6l-8-3Z"/><circle cx="12" cy="8.5" r="2.5" fill="currentColor"/><path fill="currentColor" d="M7 15a5.78 5.78 0 0 0 5 3a5.78 5.78 0 0 0 5-3c-.025-1.896-3.342-3-5-3c-1.667 0-4.975 1.104-5 3"/></svg>
                        </x-sidebar-item>
                    </li>

                    <li>
                        <x-sidebar-item :href="route('admin.rentang-bonus.index')" :active="request()->routeIs('admin.rentang-bonus.*')" title="Rentang Bonus" description="Pengaturan bonus dan komisi">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h14q.825 0 1.413.588T21 5v14q0 .825-.587 1.413T19 21zm0-2h14V5H5zm1-2h3v-2H6zm0-4h3V9H6zm0-4h3V5H6zm4 8h8v-2h-8zm0-4h8V9h-8zm0-4h8V5h-8z"/></svg>
                        </x-sidebar-item>
                    </li>
                </ul>
            @endif

            @if ($isKasir)
                <x-sidebar-category>MASTER</x-sidebar-category>
                <ul class="gym-sidebar-menu">
                    <li>
                        <x-sidebar-item :href="route('admin.akun.member.index')" :active="request()->routeIs('admin.akun.member.*')" title="Member" description="Kelola akun member">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="16.271186440677965" viewBox="0 0 472 384"><path fill="currentColor" d="M427 43h42v298h-42zm-86 298V43h43v298zM277 43q9 0 15.5 6t6.5 15v256q0 9-6.5 15t-15.5 6H21q-8 0-14.5-6T0 320V64q0-9 6.5-15T21 43zm-128 58q-20 0-34 14t-14 34t14 34t34 14t34-14t14-34t-14-34t-34-14m96 198v-16q0-22-33-35t-63-13t-63 13t-33 35v16z"/></svg>
                        </x-sidebar-item>
                    </li>
                </ul>
            @endif

            @if($isAdmin || $isKasir || $isKasirMinum)
                <x-sidebar-category>TRANSAKSI MINUMAN</x-sidebar-category>
                <ul class="gym-sidebar-menu">
                    <li>
                        <x-sidebar-item :href="route('admin.beverages.index')" :active="request()->routeIs('admin.beverages.index') || request()->routeIs('admin.beverages.restock')" title="Minuman" description="Stok dan katalog minuman">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M5 3h14a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-3v2h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-3v1a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm0 10h14v8H6zm2 2v4h3v-4z"/></svg>
                        </x-sidebar-item>
                    </li>
                    <li>
                        <x-sidebar-item :href="route('admin.beverages.pos')" :active="request()->routeIs('admin.beverages.pos') || request()->routeIs('admin.beverages.hutang') || request()->routeIs('admin.beverages.deposit')" title="Bayar" description="Pembayaran minuman">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M17 2H7a2 2 0 0 0-2 2v2c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2m0 4H7V4h10zm-1 4h-2v2h2zm0 4h-2v2h2zm4-6h-2v2h2zm0 4h-2v2h2zm4-6h-2v2h2zm0 4h-2v2h2zM7 20h10a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2"/></svg>
                        </x-sidebar-item>
                    </li>
                    <li>
                        <x-sidebar-item :href="route('admin.beverages.sales')" :active="request()->routeIs('admin.beverages.sales')" title="Penjualan" description="Riwayat transaksi penjualan">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m0 16H5V5h14zm-7-2h2V9h-2zm0-4h2V5h-2z"/></svg>
                        </x-sidebar-item>
                    </li>
                    <li>
                        <x-sidebar-item :href="route('admin.beverages.invoice')" :active="request()->routeIs('admin.beverages.invoice')" title="Invoice" description="Tagihan transaksi minuman">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm-2 2l-4 4h3v6h2v-6h3z"/></svg>
                        </x-sidebar-item>
                    </li>
                </ul>
            @endif


            @if($isAdmin || $isKasir || $isHeadCoach)
            <x-sidebar-category>MEMBERSHIP</x-sidebar-category>
            @endif
            <ul class="gym-sidebar-menu">
                @if($isAdminOrKasir || $isHeadCoach)
                <li>
                    <x-sidebar-item :href="route('admin.riwayat.index')" :active="request()->routeIs('admin.riwayat.*')" title="Riwayat Member" description="Data dan histori member">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 8 8"><path fill="currentColor" d="M1 0C.93 0 .87.01.81.03C.42.11.11.42.03.81C0 .87 0 .93 0 1v5.5C0 7.33.67 8 1.5 8H7V7H1.5c-.28 0-.5-.22-.5-.5s.22-.5.5-.5H7V.5c0-.28-.22-.5-.5-.5H6v3L5 2L4 3V0z"/></svg>
                        </x-sidebar-item>
                </li>
                @endif
                @if($isAdminOrKasir)
                <li>
                    <x-sidebar-item :href="route('admin.penjualan.index')" :active="request()->routeIs('admin.penjualan.*')" title="Penjualan" description="Riwayat transaksi penjualan">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><defs><path id="IconifyId19cd1360bf6bf2f9f1" d="M21.5 11v10h-19V11z"/></defs><g fill="none"><use href="#IconifyId19cd1360bf6bf2f9f1"/><path d="M12 13.5a2.5 2.5 0 1 1 0 5a2.5 2.5 0 0 1 0-5m5.136-7.209L19 5.67l1.824 5.333H3.002L3 11.004L14.146 2.1z"/><path stroke="currentColor" stroke-linecap="square" stroke-width="2" d="M21 11.003h-.176L19.001 5.67L3.354 11.003L3 11m-.5.004H3L14.146 2.1l2.817 3.95"/><g stroke="currentColor" stroke-linecap="square" stroke-width="2"><path d="M14.5 16a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0Z"/><use href="#IconifyId19cd1360bf6bf2f9f1"/><path d="M2.5 11h2a2 2 0 0 1-2 2zm19 0h-2a2 2 0 0 0 2 2zm-19 10h2.002A2 2 0 0 0 2.5 18.998zm19 0h-2a2 2 0 0 1 2-2z"/></g></g></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.pengeluaran.index')" :active="request()->routeIs('admin.pengeluaran.*')" title="Pengeluaran" description="Catatan pengeluaran gym">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16"><path fill="currentColor" d="M3 2.75V3h.5a.5.5 0 0 0 .5-.5V2h-.25a.75.75 0 0 0-.75.75M3.75 1h4.5C9.216 1 10 1.784 10 2.75v.543l2.975 2.975A3.5 3.5 0 0 1 14 8.743V14.5a.5.5 0 0 1-1 0V8.743a2.5 2.5 0 0 0-.732-1.768L10 4.707v2.585l.854.854a.5.5 0 0 1-.707.708l-.983-.984l-.034-.034l-1.224-1.224a.738.738 0 1 0-1.043 1.044l1.491 1.49a.5.5 0 0 1 .147.354v1a1 1 0 0 0 .999 1a.5.5 0 0 1 .5.5v1.25A1.75 1.75 0 0 1 8.25 15h-4.5A1.75 1.75 0 0 1 2 13.25V2.75C2 1.784 2.784 1 3.75 1M8 14h.25a.75.75 0 0 0 .75-.75V13h-.5a.5.5 0 0 0-.5.5zm.21-1.972a2 2 0 0 1-.71-1.527v-.794l-.193-.193A2 2 0 1 1 6.066 6q.12-.14.276-.257a1.74 1.74 0 0 1 2.271.161L9 6.292V4h-.5A1.5 1.5 0 0 1 7 2.5V2H5v.5A1.5 1.5 0 0 1 3.5 4H3v8h.5A1.5 1.5 0 0 1 5 13.5v.5h2v-.5a1.5 1.5 0 0 1 1.21-1.472M8.5 3H9v-.25A.75.75 0 0 0 8.25 2H8v.5a.5.5 0 0 0 .5.5M3 13v.25c0 .414.336.75.75.75H4v-.5a.5.5 0 0 0-.5-.5zm3.596-4.197l-.44-.44a1.73 1.73 0 0 1-.508-1.3a1 1 0 1 0 .948 1.74"/></svg>
                        </x-sidebar-item>
                </li>
                @endif

                

                @if($isAdmin || $isKasir || $isHeadCoach)
                <li>
                    <x-sidebar-item :href="route('admin.membership.index')" :active="request()->routeIs('admin.membership.index')" title="Member Aktif" description="Daftar member aktif">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 64 64"><path fill="currentColor" d="M55.295.403H8.51C3.925.403.196 4.133.196 8.719v46.78c0 4.586 3.729 8.317 8.314 8.317h46.785c4.584 0 8.314-3.731 8.314-8.317V8.719c0-4.586-3.73-8.316-8.314-8.316M44.666 8.832a3.893 3.893 0 0 1 3.893 3.891c0 2.149-1.375 3.893-3.893 3.893a3.893 3.893 0 1 1 0-7.784m-8.421 12.227c.361-1.259 1.952-3.49 4.708-3.49h7.423c2.757 0 4.35 2.231 4.708 3.49l2.711 9.119h-3.373l-1.985-6.873h-1.306l1.914 6.873H38.284l1.913-6.873h-1.305l-1.982 6.873h-3.373zM18.751 8.796a3.895 3.895 0 1 1 0 7.79a3.895 3.895 0 0 1 0-7.79m.06 19.176c-.122-.215-.494-1.111-.795-1.846l-2.575 10.872l.004 16.843c.005 1.675-1.14 3.04-2.814 3.045c-1.675.007-2.886-1.345-2.899-3.022l-.016-19.882c0-.936.37-3.157.585-3.977l2.415-10.179c.422-1.776 2.206-2.892 3.976-2.455a3.73 3.73 0 0 1 2.618 2.238c.363.905 1.98 4.997 2.358 5.844c.133.292.413.447.47.473c.32.143 1.116.421 2.561 1.072l.815.367l-.707-1.352l-1.137-2.169s-.151-.378.184-.54c.373-.181.575.124.575.124l3.427 6.389s.284.623.122 1.138c-.568-.031-.951-.707-.951-.707l-.062-.119l-.18.373a1.87 1.87 0 0 1-2.136.592c-1.304-.595-4.675-1.99-4.675-1.99c-.585-.275-.871-.624-1.162-1.131zM31 29.976l.986 1.058l-7.586 7.061l-.986-1.057zm24.945 5.055h-21.48c-.733 0-1.129.413-1.362.63c-.946.879-7.244 6.692-7.244 6.692l-1.892-1.866s7.089-6.878 7.973-7.701c.513-.473 1.129-.728 1.779-.728h22.227z"/></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.membership.gabung')" :active="request()->routeIs('admin.membership.gabung')" title="Member Belum Aktif" description="Member non-aktif">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 15V9a6 6 0 0 1 6-6h10a6 6 0 0 1 6 6v6a6 6 0 0 1-6 6H7a6 6 0 0 1-6-6Z"/><path d="M7 9a3 3 0 1 1 0 6a3 3 0 0 1 0-6Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15V9h3m2 6V9h3m-8 3h2.572M17 12h2.572"/></g></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.membership.non-member')" :active="request()->routeIs('admin.membership.non-member')" title="Member Expired" description="Member yang sudah berakhir">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"><path d="M12 12a4 4 0 1 0 0-8a4 4 0 0 0 0 8M5.5 17a6.5 6.5 0 0 1 13 0"/><path d="m17 21l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></g></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.cicilan.index')" :active="request()->routeIs('admin.cicilan.*')" title="Member Cicilan" description="Cicilan membership">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><path fill="currentColor" fill-rule="evenodd" d="M41.35 21.506c-1.995-.9-7.334-2.534-16.85-.074c-8.126 2.101-13.747 1.544-17.187.583c-.485-.135-.925-.07-1.187.06a.6.6 0 0 0-.219.17a.43.43 0 0 0-.075.225c-.179 1.799-.333 4.506-.333 8.486c0 5.898.338 8.993.592 10.477c.064.376.268.618.545.74c1.998.882 7.343 2.476 16.863.015c8.079-2.09 13.685-1.526 17.134-.55c.49.138.946.074 1.223-.064a.6.6 0 0 0 .236-.182a.45.45 0 0 0 .079-.233c.177-1.803.329-4.493.329-8.416c0-5.874-.34-8.978-.594-10.471c-.067-.39-.277-.64-.556-.766m1.645-3.646c1.571.71 2.579 2.126 2.854 3.74c.305 1.79.65 5.135.65 11.143c0 4.02-.154 6.846-.347 8.808c-.17 1.721-1.217 2.962-2.52 3.608c-1.253.62-2.74.708-4.087.328c-2.712-.767-7.594-1.353-15.045.573c-10.34 2.674-16.61 1.04-19.48-.228c-1.574-.695-2.595-2.105-2.872-3.726c-.305-1.783-.649-5.12-.649-11.15c0-4.078.158-6.921.353-8.882c.17-1.703 1.199-2.935 2.492-3.579c1.241-.618 2.712-.704 4.044-.332c2.706.755 7.608 1.336 15.111-.604c10.35-2.676 16.627-.994 19.496.3M26 24.63v-.058a2 2 0 0 0-4 0v.13a6.1 6.1 0 0 0-1.65.828c-1.026.733-1.968 1.953-1.968 3.567c0 .811.194 1.578.617 2.251c.415.662.974 1.113 1.52 1.425c.955.546 2.128.797 2.94.97l.121.026c.997.214 1.56.355 1.914.557a.7.7 0 0 1 .114.077a.6.6 0 0 1 .01.12c-.008.03-.055.15-.294.32a2.37 2.37 0 0 1-1.324.404a4.3 4.3 0 0 1-2.376-.733l-.007-.005a2 2 0 0 0-2.489 3.132l1.254-1.559a283 283 0 0 0-1.253 1.56h.001l.003.002l.005.005l.013.01l.031.024l.09.067q.11.08.287.196c.237.152.568.345.982.536c.403.185.894.372 1.459.515v.062a2 2 0 0 0 4 0v-.134a6.1 6.1 0 0 0 1.65-.828c1.026-.733 1.968-1.954 1.968-3.567c0-.811-.194-1.578-.617-2.251c-.415-.662-.975-1.113-1.52-1.425c-.956-.547-2.129-.797-2.94-.97l-.122-.026c-.996-.214-1.56-.355-1.913-.557a.7.7 0 0 1-.114-.077a.6.6 0 0 1-.01-.12c.007-.03.054-.15.294-.32a2.36 2.36 0 0 1 1.41-.403a4.3 4.3 0 0 1 2.29.731l.007.006a2 2 0 0 0 2.489-3.132l-1.254 1.559a197 197 0 0 0 1.252-1.56l-.003-.003l-.006-.004l-.012-.01l-.032-.024l-.09-.067a6 6 0 0 0-.287-.196a8 8 0 0 0-.982-.536A8.5 8.5 0 0 0 26 24.631m-.395 9.76l-.002-.004v-.002l-.001-.001zm-3.226-5.179l.002.003zm13.335 4.604a2 2 0 0 1 0-4h.976a2 2 0 1 1 0 4zm-21.428-2a2 2 0 0 1-2 2h-.976a2 2 0 0 1 0-4h.976a2 2 0 0 1 2 2M22 10.176a2 2 0 0 0 4 0V2.5a2 2 0 1 0-4 0zm-5.809 6.262a2 2 0 0 1-2-2V6.762a2 2 0 0 1 4 0v7.676a2 2 0 0 1-2 2m13.619-4.555a2 2 0 0 0 4 0V4.207a2 2 0 1 0-4 0z" clip-rule="evenodd"/></svg>
                        </x-sidebar-item>
                </li>

                <li>
                    <x-sidebar-item :href="route('admin.rekap-bonus.index')" :active="request()->routeIs('admin.rekap-bonus.*')" title="Rekap Bonus" description="Laporan bonus dan komisi">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
                        </x-sidebar-item>
                </li>

                @endif
                
            </ul>

            @if($isAdmin || $isKasir || $isHeadCoach)
            <x-sidebar-category>PERSONAL TRAINING</x-sidebar-category>
            <ul class="gym-sidebar-menu">
                {{-- <li>
                    <x-sidebar-item :href="route('admin.jadwalpt.index')" :active="request()->routeIs('admin.jadwalpt.*')" title="Jadwal PT" description="Booking sesi personal training">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-1V3c0-.6-.4-1-1-1s-1 .4-1 1v1H8V3c0-.6-.4-1-1-1s-1 .4-1 1v1H5C3.3 4 2 5.3 2 7v1h20V7c0-1.7-1.3-3-3-3M2 19c0 1.7 1.3 3 3 3h14c1.7 0 3-1.3 3-3v-9H2zm15-7c.6 0 1 .4 1 1s-.4 1-1 1s-1-.4-1-1s.4-1 1-1m0 4c.6 0 1 .4 1 1s-.4 1-1 1s-1-.4-1-1s.4-1 1-1m-5-4c.6 0 1 .4 1 1s-.4 1-1 1s-1-.4-1-1s.4-1 1-1m0 4c.6 0 1 .4 1 1s-.4 1-1 1s-1-.4-1-1s.4-1 1-1m-5-4c.6 0 1 .4 1 1s-.4 1-1 1s-1-.4-1-1s.4-1 1-1m0 4c.6 0 1 .4 1 1s-.4 1-1 1s-1-.4-1-1s.4-1 1-1"/></svg>
                        </x-sidebar-item>
                </li> --}}

                    @if($isAdmin || $isHeadCoach)
                        <li>
                            <x-sidebar-item :href="route('admin.sesi-pt.index')" :active="request()->routeIs('admin.sesi-pt.*')" title="Coach Performance" description="Performa coach & statistik">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="13" width="4" height="8" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
                        </x-sidebar-item>
                        </li>
                    @endif

                <li>
                    <x-sidebar-item :href="route('admin.pt-booking.index')" :active="request()->routeIs('admin.pt-booking.*')" title="PT Onboarding" description="Paket PT yang belum coach">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20h6m-3-6v6M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm5 3h6"/></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.booking-jadwal.index')" :active="request()->routeIs('admin.booking-jadwal.*')" title="PT Schedule" description="Jadwal sesi PT">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.pt-berjalan.index')" :active="request()->routeIs('admin.pt-berjalan.*')" title="Active Clients" description="Member dengan PT aktif">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="currentColor"><path d="M13.75 6.5a2.25 2.25 0 1 0 0-4.5a2.25 2.25 0 0 0 0 4.5m.026 5.747l-.509 2.18l2.621 2.541l1.074 3.757a1 1 0 0 1-1.923.55l-.927-3.243l-4.647-4.505a1 1 0 0 1-.404-.876l.195-2.736c-.693.783-1.379 1.906-1.794 3.36a1 1 0 0 1-1.923-.55c.546-1.911 1.492-3.392 2.47-4.407a7 7 0 0 1 1.482-1.19c.461-.267.992-.478 1.51-.478q.075 0 .149.011q.1-.002.2.007a3.18 3.18 0 0 1 2.567 1.756q.034.046.062.1l1.23 2.264a2 2 0 0 0 1.365 1.007l1.122.225a1 1 0 1 1-.392 1.96l-1.122-.224a4 4 0 0 1-2.406-1.51"/><path d="m8.145 18.404l1.208-3.626l1.596 1.538l-.907 2.72a2 2 0 0 1-.648.93L7.125 21.78a1 1 0 1 1-1.25-1.562z"/></g></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.pt-expired.index')" :active="request()->routeIs('admin.pt-expired.*')" title="PT Expired" description="Member PT berakhir">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                        </x-sidebar-item>
                </li>
                <li>
                    <x-sidebar-item :href="route('admin.pt-cicilan.index')" :active="request()->routeIs('admin.pt-cicilan.*')" title="PT Cicilan" description="Data cicilan PT">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
                        </x-sidebar-item>
                </li>
            </ul>
            @endif
            <x-sidebar-motto />
        </div>
        </aside>

        <div id="dashboard-content" @class(['sm:ml-64 mt-14 transition-[margin] duration-300 ease-in-out', 'p-4' => ! request()->routeIs('admin.pt-expired.index', 'admin.pt-berjalan.index', 'admin.pt-booking.index')])>
            <x-impersonation-banner />
            <div @class(['p-4 border-1 border-default rounded-md' => ! request()->routeIs('admin.pt-expired.index', 'admin.pt-berjalan.index', 'admin.pt-booking.index')])>
                {{ $slot }}
            </div>
        </div>
        
        @livewireScripts
    </body>
</html>
