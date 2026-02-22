<?php

use Livewire\Component;

new class extends Component {
    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/', navigate: true);
    }
};
?>

<nav class="bg-neutral-primary fixed w-full z-20 top-0 start-0 border-b border-default">
    <div class="max-w-7xl flex flex-wrap items-center mx-auto p-4">
        <a href="/" class="flex items-center space-x-3 rtl:space-x-reverse">
            <img src={{ asset('icon.png') }} class="h-7" alt="Frans GYM Logo" />
            <span class="self-center text-xl text-heading font-semibold whitespace-nowrap">Frans GYM</span>
        </a>
        @auth
            <div class="sm:ms-4 ms-auto flex items-center md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse">
                <button type="button"
                    class="flex text-sm bg-neutral-primary rounded-full md:me-0 focus:ring-4 focus:ring-neutral-tertiary"
                    id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown"
                    data-dropdown-placement="bottom">
                    <span class="sr-only">Open user menu</span>
                    <img class="w-8 h-8 rounded-full object-cover" src="{{ asset('storage/' . Auth::user()->photo) }}" alt="{{ Auth::user()->name }}">
                </button>
                <!-- Dropdown menu -->
                <div class="z-50 hidden bg-neutral-primary-medium border border-default-medium rounded-base shadow-lg w-44"
                    id="user-dropdown">
                    <div class="px-4 py-3 text-sm border-b border-default">
                        <span class="block text-heading font-medium">{{ Auth::user()->name }}</span>
                        <span class="block text-body truncate">{{ Auth::user()->email }}</span>
                    </div>
                    <ul class="p-2 text-sm text-body font-medium" aria-labelledby="user-menu-button">
                        <li>
                            <a href="{{ Auth::user()->role === 'admin' ? route('admin.packages.index') : route('member.dashboard') }}"
                                class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded">Dashboard</a>
                        </li>
                        <li>
                            <a href="#"
                                class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded">Settings</a>
                        </li>
                        <li>
                            <button type="button" 
                                wire:click="logout"
                                wire:loading.attr="disabled"
                                wire:target="logout"
                                class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded disabled:opacity-50 disabled:cursor-not-allowed">
                                
                                <span wire:loading.remove wire:target="logout">Sign out</span>
                                
                                <span wire:loading wire:target="logout" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Signing out...
                                </span>
                                
                            </button>
                        </li>
                    </ul>
                </div>
                <button data-collapse-toggle="navbar-user" type="button"
                    class="inline-flex items-center p-2 w-10 h-10 justify-center text-sm text-body rounded-base md:hidden hover:bg-neutral-secondary-soft hover:text-heading focus:outline-none focus:ring-2 focus:ring-neutral-tertiary"
                    aria-controls="navbar-user" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h14" />
                    </svg>
                </button>
            </div>
        @endauth
        <div class="ms-auto items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-user">
            <ul
                class="font-medium flex flex-col p-4 md:p-0 mt-4 border border-default rounded-base bg-neutral-secondary-soft md:flex-row md:space-x-8 rtl:space-x-reverse md:mt-0 md:border-0 md:bg-neutral-primary">
                <li>
                    <a href="/"
                        class="block py-2 px-3 rounded md:p-0 {{ request()->is('/') ? 'bg-brand text-white md:bg-transparent md:text-fg-brand' : 'text-heading hover:bg-neutral-tertiary md:hover:bg-transparent' }}"
                        wire:navigate>
                        Beranda
                    </a>
                </li>
                @guest
                    <li>
                        <a href="/pendaftaran/member" wire:navigate
                            class="block py-2 px-3 rounded md:p-0 md:border-0 
                {{ request()->is('pendaftaran/member*') ? 'bg-brand text-white md:bg-transparent md:text-fg-brand' : 'text-heading hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-fg-brand' }}">
                            Daftar
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('login') }}" wire:navigate
                            class="block py-2 px-3 rounded md:p-0 md:border-0 
                {{ request()->routeIs('login') ? 'bg-brand text-white md:bg-transparent md:text-fg-brand' : 'text-heading hover:bg-neutral-tertiary md:hover:bg-transparent md:hover:text-fg-brand' }}">
                            Login
                        </a>
                    </li>
                @endguest

            </ul>
        </div>
    </div>
</nav>