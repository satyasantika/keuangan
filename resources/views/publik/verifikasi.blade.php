@php($uang = fn ($n) => \App\Support\Uang::format((string) $n))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verifikasi Pengajuan {{ $pengajuan->nomor }}</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f6f7f9; color: #111; margin: 0; padding: 24px 16px; }
        main { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 1.15rem; margin: 0 0 16px; }
        dl { display: grid; grid-template-columns: 9rem 1fr; gap: 8px 12px; margin: 0; }
        dt { color: #555; }
        dd { margin: 0; font-weight: 600; }
        .catatan { margin-top: 20px; font-size: .9rem; color: #166534; background: #dcfce7; padding: 10px 12px; border-radius: 8px; }
        @media (max-width: 420px) { dl { grid-template-columns: 1fr; } dd { margin-bottom: 6px; } }
    </style>
</head>
<body>
<main>
    <h1>Verifikasi Lembar Pengajuan</h1>
    <dl>
        <dt>Nomor</dt><dd>{{ $pengajuan->nomor }}</dd>
        <dt>Unit</dt><dd>{{ $pengajuan->unit->nama }}</dd>
        <dt>Judul kegiatan</dt><dd>{{ $pengajuan->judul_kegiatan }}</dd>
        <dt>Status</dt><dd>{{ $pengajuan->status->getLabel() }}</dd>
        @if ($pengajuan->disetujui_pada)
            <dt>Total disetujui</dt><dd>{{ $uang($pengajuan->total_disetujui) }}</dd>
            <dt>Tanggal persetujuan</dt><dd>{{ $pengajuan->disetujui_pada->locale('id')->translatedFormat('d F Y') }}</dd>
        @endif
    </dl>
    <p class="catatan">Dokumen ini tercatat di Keuangan FKIP Unsil.</p>
</main>
</body>
</html>
