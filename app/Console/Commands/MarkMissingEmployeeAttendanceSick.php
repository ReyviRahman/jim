<?php

namespace App\Console\Commands;

use App\EmployeeAttendanceStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarkMissingEmployeeAttendanceSick extends Command
{
    protected $signature = 'employees:mark-missing-attendance-sick';

    protected $description = 'Tandai karyawan yang belum check-in hari ini sebagai sakit.';

    public function handle(): int
    {
        $date = now('Asia/Jakarta')->toDateString();
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $exitCode = self::SUCCESS;

        try {
            foreach (User::query()->forEmployeeAttendance()->select('id')->lazyById(200) as $user) {
                $counts[$this->markEmployee($user->id, $date)]++;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Pemeriksaan absensi gagal: '.$exception->getMessage());
            $exitCode = self::FAILURE;
        }

        $this->info("Tanggal {$date}: dibuat {$counts['created']}, diperbarui {$counts['updated']}, dilewati {$counts['skipped']}.");

        return $exitCode;
    }

    private function markEmployee(int $userId, string $date): string
    {
        return DB::transaction(function () use ($userId, $date): string {
            $employee = User::query()->forEmployeeAttendance()->lockForUpdate()->find($userId);
            if ($employee === null) {
                return 'skipped';
            }

            $attendance = $employee->employeeAttendances()->where('attendance_date', $date)->lockForUpdate()->first();
            if ($attendance === null) {
                $employee->employeeAttendances()->create([
                    'attendance_date' => $date,
                    'status' => EmployeeAttendanceStatus::Sakit,
                ]);

                return 'created';
            }

            if ($attendance->check_in_time !== null || $attendance->status !== EmployeeAttendanceStatus::Hadir) {
                return 'skipped';
            }

            $attendance->update(['status' => EmployeeAttendanceStatus::Sakit]);

            return 'updated';
        }, attempts: 3);
    }
}
