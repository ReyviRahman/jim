<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Membership;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

    public ?int $addonMembershipId = null;
    public string $addon_name = '';
    public $addon_duration_months = '';
    public $addon_duration_weeks = '';
    public $addon_duration_days = '';

    private function addonMembership(int $id): Membership
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return Membership::where(function ($query): void {
            $query->where('user_id', $this->user->id)
                ->orWhereHas('members', fn ($members) => $members->where('users.id', $this->user->id));
        })->lockForUpdate()->findOrFail($id);
    }

    private function ensureAddonEligible(Membership $membership): void
    {
        if ($membership->type !== 'pt' || ! in_array($membership->status, ['pending', 'active', 'completed'], true)
            || $membership->addon()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['addon' => 'Paket ini tidak dapat menerima add-on baru.']);
        }
    }

    public function openAddon(int $id): void
    {
        $membership = $this->addonMembership($id);
        $this->ensureAddonEligible($membership);
        $this->resetValidation();
        $this->reset('addon_name', 'addon_duration_months', 'addon_duration_weeks', 'addon_duration_days');
        $this->addonMembershipId = $membership->id;
    }

    public function closeAddon(): void
    {
        $this->reset('addonMembershipId');
        $this->resetValidation();
    }

    public function getAddonPreviewProperty(): ?array
    {
        if ($this->addonMembershipId === null) {
            return null;
        }
        $membership = $this->addonMembership($this->addonMembershipId);
        if ($membership->start_date === null || ! ($membership->is_active || $membership->status === 'completed')) {
            return null;
        }
        try {
            $input = app(\App\MembershipAddonInput::class)->validate('pt', array_merge($this->all(), ['has_addon' => 'yes', 'addon_name' => 'Preview']));
        } catch (\Illuminate\Validation\ValidationException) {
            return null;
        }
        $end = $membership->start_date->copy()->addDays($input['duration_months'] * 30 + $input['duration_weeks'] * 7 + $input['duration_days'] - 1);

        return ['start' => $membership->start_date->format('d/m/Y'), 'end' => $end->format('d/m/Y'), 'expired' => $end->lt(today('Asia/Jakarta'))];
    }

    public function submitAddon(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_if($this->addonMembershipId === null, 422);
        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $membership = $this->addonMembership($this->addonMembershipId);
            $this->ensureAddonEligible($membership);
            $input = app(\App\MembershipAddonInput::class)->validate('pt', array_merge($this->all(), ['has_addon' => 'yes']));
            app(\App\Actions\MembershipAddonApproval::class)->submit($membership, $input, auth()->id());
        });
        $this->closeAddon();
        unset($this->memberships);
        session()->flash('success', 'Add-on menunggu persetujuan Manager');
        $this->dispatch('addon-approvals-updated');
    }

    public function mount(User $user)
    {
        $this->user = $user;
    }

    public function getMembershipsProperty()
    {
        return Membership::where('user_id', $this->user->id)
            ->orWhereHas('members', function ($query) {
                $query->where('user_id', $this->user->id);
            })
            ->with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage', 'addon'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function delete($membershipId)
    {
        if (auth()->check() && auth()->user()->role !== 'admin') {
            session()->flash('error', 'Akses ditolak! Hanya Admin yang dapat menghapus data ini.');
            return;
        }

        $membership = Membership::findOrFail($membershipId);
        $membership->delete();

        session()->flash('success', 'Membership dan semua data terkait berhasil dihapus.');
    }
};
?>

<div>
    <x-member-history :user="$user" :memberships="$this->memberships" />
    @if ($addonMembershipId !== null)
        <dialog open x-data x-init="$el.close(); $el.showModal()" x-on:cancel.prevent="$wire.closeAddon()" aria-labelledby="addon-dialog-title" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 text-heading shadow-xl backdrop:bg-black/50">
            <form wire:submit="submitAddon" class="space-y-4">
                <h2 id="addon-dialog-title" class="text-lg font-semibold">Tambah Add-on PT #{{ $addonMembershipId }}</h2>
                @error('addon') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div>
                    <label for="history-addon-name" class="mb-2 block text-sm font-medium">Nama add-on</label>
                    <input id="history-addon-name" wire:model="addon_name" maxlength="255" required placeholder="Membership 1 Monthly Pass" class="w-full rounded-md border border-default-medium px-3 py-2">
                    @error('addon_name') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-3 gap-3">
                    @foreach (['months' => 'Bulan', 'weeks' => 'Minggu', 'days' => 'Hari'] as $unit => $label)
                        <div>
                            <label for="history-addon-{{ $unit }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                            <input id="history-addon-{{ $unit }}" type="number" min="0" step="1" placeholder="0" wire:model.live="addon_duration_{{ $unit }}" class="w-full rounded-md border border-default-medium px-3 py-2">
                            @error('addon_duration_'.$unit) <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                <div class="text-sm text-body">
                    <p>1 bulan = 30 hari. 1 minggu = 7 hari.</p>
                    @if ($this->addonPreview)
                        <p>Mulai: {{ $this->addonPreview['start'] }} · Selesai: {{ $this->addonPreview['end'] }}</p>
                        @if ($this->addonPreview['expired'])
                            <p class="text-amber-700">Masa add-on sudah lewat. Setelah disetujui, status langsung completed.</p>
                        @endif
                    @else
                        <p>Tanggal mengikuti aktivasi PT setelah durasi diisi.</p>
                    @endif
                    <p>Add-on menunggu persetujuan Manager. Akses gym tersedia setelah disetujui dan PT lunas serta diaktifkan.</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="closeAddon" class="rounded-md border border-default-medium px-4 py-2">Batal</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-brand px-4 py-2 font-medium disabled:opacity-50">Ajukan Add-on</button>
                </div>
            </form>
        </dialog>
    @endif
</div>
