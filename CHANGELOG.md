# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

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
