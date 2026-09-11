<?php

namespace App\Livewire;

use App\EmployeeAttendanceEditor;
use App\EmployeeAttendanceStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait EmployeeAttendanceCells
{
    #[Locked]
    public ?int $cellEmployeeId = null;

    #[Locked]
    public string $cellDate = '';

    #[Locked]
    public ?string $cellRevision = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $cellDetail = [];

    /** @var array<int, array{id: int, name: string, code: string, start_time: string, end_time: string}> */
    #[Locked]
    public array $cellShifts = [];

    /** @var array{status: string, notes: string, shift: string, checkIn: string, checkOut: string} */
    public array $form = ['status' => 'hadir', 'notes' => '', 'shift' => '', 'checkIn' => '', 'checkOut' => ''];

    public bool $editingCell = false;

    public bool $confirmingCellDeletion = false;

    public function openAttendanceCell(int $employeeId, string $date): void
    {
        $actor = auth()->user();
        abort_unless($this->employeesOnly && (in_array($actor?->role, ['admin', 'kasir_gym'], true) || $actor?->isHeadCoach()), 403);
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $employee = User::query()->where('role', '!=', 'member')->findOrFail($employeeId);
        $record = $employee->employeeAttendances()->where('attendance_date', $date)->first();
        $this->resetValidation();
        $this->cellEmployeeId = $employee->id;
        $this->cellDate = $date;
        $this->cellRevision = EmployeeAttendanceEditor::revision($record);
        $this->cellDetail = [
            'name' => $employee->name,
            'date' => Carbon::parse($date)->locale('id')->translatedFormat('d F Y'),
            'status' => $record?->status->label(),
            'shift' => $record?->shift_code.' / '.$record?->shift_name,
            'schedule' => substr($record?->shift_start_time ?? '', 0, 5).' – '.substr($record?->shift_end_time ?? '', 0, 5),
            'in' => $record?->check_in_time?->format('d/m/Y H:i') ?? ($record?->status === EmployeeAttendanceStatus::Hadir ? 'Belum masuk' : '—'),
            'out' => $record?->check_out_time?->format('d/m/Y H:i') ?? '—',
            'device' => $record?->nama_di_alat ?: '—',
            'notes' => $record?->notes,
            'hasSnapshot' => $record?->status === EmployeeAttendanceStatus::Hadir,
        ];
        $this->cellShifts = Shift::query()->where('role', $employee->role)->orderBy('start_time')->get(['id', 'name', 'code', 'start_time', 'end_time'])->toArray();
        $this->form = [
            'status' => $record?->status->value ?? 'hadir',
            'notes' => $record?->notes ?? '',
            'shift' => $record?->status === EmployeeAttendanceStatus::Hadir ? 'snapshot' : (string) ($employee->shift ?? ''),
            'checkIn' => $record?->check_in_time?->format('Y-m-d\TH:i') ?? '',
            'checkOut' => $record?->check_out_time?->format('Y-m-d\TH:i') ?? '',
        ];
        $this->editingCell = $record === null && auth()->user()->role === 'admin';
        $this->confirmingCellDeletion = false;
        $this->dispatch('attendance-cell-opened');
    }

    public function editAttendanceCell(): void
    {
        $this->authorizeCellMutation();
        $this->editingCell = true;
        $this->confirmingCellDeletion = false;
    }

    public function reloadAttendanceCell(): void
    {
        abort_if($this->cellEmployeeId === null, 404);
        $this->openAttendanceCell($this->cellEmployeeId, $this->cellDate);
    }

    public function saveAttendanceCell(): void
    {
        $this->authorizeCellMutation();
        try {
            app(EmployeeAttendanceEditor::class)->save(auth()->user(), $this->cellEmployeeId, $this->cellDate, $this->cellRevision, $this->form);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
            $this->dispatch('attendance-cell-invalid', field: array_key_first($exception->errors()));

            return;
        }
        $this->closeAttendanceCell();
        session()->flash('success', 'Absensi berhasil disimpan.');
    }

    public function confirmDeleteAttendanceCell(): void
    {
        $this->authorizeCellMutation();
        $this->confirmingCellDeletion = true;
    }

    public function deleteAttendanceCell(): void
    {
        $this->authorizeCellMutation();
        abort_unless($this->confirmingCellDeletion, 403);
        app(EmployeeAttendanceEditor::class)->delete(auth()->user(), $this->cellEmployeeId, $this->cellDate, $this->cellRevision);
        $this->closeAttendanceCell();
        session()->flash('success', 'Absensi berhasil dihapus.');
    }

    public function closeAttendanceCell(): void
    {
        $this->reset('cellEmployeeId', 'cellDate', 'cellRevision', 'cellDetail', 'cellShifts', 'editingCell', 'confirmingCellDeletion');
        $this->resetValidation();
        $this->dispatch('attendance-cell-closed');
    }

    private function authorizeCellMutation(): void
    {
        abort_unless($this->employeesOnly && auth()->user()?->role === 'admin', 403);
        abort_if($this->cellEmployeeId === null, 404);
    }
}
