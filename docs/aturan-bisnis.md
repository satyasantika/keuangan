# Aturan bisnis — Keuangan FKIP Unsil

Ringkasan aturan yang ditegakkan sistem (kode `BR-xx` dirujuk di komentar kode dan nama test).

## Uang dan perhitungan
- **BR-01** Uang `DECIMAL(15,2)`, tanpa float, pembulatan HALF_UP per item.
- **BR-02** `jumlah = volume × harga_satuan` dan total RAB = Σ item dihitung server; total dari klien diabaikan.
- **BR-16** Pajak dicatat sebagai informasi; nilai diinput manual, tarif acuan disalin dari master. Sistem tidak menghitung pajak otomatis.
- **BR-26** Kolom `total_*` pengajuan dihitung ulang server; `keuangan:rekonsiliasi` memeriksa konsistensinya.

## Pengajuan, pagu, dan persetujuan
- **BR-03** Pengajuan hanya diubah saat `draf`/`dikembalikan`; sejak disetujui hanya lewat revisi bernomor.
- **BR-04** Total pengajuan ≤ sisa pagu unit saat diajukan dan dicek ulang saat disetujui; pagu harus berstatus ditetapkan.
- **BR-05** Nomor `PGJ/{tahun}/{urut}` terbit saat diajukan di bawah kunci Redis + `lockForUpdate`, dan tidak berubah.
- **BR-06** Jenjang: kepala unit (opsional) → admin keuangan → wadek keuangan (opsional); snapshot pengaturan saat diajukan; kembalikan/tolak wajib catatan.
- **BR-07** Pemisahan tugas: pembuat ≠ penyetuju; satu orang satu tahap; pencatat pencairan ≠ penyetuju akhir; verifikator SPJ/pengembalian ≠ pembuatnya.
- **BR-08** Setiap perubahan status tercatat di `riwayat_status` (siapa, kapan, dari→ke, catatan, tahap) dalam transaksi yang sama.
- **BR-09** Aksi status dibungkus kunci Redis per record (anti klik ganda).
- **BR-10/11** Revisi RAB bernomor, satu yang berjalan, versi lama `digantikan`; item revisi ≥ realisasi item asal; realisasi dipetakan ke item baru.

## Pencairan, SPJ, dan pengembalian
- **BR-12** Σ pencairan ≤ total RAB aktif; bukti wajib.
- **BR-13** `batas_spj = max(selesai kegiatan, pencairan pertama) + batas_hari_spj` (pengaturan), disimpan saat pencairan pertama.
- **BR-14** Σ realisasi per item ≤ jumlah item RAB aktif; Σ per pengajuan ≤ total RAB aktif.
- **BR-15** Bukti berupa tautan tervalidasi (https, daftar putih domain, bukan pemendek/folder); kuitansi/nota/faktur wajib; dibagikan terbatas domain unsil.ac.id; `drive_file_id` yang sama di pengajuan lain menandakan bukti ganda.
- **BR-17** SPJ `draf → diajukan → diterima/dikembalikan`; pengajuan wajib pernyataan berkas final; saat diterima tautan dibekukan (URL + waktu) lalu terkunci.
- **BR-18** Sisa dana = dicairkan − realisasi; selesai bila SPJ diterima dan sisa nol/sudah disetor terverifikasi.

## Periode, akses, dan data
- **BR-19/20** Periode terkunci dan tahun ditutup tidak dapat ditulis; buka kunci hanya oleh wadek keuangan dengan alasan.
- **BR-21** Pengguna unit hanya melihat unitnya; URL memakai UUID.
- **BR-22/23** Transaksi final tidak dihapus, hanya dibatalkan beralasan; pembatalan pengajuan hanya bila tanpa pencairan aktif.
- **BR-24** Honor ujian tidak dihitung di sini; rekap dari aplikasi lain bersifat baca-saja dan tidak mengurangi pagu kecuali pengaturan.
- **BR-25** Pola pengelolaan (satker/BLU) hanya konfigurasi; tidak ada percabangan logika inti.

## Akun dan sandi
- Kata sandi dapat diganti pengguna sendiri; penggantian memutus sesi perangkat lain.
- Super-admin dapat mewajibkan ganti kata sandi per akun; akun bertanda hanya dapat membuka halaman profil sampai sandinya diganti.
- MFA (autentikator) tersedia sukarela; kewajibannya bagi peran keuangan diatur `MFA_WAJIB`.
- Impersonasi darurat hanya oleh super-admin terhadap akun aktif lain; tidak bertingkat, tercatat (mulai, akhir, dan penanda pada setiap entri audit), dan tidak mengizinkan perubahan profil/kata sandi sasaran. Pemisahan tugas BR-07 tetap berlaku bagi akun yang disamarkan.
