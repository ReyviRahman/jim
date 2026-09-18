@props(['hold'])

<span>HOLD PT {{ $hold->months }} bulan
    @if($hold->monthly_price !== null)
        × Rp {{ number_format($hold->monthly_price, 0, ',', '.') }}.
    @endif
    Total biaya hold: Rp {{ number_format($hold->total_amount, 0, ',', '.') }}.
    Tanggal akhir: {{ $hold->previous_end_date->format('d/m/Y') }} → {{ $hold->new_end_date->format('d/m/Y') }}.</span>
