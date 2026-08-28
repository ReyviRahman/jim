<?php

use App\Actions\StoreCompressedProfilePhoto;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $photo = null;

    public ?string $existingPhoto = null;

    public string $name = '';

    public ?string $occupation = null;

    public $age = '';

    public string $gender = 'Laki-laki';

    public string $phone = '';

    public ?string $medical_history = null;

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $current_password = '';

    public function mount(): void
    {
        $user = $this->authenticatedUser();

        $this->existingPhoto = $user->photo;
        $this->name = $user->name;
        $this->occupation = $user->occupation;
        $this->age = $user->age;
        $this->gender = $user->gender;
        $this->phone = $user->phone;
        $this->medical_history = $user->medical_history;
        $this->email = $user->email;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $user = $this->authenticatedUser();
        $credentialsAreChanging = $this->email !== $user->email || filled($this->password);

        return [
            'photo' => [
                Rule::requiredIf(blank($user->photo)),
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'name' => ['required', 'string', 'min:3'],
            'occupation' => ['nullable', 'string'],
            'age' => ['required', 'integer', 'min:10'],
            'gender' => ['required', 'in:Laki-laki,Perempuan'],
            'phone' => ['required', 'numeric', Rule::unique('users', 'phone')->ignore($user->id)],
            'medical_history' => ['nullable', 'string'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'current_password' => [Rule::requiredIf($credentialsAreChanging), 'nullable', 'current_password'],
        ];
    }

    public function updateProfile(StoreCompressedProfilePhoto $storeCompressedProfilePhoto): void
    {
        $user = $this->authenticatedUser();
        $validated = $this->validate();
        $oldPhotoPath = $user->photo;
        $newPhotoPath = null;

        if ($this->photo) {
            try {
                $newPhotoPath = $storeCompressedProfilePhoto->execute($this->photo);
            } catch (\RuntimeException $exception) {
                report($exception);
                $this->addError('photo', 'Foto profil gagal diproses. Silakan pilih foto lain dan coba kembali.');

                return;
            }
        }

        $profileData = [
            'name' => $validated['name'],
            'occupation' => $validated['occupation'],
            'age' => $validated['age'],
            'gender' => $validated['gender'],
            'phone' => $validated['phone'],
            'medical_history' => $validated['medical_history'],
            'email' => $validated['email'],
        ];

        if ($newPhotoPath) {
            $profileData['photo'] = $newPhotoPath;
        }

        if (filled($this->password)) {
            $profileData['password'] = Hash::make($this->password);
        }

        try {
            $user->update($profileData);
        } catch (\Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $user->refresh();
        Auth::setUser($user);
        $this->existingPhoto = $user->photo;
        $this->reset('photo', 'password', 'password_confirmation', 'current_password');
        $this->resetValidation();

        session()->flash('success', 'Profil berhasil diperbarui.');
        $this->dispatch('profile-updated');
    }

    public function render(): View
    {
        return $this->view()
            ->layout($this->profileLayout())
            ->title('Profil Saya');
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function profileLayout(): string
    {
        $user = $this->authenticatedUser();

        if ($user->isHeadCoach() || in_array($user->role, ['admin', 'kasir_gym', 'kasir_minum'], true)) {
            return 'layouts::admin';
        }

        return match ($user->role) {
            'member' => 'layouts::member',
            'pt' => 'layouts::pt',
            default => 'layouts::app',
        };
    }
};
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl text-center font-semibold mb-6">Profil Saya</h1>

    @if (session()->has('success'))
        <div role="alert" class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="updateProfile">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="mb-4 sm:col-span-2">
                <label for="profile-photo" class="block mb-2.5 text-sm font-medium text-heading">
                    Foto Profil
                    @if (blank($existingPhoto))
                        <span class="text-red-500">*</span>
                    @endif
                </label>

                <div class="mb-3">
                    @if ($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="Preview foto profil baru" class="w-24 h-24 object-cover rounded-full border border-gray-300">
                    @elseif ($existingPhoto)
                        <img src="{{ asset('storage/' . $existingPhoto) }}" alt="Foto profil {{ $name }}" class="w-24 h-24 object-cover rounded-full border border-gray-300">
                    @else
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($name) }}&background=random" alt="Avatar {{ $name }}" class="w-24 h-24 object-cover rounded-full border border-gray-300">
                    @endif
                </div>

                <input type="file" id="profile-photo" wire:model="photo"
                    accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                    data-focus-on-invalid @required(blank($existingPhoto))
                    class="cursor-pointer bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full shadow-xs placeholder:text-body">
                <p class="text-sm text-gray-500 mt-1">JPG, JPEG, PNG, atau WebP; maksimal 10 MB. Foto otomatis dikompres ke WebP maksimal 800×800 px.</p>
                <div wire:loading wire:target="photo" class="text-sm text-gray-500 mt-1">Mengunggah gambar...</div>
                @error('photo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-name" class="block mb-2.5 text-sm font-medium text-heading">Nama</label>
                <input type="text" id="profile-name" wire:model="name" required
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-occupation" class="block mb-2.5 text-sm font-medium text-heading">Pekerjaan</label>
                <input type="text" id="profile-occupation" wire:model="occupation"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('occupation') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-age" class="block mb-2.5 text-sm font-medium text-heading">Usia</label>
                <input type="number" id="profile-age" wire:model="age" required
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('age') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-gender" class="block mb-2.5 text-sm font-medium text-heading">Jenis Kelamin</label>
                <select id="profile-gender" wire:model="gender" required
                    class="block w-full px-3 py-2.5 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs">
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
                @error('gender') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-phone" class="block mb-2.5 text-sm font-medium text-heading">No HP / WhatsApp</label>
                <input type="text" inputmode="numeric" id="profile-phone" wire:model="phone" required
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-medical-history" class="block mb-2.5 text-sm font-medium text-heading">Riwayat Penyakit</label>
                <input type="text" id="profile-medical-history" wire:model="medical_history"
                    placeholder="Kosongkan jika tidak ada"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('medical_history') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4 sm:col-span-2">
                <label for="profile-email" class="block mb-2.5 text-sm font-medium text-heading">Email</label>
                <input type="email" id="profile-email" wire:model="email" required
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-password" class="block mb-2.5 text-sm font-medium text-heading">Password Baru</label>
                <input type="password" id="profile-password" wire:model="password" autocomplete="new-password"
                    placeholder="Kosongkan jika tidak ingin diubah"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label for="profile-password-confirmation" class="block mb-2.5 text-sm font-medium text-heading">Konfirmasi Password Baru</label>
                <input type="password" id="profile-password-confirmation" wire:model="password_confirmation" autocomplete="new-password"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
            </div>

            <div class="mb-4 sm:col-span-2">
                <label for="profile-current-password" class="block mb-2.5 text-sm font-medium text-heading">Password Saat Ini</label>
                <input type="password" id="profile-current-password" wire:model="current_password" autocomplete="current-password"
                    class="bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand block w-full px-3 py-2.5 shadow-xs placeholder:text-body">
                <p class="text-sm text-gray-500 mt-1">Wajib diisi jika mengubah email atau password.</p>
                @error('current_password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="updateProfile,photo"
            class="mt-4 text-heading cursor-pointer bg-brand box-border border border-transparent hover:bg-heading hover:text-white focus:ring-4 focus:accent-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none w-full mb-3 disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="updateProfile">Simpan Profil</span>
            <span wire:loading wire:target="updateProfile">Menyimpan...</span>
        </button>
    </form>
</div>
