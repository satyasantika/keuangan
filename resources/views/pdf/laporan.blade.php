<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $data->judul }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .kop h1 { font-size: 12pt; margin: 0; }
        .kop p { margin: 2px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 3px 4px; }
        th { background: #eee; }
        .kanan { text-align: right; }
        .total td { font-weight: bold; background: #f6f6f6; }
        .catatan { margin-top: 8px; font-size: 8pt; }
        .kaki { position: fixed; bottom: -6mm; left: 0; right: 0; font-size: 7.5pt; color: #555; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $namaFakultas }} — Universitas Siliwangi</h1>
        <p><strong>{{ $data->kode }} {{ $data->judul }}</strong></p>
        <p>{{ $data->subjudul }}</p>
    </div>
    <table>
        <thead><tr>@foreach ($data->kolom as $k)<th>{{ $k }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($data->baris as $baris)
                <tr>@foreach ($data->kolom as $i => $k)<td class="{{ in_array($i, $data->kolomUang, true) ? 'kanan' : '' }}">{{ $format($baris[$i] ?? '', $i) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($data->kolom) }}">Tidak ada data.</td></tr>
            @endforelse
            @if ($data->total)
                <tr class="total">@foreach ($data->kolom as $i => $k)<td class="{{ in_array($i, $data->kolomUang, true) ? 'kanan' : '' }}">{{ $format($data->total[$i] ?? '', $i) }}</td>@endforeach</tr>
            @endif
        </tbody>
    </table>
    @foreach ($data->catatan as $c)
        <p class="catatan">Catatan: {{ $c }}</p>
    @endforeach
    <div class="kaki">Dicetak oleh {{ $pencetak }} dari Keuangan FKIP Unsil pada {{ $dicetakPada->translatedFormat('d F Y H:i') }}</div>
</body>
</html>
