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
            'form.checkIn' => ['required_if:form.status,hadir', 'nullable', 'date_format:Y-m-d\TH:i'],
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
        $checkIn = Carbon::createFromFormat('!Y-m-d\TH:i', $form['checkIn'], config('app.timezone'));
        $checkOut = filled($form['checkOut']) ? Carbon::createFromFormat('!Y-m-d\TH:i', $form['checkOut'], config('app.timezone')) : null;
        if ($record?->check_in_time?->format('Y-m-d\TH:i') === $form['checkIn']) {
            $checkIn = $record->check_in_time->copy();
        }
        if ($record?->check_out_time?->format('Y-m-d\TH:i') === $form['checkOut']) {
            $checkOut = $record->check_out_time->copy();
        }
        $now = now(config('app.timezone'));
        if ($checkIn->greaterThan($now) || ! $checkIn->betweenIncluded($start, $end)) {
            throw ValidationException::withMessages(['form.checkIn' => 'Jam masuk harus berada dalam jadwal shift dan tidak boleh melewati waktu sekarang.']);
        }
        if ($checkOut !== null && ($checkOut->lessThanOrEqualTo($checkIn) || $checkOut->greaterThanOrEqualTo($deadline) || $checkOut->greaterThan($now))) {
            throw ValidationException::withMessages(['form.checkOut' => 'Jam keluar harus setelah masuk, sebelum batas checkout, dan tidak melewati waktu sekarang.']);
        }

        return $snapshot + ['check_in_time' => $checkIn, 'check_out_time' => $checkOut, 'scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'checkout_deadline_at' => $deadline];
    }
}
