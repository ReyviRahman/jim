<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\User;

new #[Layout('layouts::admin')] class extends Component
{
    public $query = '';
    public $selectedUser = null; // Menyimpan data user yang sudah di-klik

    // Reset pencarian saat query berubah (opsional, untuk UX)
    public function updatedQuery()
    {
        $this->selectedUser = null; 
    }

    #[Computed]
    public function users()
    {
        // Jika query kosong atau sudah ada user yang dipilih, jangan search DB
        if (empty($this->query) || $this->selectedUser) {
            return []; 
        }

        return User::where('name', 'like', "%{$this->query}%")
            ->limit(5) // Batasi 5 hasil saja biar rapi
            ->get();
    }

    // Fungsi saat nama di dropdown diklik
    public function selectUser($id)
    {
        $this->selectedUser = User::find($id);
        $this->query = ''; // Bersihkan input search
    }

    // Fungsi untuk membatalkan pilihan
    public function clearSelection()
    {
        $this->selectedUser = null;
        $this->query = '';
    }
};
?>

<div>
    <h5 class="text-xl font-semibold text-heading mb-6">Pendaftaran Membership</h5>
    
    <form>
        <div class="grid gap-4 mb-6 md:grid-cols-2">
            
            <div class="relative">
                <label class="block mb-2.5 text-sm font-medium text-heading">Pilih Member</label>

                @if(!$selectedUser)
                    <div class="relative">
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="query" 
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-md focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" 
                            placeholder="Ketik Nama Member..." 
                            autocomplete="off"
                        >

                        <div wire:loading wire:target="query" class="absolute inset-y-0 right-0 top-3 flex items-center pr-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>

                    @if(count($this->users) > 0)
                        <div class="absolute z-10 w-full mt-1 bg-white rounded-md shadow-lg border border-gray-200 max-h-60 overflow-y-auto dark:bg-gray-700 dark:border-gray-600">
                            <ul class="py-1 text-sm text-gray-700 dark:text-gray-200">
                                @foreach($this->users as $user)
                                    <li>
                                        <button 
                                            type="button"
                                            wire:click="selectUser({{ $user->id }})"
                                            class="block w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                                        >
                                            <span class="font-semibold">{{ $user->name }}</span>
                                            <br>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @elseif(strlen($query) > 2)
                        <div class="absolute z-10 w-full mt-1 bg-white p-2 text-sm text-gray-500 border border-gray-200 rounded-md shadow-lg">
                            Member tidak ditemukan.
                        </div>
                    @endif

                @else
                    <div class="flex items-center justify-between p-2.5 bg-blue-50 border border-blue-200 rounded-md dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="p-1 bg-blue-100 rounded-full dark:bg-blue-900">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $selectedUser->name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $selectedUser->email }}
                                </p>
                            </div>
                        </div>
                        
                        <button type="button" wire:click="clearSelection" class="text-gray-400 hover:text-red-500 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">
                @endif
            </div>

        </div>
    </form>
</div>