# 0002 — Parameter keuangan fakultas

- Status: Usulan
- Tanggal: 2026-10-07

Tabel parameter yang harus dikonfirmasi pihak keuangan FKIP/Unsil. Aplikasi memakai nilai usulan sebagai data master/pengaturan yang dapat diubah; **tidak ada angka regulasi yang dikarang** (nilai yang tidak diketahui ditulis "diisi bagian keuangan"). Sebelum F3, minimal parameter 4, 5, 6, dan 9 harus berstatus Terkonfirmasi. **Catatan 2026-10-07:** pemilik sistem memerintahkan agen memilih nilai rekomendasi terbaik agar F3 dapat dikerjakan; parameter 4, 5, 6, dan 9 diberi status "Rekomendasi, perlu verifikasi" dan wajib dikonfirmasi bagian keuangan sebelum produksi.

| No | Parameter | Nilai usulan sementara | Sumber/dasar (peraturan, SK) | Status | Dikonfirmasi oleh | Tanggal |
|---|---|---|---|---|---|---|
| 1 | Status kelembagaan Unsil (`pola_pengelolaan`): PTN Satker atau PTN-BLU | `satker` (hanya konfigurasi, BR-25) | Penetapan status Unsil oleh Kemdikbudristek/Kemenkeu — dokumen belum dikumpulkan | Perlu verifikasi | | |
| 2 | Status PP 23/2005 jo. PP 74/2012 dan perubahan terkininya | Dirujuk sebagai dasar bila BLU; tidak memengaruhi logika inti | PP 23/2005 (salinan di `docs/referensi/`), PP 74/2012; perubahan terkini belum diperiksa | Perlu verifikasi | | |
| 3 | Jenis pencairan di tingkat fakultas dan istilahnya (UP, TUP, LS) | Master `jenis_pencairan`: UP, TUP, LS (`perlu_verifikasi=true`) | PMK tata cara pembayaran APBN — nomor terbaru belum diperiksa | Perlu verifikasi | | |
| 4 | Jenjang persetujuan: verifikasi Kaprodi/Kepala Unit; penyetuju akhir (Wadek keuangan atau PPK); keberadaan BPP di fakultas | Kepala unit (opsional, aktif) → admin-keuangan → wadek-keuangan (opsional, aktif); BPP ada | Struktur organisasi & SK Dekan — belum dikumpulkan | Rekomendasi, perlu verifikasi (dipakai F3; nilai disimpan sebagai data master/pengaturan sehingga dapat diubah tanpa perubahan kode) | | |
| 5 | Batas hari SPJ setelah kegiatan | 14 hari kalender (`batas_hari_spj`) | Peraturan Rektor/Dekan tentang batas SPJ — belum dikumpulkan | Rekomendasi, perlu verifikasi (dipakai F3; nilai disimpan sebagai data master/pengaturan sehingga dapat diubah tanpa perubahan kode) | | |
| 6 | Format nomor pengajuan dan SPJ | `PGJ/{tahun}/{urut}`, `SPJ/{tahun}/{urut}` | Kebiasaan tata naskah fakultas | Rekomendasi, perlu verifikasi (dipakai F3; nilai disimpan sebagai data master/pengaturan sehingga dapat diubah tanpa perubahan kode) | | |
| 7 | Tarif referensi PPh 21, PPh 22, PPh 23, PPh 4(2), PPN beserta dasar hukum dan tanggal berlaku | **Diisi bagian keuangan** (tidak diisi angka sendiri; sistem tidak menghitung pajak otomatis, BR-16) | UU PPh, UU PPN (UU 7/2021) dan peraturan pelaksana — pasal/tarif perlu dicek | Perlu verifikasi | | |
| 8 | Berkas BAS (daftar kode akun) resmi yang dipakai Unsil | Seeder hanya contoh (`sumber=contoh`); diganti lewat impor BAS resmi | PMK/KMK BAS terbaru — nomor perlu dicek | Perlu verifikasi | | |
| 9 | Daftar resmi unit/prodi FKIP, kode singkat, dan Ketua/Kepala unit | Contoh: `DEKANAT`, `TU-FKIP`, `PMAT`, `PBIO`, `PBI`; **diganti daftar resmi** | SK Dekan / struktur organisasi FKIP | Rekomendasi, perlu verifikasi (dipakai F3; nilai disimpan sebagai data master/pengaturan sehingga dapat diubah tanpa perubahan kode) | | |
| 10 | Kategori penggunaan dana untuk laporan akreditasi (LKPS/LAMDIK) | Kategori contoh (operasional pendidikan, kemahasiswaan, penelitian, PkM, investasi SDM/sarana/prasarana) | Instrumen akreditasi LAMDIK/LKPS yang berlaku — format perlu dicek | Perlu verifikasi | | |
| 11 | Definisi "semester" pada laporan | Semester anggaran (Jan–Jun, Jul–Des) | Kebiasaan pelaporan keuangan; berbeda dari semester akademik | Perlu verifikasi | | |
| 12 | Retensi arsip dokumen keuangan (tahun) | Diisi bagian keuangan (sistem tidak menghapus transaksi final, BR-22) | Peraturan kearsipan/keuangan — perlu dicek | Perlu verifikasi | | |
| 13 | Perlu tidaknya mencatat NPWP/NIK penerima untuk bukti potong | Tidak dicatat pada v1.0 (privasi); NPWP hanya bila diwajibkan | Kebijakan privasi & ketentuan bukti potong — perlu dicek | Perlu verifikasi | | |
| 14 | Siapa yang boleh membuka kunci periode | `wadek-keuangan`, dengan alasan tertulis (BR-19/20) | Kebijakan fakultas | Perlu verifikasi | | |

## Contoh dokumen yang dikumpulkan

Disimpan di `docs/referensi/` (tanpa data pribadi); yang berisi data pribadi di `docs/referensi/rahasia/` (di-gitignore). Aturan lengkap: `docs/referensi/README.md`.

| Dokumen | Status | Lokasi |
|---|---|---|
| UU No. 17 Tahun 2003 tentang Keuangan Negara | Ada | `docs/referensi/UU-No-17-Tahun-2003-Keuangan-Negara.pdf` |
| PP No. 23 Tahun 2005 tentang Pengelolaan Keuangan BLU | Ada | `docs/referensi/PP-No-23-Tahun-2005-Pengelolaan-Keuangan-BLU.pdf` |
| Contoh RAB | Belum | |
| Contoh TOR/KAK | Belum | |
| Contoh kuitansi/nota (tanpa data pribadi; yang asli di `rahasia/`) | Belum | |
| Format SPJ | Belum | |
| Format laporan bulanan ke universitas | Belum | |
