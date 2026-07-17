<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Membership;
use App\HikvisionUserService;
use App\Jobs\SyncHikvisionMember;
use App\Exports\MemberExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination; // Menggunakan trait pagination

    public $search = '';
    // Properti untuk menyimpan ID user yang dicentang
    public array $selectedUsers = [];
    public ?int $syncingUserId = null;
    public ?string $syncStartDate = null;
    public ?string $syncEndDate = null;
    public bool $showSyncModal = false;
    public ?string $bulkSyncStartDate = null;
    public ?string $bulkSyncEndDate = null;
    public bool $showBulkSyncModal = false;
    public array $hikvisionEmployeeNumbers = [];

    public function mount(HikvisionUserService $hikvisionUserService): void
    {
        $this->refreshHikvisionMembers($hikvisionUserService);
    }

    public function openSyncModal(int $userId, HikvisionUserService $hikvisionUserService): void
    {
        $user = User::query()
            ->where('role', 'member')
            ->find($userId, ['id']);

        if ($user === null) {
            session()->flash('error', 'Member tidak ditemukan.');

            return;
        }

        if ($this->memberExistsOnHikvision($hikvisionUserService, $user->id)) {
            session()->flash('info', 'Member ini sudah terdaftar di Hikvision.');

            return;
        }

        $this->resetValidation();
        $this->syncingUserId = $user->id;
        $this->syncStartDate = now()->startOfYear()->toDateString();
        $this->syncEndDate = now()->endOfYear()->toDateString();
        $this->showSyncModal = true;
    }

    public function closeSyncModal(): void
    {
        $this->resetValidation();
        $this->reset('syncingUserId', 'syncStartDate', 'syncEndDate', 'showSyncModal');
    }

    public function openBulkSyncModal(): void
    {
        $this->resetValidation();
        $this->bulkSyncStartDate = now()->startOfYear()->toDateString();
        $this->bulkSyncEndDate = now()->endOfYear()->toDateString();
        $this->showBulkSyncModal = true;
    }

    public function closeBulkSyncModal(): void
    {
        $this->resetValidation();
        $this->reset('bulkSyncStartDate', 'bulkSyncEndDate', 'showBulkSyncModal');
    }

    public function queueBulkSync(): void
    {
        $this->validate([
            'bulkSyncStartDate' => ['required', 'date'],
            'bulkSyncEndDate' => ['required', 'date', 'after_or_equal:bulkSyncStartDate'],
        ]);

        $lock = Cache::lock('hikvision-bulk-sync-dispatch', 60);

        if (! $lock->get()) {
            session()->flash('info', 'Sinkronisasi seluruh member sedang dijadwalkan.');

            return;
        }

        try {
            $memberCount = 0;

            User::query()
                ->where('role', 'member')
                ->select('id')
                ->orderBy('id')
                ->chunkById(100, function ($users) use (&$memberCount): void {
                    foreach ($users as $user) {
                        SyncHikvisionMember::dispatch(
                            $user->id,
                            $this->bulkSyncStartDate,
                            $this->bulkSyncEndDate,
                        )->onQueue('hikvision');

                        $memberCount++;
                    }
                });
        } finally {
            $lock->release();
        }

        $this->closeBulkSyncModal();
        session()->flash('success', "{$memberCount} member dijadwalkan untuk disinkronkan ke Hikvision.");
    }

    public function syncMember(HikvisionUserService $hikvisionUserService): void
    {
        $this->validate([
            'syncingUserId' => ['required', 'integer', 'exists:users,id'],
            'syncStartDate' => ['required', 'date'],
            'syncEndDate' => ['required', 'date', 'after_or_equal:syncStartDate'],
        ]);

        $user = User::query()
            ->where('role', 'member')
            ->find($this->syncingUserId, ['id', 'name']);

        if ($user === null) {
            $this->closeSyncModal();
            session()->flash('error', 'Member tidak ditemukan.');

            return;
        }

        if ($this->memberExistsOnHikvision($hikvisionUserService, $user->id)) {
            $this->closeSyncModal();
            session()->flash('info', 'Member ini sudah terdaftar di Hikvision.');

            return;
        }

        try {
            $hikvisionUserService->sync(
                $user,
                Carbon::parse($this->syncStartDate)->startOfDay(),
                Carbon::parse($this->syncEndDate)->endOfDay(),
            );

            $this->hikvisionEmployeeNumbers = collect($this->hikvisionEmployeeNumbers)
                ->push((string) $user->id)
                ->unique()
                ->values()
                ->all();
            $this->closeSyncModal();
            session()->flash('success', "Member {$user->name} (ID: {$user->id}) berhasil dikirim ke Hikvision.");
        } catch (\Throwable $exception) {
            Log::warning('Failed to sync member to Hikvision from member account page', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'Gagal mengirim member ke Hikvision. Periksa koneksi dan konfigurasi perangkat.');
        }
    }

    public function refreshMembershipStatus($userId)
    {
        $user = User::with(['memberships' => function ($query) {
            $query->where('status', 'active');
        }])->find($userId);

        if (!$user || $user->memberships->isEmpty()) {
            session()->flash('info', 'Member ini tidak memiliki paket aktif.');
            return;
        }

        $updatedCount = 0;
        $skippedCount = 0;
        $now = Carbon::now()->startOfDay();

        foreach ($user->memberships as $membership) {
            $shouldComplete = false;

            if ($membership->type === 'membership' || $membership->type === 'visit') {
                if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->startOfDay()->lt($now)) {
                    $shouldComplete = true;
                }
            } elseif ($membership->type === 'pt') {
                $ptExpired = $membership->pt_end_date && Carbon::parse($membership->pt_end_date)->startOfDay()->lt($now);
                $noSessions = $membership->remaining_sessions !== null && $membership->remaining_sessions <= 0;
                if ($ptExpired || $noSessions) {
                    $shouldComplete = true;
                }
            } elseif ($membership->type === 'bundle_pt_membership') {
                $gymExpired = $membership->membership_end_date && Carbon::parse($membership->membership_end_date)->startOfDay()->lt($now);
                $ptExpired = $membership->pt_end_date && Carbon::parse($membership->pt_end_date)->startOfDay()->lt($now);
                $noSessions = $membership->remaining_sessions !== null && $membership->remaining_sessions <= 0;
                if ($gymExpired || $ptExpired || $noSessions) {
                    $shouldComplete = true;
                }
            }

            if ($shouldComplete) {
                $membership->update([
                    'status' => 'completed',
                    'is_active' => false,
                ]);
                $updatedCount++;
            } else {
                $skippedCount++;
            }
        }

        if ($updatedCount > 0) {
            session()->flash('success', "Paket member telah berakhir." . ($skippedCount > 0 ? " {$skippedCount} paket masih aktif." : ''));
        } else {
            session()->flash('info', 'Paket masih dalam masa aktif.');
        }
    }

    public function lanjutkanCheckout()
    {
        $jumlahTerpilih = count($this->selectedUsers);

        if ($jumlahTerpilih === 0) {
            session()->flash('error', 'Silakan pilih minimal 1 member untuk didaftarkan.');
            return;
        }

        // Bawa array ID user ke rute halaman pilih paket/checkout
        // Gunakan parameter array agar di URL menjadi ?users[0]=1&users[1]=2
        return $this->redirectRoute('admin.membership.paket', [
            'users' => $this->selectedUsers
        ], navigate: true);
    }

    public function exportExcel(): BinaryFileResponse
    {
        $fileName = 'data-member-'.date('Y-m-d').'.xlsx';

        return Excel::download(
            new MemberExport($this->search),
            $fileName
        );
    }

    // Reset halaman ke 1 setiap kali user mengetik di pencarian
    public function updatedSearch()
    {
        $this->resetPage();
        $this->refreshHikvisionMembers(app(HikvisionUserService::class));
    }

    public function updatedPage(mixed $page = null): void
    {
        $this->refreshHikvisionMembers(app(HikvisionUserService::class));
    }

    private function memberExistsOnHikvision(HikvisionUserService $hikvisionUserService, int $userId): bool
    {
        $exists = $this->checkHikvisionMemberExists($hikvisionUserService, $userId);

        if (! $exists) {
            return false;
        }

        $this->markHikvisionMemberAsExisting($userId);

        return true;
    }

    private function checkHikvisionMemberExists(HikvisionUserService $hikvisionUserService, int $userId): ?bool
    {
        try {
            $existingEmployeeNumbers = $hikvisionUserService->existingEmployeeNumbers([$userId]);

            return in_array((string) $userId, $existingEmployeeNumbers, true);
        } catch (\Throwable $exception) {
            Log::warning('Failed to check existing Hikvision member', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function markHikvisionMemberAsExisting(int $userId): void
    {
        $this->hikvisionEmployeeNumbers = collect($this->hikvisionEmployeeNumbers)
            ->push((string) $userId)
            ->unique()
            ->values()
            ->all();
    }

    private function refreshHikvisionMembers(HikvisionUserService $hikvisionUserService): void
    {
        $userIds = $this->memberQuery()
            ->latest()
            ->paginate(10)
            ->getCollection()
            ->pluck('id')
            ->all();

        try {
            $this->hikvisionEmployeeNumbers = $hikvisionUserService->existingEmployeeNumbers($userIds);
        } catch (\Throwable $exception) {
            Log::warning('Failed to check existing Hikvision members', [
                'user_ids' => $userIds,
                'error' => $exception->getMessage(),
            ]);

            $this->hikvisionEmployeeNumbers = [];
        }
    }

    private function memberQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->with(['memberships' => function ($query) {
                $query->where('status', 'active');
            }])
            ->where('role', 'member')
            ->whereDoesntHave('memberships', function ($query) {
                $query->whereIn('status', ['pending']);
            })
            ->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
    }

    // Mengirim data ke view
    public function with(): array
    {
        $users = User::query()
                // 1. Tambahkan Eager Loading di sini
                ->with(['memberships' => function ($query) {
                    // Ambil data membership yang aktif saja
                    $query->where('status', 'active'); 
                    
                    // 💡 PENTING: Jika kolom 'status' berada di tabel pivot (membership_users), 
                    // ubah baris di atas menjadi: $query->wherePivot('status', 'active');
                }])
                // 2. Filter Role
                ->where('role', 'member')
                ->whereDoesntHave('memberships', function ($query) {
                    $query->whereIn('status', ['pending']);
                })
                // 3. Pencarian Name & Email
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                })
                // 4. Urutkan & Paginate
                ->latest()
                ->paginate(10);

        return compact('users');
    }
};
?>

<div>
    {{-- Container Fixed di Pojok Kanan Atas --}}
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-3 w-full max-w-sm">

        {{-- Success Toast --}}
        @if (session()->has('success'))
            <div wire:key="toast-success-{{ rand() }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-[-1rem]"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-[-1rem]"
                class="flex items-center p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 shadow-lg">

                <svg class="flex-shrink-0 w-5 h-5 me-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>

                <span class="flex-1">{{ session('success') }}</span>

                <button type="button" @click="show = false" class="ms-3 -mx-1.5 -my-1.5 text-green-500 hover:text-green-900 rounded-lg focus:ring-2 focus:ring-green-400 p-1.5 inline-flex items-center justify-center h-8 w-8">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        {{-- Info Toast --}}
        @if (session()->has('info'))
            <div wire:key="toast-success-{{ rand() }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-[-1rem]"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-[-1rem]"
                class="flex items-center p-4 text-sm text-blue-800 rounded-lg bg-blue-50 border border-blue-200 shadow-lg">

                <svg class="flex-shrink-0 w-5 h-5 me-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>

                <span class="flex-1">{{ session('info') }}</span>

                <button type="button" @click="show = false" class="ms-3 -mx-1.5 -my-1.5 text-blue-500 hover:text-blue-900 rounded-lg focus:ring-2 focus:ring-blue-400 p-1.5 inline-flex items-center justify-center h-8 w-8">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        {{-- Error Toast --}}
        @if (session()->has('error'))
            <div wire:key="toast-success-{{ rand() }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-[-1rem]"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-[-1rem]"
                class="flex items-center p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 shadow-lg">

                <svg class="flex-shrink-0 w-5 h-5 me-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>

                <span class="flex-1">{{ session('error') }}</span>

                <button type="button" @click="show = false" class="ms-3 -mx-1.5 -my-1.5 text-red-500 hover:text-red-900 rounded-lg focus:ring-2 focus:ring-red-400 p-1.5 inline-flex items-center justify-center h-8 w-8">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

        {{-- Validation Errors Toast --}}
        @if ($errors->any())
            <div wire:key="toast-success-{{ rand() }}" x-data="{ show: true }" x-init="setTimeout(() => show = false, 8000)" x-show="show"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-[-1rem]"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-[-1rem]"
                class="relative p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 shadow-lg">

                <div class="flex items-start gap-2">
                    <svg class="flex-shrink-0 w-5 h-5 mt-0.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div class="flex-1 pr-6">
                        <p class="font-semibold mb-1">Terdapat kesalahan input:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-xs">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <button type="button" @click="show = false" class="absolute top-2 right-2 text-red-500 hover:text-red-900 rounded-lg focus:ring-2 focus:ring-red-400 p-1 inline-flex items-center justify-center h-6 w-6">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        @endif

    </div>

    <div class="relative overflow-x-auto bg-neutral-primary-soft shadow-xs rounded-base border border-default">
        <div class="flex items-center justify-between flex-column flex-wrap md:flex-row space-y-4 md:space-y-0 p-4">
            <h5 class="text-xl font-semibold text-heading">Master Data Member</h5>
            
            <div class="flex sm:flex-row flex-col gap-4 items-center">
                <div>
                    <button type="button" wire:click="exportExcel" class="text-green-700 bg-green-50 box-border border border-green-200 hover:bg-green-100 focus:ring-4 focus:ring-green-200 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Export Excel
                    </button>
                </div>
                <div>
                    <button type="button" wire:click="openBulkSyncModal" class="text-white bg-emerald-600 box-border border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-8m0 0-3-3m3 3 3-3M4 17.25V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2 2v-1.75" /></svg>Sync
                    </button>
                </div>
                <div>
                    <a href="{{ route('admin.akun.member.create') }}" wire:navigate class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-md text-sm px-4 py-2.5 focus:outline-none">+ Buat Akun</a>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                        </svg>
                    </div>
                    <input type="text" id="table-search" wire:model.live="search" 
                        class="block w-full max-w-96 ps-9 pe-3 py-2 bg-neutral-secondary-medium border border-default-medium text-heading text-sm rounded-base focus:ring-brand focus:border-brand shadow-xs placeholder:text-body" 
                        placeholder="Cari nama atau email...">
                </div>
                
            </div>
        </div>

        @if(count($selectedUsers) > 0)
            <div class="bg-brand-soft border-t border-b border-brand-medium p-3 flex justify-between items-center px-4">
                <div class="text-sm font-medium text-brand-strong">
                    Terpilih: <span class="font-bold text-lg">{{ count($selectedUsers) }}</span> Member
                </div>
                <button wire:click="lanjutkanCheckout" class="text-white bg-brand hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium font-medium rounded-md text-sm px-5 py-2 transition-colors">
                    Lanjutkan ke Checkout &rarr;
                </button>
            </div>
        @endif

        <table class="w-full text-sm text-left rtl:text-right text-body">
            <thead class="text-sm text-body bg-neutral-secondary-medium border-b border-t border-default-medium">
                <tr>
                    <th scope="col" class="px-6 py-3 font-medium w-4">
                        {{-- Dikosongkan untuk header checkbox --}}
                    </th>
                    <th scope="col" class="px-6 py-3 font-medium">ID</th>
                    <th scope="col" class="px-6 py-3 font-medium">Nama</th>
                    <th scope="col" class="px-6 py-3 font-medium">Pekerjaan</th>
                    <th scope="col" class="px-6 py-3 font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="bg-neutral-primary-soft border-b border-default hover:bg-neutral-secondary-medium">
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center">
                                <input id="checkbox-{{ $user->id }}" type="checkbox" value="{{ $user->id }}" wire:model.live="selectedUsers"
                                    class="w-4 h-4 text-brand bg-gray-100 border-gray-300 rounded focus:ring-brand focus:ring-2 cursor-pointer">
                            </div>
                        </td>
                        <td class="px-6 py-4 font-medium text-heading">
                            {{ $user->id }}
                        </td>
                        <th scope="row" class="flex items-center px-6 py-4 text-heading whitespace-nowrap">
                            @if($user->photo)
                                <img class="w-10 h-10 rounded-full object-cover" src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->name }}">
                            @else
                                <img class="w-10 h-10 rounded-full object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random" alt="{{ $user->name }}">
                            @endif
                            
                            <div class="ps-3">
                                <div class="text-base font-semibold">{{ $user->name }}</div>
                                <div class="font-normal text-body">{{ $user->email }}</div>
                            </div>  
                        </th>
                        <td class="px-6 py-4">
                            {{ $user->occupation ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex">
                                <a href="{{ route('admin.akun.member.edit', $user->id) }}" wire:navigate class="font-medium text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
                                    <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>
                                <button type="button" wire:click="refreshMembershipStatus({{ $user->id }})" wire:loading.attr="disabled"
                                    class="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    title="Cek Status Membership">
                                    <svg wire:loading.remove wire:target="refreshMembershipStatus({{ $user->id }})" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    <svg wire:loading wire:target="refreshMembershipStatus({{ $user->id }})" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                                @unless (in_array((string) $user->id, $hikvisionEmployeeNumbers, true))
                                    <button type="button" wire:click="openSyncModal({{ $user->id }})"
                                        class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-400"
                                        title="Sinkronkan ke Hikvision">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-8m0 0-3-3m3 3 3-3M4 17.25V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2 2v-1.75" />
                                        </svg>
                                    </button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-body">
                            Data user tidak ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $users->links('components.custom-pagination') }}
    </div>

    @if ($showSyncModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="sync-member-title">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 id="sync-member-title" class="text-lg font-semibold text-heading">Sinkronkan Member ke Hikvision</h2>
                        <p class="mt-1 text-sm text-body">Pilih masa berlaku akses perangkat untuk member ini.</p>
                    </div>
                    <button type="button" wire:click="closeSyncModal" class="rounded-md p-1 text-body hover:bg-neutral-secondary-medium hover:text-heading" aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit="syncMember" class="space-y-4">
                    <div>
                        <label for="sync-start-date" class="mb-1 block text-sm font-medium text-heading">Tanggal mulai</label>
                        <input id="sync-start-date" type="date" wire:model="syncStartDate" class="block w-full rounded-md border border-default-medium px-3 py-2 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand">
                        @error('syncStartDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="sync-end-date" class="mb-1 block text-sm font-medium text-heading">Tanggal berakhir</label>
                        <input id="sync-end-date" type="date" wire:model="syncEndDate" class="block w-full rounded-md border border-default-medium px-3 py-2 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand">
                        @error('syncEndDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeSyncModal" class="rounded-md border border-default-medium px-4 py-2 text-sm font-medium text-body hover:bg-neutral-secondary-medium">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="syncMember" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="syncMember">Sinkronkan</span>
                            <span wire:loading wire:target="syncMember">Mengirim...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showBulkSyncModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="bulk-sync-member-title">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 id="bulk-sync-member-title" class="text-lg font-semibold text-heading">Sinkronkan Semua Member ke Hikvision</h2>
                        <p class="mt-1 text-sm text-body">Setiap member diproses melalui antrean agar aman untuk data dalam jumlah besar. Member yang sudah ada akan dilewati.</p>
                    </div>
                    <button type="button" wire:click="closeBulkSyncModal" class="rounded-md p-1 text-body hover:bg-neutral-secondary-medium hover:text-heading" aria-label="Tutup">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit="queueBulkSync" class="space-y-4">
                    <div>
                        <label for="bulk-sync-start-date" class="mb-1 block text-sm font-medium text-heading">Tanggal mulai</label>
                        <input id="bulk-sync-start-date" type="date" wire:model="bulkSyncStartDate" class="block w-full rounded-md border border-default-medium px-3 py-2 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand">
                        @error('bulkSyncStartDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="bulk-sync-end-date" class="mb-1 block text-sm font-medium text-heading">Tanggal berakhir</label>
                        <input id="bulk-sync-end-date" type="date" wire:model="bulkSyncEndDate" class="block w-full rounded-md border border-default-medium px-3 py-2 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand">
                        @error('bulkSyncEndDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeBulkSyncModal" class="rounded-md border border-default-medium px-4 py-2 text-sm font-medium text-body hover:bg-neutral-secondary-medium">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="queueBulkSync" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="queueBulkSync">Jadwalkan Sinkronisasi</span>
                            <span wire:loading wire:target="queueBulkSync">Menjadwalkan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
