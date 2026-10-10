@props([
    'after' => null,
    'heading' => null,
    'subheading' => null,
])

@php
    use Filament\View\PanelsRenderHook;
    use Filament\Support\Facades\FilamentView;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <link rel="stylesheet" href="{{ asset('css/keuangan.css') }}">

    <div class="k-pisah">
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <aside class="k-pisah__kiri">
            <a class="k-merek" href="{{ route('beranda') }}"><span class="k-logo">K</span> {{ config('app.name') }}</a>
            <div>
                <h1>Keuangan kegiatan fakultas, tercatat rapi dan terkendali.</h1>
                <p>Pagu unit, pengajuan dan RAB, persetujuan berjenjang, pencairan, SPJ, hingga laporan dalam satu aplikasi.</p>
                <ul>
                    <li>Persetujuan berjenjang dengan pemisahan tugas</li>
                    <li>Bukti berupa tautan Google Drive terbatas</li>
                    <li>Jejak audit pada setiap perubahan status</li>
                </ul>
            </div>
            <small>Fakultas Keguruan dan Ilmu Pendidikan · Universitas Siliwangi · <a href="{{ route('beranda') }}">Beranda</a> · <a href="{{ asset('panduan/index.html') }}">Panduan</a></small>
        </aside>

        <div class="k-pisah__kanan">
            <main id="fi-main-content" tabindex="-1" class="k-pisah__form">
                {{ $slot }}
            </main>
            <div class="k-pisah__kaki">{{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}</div>
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
