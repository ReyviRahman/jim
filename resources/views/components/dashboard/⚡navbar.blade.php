<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public bool $showShiftModal = false;
    public string $selectedShift = '';

    public function openShiftModal()
    {
        $this->selectedShift = Auth::user()->shift ?? '';
        $this->showShiftModal = true;
    }

    public function closeShiftModal()
    {
        $this->showShiftModal = false;
        $this->resetValidation();
    }

    public function saveShift()
    {
        $this->validate([
            'selectedShift' => 'required|in:Pagi,Siang',
        ]);

        Auth::user()->update(['shift' => $this->selectedShift]);

        return $this->redirect(url()->previous());
    }

    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return $this->redirect('/login');
    }
};
?>

<nav class="fixed top-0 z-50 w-full bg-[#34342F] border-b border-default">
    <div class="px-3 py-3 lg:px-5 lg:pl-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center justify-start rtl:justify-end">
                <button data-drawer-target="top-bar-sidebar" data-drawer-toggle="top-bar-sidebar" aria-controls="top-bar-sidebar" type="button" class="sm:hidden text-brand bg-transparent box-border border border-transparent hover:bg-neutral-secondary-medium focus:ring-4 focus:ring-neutral-tertiary font-medium leading-5 rounded-md text-sm p-2 focus:outline-none">
                    <span class="sr-only">Open sidebar</span>
                    <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h10"/>
                    </svg>
                </button>
                <a href="/" class="flex ms-2 md:me-24" wire:navigate>
                    <img src="{{ asset('icon.png') }}" class="h-6 me-3" alt="FRANS GYM Logo" />
                    <span class="self-center text-lg font-semibold whitespace-nowrap text-brand">FRANS GYM</span>
                </a>
            </div>
            <div class="flex items-center">
                @auth
                    <div class="sm:ms-4 ms-auto flex items-center md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse">
                        @if(in_array(Auth::user()->role, ['admin', 'kasir_gym', 'kasir_minum']))
                            <button type="button" wire:click="openShiftModal"
                                class="inline-flex items-center gap-1.5 me-2 sm:me-3 px-2 sm:px-3 py-1.5 text-sm font-medium text-brand bg-[#34342F] border border-default-medium rounded-md hover:bg-neutral-tertiary-medium focus:ring-4 focus:ring-brand focus:outline-none">
                                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                                <span class="hidden sm:inline">
                                    @if(Auth::user()->shift)
                                        Shift: {{ Auth::user()->shift }}
                                    @else
                                        Pilih Shift
                                    @endif
                                </span>
                            </button>
                        @endif
                        <button type="button"
                            class="flex text-sm bg-[#34342F] rounded-full md:me-0 focus:ring-4 focus:ring-brand"
                            id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown"
                            data-dropdown-placement="bottom">
                            <span class="sr-only">Open user menu</span>
                            @if(Auth::user()->photo)
                                <img class="w-8 h-8 rounded-full object-cover" src="{{ asset('storage/' . Auth::user()->photo) }}" alt="{{ Auth::user()->name }}">
                            @else
                                <img class="w-8 h-8 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=random" alt="{{ Auth::user()->name }}">
                            @endif
                        </button>
                        <div class="z-50 hidden bg-[#34342F] border border-default-medium rounded-base shadow-lg w-44"
                            id="user-dropdown">
                            <div class="px-4 py-3 text-sm border-b border-default">
                                <span class="block text-brand font-medium">{{ Auth::user()->name }}</span>
                                <span class="block text-white truncate">{{ Auth::user()->email }}</span>
                            </div>
                            <ul class="p-2 text-sm text-white font-medium" aria-labelledby="user-menu-button">
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
                    </div>
                @endauth
            </div>
        </div>
    </div>

    @if($showShiftModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center w-full h-full overflow-y-auto bg-gray-900/50 backdrop-blur-sm">
            <div class="relative w-full max-w-sm p-4 bg-white rounded-lg shadow sm:p-5">
                <div class="flex items-center justify-between pb-4 mb-4 border-b rounded-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Ubah Shift</h3>
                    <button type="button" wire:click="closeShiftModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                        <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Tutup modal</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-5">
                    <button type="button" wire:click="$set('selectedShift', 'Pagi')"
                        class="px-4 py-3 text-sm font-medium border rounded-lg focus:outline-none focus:ring-4 {{ $selectedShift === 'Pagi' ? 'bg-brand text-secondary border-brand' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
                        Pagi
                    </button>
                    <button type="button" wire:click="$set('selectedShift', 'Siang')"
                        class="px-4 py-3 text-sm font-medium border rounded-lg focus:outline-none focus:ring-4 {{ $selectedShift === 'Siang' ? 'bg-brand text-secondary border-brand' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
                        Siang
                    </button>
                </div>
                @error('selectedShift') <span class="text-xs text-red-500 mb-4 block">{{ $message }}</span> @enderror

                <div class="flex items-center gap-3 justify-end">
                    <button type="button" wire:click="closeShiftModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 focus:outline-none">
                        Batal
                    </button>
                    <button type="button" wire:click="saveShift" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-medium text-secondary bg-brand rounded-lg hover:bg-yellow-300 focus:ring-4 focus:ring-brand focus:outline-none disabled:opacity-50">
                        <span wire:loading.remove wire:target="saveShift">Simpan</span>
                        <span wire:loading wire:target="saveShift">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</nav>