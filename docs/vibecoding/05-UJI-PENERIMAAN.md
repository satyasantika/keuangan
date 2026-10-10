# 05 — Uji Penerimaan (UAT), Daftar Test, Keamanan, dan Go-Live
# Sistem Keuangan Fakultas FKIP Unsil

Dipakai pada F12.5 (UAT di server uji) dan F12.7 (go-live). Data uji: `UatSeeder` di lingkungan `staging`. Contoh angka memakai tahun anggaran 2027, unit PMAT dan PBIO. Kolom ✓ diisi penguji (✓ lulus / ✗ gagal + nomor isu).

## 1. Skenario UAT per peran

### 1.1 Admin Prodi/Unit (`admin-unit`, unit PMAT)
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| AU-01 | Melihat sisa pagu | Login → Dasbor | Kartu pagu, komitmen, sisa PMAT 2027 tampil; tidak ada data unit lain | |
| AU-02 | Menyusun draf RAB | Kegiatan → Pengajuan → Buat; isi kegiatan; tambah 3 item (mis. 2 OH × Rp150.000,00) | Jumlah per item dan total tampil; setelah simpan, total di daftar = Σ item | |
| AU-03 | Total tidak dapat dimanipulasi | Ubah nilai total lewat DevTools lalu simpan | Total tersimpan tetap Σ item (BR-02) | |
| AU-04 | Tautan TOR | Tab Tautan berkas → tempel link Google Drive TOR; coba `http://…`, `bit.ly/…`, link folder Drive, dan dropbox.com | Link Drive diterima (status cek terisi); keempat lainnya ditolak dengan pesan validasi | |
| AU-04b | Tidak ada unggahan | Telusuri semua form (pengajuan, realisasi, pengembalian) | Tidak ada tombol/isian unggah berkas; hanya isian tautan | |
| AU-05 | Mengajukan | Tombol Ajukan | Status Diajukan; nomor `PGJ/2027/xxxx` terbit; form tidak bisa diedit | |
| AU-06 | Ditolak karena pagu | Buat pengajuan dengan total > sisa pagu → Ajukan | Ditolak; pesan menyebut sisa pagu dalam rupiah | |
| AU-07 | Perbaikan setelah dikembalikan | Setelah dikembalikan penyetuju: ubah item → Ajukan ulang | Catatan penyetuju terlihat; nomor tetap sama | |
| AU-08 | Tidak bisa mengakses unit lain | Ganti UUID di URL dengan pengajuan PBIO | 403/404 | |
| AU-09 | Realisasi dengan tautan nota dari ponsel | Simpan foto nota di Shared Drive unit → Bagikan: Terbatas/Universitas Siliwangi → Salin link → tempel di Tambah realisasi (jenis kuitansi, centang akses terbatas) | Tautan tersimpan; jumlah bruto dihitung; sisa item berkurang; pratinjau tampil | |
| AU-09b | Bukti keuangan tanpa konfirmasi akses terbatas | Tempel link kuitansi tanpa centang konfirmasi | Ditolak: "Bukti keuangan memuat NPWP/rekening — bagikan terbatas…" | |
| AU-09c | Bukti dibagikan publik | Simpan link kuitansi yang diatur "Siapa saja yang memiliki link" | Tersimpan, lalu lencana "Akses publik" + notifikasi meminta pembatasan | |
| AU-10 | Realisasi melebihi item | Isi realisasi melebihi jumlah item RAB | Ditolak dengan pesan sisa per item (BR-14) | |
| AU-11 | Pajak sebagai informasi | Tambah potongan PPh 23 dengan nilai manual | Nilai tersimpan persis seperti diketik; tarif referensi tampil berlabel "perlu verifikasi" | |
| AU-12 | Ajukan SPJ | Tombol Ajukan SPJ tanpa, lalu dengan, centang pernyataan berkas final | Tanpa centang ditolak; dengan centang: nomor `SPJ/2027/xxxx`, pernyataan tercatat (nama & waktu), realisasi terkunci | |
| AU-13 | Setor sisa dana | Setelah SPJ diterima, Catat pengembalian sisa (jumlah = sisa) + tautan bukti setor (akses terbatas) | Status pengembalian Diajukan | |
| AU-14 | Revisi RAB | Pada pengajuan Dicairkan → Buat revisi → ubah item → Ajukan revisi | Revisi ke-1 Draf → Diajukan; RAB awal tetap utuh | |
| AU-15 | Transaksi di periode terkunci | Catat realisasi bertanggal bulan yang sudah dikunci | Ditolak "Periode … terkunci" | |
| AU-16 | Pengingat SPJ | Jalankan `keuangan:pengingat-spj` pada tanggal H-3 (staging) | Notifikasi lonceng + surel diterima | |

### 1.2 Ketua Prodi/Kepala Unit (`kepala-unit`, PMAT)
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| KU-01 | Antrean verifikasi | Beranda → Menunggu tindakan | Hanya pengajuan PMAT berstatus Diajukan | |
| KU-02 | Verifikasi | Buka pengajuan → Verifikasi | Status Diverifikasi Kepala Unit; riwayat mencatat nama & waktu | |
| KU-03 | Kembalikan tanpa catatan | Kembalikan dengan catatan kosong | Validasi gagal | |
| KU-04 | Pemisahan tugas | Kepala unit yang juga membuat pengajuan membuka pengajuannya | Tombol Verifikasi tidak ada; request langsung ditolak | |
| KU-05 | Dasbor unit | Dasbor | Serapan PMAT saja | |

### 1.3 Admin Keuangan Fakultas (`admin-keuangan`)
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| AK-01 | MFA wajib | Login pertama | Diminta mengaktifkan autentikator sebelum memakai panel | |
| AK-02 | Tahun anggaran | Anggaran → Tahun anggaran → Buat 2027 | 12 periode Terbuka terbentuk | |
| AK-03 | Impor akun BAS | Master → Akun → Impor (berkas resmi) | Akun dibuat/diperbarui; baris tidak valid dilaporkan; lencana CONTOH hilang untuk akun resmi | |
| AK-04 | Pagu | Buat pagu PMAT + rincian akun > pagu | Ditolak; setelah benar, status Draf menunggu penetapan | |
| AK-05 | Verifikasi keuangan | Pengajuan Diverifikasi Kepala Unit → Verifikasi | Status Diverifikasi Keuangan | |
| AK-06 | Dobel klik | Klik Verifikasi dua kali cepat / dua tab bersamaan | Hanya satu riwayat; tab kedua mendapat "Sedang diproses" atau "sudah diproses" | |
| AK-07 | Tolak | Tolak dengan catatan | Status Ditolak; pembuat menerima notifikasi | |
| AK-08 | Verifikasi SPJ dengan bukti ganda | Buka SPJ berlencana "Kemungkinan bukti ganda" | Tautan ke realisasi lain dengan `drive_file_id` sama tampil; pratinjau Drive bisa dibuka dengan akun unsil.ac.id | |
| AK-08b | Pembekuan tautan | Terima SPJ → buka tab "Tautan dibekukan"; coba ubah tautan realisasi | Snapshot URL + waktu penerimaan tampil untuk semua bukti, pencairan, dan TOR; perubahan tautan ditolak | |
| AK-09 | Kunci periode | Anggaran → Periode Januari → Kunci (ada SPJ belum diterima) | Peringatan pra-cek tampil; setelah dikonfirmasi status Terkunci, snapshot tersedia | |
| AK-10 | Pengaturan | Ubah batas hari SPJ 14 → 30 | Pengajuan yang dicairkan sesudahnya memakai 30; yang lama tidak berubah | |
| AK-11 | Ekspor LRA | Laporan → LAP-01 bulanan (kecil) lalu tahunan semua unit → Excel | Bulanan langsung terunduh (stream); tahunan lewat antrean, notifikasi "siap diunduh", tautan berlaku ≤ 24 jam; angka sama dengan dasbor | |
| AK-12 | Tidak dapat membuka kunci | Coba Buka kunci periode | Tombol tidak tersedia | |

### 1.4 Bendahara Pengeluaran Pembantu (`bpp`)
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| BP-01 | Catat pencairan | Pengajuan Disetujui → Catat pencairan (jenis, tanggal, jumlah, tautan bukti transfer dengan akses terbatas) | Status Dicairkan; batas SPJ terisi | |
| BP-02 | Pencairan melebihi RAB | Jumlah > total RAB aktif − sudah dicairkan | Ditolak (BR-12) | |
| BP-03 | Batalkan pencairan | Batalkan dengan alasan | Status pencairan Dibatalkan; tidak ada tombol Hapus | |
| BP-04 | Verifikasi pengembalian | Pengembalian Diajukan → Verifikasi | Pengajuan Selesai bila sisa = setoran | |
| BP-05 | Rekap pajak | Laporan → LAP-05 bulan berjalan | Rekap per jenis pajak & unit dengan catatan "data informasi" | |
| BP-06 | Pemisahan tugas | BPP yang juga penyetuju akhir pengajuan X mencoba mencatat pencairan X | Ditolak (BR-07c) | |

### 1.5 Wakil Dekan bidang keuangan / PPK (`wadek-keuangan`)
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| WD-01 | Tetapkan pagu | Pagu Draf → Tetapkan | Status Ditetapkan; unit dapat mengajukan | |
| WD-02 | Persetujuan akhir dari ponsel | Menunggu tindakan → Setujui (lebar layar ponsel) | Status Disetujui; BPP & unit menerima notifikasi | |
| WD-03 | Pagu habis saat persetujuan | Setujui pengajuan yang kini melebihi sisa pagu | Ditolak dengan pesan sisa pagu | |
| WD-04 | Revisi RAB | Setujui revisi ke-1 | Versi baru aktif; versi lama Digantikan; realisasi tetap terhubung | |
| WD-05 | Buka kunci periode | Buka kunci Februari dengan alasan < 20 karakter, lalu alasan lengkap | Pertama ditolak; kedua berhasil, tercatat di riwayat | |
| WD-06 | Ubah pagu ditetapkan | Turunkan pagu di bawah komitmen | Ditolak | |

### 1.6 Pimpinan/Dekanat (`pimpinan`)
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| PM-01 | Dasbor fakultas | Dasbor; filter semester 1 | Serapan per unit, tren bulanan, SPJ jatuh tempo | |
| PM-02 | Baca-saja | Buka pengajuan mana pun | Tidak ada tombol ubah/setujui | |
| PM-03 | Ekspor | Laporan → LAP-01 PDF | PDF berkop fakultas dengan tanggal cetak & nama pencetak | |

### 1.7 Admin TI (`super-admin`) dan publik
| No | Skenario | Langkah | Hasil diharapkan | ✓ |
|---|---|---|---|---|
| SA-01 | Kelola pengguna | Buat admin-unit tanpa unit | Validasi "Unit wajib diisi…" | |
| SA-02 | Horizon | Buka `/horizon` | Terbuka untuk super-admin; 403 untuk peran lain | |
| SA-03 | Tidak bisa menyetujui | Buka pengajuan Diverifikasi Keuangan | Tidak ada tombol Setujui | |
| SA-04 | Reset MFA | Reset MFA pengguna lain | Pengguna diminta mengaktifkan ulang; tercatat di log | |
| SA-05 | Tautan mati | Ubah berbagi/hapus berkas uji di Drive, jalankan `keuangan:periksa-ulang-tautan` | Status cek "tidak dapat diakses" + notifikasi ke penambah tautan; tautan SPJ diterima tidak diperiksa ulang | |
| SA-06 | Berkas sementara | Periksa `storage/app/tmp` setelah 25 jam | Berkas ekspor/impor > 24 jam sudah terhapus | |
| PB-01 | Verifikasi QR | Pindai QR pada PDF pengajuan | Halaman publik menampilkan nomor, unit, judul, status, total disetujui — tanpa rincian item | |
| PB-02 | Kesehatan | `GET /api/health` | `{"status":"ok",...}` | |

### 1.8 Skenario lintas peran (alur lengkap)
| No | Skenario | Hasil diharapkan | ✓ |
|---|---|---|---|
| AL-01 | Pagu ditetapkan → draf → ajukan → verifikasi unit → verifikasi keuangan → setujui → pencairan → realisasi + pajak → SPJ → terima → pengembalian → verifikasi | Status akhir Selesai; linimasa ≥ 10 baris; `keuangan:rekonsiliasi` tanpa selisih | |
| AL-02 | Pengaturan `wajib_verifikasi_kepala_unit=false` lalu alur baru | Tahap kepala unit dilewati hanya untuk pengajuan yang diajukan setelah perubahan | |
| AL-03 | Impor rekap honor fkipapp (bila ADR 0001 memilihnya) | Baris "Honor ujian (impor fkipapp)" terpisah di dasbor; sisa pagu tidak berubah (bawaan) | |

## 2. Daftar test Pest minimal per modul

| Modul | Berkas test (contoh) | Perilaku minimal yang diuji |
|---|---|---|
| `inti` | `tests/Unit/UangTest.php`, `tests/Feature/PenomoranTest.php`, `tests/Feature/HealthTest.php` | Perkalian & pembulatan HALF_UP; penjumlahan tanpa galat float; penolakan float; nomor unik berurutan per tahun; lock gagal → exception; health 200/503 |
| `auth` | `tests/Feature/Auth/*` | Seeder 7 peran; MFA wajib peran keuangan; rate limit login; pengguna nonaktif ditolak; email domain; Horizon gate |
| `audit` | `tests/Feature/Audit/RiwayatStatusTest.php` | Riwayat wajib dalam transaksi; event setelah commit; activitylog tanpa rahasia |
| `master` | `tests/Feature/Master/*` | 12 periode; transisi tahun satu arah; impor akun (buat/perbarui/gagal); tarif berlaku pada tanggal; pengaturan bertipe; scope unit |
| `pagu` | `tests/Feature/Pagu/*` | Σ rincian ≤ pagu; penetapan hanya wadek; ubah pagu ≥ komitmen; isolasi unit |
| `pengajuan` | `tests/Feature/Pengajuan/*` | Total dihitung server (abaikan input klien); guard edit setelah diajukan; ajukan: pagu, tautan TOR, nomor, lock; batal; PDF; verifikasi publik tanpa rincian |
| `berkas` | `tests/Feature/Berkas/*`, `tests/Arch/TanpaUnggahanTest.php` | `TautanBerkasValid` (https, daftar putih, tolak pemendek/folder/host mirip); ekstraksi `drive_file_id`; konfirmasi akses terbatas wajib untuk bukti keuangan; `PeriksaTautanBerkas` (HEAD/Range, tanpa cookie; publik → `peringatan_privasi`; login Google → dapat diakses; 404 → tidak dapat diakses); pratinjau Drive; tautan tak dirender bagi yang tak berhak; guard tautan terkunci; tidak ada `FileUpload`/`UploadedFile` selain impor |
| `persetujuan` | `tests/Feature/Persetujuan/*` | Alur 3 tahap; tahap dapat dimatikan (snapshot); pemisahan tugas; cek ulang pagu; kembalikan/tolak wajib catatan; lock dobel klik; status berubah oleh pengguna lain |
| `pencairan` | `tests/Feature/Pencairan/*` | Tautan bukti wajib (akses terbatas); Σ ≤ RAB aktif; status dicairkan + batas SPJ; periode terkunci; batal beralasan; pemisahan tugas BPP vs penyetuju |
| `spj` | `tests/Feature/Spj/*` | Realisasi ≤ item & total; tautan kuitansi/nota/faktur wajib; bukti ganda (`drive_file_id`) ditandai; pernyataan berkas final wajib saat ajukan; pembekuan tautan saat diterima (`tautan_berkas_beku` tak dapat diubah); item versi lama ditolak; ajukan/terima/kembalikan; pemisahan tugas verifikator |
| `pajak` | `tests/Feature/Pajak/*` | Nilai tersimpan = input (tanpa perhitungan); snapshot tarif; nilai ≤ dasar |
| `pengembalian` | `tests/Feature/Pengembalian/*` | Tautan bukti setor wajib; jumlah ≤ sisa; verifikasi → selesai + tautan setor dibekukan; kurang bayar tidak selesai |
| `revisi` | `tests/Feature/Revisi/*` | Nomor revisi berurutan; satu revisi berjalan; BR-11; persetujuan berjenjang; versi lama digantikan; pemetaan realisasi |
| `periode` | `tests/Feature/Periode/*`, `tests/Arch/PeriodeTerkunciTest.php` | Kunci berurutan; snapshot benar; semua model transaksi memakai guard; buka hanya wadek dengan alasan; tahun ditutup baca-saja |
| `notifikasi` | `tests/Feature/Notifikasi/*` | Pemetaan event → penerima (termasuk `TautanBerkasBermasalah`); surel tanpa URL tautan bukti; pengecualian pemisahan tugas; antrean `notifikasi`; WhatsApp fake; pengingat H-3/H-0/terlambat |
| `laporan`/`dasbor` | `tests/Feature/Laporan/*` | Angka bulanan/semester/tahunan = perhitungan manual; snapshot untuk periode terkunci; cache berversi; ekspor kecil di-stream; ekspor besar di `storage/app/tmp` terhapus ≤ 24 jam; tautan bertanda tangan; konsistensi dasbor = LAP-01 |
| `impor` | `tests/Feature/Impor/*` | Berkas impor terhapus setelah diproses (sukses/gagal); batch atomik; validasi neto; periode terkunci; tidak mengurangi pagu (bawaan) |
| Arsitektur | `tests/Arch/*` | `$table` di semua model; `status` tidak di `$fillable`; uang `decimal:2`; tanpa `dd/dump`; Action persetujuan memakai `DenganKunci` |
| Otorisasi | `tests/Feature/Otorisasi/*` | Matriks izin PRD §3.2 per peran; IDOR via UUID; pemisahan tugas menyeluruh; konkurensi dengan Redis nyata |
| Alur | `tests/Feature/AlurLengkapTest.php` | AL-01 dengan revisi di tengah alur |

Gerbang: `composer cek` (pint `--test`, Larastan level 6, Pest paralel) hijau di CI sebelum merge.

## 3. Checklist keamanan (OWASP dasar)

| No | Butir | Cara verifikasi | ✓ |
|---|---|---|---|
| K-01 | Otorisasi per record di Policy untuk setiap Resource & aksi; tidak ada logika otorisasi di Blade | `MatriksIzinTest`, tinjau kode | ✓ |
| K-02 | IDOR: URL memakai UUID; akses lintas unit 403/404 | `IdorTest`; uji manual ganti UUID | ✓ |
| K-03 | Pemisahan tugas (BR-07) di server, bukan hanya menyembunyikan tombol | `PemisahanTugasTest` | ✓ |
| K-04 | Tidak ada unggahan berkas pengguna (STANDAR-TEKNIS §1a): tidak ada `FileUpload`/`UploadedFile` selain impor Excel/CSV yang dihapus setelah diproses; `storage/app` hanya berisi `tmp` ≤ 24 jam | `TanpaUnggahanTest`; `find storage/app -mtime +1` | ✓ |
| K-05 | Validasi tautan `TautanBerkasValid`: hanya `https`, domain daftar putih, tolak pemendek URL, folder Drive, `javascript:`/`data:`, host mirip | Test berkas | ✓ |
| K-05a | Privasi bukti keuangan (NPWP/rekening): konfirmasi akses terbatas domain unsil.ac.id wajib; `PeriksaTautanBerkas` menandai tautan yang dapat dibuka anonim; URL bukti tidak dirender untuk peran tak berhak dan tidak dikirim lewat surel/WhatsApp | Test berkas & notifikasi; UAT AU-09b/c | ✓ |
| K-05b | Integritas bukti: pernyataan berkas final saat ajukan SPJ; snapshot `tautan_berkas_beku` (URL + waktu) saat diterima; tautan terkunci setelahnya | Test SPJ; UAT AK-08b | ✓ |
| K-05c | Tautan keluar `target="_blank" rel="noopener noreferrer"`; CSP `frame-src` hanya `drive.google.com`/`docs.google.com`; pemeriksa tautan tanpa cookie, timeout 10 dtk, tanpa mengunduh isi | Test header; tinjau kode | ✓ |
| K-06 | Rate limit: login 5/mnt, simpan tautan 30/mnt, periksa tautan 60/mnt (keluar), ekspor 5/mnt, verifikasi publik 30/mnt | Test throttle | ✓ |
| K-07 | CSRF aktif (bawaan Laravel/Livewire); tidak ada route POST yang dikecualikan tanpa alasan | Tinjau `bootstrap/app.php` | ✓ |
| K-08 | XSS: deskripsi/catatan ter-escape di panel, PDF, surel, halaman publik; tidak ada `{!! !!}` untuk input pengguna | Test payload `<script>` | ✓ |
| K-09 | MFA wajib untuk super-admin, admin-keuangan, bpp, wadek-keuangan | UAT AK-01 | |
| K-10 | Sesi: `secure`, `http_only`, `same_site=lax`, HTTPS + HSTS di produksi | Cek header & cookie | ✓ |
| K-11 | Header keamanan (CSP dasar, X-Frame-Options, Referrer-Policy, Permissions-Policy) | `curl -I` | ✓ |
| K-12 | Log & activitylog tanpa kata sandi, token, secret MFA, token WhatsApp | Test log; tinjau `activity_log` | ✓ |
| K-13 | Halaman verifikasi publik hanya memuat data minimum; `noindex` | UAT PB-01 | ✓ |
| K-14 | Integritas uang: tidak ada float; total dihitung server; rekonsiliasi harian tanpa selisih | Arch test; `keuangan:rekonsiliasi` | ✓ |
| K-15 | Lock Redis + `lockForUpdate` pada penomoran & persetujuan | `LockTest` | ✓ |
| K-16 | Periode terkunci tidak dapat ditulis lewat jalur mana pun (form, impor, pembatalan) | `PeriodeTerkunciTest` | ✓ |
| K-17 | `APP_DEBUG=false`; halaman galat tanpa jejak stack | Uji 500 di staging | |
| K-18 | `kup composer audit` & `kup npm audit` bersih; dependensi pada major yang ditetapkan | CI | ✓ |
| K-19 | `.env`, dump DB, isi `storage/app/tmp` tidak masuk repo/image | `git ls-files`, `.dockerignore` | ✓ |
| K-20 | Horizon hanya super-admin; `/api/health` tidak membocorkan detail exception | Test | ✓ |

## 4. Checklist go-live

| No | Butir | Penanggung jawab | ✓ |
|---|---|---|---|
| G-01 | ADR 0001 (batas cakupan) berstatus Diterima dan ditandatangani | Admin Keuangan, Wadek | |
| G-02 | ADR 0002: parameter 1–14 terkonfirmasi atau secara sadar ditandai "perlu verifikasi" dengan nilai sementara | Admin Keuangan | |
| G-03 | Master resmi termuat: unit FKIP, akun BAS (tidak ada akun `sumber=contoh` yang dipakai), jenis pencairan, tarif pajak referensi beserta dasar hukum | Admin Keuangan, BPP | |
| G-04 | Pengguna dibuat per peran dengan unit yang benar; MFA aktif untuk peran keuangan | Super-admin | |
| G-05 | Tahun anggaran berjalan dibuat; pagu semua unit ditetapkan | Admin Keuangan, Wadek | |
| G-06 | Server: HTTPS valid, domain/subpath sesuai portal, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` | TI | |
| G-07 | Container `app`, `web`, `queue` (Horizon), `scheduler`, MySQL 8.4, Redis 7 berjalan; `/api/health` ok | TI | |
| G-08 | SMTP kampus teruji (surel uji diterima); WhatsApp aktif/nonaktif sesuai keputusan | TI | |
| G-09 | Backup harian MySQL berjalan; uji pulih pertama berhasil dan dicatat di runbook | TI | |
| G-09a | Setiap unit memiliki Shared Drive (atau folder bersama fakultas) untuk bukti keuangan dengan berbagi default "Terbatas/Universitas Siliwangi"; panduan berbagi disosialisasikan | Admin Keuangan, TI | |
| G-10 | UAT seluruh peran lulus; berita acara UAT ditandatangani | Admin Keuangan, Wadek | |
| G-11 | Panduan pengguna tersedia di menu; sosialisasi/pelatihan per peran selesai | Admin Keuangan, TI | |
| G-12 | Tautan portal Support System FKIP diperbarui sesuai ADR 0001 (ganti `/laporankeuangan/` atau tambah tautan berlabel jelas) | TI | |
| G-13 | (Opsi a) Migrasi data historis selesai dan total cocok; `laporankeuangan` lama dijadikan baca-saja | Admin Keuangan, TI | |
| G-14 | Periode paralel/pendampingan bulan pertama dijadwalkan; jalur pelaporan isu (`fix/...`) disepakati | Semua | |
| G-15 | Tag `v1.0.0` dirilis; CHANGELOG dan versi di footer = 1.0.0 | TI | |

## Tambahan: subpath & panduan pengguna

| No | Butir | Hasil diharapkan | ✓ |
|---|---|---|---|
| SP-01 | Buka `https://supportfkip.unsil.ac.id/keuangan/` (staging/produksi) dan `http://localhost:8080/keuangan/` (gateway lokal) | Halaman, CSS/JS, Livewire, panel Filament, dan Horizon berjalan; tidak ada permintaan tanpa awalan `/keuangan` | |
| SP-02 | Login di `keuangan` lalu buka aplikasi lain di domain yang sama | Sesi tidak saling menimpa (cookie `keuangan_session`, path `/keuangan`) | |
| SP-03 | Pindai QR / buka tautan yang dibangkitkan sistem | URL memuat `https://supportfkip.unsil.ac.id/keuangan/…` | |
| PG-01 | Buka `/keuangan/panduan/` dari landing page aplikasi dan dari landing page supportfkip | Daftar peran tampil; setiap peran punya halaman HTML dengan tangkapan layar terbaru | |
| PG-02 | Ikuti panduan setiap peran memakai akun UAT | Langkah & tangkapan layar sesuai tampilan sistem | |

