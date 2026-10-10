@php($daftar = $getState() ?? [])
<div class="space-y-3">
    @forelse ($daftar as $tautan)
        <div x-data="{ pratinjau: false }" class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ $tautan['label'] }}</span>
                <x-filament::badge color="gray">{{ $tautan['jenis'] }}</x-filament::badge>
                <x-filament::badge :color="$tautan['warna_status']">{{ $tautan['status'] }}</x-filament::badge>
                @if ($tautan['peringatan_privasi'])
                    <x-filament::badge color="danger">Akses publik — minta dibatasi</x-filament::badge>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                <x-filament::button tag="a" size="sm" :href="$tautan['url']" target="_blank" rel="noopener noreferrer" icon="heroicon-o-arrow-top-right-on-square">
                    Buka berkas
                </x-filament::button>
                @if ($tautan['pratinjau'])
                    <x-filament::button size="sm" color="gray" x-on:click="pratinjau = ! pratinjau" icon="heroicon-o-eye">
                        Pratinjau
                    </x-filament::button>
                @endif
            </div>
            @if ($tautan['pratinjau'])
                <div x-show="pratinjau" x-cloak class="mt-3">
                    @if ($tautan['foto'])
                        <div x-data="{ gagal: false }">
                            <img x-show="! gagal" x-on:error="gagal = true" src="{{ $tautan['pratinjau'] }}" alt="{{ $tautan['label'] }}" referrerpolicy="no-referrer" loading="lazy" class="max-h-96 rounded">
                            <p x-show="gagal" class="text-sm text-gray-500">{{ \App\Filament\Infolists\DaftarTautanBerkas::PLACEHOLDER_PRATINJAU }}</p>
                        </div>
                    @else
                        <iframe src="{{ $tautan['pratinjau'] }}" class="h-96 w-full rounded border-0" referrerpolicy="no-referrer" loading="lazy" title="Pratinjau {{ $tautan['label'] }}"></iframe>
                        <p class="mt-1 text-xs text-gray-500">{{ \App\Filament\Infolists\DaftarTautanBerkas::PLACEHOLDER_PRATINJAU }} bila bingkai kosong.</p>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Belum ada tautan berkas.</p>
    @endforelse
</div>
