<table>
    {{-- BARIS 1: JUDUL BESAR --}}
    <tr>
        <td colspan="8" style="text-align: center; border: 2px solid #000000; font-size: 14px;">
            PERHITUNGAN BONUS TARGET {{ $titleDate }}
        </td>
    </tr>

    {{-- BARIS 2: HEADER UTAMA --}}
    <tr>
        <td style="text-align: center; background-color: #fce4d6; border: 2px solid #000000;">TGL MULAI</td>
        <td style="text-align: center; background-color: #fce4d6; border: 2px solid #000000;">TGL SELESAI</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">PAKET MEMBERSHIP</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">NAMA SESUAI KTP</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">NOMINAL</td>
        <td rowspan="3" style="text-align: center; vertical-align: middle; background-color: #fce4d6; border: 2px solid #000000;">NOMINAL AKHIR</td>
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

    {{-- INISIALISASI VARIABEL TOTAL --}}
    @php
        $totalNominalBagiDua = 0;
    @endphp

    {{-- BARIS DATA --}}
    @foreach($memberships as $membership)
        @php
            $packageName = $membership->gymPackage->name ?? $membership->ptPackage->name ?? $membership->type;
            
            // Logika Nominal
            $nominal = $membership->total_paid ?? 0;
            $isBagiDua = !empty($membership->follow_up_id_two);
            $nominalAkhir = $isBagiDua ? ($nominal / 2) : $nominal;

            // Tambahkan ke Total Keseluruhan
            $totalNominalBagiDua += $nominalAkhir;

            // Logika Warna Background (Merah jika dibagi 2, Putih jika full)
            $bgColor = $isBagiDua ? '#ff0000' : '#ffffff';

            // Logika Format Tanggal (Contoh: Jumat, Januari 16, 2026)
            $tglMulai = $membership->start_date ? \Carbon\Carbon::parse($membership->start_date)->locale('id')->translatedFormat('l, F d, Y') : 'BELUM AKTIF';
            $tglSelesai = $membership->membership_end_date ? \Carbon\Carbon::parse($membership->membership_end_date)->locale('id')->translatedFormat('l, F d, Y') : 'BELUM AKTIF';
        @endphp
        
        <tr>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000;">
                {{ $tglMulai }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000;">
                {{ $tglSelesai }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ strtoupper($membership->notes) }}
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
            
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ strtoupper($membership->followUp->name ?? '-') }}
            </td>
            <td style="background-color: {{ $bgColor }}; border: 1px solid #000000; text-align: center;">
                {{ strtoupper($membership->followUpTwo->name ?? '-') }}
            </td>
        </tr>
    @endforeach

    {{-- TAMBAHAN BARU: BARIS TOTAL --}}
    @if($memberships->count() > 0)
        <tr>
            {{-- Menggabungkan 5 kolom pertama untuk teks "TOTAL" --}}
            <td colspan="5" style="text-align: right; font-weight: bold; background-color: #fce4d6; border: 2px solid #000000;">
                TOTAL KESELURUHAN NOMINAL AKHIR:
            </td>
            {{-- Menampilkan angka total tepat di bawah kolom "NOMINAL AKHIR" --}}
            <td style="text-align: right; font-weight: bold; background-color: #fce4d6; border: 2px solid #000000;">
                Rp{{ number_format($totalNominalBagiDua, 0, ',', '.') }}
            </td>
            {{-- Sisanya kosong --}}
            <td colspan="2" style="background-color: #fce4d6; border: 2px solid #000000;"></td>
        </tr>
    @endif
</table>