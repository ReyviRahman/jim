<table>
    @php
        $canSeeAdminColumns = in_array(auth()->user()->role, ['admin', 'head_coach']);
    @endphp

    {{-- BARIS 1: JUDUL BESAR --}}
    <tr>
        <td colspan="{{ $canSeeAdminColumns ? 10 : 9 }}" style="text-align: center; border: 2px solid #000000; font-size: 14px;">
            PERHITUNGAN BONUS TARGET {{ $titleDate }}
        </td>
    </tr>

    {{-- BARIS 2: HEADER UTAMA --}}
    <tr>
        <td style="text-align: center; background-color: #fce4d6; border: 2px solid #000000;">TGL MULAI</td>
        <td style="text-align: center; background-color: #fce4d6; border: 2px solid #000000;">TGL SELESAI</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">TGL BAYAR</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">PAKET MEMBERSHIP</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">NAMA SESUAI KTP</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">NOMINAL</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">NOMINAL AKHIR</td>
        @if($canSeeAdminColumns)
            <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">KATEGORI HARGA</td>
        @endif
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">FOLLOW UP 1</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">FOLLOW UP 2</td>
    </tr>

    {{-- BARIS 3: HEADER MEMBERSHIP --}}
    <tr>
        <td colspan="2" style="text-align: center; background-color: #fce4d6; border: 2px solid #000000;">MEMBERSHIP</td>
    </tr>

    {{-- BARIS 4: HEADER SALES ADMIN --}}
    <tr>
        <td style="text-align: center; background-color: #fce4d6; border: 2px solid #000000;">SALES ADMIN</td>
        <td style="text-align: center; background-color: #ffffff; border: 2px solid #000000;">{{ strtoupper($staffUser->name) }}</td>
    </tr>

    {{-- BARIS DATA --}}
    @foreach($memberships as $membership)
        @php
            $packageName = trim(($membership->transaction_type ?? '') . ' ' . ($membership->package_name ?? ''));
            $nominal = $membership->total_paid ?? 0;
            $nominalAkhir = $membership->calculateNominalAkhir();

            $priceLabelData = $membership->getPriceLabel();
            $kategoriHarga = $priceLabelData['label'] ?? '-';

            $isDibagiDua = $nominalAkhir < $nominal;
            $bgColor = $isDibagiDua ? '#FF0000' : '#ffffff';

            $tglMulai = $membership->start_date ? \Carbon\Carbon::parse($membership->start_date)->locale('id')->translatedFormat('l, F d, Y') : 'BELUM AKTIF';
            $endDate = $membership->type === 'pt' ? $membership->pt_end_date : $membership->membership_end_date;
            $tglSelesai = $endDate ? \Carbon\Carbon::parse($endDate)->locale('id')->translatedFormat('l, F d, Y') : 'BELUM AKTIF';
            $tglBayar = $membership->transactions->sortByDesc('payment_date')->first()?->payment_date?->locale('id')->translatedFormat('l, F d, Y') ?? '-';
        @endphp
        
        <tr>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000;">
                {{ $tglMulai }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000;">
                {{ $tglSelesai }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ $tglBayar }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ strtoupper($packageName) }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000;">
                {{ strtoupper($membership->user->name ?? '-') }}
            </td>
            
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: right;">
                Rp{{ number_format($nominal, 0, ',', '.') }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: right;">
                Rp{{ number_format($nominalAkhir, 0, ',', '.') }}
            </td>
            @if($canSeeAdminColumns)
                <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                    {{ strtoupper($kategoriHarga) }}
                </td>
            @endif
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ strtoupper($membership->followUp->name ?? '-') }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ strtoupper($membership->followUpTwo->name ?? '-') }}
            </td>
        </tr>
    @endforeach

    {{-- BARIS TOTAL --}}
    @if($memberships->count() > 0)
        <tr>
            <td colspan="6" style="text-align: right; font-weight: bold; background-color: #fce4d6; border: 2px solid #000000;">
                TOTAL KESELURUHAN NOMINAL AKHIR:
            </td>
            <td style="text-align: right; font-weight: bold; background-color: #fce4d6; border: 2px solid #000000;">
                Rp{{ number_format($totalNominalAkhir, 0, ',', '.') }}
            </td>
            <td colspan="{{ $canSeeAdminColumns ? 3 : 2 }}" style="background-color: #fce4d6; border: 2px solid #000000;"></td>
        </tr>

        @if($canSeeAdminColumns)
            @if($bonusInfo['persen'] > 0)
                <tr>
                    <td colspan="6" style="text-align: right; font-weight: bold; background-color: #d9edf7; border: 2px solid #000000;">
                        BONUS ({{ $bonusInfo['persen'] }}%)
                        <span style="font-size: 10px; color: #555; display: block;">
                            Rentang:
                            @if(strtolower($bonusInfo['rentang_satu']) === 'min')
                                ≤ Rp {{ number_format((float) $bonusInfo['rentang_dua'], 0, ',', '.') }}
                            @elseif(strtolower($bonusInfo['rentang_dua']) === 'plus')
                                ≥ Rp {{ number_format((float) $bonusInfo['rentang_satu'], 0, ',', '.') }}
                            @else
                                Rp {{ number_format((float) $bonusInfo['rentang_satu'], 0, ',', '.') }} - Rp {{ number_format((float) $bonusInfo['rentang_dua'], 0, ',', '.') }}
                            @endif
                        </span>
                    </td>
                    <td style="text-align: right; font-weight: bold; background-color: #d9edf7; border: 2px solid #000000;">
                        Rp {{ number_format($bonusInfo['total_bonus'], 0, ',', '.') }}
                    </td>
                    <td colspan="3" style="background-color: #d9edf7; border: 2px solid #000000;"></td>
                </tr>
            @else
                <tr>
                    <td colspan="6" style="text-align: right; font-weight: bold; background-color: #d9edf7; border: 2px solid #000000;">
                        TIDAK ADA RENTANG BONUS YANG COCOK
                    </td>
                    <td style="text-align: right; font-weight: bold; background-color: #d9edf7; border: 2px solid #000000;">
                        Rp 0
                    </td>
                    <td colspan="3" style="background-color: #d9edf7; border: 2px solid #000000;"></td>
                </tr>
            @endif
        @endif
    @endif
</table>