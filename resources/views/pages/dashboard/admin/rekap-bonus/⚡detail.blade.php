<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use App\Models\BonusPayment;
use App\Models\User;
use App\Models\Membership;
use App\Models\SalesKonsultan;
use App\Models\KasirKonsultan;
use App\Models\CoachKonsultan;
use Carbon\Carbon;
use App\Exports\RekapBonusExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts::admin')] class extends Component 
{
    
    use WithPagination;

    #[Locked]
    public User $staffUser; // Variabel untuk menyimpan data user (Admin/Sales)
    public $search = '';
    public $filterTime = 'month'; // Default bulan ini
    public $startDate;
    public $endDate;

    public $sortBy = 'transactions_max_payment_date';
    public $sortDirection = 'desc';

    public bool $showBonusPaymentModal = false;
    public string $bonusPaymentSearch = '';
    public string $nominalPotongan = '0';
    public string $keteranganPotongan = '';

    public bool $showBonusPaymentDetailModal = false;
    public string $bonusPaymentDetailSearch = '';
    public string $detailNominalPotongan = '0';
    public string $detailKeteranganPotongan = '';
    public ?string $bonusPaymentDetailSuccess = null;

    #[Locked]
    public ?int $selectedBonusPaymentId = null;

    #[Locked]
    public array $bonusPaymentRows = [];

    #[Locked]
    public float $bonusPaymentTotalNominalAkhir = 0;

    #[Locked]
    public float $bonusPaymentPercentage = 0;

    #[Locked]
    public ?string $bonusPaymentRangeStart = null;

    #[Locked]
    public ?string $bonusPaymentRangeEnd = null;

    #[Locked]
    public float $bonusPaymentAmount = 0;

    #[Locked]
    public ?string $bonusPaymentDateStart = null;

    #[Locked]
    public ?string $bonusPaymentDateEnd = null;

    #[Locked]
    public string $bonusPaymentPageSearch = '';

    #[Locked]
    public int $bonusPaymentStaffUserId = 0;

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function mount(User $user)
    {
        $this->staffUser = $user;
        $this->setFilterTime('month'); // Set default tanggal saat komponen diload
    }

    public function setFilterTime($time)
    {
        $this->filterTime = $time;

        switch ($time) {
            case 'today':
                $this->startDate = Carbon::today()->toDateString();
                $this->endDate = Carbon::today()->toDateString();
                break;
            case 'week':
                $this->startDate = Carbon::now()->startOfWeek()->toDateString();
                $this->endDate = Carbon::now()->endOfWeek()->toDateString();
                break;
            case 'month':
                $this->startDate = Carbon::now()->startOfMonth()->toDateString();
                $this->endDate = Carbon::now()->endOfMonth()->toDateString();
                break;
        }

        $this->resetPage(); // Reset paginasi setiap kali filter berubah
    }

    public function setDateRange($dateStr)
    {
        // Jika input kosong (user menghapus tanggal), hentikan fungsi
        if (empty($dateStr)) {
            return;
        }

        // Cek apakah ada kata ' to ' (berarti memilih rentang tanggal)
        if (str_contains($dateStr, ' to ')) {
            $dates = explode(' to ', $dateStr);
            $this->startDate = $dates[0];
            $this->endDate = $dates[1];
        } else {
            // Jika tidak ada ' to ', berarti user double-click 1 tanggal saja
            // Maka startDate dan endDate disamakan ke tanggal tersebut
            $this->startDate = $dateStr;
            $this->endDate = $dateStr;
        }

        $this->filterTime = 'custom';
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSortDirection()
    {
        $this->resetPage();
    }

    private function applyLatestPaymentDateFilter(Builder $query): Builder
    {
        return $query
            ->whereHas('transactions', function (Builder $query): void {
                $query->whereBetween('payment_date', [
                    $this->startDate,
                    $this->endDate,
                ]);
            })
            ->whereDoesntHave('transactions', function (Builder $query): void {
                $query->where('payment_date', '>', $this->endDate);
            });
    }

    private function getBaseQuery(): Builder
    {
        return Membership::where(function ($query) {
                $query->where('follow_up_id', $this->staffUser->id)
                      ->orWhere('follow_up_id_two', $this->staffUser->id);
            })
            ->where('type', '!=', 'visit')
            ->when(
                $this->staffUser->role === 'pt',
                fn (Builder $query): Builder => $query->where('type', 'pt')
            )
            ->where('payment_status', 'paid')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('members', function ($sub) {
                        $sub->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('user', function ($sub) {
                        $sub->where('name', 'like', '%' . $this->search . '%');
                    });
                });
            })
            ->when($this->startDate && $this->endDate, function ($query) {
                $this->applyLatestPaymentDateFilter($query);
            });
    }

    private function applySorting(Builder $query): Builder
    {
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        if ($this->sortBy === 'user_name') {
            return $query->join('users', 'memberships.user_id', '=', 'users.id')
                ->orderBy('users.name', $direction)
                ->select('memberships.*');
        }

        if ($this->sortBy === 'package_name') {
            return $query->orderBy('transaction_type', $direction)
                ->orderBy('package_name', $direction);
        }

        return $query->orderBy('transactions_max_payment_date', $direction);
    }

    #[Computed]
    public function memberships()
    {
        $query = $this->getBaseQuery()
            ->with([
                'user',
                'followUp',
                'followUpTwo',
                'gymPackage',
                'ptPackage',
                'transactions',
            ])
            ->withMax('transactions', 'payment_date');

        return $this->applySorting($query)->paginate(500);
    }

    #[Computed]
    public function totalNominalAkhir()
    {
        $memberships = $this->getBaseQuery()
            ->with('followUp:id,role')
            ->get(['total_paid', 'follow_up_id', 'follow_up_id_two', 'price_paid', 'normal_price', 'net_price', 'unrecommended_price']);

        $total = 0;
        foreach ($memberships as $membership) {
            $total += $membership->calculateNominalAkhir();
        }

        return $total;
    }

    #[Computed]
    public function bonusInfo(): array
    {
        return $this->resolveBonusInfo($this->totalNominalAkhir);
    }

    private function resolveBonusInfo(float $total): array
    {

        $range = match ($this->staffUser->role) {
            'kasir_gym' => KasirKonsultan::findByNominal($total),
            'sales' => SalesKonsultan::findByNominal($total),
            'pt' => CoachKonsultan::findByNominal($total),
            default => null,
        };

        if (! $range) {
            return [
                'rentang_satu' => null,
                'rentang_dua' => null,
                'persen' => 0,
                'total_bonus' => 0,
            ];
        }

        $persen = (float) $range->persen;
        $totalBonus = round($total * ($persen / 100), 2);

        return [
            'rentang_satu' => $range->rentang_satu,
            'rentang_dua' => $range->rentang_dua,
            'persen' => $persen,
            'total_bonus' => $totalBonus,
        ];
    }

    public function openBonusPaymentModal(): void
    {
        $this->authorizeBonusPayment();
        $this->closeBonusPaymentModal();

        $this->validate([
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $memberships = $this->applySorting(
            $this->getBaseQuery()
                ->with(['user', 'followUp:id,role'])
                ->withMax('transactions', 'payment_date')
        )->get();

        $rows = $memberships->map(function (Membership $membership): array {
            return [
                'membership_id' => $membership->id,
                'member_name' => $membership->user?->name ?? '-',
                'package_name' => trim(($membership->transaction_type ?? '').' '.($membership->package_name ?? '')),
                'nominal' => (float) ($membership->total_paid ?? 0),
                'nominal_akhir' => $membership->calculateNominalAkhir(),
                'payment_date' => $membership->transactions_max_payment_date
                    ? Carbon::parse($membership->transactions_max_payment_date)->toDateString()
                    : null,
            ];
        })->values()->all();

        $totalNominalAkhir = (float) collect($rows)->sum('nominal_akhir');
        $bonus = $this->resolveBonusInfo($totalNominalAkhir);

        if ($rows === [] || $totalNominalAkhir <= 0 || $bonus['total_bonus'] <= 0) {
            session()->flash('error', 'Tidak ada bonus yang dapat dibayar untuk filter ini.');

            return;
        }

        $this->resetValidation();
        $this->bonusPaymentRows = $rows;
        $this->bonusPaymentTotalNominalAkhir = $totalNominalAkhir;
        $this->bonusPaymentPercentage = (float) $bonus['persen'];
        $this->bonusPaymentRangeStart = (string) $bonus['rentang_satu'];
        $this->bonusPaymentRangeEnd = (string) $bonus['rentang_dua'];
        $this->bonusPaymentAmount = (float) $bonus['total_bonus'];
        $this->bonusPaymentDateStart = $this->startDate;
        $this->bonusPaymentDateEnd = $this->endDate;
        $this->bonusPaymentPageSearch = (string) $this->search;
        $this->bonusPaymentStaffUserId = $this->staffUser->id;
        $this->bonusPaymentSearch = '';
        $this->nominalPotongan = '0';
        $this->keteranganPotongan = '';
        $this->showBonusPaymentModal = true;
    }

    #[Computed]
    public function filteredBonusPaymentRows(): array
    {
        if ($this->bonusPaymentSearch === '') {
            return $this->bonusPaymentRows;
        }

        return collect($this->bonusPaymentRows)
            ->filter(fn (array $row): bool => Str::contains($row['member_name'], $this->bonusPaymentSearch, ignoreCase: true))
            ->values()
            ->all();
    }

    #[Computed]
    public function bonusPaymentNetAmount(): float
    {
        return max(0, $this->bonusPaymentAmount - $this->normalizedPotongan());
    }

    #[Computed]
    public function bonusPaymentHistory()
    {
        $this->authorizeViewBonusPaymentHistory();

        return BonusPayment::query()
            ->select([
                'id',
                'staff_user_id',
                'date_start',
                'date_end',
                'total_nominal_akhir',
                'bonus_percentage',
                'bonus_amount',
                'potongan',
                'net_amount',
                'paid_by',
                'paid_at',
            ])
            ->whereBelongsTo($this->staffUser, 'staffUser')
            ->with('paidBy:id,name')
            ->withCount('items')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(10, pageName: 'bonus-payments-page');
    }

    #[Computed]
    public function selectedBonusPayment(): ?BonusPayment
    {
        if (! $this->showBonusPaymentDetailModal || ! $this->selectedBonusPaymentId) {
            return null;
        }

        $this->authorizeViewBonusPaymentHistory();

        return $this->bonusPaymentForStaffOrFail($this->selectedBonusPaymentId)
            ->load('paidBy:id,name');
    }

    #[Computed]
    public function bonusPaymentDetailItems()
    {
        $payment = $this->selectedBonusPayment;
        abort_unless($payment, 404);

        $search = Str::limit(trim($this->bonusPaymentDetailSearch), 255, '');

        return $payment->items()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('member_name', 'like', '%'.$search.'%')
                        ->orWhere('package_name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('id')
            ->paginate(25, pageName: 'bonus-payment-items-page');
    }

    #[Computed]
    public function bonusPaymentDetailNetAmount(): float
    {
        $payment = $this->selectedBonusPayment;

        if (! $payment) {
            return 0;
        }

        return max(0, (float) $payment->bonus_amount - $this->normalizedDetailPotongan());
    }

    #[Computed]
    public function bonusPaymentDetailDeductionAmount(): float
    {
        return max(0, $this->normalizedDetailPotongan());
    }

    public function openBonusPaymentDetail(int $paymentId): void
    {
        $this->authorizeViewBonusPaymentHistory();
        $payment = $this->bonusPaymentForStaffOrFail($paymentId);

        $this->selectedBonusPaymentId = $payment->id;
        $this->bonusPaymentDetailSearch = '';
        $this->detailNominalPotongan = (string) (float) $payment->potongan;
        $this->detailKeteranganPotongan = $payment->keterangan_potongan ?? '';
        $this->bonusPaymentDetailSuccess = null;
        $this->showBonusPaymentDetailModal = true;
        $this->resetPage(pageName: 'bonus-payment-items-page');
        $this->resetValidation();
    }

    public function closeBonusPaymentDetail(): void
    {
        $this->showBonusPaymentDetailModal = false;
        $this->selectedBonusPaymentId = null;
        $this->bonusPaymentDetailSearch = '';
        $this->detailNominalPotongan = '0';
        $this->detailKeteranganPotongan = '';
        $this->bonusPaymentDetailSuccess = null;
        $this->resetPage(pageName: 'bonus-payment-items-page');
        $this->resetValidation();
    }

    public function updatingBonusPaymentDetailSearch(): void
    {
        $this->resetPage(pageName: 'bonus-payment-items-page');
    }

    public function updatingDetailNominalPotongan(): void
    {
        $this->bonusPaymentDetailSuccess = null;
    }

    public function updatingDetailKeteranganPotongan(): void
    {
        $this->bonusPaymentDetailSuccess = null;
    }

    public function updateBonusPaymentDeduction(): void
    {
        $this->authorizeBonusPayment();
        abort_unless($this->showBonusPaymentDetailModal && $this->selectedBonusPaymentId, 422);

        $potongan = $this->normalizedDetailPotongan();
        $this->detailKeteranganPotongan = trim($this->detailKeteranganPotongan);

        $payment = DB::transaction(function () use ($potongan): BonusPayment {
            $payment = BonusPayment::query()
                ->whereBelongsTo($this->staffUser, 'staffUser')
                ->whereKey($this->selectedBonusPaymentId)
                ->lockForUpdate()
                ->first();
            abort_unless($payment, 404);

            $this->validate([
                'detailNominalPotongan' => ['required', 'numeric', 'min:0', 'max:'.$payment->bonus_amount],
                'detailKeteranganPotongan' => [
                    Rule::requiredIf($potongan > 0),
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ], [
                'detailNominalPotongan.max' => 'Nominal potongan tidak boleh melebihi bonus bruto.',
                'detailKeteranganPotongan.required' => 'Keterangan potongan wajib diisi ketika ada potongan.',
            ]);

            $keterangan = $potongan > 0 ? $this->detailKeteranganPotongan : null;

            $payment->update([
                'potongan' => $potongan,
                'keterangan_potongan' => $keterangan,
                'net_amount' => round((float) $payment->bonus_amount - $potongan, 2),
            ]);

            return $payment;
        });

        $this->detailNominalPotongan = (string) $potongan;
        $this->detailKeteranganPotongan = $payment->keterangan_potongan ?? '';
        $this->bonusPaymentDetailSuccess = 'Potongan pembayaran bonus #'.$payment->id.' berhasil diperbarui.';
        unset($this->selectedBonusPayment, $this->bonusPaymentHistory, $this->bonusPaymentDetailNetAmount, $this->bonusPaymentDetailDeductionAmount);
        $this->resetValidation();
    }

    public function deleteBonusPayment(int $paymentId): void
    {
        $this->authorizeBonusPayment();

        DB::transaction(function () use ($paymentId): void {
            $payment = BonusPayment::query()
                ->whereBelongsTo($this->staffUser, 'staffUser')
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->first();

            abort_unless($payment, 404);

            $payment->delete();
        });

        if ($this->selectedBonusPaymentId === $paymentId) {
            $this->closeBonusPaymentDetail();
        }

        $this->resetPage(pageName: 'bonus-payments-page');
        unset($this->bonusPaymentHistory, $this->selectedBonusPayment, $this->bonusPaymentDetailItems);
        session()->flash('success', 'Pembayaran bonus #'.$paymentId.' beserta detailnya berhasil dihapus permanen.');
        $this->dispatch('scroll-to-bonus-payment-history');
    }

    public function closeBonusPaymentModal(): void
    {
        $this->showBonusPaymentModal = false;
        $this->bonusPaymentRows = [];
        $this->bonusPaymentSearch = '';
        $this->bonusPaymentTotalNominalAkhir = 0;
        $this->bonusPaymentPercentage = 0;
        $this->bonusPaymentRangeStart = null;
        $this->bonusPaymentRangeEnd = null;
        $this->bonusPaymentAmount = 0;
        $this->bonusPaymentDateStart = null;
        $this->bonusPaymentDateEnd = null;
        $this->bonusPaymentPageSearch = '';
        $this->bonusPaymentStaffUserId = 0;
        $this->nominalPotongan = '0';
        $this->keteranganPotongan = '';
        $this->resetValidation();
    }

    public function confirmBonusPayment(): void
    {
        $this->authorizeBonusPayment();

        abort_unless(
            $this->showBonusPaymentModal
                && $this->bonusPaymentRows !== []
                && $this->bonusPaymentAmount > 0
                && $this->bonusPaymentDateStart
                && $this->bonusPaymentDateEnd
                && $this->bonusPaymentStaffUserId === $this->staffUser->id,
            422,
            'Snapshot pembayaran bonus tidak valid.'
        );

        $potongan = $this->normalizedPotongan();

        $this->validate([
            'nominalPotongan' => ['required', 'numeric', 'min:0', 'max:'.$this->bonusPaymentAmount],
            'keteranganPotongan' => [
                Rule::requiredIf($potongan > 0),
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'nominalPotongan.max' => 'Nominal potongan tidak boleh melebihi bonus bruto.',
            'keteranganPotongan.required' => 'Keterangan potongan wajib diisi ketika ada potongan.',
        ]);

        $payment = DB::transaction(function () use ($potongan): BonusPayment {
            $payment = BonusPayment::create([
                'staff_user_id' => $this->bonusPaymentStaffUserId,
                'date_start' => $this->bonusPaymentDateStart,
                'date_end' => $this->bonusPaymentDateEnd,
                'search_filter' => $this->bonusPaymentPageSearch !== '' ? $this->bonusPaymentPageSearch : null,
                'total_nominal_akhir' => $this->bonusPaymentTotalNominalAkhir,
                'bonus_percentage' => $this->bonusPaymentPercentage,
                'range_start' => $this->bonusPaymentRangeStart,
                'range_end' => $this->bonusPaymentRangeEnd,
                'bonus_amount' => $this->bonusPaymentAmount,
                'potongan' => $potongan,
                'keterangan_potongan' => $potongan > 0 ? trim($this->keteranganPotongan) : null,
                'net_amount' => $this->bonusPaymentAmount - $potongan,
                'paid_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $payment->items()->createMany($this->bonusPaymentRows);

            return $payment;
        });

        $this->closeBonusPaymentModal();
        $this->resetPage(pageName: 'bonus-payments-page');
        session()->flash('success', 'Pembayaran bonus #'.$payment->id.' berhasil disimpan.');
        $this->dispatch('scroll-to-bonus-payment-history');
    }

    public function terbilang(int $number): string
    {
        $angka = [
            0 => 'nol',
            1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat',
            5 => 'lima', 6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan',
            10 => 'sepuluh', 11 => 'sebelas', 12 => 'dua belas',
            13 => 'tiga belas', 14 => 'empat belas', 15 => 'lima belas',
            16 => 'enam belas', 17 => 'tujuh belas', 18 => 'delapan belas', 19 => 'sembilan belas',
            20 => 'dua puluh', 30 => 'tiga puluh', 40 => 'empat puluh',
            50 => 'lima puluh', 60 => 'enam puluh', 70 => 'tujuh puluh',
            80 => 'delapan puluh', 90 => 'sembilan puluh',
        ];

        if ($number < 0) {
            return 'minus '.$this->terbilang(-$number);
        }

        if ($number < 21) {
            return $angka[$number];
        }

        if ($number < 100) {
            $puluh = (int) floor($number / 10) * 10;
            $sisa = $number % 10;

            return $angka[$puluh].($sisa > 0 ? ' '.$angka[$sisa] : '');
        }

        if ($number < 1000) {
            $ratus = (int) floor($number / 100);
            $sisa = $number % 100;
            $prefix = $ratus === 1 ? 'seratus' : $angka[$ratus].' ratus';

            return $prefix.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        if ($number < 1000000) {
            $ribu = (int) floor($number / 1000);
            $sisa = $number % 1000;
            $prefix = $ribu === 1 ? 'seribu' : $this->terbilang($ribu).' ribu';

            return $prefix.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        if ($number < 1000000000) {
            $juta = (int) floor($number / 1000000);
            $sisa = $number % 1000000;

            return $this->terbilang($juta).' juta'.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
        }

        $miliar = (int) floor($number / 1000000000);
        $sisa = $number % 1000000000;

        return $this->terbilang($miliar).' miliar'.($sisa > 0 ? ' '.$this->terbilang($sisa) : '');
    }

    private function normalizedPotongan(): float
    {
        return is_numeric($this->nominalPotongan) ? (float) $this->nominalPotongan : 0;
    }

    private function normalizedDetailPotongan(): float
    {
        return is_numeric($this->detailNominalPotongan) ? (float) $this->detailNominalPotongan : 0;
    }

    private function bonusPaymentForStaffOrFail(int $paymentId): BonusPayment
    {
        $payment = BonusPayment::query()
            ->whereBelongsTo($this->staffUser, 'staffUser')
            ->find($paymentId);

        abort_unless($payment, 404);

        return $payment;
    }

    private function authorizeViewBonusPaymentHistory(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'head_coach'], true), 403);
    }

    private function authorizeBonusPayment(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function exportExcel()
    {
        // Beri nama file yang dinamis berdasarkan nama staff dan tanggal download
        $fileName = 'Rekap_Bonus_' . str_replace(' ', '_', $this->staffUser->name) . '_' . '.xlsx';
        
        // Panggil Export Class dengan mengirimkan parameter filter yang sedang aktif
        return Excel::download(
            new RekapBonusExport($this->staffUser->id, $this->search, $this->startDate, $this->endDate), 
            $fileName
        );
    }

    
};
?>

<div>
    @if (session('success'))
        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex sm:flex-row flex-col justify-between items-center mb-6">
        <h5 class="text-xl font-semibold text-heading">
            Perhitungan Bonus {{ $staffUser->name }} Target 
            @if($startDate && $endDate)
                @if($startDate === $endDate)
                    {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}
                @else
                    {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                @endif
            @else
                -
            @endif
        </h5>
        <div class="flex gap-2">
            <button wire:click="exportExcel" wire:loading.attr="disabled" class="inline-flex items-center justify-center text-white bg-emerald-600 border border-transparent hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-300 shadow-xs font-medium rounded-md text-sm px-4 py-2.5 focus:outline-none disabled:opacity-50">
                <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Memproses...</span>
            </button>
        </div>
    </div>

    <div class="relative bg-neutral-primary-soft shadow-xs rounded-md border border-default mb-6">
        <div class="p-4 flex flex-col lg:flex-row items-center justify-between gap-4">
            
            {{-- Search --}}
            <div class="relative w-full lg:w-auto flex-1">
                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-4 h-4 text-body" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Cari nama member...">
            </div>
            
            {{-- Filters --}}
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                {{-- Datepicker Custom --}}
                <div class="relative w-full sm:w-56" wire:ignore>
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16M8 14h8m-4-7V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/></svg>
                    </div>
                    <input type="text" x-data x-init="flatpickr($el, { mode: 'range', dateFormat: 'Y-m-d', placeholder: 'Pilih Tanggal', onClose: function(selectedDates, dateStr) { $wire.setDateRange(dateStr) } })" class="block w-full ps-9 pe-3 py-2.5 bg-white border border-default-medium text-heading text-sm rounded-md focus:ring-brand focus:border-brand shadow-xs" placeholder="Pilih Rentang Tanggal">
                </div>

                {{-- Filter Presets --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="inline-flex items-center justify-center text-body bg-white border border-default-medium hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 shadow-xs font-medium rounded-md text-sm px-3 py-2.5" type="button">
                        <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M18.796 4H5.204a1 1 0 0 0-.753 1.659l5.302 6.058a1 1 0 0 1 .247.659v4.874a.5.5 0 0 0 .2.4l3 2.25a.5.5 0 0 0 .8-.4v-7.124a1 1 0 0 1 .247-.659l5.302-6.059c.566-.646.106-1.658-.753-1.658Z"/></svg>
                        @if($filterTime === 'today') Hari Ini
                        @elseif($filterTime === 'week') Minggu Ini
                        @elseif($filterTime === 'month') Bulan Ini
                        @elseif($filterTime === 'custom') Kustom @endif
                        <svg class="w-4 h-4 ms-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
                    </button>
                    
                    <div x-show="open" style="display: none;" class="absolute right-0 z-50 mt-2 bg-white border border-gray-200 rounded-md shadow-lg w-40">
                        <ul class="p-2 text-sm text-gray-700 font-medium">
                            <li><button type="button" wire:click="setFilterTime('today')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Hari ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('week')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Minggu ini</button></li>
                            <li><button type="button" wire:click="setFilterTime('month')" @click="open = false" class="w-full text-left p-2 hover:bg-gray-100 rounded">Bulan ini</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-default-medium p-3 xl:hidden">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-medium text-gray-500">Urutkan:</span>
                <button type="button" wire:click="sort('user_name')" class="rounded-md border px-2.5 py-1.5 font-medium {{ $sortBy === 'user_name' ? 'border-brand bg-brand text-[#34342F]' : 'border-default-medium bg-white text-body' }}">
                    Nama Member
                    @if($sortBy === 'user_name')
                        {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </button>
                <button type="button" wire:click="sort('package_name')" class="rounded-md border px-2.5 py-1.5 font-medium {{ $sortBy === 'package_name' ? 'border-brand bg-brand text-[#34342F]' : 'border-default-medium bg-white text-body' }}">
                    Paket Membership
                    @if($sortBy === 'package_name')
                        {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </button>
            </div>
        </div>

        <div class="overflow-hidden">
        <table class="block w-full table-fixed text-left text-xs leading-tight text-body xl:table">
            <colgroup class="hidden xl:table-column-group">
                <col class="w-[3%]">
                <col class="w-[12%]">
                <col class="w-[12%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
                <col class="w-[9%]">
                <col class="w-[9%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
                <col class="w-[9%]">
                <col class="w-[6%]">
            </colgroup>
            <thead class="hidden border-b border-default-medium bg-neutral-secondary-medium text-[10px] text-body xl:table-header-group 2xl:text-xs">
                <tr>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">No</th>
                    <th rowspan="3" wire:click="sort('user_name')" class="cursor-pointer break-words border border-default-medium px-1.5 py-2 align-middle font-medium select-none hover:bg-gray-200">
                        Nama Member
                        @if($sortBy === 'user_name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                        @endif
                    </th>
                    <th rowspan="3" wire:click="sort('package_name')" class="cursor-pointer break-words border border-default-medium px-1.5 py-2 align-middle font-medium select-none hover:bg-gray-200">
                        Paket Membership
                        @if($sortBy === 'package_name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                        @endif
                    </th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 text-right align-middle font-medium">Nominal</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 text-right align-middle font-medium">Nominal Akhir</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Follow Up 1</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Follow Up 2</th>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">Tgl Mulai</th>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">Tgl Selesai</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Tgl Bayar</th>
                    <th rowspan="3" class="break-words border border-default-medium px-1.5 py-2 align-middle font-medium">Aksi</th>
                </tr>
                <tr>
                    <th colspan="2" class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">MEMBERSHIP</th>
                </tr>
                <tr>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium">SALES ADMIN</th>
                    <th class="break-words border border-default-medium px-1.5 py-2 text-center font-medium uppercase">{{ $staffUser->name }}</th>
                </tr>
            </thead>
            <tbody class="grid gap-3 bg-gray-50 p-3 xl:table-row-group xl:bg-transparent xl:p-0">
               @forelse ($this->memberships as $membership)
                    @php
                        // Menentukan nama paket (transaction_type + package_name)
                        $packageName = trim(($membership->transaction_type ?? '') . ' ' . ($membership->package_name ?? ''));

                        $nominal = $membership->total_paid ?? 0;
                        $nominalAkhir = $membership->calculateNominalAkhir();
                    @endphp
                    
                    <tr wire:key="{{ $membership->id }}" class="grid grid-cols-1 overflow-hidden rounded-md border border-gray-200 bg-white shadow-xs sm:grid-cols-2 xl:table-row xl:rounded-none xl:border-x-0 xl:border-t-0 xl:shadow-none xl:hover:bg-gray-50">
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">No</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $loop->iteration + ($this->memberships->currentPage() - 1) * $this->memberships->perPage() }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Nama Member</span>
                            <span class="min-w-0 break-words text-right font-bold text-gray-800 sm:block sm:text-left xl:text-left">
                                {{ $membership->user->name ?? '-' }}
                            </span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Paket Membership</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">
                                <span class="inline-block max-w-full break-words rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 uppercase shadow-xs">
                                    {{ $packageName }}
                                </span>
                            </span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 text-right sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Nominal</span>
                            <span class="min-w-0 break-words sm:block">Rp {{ number_format($nominal, 0, ',', '.') }}</span>

                            @if(auth()->check() && auth()->user()->role === 'admin')
                                @php
                                    $priceLabelData = $membership->getPriceLabel();
                                @endphp

                                @if($priceLabelData)
                                    <div class="mt-1 flex justify-end sm:justify-start xl:justify-end">
                                        <span class="break-words rounded-full px-1.5 py-0.5 text-[9px] font-semibold {{ $priceLabelData['color'] }}">
                                            {{ $priceLabelData['label'] }}
                                        </span>
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 text-right sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Nominal Akhir</span>
                            <span class="min-w-0 break-words font-bold text-emerald-600 sm:block">Rp {{ number_format($nominalAkhir, 0, ',', '.') }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Follow Up 1</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $membership->followUp->name ?? '-' }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Follow Up 2</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $membership->followUpTwo->name ?? '-' }}</span>
                        </td>
                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Tgl Mulai</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $membership->start_date ? \Carbon\Carbon::parse($membership->start_date)->translatedFormat('l, d F Y') : 'BELUM AKTIF' }}</span>
                        </td>

                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            @php
                                $endDate = $membership->type === 'pt' ? $membership->pt_end_date : $membership->membership_end_date;
                            @endphp
                            <span class="font-medium text-gray-500 xl:hidden">Tgl Selesai</span>
                            <span class="min-w-0 break-words text-right sm:block sm:text-left xl:text-left">{{ $endDate ? \Carbon\Carbon::parse($endDate)->translatedFormat('l, d F Y') : 'BELUM AKTIF' }}</span>
                        </td>

                        <td class="flex min-w-0 items-start justify-between gap-4 border-b border-gray-100 px-3 py-2 sm:block xl:table-cell xl:border-0 xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Tgl Bayar</span>
                            <span class="min-w-0 break-words text-right font-semibold text-yellow-500 sm:block sm:text-left xl:text-left">{{ $membership->transactions->sortByDesc('payment_date')->first()?->payment_date?->translatedFormat('d F Y') ?? '-' }}</span>
                        </td>

                        <td class="flex min-w-0 items-start justify-between gap-4 px-3 py-2 sm:block xl:table-cell xl:px-1.5 xl:py-2 xl:align-top">
                            <span class="font-medium text-gray-500 xl:hidden">Aksi</span>
                            @if(auth()->user()->role === 'admin')
                                <a href="{{ route('admin.membership.edit', $membership->id) }}?redirect_to={{ urlencode('admin.rekap-bonus.detail') }}&redirect_id={{ $staffUser->id }}" class="inline-flex items-center break-words text-right text-xs font-medium text-brand hover:text-brand-dark sm:text-left">
                                    <svg class="me-1 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"></path></svg>
                                    Edit
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="block xl:table-row">
                        <td colspan="11" class="block px-3 py-8 text-center text-gray-500 xl:table-cell">
                            Belum ada riwayat bonus untuk rentang waktu ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($this->memberships->count() > 0)
            <tfoot class="block border-t-2 border-gray-300 bg-gray-100 font-semibold text-gray-900 xl:table-footer-group">
                    <tr class="grid grid-cols-1 xl:table-row">
                        <td colspan="4" class="block px-3 pt-3 text-left xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                            Total Keseluruhan:
                        </td>
                        <td class="block break-words px-3 pb-3 text-left text-emerald-700 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                            Rp {{ number_format($this->totalNominalAkhir, 0, ',', '.') }}
                        </td>
                        <td colspan="6" class="hidden px-1.5 py-2 xl:table-cell"></td>
                    </tr>
                    @if(in_array(auth()->user()->role, ['admin', 'head_coach']))
                        @php
                            $bonus = $this->bonusInfo;
                        @endphp
                        @if($bonus['persen'] > 0)
                            <tr class="grid grid-cols-1 border-t border-gray-200 xl:table-row">
                                <td colspan="4" class="block px-3 pt-3 text-left text-gray-600 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Bonus ({{ $bonus['persen'] }}%)
                                    <span class="block break-words text-[10px] text-gray-400">
                                        Rentang:
                                        @if(strtolower($bonus['rentang_satu']) === 'min')
                                            ≤ Rp {{ number_format((float) $bonus['rentang_dua'], 0, ',', '.') }}
                                        @elseif(strtolower($bonus['rentang_dua']) === 'plus')
                                            ≥ Rp {{ number_format((float) $bonus['rentang_satu'], 0, ',', '.') }}
                                        @else
                                            Rp {{ number_format((float) $bonus['rentang_satu'], 0, ',', '.') }} - Rp {{ number_format((float) $bonus['rentang_dua'], 0, ',', '.') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="block break-words px-3 pb-3 text-left text-blue-700 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    <span class="block">Rp {{ number_format($bonus['total_bonus'], 0, ',', '.') }}</span>
                                    @if(auth()->user()->role === 'admin')
                                        <button
                                            type="button"
                                            wire:click="openBonusPaymentModal"
                                            wire:loading.attr="disabled"
                                            wire:target="openBonusPaymentModal"
                                            class="mt-2 inline-flex items-center justify-center rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <span wire:loading.remove wire:target="openBonusPaymentModal">Bayar Bonus</span>
                                            <span wire:loading wire:target="openBonusPaymentModal">Menyiapkan...</span>
                                        </button>
                                    @endif
                                </td>
                                <td colspan="6" class="hidden px-1.5 py-2 xl:table-cell"></td>
                            </tr>
                        @else
                            <tr class="grid grid-cols-1 border-t border-gray-200 xl:table-row">
                                <td colspan="4" class="block px-3 pt-3 text-left text-gray-500 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Tidak ada rentang bonus yang cocok
                                </td>
                                <td class="block break-words px-3 pb-3 text-left text-gray-400 xl:table-cell xl:px-1.5 xl:py-2 xl:text-right">
                                    Rp 0
                                </td>
                                <td colspan="6" class="hidden px-1.5 py-2 xl:table-cell"></td>
                            </tr>
                        @endif
                    @endif
                </tfoot>
            @endif
        </table>
        </div>
    </div>
    <div class="mb-6">
        {{ $this->memberships->links() }}
    </div>

    @if(in_array(auth()->user()->role, ['admin', 'head_coach'], true))
        <section
            id="riwayat-pembayaran-bonus"
            class="mb-8 scroll-mt-6"
            x-data
            x-on:scroll-to-bonus-payment-history.window="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })"
        >
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h5 class="text-xl font-semibold text-heading">Riwayat Pembayaran Bonus</h5>
                    <p class="mt-1 text-sm text-body">Seluruh pembayaran bonus {{ $staffUser->name }}, terlepas dari filter rekap yang sedang aktif.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-md border border-default bg-neutral-primary-soft shadow-xs">
                    <table class="block w-full table-fixed text-left text-xs text-body lg:table">
                        <colgroup class="hidden lg:table-column-group">
                            <col class="w-[5%]">
                            <col class="w-[12%]">
                            <col class="w-[6%]">
                            <col class="w-[11%]">
                            <col class="w-[10%]">
                            <col class="w-[9%]">
                            <col class="w-[11%]">
                            <col class="w-[9%]">
                            <col class="w-[10%]">
                            <col class="w-[17%]">
                        </colgroup>
                        <thead class="hidden border-b border-default-medium bg-neutral-secondary-medium text-[10px] lg:table-header-group xl:text-xs">
                            <tr>
                                <th class="break-words px-1.5 py-3 font-medium">ID Bayar</th>
                                <th class="break-words px-1.5 py-3 font-medium">Periode</th>
                                <th class="break-words px-1.5 py-3 text-center font-medium">Jumlah Member</th>
                                <th class="break-words px-1.5 py-3 text-right font-medium">Total Nominal Akhir</th>
                                <th class="break-words px-1.5 py-3 text-right font-medium">Bonus Bruto</th>
                                <th class="break-words px-1.5 py-3 text-right font-medium">Potongan</th>
                                <th class="break-words px-1.5 py-3 text-right font-medium">Bersih Diterima</th>
                                <th class="break-words px-1.5 py-3 font-medium">Dibayar Oleh</th>
                                <th class="break-words px-1.5 py-3 font-medium">Tanggal Bayar</th>
                                <th class="break-words px-1.5 py-3 text-center font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="grid gap-3 bg-gray-50 p-3 sm:grid-cols-2 lg:table-row-group lg:bg-transparent lg:p-0">
                            @forelse($this->bonusPaymentHistory as $payment)
                                <tr wire:key="bonus-payment-history-{{ $payment->id }}" class="grid content-start overflow-hidden rounded-md border border-gray-200 bg-white shadow-xs lg:table-row lg:rounded-none lg:border-x-0 lg:border-t-0 lg:bg-neutral-primary-soft lg:shadow-none lg:hover:bg-neutral-secondary-medium">
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">ID Bayar</span>
                                        <span class="break-words font-semibold text-heading">#{{ $payment->id }}</span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Periode</span>
                                        <span class="max-w-[65%] break-words text-right lg:max-w-none lg:text-left">
                                            @if($payment->date_start->equalTo($payment->date_end))
                                                {{ $payment->date_start->translatedFormat('d F Y') }}
                                            @else
                                                {{ $payment->date_start->translatedFormat('d F Y') }}<span class="lg:block"> - {{ $payment->date_end->translatedFormat('d F Y') }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:text-center lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Jumlah Member</span>
                                        <span>{{ $payment->items_count }}</span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:text-right lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Total Nominal Akhir</span>
                                        <span class="break-words">Rp {{ number_format((float) $payment->total_nominal_akhir, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:text-right lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Bonus Bruto</span>
                                        <span>
                                            <span class="block break-words font-medium text-blue-700">Rp {{ number_format((float) $payment->bonus_amount, 0, ',', '.') }}</span>
                                            <span class="text-[10px] text-gray-500">{{ number_format((float) $payment->bonus_percentage, 0, ',', '.') }}%</span>
                                        </span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:text-right lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Potongan</span>
                                        <span class="break-words text-red-600">Rp {{ number_format((float) $payment->potongan, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:text-right lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Bersih Diterima</span>
                                        <span class="break-words font-semibold text-emerald-700">Rp {{ number_format((float) $payment->net_amount, 0, ',', '.') }}</span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Dibayar Oleh</span>
                                        <span class="max-w-[65%] break-words text-right lg:max-w-none lg:text-left">{{ $payment->paidBy?->name ?? '-' }}</span>
                                    </td>
                                    <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 lg:table-cell lg:border-0 lg:px-1.5 lg:py-3 lg:align-top">
                                        <span class="font-medium text-gray-500 lg:hidden">Tanggal Bayar</span>
                                        <span class="break-words text-right lg:text-left">{{ $payment->paid_at?->translatedFormat('d F Y H:i') ?? '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2 lg:table-cell lg:px-1.5 lg:py-3 lg:text-center lg:align-top">
                                        <div class="flex flex-wrap justify-end gap-1.5 lg:flex-col lg:items-stretch">
                                        <button
                                            type="button"
                                            wire:click="openBonusPaymentDetail({{ $payment->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openBonusPaymentDetail({{ $payment->id }})"
                                            class="rounded bg-brand px-2 py-1.5 text-[11px] font-medium text-white transition-colors hover:bg-brand-strong disabled:opacity-50"
                                        >
                                            Detail
                                        </button>
                                        <a
                                            href="{{ route('admin.rekap-bonus.payment.pdf', ['user' => $staffUser, 'paymentId' => $payment->id]) }}"
                                            class="rounded bg-sky-600 px-2 py-1.5 text-center text-[11px] font-medium text-white transition-colors hover:bg-sky-700"
                                        >
                                            Download PDF
                                        </a>
                                        @if(auth()->user()->role === 'admin')
                                            <button
                                                type="button"
                                                wire:click="deleteBonusPayment({{ $payment->id }})"
                                                wire:confirm="Pembayaran bonus #{{ $payment->id }} akan dihapus permanen beserta seluruh detailnya. Lanjutkan?"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteBonusPayment({{ $payment->id }})"
                                                class="rounded bg-red-600 px-2 py-1.5 text-[11px] font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                                            >
                                                <span wire:loading.remove wire:target="deleteBonusPayment({{ $payment->id }})">Hapus</span>
                                                <span wire:loading wire:target="deleteBonusPayment({{ $payment->id }})">Menghapus...</span>
                                            </button>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">Belum ada riwayat pembayaran bonus.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <div class="mt-4">
                {{ $this->bonusPaymentHistory->links(data: ['scrollTo' => '#riwayat-pembayaran-bonus']) }}
            </div>
        </section>
    @endif

    @if($showBonusPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 py-6 backdrop-blur-sm" wire:click.self="closeBonusPaymentModal">
            <div class="mx-4 flex max-h-[calc(100vh-3rem)] w-full max-w-6xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-default-medium px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="text-lg font-semibold text-heading">Pembayaran Bonus {{ $staffUser->name }}</h3>
                        <p class="mt-1 text-xs text-body">
                            Periode {{ \Carbon\Carbon::parse($bonusPaymentDateStart)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($bonusPaymentDateEnd)->translatedFormat('d F Y') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeBonusPaymentModal" class="text-body hover:text-heading" aria-label="Tutup pembayaran bonus">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">
                    <div>
                        <label for="bonus-payment-search" class="sr-only">Cari nama member dalam modal</label>
                        <input
                            id="bonus-payment-search"
                            type="search"
                            wire:model.live.debounce.250ms="bonusPaymentSearch"
                            class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading shadow-xs placeholder:text-body focus:border-brand focus:ring-brand"
                            placeholder="Cari nama member..."
                        >
                        <p class="mt-1.5 text-xs text-gray-500">Search ini hanya menyaring baris yang terlihat. Total dan data pembayaran tetap memakai seluruh snapshot dari filter halaman.</p>
                    </div>

                    <div class="overflow-hidden rounded-md border border-default-medium">
                        <table class="block w-full table-fixed text-left text-xs text-body md:table md:text-sm">
                            <colgroup class="hidden md:table-column-group">
                                <col class="w-[23%]">
                                <col class="w-[25%]">
                                <col class="w-[17%]">
                                <col class="w-[18%]">
                                <col class="w-[17%]">
                            </colgroup>
                            <thead class="hidden border-b border-default-medium bg-neutral-secondary-medium md:table-header-group">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Nama Member</th>
                                    <th class="px-4 py-3 font-medium">Paket Membership</th>
                                    <th class="px-4 py-3 text-right font-medium">Nominal</th>
                                    <th class="px-4 py-3 text-right font-medium">Nominal Akhir</th>
                                    <th class="px-4 py-3 font-medium">Tanggal Bayar</th>
                                </tr>
                            </thead>
                            <tbody class="grid gap-3 bg-gray-50 p-3 sm:grid-cols-2 md:table-row-group md:bg-transparent md:p-0">
                                @forelse($this->filteredBonusPaymentRows as $row)
                                    <tr wire:key="bonus-payment-row-{{ $row['membership_id'] }}" class="grid content-start overflow-hidden rounded-md border border-gray-200 bg-white shadow-xs md:table-row md:rounded-none md:border-0 md:border-b md:border-default-medium md:shadow-none">
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Nama Member</span>
                                            <span class="max-w-[65%] break-words text-right font-medium text-heading md:max-w-none md:text-left">{{ $row['member_name'] }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Paket Membership</span>
                                            <span class="max-w-[65%] break-words text-right md:max-w-none md:text-left">{{ $row['package_name'] !== '' ? $row['package_name'] : '-' }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:text-right md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Nominal</span>
                                            <span class="break-words">Rp {{ number_format($row['nominal'], 0, ',', '.') }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:text-right md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Nominal Akhir</span>
                                            <span class="break-words font-semibold text-emerald-700">Rp {{ number_format($row['nominal_akhir'], 0, ',', '.') }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 px-3 py-2 md:table-cell md:px-3 md:py-3 md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Tanggal Bayar</span>
                                            <span class="break-words text-right md:text-left">{{ $row['payment_date'] ? \Carbon\Carbon::parse($row['payment_date'])->translatedFormat('d F Y') : '-' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Member tidak ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="hidden border-t-2 border-gray-300 bg-gray-100 font-semibold text-heading md:table-footer-group">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right">Total Keseluruhan:</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-emerald-700">Rp {{ number_format($bonusPaymentTotalNominalAkhir, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                                <tr class="border-t border-gray-200">
                                    <td colspan="3" class="px-4 py-3 text-right">
                                        Bonus ({{ number_format($bonusPaymentPercentage, 0, ',', '.') }}%)
                                        <span class="block text-[10px] font-normal text-gray-500">
                                            Rentang:
                                            @if(strtolower((string) $bonusPaymentRangeStart) === 'min')
                                                ≤ Rp {{ number_format((float) $bonusPaymentRangeEnd, 0, ',', '.') }}
                                            @elseif(strtolower((string) $bonusPaymentRangeEnd) === 'plus')
                                                ≥ Rp {{ number_format((float) $bonusPaymentRangeStart, 0, ',', '.') }}
                                            @else
                                                Rp {{ number_format((float) $bonusPaymentRangeStart, 0, ',', '.') }} - Rp {{ number_format((float) $bonusPaymentRangeEnd, 0, ',', '.') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-blue-700">Rp {{ number_format($bonusPaymentAmount, 0, ',', '.') }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="grid gap-3 md:hidden">
                        <div class="rounded-md border border-gray-200 bg-gray-100 p-3">
                            <p class="text-xs text-gray-500">Total Keseluruhan</p>
                            <p class="mt-1 font-semibold text-emerald-700">Rp {{ number_format($bonusPaymentTotalNominalAkhir, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-md border border-blue-200 bg-blue-50 p-3">
                            <p class="text-xs text-blue-600">Bonus ({{ number_format($bonusPaymentPercentage, 0, ',', '.') }}%)</p>
                            <p class="mt-1 font-semibold text-blue-700">Rp {{ number_format($bonusPaymentAmount, 0, ',', '.') }}</p>
                            <p class="mt-1 text-[10px] text-gray-500">
                                Rentang:
                                @if(strtolower((string) $bonusPaymentRangeStart) === 'min')
                                    ≤ Rp {{ number_format((float) $bonusPaymentRangeEnd, 0, ',', '.') }}
                                @elseif(strtolower((string) $bonusPaymentRangeEnd) === 'plus')
                                    ≥ Rp {{ number_format((float) $bonusPaymentRangeStart, 0, ',', '.') }}
                                @else
                                    Rp {{ number_format((float) $bonusPaymentRangeStart, 0, ',', '.') }} - Rp {{ number_format((float) $bonusPaymentRangeEnd, 0, ',', '.') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="nominal-potongan" class="mb-1.5 block text-sm font-medium text-heading">Nominal Potongan</label>
                            <div class="flex rounded-md shadow-xs">
                                <span class="inline-flex items-center rounded-s-md border border-e-0 border-default-medium bg-neutral-secondary-medium px-3 text-sm text-body">Rp</span>
                                <input
                                    id="nominal-potongan"
                                    type="number"
                                    min="0"
                                    step="1"
                                    wire:model.live.debounce.250ms="nominalPotongan"
                                    class="block min-w-0 flex-1 rounded-e-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading focus:border-brand focus:ring-brand"
                                    placeholder="0"
                                >
                            </div>
                            @error('nominalPotongan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="keterangan-potongan" class="mb-1.5 block text-sm font-medium text-heading">Keterangan Potongan</label>
                            <textarea
                                id="keterangan-potongan"
                                rows="3"
                                wire:model="keteranganPotongan"
                                class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand"
                                placeholder="Wajib diisi jika ada potongan"
                            ></textarea>
                            @error('keteranganPotongan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-base font-bold text-emerald-800">BERSIH DITERIMA: Rp {{ number_format($this->bonusPaymentNetAmount, 0, ',', '.') }}</p>
                        <p class="mt-1 text-sm italic text-emerald-700">Terbilang: {{ $this->terbilang((int) round($this->bonusPaymentNetAmount)) }} rupiah</p>
                    </div>
                </div>

                <div class="flex flex-col-reverse justify-end gap-3 border-t border-default-medium px-5 py-4 sm:flex-row sm:px-6">
                    <button type="button" wire:click="closeBonusPaymentModal" wire:loading.attr="disabled" wire:target="confirmBonusPayment" class="rounded-md border border-default-medium bg-neutral-secondary-medium px-4 py-2.5 text-sm font-medium text-heading hover:bg-neutral-secondary-strong disabled:opacity-50">
                        Batal
                    </button>
                    <button type="button" wire:click="confirmBonusPayment" wire:loading.attr="disabled" wire:target="confirmBonusPayment" class="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirmBonusPayment">Konfirmasi Pembayaran</span>
                        <span wire:loading wire:target="confirmBonusPayment">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showBonusPaymentDetailModal && $selectedBonusPaymentId)
        @php
            $detailPayment = $this->selectedBonusPayment;
            $detailItems = $this->bonusPaymentDetailItems;
            $detailNetAmount = auth()->user()->role === 'admin'
                ? $this->bonusPaymentDetailNetAmount
                : (float) $detailPayment->net_amount;
            $detailDeductionAmount = auth()->user()->role === 'admin'
                ? $this->bonusPaymentDetailDeductionAmount
                : (float) $detailPayment->potongan;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 py-6 backdrop-blur-sm" wire:click.self="closeBonusPaymentDetail">
            <div class="mx-4 flex max-h-[calc(100vh-3rem)] w-full max-w-6xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-default-medium px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="text-lg font-semibold text-heading">Detail Pembayaran Bonus #{{ $detailPayment->id }}</h3>
                        <p class="mt-1 text-sm text-body">{{ $staffUser->name }}</p>
                    </div>
                    <button type="button" wire:click="closeBonusPaymentDetail" class="text-body hover:text-heading" aria-label="Tutup detail pembayaran bonus">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">
                    @if($bonusPaymentDetailSuccess)
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="alert">
                            {{ $bonusPaymentDetailSuccess }}
                        </div>
                    @endif

                    <dl class="grid gap-3 rounded-md border border-default-medium bg-neutral-secondary-medium p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs text-gray-500">Periode</dt>
                            <dd class="mt-1 font-medium text-heading">
                                @if($detailPayment->date_start->equalTo($detailPayment->date_end))
                                    {{ $detailPayment->date_start->translatedFormat('d F Y') }}
                                @else
                                    {{ $detailPayment->date_start->translatedFormat('d F Y') }} - {{ $detailPayment->date_end->translatedFormat('d F Y') }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Dibayar Oleh</dt>
                            <dd class="mt-1 font-medium text-heading">{{ $detailPayment->paidBy?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Tanggal Bayar</dt>
                            <dd class="mt-1 font-medium text-heading">{{ $detailPayment->paid_at?->translatedFormat('d F Y H:i') ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Terakhir Diperbarui</dt>
                            <dd class="mt-1 font-medium text-heading">{{ $detailPayment->updated_at?->translatedFormat('d F Y H:i') ?? '-' }}</dd>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <dt class="text-xs text-gray-500">Search Halaman Saat Pembayaran</dt>
                            <dd class="mt-1 font-medium text-heading">{{ $detailPayment->search_filter ?: 'Semua member' }}</dd>
                        </div>
                    </dl>

                    <div>
                        <label for="bonus-payment-detail-search" class="sr-only">Cari member atau paket pada detail pembayaran</label>
                        <input
                            id="bonus-payment-detail-search"
                            type="search"
                            wire:model.live.debounce.250ms="bonusPaymentDetailSearch"
                            class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading shadow-xs placeholder:text-body focus:border-brand focus:ring-brand"
                            placeholder="Cari nama member atau paket membership..."
                        >
                        <p class="mt-1.5 text-xs text-gray-500">Search hanya menyaring item yang terlihat. Ringkasan tetap memakai seluruh snapshot pembayaran.</p>
                    </div>

                    <div class="overflow-hidden rounded-md border border-default-medium">
                        <table class="block w-full table-fixed text-left text-xs text-body md:table md:text-sm">
                            <colgroup class="hidden md:table-column-group">
                                <col class="w-[23%]">
                                <col class="w-[25%]">
                                <col class="w-[17%]">
                                <col class="w-[18%]">
                                <col class="w-[17%]">
                            </colgroup>
                            <thead class="hidden border-b border-default-medium bg-neutral-secondary-medium md:table-header-group">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Nama Member</th>
                                    <th class="px-4 py-3 font-medium">Paket Membership</th>
                                    <th class="px-4 py-3 text-right font-medium">Nominal</th>
                                    <th class="px-4 py-3 text-right font-medium">Nominal Akhir</th>
                                    <th class="px-4 py-3 font-medium">Tanggal Bayar</th>
                                </tr>
                            </thead>
                            <tbody class="grid gap-3 bg-gray-50 p-3 sm:grid-cols-2 md:table-row-group md:bg-transparent md:p-0">
                                @forelse($detailItems as $item)
                                    <tr wire:key="bonus-payment-detail-item-{{ $item->id }}" class="grid content-start overflow-hidden rounded-md border border-gray-200 bg-white shadow-xs md:table-row md:rounded-none md:border-0 md:border-b md:border-default-medium md:shadow-none">
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Nama Member</span>
                                            <span class="max-w-[65%] break-words text-right font-medium text-heading md:max-w-none md:text-left">{{ $item->member_name }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Paket Membership</span>
                                            <span class="max-w-[65%] break-words text-right md:max-w-none md:text-left">{{ $item->package_name ?: '-' }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:text-right md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Nominal</span>
                                            <span class="break-words">Rp {{ number_format((float) $item->nominal, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 border-b border-gray-100 px-3 py-2 md:table-cell md:border-0 md:px-3 md:py-3 md:text-right md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Nominal Akhir</span>
                                            <span class="break-words font-semibold text-emerald-700">Rp {{ number_format((float) $item->nominal_akhir, 0, ',', '.') }}</span>
                                        </td>
                                        <td class="flex min-w-0 justify-between gap-3 px-3 py-2 md:table-cell md:px-3 md:py-3 md:align-top">
                                            <span class="font-medium text-gray-500 md:hidden">Tanggal Bayar</span>
                                            <span class="break-words text-right md:text-left">{{ $item->payment_date?->translatedFormat('d F Y') ?? '-' }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Item pembayaran tidak ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $detailItems->links(data: ['scrollTo' => false]) }}
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <p class="text-xs text-gray-500">Total Keseluruhan</p>
                            <p class="mt-1 font-semibold text-heading">Rp {{ number_format((float) $detailPayment->total_nominal_akhir, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-md border border-blue-200 bg-blue-50 p-4">
                            <p class="text-xs text-blue-600">Bonus Bruto ({{ number_format((float) $detailPayment->bonus_percentage, 0, ',', '.') }}%)</p>
                            <p class="mt-1 font-semibold text-blue-800">Rp {{ number_format((float) $detailPayment->bonus_amount, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-md border border-red-200 bg-red-50 p-4">
                            <p class="text-xs text-red-600">Potongan</p>
                            <p class="mt-1 font-semibold text-red-800">Rp {{ number_format($detailDeductionAmount, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs text-emerald-600">Bersih Diterima</p>
                            <p class="mt-1 font-semibold text-emerald-800">Rp {{ number_format($detailNetAmount, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="rounded-md border border-default-medium p-4 text-sm text-body">
                        <p>
                            <span class="font-medium text-heading">Rentang Bonus:</span>
                            @if(strtolower((string) $detailPayment->range_start) === 'min')
                                ≤ Rp {{ number_format((float) $detailPayment->range_end, 0, ',', '.') }}
                            @elseif(strtolower((string) $detailPayment->range_end) === 'plus')
                                ≥ Rp {{ number_format((float) $detailPayment->range_start, 0, ',', '.') }}
                            @else
                                Rp {{ number_format((float) $detailPayment->range_start, 0, ',', '.') }} - Rp {{ number_format((float) $detailPayment->range_end, 0, ',', '.') }}
                            @endif
                        </p>
                        <p class="mt-2 italic">Terbilang: {{ $this->terbilang((int) round($detailNetAmount)) }} rupiah</p>
                    </div>

                    @if(auth()->user()->role === 'admin')
                        <div class="grid gap-4 rounded-md border border-default-medium p-4 md:grid-cols-2">
                            <div>
                                <label for="detail-nominal-potongan" class="mb-1.5 block text-sm font-medium text-heading">Nominal Potongan</label>
                                <div class="flex rounded-md shadow-xs">
                                    <span class="inline-flex items-center rounded-s-md border border-e-0 border-default-medium bg-neutral-secondary-medium px-3 text-sm text-body">Rp</span>
                                    <input
                                        id="detail-nominal-potongan"
                                        type="number"
                                        min="0"
                                        step="1"
                                        wire:model.live.debounce.250ms="detailNominalPotongan"
                                        class="block min-w-0 flex-1 rounded-e-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading focus:border-brand focus:ring-brand"
                                    >
                                </div>
                                @error('detailNominalPotongan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="detail-keterangan-potongan" class="mb-1.5 block text-sm font-medium text-heading">Keterangan Potongan</label>
                                <textarea
                                    id="detail-keterangan-potongan"
                                    rows="3"
                                    wire:model="detailKeteranganPotongan"
                                    class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand"
                                    placeholder="Wajib diisi jika ada potongan"
                                ></textarea>
                                @error('detailKeteranganPotongan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @else
                        <div class="rounded-md border border-default-medium p-4 text-sm text-body">
                            <p class="text-xs text-gray-500">Keterangan Potongan</p>
                            <p class="mt-1 whitespace-pre-line text-heading">{{ $detailPayment->keterangan_potongan ?: '-' }}</p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col-reverse justify-end gap-3 border-t border-default-medium px-5 py-4 sm:flex-row sm:px-6">
                    <button type="button" wire:click="closeBonusPaymentDetail" wire:loading.attr="disabled" wire:target="updateBonusPaymentDeduction" class="rounded-md border border-default-medium bg-neutral-secondary-medium px-4 py-2.5 text-sm font-medium text-heading hover:bg-neutral-secondary-strong disabled:opacity-50">
                        Tutup
                    </button>
                    @if(auth()->user()->role === 'admin')
                        <button type="button" wire:click="updateBonusPaymentDeduction" wire:loading.attr="disabled" wire:target="updateBonusPaymentDeduction" class="rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="updateBonusPaymentDeduction">Simpan Potongan</span>
                            <span wire:loading wire:target="updateBonusPaymentDeduction">Menyimpan...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
