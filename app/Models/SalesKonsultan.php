<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesKonsultan extends Model
{
    use HasFactory;

    protected $fillable = [
        'rentang_satu',
        'rentang_dua',
        'persen',
    ];

    protected function casts(): array
    {
        return [
            'persen' => 'decimal:2',
        ];
    }

    public static function findByNominal(float $nominal): ?self
    {
        return self::all()->first(function ($row) use ($nominal) {
            $satu = strtolower($row->rentang_satu);
            $dua = strtolower($row->rentang_dua);

            if ($satu === 'min') {
                return $nominal < (float) $row->rentang_dua;
            }

            if ($dua === 'plus') {
                return $nominal >= (float) $row->rentang_satu;
            }

            return $nominal >= (float) $row->rentang_satu && $nominal < (float) $row->rentang_dua;
        });
    }
}
