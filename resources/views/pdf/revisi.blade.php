@php
    use App\Support\Uang;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Revisi RAB ke-{{ $rab->nomor_revisi }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { font-size: 12pt; margin: 0; }
        h2 { font-size: 11pt; text-align: center; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 4px; vertical-align: top; }
        .rab th, .rab td { border: 1px solid #444; padding: 3px 4px; }
        .rab th { background: #eee; }
        .kanan { text-align: right; }
        .kaki { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #555; text-align: center; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $namaFakultas }} — Universitas Siliwangi</h1><p>Revisi RAB ke-{{ $rab->nomor_revisi }}</p></div>
    <table class="info">
        <tr><td width="130">Nomor pengajuan</td><td>: {{ $pengajuan->nomor }}</td></tr>
        <tr><td>Unit</td><td>: {{ $pengajuan->unit->nama }}</td></tr>
        <tr><td>Judul kegiatan</td><td>: {{ $pengajuan->judul_kegiatan }}</td></tr>
        <tr><td>Status revisi</td><td>: {{ $rab->status->getLabel() }}</td></tr>
        <tr><td>Alasan</td><td>: {{ $rab->alasan_revisi }}</td></tr>
    </table>
    <h2>Perbandingan dengan versi sebelumnya</h2>
    <table class="rab">
        <thead><tr><th>Tanda</th><th>Uraian</th><th>Akun</th><th>Lama</th><th>Baru</th><th>Selisih</th><th>Terealisasi</th></tr></thead>
        <tbody>
            @foreach ($perbandingan['baris'] as $b)
                <tr>
                    <td>{{ ucfirst($b['tanda']) }}</td><td>{{ $b['uraian'] }}</td><td>{{ $b['akun'] }}</td>
                    <td class="kanan">{{ Uang::format($b['lama']) }}</td><td class="kanan">{{ Uang::format($b['baru']) }}</td>
                    <td class="kanan">{{ Uang::format($b['selisih']) }}</td><td class="kanan">{{ Uang::format($b['terealisasi']) }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="3" class="kanan">Total</th>
                <th class="kanan">{{ Uang::format($perbandingan['total_lama']) }}</th>
                <th class="kanan">{{ Uang::format($perbandingan['total_baru']) }}</th>
                <th class="kanan">{{ Uang::format($perbandingan['total_selisih']) }}</th>
                <th></th>
            </tr>
        </tbody>
    </table>
    <h2>Riwayat</h2>
    <table class="rab">
        <thead><tr><th>Waktu</th><th>Tahap</th><th>Nama</th><th>Ke</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($rab->riwayatStatus as $r)
                <tr>
                    <td>{{ $r->created_at->locale('id')->translatedFormat('d F Y H:i') }}</td>
                    <td>{{ $r->tahap?->getLabel() ?? '—' }}</td>
                    <td>{{ $r->user?->name ?? 'Sistem' }}</td>
                    <td>{{ \App\Enums\StatusRab::from($r->ke)->getLabel() }}</td>
                    <td>{{ $r->catatan }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="kaki">Dicetak dari Keuangan FKIP Unsil pada {{ $dicetakPada->translatedFormat('d F Y H:i') }}</div>
</body>
</html>
