# Berkas referensi

Contoh dokumen yang dikumpulkan dari pihak keuangan untuk memandu perancangan (lihat ADR 0002, bagian "Contoh dokumen yang dikumpulkan").

Aturan:

- Berkas tanpa data pribadi (contoh format RAB, TOR/KAK, format SPJ, format laporan bulanan) boleh disimpan di folder ini.
- Berkas yang memuat data pribadi (kuitansi asli, NPWP/NIK, nomor rekening, tanda tangan) HANYA disimpan di `docs/referensi/rahasia/`. Folder itu masuk `.gitignore` dan tidak boleh di-commit.
- Dilarang menaruh dump basis data, kredensial, atau token di folder ini.
