<dialog wire:ignore.self x-ref="attendanceDialog" x-on:cancel.prevent="$wire.closeAttendanceCell()" aria-labelledby="attendance-detail-title"
    x-on:attendance-cell-invalid.window="$nextTick(() => {
        const ids = { 'form.status': 'cell-status', 'form.shift': 'cell-shift', 'form.checkIn': 'cell-checkIn-time', 'form.checkOut': 'cell-checkOut-time', 'form.notes': 'cell-notes' };
        const input = document.getElementById(ids[$event.detail.field] || 'cell-error-summary');
        if (input) { input.focus({ preventScroll: true }); input.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    })"
    class="m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-[#34342F] shadow-xl backdrop:bg-black/40 sm:p-6">
    @if ($cellEmployeeId !== null)
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="attendance-detail-title" class="text-lg font-bold">{{ $cellDetail['name'] }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $cellDetail['date'] }}</p>
            </div>
            <button type="button" wire:click="closeAttendanceCell" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-gray-600">Tutup</button>
        </div>
        @if ($errors->any())
            <div id="cell-error-summary" tabindex="-1" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
                @error('conflict')
                    <button type="button" wire:click="reloadAttendanceCell" class="mt-2 font-semibold underline">Muat ulang detail</button>
                @enderror
            </div>
        @endif
        @if ($confirmingCellDeletion)
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm">Hapus absensi <strong>{{ $cellDetail['name'] }}</strong> tanggal <strong>{{ $cellDetail['date'] }}</strong>? Sel akan kembali kosong.</p>
                <div class="mt-4 flex gap-3">
                    <button type="button" wire:click="deleteAttendanceCell" wire:loading.attr="disabled" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Ya, hapus</button>
                    <button type="button" wire:click="$set('confirmingCellDeletion', false)" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm">Batal</button>
                </div>
            </div>
        @elseif ($editingCell && auth()->user()->role === 'admin')
            <form wire:submit="saveAttendanceCell" class="mt-5 space-y-4">
                <div>
                    <label for="cell-status" class="mb-1 block text-sm font-semibold">Status</label>
                    <select id="cell-status" wire:model.live="form.status" aria-invalid="{{ $errors->has('form.status') ? 'true' : 'false' }}" aria-describedby="cell-status-error" class="w-full rounded-lg border border-gray-300 px-3 py-2.5">
                        @foreach (\App\EmployeeAttendanceStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    @error('form.status')<p id="cell-status-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                @if ($form['status'] === 'hadir')
                    <div>
                        <label for="cell-shift" class="mb-1 block text-sm font-semibold">Shift</label>
                        <select id="cell-shift" wire:model="form.shift" aria-invalid="{{ $errors->has('form.shift') ? 'true' : 'false' }}" aria-describedby="cell-shift-error" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" required>
                            <option value="">Pilih shift</option>
                            @if ($cellDetail['hasSnapshot'])
                                <option value="snapshot">{{ $cellDetail['shift'] }} (histori {{ $cellDetail['schedule'] }})</option>
                            @endif
                            @foreach ($cellShifts as $shift)
                                <option value="{{ $shift['id'] }}" wire:key="edit-shift-{{ $shift['id'] }}">{{ $shift['code'] }} / {{ $shift['name'] }} ({{ substr($shift['start_time'], 0, 5) }}–{{ substr($shift['end_time'], 0, 5) }})</option>
                            @endforeach
                        </select>
                        @error('form.shift')<p id="cell-shift-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    @foreach (['checkIn' => 'masuk', 'checkOut' => 'keluar'] as $field => $label)
                        <fieldset wire:key="cell-time-{{ $cellEmployeeId }}-{{ $cellDate }}-{{ $field }}"
                            x-data="{
                                date: @js(filled($form[$field]) ? substr($form[$field], 0, 10) : $cellDate),
                                time: @js(filled($form[$field]) ? substr($form[$field], 11, 5) : ''),
                                sync() { $wire.set('form.{{ $field }}', this.date && this.time ? this.date + 'T' + this.time : '', false) }
                            }">
                            <legend class="mb-1 text-sm font-semibold">Waktu {{ $label }} @if ($field === 'checkOut')<span class="font-normal text-gray-500">(opsional)</span>@endif</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="cell-{{ $field }}-date" class="mb-1 block text-xs text-gray-500">Tanggal {{ $label }}</label>
                                    <input id="cell-{{ $field }}-date" type="date" x-model="date" x-on:change="sync()" value="{{ filled($form[$field]) ? substr($form[$field], 0, 10) : $cellDate }}" required class="w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2.5">
                                </div>
                                <div>
                                    <label for="cell-{{ $field }}-time" class="mb-1 block text-xs text-gray-500">Jam {{ $label }}</label>
                                    <input id="cell-{{ $field }}-time" type="time" x-model="time" x-on:change="sync()" aria-invalid="{{ $errors->has('form.'.$field) ? 'true' : 'false' }}" aria-describedby="cell-{{ $field }}-error" @required($field === 'checkIn') class="w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2.5">
                                </div>
                            </div>
                            @error('form.'.$field)<p id="cell-{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                @elseif ($cellDetail['hasSnapshot'])
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Menyimpan {{ \App\EmployeeAttendanceStatus::tryFrom($form['status'])?->label() }} akan mengosongkan jam masuk, jam keluar, dan shift pada record ini.</p>
                @endif
                <div>
                    <label for="cell-notes" class="mb-1 block text-sm font-semibold">Catatan <span class="font-normal text-gray-500">(opsional)</span></label>
                    <textarea id="cell-notes" wire:model="form.notes" aria-invalid="{{ $errors->has('form.notes') ? 'true' : 'false' }}" aria-describedby="cell-notes-error" maxlength="1000" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                    @error('form.notes')<p id="cell-notes-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-[#FFED00] px-4 py-2.5 font-semibold focus-visible:ring-2 focus-visible:ring-gray-600 disabled:opacity-50">Simpan absensi</button>
                <span wire:loading wire:target="saveAttendanceCell" role="status" class="block text-sm text-gray-500">Menyimpan…</span>
            </form>
        @else
            <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-5 gap-y-3 border-t border-gray-200 pt-4 text-sm">
                <dt class="text-gray-500">Status</dt><dd class="font-semibold">{{ $cellDetail['status'] ?? 'Belum ada data' }}</dd>
                @if ($cellDetail['hasSnapshot'])
                    <dt class="text-gray-500">Shift</dt><dd>{{ $cellDetail['shift'] }}</dd>
                    <dt class="text-gray-500">Jadwal</dt><dd>{{ $cellDetail['schedule'] }}</dd>
                    <dt class="text-gray-500">Masuk</dt><dd>{{ $cellDetail['in'] }}</dd>
                    <dt class="text-gray-500">Keluar</dt><dd>{{ $cellDetail['out'] }}</dd>
                    <dt class="text-gray-500">Nama di alat</dt><dd>{{ $cellDetail['device'] }}</dd>
                @endif
                <dt class="text-gray-500">Catatan</dt><dd class="whitespace-pre-wrap break-words">{{ $cellDetail['notes'] ?: '—' }}</dd>
            </dl>
            @if (auth()->user()->role === 'admin')
                <div class="mt-6 flex gap-3">
                    <button type="button" wire:click="editAttendanceCell" class="rounded-lg bg-[#FFED00] px-4 py-2 font-semibold">Edit</button>
                    @if ($cellRevision !== null)
                        <button type="button" wire:click="confirmDeleteAttendanceCell" class="rounded-lg border border-red-200 px-4 py-2 text-red-700">Hapus</button>
                    @endif
                </div>
            @endif
        @endif
    @endif
</dialog>
