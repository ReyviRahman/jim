<?php

namespace App\Livewire;

use App\EmployeeAttendanceEditor;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait EmployeeAttendanceBulk
{
    #[Locked]
    public bool $selectingAttendance = false;

    /** @var array<string, array{employeeId: int, date: string, name: string, role: string}> */
    #[Locked]
    public array $bulkSelection = [];

    #[Locked]
    public ?string $bulkRole = null;

    #[Locked]
    public bool $bulkDialogOpen = false;

    /** @var array<int, array{id: int, name: string, code: string, start_time: string, end_time: string}> */
    #[Locked]
    public array $bulkShifts = [];

    /** @var array{status: string, shift: string, notes: string, checkIn: string, checkOut: string, checkInDay: string, checkOutDay: string} */
    public array $bulkForm = ['status' => 'hadir', 'shift' => '', 'notes' => '', 'checkIn' => '', 'checkOut' => '', 'checkInDay' => '0', 'checkOutDay' => '0'];

    public function beginBulkAttendance(): void
    {
        $this->authorizeBulkAttendance();
        $this->closeAttendanceCell();
        $this->cancelBulkAttendance();
        $this->selectingAttendance = true;
    }

    public function toggleAttendanceSelection(int $employeeId, string $date): void
    {
        $this->authorizeBulkAttendance();
        abort_unless($this->selectingAttendance, 403);
        $this->resetValidation();
        $key = $employeeId.':'.$date;
        if (isset($this->bulkSelection[$key])) {
            unset($this->bulkSelection[$key]);
            if ($this->bulkSelection === []) {
                $this->bulkRole = null;
            }

            return;
        }
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $employee = User::query()->forEmployeeAttendance($this->search)->find($employeeId);
        if ($employee === null || substr($date, 0, 7) !== $this->month) {
            $this->addError('bulkSelection', 'Sel tidak tersedia pada tabel bulan ini.');

            return;
        }
        if ($this->bulkRole !== null && $employee->role !== $this->bulkRole) {
            $this->addError('bulkSelection', 'Pilih karyawan dengan role yang sama.');

            return;
        }
        $data = $this->employeeMonthData();
        if ($data['cells'][$employeeId]->get($date, collect())->isNotEmpty()) {
            $this->addError('bulkSelection', $employee->name.' / '.$date.': absensi sudah terisi.');

            return;
        }
        $this->bulkRole = $employee->role;
        $this->bulkSelection[$key] = ['employeeId' => $employeeId, 'date' => $date, 'name' => $employee->name, 'role' => $employee->role];
    }

    public function openBulkAttendance(): void
    {
        $this->authorizeBulkAttendance();
        abort_unless($this->selectingAttendance, 403);
        $this->resetValidation();
        if ($this->bulkSelection === []) {
            $this->addError('bulkSelection', 'Pilih minimal satu sel kosong.');

            return;
        }
        $this->bulkShifts = Shift::query()->where('role', $this->bulkRole)->orderBy('start_time')->get(['id', 'name', 'code', 'start_time', 'end_time'])->toArray();
        if (! in_array((int) $this->bulkForm['shift'], array_column($this->bulkShifts, 'id'), true)) {
            $assignments = User::query()->whereIn('id', array_column($this->bulkSelection, 'employeeId'))->pluck('shift')->unique();
            $shared = $assignments->count() === 1 ? $assignments->first() : null;
            $this->bulkForm['shift'] = in_array($shared, array_column($this->bulkShifts, 'id'), true) ? (string) $shared : '';
        }
        $this->bulkDialogOpen = true;
        $this->dispatch('attendance-bulk-opened');
    }

    public function closeBulkAttendance(): void
    {
        $this->bulkDialogOpen = false;
        $this->dispatch('attendance-bulk-closed');
    }

    public function cancelBulkAttendance(): void
    {
        $this->closeBulkAttendance();
        $this->reset('selectingAttendance', 'bulkSelection', 'bulkRole', 'bulkShifts', 'bulkForm');
        $this->resetValidation();
    }

    public function saveBulkAttendance(): void
    {
        $this->authorizeBulkAttendance();
        if (! $this->selectingAttendance || ! $this->bulkDialogOpen || $this->bulkSelection === []) {
            $this->addError('bulkSelection', 'Pilih sel dan buka form sebelum menyimpan.');

            return;
        }
        $cells = array_values(array_map(fn (array $cell): array => ['employeeId' => $cell['employeeId'], 'date' => $cell['date']], $this->bulkSelection));
        try {
            $count = app(EmployeeAttendanceEditor::class)->saveBulk(auth()->user(), $cells, $this->month, $this->search, $this->bulkForm);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
            $this->dispatch('attendance-bulk-invalid');

            return;
        }
        $this->cancelBulkAttendance();
        session()->flash('success', $count.' absensi berhasil dibuat.');
    }

    private function authorizeBulkAttendance(): void
    {
        abort_unless($this->employeesOnly && auth()->user()?->role === 'admin', 403);
    }
}
