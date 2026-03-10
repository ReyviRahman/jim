<table>
    {{-- BARIS 1 --}}
    <thead>
        <tr>
            <th colspan="4">GRAND TOTAL (SHIFT {{ strtoupper($shift === 'all' ? 'PAGI & SIANG' : $shift) }})</th>
        </tr>
    </thead>
    <tbody>
        {{-- BARIS 2 --}}
        <tr>
            <td>Total Transfer</td>
            <td>Rp {{ $summary['transfer'] }}</td>
            <td>Total Member Gym</td>
            <td>Rp {{ $summary['uang_member'] }}</td>
        </tr>
        {{-- BARIS 3 --}}
        <tr>
            <td>Total Debit (EDC)</td>
            <td>Rp {{ $summary['debit'] }}</td>
            <td>Total Visit Harian</td>
            <td>Rp {{ $summary['uang_visit'] }}</td>
        </tr>
        {{-- BARIS 4 --}}
        <tr>
            <td>Total QRIS</td>
            <td>Rp {{ $summary['qris'] }}</td>
            <td>Total Personal Trainer</td>
            <td>Rp {{ $summary['uang_pt'] }}</td>
        </tr>
        {{-- BARIS 5 --}}
        <tr>
            <td>Cash On Hand</td>
            <td>Rp {{ $summary['cash'] }}</td>
            <td>Balance (Pendapatan)</td>
            <td>Rp {{ $summary['uang_total'] }}</td>
        </tr>
        {{-- BARIS 6 --}}
        <tr>
            <td>Balance (Total Masuk)</td>
            <td>Rp {{ $summary['balance_merah'] }}</td>
            <td colspan="2" rowspan="4" style="vertical-align: top;">
                <b>Catatan Pengeluaran:</b>
                @forelse($summary['rincian_pengeluaran'] as $exp)
                    <br>- {{ $exp->description }} (Rp {{ number_format($exp->amount, 0, ',', '.') }}) 
                    <br>  Admin: {{ $exp->admin->name ?? '-' }}
                @empty
                    <br>- Tidak ada catatan pengeluaran.
                @endforelse
            </td>
        </tr>
        {{-- BARIS 7 --}}
        <tr>
            <td>Pengeluaran</td>
            <td>- Rp {{ $summary['pengeluaran'] }}</td>
        </tr>
        {{-- BARIS 8 --}}
        <tr>
            <td>Real Cash</td>
            <td>Rp {{ $summary['real_cash'] }}</td>
        </tr>
        {{-- BARIS 9 --}}
        <tr>
            <td>Balance Akhir</td>
            <td>Rp {{ $summary['balance_hijau'] }}</td>
        </tr>
        
        {{-- BARIS 10 (Pemisah / Jarak Kosong) --}}
        <tr>
            <td colspan="10"></td>
        </tr>

        {{-- BARIS 11 (Header Transaksi) --}}
        <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Tanggal Bayar</th>
            <th>Tgl Mulai Aktif</th>
            <th>Tgl Berakhir</th>
            <th>Status</th>
            <th>Paket Member</th>
            <th>Nominal</th>
            <th>Metode Bayar</th>
            <th>Admin</th>
        </tr>

        {{-- BARIS 12 DAN SETERUSNYA (Data Transaksi) --}}
        @foreach($transactions as $index => $transaction)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    @if($transaction->membership && $transaction->membership->members->count() > 0)
                        @foreach($transaction->membership->members as $member)
                            {{ $member->name }}{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    @else
                        {{ $transaction->user->name ?? 'User Terhapus' }}
                    @endif
                </td>
                <td>{{ \Carbon\Carbon::parse($transaction->payment_date)->format('Y-m-d H:i') }}</td>
                <td>{{ $transaction->start_date }}</td>
                <td>{{ $transaction->end_date }}</td>
                <td>{{ $transaction->transaction_type }}</td>
                <td>{{ $transaction->package_name }}</td>
                <td>{{ $transaction->amount }}</td>
                <td>{{ strtoupper($transaction->payment_method) }}</td>
                <td>{{ $transaction->admin->name ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>