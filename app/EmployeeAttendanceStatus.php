<?php

namespace App;

enum EmployeeAttendanceStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Off => 'Off',
        };
    }
}
