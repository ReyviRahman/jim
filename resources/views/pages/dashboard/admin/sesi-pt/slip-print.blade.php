<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Sesi PT - {{ $user->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
        @page {
            size: A4;
            margin: 1.5cm;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-3xl shadow-lg rounded-lg overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between p-6 border-b-2 border-gray-300">
            <div class="flex items-center gap-4 w-full">
                <img src="{{ asset('icon.png') }}" alt="Icon" class="h-12 w-auto">
                <h2 class="text-2xl font-bold text-gray-900 text-center flex-1">SLIP SESI PT</h2>
            </div>
        </div>

        {{-- Body --}}
        <div class="p-6 space-y-4">
            <div class="text-sm text-gray-700">
                @if($dateStart && $dateEnd)
                    <p class="font-medium">
                        @if($dateStart === $dateEnd)
                            {{ \Carbon\Carbon::parse($dateStart)->translatedFormat('d F Y') }}
                        @else
                            {{ \Carbon\Carbon::parse($dateStart)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($dateEnd)->translatedFormat('d F Y') }}
                        @endif
                    </p>
                @endif
                <p class="mt-1"><span class="font-semibold text-gray-900">NAMA:</span> {{ $user->name }}</p>
            </div>

            <table class="w-full text-sm text-left text-gray-700 border border-gray-300">
                <thead class="text-sm text-gray-700 bg-gray-100 border-b border-gray-300">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">JENIS</th>
                        <th scope="col" class="px-4 py-3 font-medium text-center">JUMLAH</th>
                        <th scope="col" class="px-4 py-3 font-medium text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="bg-white border-b border-gray-200">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['jenis'] }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">{{ $row['jumlah'] }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-gray-500">Belum ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-100 font-semibold text-gray-900 border-t-2 border-gray-300">
                    <tr>
                        <td class="px-4 py-3">TOTAL</td>
                        <td class="px-4 py-3 text-center">{{ $grandTotalJumlah }}</td>
                        <td class="px-4 py-3 text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>

            <div class="mt-4 space-y-1 text-sm">
                <p class="font-bold text-gray-900 text-base">BERSIH DITERIMA: Rp {{ number_format($grandTotal, 0, ',', '.') }}</p>
                @php
                    $fn = null;
                    $fn = function (int $number) use (&$fn): string {
                        $angka = [
                            0 => 'nol', 1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat',
                            5 => 'lima', 6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan',
                            10 => 'sepuluh', 11 => 'sebelas', 12 => 'dua belas', 13 => 'tiga belas',
                            14 => 'empat belas', 15 => 'lima belas', 16 => 'enam belas',
                            17 => 'tujuh belas', 18 => 'delapan belas', 19 => 'sembilan belas',
                            20 => 'dua puluh', 30 => 'tiga puluh', 40 => 'empat puluh',
                            50 => 'lima puluh', 60 => 'enam puluh', 70 => 'tujuh puluh',
                            80 => 'delapan puluh', 90 => 'sembilan puluh',
                        ];

                        if ($number < 0) {
                            return 'minus ' . $fn(-$number);
                        }

                        if ($number < 21) {
                            return $angka[$number];
                        }

                        if ($number < 100) {
                            $puluh = floor($number / 10) * 10;
                            $sisa = $number % 10;
                            return $angka[$puluh] . ($sisa > 0 ? ' ' . $angka[$sisa] : '');
                        }

                        if ($number < 1000) {
                            $ratus = floor($number / 100);
                            $sisa = $number % 100;
                            $prefix = $ratus === 1 ? 'seratus' : $angka[$ratus] . ' ratus';
                            return $prefix . ($sisa > 0 ? ' ' . $fn($sisa) : '');
                        }

                        if ($number < 1000000) {
                            $ribu = floor($number / 1000);
                            $sisa = $number % 1000;
                            $prefix = $ribu === 1 ? 'seribu' : $fn($ribu) . ' ribu';
                            return $prefix . ($sisa > 0 ? ' ' . $fn($sisa) : '');
                        }

                        if ($number < 1000000000) {
                            $juta = floor($number / 1000000);
                            $sisa = $number % 1000000;
                            return $fn($juta) . ' juta' . ($sisa > 0 ? ' ' . $fn($sisa) : '');
                        }

                        $miliar = floor($number / 1000000000);
                        $sisa = $number % 1000000000;
                        return $fn($miliar) . ' miliar' . ($sisa > 0 ? ' ' . $fn($sisa) : '');
                    };
                    $terbilang = $fn($grandTotal);
                @endphp
                <p class="text-gray-600 italic">Terbilang: {{ $terbilang }} rupiah</p>
            </div>
        </div>

        {{-- Print Button --}}
        <div class="p-6 border-t border-gray-200 flex gap-3 justify-end no-print">
            <button type="button" onclick="window.print()" class="px-4 py-2 text-white bg-emerald-600 hover:bg-emerald-700 rounded-md font-medium text-sm">
                Print / Save as PDF
            </button>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
