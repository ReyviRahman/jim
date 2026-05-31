<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Data Absensi Training Sessions - {{ $membership->user?->name ?? 'Unknown' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #333; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .section-title { font-size: 12px; font-weight: bold; margin-top: 15px; margin-bottom: 8px; background-color: #f0f0f0; padding: 6px 10px; }
        .info-grid { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-bottom: 12px; }
        .info-item { width: 48%; }
        .info-item.full { width: 100%; }
        .info-item strong { display: inline-block; width: 110px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th, table td { border: 1px solid #ccc; padding: 6px 8px; }
        table th { background-color: #f0f0f0; font-weight: bold; text-align: left; }
        table td.text-center { text-align: center; }
        .member-list { margin-bottom: 4px; }
        .footer { margin-top: 10px; font-size: 10px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>DATA ABSENSI TRAINING SESSIONS</h1>
    </div>

    <div class="section-title">PERSONAL DATA</div>
    <div class="info-grid">
        <div class="info-item full">
            <strong>Nama:</strong>
            @php
                $memberNames = $allMembers->pluck('name')->filter()->values();
            @endphp
            {{ $memberNames->join(', ') }}
        </div>
        <div class="info-item">
            <strong>Gender:</strong>
            @php
                $genders = $allMembers->pluck('gender')->filter()->values();
            @endphp
            {{ $genders->join(', ') }}
        </div>
        <div class="info-item">
            <strong>Usia:</strong>
            @php
                $ages = $allMembers->pluck('age')->filter()->values();
            @endphp
            {{ $ages->join(', ') }} th
        </div>
        <div class="info-item">
            <strong>Coach:</strong> {{ $membership->personalTrainer?->name ?? '-' }}
        </div>
        <div class="info-item">
            <strong>Total Sesi:</strong> {{ $totalSessions }}
        </div>
        <div class="info-item">
            <strong>Start Date:</strong> {{ \Carbon\Carbon::parse($membership->start_date ?? $membership->created_at)->translatedFormat('d F Y') }}
        </div>
        <div class="info-item">
            <strong>PT End Date:</strong> {{ $membership->pt_end_date ? \Carbon\Carbon::parse($membership->pt_end_date)->translatedFormat('d F Y') : '-' }}
        </div>
        <div class="info-item">
            <strong>Durasi:</strong> {{ $durationMonths }} bulan
        </div>
    </div>

    <div class="section-title">ABSENSI TRAINING SESSIONS</div>
    <table>
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">No</th>
                <th>Tanggal Booking</th>
                <th>Waktu Booking</th>
                <th>Nama Member</th>
                <th>Nama PT</th>
                <th style="text-align: center;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendedBookings as $booking)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ \Carbon\Carbon::parse($booking->booking_date)->translatedFormat('d F Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }}</td>
                    <td>{{ $memberNames->join(', ') }}</td>
                    <td>{{ $booking->pt?->name ?? '-' }}</td>
                    <td class="text-center">{{ $booking->is_free ? 'Gratis' : 'Hadir' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">Belum ada data absensi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dicetak pada {{ now()->translatedFormat('d F Y H:i') }}
    </div>
</body>
</html>
