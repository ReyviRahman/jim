<div class="space-y-5 bg-gray-50 p-2 sm:p-4 lg:hidden" wire:loading.class="opacity-50">
    @forelse ($employeeGroups as $role => $employees)
        <section wire:key="mobile-group-{{ $role }}" class="space-y-3" aria-label="{{ $roleLabels[$role] ?? \Illuminate\Support\Str::headline($role) }}">
            <h3 class="px-1 text-sm font-bold">{{ $roleLabels[$role] ?? \Illuminate\Support\Str::headline($role) }}</h3>
            @foreach ($employees as $employee)
                <article wire:key="mobile-employee-{{ $employee->id }}" class="min-w-0 rounded-xl border border-gray-200 bg-white p-3 sm:p-4">
                    <h4 class="wrap-anywhere text-base font-bold">{{ $employee->name }}</h4>
                    <p class="mt-1 wrap-anywhere text-sm text-gray-500">Shift: {{ $employee->assignedShift?->code ?: 'Belum diatur' }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach (['hadir' => 'Hadir', 'off' => 'Off', 'izin' => 'Izin/Sakit', 'all' => 'All'] as $key => $label)
                            <div wire:key="mobile-total-{{ $employee->id }}-{{ $key }}" class="rounded-lg bg-gray-50 px-3 py-2">
                                <dt class="text-xs text-gray-500">{{ $label }}</dt>
                                <dd class="text-lg font-bold tabular-nums">{{ $totals[$employee->id][$key] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div id="mobile-calendar-{{ $employee->id }}" class="mt-4">
                        <p class="mb-3 text-sm font-semibold">{{ $monthLabel }}</p>
                        <div class="grid grid-cols-7 gap-1 text-center">
                            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
                                <span wire:key="mobile-weekday-{{ $employee->id }}-{{ $weekday }}" class="py-1 text-[10px] font-semibold {{ $weekday === 'Min' ? 'text-red-700' : 'text-gray-500' }}">{{ $weekday }}</span>
                            @endforeach
                            @for ($blank = 1; $blank < $days->first()->dayOfWeekIso; $blank++)
                                <span wire:key="mobile-blank-{{ $employee->id }}-{{ $blank }}" aria-hidden="true"></span>
                            @endfor
                            @foreach ($days as $day)
                                @php
                                    $record = $cells[$employee->id]->get($day->toDateString(), collect())->first();
                                    $pending = $record?->status === \App\EmployeeAttendanceStatus::Hadir && $record->check_in_time === null;
                                    $label = match ($record?->status) {
                                        \App\EmployeeAttendanceStatus::Izin => 'IZIN',
                                        \App\EmployeeAttendanceStatus::Sakit => 'SAKIT',
                                        \App\EmployeeAttendanceStatus::Off => 'OFF',
                                        \App\EmployeeAttendanceStatus::Hadir => $record->shift_code ?: 'H',
                                        default => '—',
                                    };
                                    $color = match ($record?->status) {
                                        \App\EmployeeAttendanceStatus::Izin, \App\EmployeeAttendanceStatus::Sakit => 'bg-purple-100 text-purple-800',
                                        \App\EmployeeAttendanceStatus::Off => 'bg-red-100 text-red-800',
                                        default => $pending ? 'bg-white text-gray-600' : match ($record?->shift_code) {
                                            'P' => 'bg-[#FFED00] text-[#34342F]',
                                            'S' => 'bg-[#BAE6FD] text-[#34342F]',
                                            default => 'bg-gray-50 text-gray-600',
                                        },
                                    };
                                    $selected = isset($bulkSelection[$employee->id.':'.$day->toDateString()]);
                                    $canSelect = auth()->user()->role === 'admin' && $selectingAttendance && $record === null;
                                    $disabled = $selectingAttendance
                                        ? ! $canSelect || ($bulkRole !== null && $employee->role !== $bulkRole)
                                        : $record === null && auth()->user()->role !== 'admin';
                                    $description = $pending ? 'Belum masuk' : ($record?->status->label() ?? 'Belum ada data');
                                @endphp
                                <button type="button" wire:key="mobile-day-{{ $employee->id }}-{{ $day->toDateString() }}"
                                    wire:click="{{ $canSelect ? 'toggleAttendanceSelection' : 'openAttendanceCell' }}({{ $employee->id }}, '{{ $day->toDateString() }}')"
                                    wire:loading.attr="disabled" @disabled($disabled)
                                    aria-label="{{ $canSelect ? 'Pilih absensi' : 'Absensi' }} {{ $employee->name }}, {{ $day->translatedFormat('d F Y') }}, {{ $description }}{{ $record?->isLate() ? ', Terlambat' : '' }}"
                                    @if ($canSelect) aria-pressed="{{ $selected ? 'true' : 'false' }}" @endif
                                    @if ($day->isToday()) aria-current="date" @endif
                                    title="{{ $description }}{{ $record?->isLate() ? ', Terlambat' : '' }}"
                                    @class(['flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-md border px-0.5 py-1 focus-visible:outline-2 focus-visible:outline-gray-800 disabled:cursor-not-allowed', $color, 'border-red-600 border-2' => $record?->isLate(), 'border-dashed border-gray-400' => $pending, 'border-gray-200' => ! $pending && ! $record?->isLate(), 'ring-2 ring-inset ring-gray-700' => $selected])>
                                    <span class="text-sm font-semibold {{ $day->isSunday() ? 'text-red-700' : '' }} {{ $day->isToday() ? 'underline decoration-2 underline-offset-2' : '' }}">{{ $day->day }}</span>
                                    <span class="max-w-full break-all text-[9px] font-bold leading-tight sm:text-xs">{{ $selected ? '✓' : $label }}</span>
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Garis putus-putus: belum masuk. Bingkai merah: terlambat.</p>
                    </div>
                </article>
            @endforeach
        </section>
    @empty
        <p class="p-4 text-sm text-gray-500">Tidak ada karyawan yang cocok. Coba nama lain.</p>
    @endforelse
</div>
