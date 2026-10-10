@php($data = $pratinjau['data'])
<x-filament::section heading="Pratinjau (maks. 50 baris)">
    @if ($pratinjau['galat'])
        <p class="text-sm text-danger-600">{{ $pratinjau['galat'] }}</p>
    @elseif ($data)
        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">{{ $data->judul }} — {{ $data->subjudul }} ({{ count($data->baris) }} baris)</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left">@foreach ($data->kolom as $k)<th class="p-2">{{ $k }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach (array_slice($data->baris, 0, 50) as $baris)
                        <tr class="border-b">
                            @foreach ($data->kolom as $i => $k)
                                <td class="p-2 {{ in_array($i, $data->kolomUang, true) ? 'text-right' : '' }}">{{ in_array($i, $data->kolomUang, true) && is_numeric($baris[$i] ?? null) ? \App\Support\Uang::format((string) $baris[$i]) : ($baris[$i] ?? '') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($data->total)
                        <tr class="font-semibold">
                            @foreach ($data->kolom as $i => $k)
                                <td class="p-2 {{ in_array($i, $data->kolomUang, true) ? 'text-right' : '' }}">{{ in_array($i, $data->kolomUang, true) && is_numeric($data->total[$i] ?? null) ? \App\Support\Uang::format((string) $data->total[$i]) : ($data->total[$i] ?? '') }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @foreach ($data->catatan as $c)
            <p class="mt-2 text-xs text-gray-500">Catatan: {{ $c }}</p>
        @endforeach
    @else
        <p class="text-sm text-gray-500">Pilih jenis laporan dan tahun anggaran.</p>
    @endif
</x-filament::section>
