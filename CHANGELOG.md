# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

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
