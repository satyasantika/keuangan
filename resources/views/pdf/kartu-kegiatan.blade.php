@php
    use App\Support\Uang;
    $u = fn ($n) => Uang::format((string) $n);
    $tgl = fn ($t) => $t?->locale('id')->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Kegiatan {{ $pengajuan->nomor }}</title>
    <style>
        @page { margin: 16mm 13mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .kop h1 { font-size: 12pt; margin: 0; }
        h2 { font-size: 10pt; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        .t th, .t td { border: 1px solid #444; padding: 3px 4px; }
        .t th { background: #eee; }
        .info td { padding: 1px 4px; }
        .kanan { text-align: right; }
        .kaki { position: fixed; bottom: -6mm; left: 0; right: 0; font-size: 7.5pt; color: #555; text-align: center; }
    </style>
</head>
<body>
    <div class="kop"><h1>{{ $namaFakultas }} — Universitas Siliwangi</h1><p><strong>LAP-03 Kartu Kegiatan</strong></p></div>
    <table class="info">
        <tr><td width="130">Nomor</td><td>: {{ $pengajuan->nomor ?? '—' }}</td><td width="110">Status</td><td>: {{ $pengajuan->status->getLabel() }}</td></tr>
        <tr><td>Unit</td><td>: {{ $pengajuan->unit->nama }}</td><td>Tahun</td><td>: {{ $pengajuan->tahunAnggaran->tahun }}</td></tr>
        <tr><td>Kegiatan</td><td colspan="3">: {{ $pengajuan->judul_kegiatan }} ({{ $tgl($pengajuan->tanggal_mulai_kegiatan) }} s.d. {{ $tgl($pengajuan->tanggal_selesai_kegiatan) }})</td></tr>
        <tr><td>Disetujui</td><td>: {{ $u($pengajuan->total_disetujui) }}</td><td>Dicairkan</td><td>: {{ $u($pengajuan->total_dicairkan) }}</td></tr>
        <tr><td>Realisasi</td><td>: {{ $u($pengajuan->total_realisasi) }}</td><td>Dikembalikan</td><td>: {{ $u($pengajuan->total_dikembalikan) }}</td></tr>
        <tr><td>Batas SPJ</td><td>: {{ $tgl($pengajuan->batas_spj) ?? '—' }}</td><td>Pajak (informasi)</td><td>: {{ $u($pengajuan->total_pajak) }}</td></tr>
    </table>

    <h2>RAB aktif (versi {{ $pengajuan->rabAktif?->nomor_revisi ?? 0 }}) dan realisasi per item</h2>
    <table class="t">
        <thead><tr><th>Akun</th><th>Uraian</th><th>Jumlah RAB</th><th>Terealisasi</th><th>Sisa</th></tr></thead>
        <tbody>
            @foreach ($pengajuan->rabAktif?->item ?? [] as $item)
                @php($real = Uang::jumlahkan(($pengajuan->spj?->realisasiItem ?? collect())->where('rab_item_id', $item->id)->pluck('jumlah_bruto')->map(fn ($j) => (string) $j)->all()))
                <tr><td>{{ $item->akun->kode }}</td><td>{{ $item->uraian }}</td><td class="kanan">{{ $u($item->jumlah) }}</td><td class="kanan">{{ $u($real) }}</td><td class="kanan">{{ $u(Uang::kurang((string) $item->jumlah, $real)) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Versi RAB</h2>
    <table class="t">
        <thead><tr><th>Versi</th><th>Status</th><th>Total</th><th>Alasan</th></tr></thead>
        <tbody>
            @foreach ($pengajuan->rab as $rab)
                <tr><td>{{ $rab->nomor_revisi === 0 ? 'Awal' : 'Revisi ke-'.$rab->nomor_revisi }}</td><td>{{ $rab->status->getLabel() }}</td><td class="kanan">{{ $u($rab->total) }}</td><td>{{ $rab->alasan_revisi }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Pencairan</h2>
    <table class="t">
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Nomor bukti</th><th>Status</th><th>Jumlah</th></tr></thead>
        <tbody>
            @forelse ($pengajuan->pencairan as $c)
                <tr><td>{{ $c->tanggal->format('d-m-Y') }}</td><td>{{ $c->jenisPencairan->kode }}</td><td>{{ $c->nomor_bukti }}</td><td>{{ $c->status->getLabel() }}</td><td class="kanan">{{ $u($c->jumlah) }}</td></tr>
            @empty
                <tr><td colspan="5">—</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Realisasi & potongan pajak</h2>
    <table class="t">
        <thead><tr><th>Tanggal</th><th>No. bukti</th><th>Uraian</th><th>Bruto</th><th>Pajak</th><th>Neto</th></tr></thead>
        <tbody>
            @forelse ($pengajuan->spj?->realisasiItem ?? [] as $r)
                <tr><td>{{ $r->tanggal_bukti->format('d-m-Y') }}</td><td>{{ $r->nomor_bukti }}</td><td>{{ $r->uraian }}
                    @foreach ($r->potonganPajak as $pp)<br><small>{{ $pp->jenisPajak->nama }}: {{ $u($pp->nilai) }}</small>@endforeach
                </td><td class="kanan">{{ $u($r->jumlah_bruto) }}</td><td class="kanan">{{ $u($r->total_pajak) }}</td><td class="kanan">{{ $u($r->jumlah_neto) }}</td></tr>
            @empty
                <tr><td colspan="6">—</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Pengembalian sisa dana</h2>
    <table class="t">
        <thead><tr><th>Tanggal setor</th><th>Nomor bukti setor</th><th>Status</th><th>Jumlah</th></tr></thead>
        <tbody>
            @forelse ($pengajuan->pengembalian as $k)
                <tr><td>{{ $k->tanggal_setor->format('d-m-Y') }}</td><td>{{ $k->nomor_bukti_setor }}</td><td>{{ $k->status->getLabel() }}</td><td class="kanan">{{ $u($k->jumlah) }}</td></tr>
            @empty
                <tr><td colspan="4">—</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Linimasa status</h2>
    <table class="t">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Ke</th><th>Catatan</th></tr></thead>
        <tbody>
            @foreach ($pengajuan->riwayatStatus as $rw)
                <tr><td>{{ $rw->created_at->format('d-m-Y H:i') }}</td><td>{{ $rw->user?->name ?? 'Sistem' }}</td><td>{{ \App\Enums\StatusPengajuan::tryFrom($rw->ke)?->getLabel() ?? $rw->ke }}</td><td>{{ $rw->catatan }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="kaki">Dicetak oleh {{ $pencetak }} dari Keuangan FKIP Unsil pada {{ $dicetakPada->translatedFormat('d F Y H:i') }}</div>
</body>
</html>
