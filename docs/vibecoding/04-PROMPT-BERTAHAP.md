# 04 — Prompt Bertahap Vibecoding: Sistem Keuangan Fakultas FKIP Unsil

Dokumen inti paket. Kerjakan **berurutan**, satu langkah per sesi agen AI. Setiap langkah: tempel prompt → tinjau `git diff` → jalankan gerbang kualitas → commit (manusia yang meng-commit; lihat `STANDAR-GIT.md`).

**Sebelum mulai setiap sesi agen**, pastikan `CLAUDE.md` sudah ada di root repo dan agen sudah membacanya. Bila agen ragu tentang API Laravel 13/Filament 5/Livewire 4, minta ia memakai tool `search-docs` dari Laravel Boost (tersedia mulai F1.6).

**Kebijakan berkas.** Sistem **tidak menerima unggahan berkas** (STANDAR-TEKNIS §1a). Semua lampiran & bukti berupa tautan Google Drive/`*.unsil.ac.id` di tabel `tautan_berkas`; satu-satunya input berkas adalah impor Excel/CSV yang diproses lalu dihapus. Agen tidak boleh menambah `FileUpload` di luar impor.

**Catatan menjalankan perintah.** Semua perintah PHP/Composer/NPM dijalankan di container Docker FKIP lewat alias `kup` = `docker compose exec keuangan-php` (STANDAR-TEKNIS §2), misalnya blok "Commit" menulis `kup ./vendor/bin/pint && kup php artisan test`. MySQL native di host, Redis & Mailpit bersama; git dijalankan di host.

Ringkasan fase:

| Fase | Judul | Branch | Langkah | Tag |
|---|---|---|---|---|
| F0 | Persiapan repositori & klarifikasi cakupan | `main` (langsung) | 4 | — |
| F1 | Fondasi Laravel | `feat/f1-fondasi` | 9 | `v0.1.0` |
| F2 | Autentikasi, peran & audit | `feat/f2-auth-audit` | 7 | `v0.2.0` |
| F3 | Master data & pagu | `feat/f3-master-pagu` | 6 | `v0.3.0` |
| F4 | Pengajuan kegiatan & RAB | `feat/f4-pengajuan-rab` | 8 | `v0.4.0` |
| F5 | Persetujuan berjenjang | `feat/f5-persetujuan` | 4 | `v0.5.0` |
| F6 | Pencairan | `feat/f6-pencairan` | 3 | `v0.6.0` |
| F7 | SPJ, realisasi, pajak & pengembalian | `feat/f7-spj` | 8 | `v0.7.0` |
| F8 | Revisi RAB | `feat/f8-revisi-rab` | 4 | `v0.8.0` |
| F9 | Kunci periode | `feat/f9-kunci-periode` | 3 | `v0.9.0` |
| F10 | Notifikasi & penjadwal | `feat/f10-notifikasi` | 4 | `v0.10.0` |
| F11 | Laporan, dasbor & ekspor | `feat/f11-laporan` | 6 | `v0.11.0` |
| F12 | Pengerasan & rilis | `feat/f12-rilis` | 7 | `v1.0.0` |
| | **Total** | | **70** | |

---

## F0 — Persiapan repositori & klarifikasi cakupan

Fase ini dikerjakan langsung di `main` (diizinkan `STANDAR-GIT.md` §1.3). Laravel belum ada, sehingga `pint`/`test` belum dijalankan.

### F0.1 — Inisialisasi repositori, hook git, dan berkas konteks
**Branch:** `main`
**Tujuan:** Membuat repositori kosong yang sudah memuat pagar commit, `.gitignore`, `README.md`, `CLAUDE.md`, dan salinan paket vibecoding ini.

Langkah manusia sebelum prompt:
```bash
mkdir -p ~/code/keuangan && cd ~/code/keuangan   # di dalam WSL2, bukan di /mnt/c
git init -b main
mkdir -p docs/vibecoding
cp /path/ke/paket/keuangan/vibecoding/*.md docs/vibecoding/
cp docs/vibecoding/CLAUDE.md ./CLAUDE.md
```

**Prompt (salin ke agen AI):**
```text
Kita memulai repositori aplikasi "Keuangan FKIP Unsil" (Laravel 13 akan dipasang di F1; sekarang folder masih
tanpa Laravel). Baca CLAUDE.md dan docs/vibecoding/STANDAR-GIT.md terlebih dahulu.

Buat berkas berikut di root repo:
1. .gitignore — gabungan kebutuhan Laravel (/vendor, /node_modules, /public/build, /public/hot, /storage/*.key,
   /storage/pail, .env, .env.backup, .env.production, .phpunit.result.cache, /.phpunit.cache, Homestead.*,
   /auth.json, /.idea, /.vscode, /.fleet, .DS_Store, Thumbs.db) DITAMBAH larangan STANDAR-GIT §7:
   storage/app/tmp/* (kecuali .gitignore; berkas sementara ekspor/impor), *.sql, *.sql.gz, *.zip, /backup/,
   /docs/referensi/rahasia/.
2. .githooks/commit-msg dan .githooks/pre-commit — isinya PERSIS seperti STANDAR-GIT.md §6 (salin apa adanya,
   termasuk shebang). Jangan aktifkan core.hooksPath sekarang; aktivasi dilakukan di F1.3 setelah Pint & Pest ada.
3. README.md — judul "Keuangan FKIP Unsil", satu paragraf tujuan (pencatatan & pelaporan keuangan kegiatan
   fakultas: pagu, pengajuan + RAB, persetujuan berjenjang, pencairan, SPJ dengan bukti, pengembalian sisa dana,
   kunci periode, dasbor & laporan), daftar dokumen di docs/vibecoding/, prasyarat (PHP 8.4, Composer 2,
   Node 22, Docker Desktop + WSL2, Git), dan bagian "Cara menjalankan" yang masih bertuliskan
   "akan dilengkapi di F1".
4. docs/keputusan/README.md — indeks Architecture Decision Record (ADR) berformat tabel: No, Judul, Status,
   Tanggal. Isi baris 0001 "Batas cakupan sistem keuangan" (Status: Usulan) dan 0002 "Parameter keuangan
   fakultas" (Status: Usulan).
5. CHANGELOG.md — format Keep a Changelog berbahasa Indonesia dengan bagian "## [Belum dirilis]".
6. .editorconfig — utf-8, lf, indent 4 spasi (2 untuk yml/yaml/json/js/css/md).

Jangan membuat berkas Laravel apa pun. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan
pint dan test, lalu tampilkan ringkasan perubahan. (Catatan: pint dan test belum tersedia pada langkah ini —
cukup tampilkan ringkasan dan isi `git status`.)
```

**Selesai bila:**
- [ ] `.githooks/commit-msg` dan `.githooks/pre-commit` identik dengan STANDAR-GIT §6 (`diff` dengan salinan manual tidak menunjukkan perbedaan isi).
- [ ] `.gitignore` memuat `.env`, `/vendor`, `storage/app/tmp/*`, `*.sql`.
- [ ] `CLAUDE.md` ada di root dan `docs/vibecoding/` berisi 9 berkas paket.
- [ ] `git status` tidak menampilkan `.env` atau berkas rahasia.

**Commit:**
```bash
# Laravel belum terpasang: pint & test dilewati pada fase F0
chmod +x .githooks/*
git add -A
git commit -m "chore: inisialisasi repositori"
```

### F0.2 — Klarifikasi batas cakupan (non-koding)
**Tujuan:** Memutuskan apakah sistem ini **menggantikan** aplikasi `laporankeuangan` (opsi a) atau **melengkapinya** (opsi b), dan menegaskan bahwa honor ujian tetap di `fkipapp`. Tidak ada kode yang ditulis sebelum keputusan ini disetujui.

Kegiatan manusia (wajib, 1–2 minggu):
1. Minta akses baca ke aplikasi `/laporankeuangan/` (kode atau minimal demo + contoh keluaran) dan inventarisasi fiturnya: data apa yang diinput, laporan apa yang dihasilkan, siapa penggunanya, ke mana laporannya dikirim.
2. Wawancarai: Admin Keuangan Fakultas, BPP, Wakil Dekan bidang keuangan, pengelola `laporankeuangan`, pengembang `fkipapp`.
3. Tanyakan ke pengembang `fkipapp`: apakah tersedia ekspor Excel rekap honor ujian per bulan × prodi × jenis ujian (kolom bruto, pajak, neto)?
4. Rapat keputusan; isi bagian "Keputusan" dokumen di bawah, termasuk siapa menyetujui dan tanggal.

**Prompt (salin ke agen AI):**
```text
Susun dokumen keputusan docs/keputusan/0001-batas-cakupan.md (format ADR, bahasa Indonesia) berdasarkan
docs/vibecoding/01-PRD.md §1.1 dan §9. Struktur:

# 0001 — Batas cakupan Sistem Keuangan FKIP
- Status: Usulan | Tanggal: (kosong) | Pengambil keputusan: (kosong)
## Konteks
  - Sistem baru mencakup siklus pagu → pengajuan + RAB → persetujuan → pencairan → SPJ → pengembalian → laporan.
  - fkipapp sudah menangani honor ujian (proposal, seminar, sidang).
  - Portal Support System FKIP sudah menautkan aplikasi "Laporan Keuangan" (/laporankeuangan/) yang kodenya
    belum kita miliki.
## Inventaris aplikasi laporankeuangan
  Tabel kosong untuk diisi manusia: Fitur | Pengguna | Data masukan | Keluaran/laporan | Dipakai siapa |
  Tumpang tindih dengan modul sistem baru (sebut kode modul PRD: pengajuan, spj, laporan, dll.)
## Opsi
  ### Opsi (a) — Sistem baru menggantikan laporankeuangan
     Keuntungan, risiko, kebutuhan migrasi data (tahun anggaran berjalan, saldo/rekap), periode paralel,
     pengalihan tautan portal, pengarsipan aplikasi lama baca-saja. Dampak ke rencana: langkah F12.4 memuat
     migrasi data.
  ### Opsi (b) — Sistem baru melengkapi laporankeuangan
     Pembagian sumber kebenaran per angka (tabel: Angka | Sumber kebenaran | Cara sinkron), format ekspor
     Excel dari sistem baru untuk laporankeuangan, dua tautan portal dengan label berbeda.
## Honor ujian (fkipapp)
  Pernyataan tegas: sistem ini TIDAK menghitung honor ujian (BR-24). Pilihan: (1) tidak ada data honor sama
  sekali, atau (2) impor rekap total per bulan × prodi × jenis ujian dari Excel fkipapp (langkah F11.5),
  baca-saja, dan apakah rekap itu mengurangi pagu unit (pengaturan honor_mengurangi_pagu, bawaan false).
## Kriteria keputusan
  Daftar periksa: kelengkapan fungsi, beban input ganda, kebutuhan pelaporan ke universitas, kapasitas
  pemeliharaan TI fakultas, risiko data historis.
## Keputusan
  (kosong — diisi setelah rapat) + "Konsekuensi" + "Tindak lanjut" (daftar tugas bernomor).
## Peserta rapat
  Tabel Nama | Jabatan | Paraf (kosong).

Perbarui baris 0001 di docs/keputusan/README.md bila judulnya berbeda. Jangan menulis kode. Jangan ubah berkas
di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
(pint/test belum tersedia — cukup ringkasan.)
```

**Selesai bila:**
- [ ] `docs/keputusan/0001-batas-cakupan.md` ada dan bagian **Keputusan** sudah diisi manusia (opsi a atau b), lengkap dengan nama pengambil keputusan dan tanggal; status berubah menjadi "Diterima".
- [ ] Pilihan untuk honor ujian (tanpa data / impor rekap) tertulis eksplisit.
- [ ] Bila opsi (a): daftar data yang harus dimigrasikan sudah ada.

**Commit:**
```bash
# Laravel belum terpasang: pint & test dilewati
git add -A
git commit -m "docs(keputusan): tetapkan batas cakupan sistem keuangan"
```

### F0.3 — Parameter keuangan dan daftar "perlu verifikasi" (non-koding)
**Tujuan:** Mengumpulkan nilai kebijakan yang akan menjadi data master/pengaturan, agar agen tidak mengarang angka.

**Prompt (salin ke agen AI):**
```text
Buat docs/keputusan/0002-parameter-keuangan.md berisi tabel parameter yang harus dikonfirmasi pihak keuangan
FKIP/Unsil sebelum F3. Kolom: No | Parameter | Nilai usulan sementara | Sumber/dasar (peraturan, SK) |
Status (Perlu verifikasi/Terkonfirmasi) | Dikonfirmasi oleh | Tanggal.

Baris wajib (ambil dari docs/vibecoding/01-PRD.md dan 03-SKEMA-DATABASE.md §4):
1. Status kelembagaan Unsil: PTN Satker atau PTN-BLU (pengaturan pola_pengelolaan) — perlu verifikasi.
2. Status PP 23/2005 jo. PP 74/2012 dan perubahan terkininya — perlu verifikasi.
3. Jenis pencairan yang dipakai di tingkat fakultas dan istilahnya (UP, TUP, LS) — perlu verifikasi.
4. Jenjang persetujuan: apakah ada verifikasi Kaprodi/Kepala Unit; siapa penyetuju akhir (Wakil Dekan bidang
   keuangan atau PPK); apakah BPP ada di fakultas.
5. Batas hari SPJ setelah kegiatan (usulan 14 hari kalender) dan dasar aturannya.
6. Format nomor pengajuan dan SPJ (usulan PGJ/{tahun}/{urut}, SPJ/{tahun}/{urut}).
7. Tarif referensi PPh 21, PPh 22, PPh 23, PPh 4(2), PPN beserta dasar hukum dan tanggal berlaku — semua perlu
   verifikasi; JANGAN mengisi angka sendiri, tulis "diisi bagian keuangan".
8. Berkas BAS (daftar kode akun) resmi yang dipakai Unsil dan sumbernya (PMK/KMK BAS terbaru — perlu verifikasi).
9. Daftar resmi unit/prodi FKIP beserta kode singkat dan Ketua/Kepala unit.
10. Kategori penggunaan dana untuk laporan akreditasi (LKPS/LAMDIK) — perlu verifikasi format.
11. Definisi "semester" pada laporan: semester anggaran (Jan–Jun, Jul–Des) atau semester akademik.
12. Retensi arsip dokumen keuangan (berapa tahun bukti disimpan) — perlu verifikasi.
13. Perlu tidaknya mencatat NPWP/NIK penerima untuk bukti potong — perlu verifikasi (privasi).
14. Siapa yang boleh membuka kunci periode (usulan: Wakil Dekan bidang keuangan).

Tambahkan bagian "Contoh dokumen yang dikumpulkan" berisi daftar berkas contoh (RAB, TOR/KAK, kuitansi,
format SPJ, laporan bulanan ke universitas) yang disimpan di docs/referensi/ — berkas berisi data pribadi
disimpan di docs/referensi/rahasia/ yang di-gitignore. Buat docs/referensi/README.md yang menjelaskan aturan ini.
Perbarui indeks docs/keputusan/README.md.

Jangan menulis kode. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test,
lalu tampilkan ringkasan perubahan. (pint/test belum tersedia — cukup ringkasan.)
```

**Selesai bila:**
- [ ] `docs/keputusan/0002-parameter-keuangan.md` memuat 14 baris parameter.
- [ ] Minimal parameter 4, 5, 6, dan 9 sudah berstatus "Terkonfirmasi" sebelum F3 dimulai (sisanya boleh menyusul, aplikasi memakai nilai usulan yang dapat diubah lewat pengaturan/master).
- [ ] Tidak ada berkas berdata pribadi di luar `docs/referensi/rahasia/`.

**Commit:**
```bash
# Laravel belum terpasang: pint & test dilewati
git add -A
git commit -m "docs(keputusan): catat parameter keuangan dan daftar verifikasi"
```

### F0.4 — Tutup fase F0
**Tujuan:** Menaruh repositori di remote (GitHub/GitLab kampus) dan melindungi `main`.

**Prompt (salin ke agen AI):**
```text
Periksa isi repositori untuk penutupan fase F0: pastikan .githooks/* executable (mode 100755 di git ls-files -s),
tidak ada berkas rahasia yang terlacak (cari pola password, token, .env, *.sql), dan CHANGELOG.md bagian
"[Belum dirilis]" memuat butir: inisialisasi repositori, ADR 0001, ADR 0002. Tambahkan butir yang kurang.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan. (pint/test belum tersedia — cukup ringkasan.)
```

**Selesai bila:**
- [ ] Remote `origin` terpasang dan `main` ter-*push*.
- [ ] Proteksi branch `main` aktif (wajib PR, wajib CI hijau — CI baru ada di F1.8, aktifkan syaratnya setelah itu).

**Commit:**
```bash
git add -A
git commit -m "docs(changelog): catat penyelesaian fase persiapan"
git remote add origin git@github.com:<organisasi>/keuangan-fkip.git
git push -u origin main
```

---

## F1 — Fondasi Laravel

### F1.1 — Proyek Laravel 13 di kontainer Docker FKIP
**Branch:** `feat/f1-fondasi`
**Tujuan:** Memasang Laravel 13 ke repositori yang sudah ada dan menjalankannya di Docker FKIP (STANDAR-TEKNIS §2): PHP-FPM, Nginx, dan scheduler per aplikasi; MySQL native di host; Redis & Mailpit bersama. Tanpa Sail, tanpa PHP di host.

Persiapan manual (infrastruktur bersama & database `keuangan`/`keuangan_testing` sudah dibuat menurut STANDAR-TEKNIS §2.2):
```bash
git switch -c feat/f1-fondasi
cd ~/code/keuangan
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 \
  create-project laravel/laravel _laravel "^13.0" --ignore-platform-reqs
```

**Prompt (salin ke agen AI):**
```text
Baca CLAUDE.md dan STANDAR-TEKNIS §2–§3 (di docs/). Laravel 13 ada di folder _laravel/ hasil create-project.
Semua perintah PHP/Composer/NPM dijalankan di container: kup = docker compose exec keuangan-php.
1. Pindahkan isi _laravel/ (termasuk berkas tersembunyi) ke root TANPA menimpa README.md, CLAUDE.md, CHANGELOG.md,
   .gitignore, .editorconfig, .gitattributes, .githooks/, .github/, docs/; gabungkan entri .gitignore bawaan Laravel
   yang belum ada ke .gitignore kita; hapus _laravel/. composer.json: "php": "^8.3", "laravel/framework": "^13.0".
2. Buat docker/php/Dockerfile, docker/nginx/default.conf, dan docker-compose.yml PERSIS templat STANDAR-TEKNIS §2.3
   dengan <app>=keuangan dan <port>=8014: service keuangan-php, keuangan-nginx, keuangan-scheduler; jaringan external
   fkip-net; extra_hosts host.docker.internal. JANGAN membuat container MySQL, Redis, atau Mailpit (sudah ada
   bersama) dan JANGAN memasang Laravel Sail. Service keuangan-horizon dibiarkan sebagai komentar sampai Horizon dipasang.
3. .env.example sesuai STANDAR-TEKNIS §3 tanpa nilai rahasia: APP_NAME="Keuangan FKIP Unsil", APP_URL=http://localhost:8014,
   DB_HOST=host.docker.internal, DB_DATABASE=keuangan, DB_USERNAME=keuangan, REDIS_HOST=redis, REDIS_CLIENT=phpredis,
   REDIS_PREFIX=keuangan_, REDIS_DB=32, REDIS_CACHE_DB=33, REDIS_QUEUE_DB=34, CACHE_PREFIX=keuangan_cache_,
   HORIZON_PREFIX=keuangan_horizon:, MAIL_HOST=mailpit, MAIL_PORT=1025. Salin ke .env (DB_PASSWORD diisi manusia,
   jangan di-commit), lalu kup php artisan key:generate.
4. README.md bagian "Menjalankan lokal": prasyarat infrastruktur bersama (STANDAR-TEKNIS §2.2), alias shell
   `alias kup='docker compose exec keuangan-php'`, docker compose up -d --build, kup php artisan migrate,
   kup npm install && kup npm run dev, aplikasi http://localhost:8014, Mailpit http://localhost:8025.
5. config/app.php: 'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'), 'locale' => env('APP_LOCALE', 'id'), 'fallback_locale' => 'en', 'faker_locale' => 'id_ID'; .env.example juga APP_LOCALE=id, APP_FALLBACK_LOCALE=en, APP_FAKER_LOCALE=id_ID, APP_TIMEZONE=Asia/Jakarta, APP_VERSION=0.1.0, MAIL_FROM_ADDRESS=keuangan-fkip@unsil.ac.id.
6. config/database.php: koneksi MySQL charset utf8mb4, collation utf8mb4_0900_ai_ci, 'strict' => true, engine InnoDB.
7. Terjemahan Indonesia: kup composer require laravel-lang/common --dev, lalu kup php artisan lang:add id dan kup php artisan lang:update; pesan validasi tampil berbahasa Indonesia.
8. tests/Feature/KonfigurasiDasarTest.php: config('app.timezone') === 'Asia/Jakarta' dan config('app.locale') === 'id'.
Jalankan docker compose up -d --build lalu kup php artisan migrate. Jangan ubah berkas di luar cakupan langkah
ini. Setelah selesai jalankan pint dan test di container (kup ./vendor/bin/pint && kup php artisan test), lalu
tampilkan ringkasan perubahan.
```

**Selesai bila:**
- `docker compose ps` menampilkan `keuangan-php`, `keuangan-nginx`, `keuangan-scheduler` berjalan; tidak ada container MySQL/Redis/Mailpit milik repo ini.
- `kup php artisan --version` = Laravel 13.x; `kup php -m` memuat pdo_mysql, redis, intl, gd, bcmath, pcntl, zip.
- `kup php artisan migrate` sukses ke MySQL host; `http://localhost:8014` menampilkan halaman Laravel.
- `kup php artisan tinker --execute="Cache::put('uji',1); echo Cache::get('uji');"` mencetak `1` (Redis bersama DB 33).
- `kup php artisan about` menunjukkan timezone `Asia/Jakarta` dan locale `id`.
- `.env` tidak muncul di `git status`; `README.md`/`CLAUDE.md`/`docs/` milik kita tidak tertimpa.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "build(docker): pasang laravel 13 di kontainer docker fkip"
```

### F1.2 — Redis untuk cache, sesi, antrean, dan Horizon
**Tujuan:** Mengatur indeks DB Redis sesuai standar dan memasang Horizon dengan antrean `notifikasi`, `ekspor`, `impor`, `default`.

**Prompt (salin ke agen AI):**
```text
Atur Redis dan Horizon sesuai docs/vibecoding/STANDAR-TEKNIS.md §1 dan docs/vibecoding/02-ARSITEKTUR.md §3.

1. config/database.php bagian 'redis': koneksi 'default' (REDIS_DB=32), 'cache' (REDIS_CACHE_DB=33), dan koneksi
   baru 'queue' (REDIS_QUEUE_DB=34). Prefix memakai Str::slug(APP_NAME).
2. config/queue.php: koneksi redis memakai 'connection' => 'queue', 'retry_after' => 660 (lebih besar dari timeout
   job ekspor 600 detik).
3. config/cache.php: store redis memakai koneksi 'cache', lock_connection 'default'.
4. composer require laravel/horizon; php artisan horizon:install. Di config/horizon.php buat dua supervisor
   untuk semua environment: 'supervisor-umum' queue ['notifikasi','default'] maxProcesses 2, dan
   'supervisor-berat' queue ['ekspor','impor'] maxProcesses 1, memory 512, timeout 600. Waktu tunggu
   (waits) redis:notifikasi 60 detik.
   Aktifkan service keuangan-horizon di docker-compose.yml (templat STANDAR-TEKNIS §2.3), lalu docker compose up -d.
5. HorizonServiceProvider::gate(): sementara kembalikan false untuk semua pengguna di luar environment local
   (gate berbasis peran super-admin dipasang di F2.5) — beri komentar singkat.
6. AppServiceProvider::boot(): Model::preventLazyLoading(! app()->isProduction());
   Model::preventSilentlyDiscardingAttributes(! app()->isProduction()); DB::prohibitDestructiveCommands(app()->isProduction()).
7. phpunit.xml: DB_HOST=host.docker.internal, DB_DATABASE=keuangan_testing, CACHE_STORE=array, SESSION_DRIVER=array, QUEUE_CONNECTION=sync,
   MAIL_MAILER=array, BCRYPT_ROUNDS=4.
8. Test tests/Feature/RedisKonfigurasiTest.php: memastikan config('queue.connections.redis.connection') === 'queue',
   dan config('database.redis.cache.database') bernilai '1' dari env.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Service `keuangan-horizon` berjalan dan `http://localhost:8014/horizon` terbuka.
- [ ] `docker exec redis redis-cli -n 34 keys '*'` menunjukkan kunci antrean setelah `dispatch` job uji.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "build: konfigurasi redis dan pasang horizon"
```

### F1.3 — Pest 4, Larastan level 6, Pint, dan aktivasi hook
**Tujuan:** Memasang gerbang kualitas dan mengaktifkan hook git dari F0.1.

**Prompt (salin ke agen AI):**
```text
Pasang perangkat kualitas:
1. composer require pestphp/pest:^4.0 pestphp/pest-plugin-laravel brianium/paratest --dev --with-all-dependencies;
   jalankan ./vendor/bin/pest --init; ubah test contoh menjadi sintaks Pest. tests/Pest.php memakai
   RefreshDatabase untuk folder Feature.
2. composer require larastan/larastan --dev; buat phpstan.neon: includes larastan extension, level 6, paths
   app/ config/ database/ routes/, tmpDir build/phpstan; tambahkan build/ ke .gitignore.
3. pint.json dengan preset "laravel".
4. composer.json scripts: "lint": "pint --test", "analyse": "phpstan analyse --memory-limit=1G",
   "test": "php artisan test --parallel", "cek": ["@lint", "@analyse", "@test"].
5. tests/Arch/ArsitekturTest.php (Pest arch): app/Enums hanya berisi enum; tidak ada dd/dump/ray/var_dump di
   app/; App\Models tidak memakai facade DB secara langsung kecuali di scope; preset php dan security Pest.
6. Pastikan php artisan test --parallel berjalan dengan database MySQL host 'keuangan_testing' (DB_HOST=host.docker.internal;
   pengguna keuangan berhak pada pola keuangan\_testing% menurut STANDAR-TEKNIS §2.2).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

Setelah agen selesai, aktifkan hook (sekali per klon):
```bash
chmod +x .githooks/*
git config core.hooksPath .githooks
```

**Selesai bila:**
- [ ] `composer cek` hijau (pint, phpstan level 6, pest paralel).
- [ ] Commit uji dengan pesan `update` ditolak hook `commit-msg`.
- [ ] `git config core.hooksPath` bernilai `.githooks`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "build: pasang pest larastan dan pint"
```

### F1.4 — Kunci primer UUIDv7
**Tujuan:** Semua tabel aplikasi memakai UUIDv7 sebagai primary key sejak awal (STANDAR-TEKNIS §4a), sebelum tabel domain dibuat.

**Prompt (salin ke agen AI):**
```text
Baca STANDAR-TEKNIS §4a. 1) Migrasi bawaan: users → $table->uuid('id')->primary(); sessions →
foreignUuid('user_id')->nullable()->index(). Model User memakai Illuminate\Database\Eloquent\Concerns\HasUuids
(Laravel 13: UUIDv7). 2) Untuk paket yang SUDAH terpasang (spatie/laravel-permission, spatie/laravel-activitylog,
tabel notifications, Sanctum personal_access_tokens, tabel impor/ekspor Filament) sesuaikan migrasi & model
kustomnya PERSIS §4a butir 5; paket yang dipasang di langkah berikutnya disesuaikan pada langkah pemasangannya.
3) tests/Arch/UuidTest.php: semua kelas di app/Models memakai HasUuids; tidak ada migrasi (kecuali tabel
infrastruktur §4a butir 6) yang memuat ->id(), foreignId(, morphs( tanpa awalan uuid/nullableUuid, bigIncrements(,
atau increments(. 4) UserFactory & seeder tetap berjalan. 5) php artisan migrate:fresh (belum ada data).
Test: User::factory()->create()->id lolos Str::isUuid() dan berversi 7 (karakter ke-15 = '7'); route model
binding dengan UUID berjalan. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan
test, lalu tampilkan ringkasan perubahan.
```

**Selesai bila:** `UuidTest` hijau; kolom `users.id` bertipe CHAR(36); tidak ada `->id()` di migrasi aplikasi.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "refactor(db): pakai uuidv7 sebagai primary key semua tabel"
```

### F1.5 — Siap subpath `https://supportfkip.unsil.ac.id/keuangan`
**Tujuan:** Aplikasi berjalan benar di bawah awalan `/keuangan` — di produksi (reverse proxy) dan di lokal lewat gateway — sehingga URL, aset Vite, cookie, Livewire, Filament, Horizon, dan QR memakai awalan yang sama (STANDAR-TEKNIS §2.4).

Persiapan manual: gateway lokal (STANDAR-TEKNIS §2.4) berjalan dan berisi blok `location /keuangan/`; `keuangan-nginx` tersambung ke jaringan `fkip-net` (templat §2.3).

**Prompt (salin ke agen AI):**
```text
Baca STANDAR-TEKNIS §2.4. Perintah PHP lewat kup (docker compose exec keuangan-php).
1. .env.example (dan .env lokal): APP_URL=http://localhost:8080/keuangan (komentar: produksi
   https://supportfkip.unsil.ac.id/keuangan), ASSET_URL sama dengan APP_URL, SESSION_PATH=/keuangan,
   SESSION_COOKIE=keuangan_session, SESSION_SECURE_COOKIE=false (produksi true), TRUSTED_PROXIES=*.
2. AppServiceProvider::boot(): bila parse_url(APP_URL) memiliki path → URL::forceRootUrl(config('app.url'));
   bila skema https → URL::forceScheme('https'). bootstrap/app.php: trustProxies(at: env('TRUSTED_PROXIES'),
   headers: X-Forwarded-For | Host | Port | Proto | Prefix).
3. Build aset dengan ASSET_URL terisi (kup npm run build) sehingga laravel-vite-plugin memakai base /keuangan/build/;
   catat di README. Bila Livewire/Filament/Horizon sudah terpasang, pastikan URL update & skrip Livewire, aset
   Filament, path panel, dan Horizon berawalan /keuangan (cek API via Boost; Livewire::setUpdateRoute/setScriptRoute bila
   perlu). Bila belum terpasang, langkah pemasangannya wajib mengikuti aturan ini.
4. tests/Arch/SubpathTest.php: pindai resources/views dan resources/js — gagal bila ada href="/…", src="/…",
   action="/…", fetch('/…') atau axios ke "/…" (kecuali "//" dan "#").
5. tests/Feature/SubpathTest.php: dengan config app.url=https://supportfkip.unsil.ac.id/keuangan dan forceRootUrl
   aktif, url('/') dan asset('build/app.js') berawalan https://supportfkip.unsil.ac.id/keuangan; respons halaman awal
   memasang cookie keuangan_session ber-path /keuangan; redirect tamu ke login tetap di bawah /keuangan.
6. README: akses lokal lewat http://localhost:8080/keuangan/ dan blok location reverse proxy produksi (salin §2.4).
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- `http://localhost:8080/keuangan/` tampil lengkap; DevTools Network tidak menunjukkan permintaan ke `/build/…`, `/livewire/…`, atau `/css/…` tanpa awalan `/keuangan`.
- Cookie sesi bernama `keuangan_session` dengan path `/keuangan`; aplikasi lain di gateway tidak saling menimpa sesi.
- `SubpathTest` (arsitektur & feature) hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(config): siapkan aplikasi untuk subpath supportfkip"
```

### F1.6 — Laravel Boost untuk agen AI
**Tujuan:** Memberi agen MCP server dan pedoman versi-spesifik, tanpa menghilangkan isi `CLAUDE.md` paket ini.

Langkah manusia: salin dulu `CLAUDE.md` ke `/tmp/CLAUDE.paket.md`, lalu jalankan:
```bash
kup composer require laravel/boost --dev
kup php artisan boost:install    # pilih Claude Code; aktifkan pedoman Laravel, Filament, Livewire, Pest, Tailwind
```

**Prompt (salin ke agen AI):**
```text
Laravel Boost baru dipasang dan mungkin menulis/menambah CLAUDE.md serta .mcp.json. Bandingkan CLAUDE.md
sekarang dengan /tmp/CLAUDE.paket.md. Hasil akhir CLAUDE.md WAJIB: seluruh isi CLAUDE.md paket di bagian atas
(utuh, tidak diringkas), lalu blok pedoman Boost di bawahnya apa adanya. Bila Boost menimpa isi paket,
kembalikan isinya. Pastikan .mcp.json terlacak git (tidak berisi rahasia). Tambahkan ke README.md cara
memperbarui pedoman: php artisan boost:update. Uji MCP: jalankan php artisan boost:mcp --help (atau perintah
yang setara di versi terpasang) dan laporkan hasilnya.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `CLAUDE.md` memuat bagian "Aturan bisnis ringkas" dari paket **dan** pedoman Boost.
- [ ] Agen dapat memanggil tool Boost (mis. `search-docs`, `database-schema`) di sesi berikutnya.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "chore: pasang laravel boost untuk agen ai"
```

### F1.7 — Endpoint kesehatan dan versi aplikasi
**Tujuan:** Menyediakan `GET /api/health` (STANDAR-TEKNIS §6) untuk pemantauan dan deploy.

**Prompt (salin ke agen AI):**
```text
Buat App\Http\Controllers\HealthController (invokable) dan route GET /api/health bernama 'health' di
routes/api.php (pasang routes API dengan php artisan install:api bila belum ada, tanpa Sanctum token bila tidak
diperlukan — cukup routing). Respons JSON: {"status":"ok"|"gagal","db":true|false,"redis":true|false,
"versi":config('app.version'),"waktu":now()->toIso8601String()}. Cek DB dengan DB::select('select 1') dan Redis
dengan Redis::connection()->ping(), masing-masing dalam try/catch; HTTP 200 bila semua true, 503 bila ada yang
gagal. Tambahkan 'version' => env('APP_VERSION', '0.0.0') di config/app.php. Rate limit 60/menit per IP.
Jangan bocorkan pesan exception di respons.

Test Pest tests/Feature/HealthTest.php: (1) 200 dengan struktur JSON lengkap; (2) 503 bila koneksi Redis
dipalsukan gagal (mock Redis facade); (3) tidak memerlukan login.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `curl -s localhost:8014/api/health` mengembalikan `"status":"ok"` dan versi `0.1.0`.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(inti): tambah endpoint kesehatan api health"
```

### F1.8 — Continuous Integration
**Tujuan:** Setiap PR menjalankan pint, phpstan, dan pest dengan MySQL 8.4 dan Redis 7 (STANDAR-GIT §8).

**Prompt (salin ke agen AI):**
```text
Buat .github/workflows/ci.yml (GitHub Actions; bila repo di GitLab, buat .gitlab-ci.yml setara) yang berjalan
pada pull_request dan push ke main:
- services: mysql:8.4 (MYSQL_DATABASE=testing, MYSQL_ROOT_PASSWORD=secret, health-cmd mysqladmin ping),
  redis:7-alpine.
- shivammathur/setup-php@v2 dengan php-version 8.4, extensions: mbstring, intl, bcmath, gd, zip, pdo_mysql,
  redis; coverage none.
- cache composer; composer install --no-interaction --prefer-dist.
- cp .env.example .env; php artisan key:generate; set env DB_HOST=127.0.0.1, DB_DATABASE=testing,
  DB_USERNAME=root, DB_PASSWORD=secret, REDIS_HOST=127.0.0.1.
- langkah: ./vendor/bin/pint --test; ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress;
  php artisan test --parallel.
- npm ci && npm run build (Node 22) sebagai job terpisah 'aset'.
Tambahkan lencana status CI di README.md.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Workflow CI hijau pada PR fase ini.
- [ ] Aturan proteksi `main` mewajibkan CI hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "ci: tambah workflow pint phpstan pest"
```

### F1.9 — Tutup fase F1
**Tujuan:** Merge fondasi ke `main` dan memberi tag `v0.1.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau seluruh perubahan branch feat/f1-fondasi terhadap main (git diff main...HEAD --stat). Pastikan: tidak ada
.env/rahasia terlacak, composer cek hijau, README "Cara menjalankan" lengkap (Docker FKIP, Horizon, Mailpit,
composer cek). Perbarui CHANGELOG.md: pindahkan butir F1 ke bagian "## [0.1.0] - <tanggal hari ini>" dengan
subbagian "Ditambahkan". Setel APP_VERSION=0.1.0 di .env.example.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PR `feat/f1-fondasi → main` di-merge dengan CI hijau.
- [ ] Tag `v0.1.0` ada di remote.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.1.0 fondasi laravel"
git push -u origin feat/f1-fondasi
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.1.0 -m "Fase 1: fondasi Laravel"
git push origin main --tags
```

---

## F2 — Autentikasi, peran & audit

### F2.1 — Panel Filament 5 dan kolom pengguna
**Branch:** `feat/f2-auth-audit`
**Tujuan:** Memasang panel `keuangan` di `/panel` dan menambah kolom pengguna sesuai STANDAR-TEKNIS §6.

**Prompt (salin ke agen AI):**
```text
Pasang Filament 5 (pakai search-docs Boost untuk perintah versi 5):
composer require filament/filament:"^5.0" lalu php artisan filament:install --panels dengan panel id 'keuangan'
dan path 'panel'. Di KeuanganPanelProvider: ->login(), warna primer Amber, brandName 'Keuangan FKIP Unsil',
->databaseNotifications() dengan polling 30s, ->spa(), footer menampilkan "Keuangan FKIP Unsil v{config('app.version')}"
melalui render hook, navigationGroups berurutan: Beranda, Kegiatan, Bendahara, Anggaran, Laporan, Master, Sistem.
Route '/' redirect ke '/panel'.

Migrasi baru (migrasi users bawaan sudah memakai uuid sejak F1, STANDAR-TEKNIS §4a): tambah kolom ke tabel users:
nip VARCHAR(30) null index, no_hp VARCHAR(20) null,
kode_eksternal VARCHAR(50) null index, aktif BOOLEAN default true. (unit_id ditambahkan di F3.2.)
Model User: implements FilamentUser; canAccessPanel() mengembalikan $this->aktif; trait HasUuids
(primary key UUIDv7); cast aktif boolean; $hidden password, remember_token.
Buat UserFactory dengan faker id_ID.

Test Pest: pengguna aktif bisa membuka /panel setelah login; pengguna aktif=false ditolak (403); tamu
diarahkan ke /panel/login; footer menampilkan versi.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `/panel/login` tampil berbahasa Indonesia; login berhasil dengan pengguna buatan `kup php artisan make:filament-user`.
- [ ] Kolom `nip`, `no_hp`, `kode_eksternal`, `aktif` ada di `users`.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(auth): pasang panel filament keuangan dan kolom pengguna"
```

### F2.2 — Peran dan izin (spatie/laravel-permission)
**Tujuan:** Membuat 7 peran dan izin `<modul>.<aksi>` sesuai matriks PRD §3.2.

**Prompt (salin ke agen AI):**
```text
composer require spatie/laravel-permission; publikasikan config & migrasi; User memakai trait HasRoles.
Sesuaikan migrasi & model paket ini ke UUID PERSIS STANDAR-TEKNIS §4a butir 5 sebelum migrate.
Buat App\Enums\Peran (string-backed): SuperAdmin='super-admin', AdminKeuangan='admin-keuangan', Bpp='bpp',
WadekKeuangan='wadek-keuangan', KepalaUnit='kepala-unit', AdminUnit='admin-unit', Pimpinan='pimpinan', dengan
label Indonesia (bpp → "Bendahara Pengeluaran Pembantu", wadek-keuangan → "Wakil Dekan Bidang Keuangan / PPK").

database/seeders/PeranDanIzinSeeder.php: buat izin untuk modul [pengguna, audit, master, pengaturan, pagu,
pengajuan, persetujuan, revisi, pencairan, spj, pajak, pengembalian, periode, laporan, dasbor, impor] × aksi
[lihat, buat, ubah, hapus, setujui, ekspor] HANYA yang dipakai matriks docs/vibecoding/01-PRD.md §3.2, lalu
berikan ke peran sesuai matriks (cakupan "(u)" tetap diberi izin; pembatasan unit dilakukan Policy nanti).
Seeder idempoten (firstOrCreate) dan memanggil app()[PermissionRegistrar::class]->forgetCachedPermissions().
PenggunaAwalSeeder: super-admin dari env SEED_ADMIN_EMAIL & SEED_ADMIN_PASSWORD (tambahkan kunci kosong di
.env.example); gagal dengan pesan jelas bila env kosong. DatabaseSeeder memanggil keduanya.
Cache permission memakai store redis (config permission.cache.store = 'redis').

Test Pest: (1) seeder menghasilkan 7 peran; (2) admin-unit punya pengajuan.buat tetapi tidak punya
persetujuan.setujui; (3) pimpinan tidak punya izin buat/ubah/hapus apa pun; (4) super-admin tidak punya
persetujuan.setujui (pemisahan tugas); (5) seeder dijalankan dua kali tidak menggandakan data.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `kup php artisan db:seed` membuat 7 peran dan akun super-admin.
- [ ] `kup php artisan permission:show` sesuai matriks PRD.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(auth): tambah peran dan izin keuangan dengan seeder"
```

### F2.3 — Jejak audit: activitylog dan tabel riwayat status
**Tujuan:** Menyediakan audit perubahan nilai dan riwayat transisi status (BR-08).

**Prompt (salin ke agen AI):**
```text
1. composer require spatie/laravel-activitylog; publikasikan migrasi & config. Matikan pembersihan otomatis
   (jangan jadwalkan activitylog:clean). Buat trait App\Concerns\TercatatAktivitas yang memakai LogsActivity
   dengan logAll()->logExcept(['password','remember_token','app_authentication_secret',
   'app_authentication_recovery_codes'])->logOnlyDirty()->dontSubmitEmptyLogs(), useLogName = nama tabel.
   Pasang di User.
   Sesuaikan migrasi & model paket ini ke UUID PERSIS STANDAR-TEKNIS §4a butir 5 sebelum migrate.
2. Migrasi tabel riwayat_status persis docs/vibecoding/03-SKEMA-DATABASE.md §2.1 (tanpa updated_at, index
   (model_type, model_id, created_at)). Model App\Models\RiwayatStatus ($table='riwayat_status',
   const UPDATED_AT = null, relasi morphTo 'model', belongsTo user). Daftarkan morph map di AppServiceProvider
   dengan Relation::enforceMorphMap (alias 'user' sekarang; alias lain ditambah saat modelnya dibuat).
3. Enum App\Enums\TahapPersetujuan: kepala_unit, keuangan, wadek, bendahara (label Indonesia).
4. Trait App\Concerns\MemilikiRiwayatStatus: relasi riwayatStatus() morphMany terurut created_at.
5. Action App\Actions\Audit\CatatRiwayatStatus::handle(Model $model, ?BackedEnum $dari, BackedEnum $ke,
   ?User $pengguna, ?string $catatan = null, ?TahapPersetujuan $tahap = null): RiwayatStatus — WAJIB dipanggil
   di dalam DB::transaction (lempar LogicException bila DB::transactionLevel() === 0). Setelah tersimpan,
   dispatch event App\Events\StatusBerubah($model, $dari, $ke, $pengguna, $tahap, $catatan) dengan
   ShouldDispatchAfterCommit (listener notifikasi dipasang di F10).
6. Test Pest: riwayat tersimpan lengkap; LogicException bila dipanggil di luar transaksi; event StatusBerubah
   terkirim setelah commit dan TIDAK terkirim bila transaksi di-rollback (Event::fake); activitylog User tidak
   memuat password.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Tabel `activity_log` dan `riwayat_status` ada.
- [ ] Mengubah nama pengguna menghasilkan entri `activity_log` tanpa hash kata sandi.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(audit): tambah activitylog dan riwayat status"
```

### F2.4 — Fondasi domain: uang, lock Redis, penomoran
**Tujuan:** Helper lintas modul yang dipakai semua Action: aritmetika uang presisi (BR-01), lock Redis (BR-09), penomoran berurutan (BR-05).

**Prompt (salin ke agen AI):**
```text
Buat fondasi domain (baca CLAUDE.md bagian Aturan bisnis):
1. composer require brick/math (nyatakan eksplisit). App\Support\Uang dengan metode statis:
   kali(string $volume, string $harga): string (BigDecimal, pembulatan RoundingMode::HALF_UP, skala 2),
   jumlahkan(iterable<string> $nilai): string, kurang(string $a, string $b): string,
   bandingkan(string $a, string $b): int, format(string $nilai): string → "Rp1.234.567,00"
   (NumberFormatter id_ID atau manual). Semua masukan/keluaran string desimal; larang float (lempar
   InvalidArgumentException bila menerima float).
2. App\Support\DenganKunci::jalankan(string $kunci, Closure $fn, int $ttl = 15, int $tunggu = 3): mixed —
   memakai Cache::store('redis')->lock("lock:{$kunci}", $ttl)->block($tunggu, $fn); bila gagal memperoleh lock
   (LockTimeoutException) lempar App\Exceptions\AksiSedangDiprosesException("Sedang diproses, coba lagi.").
   Di environment testing, gunakan store default (array) agar test tidak butuh Redis.
3. Migrasi tabel penomoran (03-SKEMA §2.1) + model Penomoran. App\Support\Penomoran::berikutnya(string $jenis,
   int $tahun, string $format): string — di dalam DenganKunci("penomoran:{$jenis}:{$tahun}") dan
   DB::transaction: firstOrCreate baris, lockForUpdate, naikkan nomor_terakhir, kembalikan format dengan
   placeholder {tahun} dan {urut} (urut 4 digit, zero-pad).
4. Exception domain di app/Exceptions: AksiSedangDiprosesException, PeriodeTerkunciException,
   PaguTidakCukupException, PelanggaranPemisahanTugasException, TransisiStatusTidakSahException — semuanya
   turunan App\Exceptions\KesalahanDomain (RuntimeException) dengan pesan berbahasa Indonesia yang aman
   ditampilkan ke pengguna.
5. Test Pest: Uang::kali('2','150000.00') === '300000.00'; Uang::kali('0.5','33333.33') === '16666.67'
   (HALF_UP); jumlahkan 1000 item 0.01 === '10.00'; format('1234567.5') === 'Rp1.234.567,50'; lempar
   exception bila float. Penomoran: 50 panggilan berurutan menghasilkan 50 nomor unik berurutan
   PGJ/2027/0001..0050; tahun berbeda mulai dari 0001. DenganKunci: panggilan kedua saat lock dipegang
   melempar AksiSedangDiprosesException (simulasikan dengan Cache::lock()->get() manual).
6. Test arsitektur: App\Support\Uang tidak memakai fungsi round/floatval; app/ tidak memakai tipe float pada
   properti/parameter bernama jumlah, harga, total, nilai, pagu (cek dengan arch() atau test refleksi sederhana).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Test `Uang` dan `Penomoran` hijau, termasuk kasus pembulatan.
- [ ] Tabel `penomoran` ada dengan indeks unik `(jenis, tahun)`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(inti): tambah helper uang, lock redis, dan penomoran"
```

### F2.5 — Policy dasar, manajemen pengguna, gerbang Horizon
**Tujuan:** Super-admin dapat mengelola pengguna dan peran; Horizon hanya untuk super-admin.

**Prompt (salin ke agen AI):**
```text
1. App\Policies\UserPolicy berbasis izin pengguna.lihat/buat/ubah/hapus; pengguna tidak boleh menghapus dirinya
   sendiri; hanya super-admin yang boleh memberi peran super-admin.
2. Filament UserResource (grup Sistem): kolom tabel name, email, nip, peran (badge), aktif (toggle);
   filter peran & aktif; form: name, email (unik, wajib domain @unsil.ac.id kecuali environment local/testing),
   nip, no_hp, kode_eksternal, aktif, roles (CheckboxList dari Enum Peran). Route record memakai id.
   Aksi "Reset MFA" (dipakai F2.6) disiapkan sebagai tombol tersembunyi bila kolom belum ada.
3. Gate::before di AppServiceProvider TIDAK dipakai untuk memberi super-admin akses semua (pemisahan tugas);
   super-admin hanya mendapat izin yang diberikan seeder.
4. HorizonServiceProvider gate viewHorizon: $user->hasRole('super-admin').
5. Test Pest: super-admin bisa membuka daftar & membuat pengguna; admin-keuangan bisa melihat daftar tetapi
   tidak bisa membuat; admin-unit mendapat 403 pada /panel/users; email non-unsil ditolak; /horizon 403 untuk
   admin-keuangan dan 200 untuk super-admin; pengguna tidak dapat menghapus dirinya.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Menu "Pengguna" hanya tampil untuk super-admin & admin-keuangan (lihat saja).
- [ ] `/horizon` hanya bisa dibuka super-admin.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(auth): tambah manajemen pengguna dan gerbang horizon"
```

### F2.6 — MFA untuk peran keuangan dan rate limit login
**Tujuan:** Menegakkan MFA untuk `super-admin`, `admin-keuangan`, `bpp`, `wadek-keuangan`, dan membatasi percobaan login.

**Prompt (salin ke agen AI):**
```text
Gunakan search-docs Boost untuk fitur Multi-Factor Authentication Filament 5 (App Authentication / TOTP).
1. Migrasi: tambah app_authentication_secret TEXT null dan app_authentication_recovery_codes TEXT null pada
   users; cast 'encrypted' dan 'encrypted:array'. User mengimplementasikan kontrak MFA Filament yang sesuai.
2. KeuanganPanelProvider: aktifkan multiFactorAuthentication dengan AppAuthentication (recovery codes aktif).
   Wajibkan MFA (isMultiFactorAuthenticationRequired / middleware kustom App\Http\Middleware\WajibMfa) hanya
   untuk peran super-admin, admin-keuangan, bpp, wadek-keuangan: bila belum mengaktifkan, alihkan ke halaman
   pengaturan MFA dan blokir halaman lain.
3. Rate limiter 'login' 5 percobaan/menit per kombinasi email+IP; terapkan pada halaman login Filament
   (Filament punya throttling bawaan — pastikan nilainya 5/menit, sesuaikan bila perlu). Pesan Indonesia.
4. UserResource: aksi "Reset MFA" (super-admin saja, konfirmasi, tercatat activitylog) mengosongkan kedua kolom.
5. Test Pest: admin-keuangan tanpa MFA diarahkan ke pengaturan MFA saat membuka /panel (dasbor); admin-unit tanpa MFA tetap bisa masuk; percobaan login ke-6 dalam semenit ditolak; Reset MFA hanya
   oleh super-admin; secret tersimpan terenkripsi (nilai di DB ≠ plaintext).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Login akun `admin-keuangan` memaksa pemindaian QR autentikator sebelum bisa memakai panel.
- [ ] Percobaan login ke-6 dalam 1 menit ditolak.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(auth): wajibkan mfa peran keuangan dan batasi login"
```

### F2.7 — Tutup fase F2
**Tujuan:** Merge dan tag `v0.2.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f2-auth-audit terhadap main. Pastikan: semua Resource memiliki Policy; tidak ada logika
otorisasi di Blade; seeder idempoten; composer cek hijau. Perbarui CHANGELOG.md bagian [0.2.0] dan
APP_VERSION=0.2.0 di .env.example. Perbarui README: cara membuat super-admin pertama (SEED_ADMIN_*) dan
mengaktifkan MFA.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PR di-merge dengan CI hijau; tag `v0.2.0` ada.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.2.0 autentikasi peran dan audit"
git push -u origin feat/f2-auth-audit
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.2.0 -m "Fase 2: autentikasi, peran & audit"
git push origin main --tags
```

---

## F3 — Master data & pagu

Prasyarat: parameter 4, 5, 6, 9 di `docs/keputusan/0002-parameter-keuangan.md` sudah terkonfirmasi.

### F3.1 — Tahun anggaran, periode bulanan, dan guard periode terkunci
**Branch:** `feat/f3-master-pagu`
**Tujuan:** Master tahun anggaran yang otomatis membuat 12 periode, plus trait penjaga periode terkunci yang dipakai semua tabel transaksi (BR-19, BR-20).

**Prompt (salin ke agen AI):**
```text
1. Migrasi tahun_anggaran dan periode sesuai docs/vibecoding/03-SKEMA-DATABASE.md §2.2. Enum
   StatusTahunAnggaran (perencanaan, berjalan, ditutup) dan StatusPeriode (terbuka, terkunci) dengan HasLabel,
   HasColor, dan bolehBerpindahKe(). Model TahunAnggaran ($table='tahun_anggaran', SoftDeletes,
   TercatatAktivitas, MemilikiRiwayatStatus) hasMany periode; Model Periode ($table='periode').
   Kolom status TIDAK dimasukkan ke $fillable.
2. Action App\Actions\Master\BuatTahunAnggaran::handle(int $tahun, User $oleh): TahunAnggaran — dalam transaksi
   membuat tahun (1 Jan–31 Des, status perencanaan) dan 12 periode bernama "Januari 2027" dst. (Carbon locale id),
   mencatat riwayat status.
3. Action UbahStatusTahunAnggaran (perencanaan→berjalan→ditutup, satu arah) dengan CatatRiwayatStatus.
4. Static helper Periode::untukTanggal(CarbonInterface|string $tanggal): Periode (lempar KesalahanDomain bila
   tidak ada periode untuk tanggal itu).
5. Trait App\Concerns\MenjagaPeriodeTerkunci: model yang memakainya mendefinisikan
   protected function kolomTanggalPeriode(): array (mis. ['tanggal']). Pada event saving dan deleting, untuk
   nilai tanggal LAMA (getOriginal) dan BARU, cari periodenya; bila status terkunci → lempar
   PeriodeTerkunciException("Periode {nama} terkunci."); bila tahun anggaran ditutup → lempar KesalahanDomain.
   Bila model punya kolom periode_id, isi otomatis dari tanggal baru.
6. Morph map: 'tahun_anggaran', 'periode'.
7. Filament TahunAnggaranResource (grup Anggaran): create memanggil BuatTahunAnggaran; aksi ubah status;
   PeriodeRelationManager baca-saja (aksi kunci/buka dibuat di F9). Policy: master.* untuk admin-keuangan;
   lihat untuk peran lain.
8. Test Pest: membuat tahun 2027 menghasilkan 12 periode dengan tanggal benar (Februari 28 hari; uji 2028 →
   29 hari); tahun ganda ditolak; transisi status mundur ditolak; trait: buat model uji sementara (tabel uji di
   test) yang memakai trait → simpan pada periode terkunci melempar PeriodeTerkunciException, memindahkan
   tanggal DARI periode terkunci juga ditolak; admin-unit tidak bisa membuat tahun anggaran.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Membuat "Tahun Anggaran 2027" di panel menghasilkan 12 periode `terbuka`.
- [ ] Test trait `MenjagaPeriodeTerkunci` hijau.
- [ ] Admin-unit tidak melihat tombol "Buat" tahun anggaran.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(master): tambah tahun anggaran, periode, dan guard periode terkunci"
```

### F3.2 — Master unit dan pembatasan data per unit
**Tujuan:** Unit/prodi sebagai pemilik anggaran; pengguna unit hanya melihat data unitnya (BR-21).

**Prompt (salin ke agen AI):**
```text
1. Migrasi tabel unit (03-SKEMA §2.2) dan migrasi baru menambah users.unit_id (FK unit, nullOnDelete).
   Enum JenisUnit (prodi, laboratorium, unit_pendukung, pimpinan, lainnya). Model Unit ($table='unit',
   SoftDeletes, HasUuids, TercatatAktivitas) belongsTo kepala (User); User belongsTo unit.
2. Trait App\Concerns\DibatasiUnit untuk model yang punya unit_id: scope scopeTerlihatOleh(Builder $q, User $u):
   bila $u hanya berperan admin-unit dan/atau kepala-unit (tanpa peran fakultas: admin-keuangan, bpp,
   wadek-keuangan, pimpinan, super-admin) → where unit_id = $u->unit_id; selain itu tanpa batas.
   Sediakan juga helper User::lingkupFakultas(): bool.
3. Validasi UserResource: peran admin-unit/kepala-unit WAJIB punya unit_id (pesan "Unit wajib diisi untuk peran
   admin-unit/kepala-unit").
4. UnitResource (grup Master): CRUD untuk admin-keuangan; kolom kode, nama, jenis, jenjang, kepala, aktif.
   Cache 'master:unit:aktif' 24 jam, dihapus oleh observer saat unit disimpan/dihapus.
5. UnitContohSeeder (lokal/staging saja, beri komentar "CONTOH — ganti dengan daftar resmi unit FKIP"):
   DEKANAT, TU-FKIP, PMAT (Pendidikan Matematika), PBIO (Pendidikan Biologi), PBI (Pendidikan Bahasa Inggris).
   Impor daftar resmi dilakukan lewat Filament ImportAction (UnitImporter, kolom kode,nama,jenis,jenjang);
   berkas CSV/XLSX impor hanya sementara (livewire-tmp/storage/app/tmp) dan tidak disimpan permanen.
6. Test Pest: scope terlihatOleh untuk admin-unit PMAT hanya mengembalikan baris PMAT; kepala-unit sama;
   pimpinan melihat semua; pengguna admin-unit tanpa unit ditolak validasi; cache unit terhapus setelah
   unit diubah; impor unit membuat/memperbarui berdasarkan kode.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Unit dapat diimpor dari Excel dan tampil di panel.
- [ ] Pengguna `admin-unit` wajib memiliki unit.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(master): tambah master unit dan pembatasan data per unit"
```

### F3.3 — Akun BAS, kategori penggunaan dana, dan impor Excel
**Tujuan:** Master kode akun diisi lewat impor; seeder hanya contoh yang ditandai.

**Prompt (salin ke agen AI):**
```text
1. Migrasi akun dan kategori_penggunaan_dana (03-SKEMA §2.2). Model Akun ($table='akun', SoftDeletes,
   TercatatAktivitas), KategoriPenggunaanDana. Kolom akun.sumber: impor|manual|contoh.
2. Filament AkunResource (grup Master) dengan ImportAction memakai App\Filament\Imports\AkunImporter
   (kolom: kode wajib 6 digit angka, nama wajib, kelompok opsional — bila kosong isi 3 digit pertama kode,
   jenis_belanja opsional). Kode yang sudah ada → perbarui nama/kelompok, set sumber='impor'. Impor berjalan di
   antrean 'impor'; berkas impor tidak disimpan permanen (dihapus setelah diproses — ini satu-satunya
   pengecualian input berkas, STANDAR-TEKNIS §1a). Tabel menampilkan lencana "CONTOH" merah untuk sumber=contoh. Filter aktif, kelompok, sumber.
   Akun yang sudah dipakai rab_item (kelak) tidak boleh dihapus — siapkan method Akun::sedangDipakai() yang
   sementara mengembalikan false dan beri komentar bahwa F4.1 melengkapinya.
3. KategoriPenggunaanDanaResource (CRUD sederhana, urutan dapat diatur).
4. AkunContohSeeder: 521211 Belanja Bahan; 521213 Belanja Honor Output Kegiatan; 521219 Belanja Barang Non
   Operasional Lainnya; 522151 Belanja Jasa Profesi; 524111 Belanja Perjalanan Dinas Biasa; 524113 Belanja
   Perjalanan Dinas Dalam Kota — semua sumber='contoh', keterangan "CONTOH — perlu verifikasi terhadap BAS yang
   berlaku". Seeder tidak berjalan di production bila sudah ada akun bersumber impor.
   KategoriPenggunaanDanaContohSeeder: biaya operasional pendidikan, biaya operasional kemahasiswaan,
   penelitian, pengabdian kepada masyarakat, investasi SDM, investasi sarana, investasi prasarana — keterangan
   "CONTOH — format instrumen akreditasi perlu verifikasi".
5. Cache 'master:akun:aktif' 24 jam dengan invalidasi observer & setelah impor selesai.
6. Test Pest: impor CSV 3 baris (1 baru, 1 memperbarui, 1 kode tidak valid) → 1 dibuat, 1 diperbarui,
   1 baris gagal tercatat; akun contoh berlencana; admin-unit hanya bisa melihat (403 saat create).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Impor berkas akun contoh (CSV/XLSX) diproses Horizon dan hasilnya tampil.
- [ ] Akun hasil seeder berlencana "CONTOH".
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(master): tambah akun bas dan kategori dengan impor excel"
```

### F3.4 — Jenis pencairan, pajak, tarif referensi, dan pengaturan
**Tujuan:** Master yang mengatur istilah pencairan, tarif pajak referensi (tanpa perhitungan otomatis), dan pengaturan kebijakan.

**Prompt (salin ke agen AI):**
```text
1. Migrasi jenis_pencairan, jenis_pajak, tarif_pajak, pengaturan (03-SKEMA §2.1–§2.2). Model JenisPencairan,
   JenisPajak (hasMany tarif), TarifPajak, Pengaturan.
2. TarifPajak::berlakuPada(JenisPajak $jenis, CarbonInterface $tanggal): ?TarifPajak — tarif aktif dengan
   berlaku_mulai <= tanggal dan (berlaku_sampai null atau >= tanggal), terbaru dahulu. Validasi: rentang berlaku
   tarif dengan uraian sama tidak boleh tumpang tindih.
3. App\Support\Pengaturan: get(string $kunci, mixed $bawaan = null) dengan cast sesuai kolom tipe,
   set(string $kunci, mixed $nilai, User $oleh) (tercatat activitylog), cache 'pengaturan:semua' 24 jam.
4. Seeder: JenisPencairanSeeder (UP Uang Persediaan, TUP Tambahan Uang Persediaan, LS Pembayaran Langsung;
   perlu_verifikasi=true), JenisPajakSeeder (pph21, pph22, pph23, pph4_2, ppn), TarifPajakContohSeeder (baris
   contoh dengan perlu_verifikasi=true, dasar_hukum null, keterangan "CONTOH — angka wajib dikonfirmasi bagian
   keuangan" — JANGAN menambahkan komentar yang mengklaim angka itu benar), PengaturanSeeder dengan kunci dan
   nilai persis docs/vibecoding/03-SKEMA-DATABASE.md §4.
5. Filament: JenisPencairanResource, JenisPajakResource dengan TarifPajakRelationManager (lencana "Perlu
   verifikasi" bila perlu_verifikasi), halaman App\Filament\Pages\Pengaturan (grup Sistem) berupa form
   berkelompok: Persetujuan (wajib_verifikasi_kepala_unit, wajib_persetujuan_wadek, jabatan_penyetuju_akhir),
   SPJ (batas_hari_spj 1–90, hari_pengingat_spj, interval_pengingat_terlambat), Penomoran (format_nomor_pengajuan,
   format_nomor_spj — wajib memuat {urut}), Lain-lain (pola_pengelolaan satker/blu, honor_mengurangi_pagu,
   masa_berlaku_ekspor_jam (1–24), tanggal_pengingat_kunci, nama_fakultas). Akses: admin-keuangan & super-admin ubah;
   lainnya lihat (sesuai matriks).
6. Test Pest: TarifPajak::berlakuPada memilih tarif yang benar di sekitar tanggal pergantian; tumpang tindih
   ditolak; Pengaturan::get mengembalikan bool/int sesuai tipe; set menghapus cache; format nomor tanpa {urut}
   ditolak; admin-unit tidak bisa menyimpan halaman pengaturan.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Halaman "Pengaturan" menampilkan semua kunci dari seeder.
- [ ] Tarif pajak contoh berlencana "Perlu verifikasi".
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(master): tambah jenis pencairan, tarif pajak, dan pengaturan"
```

### F3.5 — Pagu per unit per tahun dan penetapannya
**Tujuan:** Admin keuangan menyusun pagu (opsional rinci per akun), wadek keuangan menetapkannya.

**Prompt (salin ke agen AI):**
```text
1. Migrasi pagu dan pagu_akun (03-SKEMA §2.2). Enum StatusPagu (draf, ditetapkan). Model Pagu ($table='pagu',
   SoftDeletes, HasUuids, TercatatAktivitas, MemilikiRiwayatStatus, DibatasiUnit), PaguAkun.
   Morph map 'pagu'.
2. Validasi: unik (tahun_anggaran_id, unit_id); jumlah >= 0 (string desimal, gunakan Uang); Σ pagu_akun.jumlah
   <= pagu.jumlah (dihitung server dengan Uang::jumlahkan); tahun anggaran tidak boleh 'ditutup'.
3. Action App\Actions\Master\TetapkanPagu::handle(Pagu $pagu, User $oleh): hanya wadek-keuangan (izin
   pagu.setujui), status draf → ditetapkan, isi ditetapkan_oleh/pada, CatatRiwayatStatus, dibungkus
   DenganKunci("aksi:pagu:{id}").
4. Action UbahPaguDitetapkan::handle(Pagu $pagu, string $jumlahBaru, string $alasan, User $oleh): hanya
   wadek-keuangan; alasan wajib; untuk saat ini validasi jumlahBaru >= 0 saja — validasi terhadap komitmen
   pengajuan (BR-04) ditambahkan di F4.3 lewat HitungSisaPagu; catat riwayat status ditetapkan→ditetapkan
   dengan catatan "Perubahan pagu dari X menjadi Y: alasan".
5. Filament PaguResource (grup Anggaran): admin-keuangan buat/ubah selama draf; tombol "Tetapkan" untuk
   wadek-keuangan; tombol "Ubah pagu" untuk wadek-keuangan pada status ditetapkan; PaguAkunRelationManager;
   kolom tabel: tahun, unit, jumlah (format Rp), status; filter tahun & unit; pengguna unit melihat pagu
   unitnya saja (DibatasiUnit). Hapus hanya status draf.
6. Test Pest: Σ rincian akun > pagu ditolak; admin-keuangan tidak bisa menetapkan (403); wadek bisa;
   draf tidak tampil sebagai "berlaku"; admin-unit PMAT tidak bisa melihat pagu PBIO (403 lewat URL id);
   pagu ditetapkan tidak bisa dihapus; riwayat status tercatat.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Pagu PMAT 2027 dapat dibuat, dirinci per akun, lalu ditetapkan oleh akun wadek.
- [ ] Admin-unit hanya melihat pagu unitnya.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pagu): tambah pagu unit per tahun dan penetapan"
```

### F3.6 — Tutup fase F3
**Tujuan:** Merge dan tag `v0.3.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f3-master-pagu terhadap main: semua model master memakai $table tunggal bahasa
Indonesia, SoftDeletes untuk master, kolom uang decimal:2 di $casts, Policy di setiap Resource, cache master
diinvalidasi. Tambahkan test arsitektur tests/Arch/ModelTest.php: setiap model di App\Models mendefinisikan
properti $table; tidak ada model yang memasukkan 'status' ke $fillable; semua kolom bernama jumlah/total*/pagu/
harga_satuan/nilai yang ada di $casts bertipe 'decimal:2'. Perbarui CHANGELOG [0.3.0], APP_VERSION=0.3.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Test arsitektur model hijau; PR di-merge; tag `v0.3.0` ada.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.3.0 master data dan pagu"
git push -u origin feat/f3-master-pagu
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.3.0 -m "Fase 3: master data & pagu"
git push origin main --tags
```

---

## F4 — Pengajuan kegiatan & RAB

### F4.1 — Tabel pengajuan, RAB, dan item RAB
**Branch:** `feat/f4-pengajuan-rab`
**Tujuan:** Struktur data pengajuan dengan RAB berversi.

**Prompt (salin ke agen AI):**
```text
1. Migrasi pengajuan, rab, rab_item persis docs/vibecoding/03-SKEMA-DATABASE.md §2.3 (urutan: pengajuan tanpa
   FK rab_aktif_id → rab → rab_item → migrasi kecil menambah FK pengajuan.rab_aktif_id). Indeks:
   pengajuan(tahun_anggaran_id, unit_id, status), pengajuan(batas_spj), FULLTEXT pengajuan(judul_kegiatan),
   unik rab(pengajuan_id, nomor_revisi), rab_item(rab_id, akun_id).
2. Enum StatusPengajuan (10 nilai) dan StatusRab (6 nilai) persis 03-SKEMA §3, dengan label, warna, dan
   bolehBerpindahKe() mengikuti diagram docs/vibecoding/01-PRD.md §6.1 dan §6.2 (termasuk transisi langsung
   diajukan→diverifikasi_keuangan dan →disetujui untuk konfigurasi tahap nonaktif, serta dicairkan→disetujui).
   Tambahkan helper StatusPengajuan::dapatDiubah(): bool (true hanya draf, dikembalikan) dan
   StatusPengajuan::menghitungKomitmen(): ?string ('total_diajukan' | 'total_disetujui' | 'total_realisasi' | null
   sesuai BR-04).
3. Model Pengajuan ($table='pengajuan', SoftDeletes, HasUuids, TercatatAktivitas,
   MemilikiRiwayatStatus, DibatasiUnit) — relasi tahunAnggaran, unit, kategoriPenggunaanDana, pembuat,
   rab (hasMany terurut nomor_revisi), rabAktif (belongsTo rab_aktif_id), rabAwal (hasOne nomor_revisi=0).
   Model Rab ($table='rab', HasUuids, MemilikiRiwayatStatus) hasMany item; Model RabItem ($table='rab_item')
   belongsTo rab, akun, asal (self). Cast semua uang 'decimal:2', volume 'decimal:2', status ke Enum.
   'status' dan semua kolom total_* TIDAK di $fillable. Morph map 'pengajuan', 'rab'.
4. Lengkapi Akun::sedangDipakai(): true bila ada rab_item yang memakai akun tsb.
5. Factory PengajuanFactory (state per status), RabFactory, RabItemFactory dengan volume/harga sebagai string.
6. Test Pest: migrasi & relasi berfungsi; Enum: semua transisi pada diagram diizinkan dan contoh transisi tidak
   sah (draf→disetujui, selesai→draf, ditolak→diajukan) ditolak; dapatDiubah hanya draf/dikembalikan;
   Akun yang dipakai item tidak bisa dihapus.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Tabel `pengajuan`, `rab`, `rab_item` ada dengan indeks & FK sesuai skema.
- [ ] Test tabel transisi Enum hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(db): tambah tabel pengajuan, rab, dan rab item"
```

### F4.2 — Simpan draf dengan total RAB dihitung server
**Tujuan:** Menegakkan BR-01, BR-02, BR-03 di lapisan Action, Policy, dan guard model.

**Prompt (salin ke agen AI):**
```text
1. Action App\Actions\Pengajuan\HitungTotalRab::handle(Rab $rab): Rab — untuk setiap item set
   jumlah = Uang::kali(volume, harga_satuan) (saveQuietly), lalu rab.total = Uang::jumlahkan(jumlah item);
   bila rab.nomor_revisi = 0, set pengajuan.total_diajukan = rab.total (forceFill). Semua dalam transaksi.
2. Action SimpanDrafPengajuan::handle(array $data, array $items, User $oleh, ?Pengajuan $pengajuan = null):
   Pengajuan — membuat/memperbarui pengajuan berstatus draf atau dikembalikan, unit_id dari $oleh->unit_id
   (abaikan unit_id kiriman klien untuk admin-unit), tahun anggaran wajib berstatus perencanaan atau berjalan,
   tanggal_selesai_kegiatan >= tanggal_mulai_kegiatan, membuat RAB versi 0 bila belum ada dan menjadikannya
   rab_aktif_id, mengganti seluruh item (hapus lalu buat ulang, urutan sesuai array) — ABAIKAN kunci 'jumlah'
   dan 'total' dari klien, lalu memanggil HitungTotalRab. Validasi item: akun_id aktif, uraian wajib,
   volume > 0, harga_satuan >= 0, satuan wajib, minimal 1 item.
3. Guard model: RabItem::saving dan ::deleting melempar KesalahanDomain("RAB sudah terkunci") bila
   rab.status ≠ draf; Pengajuan::updating melempar bila status lama tidak dapatDiubah() dan ada perubahan pada
   kolom data inti (judul_kegiatan, deskripsi, tanggal_*, tempat, penanggung_jawab, unit_id,
   tahun_anggaran_id, kategori_penggunaan_dana_id). Perubahan status/total oleh Action tetap diizinkan.
4. PengajuanPolicy: viewAny (pengajuan.lihat), view (lingkup fakultas atau unit_id sama), create (pengajuan.buat
   dan user punya unit_id), update & delete (pengajuan.ubah/hapus, unit sama, status dapatDiubah(); delete hanya
   bila draf dan nomor null). Gunakan scope DibatasiUnit untuk query daftar.
5. Test Pest (feature, tanpa UI): (a) item volume '2', harga '150000' → jumlah '300000.00', total '300000.00'
   walau klien mengirim total '1'; (b) volume '0.5' × '33333.33' → '16666.67'; (c) 3 item total tepat;
   (d) admin-unit PMAT tidak bisa memperbarui pengajuan PBIO (Policy false); (e) pengajuan berstatus
   diajukan tidak bisa diubah via Action maupun langsung $model->update() (exception); (f) RabItem pada RAB
   disetujui tidak bisa dihapus; (g) unit_id kiriman klien diabaikan untuk admin-unit; (h) tahun anggaran
   ditutup ditolak.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Test (a)–(h) hijau.
- [ ] Tidak ada perhitungan total di sisi klien yang disimpan.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pengajuan): simpan draf dengan total rab dihitung server"
```

### F4.3 — Resource pengajuan, form RAB, dan sisa pagu
**Tujuan:** Antarmuka penyusunan pengajuan + RAB, dengan informasi sisa pagu (BR-04).

**Prompt (salin ke agen AI):**
```text
1. Action App\Actions\Pengajuan\HitungSisaPagu::handle(int $tahunAnggaranId, int $unitId, ?int $akunId = null,
   ?int $kecualiPengajuanId = null): array{pagu:string, komitmen:string, sisa:string} — pagu dari pagu
   berstatus ditetapkan (bila $akunId diberikan dan ada pagu_akun untuk akun tsb, gunakan rinciannya; bila tidak
   ada rincian, kembalikan null untuk level akun); komitmen = Σ per pengajuan sesuai
   StatusPengajuan::menghitungKomitmen() (BR-04; untuk level akun gunakan Σ rab_item.jumlah per akun pada
   RAB aktif/awal yang relevan). Pagu belum ditetapkan → pagu '0.00'. Semua dengan Uang.
2. Lengkapi UbahPaguDitetapkan: jumlah baru tidak boleh < komitmen saat ini (lempar PaguTidakCukupException).
3. Filament PengajuanResource (grup Kegiatan, route `id` UUIDv7):
   - Form: Section "Kegiatan" (tahun anggaran berjalan, judul_kegiatan, deskripsi, tanggal mulai/selesai, tempat,
     penanggung_jawab, kategori penggunaan dana); Section "Rincian RAB" berupa Repeater item (akun select
     searchable dari cache master:akun:aktif, uraian, rincian_volume, volume, satuan dengan datalist umum
     OH/OK/OJ/paket/rim/unit/kali, harga_satuan, keterangan). Kolom "Jumlah" per baris dan "Total" hanya
     tampilan (placeholder reaktif, BUKAN field yang dikirim). Penyimpanan memanggil SimpanDrafPengajuan
     (override handleRecordCreation/handleRecordUpdate).
   - Placeholder "Sisa pagu unit" di form menampilkan pagu, komitmen, sisa (HitungSisaPagu) dengan peringatan
     merah bila total > sisa.
   - Tabel: nomor, judul, unit, total_diajukan/total_disetujui (Rp), status (badge), batas_spj; filter tahun,
     unit (hanya lingkup fakultas), status; pencarian judul (FULLTEXT) & nomor. getEloquentQuery memakai
     terlihatOleh(auth()->user()).
   - Halaman View menampilkan infolist kegiatan + tabel RAB aktif (bertumpuk di layar ponsel).
4. Widget RingkasanPaguWidget di dasbor untuk admin-unit/kepala-unit (pagu, komitmen, sisa unitnya).
5. Test Pest (Livewire Filament): admin-unit membuat pengajuan dengan 2 item lewat halaman create → total
   tersimpan benar; admin-unit tidak melihat pengajuan unit lain di tabel; URL view pengajuan unit lain → 403;
   HitungSisaPagu: skenario pagu 100 jt, pengajuan diajukan 30 jt, disetujui 20 jt, draf 50 jt, selesai
   (realisasi 5 jt) → komitmen 55 jt, sisa 45 jt; UbahPaguDitetapkan ke 50 jt ditolak.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Admin-unit dapat menyusun draf RAB di ponsel maupun desktop; total tampil dan tersimpan sesuai perhitungan server.
- [ ] Sisa pagu tampil di form.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pengajuan): tambah resource pengajuan dan form rab"
```

### F4.4 — Tautan berkas: tabel, validasi, dan kontrak penyimpanan
**Tujuan:** Mengganti unggahan dengan tautan (STANDAR-TEKNIS §1a) untuk TOR/KAK, bukti pencairan, bukti realisasi, dan bukti setor (BR-15), termasuk syarat akses terbatas domain unsil.ac.id untuk bukti keuangan.

**Prompt (salin ke agen AI):**
```text
Baca docs/vibecoding/STANDAR-TEKNIS.md §1a dan BR-15 di 01-PRD. Sistem TIDAK menerima unggahan berkas.
1. config/berkas.php: 'mode' => env('BERKAS_MODE', 'tautan'); 'domain_diizinkan' => ['drive.google.com',
   'docs.google.com', '*.unsil.ac.id'] (+ komentar opsional 'onedrive.live.com', '*.sharepoint.com');
   'pemendek_ditolak' => ['bit.ly','s.id','tinyurl.com','t.co','goo.gl','cutt.ly','shorturl.at','rebrand.ly'].
   Tambahkan BERKAS_MODE=tautan ke .env.example.
2. Migrasi tautan_berkas persis 03-SKEMA §2.1 (termasuk kolom khusus keuangan akses_terbatas_dikonfirmasi dan
   peringatan_privasi). Enum App\Enums\JenisTautan (tor, surat_tugas, bukti_pencairan, kuitansi, nota, faktur,
   daftar_hadir, foto_kegiatan, bukti_setor, lainnya; method buktiKeuangan(): bool untuk bukti_pencairan,
   kuitansi, nota, faktur, bukti_setor), PenyediaTautan, StatusCekTautan. Model TautanBerkas
   ($table='tautan_berkas', SoftDeletes, HasUuids, TercatatAktivitas — activitylog mencatat url lama → baru,
   morphTo 'pemilik'). Trait App\Concerns\MemilikiTautanBerkas (morphMany TautanBerkas 'pemilik',
   tautanJenis(JenisTautan ...)). Interface App\Contracts\PemilikTautan { tautanDapatDiubah(): bool } —
   Pengajuan mengimplementasikannya (true hanya bila status dapatDiubah()). Guard TautanBerkas saving/deleting:
   lempar KesalahanDomain("Tautan sudah terkunci") bila pemilik->tautanDapatDiubah() false.
3. App\Services\Berkas\PenguraiTautanDrive::urai(string $url): array{penyedia: PenyediaTautan,
   drive_file_id: ?string, folder: bool} — pola /file/d/<id>, open?id=<id>, uc?id=<id>, /document/d/<id>,
   /spreadsheets/d/<id>, /presentation/d/<id>, /drive/folders/<id> (folder=true).
4. Rule App\Rules\TautanBerkasValid (ValidationRule): hanya skema https; host cocok daftar putih (dukung
   wildcard *.unsil.ac.id, tolak host mirip seperti unsil.ac.id.evil.com); tolak pemendek URL; tolak tautan folder
   Drive; panjang <= 2048. Pesan Indonesia, mis. "Gunakan tautan https Google Drive/Docs atau *.unsil.ac.id
   ke satu berkas (bukan folder/pemendek URL)".
5. Kontrak App\Contracts\PenyimpananBerkas: simpan(Model&PemilikTautan $pemilik, JenisTautan $jenis, string $url,
   string $label, bool $aksesTerbatasDikonfirmasi, User $oleh): TautanBerkas; hapus(TautanBerkas $t, User $oleh):
   void; urlTampil(TautanBerkas $t): string; urlPratinjau(TautanBerkas $t): ?string; periksa(TautanBerkas $t):
   StatusCekTautan. Implementasi App\Services\Berkas\TautanEksternal; binding di AppServiceProvider berdasarkan
   config('berkas.mode') — mode selain 'tautan' melempar exception "Mode berkas belum didukung".
   (periksa() dan urlPratinjau() boleh sementara melempar/null; dilengkapi F4.5.)
6. Action App\Actions\Berkas\SimpanTautanBerkas dan HapusTautanBerkas (memakai kontrak): validasi
   TautanBerkasValid; bila $jenis->buktiKeuangan() maka aksesTerbatasDikonfirmasi WAJIB true (pesan "Bukti
   keuangan memuat NPWP/rekening — bagikan terbatas ke domain unsil.ac.id lalu centang konfirmasi"); simpan
   penyedia & drive_file_id dari PenguraiTautanDrive; rate limit 'simpan-tautan' 30/menit per pengguna.
7. TautanBerkasPolicy: view/create/delete mendelegasikan ke Policy pemilik (view → view pemilik; create/delete
   → update pemilik). Tautan tidak pernah dirender untuk pengguna yang gagal Policy.
8. Komponen Filament App\Filament\Forms\IsianTautanBerkas (Repeater: jenis, label, url TextInput type=url dengan
   rule, Checkbox akses_terbatas_dikonfirmasi visible bila jenis bukti keuangan; helper text: "Di Google Drive:
   Bagikan → Akses umum: Terbatas atau Universitas Siliwangi → Salin link. Simpan di Shared Drive unit, bukan
   Drive pribadi."). TautanBerkasRelationManager di PengajuanResource untuk jenis tor/surat_tugas: kolom label,
   jenis, penyedia, status_cek (badge), aksi "Buka berkas" (url ke tab baru, rel noopener noreferrer); tambah/hapus
   hanya saat pengajuan dapatDiubah().
9. Test arsitektur tests/Arch/TanpaUnggahanTest.php: tidak ada pemakaian Filament\Forms\Components\FileUpload,
   Illuminate\Http\UploadedFile, atau Storage::put*/putFile di app/ kecuali App\Filament\Imports\* dan
   App\Jobs\Impor* (impor Excel diproses lalu dihapus).
10. Test Pest: URL Drive /file/d/<id>/view diterima dan drive_file_id terisi; docs.google.com/document diterima;
   https://lppm.unsil.ac.id/x.pdf diterima; http:// ditolak; bit.ly ditolak; tautan folder ditolak;
   dropbox.com ditolak; unsil.ac.id.evil.com ditolak; jenis kuitansi tanpa konfirmasi akses terbatas ditolak;
   jenis tor tanpa konfirmasi diterima; admin-unit unit lain tidak dapat melihat tautan (403 / tidak dirender);
   setelah pengajuan diajukan tautan TOR tidak bisa dihapus/diubah; perubahan URL tercatat activitylog.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Tautan TOR dapat ditambahkan dari halaman pengajuan; tidak ada komponen unggah berkas di aplikasi (selain impor).
- [ ] `TanpaUnggahanTest` hijau; `config/berkas.php` dan `BERKAS_MODE=tautan` ada.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(berkas): ganti unggahan dengan tautan berkas tervalidasi"
```

### F4.5 — Pemeriksaan keteraksesan tautan dan pratinjau Drive
**Tujuan:** Job `PeriksaTautanBerkas` menandai tautan mati atau bukti keuangan yang terbuka publik; verifikator dapat melihat pratinjau tanpa server menyimpan berkas.

**Prompt (salin ke agen AI):**
```text
1. Lengkapi TautanEksternal::periksa() dan Job App\Jobs\PeriksaTautanBerkas(TautanBerkas $t) (queue 'default',
   middleware RateLimited('periksa-tautan') 60/menit global, tries 3, timeout 20): permintaan HTTP HEAD (fallback
   GET dengan header Range: bytes=0-0) TANPA cookie/kredensial, timeout 10 detik, ikuti redirect maks 5 dan catat
   tujuan akhir; JANGAN mengunduh isi berkas. Klasifikasi:
   - 404/410/galat DNS/timeout → status_cek tidak_dapat_diakses.
   - Redirect ke halaman login Google (accounts.google.com) atau 401/403 → status_cek dapat_diakses (berkas ada
     dan aksesnya terbatas — kondisi yang DIHARAPKAN untuk bukti keuangan); peringatan_privasi=false.
   - 2xx tanpa login → dapat_diakses; bila jenis->buktiKeuangan() set peringatan_privasi=true (tampaknya
     dibagikan "siapa saja yang memiliki link").
   Simpan dicek_pada. Hasil bersifat informatif — tidak pernah membatalkan simpan.
2. Bila status berubah menjadi tidak_dapat_diakses atau peringatan_privasi berubah menjadi true, kirim notifikasi
   App\Notifications\TautanBerkasBermasalah (via database; kanal mail ditambahkan F10.1) ke ditambahkan_oleh
   dan admin-unit unit pemilik, berisi label, alasan, dan langkah perbaikan (ubah berbagi ke unsil.ac.id).
3. SimpanTautanBerkas men-dispatch PeriksaTautanBerkas afterCommit setiap tautan baru/berubah.
4. Lengkapi urlPratinjau(): PDF/Docs Drive → https://drive.google.com/file/d/<id>/preview; jenis foto_kegiatan →
   https://lh3.googleusercontent.com/d/<id>; selain itu null. Komponen infolist reusable
   App\Filament\Infolists\DaftarTautanBerkas: daftar tautan dengan badge jenis/status_cek, lencana merah
   "Akses publik — minta dibatasi" bila peringatan_privasi, tombol "Buka berkas" (tab baru, rel noopener
   noreferrer) dan aksi modal "Pratinjau" (iframe/img dengan placeholder "Pratinjau tidak dapat dimuat — buka
   berkas dengan akun unsil.ac.id" bila gagal). Pasang di halaman view pengajuan.
5. Test Pest dengan Http::fake: (a) 200 anonim pada kuitansi → peringatan_privasi=true + notifikasi; (b) 302 ke
   accounts.google.com → dapat_diakses tanpa peringatan; (c) 404 → tidak_dapat_diakses + notifikasi;
   (d) ConnectionException → tidak_dapat_diakses, simpan tautan tetap berhasil; (e) request memakai HEAD atau
   Range bytes=0-0 dan tanpa header Cookie/Authorization; (f) urlPratinjau sesuai pola; (g) pengguna tanpa hak
   tidak melihat komponen DaftarTautanBerkas.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Menyimpan tautan Drive publik untuk kuitansi memunculkan lencana "Akses publik" dan notifikasi.
- [ ] Pratinjau PDF Drive tampil di modal (setelah login akun unsil.ac.id di browser).
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(berkas): periksa keteraksesan tautan dan pratinjau drive"
```

### F4.6 — Ajukan pengajuan: penomoran berkunci dan cek pagu
**Tujuan:** Transisi `draf/dikembalikan → diajukan` dengan BR-04, BR-05, BR-06 (snapshot), BR-08, BR-09.

**Prompt (salin ke agen AI):**
```text
Action App\Actions\Pengajuan\AjukanPengajuan::handle(Pengajuan $pengajuan, User $oleh): Pengajuan
- DenganKunci("aksi:pengajuan:{id}") lalu DenganKunci("pagu:{tahun}:{unit_id}") bersarang, lalu DB::transaction
  dengan lockForUpdate pada pengajuan.
- Syarat: Policy update true (admin-unit unit tsb); status draf atau dikembalikan; tahun anggaran berjalan;
  pagu unit berstatus ditetapkan; minimal 1 item RAB; minimal 1 tautan_berkas jenis 'tor'; total_diajukan <= sisa
  pagu (HitungSisaPagu dengan kecualiPengajuanId = pengajuan ini) dan, bila ada pagu_akun, Σ item per akun <=
  sisa pagu akun — bila gagal lempar PaguTidakCukupException berisi sisa dalam format Rp.
- Nomor: bila nomor null → Penomoran::berikutnya('pengajuan', tahun, Pengaturan::get('format_nomor_pengajuan'));
  bila sudah ada (pengajuan ulang setelah dikembalikan) nomor dipertahankan.
- Snapshot: wajib_kepala_unit = Pengaturan wajib_verifikasi_kepala_unit; wajib_wadek = wajib_persetujuan_wadek.
- Status → diajukan; rab versi 0 → diajukan; diajukan_pada = now(); CatatRiwayatStatus (catatan "Diajukan" atau
  "Diajukan ulang").
Action BatalkanPengajuan::handle(Pengajuan $p, string $alasan, User $oleh) untuk tahap ini: admin-unit pada
status draf/dikembalikan (alasan wajib min 10 karakter) → dibatalkan, riwayat. (Pembatalan oleh admin-keuangan
ditambahkan di F5.2.)
Tombol Filament "Ajukan" (konfirmasi, menampilkan total & sisa pagu) dan "Batalkan" di halaman view/edit;
tangkap KesalahanDomain menjadi notifikasi danger.

Test Pest: sukses → status diajukan, nomor PGJ/2027/0001, riwayat 1 baris; total > sisa pagu → gagal dengan
pesan berisi sisa; tanpa TOR → gagal; pagu draf → gagal; pengajuan ulang mempertahankan nomor; dua pengajuan
berbeda diajukan berurutan → nomor 0001 dan 0002; simulasi dobel klik (lock dipegang) →
AksiSedangDiprosesException dan tidak ada nomor kedua; admin-unit unit lain → 403; status diajukan tidak bisa
dibatalkan admin-unit.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Pengajuan yang diajukan memperoleh nomor unik dan tidak bisa diedit lagi.
- [ ] Pengajuan yang melebihi sisa pagu ditolak dengan pesan yang jelas.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pengajuan): ajukan pengajuan dengan penomoran berkunci redis"
```

### F4.7 — Cetak lembar pengajuan PDF dengan QR verifikasi
**Tujuan:** Lembar pengajuan + RAB yang dapat dicetak, dengan QR menuju halaman verifikasi publik ber-UUID.

**Prompt (salin ke agen AI):**
```text
1. composer require barryvdh/laravel-dompdf endroid/qr-code.
2. View resources/views/pdf/pengajuan.blade.php (A4 portrait, font DejaVu Sans): kop "Fakultas Keguruan dan
   Ilmu Pendidikan — Universitas Siliwangi" (nama fakultas dari Pengaturan), nomor, unit, judul, tanggal &
   tempat kegiatan, penanggung jawab, tabel RAB versi aktif (No, Akun, Uraian, Rincian volume, Volume, Satuan,
   Harga satuan, Jumlah) + baris total (angka dari DB, bukan dihitung di Blade), riwayat persetujuan (tahap,
   nama, waktu), status, kotak QR (PNG base64) berisi route('verifikasi.pengajuan', id), catatan kaki
   "Dicetak dari Keuangan FKIP Unsil pada {tanggal}". Format uang via Uang::format, tanggal 'd F Y' locale id.
3. Aksi Filament "Cetak PDF" di halaman view pengajuan (berstatus selain draf) → stream PDF bernama
   "pengajuan-{nomor-dengan-tanda-hubung}.pdf". Otorisasi Policy view.
4. Route publik GET /verifikasi/{id} (name verifikasi.pengajuan, throttle verifikasi-publik 30/menit per IP),
   App\Http\Controllers\VerifikasiPublikController + view Blade Tailwind responsif: menampilkan HANYA nomor,
   nama unit, judul kegiatan, status (label), total disetujui (bila sudah disetujui), tanggal persetujuan, dan
   teks "Dokumen ini tercatat di Keuangan FKIP Unsil". Pengajuan draf atau id tidak dikenal → 404. Tidak ada
   rincian item/penerima/tautan berkas. Tambahkan <meta name="robots" content="noindex">.
5. Test Pest: PDF dapat dibuat (respons application/pdf) untuk pemilik; 403 untuk unit lain; halaman verifikasi
   menampilkan nomor tetapi TIDAK menampilkan uraian item; draf → 404; UUID acak → 404; percobaan ke-31 →
   429.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PDF tercetak rapi dengan total sama dengan di layar.
- [ ] QR dipindai ponsel membuka halaman verifikasi tanpa login.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pengajuan): cetak lembar pengajuan pdf dengan qr verifikasi"
```

### F4.8 — Tutup fase F4
**Tujuan:** Merge dan tag `v0.4.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f4-pengajuan-rab terhadap main: total RAB hanya dihitung di HitungTotalRab; tidak ada
float; semua transisi memanggil CatatRiwayatStatus dalam transaksi; Resource memakai id; N+1 dicegah
(eager load di tabel). Tambahkan DemoSeeder (hanya environment local) yang membuat tahun anggaran berjalan,
pagu ditetapkan untuk unit contoh, dan 10 pengajuan draf/diajukan dengan pengguna demo per peran
(*@demo.test, kata sandi "password"), dengan tautan TOR contoh berupa URL Drive fiktif berformat valid.
Perbarui CHANGELOG [0.4.0], APP_VERSION=0.4.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `kup php artisan migrate:fresh --seed` di lokal menghasilkan data demo; PR di-merge; tag `v0.4.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.4.0 pengajuan dan rab"
git push -u origin feat/f4-pengajuan-rab
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.4.0 -m "Fase 4: pengajuan kegiatan & RAB"
git push origin main --tags
```

---

## F5 — Persetujuan berjenjang

### F5.1 — Aksi verifikasi dan persetujuan dengan pemisahan tugas
**Branch:** `feat/f5-persetujuan`
**Tujuan:** Transisi persetujuan berjenjang (BR-06) yang aman dari dobel klik (BR-09) dan menegakkan pemisahan tugas (BR-07).

**Prompt (salin ke agen AI):**
```text
Buat di app/Actions/Persetujuan:
1. Kelas dasar/trait MemeriksaPemisahanTugas dengan method pastikanBolehBertindak(Pengajuan $p, User $u):
   lempar PelanggaranPemisahanTugasException bila $u->id == $p->dibuat_oleh, atau $u pernah tercatat sebagai
   user_id di riwayat_status pengajuan ini dengan tahap tidak null (sudah bertindak di tahap lain).
2. VerifikasiPengajuan::handle(Pengajuan $p, User $u, ?string $catatan = null):
   - status diajukan + wajib_kepala_unit → hanya kepala-unit dengan unit_id sama → diverifikasi_unit
     (tahap kepala_unit).
   - status diverifikasi_unit, ATAU diajukan dengan wajib_kepala_unit=false → hanya admin-keuangan →
     diverifikasi_keuangan (tahap keuangan); bila wajib_wadek=false, langsung jalankan logika persetujuan akhir
     (lihat butir 3) dengan tahap keuangan.
3. SetujuiPengajuan::handle(Pengajuan $p, User $u, ?string $catatan = null): status diverifikasi_keuangan, hanya
   wadek-keuangan (izin persetujuan.setujui + peran) → disetujui. Di bawah DenganKunci("pagu:{tahun}:{unit}")
   cek ulang total vs sisa pagu (BR-04, kecualikan pengajuan ini) — gagal → PaguTidakCukupException. Set
   total_disetujui = rabAktif.total, rab versi 0 → disetujui (disetujui_oleh/pada), disetujui_pada.
4. Semua aksi: DenganKunci("aksi:pengajuan:{id}"), DB::transaction + lockForUpdate, cek ulang status setelah
   lock (bila sudah berubah lempar TransisiStatusTidakSahException "Pengajuan sudah diproses pengguna lain"),
   cek Enum bolehBerpindahKe, CatatRiwayatStatus dengan TahapPersetujuan yang sesuai.
5. PengajuanPolicy: tambahkan verifikasi(User, Pengajuan) dan setujui(User, Pengajuan) yang mencerminkan aturan
   di atas (untuk menampilkan/menyembunyikan tombol), termasuk pemisahan tugas.
6. Test Pest (minimal 12 kasus): alur penuh 3 tahap oleh 3 pengguna berbeda → disetujui dengan 4 baris
   riwayat; kepala-unit unit lain ditolak; pembuat pengajuan yang juga kepala-unit ditolak; pengguna yang
   sudah memverifikasi di tahap unit lalu mencoba tahap keuangan (punya dua peran) ditolak; wajib_kepala_unit
   false → admin-keuangan langsung dari diajukan; wajib_wadek false → admin-keuangan menghasilkan disetujui;
   snapshot: mengubah pengaturan setelah diajukan tidak mengubah jalur pengajuan itu; sisa pagu habis oleh
   pengajuan lain sebelum persetujuan → ditolak; aksi kedua saat lock dipegang → AksiSedangDiprosesException;
   status sudah berubah → TransisiStatusTidakSahException; super-admin tidak bisa menyetujui; pimpinan tidak
   bisa menyetujui.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Semua 12+ test hijau.
- [ ] Riwayat status menyimpan tahap untuk setiap tindakan.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(persetujuan): tambah verifikasi dan persetujuan berjenjang"
```

### F5.2 — Kembalikan, tolak, dan batalkan oleh keuangan
**Tujuan:** Jalur negatif persetujuan dengan catatan wajib (BR-06) dan pembatalan oleh admin keuangan (BR-23).

**Prompt (salin ke agen AI):**
```text
1. KembalikanPengajuan::handle(Pengajuan $p, User $u, string $catatan): dari diajukan/diverifikasi_unit/
   diverifikasi_keuangan oleh pemegang tahap yang sedang berjalan (kepala-unit untuk diajukan bila
   wajib_kepala_unit; admin-keuangan untuk diverifikasi_unit atau diajukan tanpa tahap unit; wadek-keuangan
   untuk diverifikasi_keuangan) → dikembalikan; rab versi 0 → draf (agar dapat diedit lagi); catatan wajib
   min 10 karakter; pemisahan tugas berlaku.
2. TolakPengajuan::handle(Pengajuan $p, User $u, string $catatan): dari status yang sama oleh pemegang tahap →
   ditolak (final); rab versi 0 → ditolak.
3. Perluas BatalkanPengajuan: admin-keuangan boleh membatalkan status diajukan s.d. disetujui bila tidak ada
   pencairan berstatus dicatat (cek relasi pencairan bila tabelnya sudah ada — untuk sekarang buat method
   Pengajuan::punyaPencairanAktif() yang mengembalikan false dengan komentar "dilengkapi F6.1"); alasan wajib;
   komitmen pagu terlepas otomatis karena status dibatalkan.
4. Semua dengan DenganKunci, transaksi, lockForUpdate, Enum transisi, CatatRiwayatStatus (tahap sesuai).
5. Test Pest: kembalikan tanpa catatan → validasi gagal; setelah dikembalikan admin-unit bisa mengedit item dan
   mengajukan ulang (nomor tetap) dan alur persetujuan mulai lagi dari tahap pertama; ditolak tidak bisa
   diajukan ulang; admin-keuangan membatalkan pengajuan disetujui → sisa pagu unit bertambah kembali;
   admin-unit tidak bisa membatalkan pengajuan disetujui.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Alur kembalikan → perbaiki → ajukan ulang berjalan dengan nomor tetap.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(persetujuan): tambah kembalikan, tolak, dan pembatalan"
```

### F5.3 — Antrean "Menunggu tindakan saya" dan tombol keputusan
**Tujuan:** Antarmuka bagi penyetuju di desktop dan ponsel, plus linimasa status.

**Prompt (salin ke agen AI):**
```text
1. Halaman View PengajuanResource: header actions "Verifikasi", "Setujui", "Kembalikan", "Tolak", "Batalkan"
   yang visible() berdasarkan Policy (verifikasi, setujui, kembalikan, tolak, batalkan) — modal dengan Textarea
   catatan (wajib untuk kembalikan/tolak/batalkan) dan ringkasan total + sisa pagu; memanggil Action terkait;
   KesalahanDomain → notifikasi danger; sukses → notifikasi success dan redirect ke halaman
   MenungguTindakan. Tombol dinonaktifkan selama request (Filament default) — lock di server tetap wajib.
2. RiwayatStatusRelationManager (baca-saja) pada pengajuan: kolom waktu (d F Y H:i), pengguna, tahap,
   dari → ke (badge), catatan. Tab "Log perubahan" menampilkan activity_log pengajuan & item (hanya
   peran fakultas).
3. App\Filament\Pages\MenungguTindakan (grup Beranda, badge jumlah): tabel pengajuan yang menunggu tindakan
   pengguna login — kepala-unit: diajukan & wajib_kepala_unit & unit sama; admin-keuangan: diverifikasi_unit,
   atau diajukan tanpa tahap unit; wadek-keuangan: diverifikasi_keuangan — kecualikan yang melanggar pemisahan
   tugas. Urut diajukan_pada terlama. Kolom: nomor, unit, judul, total, menunggu sejak (diffForHumans).
4. AntreanPersetujuanWidget di dasbor untuk peran penyetuju (jumlah per tahap).
5. Test Pest (Livewire): admin-keuangan melihat tombol Verifikasi hanya pada status yang tepat; admin-unit tidak
   melihat tombol keputusan; halaman MenungguTindakan kepala-unit PMAT hanya berisi pengajuan PMAT; memanggil
   aksi Kembalikan tanpa catatan menampilkan galat validasi; riwayat tampil terurut.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Penyetuju dapat menyelesaikan persetujuan dari ponsel (uji di lebar 375 px).
- [ ] Badge "Menunggu tindakan" menunjukkan jumlah yang benar per pengguna.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(persetujuan): tambah antrean menunggu tindakan dan tombol keputusan"
```

### F5.4 — Tutup fase F5
**Tujuan:** Merge dan tag `v0.5.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f5-persetujuan terhadap main. Pastikan setiap Action persetujuan memakai DenganKunci,
transaksi, lockForUpdate, pemeriksaan pemisahan tugas, dan CatatRiwayatStatus; tidak ada tombol yang
menggantikan pemeriksaan server. Tambahkan test arsitektur: kelas di App\Actions\Persetujuan memakai
App\Support\DenganKunci. Perbarui DemoSeeder agar memuat pengajuan di setiap status persetujuan.
Perbarui CHANGELOG [0.5.0], APP_VERSION=0.5.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PR di-merge; tag `v0.5.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.5.0 persetujuan berjenjang"
git push -u origin feat/f5-persetujuan
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.5.0 -m "Fase 5: persetujuan berjenjang"
git push origin main --tags
```

---

## F6 — Pencairan

### F6.1 — Catat pencairan dan batas SPJ
**Branch:** `feat/f6-pencairan`
**Tujuan:** BPP mencatat pencairan UP/TUP/LS (istilah perlu verifikasi) dengan BR-12, BR-13, BR-19, BR-07(c), BR-26.

**Prompt (salin ke agen AI):**
```text
1. Migrasi pencairan (03-SKEMA §2.3). Enum StatusPencairan (dicatat, dibatalkan). Model Pencairan
   ($table='pencairan', HasUuids, TercatatAktivitas, MemilikiRiwayatStatus, MemilikiTautanBerkas,
   MenjagaPeriodeTerkunci dengan kolomTanggalPeriode ['tanggal']; implements PemilikTautan: tautanDapatDiubah()
   true bila status dicatat, periode terbuka, dan SPJ pengajuan belum diterima). Morph map 'pencairan'.
   Lengkapi Pengajuan::punyaPencairanAktif().
2. Action App\Actions\Pengajuan\HitungUlangTotalPengajuan::handle(Pengajuan $p): menghitung ulang
   total_dicairkan = Σ pencairan dicatat (Uang::jumlahkan) — total lain dilengkapi F7; forceFill + save; lalu
   increment Cache 'dasbor:versi' (App\Support\VersiCacheDasbor::naikkan()).
3. Action App\Actions\Pencairan\CatatPencairan::handle(Pengajuan $p, array $data, array $tautanBukti, User $u):
   - DenganKunci("aksi:pengajuan-dana:{id}") + transaksi + lockForUpdate pengajuan.
   - Hanya peran bpp (izin pencairan.buat); pemisahan tugas: $u bukan user yang menyetujui akhir pengajuan
     (riwayat_status ke=disetujui) dan bukan pembuat.
   - Status pengajuan disetujui atau dicairkan; jenis_pencairan aktif; jumlah > 0; Σ pencairan dicatat + jumlah
     <= rabAktif.total (BR-12); tanggal <= hari ini dan berada pada periode terbuka & tahun anggaran berjalan
     (guard trait juga menjaga).
   - Simpan pencairan (status dicatat, dicatat_oleh), minimal satu tautan jenis bukti_pencairan via
     SimpanTautanBerkas (wajib, akses_terbatas_dikonfirmasi=true karena memuat rekening),
     CatatRiwayatStatus (null → dicatat, tahap bendahara).
   - Bila ini pencairan pertama: status pengajuan disetujui → dicairkan (riwayat), dan batas_spj =
     max(tanggal_selesai_kegiatan, tanggal pencairan) + Pengaturan batas_hari_spj hari (BR-13).
   - Panggil HitungUlangTotalPengajuan.
4. Test Pest: pencairan pertama → pengajuan dicairkan & batas_spj benar (uji dua kasus: kegiatan selesai
   setelah/ sebelum tanggal pencairan); Σ melebihi total RAB → ditolak; pengajuan diajukan → ditolak; tanggal
   di periode terkunci → PeriodeTerkunciException; tanpa tautan bukti → validasi gagal; tautan bukti tanpa
   konfirmasi akses terbatas → gagal; wadek yang menyetujui
   pengajuan tsb juga punya peran bpp → ditolak; admin-unit → 403; mengubah batas_hari_spj setelah pencairan
   tidak mengubah batas_spj yang tersimpan; versi cache dasbor bertambah.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Pencairan tercatat dengan tautan bukti; status pengajuan menjadi `dicairkan` dengan `batas_spj` terisi.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pencairan): catat pencairan dengan tautan bukti dan batas spj"
```

### F6.2 — Pembatalan pencairan dan antarmuka bendahara
**Tujuan:** Koreksi pencairan tanpa menghapus jejak (BR-22) dan halaman kerja BPP.

**Prompt (salin ke agen AI):**
```text
1. Action BatalkanPencairan::handle(Pencairan $c, string $alasan, User $u): hanya bpp; status dicatat →
   dibatalkan; alasan wajib; tanggal pencairan harus di periode terbuka; DenganKunci pengajuan-dana; tidak boleh
   bila SPJ pengajuan sudah diterima (cek relasi spj bila ada — siapkan Pengajuan::spjSudahDiterima() yang
   sementara false, dilengkapi F7.1); bila setelah pembatalan tidak ada pencairan dicatat dan belum ada realisasi
   → pengajuan dicairkan → disetujui dan batas_spj = null; HitungUlangTotalPengajuan; riwayat.
2. Filament PencairanResource (grup Bendahara): daftar semua pencairan (filter jenis, periode, status, unit),
   kolom nomor pengajuan, unit, jenis, tanggal, jumlah, status. Aksi "Catat pencairan" di halaman view
   pengajuan (visible untuk bpp bila status disetujui/dicairkan) dengan form jenis, tanggal, jumlah (default
   sisa yang belum dicairkan = total RAB aktif − total_dicairkan, tetap dapat diubah), nomor_bukti, penerima,
   keterangan, tautan bukti (komponen IsianTautanBerkas, jenis bukti_pencairan) dan DaftarTautanBerkas di detail.
   PencairanRelationManager di PengajuanResource (baca-saja untuk unit) dengan
   aksi "Batalkan" untuk bpp.
3. Halaman pengajuan menampilkan ringkasan dana: total disetujui, dicairkan, sisa belum dicairkan, batas SPJ
   (merah bila lewat).
4. Test Pest: batalkan pencairan satu-satunya → pengajuan kembali disetujui; pembatalan tanpa alasan → gagal;
   pencairan tidak bisa dihapus (tidak ada DeleteAction; request delete → 403); admin-unit melihat pencairan
   unitnya tetapi tidak tombol Batalkan; filter periode berfungsi.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] BPP dapat mencatat dan membatalkan pencairan dari panel; tidak ada tombol hapus.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pencairan): tambah pembatalan pencairan dan halaman bendahara"
```

### F6.3 — Tutup fase F6
**Tujuan:** Merge dan tag `v0.6.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f6-pencairan terhadap main: MenjagaPeriodeTerkunci terpasang di Pencairan; total
dihitung ulang oleh HitungUlangTotalPengajuan; tidak ada penulisan total dari input klien. Tambahkan perintah
artisan keuangan:rekonsiliasi (App\Console\Commands\RekonsiliasiTotal) yang untuk setiap pengajuan
membandingkan kolom total_* dengan perhitungan ulang (tanpa menyimpan) dan mencetak tabel selisih; opsi --perbaiki
menyimpan hasil hitung ulang. Test untuk perintah tsb. Perbarui CHANGELOG [0.6.0], APP_VERSION=0.6.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `kup php artisan keuangan:rekonsiliasi` berjalan tanpa selisih pada data demo; PR di-merge; tag `v0.6.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.6.0 pencairan"
git push -u origin feat/f6-pencairan
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.6.0 -m "Fase 6: pencairan"
git push origin main --tags
```

---

## F7 — SPJ, realisasi, pajak & pengembalian

### F7.1 — Tabel SPJ, realisasi, potongan pajak, pengembalian
**Branch:** `feat/f7-spj`
**Tujuan:** Struktur data pertanggungjawaban.

**Prompt (salin ke agen AI):**
```text
1. Migrasi spj (termasuk pernyataan_final_oleh/pada), realisasi_item, potongan_pajak, pengembalian persis
   03-SKEMA §2.3, dan tautan_berkas_beku (03-SKEMA §2.1) beserta indeks.
2. Enum StatusSpj (draf, diajukan, dikembalikan, diterima) dan StatusPengembalian (diajukan, diverifikasi,
   ditolak) dengan transisi sesuai 01-PRD §6.3.
3. Model Spj ($table='spj', HasUuids, TercatatAktivitas, MemilikiRiwayatStatus) hasMany realisasiItem;
   RealisasiItem ($table='realisasi_item', SoftDeletes, HasUuids, TercatatAktivitas, MemilikiTautanBerkas,
   implements PemilikTautan — tautanDapatDiubah() true hanya bila spj draf/dikembalikan,
   MenjagaPeriodeTerkunci ['tanggal_bukti']) belongsTo spj, rabItem, hasMany potonganPajak;
   PotonganPajak ($table='potongan_pajak', TercatatAktivitas, MenjagaPeriodeTerkunci ['tanggal_setor'] — abaikan
   bila null); Pengembalian ($table='pengembalian', HasUuids, TercatatAktivitas, MemilikiRiwayatStatus,
   MemilikiTautanBerkas + PemilikTautan (dapat diubah hanya saat status diajukan), MenjagaPeriodeTerkunci
   ['tanggal_setor']). Model TautanBerkasBeku ($table='tautan_berkas_beku', tanpa updated_at, tidak dapat
   diubah/dihapus — lempar KesalahanDomain di updating/deleting). Spj hasMany tautanBeku.
   Pengajuan hasOne spj, hasMany pengembalian.
   Morph map 'spj', 'realisasi_item', 'pengembalian'.
4. Guard: RealisasiItem dan PotonganPajak saving/deleting ditolak bila spj.status ∉ {draf, dikembalikan}.
5. Lengkapi Pengajuan::spjSudahDiterima() dan HitungUlangTotalPengajuan: total_realisasi = Σ jumlah_bruto
   realisasi (tidak terhapus), total_pajak = Σ potongan_pajak.nilai, total_dikembalikan = Σ pengembalian
   diverifikasi; juga perbarui spj.total_realisasi & spj.total_pajak. Method Pengajuan::sisaDana(): string =
   total_dicairkan − total_realisasi (boleh negatif = kurang bayar).
6. Factory untuk semua model baru. Perbarui keuangan:rekonsiliasi agar memeriksa total baru.
7. Test Pest: relasi; guard menolak perubahan realisasi saat SPJ diajukan/diterima; sisaDana untuk kasus lebih
   bayar dan kurang bayar; rekonsiliasi mendeteksi selisih buatan.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Empat tabel baru ada dengan FK dan indeks.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(db): tambah tabel spj, realisasi, potongan pajak, pengembalian"
```

### F7.2 — Catat realisasi per item dengan tautan bukti wajib
**Tujuan:** Realisasi tidak melebihi pagu tersetujui (BR-14), tautan bukti wajib dengan akses terbatas (BR-15), periode terbuka (BR-19).

**Prompt (salin ke agen AI):**
```text
1. Action App\Actions\Spj\CatatRealisasi::handle(Pengajuan $p, array $data, array $tautan, User $u,
   ?RealisasiItem $item = null): RealisasiItem
   - DenganKunci("aksi:pengajuan-dana:{id}") + transaksi + lockForUpdate pengajuan.
   - admin-unit unit pengajuan (izin spj.buat/ubah); status pengajuan dicairkan; SPJ dibuat otomatis (status
     draf, dibuat_oleh $u) bila belum ada; SPJ berstatus draf atau dikembalikan; tidak ada revisi RAB yang
     sedang berjalan (cek rab dengan status diajukan/diverifikasi_keuangan/draf dan nomor_revisi > 0 — relevan
     setelah F8).
   - rab_item_id harus milik RAB aktif pengajuan; jumlah_bruto = Uang::kali(volume, harga_satuan);
     Σ jumlah_bruto realisasi item tsb (kecuali $item yang sedang diubah) + baru <= rab_item.jumlah
     (BR-14) — pesan galat menyebut sisa per item; Σ seluruh realisasi <= rabAktif.total.
   - tanggal_bukti <= hari ini, >= tanggal_mulai_kegiatan − 30 hari (konstanta, komentar "kebijakan perlu
     verifikasi"), periode terbuka.
   - $tautan = daftar [jenis, label, url, akses_terbatas_dikonfirmasi]; setelah simpan, realisasi wajib punya
     minimal satu tautan berjenis kuitansi, nota, atau faktur (jenis tambahan: daftar_hadir, foto_kegiatan,
     lainnya); semua lewat SimpanTautanBerkas (bukti keuangan wajib konfirmasi akses terbatas unsil.ac.id).
   - Bila drive_file_id (atau URL ternormalisasi untuk non-Drive) sama dengan tautan bukti realisasi pada
     pengajuan LAIN → indikasi_bukti_ganda = true dan dispatch event IndikasiBuktiGandaTerdeteksi (listener
     notifikasi di F10).
   - jumlah_neto = bruto − total_pajak; HitungUlangTotalPengajuan.
2. Action HapusRealisasi (soft delete) dengan syarat sama; tautan_berkas miliknya ikut soft delete.
3. Test Pest: realisasi pas sama dengan jumlah item → sukses; melebihi 1 rupiah → ditolak; dua realisasi
   parsial yang jumlahnya melebihi → yang kedua ditolak; item dari RAB versi lama ditolak; tanpa tautan
   kuitansi/nota/faktur (hanya foto_kegiatan) → gagal; tautan kuitansi tanpa konfirmasi akses terbatas → gagal;
   tautan bit.ly → gagal; drive_file_id sama dengan realisasi pengajuan lain → tersimpan dengan
   indikasi_bukti_ganda=true dan event terkirim; drive_file_id sama di pengajuan yang SAMA tidak ditandai;
   tanggal di periode terkunci → gagal; pengajuan masih disetujui (belum dicairkan) → gagal; admin-unit unit
   lain → 403; SPJ diajukan → realisasi tidak bisa ditambah.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Semua skenario BR-14/BR-15 teruji.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(spj): catat realisasi per item rab dengan tautan bukti wajib"
```

### F7.3 — Potongan pajak sebagai informasi
**Tujuan:** Mencatat PPh 21/22/23, PPh 4(2), PPN tanpa menghitung otomatis (BR-16).

**Prompt (salin ke agen AI):**
```text
1. Action App\Actions\Spj\CatatPotonganPajak::handle(RealisasiItem $r, array $data, User $u,
   ?PotonganPajak $pp = null): admin-unit unit terkait (selama SPJ draf/dikembalikan) atau bpp (selama SPJ
   draf/dikembalikan/diajukan — untuk melengkapi nomor bukti setor). Field: jenis_pajak_id, dasar_pengenaan
   (string desimal > 0, <= jumlah_bruto), nilai (string desimal >= 0, <= dasar_pengenaan — DIISI PENGGUNA),
   nomor_bukti_setor, tanggal_setor, keterangan. Sistem mengambil TarifPajak::berlakuPada(jenis, tanggal_bukti)
   dan MENYALIN tarif_pajak_id + tarif_referensi; sistem TIDAK pernah menghitung nilai dari tarif.
   Setelah simpan: perbarui realisasi_item.total_pajak & jumlah_neto, HitungUlangTotalPengajuan.
   DenganKunci pengajuan-dana.
2. Hapus potongan pajak dengan syarat sama.
3. Di form realisasi (F7.7 akan memakai) tampilkan helper text "Tarif referensi: x% (perlu verifikasi) — nilai
   pajak diisi sesuai bukti potong"; JANGAN ada kalkulasi otomatis di klien maupun server.
4. Test Pest: nilai yang disimpan sama persis dengan input (mis. dasar 1.000.000, nilai 17.500 walau tarif
   referensi 2%) — membuktikan tidak ada perhitungan; tarif_referensi tersnapshot dan tidak berubah setelah
   master tarif diubah; nilai > dasar_pengenaan ditolak; realisasi.jumlah_neto terbarui; bpp bisa mengisi nomor
   bukti setor saat SPJ diajukan, admin-unit tidak bisa.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Test "tidak ada perhitungan otomatis" hijau.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pajak): catat potongan pajak sebagai informasi dengan tarif referensi"
```

### F7.4 — Ajukan dan verifikasi SPJ
**Tujuan:** Alur SPJ `draf → diajukan → diterima/dikembalikan` (BR-17, BR-07).

**Prompt (salin ke agen AI):**
```text
1. AjukanSpj::handle(Spj $s, User $u): admin-unit unit terkait; status draf/dikembalikan; minimal 1 realisasi;
   setiap realisasi punya >= 1 tautan kuitansi/nota/faktur; tidak ada revisi RAB berjalan; nomor SPJ via
   Penomoran::berikutnya('spj', tahun, format_nomor_spj) bila belum ada (DenganKunci penomoran:spj:{tahun});
   → diajukan, diajukan_pada; riwayat. DenganKunci("aksi:spj:{id}").
2. TerimaSpj::handle(Spj $s, User $u, ?string $catatan): admin-keuangan atau bpp; pemisahan tugas
   (bukan pembuat SPJ/pengaju realisasi mana pun); status diajukan → diterima; diputuskan_oleh/pada;
   riwayat tahap keuangan/bendahara sesuai peran; setelah itu panggil SelesaikanPengajuan (dilengkapi di F7.6;
   untuk sekarang buat kelas dengan method handle yang hanya memeriksa sisa dana = 0 → pengajuan selesai).
3. KembalikanSpj::handle(Spj $s, User $u, string $catatan): diajukan → dikembalikan; catatan wajib.
4. Semua: transaksi, lockForUpdate, Enum transisi, CatatRiwayatStatus, Policy SpjPolicy (view: lingkup unit;
   ajukan; verifikasi).
5. Test Pest: ajukan tanpa realisasi → gagal; nomor SPJ/2027/0001 terbit; pengaju SPJ yang juga bpp tidak bisa
   menerima SPJ-nya sendiri; dikembalikan → admin-unit bisa menambah realisasi lalu mengajukan ulang (nomor
   tetap); diterima dengan sisa dana 0 → pengajuan selesai; diterima dengan sisa dana > 0 → pengajuan tetap
   dicairkan; setelah diterima realisasi tidak bisa diubah.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] SPJ dapat diajukan, dikembalikan, diajukan ulang, dan diterima.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(spj): tambah pengajuan dan verifikasi spj"
```

### F7.5 — Pernyataan berkas final dan pembekuan tautan bukti
**Tujuan:** Karena berkas di Drive bisa diubah di luar sistem, pengaju menyatakan berkas final saat mengajukan SPJ, dan sistem membekukan daftar tautan (snapshot URL + waktu) saat SPJ diterima (BR-17).

**Prompt (salin ke agen AI):**
```text
1. Perluas AjukanSpj::handle(Spj $s, User $u, bool $pernyataanFinal): $pernyataanFinal WAJIB true (pesan "Centang
   pernyataan bahwa berkas pada tautan adalah versi final, tidak akan diubah/dipindah/dihapus, dan dibagikan
   terbatas ke domain unsil.ac.id"); isi spj.pernyataan_final_oleh/pada setiap kali diajukan (termasuk ajukan
   ulang); catat teks pernyataan di catatan riwayat_status. Bila ada tautan bukti keuangan dengan
   peringatan_privasi=true atau status_cek tidak_dapat_diakses, TETAP boleh diajukan tetapi tampilkan daftar
   peringatan di modal konfirmasi.
2. Action App\Actions\Spj\BekukanTautanSpj::handle(Spj $s, RiwayatStatus $riwayat): menyalin SEMUA tautan_berkas
   aktif milik realisasi_item SPJ tsb, milik pencairan berstatus dicatat pengajuannya, dan TOR/surat_tugas
   pengajuan ke tautan_berkas_beku (pemilik_type/id, jenis, label, url, drive_file_id, status_cek, dicek_pada,
   dibekukan_pada = now(), riwayat_status_id = $riwayat->id, spj_id). Idempoten per (spj_id, riwayat_status_id).
3. TerimaSpj: di dalam transaksi yang sama, setelah CatatRiwayatStatus (diajukan → diterima), panggil
   BekukanTautanSpj dengan baris riwayat yang baru dibuat. Setelah itu guard PemilikTautan membuat tautan
   realisasi terkunci (spj diterima).
4. VerifikasiPengembalian (F7.6) kelak juga membekukan tautan bukti_setor pengembalian itu dengan riwayat
   verifikasinya — siapkan method BekukanTautanSpj::bekukanPemilik(Spj $s, Model $pemilik, RiwayatStatus $r).
5. Tampilan: tab "Tautan dibekukan" di SpjResource view (baca-saja): kolom pemilik (item/pencairan/TOR), jenis,
   label, URL (tombol Buka), status saat beku, waktu beku; bandingkan dengan tautan saat ini dan beri lencana
   "Berubah setelah dibekukan" bila URL berbeda (seharusnya tidak mungkin lewat sistem, tetapi penting untuk
   audit data lama/impor).
6. Test Pest: ajukan tanpa pernyataan → gagal; pernyataan tersimpan dengan pengguna & waktu; terima SPJ →
   jumlah baris tautan_berkas_beku = jumlah tautan aktif (realisasi + pencairan + TOR) dan merujuk riwayat
   penerimaan; setelah diterima, mengubah/menghapus tautan realisasi → KesalahanDomain; TautanBerkasBeku tidak
   bisa di-update/delete; SPJ dikembalikan lalu diterima pada putaran kedua → snapshot hanya dibuat saat
   diterima; pengguna unit lain tidak dapat melihat tab tautan dibekukan.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] SPJ tidak dapat diajukan tanpa centang pernyataan berkas final.
- [ ] Setelah SPJ diterima, tab "Tautan dibekukan" menampilkan snapshot URL + waktu; tautan bukti tidak dapat diubah.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(spj): wajibkan pernyataan berkas final dan bekukan tautan bukti"
```

### F7.6 — Pengembalian sisa dana dan penyelesaian pengajuan
**Tujuan:** Setoran sisa dana diverifikasi BPP; pengajuan `selesai` sesuai BR-18.

**Prompt (salin ke agen AI):**
```text
1. CatatPengembalian::handle(Pengajuan $p, array $data, array $tautanBukti, User $u): admin-unit unit terkait;
   pengajuan dicairkan; SPJ sudah diajukan atau diterima; jumlah > 0 dan <= sisaDana() − Σ pengembalian
   berstatus diajukan/diverifikasi; tanggal_setor periode terbuka; tautan bukti_setor wajib via
   SimpanTautanBerkas (akses terbatas dikonfirmasi); status diajukan; riwayat. DenganKunci pengajuan-dana.
2. VerifikasiPengembalian::handle(Pengembalian $k, User $u): bpp, pemisahan tugas (bukan pembuat) → diverifikasi;
   dalam transaksi yang sama bekukan tautan bukti_setor pengembalian itu (BekukanTautanSpj::bekukanPemilik
   dengan riwayat verifikasi); HitungUlangTotalPengajuan; panggil SelesaikanPengajuan. TolakPengembalian
   (catatan wajib) → ditolak.
3. Lengkapi SelesaikanPengajuan::handle(Pengajuan $p, ?User $u): bila spj diterima DAN (sisaDana() <= 0 ATAU
   total_dikembalikan == sisaDana()) → status selesai, selesai_pada, riwayat (catatan "Selesai otomatis").
   Bila sisaDana() < 0 (kurang bayar) → jangan selesai; tandai di UI "kurang bayar Rp…, catat pencairan
   tambahan" (pencairan tambahan tetap tunduk BR-12).
4. Test Pest: skenario lengkap — RAB 10 jt, dicairkan 10 jt, realisasi 9,75 jt, SPJ diterima → status masih
   dicairkan, sisa 250 rb; pengembalian 300 rb → ditolak (melebihi); pengembalian 250 rb diajukan → diverifikasi
   bpp → pengajuan selesai; pengembalian oleh pembuat yang juga bpp tidak bisa diverifikasinya sendiri;
   kurang bayar → tidak selesai sampai pencairan tambahan dicatat dan SPJ diterima kembali (gunakan
   factory state); tautan bukti_setor terbekukan setelah verifikasi.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Skenario lengkap RAB → pencairan → realisasi → SPJ → pengembalian → selesai hijau.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(pengembalian): tambah setoran sisa dana dan penyelesaian pengajuan"
```

### F7.7 — Antarmuka SPJ, realisasi, dan pengembalian (ramah ponsel)
**Tujuan:** Admin unit mencatat realisasi dan menempelkan tautan bukti dari aplikasi Drive di ponsel; verifikator memeriksa SPJ dengan pratinjau, peringatan bukti ganda, dan peringatan privasi.

**Prompt (salin ke agen AI):**
```text
1. SpjResource (grup Kegiatan, route `id` UUIDv7): tabel nomor SPJ, nomor pengajuan, unit, total realisasi, total pajak,
   status, batas SPJ (merah bila lewat & belum diterima); filter status/unit/terlambat; scope terlihatOleh.
   Halaman view: ringkasan dana (disetujui, dicairkan, realisasi, sisa dana / kurang bayar), tabel per item
   RAB aktif: jumlah RAB, terealisasi, sisa (BR-14).
2. RealisasiItemRelationManager: form (rab_item select hanya item RAB aktif dengan teks "sisa Rp…", tanggal_bukti,
   nomor_bukti, uraian, penerima, volume, harga_satuan, tautan bukti memakai komponen IsianTautanBerkas — jenis
   kuitansi/nota/faktur/daftar_hadir/foto_kegiatan, checkbox akses terbatas untuk bukti keuangan, petunjuk
   "Simpan di Shared Drive unit; Bagikan → Terbatas/Universitas Siliwangi → Salin link") → CatatRealisasi;
   Repeater potongan pajak → CatatPotonganPajak (helper text tarif referensi, lencana "perlu verifikasi");
   kolom jumlah_bruto/neto hanya tampilan. Lencana kuning "Kemungkinan bukti ganda" dengan tautan ke realisasi
   lain yang drive_file_id-nya sama (hanya untuk peran fakultas); lencana merah "Akses publik" bila
   peringatan_privasi. Pratinjau bukti memakai DaftarTautanBerkas (F4.5).
3. Header actions SPJ: "Ajukan SPJ" (modal berisi checkbox pernyataan berkas final + daftar peringatan tautan),
   "Terima", "Kembalikan" (modal catatan) sesuai Policy.
4. PengembalianResource (grup Bendahara) dan aksi "Catat pengembalian sisa dana" di halaman pengajuan/SPJ
   untuk admin-unit (default jumlah = sisa yang belum disetor; tautan bukti_setor), aksi "Verifikasi"/"Tolak"
   untuk bpp.
5. Test Pest (Livewire): admin-unit menambah realisasi dengan tautan Drive kuitansi lewat relation manager; verifikator
   melihat lencana bukti ganda, admin-unit tidak melihat tautan realisasi unit lain; tombol Terima tidak tampil
   untuk pembuat SPJ; tabel item menampilkan sisa yang benar.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Uji manual di ponsel (lebar 375 px): menyalin link dari aplikasi Google Drive lalu menempelkannya ke form realisasi berhasil.
- [ ] Verifikator melihat pratinjau Drive, peringatan bukti ganda, dan peringatan akses publik.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(spj): tambah antarmuka spj realisasi dan pengembalian"
```

### F7.8 — Tutup fase F7
**Tujuan:** Merge dan tag `v0.7.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f7-spj terhadap main. Tambahkan test end-to-end tests/Feature/AlurLengkapTest.php:
pagu ditetapkan → draf → ajukan → verifikasi unit → verifikasi keuangan → setujui → pencairan → realisasi 2 item
+ pajak (semua bukti berupa tautan) → ajukan SPJ dengan pernyataan final → terima (tautan dibekukan) →
pengembalian → verifikasi → selesai; periksa semua total pengajuan, 10+ baris riwayat status, snapshot tautan, dan keuangan:rekonsiliasi tanpa selisih. Perbarui DemoSeeder dengan pengajuan selesai dan
yang SPJ-nya terlambat. Perbarui CHANGELOG [0.7.0], APP_VERSION=0.7.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `AlurLengkapTest` hijau; PR di-merge; tag `v0.7.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.7.0 spj realisasi pajak pengembalian"
git push -u origin feat/f7-spj
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.7.0 -m "Fase 7: SPJ, realisasi, pajak & pengembalian"
git push origin main --tags
```

---

## F8 — Revisi RAB

### F8.1 — Buat dan ajukan revisi RAB bernomor
**Branch:** `feat/f8-revisi-rab`
**Tujuan:** Perubahan RAB setelah disetujui hanya lewat revisi bernomor (BR-03, BR-10, BR-11, BR-04).

**Prompt (salin ke agen AI):**
```text
Di app/Actions/Revisi:
1. BuatRevisiRab::handle(Pengajuan $p, string $alasan, User $u): Rab — admin-unit unit terkait (izin
   revisi.buat); pengajuan disetujui atau dicairkan; SPJ belum ada atau berstatus draf/dikembalikan; tidak ada
   RAB revisi berstatus draf/diajukan/diverifikasi_keuangan; nomor_revisi = max + 1 (di bawah
   DenganKunci("aksi:pengajuan:{id}") + lockForUpdate); salin semua item RAB aktif dengan asal_rab_item_id =
   id item lama; status draf; alasan_revisi wajib; HitungTotalRab (jangan mengubah total_diajukan karena
   nomor_revisi > 0). Riwayat (null → draf).
2. Pengeditan item revisi memakai SimpanItemRevisi::handle(Rab $rab, array $items, User $u): hanya saat rab
   draf; item lama boleh diubah (asal_rab_item_id tetap), ditambah (asal null), atau dihapus; HitungTotalRab.
3. AjukanRevisiRab::handle(Rab $rab, User $u): validasi BR-11 — untuk setiap item asal yang punya realisasi:
   item turunan harus ada dan jumlah >= Σ realisasi item asal (pesan menyebut uraian item); total revisi >=
   Σ realisasi; bila total revisi < total_dicairkan, tampilkan peringatan (bukan blokir) bahwa selisih menjadi
   sisa dana; bila total naik, cek selisih kenaikan terhadap sisa pagu (BR-04) di bawah DenganKunci pagu;
   rab → diajukan, diajukan_pada; riwayat.
4. Selama ada revisi berjalan, CatatRealisasi dan AjukanSpj menolak (pastikan pemeriksaan F7 mengenali status
   draf/diajukan/diverifikasi_keuangan untuk nomor_revisi > 0).
5. Test Pest: revisi pertama bernomor 1 dan item tersalin dengan asal; revisi kedua tidak bisa dibuat saat
   revisi 1 masih draf; pengajuan diajukan (belum disetujui) tidak bisa direvisi; menurunkan item di bawah
   realisasi → ditolak saat diajukan; menghapus item yang punya realisasi → ditolak; kenaikan melebihi sisa
   pagu → PaguTidakCukupException; realisasi baru ditolak selama revisi berjalan; RAB versi 0 tetap utuh.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Revisi bernomor dapat dibuat, diedit, dan diajukan dengan validasi BR-11.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(revisi): buat dan ajukan revisi rab bernomor"
```

### F8.2 — Persetujuan revisi, aktivasi versi, dan pemetaan realisasi
**Tujuan:** Revisi disetujui berjenjang; versi lama `digantikan`; realisasi dipetakan ke item baru.

**Prompt (salin ke agen AI):**
```text
1. VerifikasiRevisiRab (admin-keuangan: diajukan → diverifikasi_keuangan; bila wajib_wadek pada pengajuan false,
   langsung menjalankan aktivasi), SetujuiRevisiRab (wadek-keuangan: diverifikasi_keuangan → disetujui),
   KembalikanRevisiRab (→ draf, catatan wajib), TolakRevisiRab (→ ditolak, catatan wajib). Pemisahan tugas:
   pembuat revisi tidak boleh menyetujui; satu pengguna satu tahap per revisi (cek riwayat_status model rab).
   DenganKunci("aksi:rab:{id}") + transaksi.
2. Aktivasi (private method di SetujuiRevisiRab, dipakai juga oleh jalur tanpa wadek): di bawah
   DenganKunci("aksi:pengajuan-dana:{pengajuan_id}") dan DenganKunci pagu → cek ulang BR-04 dan BR-11; RAB aktif
   lama → digantikan (riwayat); RAB revisi → disetujui (disetujui_oleh/pada); pengajuan.rab_aktif_id = revisi;
   total_disetujui = revisi.total; untuk setiap realisasi_item yang menunjuk item lama, set rab_item_id ke item
   baru dengan asal_rab_item_id = item lama (gunakan query update langsung — guard RealisasiItem dikecualikan
   khusus untuk pemetaan ini melalui flag statis yang di-reset dalam finally, beri komentar); riwayat pada
   pengajuan (disetujui→disetujui atau dicairkan→dicairkan dengan catatan "Revisi RAB ke-N berlaku").
   HitungUlangTotalPengajuan.
3. Test Pest: alur revisi 2 tahap → versi baru aktif, versi lama digantikan, total_disetujui berubah, realisasi
   lama kini menunjuk item baru dan Σ realisasi per item tetap; jalur tanpa wadek; pembuat revisi tidak bisa
   menyetujui; revisi ditolak → RAB aktif tidak berubah dan revisi baru dapat dibuat (nomor 2); dua klik
   setujui bersamaan → satu berhasil, satu AksiSedangDiprosesException.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Setelah revisi disetujui, sisa per item di halaman SPJ dihitung terhadap item versi baru.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(revisi): setujui revisi rab dan petakan realisasi"
```

### F8.3 — Antarmuka revisi dan perbandingan versi
**Tujuan:** Admin unit dan penyetuju melihat perbedaan antarversi RAB dengan jelas.

**Prompt (salin ke agen AI):**
```text
1. RabRelationManager di PengajuanResource: daftar versi (Revisi ke-0 "Awal", 1, 2, …), status, total, alasan,
   tanggal disetujui; aksi "Buat revisi" (admin-unit, modal alasan) dan "Edit item revisi" (Repeater seperti
   F4.3, memanggil SimpanItemRevisi), "Ajukan revisi".
2. Halaman/aksi "Bandingkan dengan versi aktif": tabel per item (uraian, akun, jumlah lama, jumlah baru,
   selisih, terealisasi) dengan penanda baris tambah/hapus/ubah dan total selisih — dihitung server dengan Uang.
3. RevisiRabResource (grup Kegiatan) untuk admin-keuangan & wadek: daftar revisi yang menunggu, aksi
   Verifikasi/Setujui/Kembalikan/Tolak sesuai Policy RabPolicy. Tambahkan revisi ke halaman MenungguTindakan
   (tab kedua).
4. Cetak PDF lembar revisi (template F4.7 dengan judul "Revisi RAB ke-N" dan tabel perbandingan).
5. Test Pest (Livewire): admin-unit membuat revisi dari halaman pengajuan; tabel perbandingan menunjukkan
   selisih yang benar; wadek melihat revisi di MenungguTindakan; admin-unit tidak melihat tombol Setujui.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Perbandingan versi tampil dan sesuai perhitungan manual.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(revisi): tambah antarmuka revisi dan perbandingan versi rab"
```

### F8.4 — Tutup fase F8
**Tujuan:** Merge dan tag `v0.8.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f8-revisi-rab terhadap main: tidak ada jalur yang mengubah item RAB disetujui selain
revisi; versi lama tidak pernah dihapus; flag pengecualian guard pada pemetaan realisasi selalu di-reset.
Perluas AlurLengkapTest dengan revisi di tengah alur. Perbarui CHANGELOG [0.8.0], APP_VERSION=0.8.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PR di-merge; tag `v0.8.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.8.0 revisi rab"
git push -u origin feat/f8-revisi-rab
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.8.0 -m "Fase 8: revisi RAB"
git push origin main --tags
```

---

## F9 — Kunci periode

### F9.1 — Kunci periode dengan snapshot rekap
**Branch:** `feat/f9-kunci-periode`
**Tujuan:** Admin keuangan mengunci periode; angka periode tersimpan sebagai snapshot (BR-19).

**Prompt (salin ke agen AI):**
```text
1. Migrasi rekap_periode dan rekap_periode_akun (03-SKEMA §2.4). Model RekapPeriode, RekapPeriodeAkun.
2. Query App\Queries\RekapPeriodeQuery::untuk(Periode $p): Collection per unit — pagu (ditetapkan),
   total_disetujui_kumulatif (Σ total_disetujui pengajuan dengan disetujui_pada <= akhir periode dan status
   bukan dibatalkan), total_pencairan (pencairan dicatat bertanggal dalam periode), total_realisasi
   (realisasi_item tidak terhapus, tanggal_bukti dalam periode), total_pajak, total_pengembalian (diverifikasi,
   tanggal_setor dalam periode); dan per unit × akun (realisasi & disetujui kumulatif per akun lewat rab_item
   RAB aktif). Semua agregasi SQL SUM pada DECIMAL (tanpa float di PHP; ambil sebagai string).
3. Action App\Actions\Periode\KunciPeriode::handle(Periode $p, User $u, bool $abaikanPeringatan = false):
   admin-keuangan (izin periode.setujui); periode terbuka; periode sebelumnya harus sudah terkunci (kecuali
   Januari); pra-cek menghasilkan daftar peringatan: pencairan/realisasi pada periode ini milik SPJ yang belum
   diterima, pengembalian berstatus diajukan — bila ada dan $abaikanPeringatan false lempar KesalahanDomain
   berisi ringkasan; DenganKunci("periode:{id}", 30) + transaksi: status → terkunci, dikunci_oleh/pada,
   versi_kunci++, hapus snapshot lama periode ini lalu simpan snapshot baru dari RekapPeriodeQuery; riwayat;
   VersiCacheDasbor::naikkan().
4. Test Pest: mengunci Februari sebelum Januari → ditolak; kunci Januari menghasilkan baris rekap per unit
   dengan angka benar dari data uji; setelah dikunci CatatRealisasi bertanggal Januari ditolak,
   CatatPencairan bertanggal Januari ditolak, pembatalan pencairan bertanggal Januari ditolak; pra-cek
   memunculkan peringatan untuk SPJ belum diterima; admin-unit → 403.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Periode terkunci memiliki snapshot di `rekap_periode`.
- [ ] Transaksi bertanggal periode terkunci ditolak di semua jalur.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(periode): kunci periode dengan snapshot rekap"
```

### F9.2 — Buka kunci beralasan, tutup tahun anggaran, dan uji menyeluruh guard
**Tujuan:** Pembukaan kunci yang terkendali, penutupan tahun (BR-20), dan jaminan bahwa semua model transaksi terlindungi.

**Prompt (salin ke agen AI):**
```text
1. BukaKunciPeriode::handle(Periode $p, string $alasan, User $u): hanya wadek-keuangan; periode terkunci; tidak
   ada periode SESUDAHNYA yang terkunci (buka berurutan dari yang terbaru); alasan wajib min 20 karakter;
   status → terbuka; riwayat dengan catatan alasan; snapshot lama dipertahankan sampai dikunci ulang.
2. TutupTahunAnggaran (perluasan UbahStatusTahunAnggaran berjalan → ditutup): hanya wadek-keuangan; semua
   12 periode terkunci; tidak ada pengajuan berstatus diajukan/diverifikasi_*; peringatan bila ada pengajuan
   dicairkan yang belum selesai (harus dikonfirmasi).
3. PeriodeRelationManager (TahunAnggaranResource): kolom status, dikunci oleh/pada, versi_kunci; aksi "Kunci"
   (modal menampilkan hasil pra-cek + checkbox "Saya memahami peringatan"), "Buka kunci" (modal alasan),
   "Lihat snapshot" (tabel rekap per unit, unduh Excel lewat antrean kelak di F11).
4. Test arsitektur/refleksi tests/Arch/PeriodeTerkunciTest.php: model Pencairan, RealisasiItem, PotonganPajak,
   Pengembalian, RekapHonorEksternal (bila sudah ada; lewati bila belum) memakai trait MenjagaPeriodeTerkunci.
5. Test Pest: wadek membuka Januari saat Februari terkunci → ditolak; buka Februari lalu Januari → sukses;
   setelah dibuka realisasi bertanggal Januari dapat dicatat; kunci ulang → versi_kunci 2 dan snapshot
   diperbarui; admin-keuangan tidak bisa membuka kunci; tahun tidak bisa ditutup bila ada periode terbuka;
   tahun ditutup → pembuatan pengajuan & pagu baru di tahun itu ditolak.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Buka kunci memerlukan alasan dan hanya untuk wadek.
- [ ] Test arsitektur guard hijau.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(periode): tambah buka kunci beralasan dan tutup tahun anggaran"
```

### F9.3 — Tutup fase F9
**Tujuan:** Merge dan tag `v0.9.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f9-kunci-periode terhadap main. Pastikan pesan PeriodeTerkunciException tampil sebagai
notifikasi Filament yang jelas di semua form transaksi. Perbarui DemoSeeder: periode Januari–(bulan lalu)
terkunci. Perbarui CHANGELOG [0.9.0], APP_VERSION=0.9.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PR di-merge; tag `v0.9.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.9.0 kunci periode"
git push -u origin feat/f9-kunci-periode
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.9.0 -m "Fase 9: kunci periode"
git push origin main --tags
```

---

## F10 — Notifikasi & penjadwal

### F10.1 — Notifikasi database dan surel dari perubahan status
**Branch:** `feat/f10-notifikasi`
**Tujuan:** Setiap event `StatusBerubah` mengirim notifikasi ke pihak yang perlu bertindak (02-ARSITEKTUR §9).

**Prompt (salin ke agen AI):**
```text
1. Listener App\Listeners\KirimNotifikasiStatus (ShouldQueue, queue 'notifikasi') untuk event StatusBerubah.
   Pemetaan (buat kelas Notification masing-masing, semua ShouldQueue di antrean 'notifikasi', via
   ['database','mail']):
   - pengajuan → diajukan/diverifikasi_unit/diverifikasi_keuangan: PengajuanMenungguTindakan ke pemegang tahap
     berikutnya (kepala-unit unit terkait / semua admin-keuangan / semua wadek-keuangan), KECUALI pengguna yang
     terlarang bertindak (pemisahan tugas).
   - pengajuan → dikembalikan/ditolak: PengajuanDikembalikan/PengajuanDitolak ke pembuat + admin-unit unit.
   - pengajuan → disetujui: PengajuanDisetujui ke admin-unit, kepala-unit, semua bpp.
   - pencairan → dicatat: PencairanDicatat ke admin-unit & kepala-unit (memuat batas SPJ).
   - rab revisi: RevisiRabMenungguTindakan / RevisiRabDiputuskan.
   - spj → diajukan: SpjMenungguVerifikasi ke admin-keuangan & bpp; → diterima/dikembalikan: SpjDiputuskan.
   - pengembalian → diajukan: PengembalianMenungguVerifikasi ke bpp; pengajuan → selesai: PengajuanSelesai.
   - periode → terkunci/terbuka: PeriodeDikunci/PeriodeDibukaKembali ke semua peran keuangan.
   Listener untuk IndikasiBuktiGandaTerdeteksi → IndikasiBuktiGanda ke admin-keuangan & bpp.
   Tambahkan kanal 'mail' pada TautanBerkasBermasalah (F4.5).
2. Notifikasi database memakai format Filament (judul, isi, ikon, tombol "Buka" ke URL id). Surel memakai
   markdown mail berbahasa Indonesia, subjek berawalan "[Keuangan FKIP]", tanpa lampiran, tanpa URL tautan bukti
   (cukup tombol ke halaman aplikasi), dan tanpa data
   penerima/vendor.
3. Pengguna nonaktif tidak menerima notifikasi.
4. Test Pest (Notification::fake): setiap pemetaan di atas minimal satu test; pembuat yang juga admin-keuangan
   tidak menerima PengajuanMenungguTindakan untuk pengajuannya sendiri; notifikasi diantrekan ke 'notifikasi';
   isi surel tidak memuat nama penerima realisasi.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Lonceng notifikasi Filament menampilkan pemberitahuan saat pengajuan masuk tahap berikutnya.
- [ ] Surel tertangkap di Mailpit.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(notifikasi): kirim notifikasi database dan surel per perubahan status"
```

### F10.2 — Kanal WhatsApp opsional
**Tujuan:** Notifikasi mendesak (menunggu tindakan, pengingat SPJ) dapat dikirim via gateway WhatsApp (mis. Fonnte).

**Prompt (salin ke agen AI):**
```text
1. config/layanan.php (atau config/services.php kunci 'whatsapp'): aktif (env WHATSAPP_AKTIF, bawaan false),
   url (WHATSAPP_URL), token (WHATSAPP_TOKEN), batas per menit. Tambahkan kunci kosong ke .env.example.
2. App\Notifications\Channels\WhatsAppChannel: mengirim HTTP POST via Http::timeout(10)->retry(2, 500) ke gateway;
   nomor dinormalisasi ke format 62xxxxxxxxxx; jangan pernah mencatat token di log; kegagalan dilempar agar job
   di-retry (tries 3, backoff 60/300/900).
3. Notifikasi PengajuanMenungguTindakan, SpjMenungguVerifikasi, PengingatBatasSpj (dibuat F10.3) menambahkan
   'whatsapp' ke via() bila kanal aktif dan pengguna punya no_hp; method toWhatsApp() mengembalikan teks pendek
   (maks 300 karakter) berisi nomor pengajuan, tindakan yang diminta, dan URL.
4. RateLimiter 'whatsapp' (mis. 20/menit global) diterapkan di channel (release job bila terlampaui).
5. Test Pest dengan Http::fake: pesan terkirim ke URL gateway dengan nomor ternormalisasi; WHATSAPP_AKTIF=false
   → tidak ada request; respons 500 → exception (job retry); token tidak muncul di log (Log::spy).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Dengan kredensial uji, satu pesan WhatsApp diterima; tanpa kredensial aplikasi tetap berjalan.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(notifikasi): tambah kanal whatsapp opsional"
```

### F10.3 — Penjadwal: pengingat SPJ, rekap mingguan, pengingat kunci, rekonsiliasi
**Tujuan:** Pengingat otomatis batas SPJ (konfigurable) dan tugas rutin.

**Prompt (salin ke agen AI):**
```text
Buat perintah artisan di app/Console/Commands dan jadwalkan di routes/console.php (zona Asia/Jakarta,
->withoutOverlapping()->onOneServer()):
1. keuangan:pengingat-spj (setiap hari 07:00): untuk pengajuan berstatus dicairkan dengan SPJ belum diajukan
   atau berstatus dikembalikan: kirim PengingatBatasSpj bila hari ini = batas_spj − h untuk h di
   Pengaturan hari_pengingat_spj (mis. "3,0"); bila terlambat, kirim setiap interval_pengingat_terlambat hari
   (hitung dari batas_spj) dengan tembusan admin-keuangan. Opsi --dry-run mencetak daftar tanpa mengirim.
   Penerima: admin-unit & kepala-unit unit terkait.
2. keuangan:rekap-mingguan (Senin 07:30): RekapMingguan ke admin-keuangan, bpp, wadek: jumlah menunggu per tahap,
   SPJ terlambat per unit, pengembalian menunggu verifikasi.
3. keuangan:pengingat-kunci-periode (tanggal = Pengaturan tanggal_pengingat_kunci, 08:00): ingatkan
   admin-keuangan bila periode bulan lalu masih terbuka.
4. keuangan:rekonsiliasi (01:00): jalankan pemeriksaan F6.3; bila ada selisih kirim notifikasi database ke
   super-admin (tanpa --perbaiki otomatis).
5. keuangan:periksa-ulang-tautan (Minggu 03:00): dispatch PeriksaTautanBerkas untuk tautan_berkas aktif dengan
   dicek_pada null atau > 7 hari (lewati tautan milik SPJ yang sudah diterima — acuannya snapshot beku);
   opsi --dry-run.
6. keuangan:bersihkan-sementara (setiap jam, menit 15): hapus berkas di storage/app/tmp/{ekspor,impor} berumur
   > 24 jam (STANDAR-TEKNIS §1a.6); tandai laporan_ekspor terkait 'kedaluwarsa' bila tabelnya sudah ada.
7. horizon:snapshot setiap 5 menit.
8. Test Pest dengan Carbon::setTestNow dan Notification::fake: H-3 dan H-0 mengirim, H-2 tidak; terlambat
   1 hari tidak mengirim bila interval 3, terlambat 3 hari mengirim + tembusan; SPJ sudah diajukan tidak
   mengirim; --dry-run tidak mengirim; pengingat kunci hanya pada tanggal yang diatur; jadwal terdaftar
   (Artisan schedule:list memuat semua perintah di atas); periksa-ulang-tautan tidak menyentuh tautan SPJ diterima;
   bersihkan-sementara menghapus berkas tmp berumur 25 jam dan mempertahankan yang 1 jam.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `kup php artisan schedule:list` menampilkan jadwal di atas.
- [ ] `kup php artisan keuangan:pengingat-spj --dry-run` menampilkan daftar yang benar pada data demo.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(notifikasi): jadwalkan pengingat spj dan tugas rutin"
```

### F10.4 — Tutup fase F10
**Tujuan:** Merge dan tag `v0.10.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f10-notifikasi terhadap main: semua Notification ShouldQueue di antrean 'notifikasi';
tidak ada data pribadi/penerima di surel/WhatsApp; kredensial hanya dari env. Perbarui README: cara menjalankan
scheduler lokal (php artisan schedule:work) dan menguji surel di Mailpit. Perbarui CHANGELOG [0.10.0],
APP_VERSION=0.10.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] PR di-merge; tag `v0.10.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.10.0 notifikasi dan penjadwal"
git push -u origin feat/f10-notifikasi
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.10.0 -m "Fase 10: notifikasi & penjadwal"
git push origin main --tags
```

---

## F11 — Laporan, dasbor & ekspor

### F11.1 — Query laporan realisasi dan cache berversi
**Branch:** `feat/f11-laporan`
**Tujuan:** Satu sumber perhitungan untuk dasbor dan laporan periodik (bulanan/semester/tahunan) dengan cache Redis.

**Prompt (salin ke agen AI):**
```text
1. App\Support\VersiCacheDasbor: versi() membaca Cache 'dasbor:versi' (bawaan 1), naikkan() increment,
   ingat(string $kunci, Closure $fn, int $detik = 600) → Cache::remember("dasbor:v{versi}:{kunci}", ...).
   Pastikan naikkan() dipanggil di HitungUlangTotalPengajuan, KunciPeriode, BukaKunciPeriode, TetapkanPagu,
   UbahPaguDitetapkan.
2. App\Queries\LaporanRealisasiQuery dengan parameter objek App\Data\ParameterLaporan (tahunAnggaranId,
   jenisPeriode: bulanan|semester|tahunan, nilaiPeriode: 1–12 | 1–2 | null, ?unitId, ?akunId). Hasil per unit
   (dan opsional per akun): pagu, komitmen (BR-04), disetujui, dicairkan (dalam rentang), realisasi (dalam
   rentang, berdasarkan tanggal_bukti), realisasi_kumulatif s.d. akhir rentang, sisa pagu, serapan_persen
   (= realisasi_kumulatif / pagu × 100, 2 desimal, dihitung dengan BigDecimal; pagu 0 → null). Bila seluruh
   rentang berada pada periode terkunci, ambil angka dari rekap_periode (snapshot) — beri kolom sumber
   'snapshot'|'langsung'. Semester = bulan 1–6 / 7–12 (konstanta; lihat ADR 0002 butir 11).
3. App\Queries\SpjTerlambatQuery, RekapPajakQuery (per jenis × bulan × unit), RekapPengembalianQuery,
   PenggunaanDanaQuery (per prodi × kategori_penggunaan_dana × tahun; tiga tahun terakhir TS-2, TS-1, TS).
4. Pembatasan: semua query menerima User dan menerapkan terlihatOleh (unit sendiri untuk peran unit).
5. Test Pest dengan data uji terkontrol: angka per unit untuk bulanan Maret, semester 1, tahunan sesuai
   perhitungan manual; serapan persen benar; rentang terkunci memakai snapshot (ubah data mentah setelah kunci
   lewat query langsung → hasil tetap angka snapshot); cache dipakai (hit kedua tanpa query DB — gunakan
   DB::enableQueryLog) dan berganti setelah naikkan(); admin-unit hanya mendapat unitnya.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Test angka laporan cocok dengan perhitungan manual.
- [ ] Cache dasbor berganti otomatis setelah transaksi.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(laporan): tambah query realisasi anggaran dengan cache berversi"
```

### F11.2 — Dasbor anggaran vs realisasi per peran
**Tujuan:** Pimpinan, keuangan, dan unit melihat serapan anggaran sekilas (US-LAP-01).

**Prompt (salin ke agen AI):**
```text
Halaman App\Filament\Pages\Dasbor (ganti dasbor bawaan) dengan filter tahun anggaran & periode (bulan/semester/
tahun) di header (filtersForm). Widget (semua memakai LaporanRealisasiQuery via VersiCacheDasbor):
1. RingkasanPaguWidget (StatsOverview): pagu, disetujui, dicairkan, realisasi, serapan % — lingkup sesuai
   pengguna.
2. SerapanPerUnitChart (bar horizontal: pagu vs realisasi per unit) — hanya peran fakultas.
3. TrenRealisasiBulananChart (line: realisasi per bulan tahun berjalan, kumulatif).
4. SpjJatuhTempoWidget (tabel: pengajuan dengan batas_spj <= 7 hari lagi atau terlambat).
5. AntreanPersetujuanWidget (dari F5.3) untuk penyetuju.
Visibilitas widget per peran: pimpinan & wadek → 1,2,3,4; admin-keuangan & bpp → 1,2,3,4,5; kepala-unit &
admin-unit → 1,3,4 (unit sendiri). Warna grafik mengikuti tema panel dan tetap terbaca di mode gelap; label
rupiah disingkat (jt/M) hanya pada sumbu, tooltip menampilkan nilai lengkap. Responsif: di ponsel widget
bertumpuk satu kolom.
Test Pest (Livewire): pimpinan melihat SerapanPerUnitChart, admin-unit tidak; data widget admin-unit hanya
unitnya; filter semester mengubah angka; dasbor dimuat dengan jumlah query terbatas (< 30) berkat cache.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Dasbor pimpinan menampilkan serapan per unit; dasbor admin-unit hanya unitnya.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(dasbor): tambah dasbor anggaran vs realisasi per peran"
```

### F11.3 — Ekspor laporan Excel/PDF lewat antrean
**Tujuan:** LAP-01, LAP-02, LAP-07 diekspor tanpa membuat pengguna menunggu (US-LAP-02).

**Prompt (salin ke agen AI):**
```text
1. Migrasi laporan_ekspor (03-SKEMA §2.1). Enum StatusEkspor. Model LaporanEkspor (HasUuids).
2. Halaman App\Filament\Pages\Laporan (grup Laporan): form jenis laporan (LAP-01 Realisasi Anggaran, LAP-02
   Rekap Pengajuan per Status, LAP-07 Snapshot Periode), tahun, jenis periode & nilai, unit (hanya lingkup
   fakultas), format xlsx/pdf → membuat LaporanEkspor (antre) dan dispatch App\Jobs\BuatEksporLaporan
   (queue 'ekspor', timeout 600, tries 2). Laporan kecil (<= 2.000 baris) di-STREAM langsung ke browser
   (SimpleExcelWriter::streamDownload / dompdf stream) tanpa menyimpan berkas dan tanpa baris laporan_ekspor.
   Rate limit 'ekspor' 5/menit per pengguna. Pratinjau tabel di halaman (maks 50 baris) sebelum ekspor.
3. BuatEksporLaporan: status diproses → menulis berkas SEMENTARA ke storage/app/tmp/ekspor/{id}.{ext};
   xlsx memakai spatie/simple-excel (header berbahasa Indonesia, angka sebagai numerik 2 desimal, baris
   total); pdf memakai dompdf (A4 landscape, kop fakultas, parameter laporan, tanggal cetak, nama pencetak,
   catatan "angka periode terkunci berasal dari snapshot" bila relevan). Selesai → status selesai, ukuran,
   kedaluwarsa_pada = now + min(Pengaturan masa_berlaku_ekspor_jam, 24) jam; kirim EksporSiap (database) berisi tautan
   URL::temporarySignedRoute('ekspor.unduh', kedaluwarsa, id). Gagal → status gagal, pesan_galat ringkas,
   EksporGagal.
4. Route GET /ekspor/{laporanEkspor} name ekspor.unduh: middleware auth + signed; hanya peminta; 410 bila
   sudah kedaluwarsa/dihapus.
5. LaporanEksporResource (riwayat ekspor milik pengguna, unduh ulang selama belum kedaluwarsa).
6. Pastikan keuangan:bersihkan-sementara (F10.3) menghapus berkas ekspor > 24 jam dan menandai status
   kedaluwarsa; tidak ada salinan permanen keluaran sistem.
7. Test Pest (Queue::fake & sync): ekspor xlsx LAP-01 menghasilkan berkas dengan baris unit dan total benar
   (baca balik dengan SimpleExcelReader); pdf dibuat; pengguna lain mendapat 403 walau punya tautan;
   tautan tanpa signature → 403; ekspor ke-6 dalam semenit → 429; laporan kecil di-stream tanpa berkas tersisa
   di storage; berkas ekspor berumur > 24 jam terhapus dan unduhan → 410;
   admin-unit hanya dapat mengekspor unitnya (parameter unit lain diabaikan/ditolak).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Ekspor LAP-01 tahunan diproses Horizon dan notifikasi "siap diunduh" muncul.
- [ ] Angka di Excel sama dengan dasbor.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(laporan): ekspor laporan excel dan pdf lewat antrean"
```

### F11.4 — Laporan khusus: kartu kegiatan, SPJ terlambat, pajak, pengembalian, penggunaan dana
**Tujuan:** Melengkapi LAP-03 s.d. LAP-06 dan LAP-08.

**Prompt (salin ke agen AI):**
```text
Tambahkan ke halaman Laporan dan BuatEksporLaporan:
1. LAP-03 Kartu kegiatan (PDF, dari halaman view pengajuan juga): data pengajuan, RAB aktif + daftar versi
   revisi, pencairan, realisasi per item (dengan nomor bukti, tanpa melampirkan berkas), potongan pajak,
   pengembalian, linimasa riwayat status. Otorisasi Policy view pengajuan.
2. LAP-04 SPJ terlambat/jatuh tempo (xlsx): SpjTerlambatQuery — kolom unit, nomor, judul, batas SPJ, hari
   terlambat, status SPJ.
3. LAP-05 Rekap potongan pajak (xlsx): RekapPajakQuery — per bulan × jenis pajak × unit, nilai, jumlah bukti,
   jumlah dengan/ tanpa nomor bukti setor. Judul memuat catatan "Data informasi; tarif referensi perlu
   verifikasi".
4. LAP-06 Rekap pengembalian sisa dana (xlsx).
5. LAP-08 Penggunaan dana per prodi untuk akreditasi (xlsx): PenggunaanDanaQuery — baris kategori, kolom TS-2,
   TS-1, TS, rata-rata; judul memuat "Format instrumen akreditasi perlu verifikasi"; hanya unit berjenis prodi.
6. Akses sesuai 01-PRD §8 (bpp & admin-keuangan untuk LAP-05/06; kepala-unit unit sendiri untuk LAP-04/08).
7. Test Pest: setiap laporan menghasilkan berkas dengan baris/angka yang benar pada data uji; peran yang tidak
   berhak tidak melihat opsi laporan tsb dan request langsung ditolak; LAP-08 hanya memuat prodi.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Kelima laporan dapat diekspor dan isinya cocok dengan data uji.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(laporan): tambah kartu kegiatan, rekap pajak, dan penggunaan dana"
```

### F11.5 — Impor rekap honor ujian dari fkipapp (opsional sesuai ADR 0001)
**Tujuan:** Bila ADR 0001 memilih impor rekap, tampilkan total honor ujian di dasbor/laporan tanpa menghitung honor (BR-24). Bila ADR 0001 memilih "tanpa data honor", **lewati langkah ini** dan catat di CHANGELOG.

**Prompt (salin ke agen AI):**
```text
Baca docs/keputusan/0001-batas-cakupan.md bagian Keputusan; lanjutkan hanya bila memilih impor rekap honor.
1. Migrasi rekap_honor_eksternal (03-SKEMA §2.4). Model RekapHonorEksternal (HasUuids, TercatatAktivitas,
   MenjagaPeriodeTerkunci memakai periode_id → turunkan tanggal = periode.tanggal_mulai). Kolom
   nama_berkas_sumber menyimpan nama berkas saja.
2. Job App\Jobs\ImporRekapHonor (queue 'impor'): membaca Excel ekspor fkipapp via SimpleExcelReader dengan
   kolom: tahun, bulan, kode_prodi, jenis_ujian (proposal|seminar|sidang|lainnya), jumlah_kegiatan,
   jumlah_bruto, jumlah_pajak, jumlah_neto (sesuaikan nama kolom dengan contoh berkas di docs/referensi/ —
   bila berbeda, buat pemetaan kolom di satu konstanta). Validasi per baris: periode ada & terbuka, kode_prodi
   dipetakan ke unit.kode (tidak terpetakan → unit_id null + peringatan), neto = bruto − pajak (Uang). Semua
   baris satu batch_impor (UUID) dalam satu transaksi; bila ada baris tidak valid, batch gagal seluruhnya dan
   laporan galat per baris disimpan/ditampilkan.
3. RekapHonorEksternalResource (grup Laporan): tombol "Impor rekap honor" (admin-keuangan; satu-satunya
   FileUpload yang diizinkan: xlsx maks 10 MB ke storage/app/tmp/impor, diproses ImporRekapHonor lalu DIHAPUS di
   blok finally — sukses maupun gagal; tambahkan resource ini ke daftar pengecualian TanpaUnggahanTest),
   daftar per batch, aksi "Batalkan batch" (status dibatalkan, hanya bila periode
   terbuka). Tidak ada edit per baris.
4. Integrasi: LaporanRealisasiQuery dan dasbor menampilkan baris terpisah "Honor ujian (impor fkipapp)" —
   TIDAK digabung ke realisasi kegiatan dan TIDAK mengurangi pagu kecuali Pengaturan honor_mengurangi_pagu=true.
   Tambahkan LAP-09 Rekap honor ujian (xlsx).
5. Test Pest: impor berkas valid 6 baris → 6 baris aktif satu batch dan berkas tmp sudah terhapus; satu baris neto salah → seluruh batch
   ditolak; periode terkunci → ditolak; batalkan batch → tidak muncul di dasbor; honor tidak mengubah sisa pagu
   bila pengaturan false dan mengurangi bila true; tidak ada kolom per dosen/mahasiswa.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] (Bila dijalankan) rekap honor tampil sebagai baris terpisah di dasbor; tidak ada perhitungan honor di sistem ini.
- [ ] (Bila dilewati) CHANGELOG mencatat "Impor rekap honor tidak diaktifkan sesuai ADR 0001".
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "feat(impor): impor rekap honor ujian dari ekspor fkipapp"
```

### F11.6 — Tutup fase F11
**Tujuan:** Merge dan tag `v0.11.0`.

**Prompt (salin ke agen AI):**
```text
Tinjau perubahan feat/f11-laporan terhadap main: semua laporan memakai Queries (tidak ada agregasi di Blade
atau Resource), angka uang diambil sebagai string, ekspor berjalan di antrean, tautan unduh bertanda tangan.
Tambahkan test konsistensi: total realisasi LAP-01 tahunan = Σ total_realisasi pengajuan tahun itu = angka
dasbor. Perbarui CHANGELOG [0.11.0], APP_VERSION=0.11.0.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Test konsistensi angka hijau; PR di-merge; tag `v0.11.0`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v0.11.0 laporan dasbor dan ekspor"
git push -u origin feat/f11-laporan
# buka PR → review → squash merge
git switch main && git pull
git tag -a v0.11.0 -m "Fase 11: laporan, dasbor & ekspor"
git push origin main --tags
```

---

## F12 — Pengerasan & rilis

### F12.1 — Uji otorisasi menyeluruh, IDOR, dan konkurensi
**Branch:** `feat/f12-rilis`
**Tujuan:** Membuktikan matriks izin PRD §3.2 dan BR-07/BR-09/BR-21 dengan test sistematis.

**Prompt (salin ke agen AI):**
```text
1. tests/Feature/Otorisasi/MatriksIzinTest.php: dataset Pest berisi (peran, resource/halaman, aksi, harapan)
   yang diturunkan dari tabel docs/vibecoding/01-PRD.md §3.2 untuk semua Resource Filament & halaman (index,
   create, edit, view, aksi kustom). Jalankan sebagai pengguna per peran (dengan MFA dianggap aktif).
2. tests/Feature/Otorisasi/IdorTest.php: untuk setiap model ber-UUID (pengajuan, rab, pencairan, spj,
   realisasi_item, pengembalian, tautan_berkas, pagu, laporan_ekspor), admin-unit unit A mencoba view/edit milik
   unit B lewat URL → 403/404, dan URL tautan bukti unit B tidak pernah muncul di HTML/JSON Livewire yang
   diterima unit A; tidak ada route yang menerima id numerik untuk model tersebut (periksa daftar
   route).
3. tests/Feature/Konkurensi/LockTest.php (grup 'redis', dijalankan di CI yang punya Redis): gunakan store
   redis sungguhan; simulasikan dua proses (pcntl_fork bila tersedia, atau dua lock bersamaan) pada
   AjukanPengajuan dan SetujuiPengajuan → hanya satu sukses; Penomoran 100 panggilan paralel → 100 nomor unik.
4. tests/Feature/Otorisasi/PemisahanTugasTest.php: kumpulkan seluruh kasus BR-07 (pengajuan, revisi, pencairan,
   SPJ, pengembalian).
Perbaiki celah yang ditemukan di Policy/Action terkait (catat di ringkasan); jangan melonggarkan test.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Matriks izin teruji untuk 7 peran; tidak ada IDOR.
- [ ] Test konkurensi dengan Redis hijau di CI.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "test(auth): uji matriks izin, idor, dan konkurensi"
```

### F12.2 — Pengerasan keamanan
**Tujuan:** Menutup checklist keamanan di `05-UJI-PENERIMAAN.md` §3.

**Prompt (salin ke agen AI):**
```text
1. Middleware App\Http\Middleware\HeaderKeamanan (global web): X-Frame-Options SAMEORIGIN,
   X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin, Permissions-Policy
   (camera=(), geolocation=()), Strict-Transport-Security di production, Content-Security-Policy dasar
   yang kompatibel dengan Filament/Livewire dengan frame-src hanya https://drive.google.com https://docs.google.com
   dan img-src menyertakan https://lh3.googleusercontent.com (pratinjau tautan); uji di browser.
2. config/session.php: secure true di production, http_only, same_site lax; SESSION_SECURE_COOKIE=true di
   .env.example produksi (komentar).
3. Pastikan semua input teks panjang (deskripsi, catatan) dirender sebagai teks ter-escape (tidak ada {!! !!}
   kecuali konten aman); test XSS: payload <script> di deskripsi & catatan tidak tereksekusi di view, PDF,
   surel.
4. Log: pastikan activitylog/Log tidak memuat password, token, app_authentication_*; tambahkan processor
   Monolog yang menyamarkan kunci sensitif.
5. Jalankan composer audit dan npm audit --production; perbarui paket bermasalah dalam major yang sama.
6. Larastan level 6 tanpa baseline baru; bila ada baseline lama, kurangi.
7. APP_DEBUG=false dan halaman galat 403/404/419/429/500 berbahasa Indonesia tanpa jejak stack.
8. Test Pest: header keamanan ada (termasuk frame-src terbatas); XSS tidak tereksekusi; URL tautan
   javascript:, data:, http:, dan domain di luar daftar putih ditolak TautanBerkasValid; label tautan ter-escape;
   semua tautan keluar memakai rel="noopener noreferrer"; halaman 500 tidak memuat nama kelas.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] `kup composer audit` bersih; Larastan level 6 hijau.
- [ ] Checklist keamanan 05 §3 tercentang.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "fix(inti): perkuat header keamanan, sesi, dan sanitasi log"
```

### F12.3 — Docker produksi mengikuti pola docker-apps
**Tujuan:** Image produksi dan compose dengan container app, queue (Horizon), scheduler, MySQL 8.4, Redis 7 (STANDAR-TEKNIS §7).

**Prompt (salin ke agen AI):**
```text
Buat (meniru pola docker-apps fkipapp/plp; bila contoh tersedia di docs/referensi/, ikuti strukturnya):
1. docker/Dockerfile multi-stage: stage node:22 untuk npm ci && npm run build; stage composer untuk
   composer install --no-dev --optimize-autoloader; stage akhir php:8.4-fpm-alpine (atau FrankenPHP) dengan
   ekstensi pdo_mysql, redis, intl, bcmath, gd, zip, opcache; user non-root; salin aplikasi & aset build.
2. docker/nginx/default.conf: root /var/www/html/public, client_max_body_size 10M (hanya untuk impor Excel/CSV;
   tidak ada unggahan bukti),
   blokir akses ke dotfiles, gzip, header cache untuk /build.
3. docker/entrypoint.sh: php artisan migrate --force (hanya container app bila RUN_MIGRATIONS=true),
   php artisan optimize, php artisan filament:optimize, php artisan icons:cache, storage:link TIDAK dipakai
   (tidak ada berkas publik/unggahan).
4. docker-compose.prod.yml: services app (php-fpm), web (nginx), queue (php artisan horizon), scheduler
   (php artisan schedule:work), mysql:8.4 (volume, utf8mb4, time_zone +07:00), redis:7-alpine (appendonly yes,
   volume); volume kecil storage/app/tmp bersama untuk app/queue/scheduler (ekspor/impor sementara ≤ 24 jam); healthcheck web memanggil /api/health;
   restart unless-stopped; env dari berkas .env.production (tidak di-commit).
5. .dockerignore (vendor, node_modules, .env*, storage/app/tmp, tests, docs/referensi/rahasia).
6. docs/deploy.md: langkah deploy pertama, pembaruan versi (pull tag → build → up -d → migrate), rollback ke
   tag sebelumnya, cara membuka Horizon.
7. Job CI baru 'docker' (hanya pada tag v*) yang membangun image untuk memastikan Dockerfile valid.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
Subpath produksi PERSIS STANDAR-TEKNIS §2.4: APP_URL & ASSET_URL = https://supportfkip.unsil.ac.id/keuangan,
SESSION_PATH=/keuangan, SESSION_COOKIE=keuangan_session, SESSION_SECURE_COOKIE=true, TRUSTED_PROXIES = IP reverse proxy,
npm run build dengan ASSET_URL terisi, dan blok location /keuangan/ untuk reverse proxy dicatat di docs/DEPLOY.md.
```

**Selesai bila:**
- [ ] `docker compose -f docker-compose.prod.yml up -d --build` di server uji berjalan; `/api/health` = ok.
- [ ] Horizon memproses antrean di container `queue`; scheduler berjalan di container `scheduler`.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "build: tambah dockerfile dan compose produksi"
```

### F12.4 — Cadangan, pemulihan, dan migrasi data (opsi a)
**Tujuan:** Backup harian MySQL dengan retensi ≥ 30 hari dan uji pulih (server tidak menyimpan berkas pengguna); migrasi data `laporankeuangan` bila ADR 0001 memilih opsi (a).

**Prompt (salin ke agen AI):**
```text
1. docker/backup/backup.sh: mysqldump --single-transaction --routines --triggers | gzip ke
   /backup/mysql/keuangan-{Y-m-d-His}.sql.gz (tidak ada arsip storage — bukti ada di Google Drive unit); hapus berkas
   > RETENSI_HARI (bawaan 30); tulis checksum sha256; keluar non-zero bila gagal. Tambahkan service 'backup'
   di docker-compose.prod.yml (cron harian 01:30 WIB) atau dokumentasikan cron host.
2. docker/backup/restore.sh: memulihkan dump ke lingkungan uji (menolak berjalan bila
   APP_ENV=production kecuali --paksa).
3. docs/runbook-cadangan.md: jadwal, lokasi, retensi, prosedur uji pulih per semester (checklist + tabel log
   uji), siapa penanggung jawab; bagian "Berkas bukti di Google Drive": anjuran Shared Drive fakultas/unit,
   pemindahan kepemilikan saat pegawai pindah/purnatugas, dan bahwa tautan_berkas_beku (di dalam dump MySQL) adalah
   jejak audit URL + waktu.
4. Bila docs/keputusan/0001-batas-cakupan.md memilih opsi (a): buat perintah php artisan
   keuangan:impor-historis {berkas} yang mengimpor rekap historis dari ekspor laporankeuangan (format mengikuti
   contoh di docs/referensi/; bila belum ada contoh, buat kerangka dengan pemetaan kolom di satu konstanta dan
   test dengan berkas contoh sintetis) ke tabel yang sesuai (minimal pagu dan pengajuan berstatus selesai
   dengan RAB satu item per akun), dalam satu transaksi, dengan mode --dry-run dan laporan selisih total.
   Bila opsi (b), lewati butir ini dan tulis di runbook bahwa data lama tetap di laporankeuangan.
5. Test: skrip backup diuji dengan bats/shellcheck di CI (shellcheck minimal); perintah impor historis diuji
   dengan berkas contoh sintetis (dry-run tidak menulis apa pun; mode nyata menghasilkan total yang sama).

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Backup harian berjalan dan uji pulih ke server uji berhasil (dicatat di runbook).
- [ ] (Opsi a) impor historis dry-run menunjukkan total yang cocok dengan laporan lama.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "build: tambah skrip cadangan mysql dan impor historis"
```

### F12.5 — Panduan pengguna dan pelaksanaan UAT
**Tujuan:** Pengguna dilatih dan UAT `05-UJI-PENERIMAAN.md` dijalankan di server uji.

**Prompt (salin ke agen AI):**
```text
1. docs/panduan/ berisi panduan singkat per peran (admin-unit, kepala-unit, admin-keuangan, bpp,
   wadek-keuangan, pimpinan, super-admin): langkah-langkah bernomor dengan nama menu persis seperti di panel,
   termasuk menyimpan bukti di Shared Drive unit, mengatur berbagi "Terbatas/Universitas Siliwangi",
   menyalin link dari aplikasi Drive di ponsel, menyatakan berkas final, membaca dasbor, mengunci periode. Bahasa Indonesia sederhana.
2. docs/uat/hasil-uat.md: salin tabel skenario dari docs/vibecoding/05-UJI-PENERIMAAN.md §1 dengan kolom
   tambahan Penguji, Tanggal, Hasil (Lulus/Gagal), Catatan; dan daftar isu temuan.
3. Seeder UatSeeder (hanya APP_ENV=staging): pengguna uji per peran dengan email penguji dari env
   UAT_EMAILS, tahun anggaran, pagu, dan beberapa pengajuan pada berbagai status.
4. Tautan "Panduan" di menu pengguna Filament mengarah ke halaman Blade yang merender docs/panduan (markdown →
   HTML, ter-escape) atau ke URL panduan.
Test Pest: UatSeeder menolak berjalan di production; halaman panduan dapat diakses pengguna login.

Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] UAT dijalankan perwakilan setiap peran; semua skenario kritis "Lulus" atau isunya sudah diperbaiki (`fix/...` → PR).
- [ ] Berita acara UAT ditandatangani Admin Keuangan dan Wakil Dekan bidang keuangan.
- [ ] `kup php artisan test` hijau.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(panduan): tambah panduan pengguna dan lembar hasil uat"
```

### F12.6 — Panduan per peran: HTML + tangkapan layar di landing page
**Tujuan:** Setelah semua fitur selesai, membuat panduan pengguna **HTML** untuk setiap peran lengkap dengan tangkapan layar otomatis, tersaji di `/keuangan/panduan/` dan ditautkan dari landing page (STANDAR-TEKNIS §2.5).

Persiapan manual (lokal saja):
```bash
kup php artisan migrate:fresh --seeder=PanduanSeeder     # akun demo per peran + data rekaan
docker compose --profile panduan run --rm keuangan-panduan     # setelah skrip dibuat agen
```

**Prompt (salin ke agen AI):**
```text
Baca 01-PRD §3 (peran & hak akses), 05-UJI §2 (skenario per peran), STANDAR-TEKNIS §2.4–§2.5.
1. database/seeders/PanduanSeeder.php — hanya APP_ENV=local (lempar exception di lingkungan lain): satu akun demo
   per peran (panduan.<peran>@contoh.test, kata sandi dari env PANDUAN_PASSWORD, MFA dinonaktifkan khusus akun demo
   lokal) dan data rekaan secukupnya agar setiap layar terisi. Dilarang memakai data pribadi asli.
2. docker-compose.yml: service keuangan-panduan dengan profiles: ["panduan"], image mcr.microsoft.com/playwright
   (tag stabil terbaru), jaringan fkip-net, volume .:/work, working_dir /work, env PANDUAN_BASE_URL=http://gateway/keuangan
   dan PANDUAN_PASSWORD, command node tests/panduan/tangkap.mjs. Skrip tests/panduan/tangkap.mjs (Playwright,
   Chromium, viewport 1366×768; tambah 390×844 untuk alur yang dipakai di ponsel): untuk setiap peran → login →
   jalankan alur utama peran sesuai 05-UJI §2 → simpan public/panduan/img/<peran>/NN-<langkah>.png (lebar maks
   1366, dikompres). Kaburkan/hilangkan token, kode MFA, dan surel nyata bila tampil.
3. public/panduan/index.html (daftar peran + ringkasan aplikasi) dan public/panduan/<peran>.html untuk SETIAP
   peran: HTML statis mandiri (CSS di dalam berkas, tanpa CDN, tanpa JS wajib), bahasa Indonesia, berisi tujuan
   peran, cara masuk, langkah bernomor dengan gambar (alt deskriptif) + keterangan, tanya-jawab singkat, kontak
   admin, tanggal & versi aplikasi. Semua href/src RELATIF (img/<peran>/…, index.html) agar berfungsi di
   /keuangan/panduan/. Responsif di ponsel; siap cetak (CSS @media print).
4. Landing page aplikasi (halaman publik "/" — bila tidak ada, halaman login): tautan jelas "Panduan Pengguna" ke
   asset('panduan/index.html'); panel Filament: tautan panduan di menu pengguna/footer.
5. composer.json script "panduan" yang menjalankan service keuangan-panduan; README bagian "Memperbarui panduan".
6. Ganti panduan Markdown lama (docs/PANDUAN-*.md, bila ada) dengan tautan ke HTML ini agar tidak ada dua versi.
7. tests/Feature/PanduanTest.php: setiap peran di seeder peran punya public/panduan/<peran>.html dan tercantum di
   index.html; setiap <img src> menunjuk berkas yang ada; tidak ada href/src berawalan "/" atau "http://localhost";
   landing page memuat tautan panduan; ukuran total public/panduan ≤ 15 MB; PanduanSeeder menolak APP_ENV=production.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- `http://localhost:8080/keuangan/panduan/` menampilkan daftar peran; setiap halaman peran berisi tangkapan layar terbaru dan tautannya berfungsi.
- Landing page aplikasi menautkan panduan.
- `PanduanTest` dan seluruh test hijau.
- Langkah manusia: kartu aplikasi di landing page `supportfkip` (root `index.html`) diberi tautan `keuangan/` dan `keuangan/panduan/`.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(panduan): tambah panduan html per peran dengan tangkapan layar"
```

### F12.7 — Rilis v1.0.0
**Tujuan:** Rilis produksi pertama dengan checklist go-live `05-UJI-PENERIMAAN.md` §4.

**Prompt (salin ke agen AI):**
```text
Persiapan rilis 1.0.0:
1. Periksa seluruh checklist go-live di docs/vibecoding/05-UJI-PENERIMAAN.md §4 dan laporkan butir yang belum
   dapat diverifikasi dari kode (mis. SMTP produksi, DNS, sertifikat) sebagai daftar tugas manusia.
2. Pastikan seeder produksi hanya menjalankan PeranDanIzinSeeder, PenggunaAwalSeeder, PengaturanSeeder,
   JenisPencairanSeeder, JenisPajakSeeder (tarif & akun contoh TIDAK dijalankan di production bila ADR 0002
   sudah menyediakan data resmi — tambahkan pengecekan dan pesan).
3. Daftar "perlu verifikasi" yang masih terbuka (dari ADR 0002, tarif pajak, jenis pencairan, kategori
   akreditasi) ditampilkan sebagai lencana di halaman Pengaturan untuk super-admin & admin-keuangan.
4. CHANGELOG [1.0.0] dengan ringkasan fitur per modul; APP_VERSION=1.0.0; README diperbarui (tautan panduan,
   deploy, runbook).
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan
perubahan.
```

**Selesai bila:**
- [ ] Checklist go-live 05 §4 tercentang seluruhnya.
- [ ] Tautan portal Support System FKIP diperbarui sesuai ADR 0001 (opsi a: ganti `/laporankeuangan/`; opsi b: tambah tautan baru berlabel jelas).
- [ ] Tag `v1.0.0` ter-*push* dan image produksi dibangun dari tag tersebut.

**Commit:**
```bash
kup ./vendor/bin/pint && kup php artisan test \
  && git add -A \
  && git commit -m "docs(changelog): rilis v1.0.0 keuangan fkip"
git push -u origin feat/f12-rilis
# buka PR → review → squash merge
git switch main && git pull
git tag -a v1.0.0 -m "Rilis produksi pertama Keuangan FKIP Unsil"
git push origin main --tags
```

---

## Setelah v1.0.0

- Perbaikan bug: branch `fix/<ringkas>` → PR → tag *patch* (`v1.0.1`).
- Perubahan kebijakan (batas hari SPJ, jenjang persetujuan, tarif referensi) cukup lewat halaman Pengaturan/master — **bukan** perubahan kode.
- Setiap semester: uji pulih cadangan, tinjau daftar "perlu verifikasi", perbarui pedoman Boost (`kup php artisan boost:update`).
