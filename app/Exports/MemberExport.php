<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MemberExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(public ?string $search = '') {}

    public function query()
    {
        return User::query()
            ->where('role', 'member')
            ->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            })
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama',
            'Email',
            'Pekerjaan',
            'Status Akun',
            'Tanggal Bergabung',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->occupation ?? '-',
            $user->is_active ? 'Aktif' : 'Nonaktif',
            $user->joined_at?->format('Y-m-d') ?? '-',
        ];
    }
}
