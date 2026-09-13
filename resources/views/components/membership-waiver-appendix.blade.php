@props(['waivers' => []])

@if(count($waivers))
    <section style="page-break-before: always; padding: 28px 34px; font-size: 10px; line-height: 1.5;">
        <h2 style="font-size: 17px; margin: 0 0 16px;">Lampiran Persetujuan &amp; Waiver</h2>
        @foreach($waivers as $waiver)
            <div style="page-break-inside: avoid; border: 1px solid #d1d5db; padding: 14px; margin-bottom: 16px;">
                <h3 style="font-size: 13px; margin: 0 0 10px;">{{ $waiver['member_name'] }}</h3>
                <strong>{{ $waiver['terms_snapshot']['heading'] }}</strong>
                <p>{{ $waiver['terms_snapshot']['introduction'] }}</p>
                <ol style="margin: 8px 0 12px; padding-left: 20px;">
                    @foreach($waiver['terms_snapshot']['items'] as $term)
                        <li style="margin-bottom: 4px;">{{ $term }}</li>
                    @endforeach
                </ol>
                <p>{{ $waiver['consent_label'] }}</p>
                <p><strong>Status checkbox:</strong> {{ $waiver['accepted'] ? 'Disetujui' : 'Belum dicentang' }}</p>
                <div><strong>Tanda tangan</strong></div>
                @if($waiver['signature_data_uri'])
                    <img src="{{ $waiver['signature_data_uri'] }}" alt="Tanda tangan {{ $waiver['member_name'] }}" style="width: 240px; height: auto; max-height: 144px;">
                @else
                    <p>Tidak diisi</p>
                @endif
                <div style="font-size: 9px; color: #6b7280; margin-top: 6px;">Dicatat {{ $waiver['recorded_at']->locale('id')->translatedFormat('d F Y H:i') }} WIB</div>
            </div>
        @endforeach
    </section>
@endif
