@php($uang = fn ($n) => \App\Support\Uang::format((string) $n))
@php($warna = ['tambah' => 'text-success-600', 'hapus' => 'text-danger-600 line-through', 'ubah' => 'text-warning-600', 'tetap' => ''])
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left">
                <th class="p-2">Tanda</th><th class="p-2">Uraian</th><th class="p-2">Akun</th>
                <th class="p-2 text-right">Lama</th><th class="p-2 text-right">Baru</th><th class="p-2 text-right">Selisih</th><th class="p-2 text-right">Terealisasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($perbandingan['baris'] as $b)
                <tr class="border-b {{ $warna[$b['tanda']] ?? '' }}">
                    <td class="p-2">{{ ucfirst($b['tanda']) }}</td>
                    <td class="p-2">{{ $b['uraian'] }}</td>
                    <td class="p-2">{{ $b['akun'] }}</td>
                    <td class="p-2 text-right">{{ $uang($b['lama']) }}</td>
                    <td class="p-2 text-right">{{ $uang($b['baru']) }}</td>
                    <td class="p-2 text-right">{{ $uang($b['selisih']) }}</td>
                    <td class="p-2 text-right">{{ $uang($b['terealisasi']) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold">
                <td class="p-2" colspan="3">Total</td>
                <td class="p-2 text-right">{{ $uang($perbandingan['total_lama']) }}</td>
                <td class="p-2 text-right">{{ $uang($perbandingan['total_baru']) }}</td>
                <td class="p-2 text-right">{{ $uang($perbandingan['total_selisih']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>
