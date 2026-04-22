<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Expired Notification</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #dc2626; color: #ffffff; padding: 20px; text-align: center; }
        .content { padding: 24px; }
        .footer { background-color: #f9fafb; padding: 16px; text-align: center; font-size: 12px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        th { background-color: #f3f4f6; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .badge-membership { background: #dbeafe; color: #1e40af; }
        .badge-pt { background: #fef3c7; color: #92400e; }
        .badge-visit { background: #d1fae5; color: #065f46; }
        .badge-bundle { background: #f3e8ff; color: #6b21a8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0; font-size: 20px;">Notifikasi Membership Expired</h1>
            <p style="margin:8px 0 0; font-size: 14px;">{{ now()->format('l, d F Y') }}</p>
        </div>

        <div class="content">
            <p>Halo Admin,</p>
            <p>Cron job <strong>memberships:check-expired</strong> telah berjalan. Berikut ringkasan hasil pemeriksaan:</p>

            <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 16px 0; border-radius: 4px;">
                <p style="margin: 0; font-size: 18px; font-weight: bold; color: #dc2626;">{{ $updatedCount }} Membership</p>
                <p style="margin: 4px 0 0; color: #7f1d1d;">telah diupdate ke status <strong>COMPLETED</strong> karena melewati tenggat waktu atau sesi habis.</p>
            </div>

            @if(count($details) > 0)
                <h3 style="margin-top: 24px; font-size: 16px;">Detail Membership:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipe</th>
                            <th>Alasan</th>
                            <th>User ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($details as $item)
                            <tr>
                                <td>#{{ $item['id'] }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($item['type']) {
                                            'membership' => 'badge-membership',
                                            'pt' => 'badge-pt',
                                            'visit' => 'badge-visit',
                                            'bundle_pt_membership' => 'badge-bundle',
                                            default => 'badge-membership',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ strtoupper($item['type']) }}</span>
                                </td>
                                <td>{{ $item['reason'] }}</td>
                                <td>{{ $item['user_id'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <p style="margin-top: 24px; color: #374151;">Jika ada pertanyaan, silakan hubungi tim IT.</p>
        </div>

        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh sistem.</p>
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
