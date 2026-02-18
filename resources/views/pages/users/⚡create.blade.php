<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;

new #[Title('Register User')] class extends Component 
{
    #[Validate('required|max:255')]
    public $name = '';

    #[Validate('required|max:255')]
    public $email = '';

    #[Validate('required|min:8')]
    public $password = '';

    public function store()
    {
        $this->validate();

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['name', 'email', 'password']);

        session()->flash('success', 'User berhasil dibuat!');
    }


};
?>

<div class="p-8">
    @if (session('success'))
        <div id="toast-undo" class="flex items-center w-full p-4 mb-4 text-body bg-neutral-primary-soft rounded-base shadow-xs border border-default" role="alert">
            <div class="text-sm font-normal">
                {{ session('success') }}
            </div>
            <div class="flex items-center ms-auto space-x-2 rtl:space-x-reverse">
                <a class="text-sm font-medium text-fg-brand hover:underline" href="#">Undo</a>
                <button type="button" class="ms-auto flex items-center justify-center text-body hover:text-heading bg-transparent box-border border border-transparent hover:bg-neutral-secondary-medium focus:ring-4 focus:ring-neutral-tertiary font-medium leading-5 rounded text-sm h-8 w-8 focus:outline-none" data-dismiss-target="#toast-undo" aria-label="Close">
                    <span class="sr-only">Close</span>
                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 17.94 6M18 18 6.06 6"/></svg>
                </button>
            </div>
        </div>
    @endif

    <form wire:submit="store">
        <div class="grid gap-6 mb-6 md:grid-cols-2">
            <div>
                <label for="name" class="block mb-2.5 text-sm font-medium text-heading">Name</label>
                <input type="text" id="name" wire:model='name'
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="Masukkan Nama"  />
                @error('name')
                    <p class="mt-2.5 text-sm text-fg-danger-strong">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-6">
                <label for="email" class="block mb-2.5 text-sm font-medium text-heading">Email address</label>
                <input type="email" id="email"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                    placeholder="john.doe@company.com" required />
            </div>

        </div>

        <div class="mb-6">
            <label for="password" class="block mb-2.5 text-sm font-medium text-heading">Password</label>
            <input type="password" id="password"
                class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body"
                placeholder="•••••••••" required />
        </div>
        <button type="submit"
            class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none">
            <span wire:loading.remove>Simpan</span>
            <div role="status" wire:loading>
                <svg aria-hidden="true" class="w-4 h-4 text-neutral-quaternary animate-spin fill-brand" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/></svg>
            </div>
            <span wire:loading>Loading...</span>


        </button>
    </form>

</div>
