<section class="space-y-5 text-[#34342F] [--total-width:2.25rem] sm:[--total-width:3.5rem]" x-data
    x-on:attendance-cell-opened.window="if (!$refs.attendanceDialog.open) $refs.attendanceDialog.showModal()"
    x-on:attendance-cell-closed.window="$refs.attendanceDialog.close()"
    x-on:attendance-bulk-opened.window="if (!$refs.bulkAttendanceDialog.open) $refs.bulkAttendanceDialog.showModal()"
    x-on:attendance-bulk-closed.window="$refs.bulkAttendanceDialog.close()">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Absensi karyawan</h1>
            <p class="mt-1 text-sm text-gray-500">Rekap scan masuk dan keluar setiap bulan.</p>
        </div>
        <div class="w-full xl:w-80">
            <label for="scanner_input" class="mb-1 block text-xs font-semibold">Scanner QR</label>
            <input id="scanner_input" type="text" wire:model="scannedCode" wire:keydown.enter="processScan" autocomplete="off"
                placeholder="Hasil QR muncul disini" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-gray-600">
        </div>
    </div>
    @foreach (['success' => 'bg-green-50 text-green-800', 'error' => 'bg-red-50 text-red-800'] as $status => $classes)
        @if (session()->has($status))
            <div role="alert" class="rounded-lg p-3 text-sm {{ $classes }}">{{ session($status) }}</div>
        @endif
    @endforeach
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 p-4">
            <div class="flex w-full min-w-0 flex-wrap items-center gap-2 md:w-auto">
                <div class="flex w-full min-w-0 items-center justify-between gap-1 sm:w-auto">
                    <button type="button" wire:click="previousMonth" aria-label="Bulan sebelumnya" class="rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-600">‹</button>
                    <h2 class="min-w-0 px-2 text-center text-lg font-bold">{{ $monthLabel }}</h2>
                    <button type="button" wire:click="nextMonth" aria-label="Bulan berikutnya" class="rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-600">›</button>
                </div>
                <label class="sr-only" for="attendance-month">Pilih bulan</label>
                <input id="attendance-month" type="month" min="1000-01" max="9999-12" wire:model.live="month" class="min-w-0 max-w-full flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm sm:flex-none">
                <button type="button" wire:click="currentMonth" class="rounded-lg bg-[#FFED00] px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-gray-600">Bulan ini</button>
            </div>
            <div class="w-full sm:w-60">
                <label for="attendance-search" class="sr-only">Cari nama karyawan</label>
                <input id="attendance-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama karyawan..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>
        @if (auth()->user()->role === 'admin')
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 px-4 py-3">
                @if ($selectingAttendance)
                    <span class="text-sm font-semibold" role="status">{{ count($bulkSelection) }} sel dipilih{{ $bulkRole ? ' · '.($roleLabels[$bulkRole] ?? $bulkRole) : '' }}</span>
                    <button type="button" wire:click="openBulkAttendance" wire:loading.attr="disabled" @disabled($bulkSelection === []) class="rounded-lg bg-[#FFED00] px-3 py-2 text-sm font-semibold disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-gray-600">Isi absensi</button>
                    <button type="button" wire:click="cancelBulkAttendance" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-gray-600">Batal</button>
                    <p class="w-full text-xs text-gray-600">Klik sel kosong untuk memilih atau membatalkan. Pilih karyawan dengan role yang sama. Mengganti bulan atau pencarian menghapus pilihan.</p>
                    @error('bulkSelection')<p role="alert" class="w-full text-sm text-red-700">{{ $message }}</p>@enderror
                @else
                    <button type="button" wire:click="beginBulkAttendance" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-gray-600">Pilih beberapa sel</button>
                @endif
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 px-4 py-3 text-xs text-gray-600">
            <span>{{ $employeeCount }} karyawan</span>
            @foreach ($legend as $entry)
                <span wire:key="legend-{{ $entry->id }}" class="inline-flex items-center gap-2"><span class="rounded px-2 py-1 font-bold text-[#34342F] {{ match ($entry->shift_code) { 'P' => 'bg-[#FFED00]', 'S' => 'bg-[#BAE6FD]', default => 'bg-gray-100' } }}">{{ $entry->shift_code ?: 'H' }}</span>{{ $entry->shift_name ?: 'Hadir' }}</span>
            @endforeach
            <span class="inline-flex items-center gap-2"><span class="rounded bg-purple-100 px-2 py-1 font-bold text-purple-800">IZIN</span>Izin</span>
            <span class="inline-flex items-center gap-2"><span class="rounded bg-purple-100 px-2 py-1 font-bold text-purple-800">SAKIT</span>Sakit</span>
            <span class="inline-flex items-center gap-2"><span class="rounded bg-red-100 px-2 py-1 font-bold text-red-800">OFF</span>Off</span>
            <span>— Belum ada data</span>
            <span class="ml-auto" wire:loading role="status">Memuat rekap…</span>
        </div>
        @include('pages.dashboard.admin.absensi.mobile')
        <div class="hidden max-h-[65vh] overflow-auto md:block" tabindex="0" aria-label="Tabel absensi bulanan, geser untuk melihat semua tanggal" wire:loading.class="opacity-50">
            <table class="w-full border-separate border-spacing-0 text-center text-xs">
                <caption class="sr-only">Rekap absensi {{ $monthLabel }}</caption>
                <thead class="sticky top-0 z-30">
                    <tr>
                        <th scope="col" rowspan="2" class="sticky left-0 z-40 w-8 min-w-8 border-b border-r border-gray-200 bg-gray-50 px-1 py-3 sm:w-10 sm:min-w-10 sm:px-2">No</th>
                        <th scope="col" rowspan="2" class="sticky left-8 z-40 w-24 min-w-24 max-w-24 border-b border-r border-gray-200 bg-gray-50 px-2 py-3 text-left sm:left-10 sm:w-52 sm:min-w-52 sm:max-w-52 sm:px-3">Nama karyawan</th>
                        @foreach ($days as $day)
                            <th scope="col" rowspan="2" wire:key="day-{{ $day->toDateString() }}" class="min-w-11 border-b border-r border-gray-200 px-1 py-2 {{ $day->isSunday() ? 'bg-[#FEE2E2] text-red-800' : 'bg-gray-50' }}">
                                <span class="block text-sm font-bold {{ $day->isToday() ? 'underline decoration-2 underline-offset-4' : '' }}">{{ $day->day }}</span>
                                <span class="mt-1 block text-[10px] font-medium">{{ $day->translatedFormat('D') }}</span>
                            </th>
                        @endforeach
                        <th scope="colgroup" colspan="4" class="sticky right-0 z-40 border-b border-l-2 border-gray-300 bg-gray-100 px-3 py-2 font-bold">Total Masuk</th>
                    </tr>
                    <tr>
                        <th scope="col" class="sticky right-[calc(var(--total-width)*3)] z-40 w-[var(--total-width)] min-w-[var(--total-width)] max-w-[var(--total-width)] border-b border-l-2 border-r border-gray-300 bg-yellow-50 px-1 py-2 text-[10px] sm:text-xs">Hadir</th>
                        <th scope="col" class="sticky right-[calc(var(--total-width)*2)] z-40 w-[var(--total-width)] min-w-[var(--total-width)] max-w-[var(--total-width)] border-b border-r border-gray-200 bg-red-50 px-1 py-2 text-[10px] sm:text-xs">Off</th>
                        <th scope="col" class="sticky right-[var(--total-width)] z-40 w-[var(--total-width)] min-w-[var(--total-width)] max-w-[var(--total-width)] border-b border-r border-gray-200 bg-purple-50 px-1 py-2 text-[9px] sm:text-[11px]">Izin/Sakit</th>
                        <th scope="col" class="sticky right-0 z-40 w-[var(--total-width)] min-w-[var(--total-width)] max-w-[var(--total-width)] border-b border-gray-200 bg-gray-100 px-1 py-2 text-[10px] sm:text-xs" title="Hadir + Off + Izin + Sakit">All</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $number = 0;
                    @endphp
                    @forelse ($employeeGroups as $role => $employees)
                        <tr wire:key="group-{{ $role }}">
                            <th colspan="2" scope="rowgroup" class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-100 px-3 py-2 text-left font-bold">{{ $roleLabels[$role] ?? \Illuminate\Support\Str::headline($role) }}</th>
                            <td colspan="{{ $days->count() }}" class="border-b border-gray-200 bg-gray-100"></td>
                            <td colspan="4" class="sticky right-0 z-20 border-b border-l-2 border-gray-300 bg-gray-100"></td>
                        </tr>
                        @foreach ($employees as $employee)
                            <tr wire:key="employee-{{ $employee->id }}">
                                <td class="sticky left-0 z-20 border-b border-r border-gray-200 bg-white px-1 py-3 text-gray-500 sm:px-2">{{ ++$number }}</td>
                                <th scope="row" class="sticky left-8 z-20 max-w-24 break-words border-b border-r border-gray-200 bg-white px-2 py-3 text-left font-semibold sm:left-10 sm:max-w-52 sm:px-3">{{ filled($employee->assignedShift?->code) ? '('.$employee->assignedShift->code.') '.$employee->name : $employee->name }}</th>
                                @foreach ($days as $day)
                                    @php
                                        $records = $cells[$employee->id]->get($day->toDateString(), collect());
                                    @endphp
                                    <td wire:key="cell-{{ $employee->id }}-{{ $day->day }}" class="border-b border-r border-gray-200 p-0.5">
                                        @if ($records->isNotEmpty())
                                            @php
                                                $record = $records->first();
                                                $pending = $record->status === \App\EmployeeAttendanceStatus::Hadir && $record->check_in_time === null;
                                                $label = match ($record->status) {
                                                    \App\EmployeeAttendanceStatus::Izin => 'IZIN',
                                                    \App\EmployeeAttendanceStatus::Sakit => 'SAKIT',
                                                    \App\EmployeeAttendanceStatus::Off => 'OFF',
                                                    default => $record->shift_code ?: 'H',
                                                };
                                                $color = match ($record->status) {
                                                    \App\EmployeeAttendanceStatus::Izin, \App\EmployeeAttendanceStatus::Sakit => 'bg-purple-100 text-purple-800',
                                                    \App\EmployeeAttendanceStatus::Off => 'bg-red-100 text-red-800',
                                                    default => match ($record->shift_code) {
                                                        'P' => $pending ? '' : 'bg-[#FFED00]',
                                                        'S' => $pending ? '' : 'bg-[#BAE6FD]',
                                                        default => $pending ? '' : 'bg-gray-100',
                                                    },
                                                };
                                            @endphp
                                            <button type="button" wire:click="openAttendanceCell({{ $employee->id }}, '{{ $day->toDateString() }}')" title="{{ $pending ? 'Belum masuk' : $record->status->label() }}" aria-label="Detail {{ $employee->name }}, {{ $day->translatedFormat('d F Y') }}{{ $pending ? ', Belum masuk' : '' }}"
                                                @disabled($selectingAttendance)
                                                class="min-h-10 w-full rounded px-1 font-bold focus-visible:outline-2 focus-visible:outline-gray-800 {{ $color }} {{ $record->isLate() ? 'border-2 border-red-600' : '' }}">
                                                {{ $label }}
                                            </button>
                                        @else
                                            @if (auth()->user()->role === 'admin')
                                                @if ($selectingAttendance)
                                                    @php($selected = isset($bulkSelection[$employee->id.':'.$day->toDateString()]))
                                                    <button type="button" wire:click="toggleAttendanceSelection({{ $employee->id }}, '{{ $day->toDateString() }}')" wire:loading.attr="disabled"
                                                        aria-label="Pilih absensi {{ $employee->name }}, {{ $day->translatedFormat('d F Y') }}" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                        @disabled($bulkRole !== null && $employee->role !== $bulkRole)
                                                        @class(['min-h-10 w-full rounded focus-visible:outline-2 focus-visible:outline-gray-800 disabled:cursor-not-allowed', 'bg-[#FFED00]/30 ring-2 ring-inset ring-gray-700 font-bold' => $selected, 'text-gray-400 hover:bg-yellow-50 disabled:text-gray-200' => ! $selected])>{{ $selected ? '✓' : '—' }}</button>
                                                @else
                                                <button type="button" wire:click="openAttendanceCell({{ $employee->id }}, '{{ $day->toDateString() }}')" aria-label="Tambah absensi {{ $employee->name }}, {{ $day->translatedFormat('d F Y') }}" class="min-h-10 w-full rounded text-gray-400 hover:bg-yellow-50 hover:text-gray-800 focus-visible:outline-2 focus-visible:outline-gray-800">—</button>
                                                @endif
                                            @else
                                                <span class="text-gray-300" title="Belum ada data">—</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                                <td class="sticky right-[calc(var(--total-width)*3)] z-20 border-b border-l-2 border-r border-gray-300 bg-yellow-50 px-1 py-3 font-semibold tabular-nums">{{ $totals[$employee->id]['hadir'] }}</td>
                                <td class="sticky right-[calc(var(--total-width)*2)] z-20 border-b border-r border-gray-200 bg-red-50 px-1 py-3 font-semibold tabular-nums">{{ $totals[$employee->id]['off'] }}</td>
                                <td class="sticky right-[var(--total-width)] z-20 border-b border-r border-gray-200 bg-purple-50 px-1 py-3 font-semibold tabular-nums">{{ $totals[$employee->id]['izin'] }}</td>
                                <td class="sticky right-0 z-20 border-b border-gray-200 bg-gray-100 px-1 py-3 font-bold tabular-nums">{{ $totals[$employee->id]['all'] }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ $days->count() + 6 }}" class="p-10 text-left text-gray-500">Tidak ada karyawan yang cocok. Coba nama lain.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-gray-200 px-4 py-3 text-xs text-gray-500">{{ auth()->user()->role === 'admin' ? 'Klik sel untuk mengelola absensi.' : 'Klik sel terisi untuk melihat detail absensi.' }} Tanggal merah menandai hari Minggu, bukan status libur.</p>
    </div>
    @include('pages.dashboard.admin.absensi.cell-editor')
    @include('pages.dashboard.admin.absensi.bulk-editor')
</section>
