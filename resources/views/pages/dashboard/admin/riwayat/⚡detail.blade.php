<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Membership;

new #[Layout('layouts::admin')] class extends Component
{
    public User $user;

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
            ->with(['user', 'members', 'admin', 'followUp', 'followUpTwo', 'personalTrainer', 'gymPackage', 'ptPackage'])
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

<div><x-member-history :user="$user" :memberships="$this->memberships" /></div>
