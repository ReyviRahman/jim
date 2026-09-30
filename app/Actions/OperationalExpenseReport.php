<?php

namespace App\Actions;

use App\Models\BeverageSale;
use App\Models\Expense;
use App\Models\MembershipTransaction;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OperationalExpenseReport
{
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $source = '',
        public readonly string $kind = '',
        public readonly string $shift = '',
    ) {}

    public function query(): Builder
    {
        $gym = MembershipTransaction::query()
            ->leftJoin('users', 'users.id', '=', 'membership_transactions.admin_id')
            ->where('payment_method', 'operasional')
            ->selectRaw("membership_transactions.id AS record_id, 'membership' AS record_source, 'Gym' AS source, 'Operasional' AS kind, payment_date AS occurred_at, invoice_number AS reference, package_name AS description, users.name AS staff, membership_transactions.shift, amount")
            ->toBase();
        $expenses = Expense::query()
            ->leftJoin('users', 'users.id', '=', 'expenses.admin_id')
            ->selectRaw("expenses.id AS record_id, 'expense' AS record_source, 'Gym' AS source, 'Pengeluaran' AS kind, expense_date AS occurred_at, NULL AS reference, description, users.name AS staff, expenses.shift, amount")
            ->toBase();
        $beverages = BeverageSale::query()
            ->whereIn('keterangan_bayar', ['operasional', 'pengeluaran_umum'])
            ->selectRaw("id AS record_id, 'beverage' AS record_source, 'Minuman' AS source, CASE WHEN keterangan_bayar = 'operasional' THEN 'Operasional' ELSE 'Pengeluaran' END AS kind, waktu_transaksi AS occurred_at, NULL AS reference, nama_produk AS description, nama_staff AS staff, shift, total_harga AS amount")
            ->toBase();

        return DB::query()->fromSub($gym->unionAll($expenses)->unionAll($beverages), 'report')
            ->where('occurred_at', '>=', $this->startDate.' 00:00:00')
            ->where('occurred_at', '<', Carbon::parse($this->endDate)->addDay()->toDateString().' 00:00:00')
            ->when($this->source !== '', fn (Builder $query) => $query->where('source', $this->source))
            ->when($this->kind !== '', fn (Builder $query) => $query->where('kind', $this->kind))
            ->when($this->shift !== '', fn (Builder $query) => $query->where('shift', $this->shift));
    }

    public function details(): Builder
    {
        return $this->query()->orderByDesc('occurred_at')->orderBy('record_source')->orderByDesc('record_id');
    }

    /** @return array<string, array{operasional: int, pengeluaran: int}> */
    public function summary(): array
    {
        $summary = array_fill_keys(['Gym', 'Minuman', 'Total'], ['operasional' => 0, 'pengeluaran' => 0]);
        foreach ($this->query()->selectRaw('source, kind, SUM(amount) AS total')->groupBy('source', 'kind')->get() as $row) {
            $kind = mb_strtolower($row->kind);
            $summary[$row->source][$kind] = (int) $row->total;
            $summary['Total'][$kind] += (int) $row->total;
        }

        return $summary;
    }

    /** @return array<int, array<int, string>> */
    public function headings(): array
    {
        return [
            ['Operasional & Pengeluaran'],
            ['Periode', $this->startDate.' s.d. '.$this->endDate],
            ['Sumber', $this->source ?: 'Semua', 'Jenis', $this->kind ?: 'Semua', 'Shift', $this->shift ?: 'Semua'],
            [],
        ];
    }
}
