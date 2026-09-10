<?php

use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Validation\Rule;

new #[Layout('layouts::admin')] #[Title('Shift Absen')] class extends Component
{
    public string $code = '';
    public string $name = '';
    public string $role = 'admin';
    public string $start_time = '';
    public string $end_time = '';
    public bool $showModal = false;

    #[Locked]
    public ?int $editingId = null;

    public function boot(): void
    {
        $this->authorize('viewAny', Shift::class);
    }

    public function openModal(): void
    {
        $this->authorize('create', Shift::class);
        $this->closeModal();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('code', 'name', 'role', 'start_time', 'end_time', 'editingId', 'showModal');
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $shift = Shift::findOrFail($id);
        $this->authorize('update', $shift);
        $this->closeModal();
        $this->editingId = $shift->id;
        $this->code = $shift->code;
        $this->name = $shift->name;
        $this->role = $shift->role;
        $this->start_time = substr($shift->start_time, 0, 5);
        $this->end_time = substr($shift->end_time, 0, 5);
        $this->showModal = true;
    }

    public function save(): void
    {
        $shift = $this->editingId === null ? new Shift : Shift::findOrFail($this->editingId);
        $this->authorize($shift->exists ? 'update' : 'create', $shift);
        $this->code = trim($this->code);
        $this->name = trim($this->name);
        $data = $this->validate([
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(Shift::ROLE_LABELS))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'date_format' => ':attribute harus menggunakan format 24 jam HH:mm.',
            'after' => 'Jam selesai harus setelah jam mulai pada hari yang sama.',
        ], [
            'code' => 'Kode', 'name' => 'Nama shift', 'role' => 'Role',
            'start_time' => 'Jam mulai', 'end_time' => 'Jam selesai',
        ]);

        $message = $shift->exists ? 'Shift berhasil diperbarui.' : 'Shift berhasil ditambahkan.';
        DB::transaction(function () use ($shift, $data): void {
            $record = $shift->exists ? Shift::query()->lockForUpdate()->findOrFail($shift->id) : $shift;
            if ($record->exists && $record->role !== $data['role'] && $record->users()->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'Role tidak dapat diubah karena shift ini sedang digunakan oleh user.',
                ]);
            }
            $record->fill($data)->save();
        });
        unset($this->shifts);
        $this->closeModal();
        session()->flash('success', $message);
    }

    public function delete(int $id): void
    {
        $shift = Shift::findOrFail($id);
        $this->authorize('delete', $shift);
        $deleted = DB::transaction(function () use ($shift): bool {
            $record = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($record->users()->exists()) {
                return false;
            }
            $record->delete();

            return true;
        });
        if (! $deleted) {
            $this->addError('deleteShift', 'Shift tidak dapat dihapus karena sedang digunakan oleh user.');

            return;
        }
        $this->resetValidation('deleteShift');
        unset($this->shifts);
        session()->flash('success', 'Shift berhasil dihapus.');
    }

    /** @return Collection<int, Shift> */
    #[Computed]
    public function shifts(): Collection
    {
        return Shift::orderByDesc('id')->get();
    }
};
?>

<div>
    @error('deleteShift')<p role="alert" class="mb-4 text-sm text-red-700">{{ $message }}</p>@enderror
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-heading">Shift Absen</h1>
        <button type="button" wire:click="openModal" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-[#34342F] focus:ring-2 focus:ring-brand">Tambah Shift</button>
    </div>
    @if (session()->has('success'))
        <div role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    <div class="overflow-x-auto rounded-lg border border-default bg-white">
        <table class="w-full text-left text-sm text-body">
            <thead class="bg-neutral-secondary-soft text-heading">
                <tr>
                    <th scope="col" class="px-4 py-3">Kode</th>
                    <th scope="col" class="px-4 py-3">Nama Shift</th>
                    <th scope="col" class="px-4 py-3">Role</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">Jam Mulai</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">Jam Selesai</th>
                    <th scope="col" class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->shifts as $shift)
                    <tr wire:key="shift-{{ $shift->id }}" class="border-t border-default">
                        <td class="max-w-64 break-words px-4 py-3">{{ $shift->code }}</td>
                        <td class="max-w-64 break-words px-4 py-3">{{ $shift->name }}</td>
                        <td class="px-4 py-3">{{ Shift::ROLE_LABELS[$shift->role] ?? $shift->role }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ substr($shift->start_time, 0, 5) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ substr($shift->end_time, 0, 5) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-3">
                                <button type="button" wire:click="edit({{ $shift->id }})" wire:loading.attr="disabled" class="font-medium text-blue-700 hover:underline disabled:opacity-50" aria-label="Edit {{ $shift->name }}">Edit</button>
                                <button type="button" wire:click="delete({{ $shift->id }})" wire:confirm="Hapus shift {{ $shift->name }}?" wire:loading.attr="disabled" class="font-medium text-red-700 hover:underline disabled:opacity-50" aria-label="Hapus {{ $shift->name }}">Hapus</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-body">Belum ada shift absen. Klik Tambah Shift untuk membuat shift pertama.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($showModal)
        <div x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeModal()" wire:click.self="closeModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="shift-modal-title">
            <div class="max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 id="shift-modal-title" class="text-lg font-semibold text-heading">{{ $editingId ? 'Edit Shift' : 'Tambah Shift' }}</h2>
                    <button type="button" wire:click="closeModal" aria-label="Tutup" class="rounded px-2 py-1 text-body hover:bg-gray-100">&times;</button>
                </div>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label for="shift-role" class="mb-2 block text-sm font-medium text-heading">Role</label>
                        <select id="shift-role" wire:model="role" required class="block w-full rounded-lg border border-default bg-neutral-secondary-soft p-2.5 text-sm text-heading focus:border-brand focus:ring-brand">
                            @foreach (Shift::ROLE_LABELS as $value => $label)
                                <option wire:key="shift-role-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    @foreach (['code' => 'Kode', 'name' => 'Nama Shift', 'start_time' => 'Jam Mulai', 'end_time' => 'Jam Selesai'] as $field => $label)
                        <div wire:key="shift-field-{{ $field }}">
                            <label for="shift-{{ $field }}" class="mb-2 block text-sm font-medium text-heading">{{ $label }}</label>
                            <input id="shift-{{ $field }}" wire:model="{{ $field }}" type="{{ in_array($field, ['start_time', 'end_time']) ? 'time' : 'text' }}" @if (in_array($field, ['code', 'name'])) maxlength="255" @else step="60" @endif required aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @error($field) aria-describedby="shift-{{ $field }}-error" @enderror class="block w-full rounded-lg border border-default bg-neutral-secondary-soft p-2.5 text-sm text-heading focus:border-brand focus:ring-brand">
                            @error($field)<p id="shift-{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <p class="text-sm text-body">Jam selesai harus setelah jam mulai pada hari yang sama.</p>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" wire:loading.attr="disabled" wire:target="save" class="rounded-lg border border-default px-4 py-2 text-sm text-heading">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-[#34342F] disabled:opacity-50">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
