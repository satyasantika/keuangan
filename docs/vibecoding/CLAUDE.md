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
