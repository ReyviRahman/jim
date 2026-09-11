<?php

namespace App;

use App\Models\AttendanceEmployee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeAttendanceEditor
{
    /**
     * @param  array<int, array{employeeId: int, date: string}>  $cells
     * @param  array{status: string, shift?: string, notes?: string, checkIn?: string, checkOut?: string, checkInDay?: string, checkOutDay?: string}  $form
     */
    public function saveBulk(User $actor, array $cells, string $month, string $search, array $form): int
    {
        abort_unless($actor->role === 'admin', 403);
        $form += ['shift' => '', 'notes' => '', 'checkIn' => '', 'checkOut' => '', 'checkInDay' => '0', 'checkOutDay' => '0'];
        Validator::make(['cells' => $cells, 'month' => $month, 'bulkForm' => $form], [
            'cells' => ['required', 'array', 'list'],
            'cells.*' => ['required', 'array:employeeId,date'],
            'cells.*.employeeId' => ['required', 'integer'],
            'cells.*.date' => ['required', 'date_format:Y-m-d'],
            'month' => ['required', 'date_format:Y-m'],
            'bulkForm.status' => ['required', Rule::enum(EmployeeAttendanceStatus::class)],
            'bulkForm.shift' => ['exclude_unless:bulkForm.status,hadir', 'required', 'integer'],
            'bulkForm.notes' => ['nullable', 'string', 'max:1000'],
            'bulkForm.checkIn' => ['exclude_unless:bulkForm.status,hadir', 'nullable', 'date_format:H:i'],
            'bulkForm.checkOut' => ['exclude_unless:bulkForm.status,hadir', 'nullable', 'date_format:H:i'],
            'bulkForm.checkInDay' => ['exclude_unless:bulkForm.status,hadir', Rule::in(['0', '1'])],
            'bulkForm.checkOutDay' => ['exclude_unless:bulkForm.status,hadir', Rule::in(['0', '1'])],
        ])->validate();
        $keys = collect($cells)->map(fn (array $cell): string => $cell['employeeId'].':'.$cell['date']);
        if ($keys->unique()->count() !== count($cells)) {
            throw ValidationException::withMessages(['bulkSelection' => 'Pilihan sel tidak boleh berulang.']);
        }

        return DB::transaction(function () use ($actor, $cells, $month, $search, $form): int {
            $employees = collect();
            foreach (collect(array_column($cells, 'employeeId'))->unique()->sort() as $employeeId) {
                $employee = User::query()->lockForUpdate()->find($employeeId);
                if ($employee !== null) {
                    $employees->put($employee->id, $employee);
                }
            }
            $eligible = User::query()->forEmployeeAttendance($search)->whereIn('id', $employees->keys())->pluck('id');
            $role = null;
            foreach ($cells as $cell) {
                $employee = $employees->get($cell['employeeId']);
                $label = ($employee?->name ?? 'Karyawan #'.$cell['employeeId']).' / '.$cell['date'];
                if (! $eligible->contains($cell['employeeId']) || substr($cell['date'], 0, 7) !== $month) {
                    throw ValidationException::withMessages(['bulkSelection' => $label.': sel tidak tersedia pada tabel bulan ini.']);
                }
                $role ??= $employee->role;
                if ($employee->role !== $role) {
                    throw ValidationException::withMessages(['bulkSelection' => $label.': pilih karyawan dengan role yang sama.']);
                }
                $occupied = $employee->employeeAttendances()->where('attendance_date', $cell['date'])->lockForUpdate()->exists();
                if ($occupied) {
                    throw ValidationException::withMessages(['bulkSelection' => $label.': absensi sudah terisi. Batalkan pilihan sel ini lalu coba kembali.']);
                }
                $single = ['status' => $form['status'], 'shift' => (string) $form['shift'], 'notes' => $form['notes'], 'checkIn' => '', 'checkOut' => ''];
                if ($form['status'] === EmployeeAttendanceStatus::Hadir->value) {
                    foreach (['checkIn', 'checkOut'] as $field) {
                        if (filled($form[$field])) {
                            $single[$field] = Carbon::parse($cell['date'], config('app.timezone'))->addDays((int) $form[$field.'Day'])->format('Y-m-d').'T'.$form[$field];
                        }
                    }
                }
                try {
                    $this->save($actor, $employee->id, $cell['date'], null, $single);
                } catch (ValidationException $exception) {
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $errors[str_replace('form.', 'bulkForm.', $field)] = array_map(fn (string $message): string => $label.': '.$message, $messages);
                    }
                    throw ValidationException::withMessages($errors);
                }
            }

            return count($cells);
        }, attempts: 3);
    }

    public static function revision(?AttendanceEmployee $record): ?string
    {
        return $record === null ? null : hash('sha256', json_encode($record->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    /** @param array{status: string, notes: string, shift: string, checkIn: string, checkOut: string} $form */
    public function save(User $actor, int $employeeId, string $date, ?string $revision, array $form): AttendanceEmployee
    {
        abort_unless($actor->role === 'admin', 403);
        $form += ['notes' => '', 'shift' => '', 'checkIn' => '', 'checkOut' => ''];
        Validator::make(['date' => $date, 'form' => $form], [
            'date' => ['required', 'date_format:Y-m-d'],
            'form.status' => ['required', Rule::enum(EmployeeAttendanceStatus::class)],
            'form.notes' => ['nullable', 'string', 'max:1000'],
            'form.shift' => ['required_if:form.status,hadir', 'string'],
            'form.checkIn' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'form.checkOut' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ])->validate();

        return DB::transaction(function () use ($employeeId, $date, $revision, $form): AttendanceEmployee {
            $employee = User::query()->where('role', '!=', 'member')->lockForUpdate()->findOrFail($employeeId);
            $record = $employee->employeeAttendances()->where('attendance_date', $date)->lockForUpdate()->first();
            $this->assertCurrent($record, $revision);
            $data = [
                'status' => $form['status'],
                'notes' => trim($form['notes']) === '' ? null : trim($form['notes']),
                'check_in_time' => null, 'check_out_time' => null,
                'shift_code' => null, 'shift_name' => null, 'shift_role' => null,
                'shift_start_time' => null, 'shift_end_time' => null,
                'scheduled_start_at' => null, 'scheduled_end_at' => null, 'checkout_deadline_at' => null,
            ];
            if ($form['status'] === EmployeeAttendanceStatus::Hadir->value) {
                $data = array_merge($data, $this->presenceData($employee, $record, $date, $form));
            }
            $record ??= $employee->employeeAttendances()->make(['attendance_date' => $date]);
            $record->fill($data)->save();

            return $record;
        }, attempts: 3);
    }

    public function delete(User $actor, int $employeeId, string $date, ?string $revision): void
    {
        abort_unless($actor->role === 'admin', 403);
        DB::transaction(function () use ($employeeId, $date, $revision): void {
            $employee = User::query()->where('role', '!=', 'member')->lockForUpdate()->findOrFail($employeeId);
            $record = $employee->employeeAttendances()->where('attendance_date', $date)->lockForUpdate()->first();
            $this->assertCurrent($record, $revision);
            $record?->delete();
        }, attempts: 3);
    }

    private function assertCurrent(?AttendanceEmployee $record, ?string $revision): void
    {
        if (self::revision($record) !== $revision) {
            throw ValidationException::withMessages(['conflict' => 'Data berubah sejak dialog dibuka. Muat ulang detail sebelum menyimpan atau menghapus.']);
        }
    }

    /**
     * @param  array{status: string, notes: string, shift: string, checkIn: string, checkOut: string}  $form
     * @return array<string, mixed>
     */
    private function presenceData(User $employee, ?AttendanceEmployee $record, string $date, array $form): array
    {
        if ($form['shift'] === 'snapshot' && $record?->status === EmployeeAttendanceStatus::Hadir) {
            $snapshot = $record->only(['shift_code', 'shift_name', 'shift_role', 'shift_start_time', 'shift_end_time']);
        } else {
            $shift = Shift::query()->where('role', $employee->role)->find($form['shift']);
            if ($shift === null) {
                throw ValidationException::withMessages(['form.shift' => 'Pilih shift yang sesuai dengan role karyawan.']);
            }
            $snapshot = ['shift_code' => $shift->code, 'shift_name' => $shift->name, 'shift_role' => $shift->role, 'shift_start_time' => $shift->start_time, 'shift_end_time' => $shift->end_time];
        }
        $start = Carbon::parse($date, config('app.timezone'))->setTimeFromTimeString($snapshot['shift_start_time']);
        $end = Carbon::parse($date, config('app.timezone'))->setTimeFromTimeString($snapshot['shift_end_time']);
        if ($end->equalTo($start)) {
            throw ValidationException::withMessages(['form.shift' => 'Jam mulai dan selesai shift tidak boleh sama.']);
        }
        if ($end->lessThan($start)) {
            $end->addDay();
        }
        $deadline = $start->copy()->addDay();
        $checkIn = filled($form['checkIn']) ? Carbon::createFromFormat('!Y-m-d\TH:i', $form['checkIn'], config('app.timezone')) : null;
        $checkOut = filled($form['checkOut']) ? Carbon::createFromFormat('!Y-m-d\TH:i', $form['checkOut'], config('app.timezone')) : null;
        if ($record?->check_in_time?->format('Y-m-d\TH:i') === $form['checkIn']) {
            $checkIn = $record->check_in_time->copy();
        }
        if ($record?->check_out_time?->format('Y-m-d\TH:i') === $form['checkOut']) {
            $checkOut = $record->check_out_time->copy();
        }
        $now = now(config('app.timezone'));
        if ($checkIn !== null && ($checkIn->greaterThan($now) || ! $checkIn->betweenIncluded($start, $end))) {
            throw ValidationException::withMessages(['form.checkIn' => 'Jam masuk harus berada dalam jadwal shift dan tidak boleh melewati waktu sekarang.']);
        }
        if ($checkOut !== null && $checkIn === null) {
            throw ValidationException::withMessages(['form.checkOut' => 'Isi waktu masuk sebelum mengisi waktu keluar.']);
        }
        if ($checkOut !== null && ($checkOut->lessThanOrEqualTo($checkIn) || $checkOut->greaterThanOrEqualTo($deadline) || $checkOut->greaterThan($now))) {
            throw ValidationException::withMessages(['form.checkOut' => 'Jam keluar harus setelah masuk, sebelum batas checkout, dan tidak melewati waktu sekarang.']);
        }

        return $snapshot + ['check_in_time' => $checkIn, 'check_out_time' => $checkOut, 'scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'checkout_deadline_at' => $deadline];
    }
}
