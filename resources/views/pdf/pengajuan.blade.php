@php
    use App\Support\Uang;
    $rab = $pengajuan->rabAktif;
    $tgl = fn ($t) => $t?->locale('id')->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pengajuan {{ $pengajuan->nomor }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #111; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { font-size: 12pt; margin: 0; }
        .kop p { margin: 2px 0 0; font-size: 10pt; }
        h2 { font-size: 11pt; text-align: center; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 4px; vertical-align: top; }
        .rab th, .rab td { border: 1px solid #444; padding: 3px 4px; }
        .rab th { background: #eee; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .qr { width: 110px; text-align: center; font-size: 7.5pt; }
        .kaki { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #555; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $namaFakultas }} — Universitas Siliwangi</h1>
        <p>Lembar Pengajuan Kegiatan dan Rencana Anggaran Biaya</p>
    </div>

    <table>
        <tr>
            <td>
                <table class="info">
                    <tr><td width="130">Nomor</td><td>: {{ $pengajuan->nomor }}</td></tr>
                    <tr><td>Unit</td><td>: {{ $pengajuan->unit->kode }} — {{ $pengajuan->unit->nama }}</td></tr>
                    <tr><td>Tahun anggaran</td><td>: {{ $pengajuan->tahunAnggaran->tahun }}</td></tr>
                    <tr><td>Judul kegiatan</td><td>: {{ $pengajuan->judul_kegiatan }}</td></tr>
                    <tr><td>Tanggal kegiatan</td><td>: {{ $tgl($pengajuan->tanggal_mulai_kegiatan) }} s.d. {{ $tgl($pengajuan->tanggal_selesai_kegiatan) }}</td></tr>
                    <tr><td>Tempat</td><td>: {{ $pengajuan->tempat ?? '—' }}</td></tr>
                    <tr><td>Penanggung jawab</td><td>: {{ $pengajuan->penanggung_jawab }}</td></tr>
                    <tr><td>Status</td><td>: {{ $pengajuan->status->getLabel() }}</td></tr>
                </table>
            </td>
            <td class="qr">
                <img src="{{ $qr }}" width="100" height="100" alt="QR verifikasi"><br>
                Pindai untuk verifikasi
            </td>
        </tr>
    </table>

    <h2>Rincian RAB (versi {{ $rab?->nomor_revisi ?? 0 }})</h2>
    <table class="rab">
        <thead>
            <tr>
                <th>No</th><th>Akun</th><th>Uraian</th><th>Rincian volume</th><th>Volume</th><th>Satuan</th><th>Harga satuan</th><th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rab?->item ?? [] as $item)
                <tr>
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td>{{ $item->akun->kode }}</td>
                    <td>{{ $item->uraian }}</td>
                    <td>{{ $item->rincian_volume }}</td>
                    <td class="kanan">{{ $item->volume }}</td>
                    <td>{{ $item->satuan }}</td>
                    <td class="kanan">{{ Uang::format((string) $item->harga_satuan) }}</td>
                    <td class="kanan">{{ Uang::format((string) $item->jumlah) }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="7" class="kanan">Total</th>
                <th class="kanan">{{ Uang::format((string) ($rab?->total ?? '0')) }}</th>
            </tr>
        </tbody>
    </table>

    <h2>Riwayat persetujuan</h2>
    <table class="rab">
        <thead><tr><th>Waktu</th><th>Tahap</th><th>Nama</th><th>Perubahan</th><th>Catatan</th></tr></thead>
        <tbody>
            @forelse ($pengajuan->riwayatStatus as $r)
                <tr>
                    <td>{{ $r->created_at->locale('id')->translatedFormat('d F Y H:i') }}</td>
                    <td>{{ $r->tahap?->getLabel() ?? '—' }}</td>
                    <td>{{ $r->user?->name ?? 'Sistem' }}</td>
                    <td>{{ $r->dari ? \App\Enums\StatusPengajuan::from($r->dari)->getLabel().' → ' : '' }}{{ \App\Enums\StatusPengajuan::from($r->ke)->getLabel() }}</td>
                    <td>{{ $r->catatan }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="tengah">—</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="kaki">Dicetak dari Keuangan FKIP Unsil pada {{ $dicetakPada->translatedFormat('d F Y H:i') }}</div>
</body>
</html>
