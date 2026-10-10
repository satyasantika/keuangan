<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Panduan — {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; line-height: 1.6; color: #111827; background: #f9fafb; }
        header { background: #d97706; color: #fff; padding: .75rem 1rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        header a { color: #fff; }
        nav { padding: .5rem 1rem; background: #fff; border-bottom: 1px solid #e5e7eb; display: flex; gap: .75rem; flex-wrap: wrap; font-size: .9rem; }
        nav a { color: #92400e; text-decoration: none; }
        nav a[aria-current="page"] { font-weight: 700; text-decoration: underline; }
        main { max-width: 52rem; margin: 0 auto; padding: 1rem; background: #fff; }
        table { border-collapse: collapse; width: 100%; } td, th { border: 1px solid #e5e7eb; padding: .35rem .5rem; text-align: left; }
        code { background: #f3f4f6; padding: 0 .25rem; border-radius: .25rem; }
        pre { overflow-x: auto; background: #f3f4f6; padding: .75rem; }
    </style>
</head>
<body>
<header>
    <strong>Panduan {{ config('app.name') }}</strong>
    <a href="{{ url('/panel') }}">← Kembali ke aplikasi</a>
</header>
<nav aria-label="Daftar panduan">
    @foreach ($daftar as $item)
        <a href="{{ route('panduan.pengguna', $item) }}" @if ($item === $halaman) aria-current="page" @endif>{{ $item === 'README' ? 'Umum' : $item }}</a>
    @endforeach
</nav>
<main>
    {!! $isi !!}{{-- Markdown dari docs/panduan dirender dengan html_input=escape --}}
</main>
</body>
</html>
