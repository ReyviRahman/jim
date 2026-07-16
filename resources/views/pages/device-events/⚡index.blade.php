<?php

namespace App\Livewire;

use App\HikvisionUserService;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts::empty')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $deviceFilter = '';
    public string $eventTypeFilter = '';
    public string $statusFilter = '';
    public ?string $dateStart = null;
    public ?string $dateEnd = null;

    public function syncLatestMember(HikvisionUserService $hikvisionUserService): void
    {
        $user = User::query()
            ->where('role', 'member')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'name']);

        if ($user === null) {
            session()->flash('error', 'Belum ada member yang dapat disinkronkan.');

            return;
        }

        try {
            $hikvisionUserService->sync($user);

            session()->flash('success', "Member {$user->name} (ID: {$user->id}) berhasil dikirim ke Hikvision.");
        } catch (\Throwable $exception) {
            Log::warning('Failed to sync member to Hikvision', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'Gagal mengirim member ke Hikvision. Periksa koneksi dan konfigurasi perangkat.');
        }
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'deviceFilter', 'eventTypeFilter', 'statusFilter', 'dateStart', 'dateEnd'])) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $query = DeviceEvent::query();

        if ($this->search !== '') {
            $query->where('payload', 'like', '%'.$this->search.'%');
        }

        if ($this->deviceFilter !== '') {
            $query->where('device_code', $this->deviceFilter);
        }

        if ($this->eventTypeFilter !== '') {
            $query->where('event_type', $this->eventTypeFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->dateStart && $this->dateEnd) {
            $query->whereBetween('created_at', [
                $this->dateStart.' 00:00:00',
                $this->dateEnd.' 23:59:59',
            ]);
        }

        return [
            'events' => $query->latest('created_at')->paginate(25),
            'devices' => DeviceEvent::distinct()->orderBy('device_code')->pluck('device_code'),
            'eventTypes' => DeviceEvent::whereNotNull('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
        ];
    }
};
?>

<div class="min-h-screen bg-gray-50 p-4 sm:p-6 lg:p-8" wire:poll.5s>
    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Log Perangkat Hikvision</h1>
            <p class="text-sm text-gray-600 mt-1">Memantau event yang masuk dari perangkat akses kontrol.</p>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-6 flex justify-end">
            <button type="button" wire:click="syncLatestMember"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 data-loading:pointer-events-none data-loading:opacity-50">
                <span wire:loading.remove wire:target="syncLatestMember">Sinkronkan Member Terbaru</span>
                <span wire:loading wire:target="syncLatestMember">Mengirim...</span>
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cari payload</label>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        placeholder="Kata kunci...">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Perangkat</label>
                    <select wire:model.live="deviceFilter"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">Semua</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device }}">{{ $device }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tipe Event</label>
                    <select wire:model.live="eventTypeFilter"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">Semua</option>
                        @foreach ($eventTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model.live="statusFilter"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="">Semua</option>
                        <option value="received">Received</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Rentang Tanggal</label>
                    <input type="text" x-data
                        x-init="flatpickr($el, {
                            mode: 'range',
                            dateFormat: 'Y-m-d',
                            onClose: function(selectedDates, dateStr) {
                                if (dateStr.includes(' to ')) {
                                    const dates = dateStr.split(' to ');
                                    @this.set('dateStart', dates[0]);
                                    @this.set('dateEnd', dates[1]);
                                } else if (dateStr) {
                                    @this.set('dateStart', dateStr);
                                    @this.set('dateEnd', dateStr);
                                } else {
                                    @this.set('dateStart', null);
                                    @this.set('dateEnd', null);
                                }
                            }
                        })"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        placeholder="Pilih rentang...">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perangkat</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe Event</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Pegawai</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kartu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pintu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hasil</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Absensi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode Verifikasi</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payload</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($events as $event)
                            <tr wire:key="{{ $event->id }}">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $event->created_at->format('d M Y H:i:s') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    {{ $event->device_code }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->event_type ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->employee_no ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->card_no ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->door_no ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->swipe_result ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->attendance_status ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    {{ $event->verify_mode ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if ($event->status === 'received')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Received
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Failed
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-xl">
                                    <details>
                                        <summary class="cursor-pointer text-blue-600 hover:text-blue-800 truncate select-none">
                                            {{ Str::limit($event->payload, 80) }}
                                        </summary>
                                        <pre class="mt-2 p-3 bg-gray-100 rounded text-xs overflow-x-auto">{{ $event->payload }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada log event.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-200">
                {{ $events->links() }}
            </div>
        </div>
    </div>
</div>
