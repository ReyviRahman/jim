<?php

namespace App\Livewire\Concerns;

use App\Actions\BeverageStockImpact;
use App\Actions\DeleteBeverageSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;

trait DeletesBeverageSales
{
    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $selectedSaleId = null;

    #[Locked]
    public array $saleDeletePreview = [];

    public int $saleImpactPage = 1;

    public string $saleDeleteNotice = '';

    public function confirmDelete(int $id): void
    {
        $preview = app(DeleteBeverageSale::class)->preview($id);
        if ($preview === null) {
            $this->closeDeleteModal();
            session()->flash('error', 'Transaksi sudah tidak tersedia.');

            return;
        }
        $this->selectedSaleId = $id;
        $this->saleDeletePreview = $preview;
        $this->saleImpactPage = 1;
        $this->saleDeleteNotice = '';
        $this->showDeleteModal = true;
    }

    public function deleteSale(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        if (! $this->selectedSaleId || ! $this->saleDeletePreview) {
            return;
        }
        $result = app(DeleteBeverageSale::class)->execute($this->selectedSaleId, $this->saleDeletePreview['fingerprint']);
        if ($result !== null) {
            $this->saleDeletePreview = $result;
            $this->saleImpactPage = 1;
            $this->saleDeleteNotice = 'Dampak terbaru ditampilkan. Periksa kembali sebelum mengonfirmasi penghapusan.';

            return;
        }
        $message = 'Data transaksi berhasil dihapus.';
        if ($this->saleDeletePreview['stock_affecting']) {
            $message .= ' '.$this->saleDeletePreview['pcs'].' pcs dikembalikan. Koreksi stok '.$this->saleDeletePreview['date'].' sampai '.$this->saleDeletePreview['through'].'.';
        }
        $this->closeDeleteModal();
        session()->flash('success', $message);
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->selectedSaleId = null;
        $this->saleDeletePreview = [];
        $this->saleDeleteNotice = '';
        $this->saleImpactPage = 1;
    }

    public function changeSaleImpactPage(int $direction): void
    {
        $lastPage = max(1, (int) ceil(($this->saleDeletePreview['affected_count'] ?? 0) / 10));
        $this->saleImpactPage = min($lastPage, max(1, $this->saleImpactPage + ($direction > 0 ? 1 : -1)));
    }

    public function getSaleSnapshotChangesProperty(): ?LengthAwarePaginator
    {
        if (empty($this->saleDeletePreview['products'])) {
            return null;
        }
        abort_unless(auth()->user()?->role === 'admin', 403);

        return app(BeverageStockImpact::class)->snapshots(array_column($this->saleDeletePreview['products'], 'id'), $this->saleDeletePreview['date'])
            ->orderBy('tanggal')->orderBy('beverage_id')->orderBy('tipe')
            ->paginate(10, ['*'], 'saleImpactPage', $this->saleImpactPage);
    }
}
