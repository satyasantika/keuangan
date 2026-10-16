# Keuangan FKIP Unsil

![Uji keuangan](../../actions/workflows/uji.yml/badge.svg)

Aplikasi pencatatan dan pelaporan keuangan kegiatan **Fakultas Keguruan dan Ilmu Pendidikan (FKIP) Universitas Siliwangi**.

Siklus yang dikelola: **pagu unit per tahun → pengajuan kegiatan + RAB → persetujuan berjenjang → pencairan → SPJ/realisasi dengan bukti → potongan pajak (informasi) → pengembalian sisa dana → kunci periode → dasbor dan laporan.**

Produksi: `https://supportfkip.unsil.ac.id/keuangan` · Panduan pengguna: `/keuangan/panduan/`

## Fitur utama

- **Pagu dan RAB** — pagu per unit dan tahun anggaran; RAB per item dihitung server dengan `DECIMAL`, tanpa float.
- **Persetujuan berjenjang** — kepala unit (opsional) → admin keuangan → wakil dekan (opsional), pemisahan tugas, catatan wajib saat dikembalikan/ditolak, nomor pengajuan terkunci anti-ganda.
- **Pencairan dan SPJ** — pencairan bertahap berbukti, realisasi per item, batas SPJ otomatis, pernyataan berkas final.
- **Bukti berupa tautan** — kuitansi/nota disimpan sebagai tautan Google Drive terbatas (tanpa unggahan); tautan diperiksa berkala dan dibekukan saat SPJ diterima.
- **Pajak sebagai informasi**, revisi RAB bernomor, **kunci periode** dan tutup tahun.
- **Dasbor dan laporan** — LAP-01 s.d. LAP-09 dengan ekspor PDF/Excel; rekap honor ujian aplikasi lain diimpor baca-saja.
- **Notifikasi** database, surel, dan WhatsApp opsional; **jejak audit** pada setiap perubahan status.
- **Manajemen akun** — tujuh peran, impor massal pengguna dengan salin-tempel, profil dan ganti kata sandi sendiri, wajib-ganti-sandi per akun, MFA sukarela.

Peran: `super-admin`, `admin-keuangan`, `bpp`, `wadek-keuangan`, `kepala-unit`, `admin-unit`, `pimpinan`.
Aturan bisnis lengkap: [docs/aturan-bisnis.md](docs/aturan-bisnis.md). Keputusan arsitektural: [docs/keputusan/](docs/keputusan/).

## Teknologi

PHP 8.5 · Laravel 13 · Filament 5 (Livewire 4) · Tailwind CSS 4 · MariaDB 10.11+ (cache, sesi, antrean, lock: driver database; Profil Ringan tanpa Redis/Horizon) ·
spatie/laravel-permission · spatie/laravel-activitylog · Pest 4 · Larastan (level 6) · Pint. Locale `id`, zona waktu `Asia/Jakarta`.

## Menjalankan lokal

Prasyarat: Docker (WSL2), Git, Node 22, dan MariaDB native di host (database `db_keuangan` dan `db_keuangan_testing`). PHP dan Composer dijalankan di container, bukan di host
(lihat `docs/keputusan/0003-lingkungan-dan-penyimpangan-standar.md`).

```bash
alias kup='docker exec keuangan-php'
cd /home/satya/code && docker compose up -d --build keuangan-php keuangan-nginx keuangan-scheduler
cd keuangan
cp .env.example .env            # isi DB_PASSWORD
kup composer install
kup php artisan key:generate
kup php artisan migrate --seed  # peran, izin, pengaturan, dan super-admin awal
npm install && npm run build
```

> **Profil Ringan berlaku setelah Fase B; kondisi saat ini:** `/home/satya/code/docker-compose.yml` masih mendefinisikan `keuangan-horizon` dan kode/`.env.example` masih memakai Redis. Perintah `up -d` di atas (tanpa `keuangan-horizon`) dan keterangan antrean berbasis database baru benar setelah Fase B; sebelum itu tambahkan `keuangan-horizon` dan jalankan Redis bersama.

Aplikasi: http://localhost:8035/keuangan/ · Kesehatan: `/api/health` · Antrean: `kup php artisan queue:failed` / `queue:retry <id>` (dijalankan penjadwal, STANDAR-TEKNIS §1b).
Data contoh lokal: `kup php artisan db:seed --class=DemoSeeder`.

### Akun super-admin awal

Seeder membuat `fkip@unsil.ac.id` (kata sandi awal diisi manual lewat `SEED_ADMIN_PASSWORD` di `.env` sebelum `db:seed`).
**Ganti kata sandi segera setelah masuk pertama** lewat menu pengguna → Profil.
MFA tidak diwajibkan secara bawaan (`MFA_WAJIB=false`); setel `MFA_WAJIB=true` bila peran keuangan harus memakai autentikator.

### Manajemen pengguna

- Menu **Sistem → Pengguna**: tambah/ubah pengguna, atur peran dan unit, reset MFA, dan **wajibkan ganti kata sandi** (per akun atau aksi massal).
- **Impor massal (tempel)**: salin baris dari Excel/Google Sheets — kolom `nama, surel, peran, kode unit, NIP, no. HP` —
  tempel, isi kata sandi awal. Satu baris salah membatalkan seluruh impor dan semua galat ditampilkan.
- **Impersonasi darurat**: super-admin dapat memilih **Masuk sebagai** pada baris pengguna aktif mana pun. Spanduk oranye tampil selama menyamar
  dengan tombol **Kembali ke akun saya**; mulai dan akhir impersonasi tercatat, setiap entri audit selama menyamar memuat `diimpersonasi_oleh`,
  dan profil/kata sandi pengguna yang disamarkan tidak dapat diubah. Gunakan hanya untuk keadaan darurat.
- Setiap pengguna dapat mengubah profil dan kata sandinya; mengganti kata sandi otomatis mengeluarkan perangkat lain.

## Uji dan kualitas

```bash
kup composer cek            # pint --test + phpstan + pest paralel
kup ./vendor/bin/pint       # format
kup php artisan test        # uji berjalan di MariaDB db_keuangan_testing
```

Hook git (sekali per klon): `git config core.hooksPath .githooks` — `pre-commit` menjalankan pint dan test,
`commit-msg` mewajibkan Conventional Commits (`feat(modul): ...`).

## Subpath `/keuangan`

Aplikasi berjalan di bawah awalan path di belakang reverse proxy. Semua URL dibangkitkan lewat `route()`/`url()`/`asset()`
dengan awalan dari `APP_URL`; proxy boleh memotong awalan dan mengirim `X-Forwarded-Prefix`. Konfigurasi dan blok proxy ada di
[docs/deploy.md](docs/deploy.md). Aplikasi lain dapat memakai pola yang sama dengan mengganti awalan (`APP_URL`, `ASSET_URL`, `SESSION_PATH`, `SESSION_COOKIE`).

## Deploy dan operasional

- **Deploy produksi (Docker)**, **auto-pull dari GitHub** (tag `vX.Y.Z` → uji → `scripts/deploy.sh`), rollback: [docs/deploy.md](docs/deploy.md)
- **Cadangan dan uji pulih**: [docs/runbook-cadangan.md](docs/runbook-cadangan.md)
- **Lembar UAT**: [docs/uat/hasil-uat.md](docs/uat/hasil-uat.md)
- **Panduan pengguna** per peran (HTML bergambar): `public/panduan/` — ditautkan dari landing page, halaman masuk, dan menu pengguna

### Memperbarui panduan

Tangkapan layar dibuat ulang dengan Playwright dari data rekaan lokal:

```bash
# .env lokal: PANDUAN_PASSWORD=<sandi akun demo>
kup php artisan migrate:fresh --seeder=PanduanSeeder     # HANYA lokal; akun panduan.<peran>@contoh.test
PANDUAN_PASSWORD=<sandi> composer panduan
```

Gambar tersimpan di `public/panduan/img/<peran>/`; teks diedit langsung di `public/panduan/<peran>.html`.

## Halaman eror

Semua kode eror HTTP yang lazim (400–504) memakai halaman bertema yang sama dengan landing page. Teksnya ada di `lang/id/galat.php`;
untuk gangguan di tingkat nginx tersedia halaman statis di `public/galat/`.
