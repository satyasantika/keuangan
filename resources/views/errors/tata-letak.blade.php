@php
    $kode = (int) ($kode ?? 500);
    $teks = trans('galat.'.$kode);
    $teks = is_array($teks) ? $teks : trans('galat.umum');
    $bisaUlang = ($teks['ulang'] ?? false) && request()->isMethod('GET');
    $css = @file_get_contents(public_path('css/keuangan.css')) ?: '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $kode }} {{ $teks['judul'] }} — {{ config('app.name', 'Keuangan FKIP Unsil') }}</title>
    <style>{!! $css !!}</style>
</head>
<body class="k-galat">
<header class="k-atas">
    <div class="k-wadah">
        <a class="k-merek" href="{{ url('/') }}"><span class="k-logo">K</span> {{ config('app.name', 'Keuangan FKIP Unsil') }}</a>
    </div>
</header>
<main>
    <div class="k-galat__kotak">
        <p class="k-galat__kode">{{ $kode }}</p>
        <h1>{{ $teks['judul'] }}</h1>
        <p>{{ $teks['pesan'] }}</p>
        @if (! empty($teks['saran']))
            <ul class="k-galat__saran">
                @foreach ($teks['saran'] as $saran)
                    <li>{{ $saran }}</li>
                @endforeach
            </ul>
        @endif
        <div class="k-galat__aksi">
            @if ($bisaUlang)
                <a class="k-tombol" href="{{ request()->fullUrl() }}">Coba lagi</a>
            @endif
            <a class="k-tombol {{ $bisaUlang ? 'k-tombol--biru' : '' }}" href="{{ url('/panel') }}">{{ $kode === 401 ? 'Masuk' : 'Buka aplikasi' }}</a>
            <a class="k-tombol k-tombol--biru" href="{{ url('/') }}">Ke beranda</a>
        </div>
        @if ($kode >= 500)
            <p class="k-galat__rujukan">Waktu kejadian: <code>{{ now()->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }} WIB</code></p>
        @endif
    </div>
</main>
<footer class="k-kaki"><div class="k-wadah">{{ config('app.name', 'Keuangan FKIP Unsil') }}</div></footer>
</body>
</html>
