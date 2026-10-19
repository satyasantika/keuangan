# 0004 — Pelaksanaan Profil Ringan di kode: pilihan teknis

- Status: Menunggu persetujuan pemilik
- Tanggal: 2026-10-16

Pilihan yang diambil saat menerapkan keputusan pemilik 15-16 Oktober 2026 (Profil Ringan, PHP 8.5, MariaDB native di host, UUID native) pada kode
(cabang `feat/profil-ringan`; padanan K-09 di repo `surat`). Butir bertanda (!) berbeda dari teks standar, menyentuh kebijakan, atau butuh keputusan pemilik.

1. Nama antrean `notifikasi` (notifikasi, listener, tautan) digabung ke `default`; ekspor laporan dan impor rekap honor ke koneksi `database-berat` (antrean `berat`),
   begitu pula importer Filament `AkunImporter` dan `UnitImporter` (`getJobConnection`/`getJobQueue`). Antrean `ekspor` dan `impor` tidak ada lagi.
2. (!) Worker `default` memakai `--memory=256` (tidak tercantum di standar §1b). `scheduler` diberi `mem_limit: 768m` karena worker berjalan sebagai subproses
   (256m + 512m); `app` 512m, `web` 64m, `backup` 128m. Semua angka memori adalah **perkiraan awal**, wajib dibuktikan dengan `docker stats` (STANDAR-TEKNIS §7.1).
3. `/api/health` membalas 503 hanya bila DB gagal. Job tertunda > 10 menit, job gagal 24 jam, dan detak penjadwal (> 5 menit) bersifat informatif. Kunci `status` (`ok`/`gagal`)
   dipertahankan karena dipakai `scripts/deploy.sh` dan healthcheck `web`; kunci `redis` dihapus dan `db` kini bernilai `ok`/`gagal` (sebelumnya boolean).
4. (!) `Cache::lock` di `DenganKunci` **dipertahankan** di atas store cache `database` (tabel `cache_locks`; `config/keuangan.php` `lock_store` bawaan `database`), sesuai instruksi tidak menulis ulang ke
   `lockForUpdate`. Jaring pengaman `DB::transaction` + `lockForUpdate` + UNIQUE tetap ada. Tes konkurensi (`tests/Feature/Konkurensi/LockTest.php`) kini memakai store `database` sungguhan
   (koneksi sendiri di luar transaksi tes), tidak lagi dilewati bila Redis tidak ada. Pada satu kali suite penuh paralel (16 proses) tes "penomoran 100 panggilan paralel" gagal (65 dari 100 nomor):
   kunci ditolak (`AksiSedangDiprosesException`, batas tunggu 3 detik) saat DB sibuk adalah perilaku sah, jadi tes kini mengulangi penolakan (maks. 30 kali) sebelum menyatakan gagal. Latensi lock di tabel di bawah beban
   produksi belum diukur.
5. `PERMISSION_CACHE_STORE` bawaan diubah dari `redis` ke `default` (cache `database`).
6. Horizon dicabut seluruhnya (paket, `config/horizon.php`, provider, gate `viewHorizon`, `horizon:snapshot`, rute `/horizon`, tes, panduan super-admin). Keuangan tidak memiliki izin `horizon.*` di seeder
   (akses lewat gate peran), sehingga **tidak ada migrasi data**; yang tidak dapat diverifikasi dari repo: tidak ada baris `permissions` bernama `horizon*` di data produksi.
7. (!) Nama service compose `web` dan jalur cadangan `./backup/mysql` **dipertahankan** (tidak diganti `nginx`/`backup/mariadb`) karena `scripts/deploy.sh`, runbook, dan operasi yang ada bergantung padanya; penggantian menunggu keputusan pemilik.
8. (!) Service `backup` tetap container berjalan dengan cron 01:30 WIB (rancangan lama, dipakai `deploy.sh`), kini hanya klien `mariadb-dump`. Ia memakai `DB_USERNAME`/`DB_PASSWORD` aplikasi (bukan root;
   `DB_ROOT_PASSWORD` dihapus). **Nama pengguna DB (`keuangan`) tidak diubah** dan hak yang dibutuhkan dump (SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT, routine) harus diberikan: keputusan pemilik.
9. (!) `uji.yml` (CI) tetap ada tetapi tanpa service Redis; MariaDB 10.11 sebagai service container sementara. Tidak dijalankan di sesi ini (tanpa akses GitHub).
10. `composer.json` `^8.5`; `composer.lock` hanya berubah pada hash dan metadata URL phpdotenv. Pencabutan Horizon ikut menghapus `laravel/sentinel` dan `symfony/polyfill-php83`; tidak ada paket lain yang bergeser versi.
11. Gerbang uji UUID lulus di MariaDB 10.11.14: kolom `id` bertipe `uuid` native, tipe FK sama dengan acuan, 100 id UUIDv7 terbaca `ORDER BY id` sesuai urutan pembuatan, relasi polimorfik berfungsi.
12. (!) Peralihan produksi memindahkan data dari container `mysql` 8.4 (database `keuangan`, kolasi `utf8mb4_0900_ai_ci`, kolom UUID `char(36)`) ke MariaDB host (`db_keuangan`): lihat `docs/deploy.md`
    "Peralihan dari v1.0.0". Prosedur data **belum diuji** pada data nyata. Perubahan di luar repo yang dibutuhkan: hapus service `keuangan-horizon` dari `/home/satya/code/docker-compose.yml`
    (dev lokal), dan sediakan database serta hak di MariaDB host.

Yang menunggu pemilik: poin 2 (angka memori), 7 (nama service dan jalur cadangan), 8 (pengguna DB), 9 (CI), jadwal jendela henti peralihan, dan target kinerja bila diperlukan.
