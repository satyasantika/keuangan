# Panduan Admin Keuangan Fakultas (`admin-keuangan`)

Tugas: menyiapkan tahun anggaran & pagu, memverifikasi pengajuan (tahap keuangan), memverifikasi SPJ dan
pengembalian, mengunci periode, dan menyusun laporan. Wajib MFA.

## 1. Tahun anggaran & master
1. **Anggaran → Tahun Anggaran → Buat**: isi tahun → 12 periode bulanan terbentuk (Terbuka). Ubah status ke
   **Berjalan** saat mulai dipakai.
2. **Master → Unit/Prodi**, **Akun BAS** (tombol **Impor** untuk berkas CSV resmi), **Jenis Pencairan**,
   **Jenis & Tarif Pajak**, **Kategori Penggunaan Dana**. Data berlabel *contoh*/*perlu verifikasi* diganti data resmi.
3. **Sistem → Pengaturan**: batas hari SPJ, jenjang persetujuan, dll.

## 2. Pagu unit
1. **Anggaran → Pagu Unit → Buat**: unit, tahun, jumlah; rincian per akun (opsional) tidak boleh melebihi pagu.
2. Pagu berstatus Draf sampai **ditetapkan** Wadek.

## 3. Verifikasi pengajuan
1. **Beranda → Menunggu tindakan** → buka pengajuan berstatus *Diverifikasi Kepala Unit*.
2. Periksa RAB dan TOR → **Verifikasi**, **Kembalikan** (catatan wajib), atau **Tolak** (catatan wajib).
3. Revisi RAB: **Kegiatan → Revisi RAB** → **Bandingkan** → **Verifikasi**/**Kembalikan**/**Tolak**.

## 4. Verifikasi SPJ & pengembalian
1. **Kegiatan → SPJ & Realisasi** → SPJ berstatus Diajukan → periksa realisasi dan bukti (pratinjau Drive dengan akun
   unsil.ac.id). Lencana *Kemungkinan bukti ganda* menandai berkas Drive yang sama di pengajuan lain.
2. **Terima** (tautan dibekukan; tab **Tautan dibekukan** menampilkan snapshot URL + waktu) atau **Kembalikan**
   (catatan wajib).
3. **Bendahara → Pengembalian** → **Verifikasi** atau **Tolak** setoran sisa dana.

## 5. Mengunci periode
1. **Anggaran → Tahun Anggaran** → buka tahun → tab **Periode** → **Kunci** pada bulan tertua yang masih terbuka.
2. Baca peringatan pra-cek (SPJ belum diterima, pengembalian menunggu), centang **Saya memahami peringatan**.
3. Snapshot angka tersimpan (**Lihat snapshot**). Membuka kunci hanya oleh Wadek.

## 6. Laporan & honor ujian
1. **Laporan → Laporan**: pilih jenis (LAP-01 … LAP-09), tahun, periode, unit → pratinjau → unduh Excel/PDF.
   Laporan besar diproses di antrean; tautan unduh muncul di notifikasi dan **Laporan → Riwayat Ekspor** (≤ 24 jam).
2. **Laporan → Rekap Honor Ujian → Impor rekap honor**: unggah berkas Excel ekspor fkipapp (rekap total).
   Hasil impor dikirim lewat notifikasi; **Batalkan batch** bila salah (sebelum periodenya dikunci).

## Tanya-jawab
- *Tombol Verifikasi tidak muncul?* Anda membuat pengajuan itu atau sudah bertindak di tahap lain (pemisahan tugas).
- *"Sedang diproses, coba lagi."* Aksi yang sama sedang berjalan di tab/pengguna lain.
