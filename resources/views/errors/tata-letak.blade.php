<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('kode') — {{ config('app.name', 'Keuangan FKIP Unsil') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f9fafb; color: #111827; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        .kode { font-size: 3rem; font-weight: 700; color: #d97706; margin: 0; }
        h1 { font-size: 1.25rem; margin: .5rem 0 1rem; }
        p { color: #4b5563; line-height: 1.5; }
        a { display: inline-block; margin-top: 1.5rem; color: #fff; background: #d97706; padding: .5rem 1rem; border-radius: .5rem; text-decoration: none; }
        @media (prefers-color-scheme: dark) { body { background: #111827; color: #f9fafb; } p { color: #d1d5db; } }
    </style>
</head>
<body>
<main>
    <p class="kode">@yield('kode')</p>
    <h1>@yield('judul')</h1>
    <p>@yield('pesan')</p>
    <a href="{{ url('/panel') }}">Kembali ke beranda</a>
</main>
</body>
</html>
