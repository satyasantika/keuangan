# Keuangan FKIP Unsil

![Uji keuangan](../../actions/workflows/uji.yml/badge.svg)

Aplikasi pencatatan dan pelaporan keuangan kegiatan Fakultas Keguruan dan Ilmu Pendidikan (FKIP) Universitas Siliwangi: pagu unit, pengajuan kegiatan + RAB, persetujuan berjenjang, pencairan, SPJ dengan bukti (tautan), pengembalian sisa dana, kunci periode, serta dasbor dan laporan.

## Dokumen

Semua dokumen perancangan ada di `docs/vibecoding/`:

- `01-PRD.md` — kebutuhan produk dan aturan bisnis
- `02-ARSITEKTUR.md` — arsitektur
- `03-SKEMA-DATABASE.md` — skema basis data
- `04-PROMPT-BERTAHAP.md` — rencana kerja bertahap per fase
- `05-UJI-PENERIMAAN.md` — uji penerimaan
- `STANDAR-TEKNIS.md`, `STANDAR-GIT.md` — standar bersama
- `CLAUDE.md` — konteks untuk agen AI (juga di root repo)

Keputusan arsitektural (ADR) ada di `docs/keputusan/`.

## Prasyarat

- PHP 8.5 (dijalankan di container Docker, bukan di host), Composer 2
- Node 22
- Docker (WSL2) dan Git
- MariaDB di host, Redis bersama

## Cara menjalankan

### Menjalankan lokal

Infrastruktur (di luar repo ini, di `/home/satya/code`, lihat `docs/keputusan/0003-lingkungan-dan-penyimpangan-standar.md`):
container `keuangan-php`, `keuangan-nginx`, `keuangan-scheduler` (file `docker-compose.yml`), MariaDB native di host
(`db_keuangan`, `db_keuangan_testing`), dan Redis bersama (container `redis`). Tanpa Sail, tanpa PHP di host.

```bash
alias kup='docker exec keuangan-php'
cd /home/satya/code && docker compose up -d --build keuangan-php keuangan-nginx keuangan-scheduler
cd keuangan
cp .env.example .env          # isi DB_PASSWORD
kup composer install
kup php artisan key:generate
kup php artisan migrate
npm install && npm run build  # Node di host (container tidak memiliki Node)
```

Aplikasi: http://localhost:8035/keuangan/ (juga http://localhost:8035 untuk pemeriksaan langsung; kesehatan: `/api/health`) · Mailpit: http://localhost:8025 (bila tersedia).

Horizon (antrean) berjalan sebagai service `keuangan-horizon` dan dasbornya di `/keuangan/horizon` (hanya peran `super-admin`, di semua environment);
restart setelah mengubah kode job: `cd /home/satya/code && docker compose restart keuangan-horizon`. Penjadwal: service `keuangan-scheduler`.
Redis bersama: DB 13 (default/lock), 14 (cache), 15 (antrean), prefiks `keuangan_`.

### Menjalankan uji dan gerbang kualitas

```bash
kup composer cek          # pint --test + phpstan (level 6) + pest paralel
kup ./vendor/bin/pint     # format
kup php artisan test      # uji berjalan di MariaDB db_keuangan_testing, bukan SQLite
```

Hook git (sekali per klon): `git config core.hooksPath .githooks`. Hook `pre-commit` menjalankan pint dan test di container `keuangan-php`;
`commit-msg` mewajibkan Conventional Commits (lihat `docs/vibecoding/STANDAR-GIT.md`).

### Super-admin pertama dan MFA

1. Isi di `.env`: `SEED_ADMIN_EMAIL` (mis. `admin@unsil.ac.id`), `SEED_ADMIN_PASSWORD` (kata sandi kuat), opsional `SEED_ADMIN_NAMA`.
2. Jalankan `kup php artisan migrate` lalu `kup php artisan db:seed` (idempoten): membuat 7 peran + izin sesuai PRD §3.2 dan akun super-admin.
   Seeder gagal dengan pesan jelas bila kedua variabel kosong. Lihat izin: `kup php artisan permission:show`.
3. Login di `/panel/login`. Peran `super-admin`, `admin-keuangan`, `bpp`, `wadek-keuangan` **wajib MFA**: setelah login pertama
   diarahkan ke halaman pengaturan MFA; pindai QR dengan aplikasi autentikator (Google Authenticator/Authy/dsb.), masukkan kode,
   lalu simpan kode pemulihan. Peran lain dapat mengaktifkan MFA opsional lewat menu profil.
4. Pengguna lain dibuat super-admin di menu Sistem > Pengguna. Bila pengguna kehilangan perangkat autentikator dan kode pemulihan,
   super-admin memakai aksi "Reset MFA" (tercatat di jejak audit). Login dibatasi 5 percobaan per menit per kombinasi surel + IP.
5. Aset Filament (`public/css|js|fonts/filament`) tidak masuk git; dipublikasikan oleh `composer install`/`php artisan filament:upgrade`
   (script `post-autoload-dump`) dan wajib ada saat build image produksi.

### Subpath `/keuangan`

Produksi: `https://supportfkip.unsil.ac.id/keuangan`. Reverse proxy memotong awalan sebelum meneruskan ke Nginx aplikasi,
sedangkan aplikasi membangkitkan semua URL dengan awalan itu (`URL::forceRootUrl`, `ASSET_URL`, `SESSION_PATH=/keuangan`,
`SESSION_COOKIE=keuangan_session`, `trustProxies`). Nginx lokal (`/home/satya/code/docker/nginx/keuangan.conf`) meniru
pemotongan awalan itu, sehingga lokal dan produksi berperilaku sama. Aset dibangun dengan `ASSET_URL` terisi
(`npm run build` membaca `.env`, base `/keuangan/build/`). Dilarang `href="/..."`, `src="/..."`, `action="/..."`,
`fetch('/...')` di Blade/JS (diuji `tests/Arch/SubpathTest.php`).

Blok reverse proxy produksi (di server depan):

```nginx
location = /keuangan { return 301 /keuangan/; }
location /keuangan/ {
    proxy_pass http://127.0.0.1:<port>/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /keuangan;
    client_max_body_size 20m;
}
```

### Laravel Boost (agen AI)

`.mcp.json` menjalankan server MCP Boost di dalam container (`docker exec -i keuangan-php php artisan boost:mcp`).
Pedoman versi-spesifik ditambahkan Boost di bagian bawah `CLAUDE.md` (isi paket di bagian atas tidak boleh diringkas).
Perbarui pedoman dengan `kup php artisan boost:update`; periksa hasilnya agar bagian atas `CLAUDE.md` tetap utuh.
