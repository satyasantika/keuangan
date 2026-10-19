# Lembar hasil UAT — Keuangan FKIP Unsil

Lembar uji penerimaan pengguna (UAT). Dijalankan di server uji (`APP_ENV=staging`) dengan data
`UatSeeder` (`php artisan db:seed --class=UatSeeder --force`; isi `UAT_PASSWORD` dan `UAT_EMAILS` di `.env`).
Isi kolom Penguji, Tanggal, Hasil (**Lulus**/**Gagal**), dan Catatan; setiap Gagal dicatat di Daftar isu temuan.

Versi aplikasi diuji: ……  ·  Server uji: ……  ·  Periode UAT: …… s.d. ……


## 1.1 Admin Prodi/Unit (`admin-unit`, unit PMAT)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| AU-01 | Melihat sisa pagu | Login → Dasbor | Kartu pagu, komitmen, sisa PMAT 2027 tampil; tidak ada data unit lain | | | | |
| AU-02 | Menyusun draf RAB | Kegiatan → Pengajuan → Buat; isi kegiatan; tambah 3 item (mis. 2 OH × Rp150.000,00) | Jumlah per item dan total tampil; setelah simpan, total di daftar = Σ item | | | | |
| AU-03 | Total tidak dapat dimanipulasi | Ubah nilai total lewat DevTools lalu simpan | Total tersimpan tetap Σ item (BR-02) | | | | |
| AU-04 | Tautan TOR | Tab Tautan berkas → tempel link Google Drive TOR; coba `http://…`, `bit.ly/…`, link folder Drive, dan dropbox.com | Link Drive diterima (status cek terisi); keempat lainnya ditolak dengan pesan validasi | | | | |
| AU-04b | Tidak ada unggahan | Telusuri semua form (pengajuan, realisasi, pengembalian) | Tidak ada tombol/isian unggah berkas; hanya isian tautan | | | | |
| AU-05 | Mengajukan | Tombol Ajukan | Status Diajukan; nomor `PGJ/2027/xxxx` terbit; form tidak bisa diedit | | | | |
| AU-06 | Ditolak karena pagu | Buat pengajuan dengan total > sisa pagu → Ajukan | Ditolak; pesan menyebut sisa pagu dalam rupiah | | | | |
| AU-07 | Perbaikan setelah dikembalikan | Setelah dikembalikan penyetuju: ubah item → Ajukan ulang | Catatan penyetuju terlihat; nomor tetap sama | | | | |
| AU-08 | Tidak bisa mengakses unit lain | Ganti UUID di URL dengan pengajuan PBIO | 403/404 | | | | |
| AU-09 | Realisasi dengan tautan nota dari ponsel | Simpan foto nota di Shared Drive unit → Bagikan: Terbatas/Universitas Siliwangi → Salin link → tempel di Tambah realisasi (jenis kuitansi, centang akses terbatas) | Tautan tersimpan; jumlah bruto dihitung; sisa item berkurang; pratinjau tampil | | | | |
| AU-09b | Bukti keuangan tanpa konfirmasi akses terbatas | Tempel link kuitansi tanpa centang konfirmasi | Ditolak: "Bukti keuangan memuat NPWP/rekening — bagikan terbatas…" | | | | |
| AU-09c | Bukti dibagikan publik | Simpan link kuitansi yang diatur "Siapa saja yang memiliki link" | Tersimpan, lalu lencana "Akses publik" + notifikasi meminta pembatasan | | | | |
| AU-10 | Realisasi melebihi item | Isi realisasi melebihi jumlah item RAB | Ditolak dengan pesan sisa per item (BR-14) | | | | |
| AU-11 | Pajak sebagai informasi | Tambah potongan PPh 23 dengan nilai manual | Nilai tersimpan persis seperti diketik; tarif referensi tampil berlabel "perlu verifikasi" | | | | |
| AU-12 | Ajukan SPJ | Tombol Ajukan SPJ tanpa, lalu dengan, centang pernyataan berkas final | Tanpa centang ditolak; dengan centang: nomor `SPJ/2027/xxxx`, pernyataan tercatat (nama & waktu), realisasi terkunci | | | | |
| AU-13 | Setor sisa dana | Setelah SPJ diterima, Catat pengembalian sisa (jumlah = sisa) + tautan bukti setor (akses terbatas) | Status pengembalian Diajukan | | | | |
| AU-14 | Revisi RAB | Pada pengajuan Dicairkan → Buat revisi → ubah item → Ajukan revisi | Revisi ke-1 Draf → Diajukan; RAB awal tetap utuh | | | | |
| AU-15 | Transaksi di periode terkunci | Catat realisasi bertanggal bulan yang sudah dikunci | Ditolak "Periode … terkunci" | | | | |
| AU-16 | Pengingat SPJ | Jalankan `keuangan:pengingat-spj` pada tanggal H-3 (staging) | Notifikasi lonceng + surel diterima | | | | |

## 1.2 Ketua Prodi/Kepala Unit (`kepala-unit`, PMAT)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| KU-01 | Antrean verifikasi | Beranda → Menunggu tindakan | Hanya pengajuan PMAT berstatus Diajukan | | | | |
| KU-02 | Verifikasi | Buka pengajuan → Verifikasi | Status Diverifikasi Kepala Unit; riwayat mencatat nama & waktu | | | | |
| KU-03 | Kembalikan tanpa catatan | Kembalikan dengan catatan kosong | Validasi gagal | | | | |
| KU-04 | Pemisahan tugas | Kepala unit yang juga membuat pengajuan membuka pengajuannya | Tombol Verifikasi tidak ada; request langsung ditolak | | | | |
| KU-05 | Dasbor unit | Dasbor | Serapan PMAT saja | | | | |

## 1.3 Admin Keuangan Fakultas (`admin-keuangan`)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| AK-01 | MFA (bila `MFA_WAJIB=true`) | Login pertama | Diminta mengaktifkan autentikator sebelum memakai panel; bila tidak diwajibkan, langsung masuk | | | | |
| AK-02 | Tahun anggaran | Anggaran → Tahun anggaran → Buat 2027 | 12 periode Terbuka terbentuk | | | | |
| AK-03 | Impor akun BAS | Master → Akun → Impor (berkas resmi) | Akun dibuat/diperbarui; baris tidak valid dilaporkan; lencana CONTOH hilang untuk akun resmi | | | | |
| AK-04 | Pagu | Buat pagu PMAT + rincian akun > pagu | Ditolak; setelah benar, status Draf menunggu penetapan | | | | |
| AK-05 | Verifikasi keuangan | Pengajuan Diverifikasi Kepala Unit → Verifikasi | Status Diverifikasi Keuangan | | | | |
| AK-06 | Dobel klik | Klik Verifikasi dua kali cepat / dua tab bersamaan | Hanya satu riwayat; tab kedua mendapat "Sedang diproses" atau "sudah diproses" | | | | |
| AK-07 | Tolak | Tolak dengan catatan | Status Ditolak; pembuat menerima notifikasi | | | | |
| AK-08 | Verifikasi SPJ dengan bukti ganda | Buka SPJ berlencana "Kemungkinan bukti ganda" | Tautan ke realisasi lain dengan `drive_file_id` sama tampil; pratinjau Drive bisa dibuka dengan akun unsil.ac.id | | | | |
| AK-08b | Pembekuan tautan | Terima SPJ → buka tab "Tautan dibekukan"; coba ubah tautan realisasi | Snapshot URL + waktu penerimaan tampil untuk semua bukti, pencairan, dan TOR; perubahan tautan ditolak | | | | |
| AK-09 | Kunci periode | Anggaran → Periode Januari → Kunci (ada SPJ belum diterima) | Peringatan pra-cek tampil; setelah dikonfirmasi status Terkunci, snapshot tersedia | | | | |
| AK-10 | Pengaturan | Ubah batas hari SPJ 14 → 30 | Pengajuan yang dicairkan sesudahnya memakai 30; yang lama tidak berubah | | | | |
| AK-11 | Ekspor LRA | Laporan → LAP-01 bulanan (kecil) lalu tahunan semua unit → Excel | Bulanan langsung terunduh (stream); tahunan lewat antrean, notifikasi "siap diunduh", tautan berlaku ≤ 24 jam; angka sama dengan dasbor | | | | |
| AK-12 | Tidak dapat membuka kunci | Coba Buka kunci periode | Tombol tidak tersedia | | | | |

## 1.4 Bendahara Pengeluaran Pembantu (`bpp`)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| BP-01 | Catat pencairan | Pengajuan Disetujui → Catat pencairan (jenis, tanggal, jumlah, tautan bukti transfer dengan akses terbatas) | Status Dicairkan; batas SPJ terisi | | | | |
| BP-02 | Pencairan melebihi RAB | Jumlah > total RAB aktif − sudah dicairkan | Ditolak (BR-12) | | | | |
| BP-03 | Batalkan pencairan | Batalkan dengan alasan | Status pencairan Dibatalkan; tidak ada tombol Hapus | | | | |
| BP-04 | Verifikasi pengembalian | Pengembalian Diajukan → Verifikasi | Pengajuan Selesai bila sisa = setoran | | | | |
| BP-05 | Rekap pajak | Laporan → LAP-05 bulan berjalan | Rekap per jenis pajak & unit dengan catatan "data informasi" | | | | |
| BP-06 | Pemisahan tugas | BPP yang juga penyetuju akhir pengajuan X mencoba mencatat pencairan X | Ditolak (BR-07c) | | | | |

## 1.5 Wakil Dekan bidang keuangan / PPK (`wadek-keuangan`)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| WD-01 | Tetapkan pagu | Pagu Draf → Tetapkan | Status Ditetapkan; unit dapat mengajukan | | | | |
| WD-02 | Persetujuan akhir dari ponsel | Menunggu tindakan → Setujui (lebar layar ponsel) | Status Disetujui; BPP & unit menerima notifikasi | | | | |
| WD-03 | Pagu habis saat persetujuan | Setujui pengajuan yang kini melebihi sisa pagu | Ditolak dengan pesan sisa pagu | | | | |
| WD-04 | Revisi RAB | Setujui revisi ke-1 | Versi baru aktif; versi lama Digantikan; realisasi tetap terhubung | | | | |
| WD-05 | Buka kunci periode | Buka kunci Februari dengan alasan < 20 karakter, lalu alasan lengkap | Pertama ditolak; kedua berhasil, tercatat di riwayat | | | | |
| WD-06 | Ubah pagu ditetapkan | Turunkan pagu di bawah komitmen | Ditolak | | | | |

## 1.6 Pimpinan/Dekanat (`pimpinan`)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| PM-01 | Dasbor fakultas | Dasbor; filter semester 1 | Serapan per unit, tren bulanan, SPJ jatuh tempo | | | | |
| PM-02 | Baca-saja | Buka pengajuan mana pun | Tidak ada tombol ubah/setujui | | | | |
| PM-03 | Ekspor | Laporan → LAP-01 PDF | PDF berkop fakultas dengan tanggal cetak & nama pencetak | | | | |

## 1.7 Admin TI (`super-admin`) dan publik
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| SA-01 | Kelola pengguna | Buat admin-unit tanpa unit | Validasi "Unit wajib diisi…" | | | | |
| SA-02 | Kesehatan antrean | Buka `/api/health` | `status` ok; `job_tertunda` 0, `detak_penjadwal` ok | | | | |
| SA-03 | Tidak bisa menyetujui | Buka pengajuan Diverifikasi Keuangan | Tidak ada tombol Setujui | | | | |
| SA-04 | Reset MFA | Reset MFA pengguna lain | Pengguna diminta mengaktifkan ulang; tercatat di log | | | | |
| SA-05 | Tautan mati | Ubah berbagi/hapus berkas uji di Drive, jalankan `keuangan:periksa-ulang-tautan` | Status cek "tidak dapat diakses" + notifikasi ke penambah tautan; tautan SPJ diterima tidak diperiksa ulang | | | | |
| SA-06 | Berkas sementara | Periksa `storage/app/tmp` setelah 25 jam | Berkas ekspor/impor > 24 jam sudah terhapus | | | | |
| PB-01 | Verifikasi QR | Pindai QR pada PDF pengajuan | Halaman publik menampilkan nomor, unit, judul, status, total disetujui — tanpa rincian item | | | | |
| PB-02 | Kesehatan | `GET /api/health` | `{"status":"ok",...}` | | | | |

## 1.8 Skenario lintas peran (alur lengkap)
| No | Skenario | Langkah | Hasil diharapkan | Penguji | Tanggal | Hasil (Lulus/Gagal) | Catatan |
|---|---|---|---|---|---|---|---|
| AL-01 | Pagu ditetapkan → draf → ajukan → verifikasi unit → verifikasi keuangan → setujui → pencairan → realisasi + pajak → SPJ → terima → pengembalian → verifikasi | Status akhir Selesai; linimasa ≥ 10 baris; `keuangan:rekonsiliasi` tanpa selisih |  | | | | |
| AL-02 | Pengaturan `wajib_verifikasi_kepala_unit=false` lalu alur baru | Tahap kepala unit dilewati hanya untuk pengajuan yang diajukan setelah perubahan |  | | | | |
| AL-03 | Impor rekap honor fkipapp (bila ADR 0001 memilihnya) | Baris "Honor ujian (impor fkipapp)" terpisah di dasbor; sisa pagu tidak berubah (bawaan) |  | | | | |


## Daftar isu temuan

| No | Skenario | Uraian isu | Tingkat (kritis/sedang/ringan) | Pelapor | Tanggal | Tindak lanjut (branch `fix/…`/PR) | Status |
|---|---|---|---|---|---|---|---|
| 1 | | | | | | | |

## Berita acara UAT

UAT aplikasi Keuangan FKIP Unsil versi …… telah dilaksanakan pada …… oleh perwakilan setiap peran. Seluruh
skenario kritis berstatus Lulus atau isunya telah diperbaiki.

| Peran | Nama | Tanda tangan | Tanggal |
|---|---|---|---|
| Admin Keuangan Fakultas | | | |
| Wakil Dekan bidang keuangan | | | |
