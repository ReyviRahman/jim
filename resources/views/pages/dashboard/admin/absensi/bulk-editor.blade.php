<dialog wire:ignore.self x-ref="bulkAttendanceDialog" x-on:cancel.prevent="$wire.closeBulkAttendance()" aria-labelledby="bulk-attendance-title"
    x-on:attendance-bulk-invalid.window="$nextTick(() => { $refs.bulkErrors?.focus(); $refs.bulkErrors?.scrollIntoView({ block: 'nearest' }); })"
    class="m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-[#34342F] shadow-xl backdrop:bg-black/40 sm:p-6">
    @if ($bulkDialogOpen)
        <div class="flex items-start justify-between gap-3">
            <h2 id="bulk-attendance-title" class="text-lg font-bold">Isi {{ count($bulkSelection) }} sel absensi</h2>
            <button type="button" wire:click="closeBulkAttendance" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-gray-600">Tutup</button>
        </div>
        <p class="mt-2 text-sm text-gray-600">Data yang sama akan diterapkan ke semua sel berikut.</p>
        <ul aria-label="Sel terpilih" class="mt-3 max-h-36 space-y-2 overflow-y-auto rounded-lg bg-gray-50 p-3 text-sm">
            @foreach ($bulkSelection as $key => $cell)
                <li wire:key="bulk-summary-{{ $key }}" class="flex items-center justify-between gap-3">
                    <span class="min-w-0 wrap-anywhere">{{ $cell['name'] }} · {{ \Illuminate\Support\Carbon::parse($cell['date'])->locale('id')->translatedFormat('d F Y') }}</span>
                    <button type="button" wire:click="toggleAttendanceSelection({{ $cell['employeeId'] }}, '{{ $cell['date'] }}')" aria-label="Batalkan pilihan {{ $cell['name'] }}, {{ $cell['date'] }}" class="shrink-0 rounded px-2 py-1 text-red-700 underline focus-visible:ring-2 focus-visible:ring-gray-600">Batal pilih</button>
                </li>
            @endforeach
        </ul>
        @if ($errors->any())
            <div x-ref="bulkErrors" tabindex="-1" role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">
                <p class="font-semibold">Belum ada data yang disimpan.</p>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <form wire:submit="saveBulkAttendance" class="mt-5 space-y-4">
            <div>
                <label for="bulk-status" class="mb-1 block text-sm font-semibold">Status</label>
                <select id="bulk-status" wire:model.live="bulkForm.status" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" required>
                    @foreach (\App\EmployeeAttendanceStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if ($bulkForm['status'] === 'hadir')
                <div>
                    <label for="bulk-shift" class="mb-1 block text-sm font-semibold">Shift untuk semua sel</label>
                    <select id="bulk-shift" wire:model="bulkForm.shift" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" required>
                        <option value="">Pilih shift</option>
                        @foreach ($bulkShifts as $shift)
                            <option wire:key="bulk-shift-{{ $shift['id'] }}" value="{{ $shift['id'] }}">{{ $shift['code'] }} / {{ $shift['name'] }} ({{ substr($shift['start_time'], 0, 5) }}–{{ substr($shift['end_time'], 0, 5) }})</option>
                        @endforeach
                    </select>
                </div>
                @foreach (['checkIn' => 'masuk', 'checkOut' => 'keluar'] as $field => $label)
                    <fieldset wire:key="bulk-time-{{ $field }}">
                        <legend class="mb-1 text-sm font-semibold">Waktu {{ $label }} <span class="font-normal text-gray-500">(opsional)</span></legend>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label for="bulk-{{ $field }}-day" class="mb-1 block text-xs text-gray-500">Tanggal {{ $label }}</label>
                                <select id="bulk-{{ $field }}-day" wire:model="bulkForm.{{ $field }}Day" class="w-full rounded-lg border border-gray-300 px-3 py-2.5">
                                    <option value="0">Tanggal sel</option>
                                    <option value="1">Hari berikutnya</option>
                                </select>
                            </div>
                            <div>
                                <label for="bulk-{{ $field }}-time" class="mb-1 block text-xs text-gray-500">Jam {{ $label }}</label>
                                <input id="bulk-{{ $field }}-time" type="time" wire:model="bulkForm.{{ $field }}" class="w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2.5">
                            </div>
                        </div>
                    </fieldset>
                @endforeach
                <p class="text-xs text-gray-600">Kosongkan waktu untuk membuat jadwal yang belum masuk. Jam yang diisi berlaku pada tanggal masing-masing sel.</p>
            @endif
            <div>
                <label for="bulk-notes" class="mb-1 block text-sm font-semibold">Catatan <span class="font-normal text-gray-500">(opsional)</span></label>
                <textarea id="bulk-notes" wire:model="bulkForm.notes" maxlength="1000" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
            </div>
            <button type="submit" wire:loading.attr="disabled" @disabled($bulkSelection === []) class="w-full rounded-lg bg-[#FFED00] px-4 py-2.5 font-semibold disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-gray-600">Buat {{ count($bulkSelection) }} absensi</button>
            <span wire:loading wire:target="saveBulkAttendance" role="status" class="block text-sm text-gray-600">Menyimpan absensi…</span>
        </form>
    @endif
</dialog>
