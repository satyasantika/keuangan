@php($uang = fn ($n) => \App\Support\Uang::format((string) $n))
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left">
                <th class="p-2">Unit</th><th class="p-2 text-right">Pagu</th><th class="p-2 text-right">Disetujui (kumulatif)</th>
                <th class="p-2 text-right">Pencairan</th><th class="p-2 text-right">Realisasi</th><th class="p-2 text-right">Pajak</th><th class="p-2 text-right">Pengembalian</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rekap as $r)
                <tr class="border-b">
                    <td class="p-2">{{ $r->unit->kode }}</td>
                    <td class="p-2 text-right">{{ $uang($r->pagu) }}</td>
                    <td class="p-2 text-right">{{ $uang($r->total_disetujui_kumulatif) }}</td>
                    <td class="p-2 text-right">{{ $uang($r->total_pencairan) }}</td>
                    <td class="p-2 text-right">{{ $uang($r->total_realisasi) }}</td>
                    <td class="p-2 text-right">{{ $uang($r->total_pajak) }}</td>
                    <td class="p-2 text-right">{{ $uang($r->total_pengembalian) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-2 text-gray-500">Tidak ada angka pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
