# Deploy produksi — Keuangan FKIP Unsil

Pola `docker-apps`: container `app` (PHP-FPM), `web` (Nginx), `scheduler` (`schedule:work`, sekaligus menyalakan
worker antrean tiap menit) dan basis data MariaDB 10.11+. Tanpa Redis, Horizon, maupun Octane: antrean, cache, sesi, dan lock memakai
MariaDB (driver `database`). Aplikasi dipasang di subpath `https://supportfkip.unsil.ac.id/keuangan`.

> Profil Ringan (STANDAR-TEKNIS §1b) dan keputusan 16 Oktober 2026 sudah diterapkan di kode (cabang `feat/profil-ringan`; pilihan teknis
> dan hal yang menunggu pemilik: `docs/keputusan/0004-pelaksanaan-profil-ringan.md`). Produksi **v1.0.0** masih memakai container
> `mysql` 8.4, `redis`, dan Horizon: lihat bagian "Peralihan dari v1.0.0" di bawah sebelum menggelar rilis ini.

## Prasyarat server

- Docker Engine + Compose v2, akses `git` ke repositori, HTTPS valid di server depan.
- **MariaDB 10.11+ native di host** (bukan container): database `db_keuangan` (kolasi `utf8mb4_unicode_ci`) dan pengguna DB dengan hak penuh atas
  database itu; `bind-address` mencakup gateway Docker agar container menjangkaunya lewat `host.docker.internal` (STANDAR-TEKNIS §2.2).
- Port host `WEB_PORT` (bawaan 8035) hanya dapat diakses reverse proxy depan.

## Reverse proxy depan (server `supportfkip.unsil.ac.id`)

```nginx
location = /keuangan { return 301 /keuangan/; }
location /keuangan/ {
    proxy_pass http://127.0.0.1:8035/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /keuangan;
    client_max_body_size 10m;                     # hanya impor Excel/CSV
}
```

`TRUSTED_PROXIES` di `.env.production` diisi IP proxy ini (bukan `*`), agar header `X-Forwarded-*` dipercaya.

## Deploy pertama

```bash
git clone <repo> keuangan && cd keuangan
git checkout v1.0.0
cp .env.production.example .env.production
# isi APP_KEY (docker run --rm keuangan-app php artisan key:generate --show setelah build, atau php artisan key:generate --show di mesin dev),
# DB_PASSWORD, MAIL_*, TRUSTED_PROXIES, SEED_ADMIN_* (bawaan: fkip@unsil.ac.id),
# MFA_WAJIB (bawaan false)
export APP_VERSION=1.0.0
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan db:seed --force   # peran, izin, super-admin, master contoh
curl -fsS http://127.0.0.1:8035/api/health                                                                 # {"status":"ok",...}
```

- Migrasi berjalan otomatis di container `app` (`RUN_MIGRATIONS=true`); `scheduler` tidak bermigrasi.
- Entrypoint menjalankan `php artisan optimize`, `filament:optimize`, `icons:cache`. `storage:link` **tidak** dipakai
  (tidak ada berkas publik/unggahan; bukti berupa tautan Google Drive).
- Aset Vite dibangun di image dengan `ASSET_URL=https://supportfkip.unsil.ac.id/keuangan` (build arg).
- Volume `tmp` (ekspor/impor sementara, dibersihkan ≤ 24 jam oleh `keuangan:bersihkan-sementara`) dipakai bersama
  `app` dan `scheduler`.
- **Jangan** menjalankan `DemoSeeder` di produksi.

## Pembaruan versi

```bash
cd keuangan
git fetch --tags && git checkout vX.Y.Z
export APP_VERSION=X.Y.Z
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d          # app menjalankan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec scheduler php artisan queue:restart    # pekerja berikutnya memuat kode baru
curl -fsS http://127.0.0.1:8035/api/health
```

Sebelum memperbarui, pastikan cadangan MariaDB terbaru ada (lihat `docs/runbook-cadangan.md`; service `backup`
membuat dump harian 01:30 WIB ke `./backup/mysql`; nama jalur dipertahankan, lihat ADR 0004).

## Auto-pull dari GitHub (CD)

Pola mengikuti `sigap`/`puspresma`/`alias` (GitHub Actions → SSH ke server) dengan skrip rilis yang ikut di repo
seperti `silogy` (`scripts/deploy.sh`), bukan salinan di `~/docker-apps/deploy.sh`.

Alur: `git tag -a vX.Y.Z -m "..." && git push origin vX.Y.Z` → job `uji` (pint, phpstan, pest, shellcheck; dipakai ulang
dari `uji.yml`) → job `deploy` masuk ke server dan menjalankan:

```bash
cd ~/docker-apps/keuangan
git fetch --tags --prune --force origin && git fetch origin main
git checkout --detach "refs/tags/$TAG"
bash scripts/deploy.sh
```

`scripts/deploy.sh`: menolak bila HEAD bukan tag `vX.Y.Z`, tag tidak ada di riwayat `origin/main`, atau working tree
kotor → cadangan MariaDB pra-migrasi (service `backup`) → build → `up -d` (migrasi di `app`) → health check
`/api/health` (maks. 2 menit; gagal = job merah + 50 baris log `app`) → `queue:restart` → hapus image lama
(3 versi terakhir disimpan). Push biasa ke `main` **tidak** menggelar apa pun; hanya tag yang dirilis.

- Rahasia repositori (diisi manusia): `SERVER_HOST`, `SERVER_USER`, `SSH_PRIVATE_KEY` (kunci khusus deploy; publiknya di `authorized_keys`).
- Server: clone repo ke `~/docker-apps/keuangan` (perlu verifikasi dengan UPT TIK), `.env.production` di sana, akun deploy boleh menjalankan `docker`.
  Pakai *deploy key* GitHub baca-saja bila repo privat agar `git fetch` jalan tanpa kata sandi.
- Deploy ulang/rollback dari GitHub: *Actions → Deploy keuangan → Run workflow* dengan tag tujuan. Rollback cepat di server
  tanpa build ulang: `bash scripts/deploy.sh --rollback X.Y.Z` (`--list` menampilkan versi tersedia). Rollback hanya
  mengembalikan kode, bukan skema — lihat bagian Rollback.
- Aktif setelah repo punya remote GitHub (ADR 0003: saat ini belum ada remote; tidak ada push dilakukan).

## Rollback ke tag sebelumnya

```bash
git checkout v<sebelumnya>
export APP_VERSION=<sebelumnya>
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

Bila versi baru membawa migrasi yang tidak kompatibel ke belakang, pulihkan dump MariaDB sebelum pembaruan
(`docker/backup/restore.sh`, lihat runbook cadangan) — migrasi yang sudah dirilis tidak pernah diubah.

## Peralihan dari v1.0.0 (MySQL 8.4 + Redis + Horizon) ke Profil Ringan

**Jangan merge ke `main` sebelum langkah 1-6 selesai.** `deploy.yml` men-deploy dari tag rilis, tetapi compose baru tidak lagi memuat
`mysql`, `redis`, dan `queue`; `.env.production` lama (`DB_HOST=mysql`, `SESSION_DRIVER=redis`) membuat aplikasi mati bila rilis baru digelar tanpa penyesuaian.
Langkah 2 dan 4 menyentuh data produksi dan **belum pernah diuji pada data nyata**: lakukan dulu pada salinan dan catat hasilnya.

| # | Langkah | Bukti |
|---|---|---|
| 1 | Jadwalkan di luar jam kerja dan umumkan: **semua sesi berakhir** (sesi pindah dari Redis ke database), pengguna masuk ulang | Pengumuman |
| 2 | Cadangan penuh dari container lama (`exec backup /usr/local/bin/backup.sh`), salin keluar server bersama `.env.production` dan `APP_KEY`; simpan `.env.production` lama sebagai `.env.production.v1.0.0` | Berkas `.sql.gz` + `.sha256` |
| 3 | **Kuras antrean lama**: di `/keuangan/horizon` tunggu job tertunda dan berjalan = 0, lalu `exec queue php artisan horizon:terminate`. Job yang tersisa di Redis **tidak** berpindah dan akan hilang | Tangkapan layar Horizon kosong |
| 4 | **Pindahkan data** dari container `mysql` 8.4 (database `keuangan`) ke MariaDB host, database **`db_keuangan`** (nama baru). Garis besar (usulan, belum teruji): buat database dan pengguna di MariaDB host; jalankan `php artisan migrate --force` agar skema dibuat oleh Blueprint Laravel 13 (kolom `uuid` native, bukan `char(36)` bawaan MySQL); impor **data saja** dari dump lama (`--no-create-info`, kecualikan tabel `migrations`, `jobs`, `cache`, `cache_locks`, `sessions`) dengan pemeriksaan kunci asing dimatikan sementara; dump penuh MySQL 8.4 memuat kolasi `utf8mb4_0900_ai_ci` yang tidak dikenal MariaDB 10.11, jadi jangan diimpor apa adanya | Hitungan baris per tabel sama; `php artisan keuangan:rekonsiliasi` sukses |
| 5 | Ubah `.env.production` di server: `DB_CONNECTION=mariadb`, `DB_HOST=host.docker.internal`, `DB_DATABASE=db_keuangan`, hapus `DB_ROOT_PASSWORD`; `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `KEUANGAN_LOCK_STORE=database`, tambah `APP_KODE=keuangan`; hapus `REDIS_*` dan `HORIZON_*` (lihat `.env.production.example`) | `diff` terhadap `.env.production.example` |
| 6 | Merge ke `main`, checkout tag rilis baru, lalu `bash scripts/deploy.sh` (`up -d --remove-orphans` menghapus container `queue`, `redis`, `mysql` lama) | `docker compose ps` |
| 7 | Verifikasi: `/api/health` -> `db: ok`, `detak_penjadwal: ok` (maks. 2 menit); `schedule:list` memuat dua `queue:work`; kirim notifikasi uji dan satu ekspor laporan; uji pulih cadangan baru ke database uji | Keluaran perintah |
| 8 | Ukur memori dengan `docker stats` pada jam sibuk dan sesuaikan `mem_limit` (nilai awal: app 512m, web 64m, scheduler 768m, backup 128m) | Catatan ukur |
| 9 | Setelah 7 hari tanpa masalah: hapus volume `mysql` dan `redis` lama (`docker volume ls`) dan database `keuangan` lama | - |

**Pembatalan:** `git checkout v1.0.0`, kembalikan `.env.production.v1.0.0` ke `.env.production`, `docker compose ... up -d --remove-orphans`.
Container `mysql` lama dan volumenya **jangan dihapus** sebelum langkah 9, sehingga pembatalan tidak memerlukan pemulihan; transaksi yang masuk ke
MariaDB sesudah peralihan tidak ikut kembali (catat jam peralihan). Job yang sempat dikirim ke antrean `database` selama percobaan dapat hilang saat mundur.

## Akun awal

`db:seed` membuat super-admin `fkip@unsil.ac.id` dengan kata sandi dari `SEED_ADMIN_PASSWORD` (isi manual di `.env.production` sebelum `db:seed`; tidak ada nilai bawaan). **Ganti kata sandi segera setelah
masuk pertama** (menu pengguna → Profil), atau tandai akun wajib ganti sandi. MFA tidak diwajibkan secara bawaan; setel
`MFA_WAJIB=true` di `.env.production` (lalu `php artisan config:cache`) untuk mewajibkannya bagi super-admin, admin-keuangan, bpp, dan wadek-keuangan.

## Antrean & log

- Tidak ada dasbor antrean. Pantau lewat `/api/health` (job tertunda > 10 menit, `failed_jobs` 24 jam, detak penjadwal) dan
  `docker compose … exec scheduler php artisan queue:failed` / `queue:retry <id>`; `schedule:list` memuat dua `queue:work` (antrean `default` dan `berat`).
- Log aplikasi: volume `logs` (`storage/logs`, kanal `daily`, data sensitif disamarkan).
- Status layanan: `docker compose --env-file .env.production -f docker-compose.prod.yml ps` — `web` sehat bila
  `/api/health` mengembalikan `ok`; `app` sehat bila ping PHP-FPM membalas `pong`.

## Checklist go-live (tugas manusia)

Butir 05-UJI-PENERIMAAN §4 yang tidak dapat diverifikasi dari kode dan harus dikerjakan/ditandatangani:

1. **G-01/G-02** — Sahkan ADR 0001 (nama, tanggal, paraf) dan konfirmasi parameter ADR 0002; lencana *Perlu
   verifikasi* di **Sistem → Pengaturan** menunjukkan butir yang masih terbuka.
2. **G-03** — Muat master resmi: unit FKIP, impor akun BAS resmi, jenis pencairan (hapus tanda perlu verifikasi),
   tarif pajak referensi beserta dasar hukum (diisi bagian keuangan), kategori penggunaan dana.
3. **G-04/G-05** — Buat pengguna per peran (unit benar; impor massal dengan salin-tempel di Sistem → Pengguna, dengan opsi wajib ganti kata sandi), tahun anggaran berjalan, dan tetapkan pagu semua unit.
4. **G-06** — Server: sertifikat HTTPS valid, blok reverse proxy `/keuangan/`, `.env.production` (`APP_ENV=production`,
   `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` = IP proxy).
5. **G-07** — `docker compose … up -d --build` di server; cek `/api/health` = ok, scheduler berjalan dan detak penjadwal terbaru.
6. **G-08** — Uji SMTP kampus (surel uji diterima); putuskan WhatsApp aktif/nonaktif.
7. **G-09** — Pastikan cadangan harian tercipta di `./backup/mysql`, salin ke luar server, uji pulih pertama dan catat di runbook.
8. **G-09a** — Shared Drive per unit dengan berbagi default *Terbatas/Universitas Siliwangi*; sosialisasi panduan berbagi.
9. **G-10/G-11** — Jalankan UAT (`docs/uat/hasil-uat.md`, data `UatSeeder` di staging), tanda tangani berita acara,
   pelatihan per peran memakai panduan `/keuangan/panduan/`.
10. **G-12** — Tambah kartu/tautan portal Support System FKIP ke `keuangan/` dan `keuangan/panduan/` (ADR 0001 opsi b:
    tautan baru berlabel jelas, `/laporankeuangan/` tetap).
11. **G-14** — Jadwalkan pendampingan bulan pertama dan jalur pelaporan isu.
12. **G-15** — Dorong tag `v1.0.0` ke remote kampus dan bangun image produksi dari tag tersebut (job CI `docker`).
