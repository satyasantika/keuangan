// Tangkapan layar panduan per peran.
// Jalankan: composer panduan  (atau docker compose -f docker-compose.panduan.yml --profile panduan run --rm keuangan-panduan)
// Prasyarat lokal: php artisan migrate:fresh --seeder=PanduanSeeder ; PANDUAN_PASSWORD sama dengan .env.
// Keluaran: public/panduan/img/<peran>/NN-<langkah>.png (viewport 1366×768; alur ponsel 390×844).
import { mkdir } from 'node:fs/promises';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ?? 'playwright');

const BASE = (process.env.PANDUAN_BASE_URL ?? 'http://localhost:8035/keuangan').replace(/\/$/, '');
const SANDI = process.env.PANDUAN_PASSWORD;
const KELUARAN = new URL('../../public/panduan/img/', import.meta.url).pathname;

if (!SANDI) {
    console.error('PANDUAN_PASSWORD wajib diisi.');
    process.exit(1);
}

const LAYAR = { width: 1366, height: 768 };
const PONSEL = { width: 390, height: 844 };

/** Buka record pertama dari halaman daftar (tautan baris /panel/<sumber>/<uuid>). */
async function bukaPertama(page, path, sumber) {
    await buka(page, path);
    const tautan = page.locator(`a[href*="/panel/${sumber}/"]`).filter({ hasNot: page.locator('text=Buat') });
    const hrefs = await tautan.evaluateAll((el) => el.map((a) => a.getAttribute('href')).filter((h) => /\/[0-9a-f-]{36}$/.test(h)));
    if (hrefs.length === 0) return false;
    await page.goto(hrefs[0]);
    await tunggu(page);
    return true;
}

async function tunggu(page) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
}

async function buka(page, path) {
    await page.goto(BASE + path);
    await tunggu(page);
}

/** Langkah per peran: [nama-berkas, fungsi persiapan, opsi]. */
const ALUR = {
    'admin-unit': [
        ['dasbor', (p) => buka(p, '/panel')],
        ['daftar-pengajuan', (p) => buka(p, '/panel/pengajuan')],
        ['buat-pengajuan', (p) => buka(p, '/panel/pengajuan/create'), { penuh: true }],
        ['rincian-pengajuan', (p) => bukaPertama(p, '/panel/pengajuan', 'pengajuan'), { penuh: true }],
        ['spj-realisasi', (p) => bukaPertama(p, '/panel/spj', 'spj'), { penuh: true }],
        ['pengembalian', (p) => buka(p, '/panel/pengembalian')],
        ['ponsel-dasbor', (p) => buka(p, '/panel'), { ponsel: true }],
        ['ponsel-pengajuan', (p) => bukaPertama(p, '/panel/pengajuan', 'pengajuan'), { ponsel: true }],
    ],
    'kepala-unit': [
        ['menunggu-tindakan', (p) => buka(p, '/panel/menunggu-tindakan')],
        ['verifikasi-pengajuan', (p) => bukaPertama(p, '/panel/menunggu-tindakan', 'pengajuan'), { penuh: true }],
        ['dasbor-unit', (p) => buka(p, '/panel')],
        ['laporan', (p) => buka(p, '/panel/laporan')],
    ],
    'admin-keuangan': [
        ['dasbor', (p) => buka(p, '/panel')],
        ['tahun-anggaran', (p) => bukaPertama(p, '/panel/tahun-anggaran', 'tahun-anggaran'), { penuh: true }],
        ['pagu-unit', (p) => buka(p, '/panel/pagu')],
        ['menunggu-tindakan', (p) => buka(p, '/panel/menunggu-tindakan')],
        ['verifikasi-spj', (p) => bukaPertama(p, '/panel/spj', 'spj'), { penuh: true }],
        ['laporan', (p) => buka(p, '/panel/laporan')],
        ['rekap-honor', (p) => buka(p, '/panel/rekap-honor')],
        ['pengaturan', (p) => buka(p, '/panel/pengaturan'), { penuh: true }],
    ],
    bpp: [
        ['menunggu-tindakan', (p) => buka(p, '/panel/menunggu-tindakan')],
        ['daftar-pencairan', (p) => buka(p, '/panel/pencairan')],
        ['rincian-pencairan', (p) => bukaPertama(p, '/panel/pencairan', 'pencairan'), { penuh: true }],
        ['spj', (p) => buka(p, '/panel/spj')],
        ['pengembalian', (p) => buka(p, '/panel/pengembalian')],
    ],
    'wadek-keuangan': [
        ['menunggu-tindakan', (p) => buka(p, '/panel/menunggu-tindakan')],
        ['persetujuan-akhir', (p) => bukaPertama(p, '/panel/menunggu-tindakan', 'pengajuan'), { penuh: true }],
        ['pagu-unit', (p) => buka(p, '/panel/pagu')],
        ['periode', (p) => bukaPertama(p, '/panel/tahun-anggaran', 'tahun-anggaran'), { penuh: true }],
        ['revisi-rab', (p) => buka(p, '/panel/revisi-rab')],
    ],
    pimpinan: [
        ['dasbor', (p) => buka(p, '/panel'), { penuh: true }],
        ['laporan', (p) => buka(p, '/panel/laporan')],
        ['daftar-pengajuan', (p) => buka(p, '/panel/pengajuan')],
        ['ponsel-dasbor', (p) => buka(p, '/panel'), { ponsel: true }],
    ],
    'super-admin': [
        ['pengguna', (p) => buka(p, '/panel/users')],
        ['buat-pengguna', (p) => buka(p, '/panel/users/create'), { penuh: true }],
        ['pengaturan', (p) => buka(p, '/panel/pengaturan')],
    ],
};

/** Halaman publik (tanpa masuk): landing dan halaman masuk. */
async function tangkapUmum(browser) {
    await mkdir(KELUARAN + 'umum', { recursive: true });
    const konteks = await browser.newContext({ viewport: LAYAR, locale: 'id-ID', timezoneId: 'Asia/Jakarta', deviceScaleFactor: 1 });
    const page = await konteks.newPage();
    await buka(page, '/');
    await page.screenshot({ path: KELUARAN + 'umum/landing.png', fullPage: true });
    await buka(page, '/panel/login');
    await page.screenshot({ path: KELUARAN + 'umum/masuk.png' });
    await konteks.close();
    console.log('✓ img/umum/landing.png, img/umum/masuk.png');
}

async function masuk(page, peran) {
    await page.goto(BASE + '/panel/login');
    await page.fill('input[type=email]', `panduan.${peran}@contoh.test`);
    await page.fill('input[type=password]', SANDI);
    await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }), page.click('button[type=submit]')]);
    await tunggu(page);
}

const peranDipilih = process.argv.slice(2);
const browser = await chromium.launch();
let gagal = 0;

if (peranDipilih.length === 0 || peranDipilih.includes('umum')) await tangkapUmum(browser);

for (const [peran, langkah] of Object.entries(ALUR)) {
    if (peranDipilih.length && !peranDipilih.includes(peran)) continue;
    await mkdir(KELUARAN + peran, { recursive: true });

    for (const ponsel of [false, true]) {
        const daftar = langkah.map((l, i) => [i, ...l]).filter(([, , , o]) => Boolean(o?.ponsel) === ponsel);
        if (daftar.length === 0) continue;

        const konteks = await browser.newContext({ viewport: ponsel ? PONSEL : LAYAR, locale: 'id-ID', timezoneId: 'Asia/Jakarta', deviceScaleFactor: 1 });
        const page = await konteks.newPage();
        await masuk(page, peran);

        for (const [i, nama, siapkan, opsi] of daftar) {
            const berkas = `${KELUARAN}${peran}/${String(i + 1).padStart(2, '0')}-${nama}.png`;
            try {
                await siapkan(page);
                // Samarkan surel pengguna pada menu bila tampil.
                await page.screenshot({ path: berkas, fullPage: Boolean(opsi?.penuh), mask: [page.locator('input[type=password]')] });
                console.log('✓', berkas.replace(KELUARAN, 'img/'));
            } catch (e) {
                gagal++;
                console.error('✗', peran, nama, e.message);
            }
        }

        await konteks.close();
    }

    // Halaman profil/ganti kata sandi (sama untuk semua peran) dan, untuk super-admin, dialog impor massal.
    const konteksProfil = await browser.newContext({ viewport: LAYAR, locale: 'id-ID', timezoneId: 'Asia/Jakarta', deviceScaleFactor: 1 });
    const halaman = await konteksProfil.newPage();
    try {
        await masuk(halaman, peran);
        await buka(halaman, '/panel/profile');
        await halaman.screenshot({ path: `${KELUARAN}${peran}/profil.png`, fullPage: true, mask: [halaman.locator('input[type=password]')] });
        console.log('✓', `img/${peran}/profil.png`);

        if (peran === 'super-admin') {
            await buka(halaman, '/panel/users');
            await halaman.getByRole('button', { name: 'Impor massal (tempel)' }).click();
            await halaman.waitForTimeout(800);
            await halaman.screenshot({ path: `${KELUARAN}${peran}/impor-tempel.png` });
            console.log('✓', `img/${peran}/impor-tempel.png`);
        }
    } catch (e) {
        gagal++;
        console.error('✗', peran, 'profil', e.message);
    }
    await konteksProfil.close();
}

await browser.close();
process.exit(gagal ? 1 : 0);
