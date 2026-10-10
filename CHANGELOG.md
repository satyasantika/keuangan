# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [1.0.0] - 2026-10-10

Rilis produksi pertama Keuangan FKIP Unsil. Ringkasan per modul (rincian di versi 0.1.0–0.11.0 di bawah):

- **Autentikasi & pengguna**: panel Filament berbahasa Indonesia di subpath `/keuangan`, MFA wajib peran keuangan, 7 peran dengan matriks izin PRD §3.2, manajemen pengguna & reset MFA.
- **Master & anggaran**: tahun anggaran + 12 periode, unit/prodi, akun BAS (impor), kategori penggunaan dana, jenis pencairan, jenis & tarif pajak referensi, pengaturan; pagu unit (+ rincian akun) dengan penetapan Wadek.
- **Pengajuan & persetujuan**: draf + RAB (uang DECIMAL, dihitung server), tautan TOR, penomoran `PGJ/{tahun}/{urut}` di bawah lock, persetujuan berjenjang dengan pemisahan tugas, PDF + QR verifikasi publik.
- **Pencairan, SPJ, pajak, pengembalian**: pencairan berbukti, realisasi per item dengan tautan bukti terbatas, deteksi bukti ganda, potongan pajak sebagai informasi, SPJ dengan pernyataan berkas final & pembekuan tautan, setoran sisa dana & penyelesaian.
- **Revisi RAB, kunci periode, notifikasi**: revisi bernomor, kunci/buka periode dengan snapshot, tutup tahun, notifikasi database/surel/WhatsApp opsional, penjadwal pengingat & rekonsiliasi.
- **Dasbor & laporan**: dasbor per peran, LAP-01 s.d. LAP-09 (Excel/PDF, ekspor besar lewat antrean), impor rekap honor ujian fkipapp (baca-saja).

### Ditambahkan
- Uji otorisasi menyeluruh: matriks izin 7 peran × halaman, IDOR lintas unit, pemisahan tugas BR-07, konkurensi lock Redis (fork paralel) dan penomoran 100 nomor unik.
- Pengerasan keamanan: header keamanan & CSP (frame hanya Google Drive/Docs), HSTS di produksi, sesi `secure`/`http_only`/`lax`, penyamaran data sensitif di log, halaman galat berbahasa Indonesia, route unggah `storage.local` dinonaktifkan.
- Docker produksi (`docker/Dockerfile`, `docker-compose.prod.yml`: app, web, queue/Horizon, scheduler, MySQL 8.4, Redis 7, backup), `docs/deploy.md`, job CI pembangunan image pada tag.
- Cadangan harian MySQL (retensi 30 hari, sha256) & skrip pemulihan yang menolak production, `docs/runbook-cadangan.md`; pemeriksaan format ekspor Excel (pengganti migrasi historis sesuai ADR 0001 opsi b).
- Panduan pengguna HTML per peran dengan tangkapan layar Playwright di `/keuangan/panduan/` (tautan di halaman masuk & menu pengguna), `PanduanSeeder` (lokal), `UatSeeder` (staging), lembar hasil UAT.
- Lencana "Perlu verifikasi" di halaman Pengaturan (ADR 0002, jenis pencairan, tarif pajak, akun contoh, kategori akreditasi) untuk super-admin & admin-keuangan.

### Diubah
- Seeder production hanya menjalankan peran & izin, super-admin pertama, pengaturan, jenis pencairan, dan jenis pajak; data contoh dilewati dengan pesan.

### Diperbaiki
- Proxy tepercaya dibaca dari config (sebelumnya `env()` di `bootstrap/app.php` dievaluasi sebelum `.env` dimuat sehingga `X-Forwarded-*` diabaikan).
- URL update Livewire tidak lagi menggandakan awalan `/keuangan` saat diakses tanpa `X-Forwarded-Prefix`.
- Data dasbor/laporan dan master tercache disimpan sebagai larik; sebelumnya Collection kembali sebagai `__PHP_Incomplete_Class` pada store Redis/file.
- Pemisahan tugas revisi RAB melempar `PelanggaranPemisahanTugasException` yang jelas.

## [0.11.0] - 2026-10-10

### Ditambahkan
- `LaporanRealisasiQuery` (pagu, komitmen, disetujui, dicairkan, realisasi, kumulatif, sisa, serapan) per bulan/semester/tahun dengan cache berversi (`VersiCacheDasbor`); rentang yang seluruhnya terkunci memakai snapshot `rekap_periode`.
- Dasbor per peran (filter tahun & periode): ringkasan anggaran, serapan per unit, tren realisasi bulanan, SPJ jatuh tempo; pengguna unit hanya melihat unitnya.
- Katalog laporan LAP-01 s.d. LAP-09 dengan hak akses per peran, pratinjau, ekspor XLSX/PDF di-*stream* (≤ 2.000 baris) atau lewat antrean `ekspor` ke `storage/app/tmp` dengan tautan unduh bertanda tangan, riwayat ekspor, dan pembatas laju.
- Kartu kegiatan (PDF), rekap potongan pajak (informasi), rekap pengembalian, snapshot periode, penggunaan dana per prodi (format akreditasi perlu verifikasi), SPJ terlambat.
- Impor rekap honor ujian dari ekspor fkipapp (ADR 0001, BR-24): job `ImporRekapHonor` di antrean `impor`, satu batch per berkas (seluruhnya ditolak bila ada baris tidak valid), berkas sementara selalu dihapus; daftar per batch dan "Batalkan batch"; baris terpisah "Honor ujian (impor fkipapp)" di dasbor/LAP-01, LAP-09; mengurangi pagu hanya bila `honor_mengurangi_pagu` aktif.
- Uji konsistensi angka: realisasi LAP-01 tahunan = Σ `total_realisasi` pengajuan = dasbor.

## [0.10.0] - 2026-10-10

### Ditambahkan
- Notifikasi database (format Filament, tombol "Buka") dan surel ("[Keuangan FKIP] …", tanpa URL tautan bukti/data penerima) dari event `StatusBerubah` lewat listener antrean `notifikasi`: menunggu tindakan per tahap (tanpa pengguna yang terlarang bertindak), dikembalikan/ditolak/disetujui/selesai, pencairan dicatat, revisi, SPJ, pengembalian, kunci/buka periode, indikasi bukti ganda; `TautanBerkasBermasalah` kini juga via surel. Pengguna nonaktif tidak menerima notifikasi.
- Kanal WhatsApp opsional (`services.whatsapp`, `WhatsAppChannel`: nomor dinormalisasi 62…, batas per menit, token hanya dari env) untuk notifikasi mendesak.
- Perintah terjadwal: `keuangan:pengingat-spj` (H-n dan interval terlambat, `--dry-run`), `keuangan:rekap-mingguan`, `keuangan:pengingat-kunci-periode`, `keuangan:periksa-ulang-tautan` (lewati tautan beku), `keuangan:bersihkan-sementara`; `keuangan:rekonsiliasi` memberi tahu super-admin bila ada selisih; jadwal di `routes/console.php`.
- Uji arsitektur: semua notifikasi & listener diantrekan. README: penjadwal lokal & Mailpit.

## [0.9.0] - 2026-10-10

### Ditambahkan
- Kunci periode (BR-19): `KunciPeriode` oleh admin-keuangan secara berurutan dengan pra-cek (SPJ belum diterima, pengembalian menunggu), snapshot `rekap_periode`/`rekap_periode_akun` dari `RekapPeriodeQuery` (SUM SQL atas DECIMAL), versi kunci, dan riwayat status.
- `BukaKunciPeriode` oleh wadek-keuangan dengan alasan ≥ 20 karakter, berurutan dari periode terbaru; snapshot lama dipertahankan sampai dikunci ulang.
- Penutupan tahun anggaran oleh wadek-keuangan (`TutupTahunAnggaran`): semua periode terkunci, tanpa pengajuan dalam proses, konfirmasi bila masih ada pengajuan dicairkan (BR-20).
- Relation manager periode dengan aksi Kunci (pra-cek), Buka kunci, dan Lihat snapshot; uji arsitektur model transaksi memakai `MenjagaPeriodeTerkunci`.
- DemoSeeder mengunci periode Januari s.d. bulan lalu.

## [0.8.0] - 2026-10-10

### Ditambahkan
- Revisi RAB bernomor (BR-10, BR-11): `BuatRevisiRab` menyalin RAB aktif dengan asal item, `SimpanItemRevisi` (ubah/tambah/hapus; item ber-realisasi tidak boleh dihapus), `AjukanRevisiRab` (item ≥ realisasi, total ≥ realisasi, kenaikan ≤ sisa pagu, peringatan bila di bawah dana dicairkan).
- Persetujuan revisi (`VerifikasiRevisiRab`, `SetujuiRevisiRab`, `KembalikanRevisiRab`, `TolakRevisiRab`) dengan pemisahan tugas; aktivasi versi: versi lama `digantikan`, `rab_aktif_id`/`total_disetujui` diperbarui, realisasi dipetakan ke item baru (pengecualian guard berflag yang selalu di-reset).
- `RabPolicy`; relation manager versi RAB (buat/edit/ajukan/bandingkan), `RevisiRabResource`, antrean revisi di halaman "Menunggu tindakan", `PerbandinganRabQuery`, dan PDF lembar revisi.
- Selama revisi berjalan, realisasi baru dan pengajuan SPJ ditolak.

## [0.7.0] - 2026-10-10

### Ditambahkan
- Tabel `spj`, `realisasi_item`, `potongan_pajak`, `pengembalian`, `tautan_berkas_beku` dengan `StatusSpj`/`StatusPengembalian`, guard SPJ (realisasi terkunci sejak diajukan), guard periode terkunci, dan perhitungan ulang total realisasi/pajak/dikembalikan (rekonsiliasi memeriksa total SPJ).
- `CatatRealisasi`/`HapusRealisasi`: realisasi per item RAB aktif ≤ jumlah item & total RAB (BR-14), tautan kuitansi/nota/faktur wajib dengan akses terbatas (BR-15), deteksi bukti ganda lintas pengajuan (`IndikasiBuktiGandaTerdeteksi`).
- `CatatPotonganPajak` sebagai informasi (BR-16): nilai diisi pengguna, tarif referensi disalin; BPP dapat melengkapi bukti setor saat SPJ diajukan.
- Alur SPJ (`AjukanSpj` bernomor `SPJ/{tahun}/{urut}` dengan pernyataan berkas final, `TerimaSpj`, `KembalikanSpj`) dengan pemisahan tugas dan pembekuan tautan bukti (`BekukanTautanSpj`) saat diterima.
- Pengembalian sisa dana (`CatatPengembalian`, `VerifikasiPengembalian` membekukan bukti setor, `TolakPengembalian`) dan `SelesaikanPengajuan` (BR-18, termasuk kurang bayar yang tuntas lewat pencairan tambahan).
- `SpjResource` (ringkasan dana, sisa per item, relation manager realisasi + pajak, tab tautan dibekukan, tombol ajukan/terima/kembalikan), `PengembalianResource`, aksi "Susun SPJ" dan "Catat pengembalian sisa dana".
- Test alur lengkap end-to-end; DemoSeeder memuat pengajuan selesai dan SPJ terlambat.

## [0.6.0] - 2026-10-10

### Ditambahkan
- Tabel `pencairan` dengan `StatusPencairan`, guard periode terkunci (`MenjagaPeriodeTerkunci`, `periode_id` otomatis) dan tautan bukti pencairan wajib berakses terbatas.
- `CatatPencairan` oleh BPP (BR-12 Σ ≤ total RAB aktif, BR-13 batas SPJ disimpan saat pencairan pertama, BR-07c pencatat ≠ penyetuju akhir, BR-19), status pengajuan → `dicairkan`.
- `BatalkanPencairan` beralasan (tanpa hapus); pengajuan kembali `disetujui` bila tidak ada pencairan aktif.
- `HitungUlangTotalPengajuan` (total dicairkan) + `VersiCacheDasbor`; perintah `keuangan:rekonsiliasi` (`--perbaiki`).
- `PencairanResource` (grup Bendahara, filter jenis/periode/status/unit), aksi "Catat pencairan" di halaman pengajuan, relation manager pencairan, dan ringkasan dana (batas SPJ merah bila lewat).

## [0.5.0] - 2026-10-10

### Ditambahkan
- Persetujuan berjenjang (BR-06): `VerifikasiPengajuan` (kepala unit → keuangan; langsung disetujui bila tahap wadek nonaktif menurut snapshot) dan `SetujuiPengajuan` (wadek, cek ulang sisa pagu di bawah lock pagu unit, `total_disetujui`, RAB awal disetujui).
- Pemisahan tugas (BR-07) lewat `MemeriksaPemisahanTugas`: pembuat tidak boleh menyetujui; satu pengguna satu tahap; deteksi aksi basi ("sudah diproses pengguna lain").
- `KembalikanPengajuan` (RAB awal kembali draf) dan `TolakPengajuan` (final) dengan catatan wajib; `BatalkanPengajuan` oleh admin-keuangan untuk status diajukan s.d. disetujui tanpa pencairan aktif.
- Tombol keputusan di halaman pengajuan (berbasis Policy), linimasa status, log perubahan (peran fakultas), halaman "Menunggu tindakan saya" dengan lencana jumlah, dan widget antrean persetujuan.
- Uji arsitektur aksi persetujuan (DenganKunci, CatatRiwayatStatus); DemoSeeder memuat pengajuan di setiap status persetujuan.

### Diubah
- Batas memori uji 512M (`phpunit.xml`).

## [0.4.0] - 2026-10-10

### Ditambahkan
- Tabel `pengajuan`, `rab`, `rab_item` (RAB berversi, FULLTEXT judul) dengan `StatusPengajuan`/`StatusRab` beserta tabel transisi, `dapatDiubah()`, dan `menghitungKomitmen()` (BR-04); `Akun::sedangDipakai()`.
- `SimpanDrafPengajuan` + `HitungTotalRab`: jumlah item dan total RAB dihitung server dengan `Uang` (HALF_UP), nilai klien diabaikan; guard model mengunci data inti dan item RAB setelah diajukan (BR-02, BR-03); `PengajuanPolicy` berbatas unit.
- `PengajuanResource` (form kegiatan + Repeater RAB dengan jumlah/total tampilan saja, sisa pagu reaktif, infolist RAB aktif, pencarian nomor/judul) dan widget `RingkasanPaguWidget`; `HitungSisaPagu` (unit & akun); `UbahPaguDitetapkan` menolak jumlah di bawah komitmen.
- Tautan berkas pengganti unggahan: `config/berkas.php` (`BERKAS_MODE=tautan`), tabel `tautan_berkas`, `TautanBerkasValid`, `PenguraiTautanDrive`, kontrak `PenyimpananBerkas` + `TautanEksternal`, `SimpanTautanBerkas`/`HapusTautanBerkas`, konfirmasi akses terbatas untuk bukti keuangan, kunci tautan mengikuti pemilik, relation manager TOR/KAK, uji arsitektur tanpa unggahan.
- Job `PeriksaTautanBerkas` (HEAD/Range tanpa cookie, rate limit global) dengan notifikasi `TautanBerkasBermasalah`, pratinjau Drive, dan daftar tautan di halaman pengajuan.
- `AjukanPengajuan` (lock Redis per pengajuan + per pagu unit, cek sisa pagu unit/akun, wajib TOR, nomor `PGJ/{tahun}/{urut}` dipertahankan saat diajukan ulang, snapshot tahap persetujuan) dan `BatalkanPengajuan` beralasan.
- Lembar pengajuan PDF (dompdf, di-stream) dengan QR ke halaman publik `/verifikasi/{id}` (ringkas, noindex, 30/menit per IP).
- `DemoSeeder` (hanya local): pengguna per peran `*@demo.test`, pagu ditetapkan, 10 pengajuan draf/diajukan.

## [0.3.0] - 2026-10-10

### Ditambahkan
- Tahun anggaran (`BuatTahunAnggaran` membuat 12 periode bulanan, `UbahStatusTahunAnggaran` satu arah perencanaan → berjalan → ditutup) dan `Periode::untukTanggal()`; trait `MenjagaPeriodeTerkunci` menolak tulis/hapus pada periode terkunci atau tahun ditutup (BR-19, BR-20).
- Master unit/prodi (`UnitResource`, `JenisUnit`, impor `UnitImporter`, cache `master:unit:aktif`), kolom `users.unit_id` wajib untuk admin-unit/kepala-unit, trait `DibatasiUnit` dan `User::lingkupFakultas()` (BR-21); `UnitContohSeeder` (CONTOH, lokal/staging).
- Akun BAS (`AkunResource`, impor `AkunImporter` di antrean `impor`, lencana "CONTOH", cache `master:akun:aktif`) dan kategori penggunaan dana (urutan dapat diatur); seeder contoh bertanda "perlu verifikasi".
- Jenis pencairan (UP/TUP/LS, perlu verifikasi), jenis pajak dan tarif referensi berjangka berlaku tanpa tumpang tindih (`TarifPajak::berlakuPada()`, tanpa perhitungan pajak — BR-16); tarif contoh bernilai 0,00 sampai diisi bagian keuangan.
- Pengaturan kebijakan (`App\Support\Pengaturan` bertipe, tercache, tercatat activitylog) dan halaman Pengaturan berkelompok (ubah: admin-keuangan & super-admin).
- Pagu unit per tahun beserta rincian per akun (Σ rincian ≤ pagu), penetapan `TetapkanPagu` dan perubahan beralasan `UbahPaguDitetapkan` oleh wadek-keuangan dengan riwayat status; pagu draf tidak berlaku; pengguna unit hanya melihat pagu unitnya (403 lintas unit).
- Tabel impor/ekspor Filament ber-UUID; berkas impor sementara dihapus setelah dibaca.
- Uji arsitektur model: `$table` wajib, `status` tidak di `$fillable`, kolom uang `decimal:2`.

## [0.2.0] - 2026-10-07

### Ditambahkan
- Panel Filament 5 `keuangan` di `/panel` (login, notifikasi basis data polling 30 detik, SPA, footer versi, grup navigasi); `/` diarahkan ke `/panel`.
- Kolom pengguna `nip`, `no_hp`, `kode_eksternal`, `aktif`; `User` mengimplementasikan `FilamentUser` (akun nonaktif ditolak 403); tabel `notifications` (UUID).
- Tujuh peran (`App\Enums\Peran`) dan izin `<modul>.<aksi>` menurut matriks PRD §3.2 lewat `PeranDanIzinSeeder` (idempoten) dan `PenggunaAwalSeeder` (`SEED_ADMIN_*`); model `Role`/`Permission` ber-UUID; cache izin di Redis.
- Jejak audit: `spatie/laravel-activitylog` (model `Aktivitas` ber-UUID, trait `TercatatAktivitas` tanpa kata sandi/rahasia MFA), tabel `riwayat_status`, `TahapPersetujuan`, `CatatRiwayatStatus` (wajib dalam transaksi) dan event `StatusBerubah` (setelah commit); morph map wajib.
- Fondasi domain: `Uang` (brick/math, HALF_UP, menolak float), `DenganKunci` (lock Redis), `Penomoran` (lock + `lockForUpdate`), tabel `penomoran`, exception domain turunan `KesalahanDomain`.
- Manajemen pengguna (`UserResource`, `UserPolicy`; surel wajib `@unsil.ac.id` kecuali local/testing; hanya super-admin yang boleh memberi peran super-admin; tidak bisa menghapus diri sendiri); gerbang Horizon hanya super-admin di semua environment.
- MFA aplikasi autentikator (TOTP + kode pemulihan, terenkripsi) wajib untuk super-admin, admin-keuangan, bpp, wadek-keuangan (`WajibMfa`); aksi "Reset MFA" oleh super-admin; pembatasan login 5/menit per surel + IP.

### Diubah
- Uji memakai `APP_URL=http://localhost` (agar pengujian Livewire tidak terkena awalan subpath); `PERMISSION_CACHE_STORE` dan `KEUANGAN_LOCK_STORE` = `array` saat uji.

## [0.1.0] - 2026-10-07

### Ditambahkan
- Inisialisasi repositori: `.gitignore`, `.editorconfig`, hook git (`commit-msg`, `pre-commit`), `CLAUDE.md`, dan salinan paket dokumen di `docs/vibecoding/`.
- ADR 0001 — batas cakupan sistem keuangan (`docs/keputusan/0001-batas-cakupan.md`).
- ADR 0002 — parameter keuangan dan daftar "perlu verifikasi" (`docs/keputusan/0002-parameter-keuangan.md`).
- Kerangka Laravel 13 (PHP 8.5) di container Docker FKIP, MariaDB native, locale `id`, zona waktu `Asia/Jakarta`, terjemahan Indonesia.
- Redis bersama untuk cache, sesi, dan antrean; Laravel Horizon dengan dua supervisor (`notifikasi`/`default` dan `ekspor`/`impor`).
- Pest 4, Larastan level 6, Pint, dan hook git aktif (`commit-msg`, `pre-commit`).
- Primary key UUIDv7 sejak awal beserta uji arsitektur.
- Dukungan subpath `https://supportfkip.unsil.ac.id/keuangan` (URL, aset, cookie sesi, proxy tepercaya).
- Laravel Boost (MCP berjalan di container) untuk agen AI.
- Endpoint kesehatan `GET /api/health`.
- Continuous Integration: `uji.yml` (MariaDB 10.11, Redis 7, PHP 8.5; pint, phpstan, pest) dan `deploy.yml` (SSH, `deploy.sh keuangan`).
- ADR 0003: lingkungan dan penyimpangan dari standar paket.
