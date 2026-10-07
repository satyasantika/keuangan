<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased flex items-center justify-center">
    <main class="max-w-xl p-8 text-center">
        <h1 class="text-3xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-3 text-stone-600">Pencatatan dan pelaporan keuangan kegiatan fakultas.</p>
        <p class="mt-6 text-sm text-stone-500">Versi {{ config('app.version') }}</p>
    </main>
</body>
</html>
