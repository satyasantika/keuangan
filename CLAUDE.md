# CLAUDE.md — Keuangan FKIP Unsil

Berkas konteks untuk agen AI (Claude Code, dsb.). Salin ke root repo aplikasi. Bila Laravel Boost menambahkan pedomannya, bagian di bawah ini tetap di atas dan tidak boleh diringkas.

## Ringkasan sistem

Aplikasi pencatatan & pelaporan keuangan kegiatan Fakultas Keguruan dan Ilmu Pendidikan (FKIP) Universitas Siliwangi. Siklus: **pagu unit per tahun → pengajuan kegiatan + RAB → persetujuan berjenjang → pencairan → SPJ/realisasi dengan bukti → potongan pajak (informasi) → pengembalian sisa dana → kunci periode → dasbor & laporan**.

Peran (`App\Enums\Peran`): `super-admin`, `admin-keuangan`, `bpp` (Bendahara Pengeluaran Pembantu), `wadek-keuangan` (penyetuju akhir / PPK), `kepala-unit`, `admin-unit`, `pimpinan`. Peran `bpp`, `wadek-keuangan`, `kepala-unit` disesuaikan dengan struktur Unsil.

Batas cakupan: **honor ujian tidak dihitung di sini** (milik `fkipapp`; paling jauh impor rekap total). Hubungan dengan aplikasi lama `/laporankeuangan/` ditentukan `docs/keputusan/0001-batas-cakupan.md` — baca sebelum mengerjakan laporan/impor.

Dokumen acuan: `docs/vibecoding/01-PRD.md` (aturan bisnis BR-xx, matriks izin), `02-ARSITEKTUR.md`, `03-SKEMA-DATABASE.md`, `04-PROMPT-BERTAHAP.md`, `05-UJI-PENERIMAAN.md`, `STANDAR-TEKNIS.md`, `STANDAR-GIT.md`.

## Stack & versi

PHP 8.4 · Laravel `^13.0` · Filament `^5.0` (Livewire 4) · Tailwind CSS 4 · MySQL 8.4 LTS (`utf8mb4_0900_ai_ci`) · Redis 7 (DB 32 default/lock, 33 cache, 34 queue) · Horizon · spatie/laravel-permission · spatie/laravel-activitylog · brick/math · barryvdh/laravel-dompdf (stream, tanpa simpan) · spatie/simple-excel · endroid/qr-code · Pest 4 · Larastan level 6 · Pint · Laravel Boost. Locale `id`, zona waktu `Asia/Jakarta`.

Gunakan tool `search-docs` Laravel Boost untuk API Laravel 13/Filament 5/Livewire 4 yang tidak Anda yakini; jangan menebak nama metode.

## Perintah penting

```bash
docker compose up -d                 # layanan lokal (mysql, redis, mailpit)
kup php artisan migrate:fresh --seed        # hanya lokal; DemoSeeder untuk data contoh
docker compose restart keuangan-horizon   # pekerja antrean berjalan sebagai service; restart setelah ubah kode job
docker compose logs -f keuangan-scheduler   # penjadwal berjalan sebagai service
kup ./vendor/bin/pint                       # format
kup ./vendor/bin/phpstan analyse --memory-limit=1G
kup php artisan test --parallel             # atau ./vendor/bin/pest
kup composer cek                            # pint --test + phpstan + pest
kup php artisan keuangan:rekonsiliasi       # cek total denormalisasi
kup php artisan keuangan:pengingat-spj --dry-run
kup php artisan keuangan:periksa-ulang-tautan --dry-run
```
Mailpit: http://localhost:8025 · Horizon: `/horizon` (super-admin) · Panel: `/panel`.

## Konvensi

- **Kunci**: semua primary key `id` **UUIDv7** CHAR(36) (`HasUuids`, STANDAR-TEKNIS §4a); FK `foreignUuid`, polimorfik `uuidMorphs`; URL, QR, dan API memakai `id` yang sama (tanpa kolom `ulid`).
- **Lingkungan**: Docker FKIP (STANDAR-TEKNIS §2) — semua perintah PHP/Composer/NPM lewat `kup` = `docker compose exec keuangan-php`; aplikasi di http://localhost:8014; MySQL native di host (`host.docker.internal`, DB `keuangan`/`keuangan_testing`); Redis bersama DB 32/33/34; Mailpit bersama; Horizon & scheduler berjalan sebagai service `keuangan-horizon`/`keuangan-scheduler`. Git dijalankan di host.
- **Subpath**: produksi di `https://supportfkip.unsil.ac.id/keuangan` (lokal `http://localhost:8080/keuangan`). Semua URL lewat `route()`/`url()`/`asset()` atau path relatif — dilarang `href="/…"`, `src="/…"`, `action="/…"`, `fetch('/…')` (STANDAR-TEKNIS §2.4). Cookie `keuangan_session` ber-path `/keuangan`.
- **Panduan**: setelah semua fitur selesai, panduan HTML per peran + tangkapan layar Playwright di `public/panduan/`, ditautkan dari landing page (STANDAR-TEKNIS §2.5).
- **Commit hanya bila test lulus**: jalankan blok Commit sebagai rantai `&&`; jangan `--no-verify`.
- **Bahasa domain Indonesia**: tabel/kolom `snake_case` bentuk tunggal (`pengajuan`, `rab_item`, `realisasi_item`); setiap model WAJIB `protected $table`. Kata kerja teknis Laravel tetap Inggris (`store`, `handle`).
- **Status = PHP Enum** di `app/Enums` (`StatusPengajuan`, `StatusRab`, `StatusSpj`, …) dengan `HasLabel`, `HasColor`, `bolehBerpindahKe()`. Kolom `status` VARCHAR, di-cast ke Enum, **tidak** di `$fillable`.
- **Actions** (`app/Actions/<Modul>/<KataKerja><Objek>`), satu kelas satu aksi bisnis, method `handle()`. Setiap transisi status: `DenganKunci::jalankan()` → `DB::transaction()` → `lockForUpdate()` → cek ulang status/izin/pemisahan tugas → ubah → `CatatRiwayatStatus` (dalam transaksi yang sama).
- **Policy** untuk setiap model & Resource Filament; Resource bercakupan unit memakai scope `terlihatOleh(auth()->user())`. Route record memakai `id`.
- **Uang**: `DECIMAL(15,2)`, cast `decimal:2`, aritmetika lewat `App\Support\Uang` (brick/math, HALF_UP). Volume `DECIMAL(12,2)`.
- **Berkas = tautan, bukan unggahan** (STANDAR-TEKNIS §1a, `BERKAS_MODE=tautan`): semua lampiran/bukti disimpan sebagai baris `tautan_berkas` (polimorfik) lewat `App\Actions\Berkas\SimpanTautanBerkas` → kontrak `App\Contracts\PenyimpananBerkas` (implementasi `TautanEksternal`), divalidasi `App\Rules\TautanBerkasValid`, diperiksa job `PeriksaTautanBerkas`. Bukti keuangan (kuitansi, nota, faktur, bukti transfer, bukti setor) wajib konfirmasi akses terbatas domain unsil.ac.id. Pratinjau via `drive.google.com/file/d/<id>/preview` / `lh3.googleusercontent.com/d/<id>`. Satu-satunya input berkas adalah impor Excel/CSV yang dihapus setelah diproses.
- **SPJ**: ajukan wajib pernyataan berkas final; diterima → `BekukanTautanSpj` menyalin tautan ke `tautan_berkas_beku` (URL + waktu); setelah itu tautan terkunci.
- **Keluaran** PDF/Excel di-*stream*; ekspor besar lewat antrean ke `storage/app/tmp`, dihapus ≤ 24 jam.
- **Periode**: model transaksi bertanggal memakai trait `MenjagaPeriodeTerkunci`.
- **Cache dasbor/laporan**: lewat `VersiCacheDasbor::ingat()`; panggil `VersiCacheDasbor::naikkan()` setelah perubahan angka.
- **Notifikasi**: `ShouldQueue` di antrean `notifikasi`; dipicu event `StatusBerubah`, bukan dipanggil dari Resource.
- **Queries laporan** di `app/Queries`; tidak ada agregasi di Blade/Resource.
- Test Pest untuk setiap perilaku: minimal jalur sukses + gagal otorisasi + pelanggaran aturan bisnis terkait.

## Aturan bisnis ringkas (rincian di 01-PRD §6)

- **BR-01** Uang DECIMAL(15,2), tanpa float, pembulatan HALF_UP per item.
- **BR-02** `jumlah = volume × harga_satuan` dan `total RAB = Σ item` dihitung server; abaikan total dari klien.
- **BR-03** Pengajuan hanya diubah saat `draf`/`dikembalikan`; sejak disetujui hanya lewat revisi bernomor.
- **BR-04** Total pengajuan ≤ sisa pagu unit (pagu − komitmen) saat diajukan dan dicek ulang saat disetujui; pagu harus `ditetapkan`.
- **BR-05** Nomor `PGJ/{tahun}/{urut}` terbit saat diajukan, di bawah Redis lock + `lockForUpdate`; tidak berubah.
- **BR-06** Jenjang: kepala-unit (opsional) → admin-keuangan → wadek-keuangan (opsional); snapshot pengaturan saat diajukan; kembalikan/tolak wajib catatan.
- **BR-07** Pemisahan tugas per user: pembuat ≠ penyetuju; satu orang satu tahap; pencatat pencairan ≠ penyetuju akhir; verifikator SPJ/pengembalian ≠ pembuatnya.
- **BR-08** Setiap perubahan status → `riwayat_status` (siapa, kapan, dari→ke, catatan, tahap) dalam transaksi yang sama.
- **BR-09** Aksi status dibungkus lock Redis per record (anti dobel klik).
- **BR-10/11** Revisi RAB bernomor, satu yang berjalan, versi lama `digantikan`; item revisi ≥ realisasi item asal; realisasi dipetakan ke item baru.
- **BR-12** Σ pencairan ≤ total RAB aktif; bukti wajib.
- **BR-13** `batas_spj = max(selesai kegiatan, pencairan pertama) + batas_hari_spj` (pengaturan), disimpan saat pencairan pertama.
- **BR-14** Σ realisasi per item ≤ jumlah item RAB aktif; Σ per pengajuan ≤ total RAB aktif.
- **BR-15** Bukti = tautan tervalidasi (https, daftar putih domain, bukan pemendek/folder); realisasi wajib tautan kuitansi/nota/faktur; bukti keuangan dibagikan terbatas unsil.ac.id (dikonfirmasi; job menandai yang publik); `drive_file_id` sama di pengajuan lain → indikasi bukti ganda.
- **BR-16** Pajak (PPh 21/22/23, PPN, …) dicatat sebagai informasi; nilai diinput manual; tarif referensi disalin dari master; **jangan menghitung pajak otomatis**.
- **BR-17** SPJ `draf → diajukan → diterima/dikembalikan`; ajukan wajib pernyataan berkas final; saat diterima tautan bukti dibekukan (snapshot URL + waktu di `tautan_berkas_beku`, terhubung ke `riwayat_status`) lalu terkunci.
- **BR-18** Sisa dana = dicairkan − realisasi; selesai bila SPJ diterima dan sisa nol/sudah disetor terverifikasi.
- **BR-19/20** Periode terkunci & tahun ditutup tidak dapat ditulis; buka kunci hanya wadek-keuangan dengan alasan.
- **BR-21** Pengguna unit hanya melihat unitnya; URL pakai UUID.
- **BR-22/23** Transaksi final tidak dihapus — dibatalkan beralasan; pembatalan pengajuan hanya bila tanpa pencairan aktif.
- **BR-24** Honor ujian tidak dihitung; rekap fkipapp baca-saja, tidak mengurangi pagu kecuali pengaturan.
- **BR-25** `pola_pengelolaan` (satker/BLU) hanya konfigurasi; tidak ada percabangan logika inti.
- **BR-26** Kolom `total_*` pengajuan dihitung ulang server (`HitungUlangTotalPengajuan`); `keuangan:rekonsiliasi` memeriksa.

## Larangan

- Jangan memasang Laravel Sail dan jangan menambah container MySQL/Redis/Mailpit ke repo; pakai infrastruktur bersama (STANDAR-TEKNIS §2).
- Jangan memakai `$table->id()`, `foreignId()`, `morphs()`, atau `HasUlids`/auto-increment untuk tabel aplikasi (STANDAR-TEKNIS §4a).
- Jangan commit `.env`, kredensial (SMTP, token WhatsApp), dump DB, atau isi `storage/app/tmp`.
- **Jangan menambah `FileUpload`, `<input type="file">`, `UploadedFile`, atau `Storage::put*` untuk berkas pengguna** — gunakan `tautan_berkas`. Pengecualian hanya impor Excel/CSV yang diproses lalu dihapus. Jangan menyimpan keluaran PDF/Excel secara permanen.
- Jangan menampilkan URL tautan bukti kepada peran yang gagal Policy, dan jangan memasukkannya ke surel/WhatsApp.
- Jangan mengubah migrasi yang sudah dirilis/di-merge — buat migrasi baru.
- Jangan memakai `float`/`double` untuk uang, dan jangan menghitung uang dengan operator PHP biasa — pakai `App\Support\Uang`.
- Jangan menaruh logika otorisasi di Blade/view; gunakan Policy.
- Jangan menulis kolom `status` atau `total_*` di luar Action; jangan menghapus transaksi final.
- Jangan menghitung tarif pajak atau honor ujian.
- Jangan mengarang fakta regulasi, kode akun, tarif, atau istilah; tandai "perlu verifikasi" dan jadikan data master/pengaturan.
- Jangan membuat URL berbasis `id` numerik untuk data transaksi; jangan mengunduh isi berkas Drive di server (pemeriksaan hanya HEAD/Range tanpa cookie).
- Jangan membangun integrasi SSO FKIP Edu, SAKTI/SPAN, atau perbankan (di luar cakupan).
- Jangan mengubah berkas di luar cakupan langkah yang sedang dikerjakan.

## Alur kerja per langkah

1. Baca langkah di `docs/vibecoding/04-PROMPT-BERTAHAP.md` dan BR yang disebut.
2. Kerjakan hanya cakupan langkah itu, termasuk test Pest.
3. Setiap selesai langkah: jalankan `kup ./vendor/bin/pint` dan `kup php artisan test` (idealnya `composer cek`), lalu tampilkan ringkasan perubahan.
4. **Jangan commit sendiri.** Tunggu manusia meninjau `git diff` dan meng-commit dengan Conventional Commits (`feat(<modul>): ...`, lihat `STANDAR-GIT.md`).

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
