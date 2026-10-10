@php
    $peran = [
        ['admin-unit', 'Admin Prodi/Unit', 'Menyusun pengajuan kegiatan dan RAB, mencatat realisasi dan bukti, mengajukan SPJ, serta menyetor sisa dana.'],
        ['kepala-unit', 'Ketua Prodi/Kepala Unit', 'Memverifikasi pengajuan unit pada tahap unit dan memantau serapan anggaran unit.'],
        ['admin-keuangan', 'Admin Keuangan Fakultas', 'Menyiapkan tahun anggaran dan pagu, memverifikasi pengajuan, SPJ, dan pengembalian, mengunci periode, menyusun laporan.'],
        ['bpp', 'Bendahara Pengeluaran Pembantu', 'Mencatat pencairan dana, memverifikasi SPJ dan setoran sisa dana.'],
        ['wadek-keuangan', 'Wakil Dekan bidang Keuangan', 'Menetapkan pagu, memberi persetujuan akhir, membuka kunci periode, dan menutup tahun anggaran.'],
        ['pimpinan', 'Pimpinan/Dekanat', 'Memantau anggaran dan realisasi seluruh unit dalam tampilan baca-saja.'],
    ];
    $fitur = [
        ['📊', 'Pagu per unit & tahun', 'Pagu ditetapkan per tahun anggaran dan per unit; sisa pagu dihitung otomatis dari komitmen yang berjalan.'],
        ['📝', 'Pengajuan & RAB', 'Rencana anggaran biaya per item dengan perhitungan server dan nomor pengajuan yang tidak berubah.'],
        ['✅', 'Persetujuan berjenjang', 'Kepala unit, admin keuangan, hingga wakil dekan, dengan pemisahan tugas dan catatan wajib saat dikembalikan.'],
        ['💸', 'Pencairan & SPJ', 'Pencairan bertahap, realisasi per item, dan SPJ yang batas waktunya dihitung dari pengaturan.'],
        ['🔗', 'Bukti berupa tautan', 'Kuitansi dan nota disimpan sebagai tautan Google Drive terbatas; tautan dibekukan saat SPJ diterima.'],
        ['🧾', 'Pajak sebagai informasi', 'Potongan pajak dicatat dari nilai input dan tarif acuan master; sistem tidak menghitung pajak otomatis.'],
        ['🔒', 'Kunci periode', 'Periode terkunci dan tahun ditutup tidak dapat diubah; buka kunci hanya oleh wakil dekan dengan alasan.'],
        ['📈', 'Dasbor & laporan', 'Serapan, sisa pagu, dan laporan realisasi dengan ekspor PDF dan Excel, konsisten dengan angka dasbor.'],
        ['🕘', 'Jejak audit', 'Setiap perubahan status tercatat: siapa, kapan, dari dan ke status apa, beserta catatannya.'],
    ];
    $alur = ['Pagu unit', 'Pengajuan & RAB', 'Persetujuan berjenjang', 'Pencairan', 'SPJ & realisasi', 'Pengembalian sisa dana', 'Kunci periode', 'Dasbor & laporan'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pencatatan dan pelaporan keuangan kegiatan Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi.">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/keuangan.css') }}">
</head>
<body>
<header class="k-atas">
    <div class="k-wadah">
        <a class="k-merek" href="{{ url('/') }}"><span class="k-logo">K</span> {{ config('app.name') }}</a>
        <nav>
            <a class="k-sembunyi" href="#fitur">Fitur</a>
            <a class="k-sembunyi" href="#alur">Alur</a>
            <a href="#panduan">Panduan</a>
            <a class="k-tombol" href="{{ route('filament.keuangan.auth.login') }}">Masuk</a>
        </nav>
    </div>
</header>

<section class="k-hero">
    <div class="k-wadah">
        <div>
            <span class="k-lencana">Fakultas Keguruan dan Ilmu Pendidikan · Universitas Siliwangi</span>
            <h1>Keuangan kegiatan fakultas, tercatat rapi dan terkendali.</h1>
            <p>Dari pagu unit, pengajuan dan RAB, persetujuan berjenjang, pencairan, hingga SPJ dan laporan, dalam satu aplikasi dengan jejak audit yang lengkap.</p>
            <div class="k-aksi">
                <a class="k-tombol" href="{{ route('filament.keuangan.auth.login') }}">Masuk ke aplikasi</a>
                <a class="k-tombol k-tombol--garis" href="#panduan">Lihat panduan pengguna</a>
            </div>
        </div>
        <div class="k-pratinjau">
            <img src="{{ asset('panduan/img/admin-keuangan/01-dasbor.png') }}" alt="Tampilan dasbor keuangan" loading="lazy">
        </div>
    </div>
</section>

<section class="k-bagian" id="fitur">
    <div class="k-wadah">
        <h2>Yang dikelola aplikasi ini</h2>
        <p class="k-sub">Seluruh siklus keuangan kegiatan fakultas, dengan aturan bisnis yang ditegakkan oleh sistem, bukan oleh ingatan pengguna.</p>
        <ul class="k-grid">
            @foreach ($fitur as [$ikon, $judul, $isi])
                <li class="k-kartu"><span class="k-ikon" aria-hidden="true">{{ $ikon }}</span><h3>{{ $judul }}</h3><p>{{ $isi }}</p></li>
            @endforeach
        </ul>
    </div>
</section>

<section class="k-bagian k-bagian--alt" id="alur">
    <div class="k-wadah">
        <h2>Alur kerja</h2>
        <p class="k-sub">Setiap tahap punya penanggung jawab dan status yang jelas.</p>
        <ol class="k-alur">
            @foreach ($alur as $langkah)
                <li>{{ $langkah }}</li>
            @endforeach
        </ol>
        <p class="k-catatan" style="margin-top:1.5rem">Bukti keuangan disimpan di Google Drive unit dan dibagikan terbatas; aplikasi hanya menyimpan tautannya. Honor ujian tidak dihitung di sini.</p>
    </div>
</section>

<section class="k-bagian" id="panduan">
    <div class="k-wadah">
        <h2>Panduan pengguna per peran</h2>
        <p class="k-sub">Panduan bergambar langkah demi langkah. Pilih sesuai peran Anda.</p>
        <ul class="k-grid">
            @foreach ($peran as [$slug, $nama, $isi])
                <li><a class="k-kartu k-peran" href="{{ asset("panduan/{$slug}.html") }}"><h3>{{ $nama }}</h3><p>{{ $isi }}</p><small>Buka panduan →</small></a></li>
            @endforeach
        </ul>
    </div>
</section>

<footer class="k-kaki">
    <div class="k-wadah">
        {{ config('app.name') }} · versi {{ config('app.version') }} · Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi
    </div>
</footer>
</body>
</html>
