# 0001 — Batas cakupan Sistem Keuangan FKIP

- Status: **Diterima (sementara)** — dipilih oleh pemilik produk/pengembang agar pembangunan dapat berjalan; menunggu pengesahan resmi pihak keuangan (lihat "Tindak lanjut")
- Tanggal: 2026-10-07
- Pengambil keputusan: pemilik produk (pengembang) — pengesahan Admin Keuangan Fakultas/Wakil Dekan: (kosong)

## Konteks

- Sistem baru mencakup siklus pagu → pengajuan + RAB → persetujuan → pencairan → SPJ → pengembalian → laporan (PRD §1, §9).
- `fkipapp` sudah menangani honor ujian (proposal, seminar, sidang).
- Portal Support System FKIP sudah menautkan aplikasi "Laporan Keuangan" (`/laporankeuangan/`) yang kodenya belum kita miliki, sehingga fungsinya belum dapat diinventarisasi.

## Inventaris aplikasi laporankeuangan

Diisi manusia setelah akses baca diperoleh.

| Fitur | Pengguna | Data masukan | Keluaran/laporan | Dipakai siapa | Tumpang tindih dengan modul sistem baru (kode modul PRD) |
|---|---|---|---|---|---|
| (kosong) | | | | | |

## Opsi

### Opsi (a) — Sistem baru menggantikan laporankeuangan

- Keuntungan: satu sumber data; tidak ada input ganda; jejak audit utuh.
- Risiko: fungsi lama yang belum terinventarisasi bisa hilang; laporan ke universitas yang sudah berjalan terganggu; kodenya tidak kita miliki sehingga migrasi sulit.
- Kebutuhan migrasi data: saldo/rekap tahun anggaran berjalan, daftar unit, pagu; periode paralel (mis. satu bulan input ganda); pengalihan tautan portal; pengarsipan aplikasi lama baca-saja.
- Dampak ke rencana: langkah F12.4 memuat migrasi data.

### Opsi (b) — Sistem baru melengkapi laporankeuangan

`laporankeuangan` tetap dipakai untuk fungsi yang sudah dimilikinya (mis. laporan ke universitas); sistem ini fokus pada siklus kegiatan (RAB → SPJ).

| Angka | Sumber kebenaran | Cara sinkron |
|---|---|---|
| Pagu unit per tahun anggaran | Sistem baru (keuangan) setelah ditetapkan; penetapan awal dari DIPA/RKA | Input manual oleh Admin Keuangan; ekspor Excel dari sistem baru |
| Pengajuan, RAB, persetujuan, pencairan, SPJ/realisasi per kegiatan | Sistem baru | Ekspor Excel realisasi per periode (F11) untuk dibaca `laporankeuangan` |
| Pengembalian sisa dana, pajak (informasi) | Sistem baru | Ekspor Excel |
| Laporan resmi ke universitas/pihak luar | `laporankeuangan` (sementara) | Admin Keuangan menyalin dari ekspor sistem baru; otomatisasi di luar cakupan |
| Honor ujian | `fkipapp` | Opsional impor rekap total (lihat bawah) |

- Format ekspor Excel dari sistem baru dirancang agar dapat dibuka/dimasukkan ke `laporankeuangan` (kolom stabil, tanpa rumus tersembunyi).
- Portal memiliki dua tautan berbeda dengan label jelas: "Keuangan Kegiatan (RAB–SPJ)" dan "Laporan Keuangan".

## Honor ujian (fkipapp)

Sistem ini **TIDAK menghitung honor ujian** (BR-24). Pilihan yang diambil: **(2) impor rekap total per bulan × prodi × jenis ujian dari Excel fkipapp** (langkah F11.5), baca-saja, ditampilkan di dasbor/laporan. Rekap itu **tidak mengurangi pagu unit** (`honor_mengurangi_pagu=false`, bawaan). Bila fkipapp tidak dapat mengekspor rekap, F11.5 dilewati tanpa mengubah bagian lain. Perlu konfirmasi pengembang `fkipapp` atas ketersediaan ekspor (kolom bruto, pajak, neto).

## Kriteria keputusan

- [x] Kelengkapan fungsi: fungsi `laporankeuangan` belum diketahui → memilih tidak menggantikan sebelum inventaris.
- [x] Beban input ganda: dihindari dengan pembagian sumber kebenaran dan ekspor Excel.
- [x] Kebutuhan pelaporan ke universitas: tetap terlayani `laporankeuangan`.
- [x] Kapasitas pemeliharaan TI fakultas: satu aplikasi tambahan, tanpa memikul fungsi yang tidak dikenal.
- [x] Risiko data historis: nol pada opsi (b) (tidak ada migrasi).

## Keputusan

**Opsi (b): sistem baru melengkapi `laporankeuangan`.** Alasan: kode dan fungsi `laporankeuangan` belum diketahui sehingga menggantikannya berisiko; desain inti tidak berubah dan opsi (a) dapat dipilih kelak tanpa membongkar sistem. Honor ujian: opsi (2), impor rekap baca-saja yang tidak mengurangi pagu.

### Konsekuensi

- F12.4 (migrasi data historis) tidak dikerjakan; diganti pemeriksaan format ekspor Excel.
- F11.5 (impor rekap honor) tetap dikerjakan, bergantung pada ekspor fkipapp.
- Portal menampilkan dua tautan terpisah.
- Bila kelak opsi (a) dipilih, buat ADR baru yang menggantikan keputusan ini.

### Tindak lanjut

1. Minta akses baca ke `/laporankeuangan/` dan isi tabel inventaris.
2. Wawancara Admin Keuangan, BPP, Wakil Dekan bidang keuangan, pengelola `laporankeuangan`, pengembang `fkipapp`.
3. Tanyakan ke pengembang `fkipapp` ketersediaan ekspor Excel rekap honor ujian.
4. Sahkan keputusan ini (nama, tanggal, paraf) atau ubah ke opsi (a).

## Peserta rapat

| Nama | Jabatan | Paraf |
|---|---|---|
| (kosong) | | |
