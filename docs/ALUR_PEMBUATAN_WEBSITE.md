# Alur Pembuatan Website SIMAK (Urutan Pembuatan File)

Dokumen ini menjelaskan **urutan/langkah membuat website SIMAK dari nol**: file mana yang dibuat lebih dulu, file mana yang bergantung pada file mana, dan mengapa urutannya begitu. Perhatikan **Aturan Kunci** di setiap tahap: file yang bergantung pada banyak file lain harus dibuat belakangan (misal `PdfController` dibuat terakhir karena butuh controller laporan + data DB yang sudah jadi).

> Pendamping: `docs/DOKUMENTASI_SIMAK.md`, `docs/STRUKTUR_FILE_SIMAK.md`, `docs/ALUR_PROSES.md`, dan `docs/CSS_LEMBAR_PDF_REPORT.md` (letak desain lembar PDF).

---

## Ringkasan Urutan (peta besar)

```
Tahap 1  Fondasi proyek        → .gitignore, .env.example, struktur folder
Tahap 2  Konfigurasi inti      → config/env.php → config/database.php → config/session.php
Tahap 3  Database              → skema tabel (users, mahasiswa, dosen, dst.)
Tahap 4  Autentikasi           → includes/ + AuthController + halaman login
Tahap 5  Kerangka tampilan     → includes/header.php, footer.php, base.css, app.js
Tahap 6  Dashboard 3 role      → dashboard.css
Tahap 7  Master data (admin)   → AdminController, MasterDataController
Tahap 8  Fitur mahasiswa       → MahasiswaController (IRS, KHS, presensi, tugas)
Tahap 9  Fitur dosen           → DosenController (presensi, nilai, tugas, broadcast)
Tahap 10 Persetujuan admin     → validasi_irs, verifikasi_nilai
Tahap 11 Laporan (HTML)        → LaporanController + admin/laporan.php + laporan.css
Tahap 12 PDF (Dompdf)          → PdfController + cetak_pdf.php (3 role)
Tahap 13 Profil                → ProfileController + profil.php + profil.css
Tahap 14 Email                 → SmtpMailer, EmailHelper, template/email
Tahap 15 Halaman report cetak  → report_krs, report_nilai, khs, irs_print + report.css
Tahap 16 Rapikan & uji         → .env, logs, test, dokumentasi
```

**Logika urutannya:** yang tanpa dependensi dulu (fondasi → config), lalu dari "bawah ke atas" arsitektur (DB → controller → view), dan fitur yang saling bergantung diselesaikan berurutan (IRS dulu baru validasi IRS; laporan HTML dulu baru PDF).

---

## Tahap 1 — Fondasi proyek (paling awal, tanpa dependensi)

| # | File / folder | Kenapa dulu |
|----|---|---|
| 1.1 | **`.gitignore`** | WAJIB paling pertama! Baris `.env` harus ada **sebelum** file `.env` nyata dibuat, supaya rahasia tidak pernah ter-track oleh git. |
| 1.2 | `.env.example` | Template kosong (tanpa nilai rahasia) sebagai kontrak tim: variabel apa saja yang dibutuhkan (`SIMAK_DB_*`, `SIMAK_EMAIL_*`). |
| 1.3 | Struktur folder | `config/`, `controllers/`, `includes/`, `helper/`, `template/email/`, `libraries/`, `docs/`, `logs/`, `public/` (+ `public/assets/{css,js,img,fontawesome}`, `public/uploads/avatars/`). |

---

## Tahap 2 — Konfigurasi inti (`config/`)

Urutan tetap, karena tiap file memakai file sebelumnya:

1. **`config/env.php`** — loader `.env` + helper `env()`. Semua file lain membaca konfigurasi lewat ini, jadi harus paling dulu.
2. **`config/database.php`** — koneksi PDO MySQL memakai `env('SIMAK_DB_*')` dari `env.php`. Menghasilkan `$pdo` global.
3. **`config/session.php`** — sesi, **`checkAccess($roles)`** (role guard), flash message, **CSRF**, `redirectToDashboard()`.

> Setiap halaman yang dibuat mulai Tahap 4 akan `require` ketiga file ini — itulah kenapa mereka dulu.

---

## Tahap 3 — Skema database

Buat tabel sesuai kebutuhan fitur (urutan mengikuti foreign key):

```
users ──┬── mahasiswa      (semester, jurusan)
        ├── dosen          (fakultas, jurusan)
        ├── fakultas / jurusan / matakuliah / jadwal
        ├── irs            (status: pending | approved | rejected)
        ├── presensi_sessions + presensi
        ├── grades         (status_verifikasi: draft | verified)
        ├── tasks + task_submissions
        └── announcements + announcement_reads
```

---

## Tahap 4 — Autentikasi

| # | File | Ketergantungan |
|----|---|---|
| 4.1 | `controllers/AuthController.php` | config/session.php, database.php |
| 4.2 | `public/index.php` (halaman login) + `public/assets/css/login.css` | AuthController |
| 4.3 | `public/login.php` (handler POST) | AuthController |
| 4.4 | `public/logout.php` | — |

> Login adalah pintu masuk; semua halaman berikutnya memakai role guard, jadi ini dibuat sebelum fitur apa pun.

---

## Tahap 5 — Kerangka tampilan bersama

| # | File | Catatan |
|----|---|---|
| 5.1 | `includes/header.php` | Sidebar per role, guard login, alert flash, dan **`$page_css_map`** (peta CSS per halaman — nanti dianinkar di setiap tahap fitur). |
| 5.2 | `includes/footer.php` | Menutup app shell + memuat `app.js`. |
| 5.3 | `public/assets/css/base.css` | Tema global (layout, sidebar, tabel, tombol, modal, badge) — jadi CSS dasar untuk semua halaman. |
| 5.4 | `public/assets/js/app.js` | Interaksi umum: modal, toggle sidebar, konfirmasi hapus, auto-submit filter, dsb. |
| 5.5 | Aset | `assets/img/logo_poltek.jpg` (juga dipakai kop PDF!), Font Awesome lokal, favicon. |

---

## Tahap 6 — Dashboard 3 role

`public/admin/dashboard.php`, `public/dosen/dashboard.php`, `public/mahasiswa/dashboard.php` memakai **`dashboard.css`** (kartu statistik, grid). Data awal disediakan oleh `AdminController::getDashboardStats()` / `DosenController::getDashboardStats()` / `MahasiswaController::getDashboardStats()` yang mulai dikembangkan di tahap berikut.

---

## Tahap 7 — Master data (admin)

Urutan mengikuti foreign key — data induk dulu:

1. `public/admin/fakultas.php` + `controllers/MasterDataController.php`
2. `public/admin/jurusan.php`
3. `public/admin/mahasiswa.php` + `*mahasiswa_get.php` (Ajax)
4. `public/admin/dosen.php` + `*dosen_get.php` — di sini **`controllers/AdminController.php`** mulai dikembangkan besar (CRUD transaksional `users` + data role)
5. `public/admin/matakuliah.php` + `*matakuliah_get.php`
6. `public/admin/jadwal.php` + `*jadwal_get.php` (deteksi bentrok)

Semua halaman ini hanya memakai `base.css`.

---

## Tahap 8 — Fitur mahasiswa (`controllers/MahasiswaController.php`)

Urutan dalam controller:

1. **IRS** — `getIRS`, `getPaketBySemester`, `createIRSFromPaket` (transaksi, maks 24 SKS, anti-duplikat), `deleteIRS`, `validateAksesSemester` → halaman `public/mahasiswa/irs.php` + **`irs.css`**
2. **KHS/IPK** — `getKHS`, `hitungIPS`, `hitungIPK`, `getDetailIPK`
3. **Presensi** — `getActivePresensiSession`, `konfirmasiPresensi` (triple validasi) → `mahasiswa/presensi.php` + `public/api/check_validasi.php` (Ajax polling)
4. **Tugas** — `getAllTugas`, `submitTugas`, `getTugasMendekat` → `mahasiswa/tugas.php`

> IRS dibuat sebelum dosen/admin, karena fitur presensi dan validasi admin bertumpuk pada data IRS.

---

## Tahap 9 — Fitur dosen (`controllers/DosenController.php`)

1. **Presensi** — `bukaSesiPresensi` (kode 6 char), `tutupSesiPresensi`, `cekSesiPresensi`, `inputPresensiMassal`, `getRekapPresensi` → `dosen/presensi.php`
2. **Nilai** — `getMahasiswaForNilai`, `inputNilaiMassal` (UPSERT `grades`, status draft/verified) → `dosen/nilai.php`
3. **Tugas** — `createTugas`, `getSubmissions`, `nilaiSubmission` → `dosen/tugas.php`
4. **Broadcast** — `sendBroadcast`, `kirimBroadcastViaEmail` → `dosen/broadcast.php`
5. `dosen/jadwal.php`

---

## Tahap 10 — Persetujuan admin

Dibuat **setelah** IRS & nilai mahasiswa ada datanya, karena mengonsumsinya:

1. `public/admin/validasi_irs.php` — approve/reject IRS (per baris & massal) → `AdminController::approveIRS/...`; memakai **`irs.css`**
2. `public/admin/verifikasi_nilai.php` — nilai draft → verified → `AdminController::verifyGrade/...`; juga memakai **`irs.css`**
3. `public/admin/broadcast.php` — broadcast ke semua role

---

## Tahap 11 — Laporan HTML (admin)

1. **`controllers/LaporanController.php`** — semua query laporan + pengisi dropdown filter
2. `public/admin/laporan.php` — 5 tab (Nilai MK / Rekap Nilai / IRS / Presensi / Mahasiswa) → **`laporan.css`**
3. Setiap tab punya tombol PDF yang mengarah ke `cetak_pdf.php` (dibuat di Tahap 12)

---

## Tahap 12 — PDF (Dompdf) — paling akhir dari jalur "laporan"

Karena membutuhkan LaporanController (Tahap 11), format data di semua controller, dan logo (Tahap 5):

1. **`controllers/PdfController.php`** — 7 generator PDF + util kop surat (`getHeader`), tanda tangan (`getFooter`), dan style lembar PDF (`getBaseStyle` — CSS-nya inline di file ini, bukan file CSS publik).
2. Tiga router per role:
   - `public/admin/cetak_pdf.php` → 5 laporan admin
   - `public/dosen/cetak_pdf.php` → `generateLaporanNilaiDosen` (+ `dosen/laporan_nilai.php`)
   - `public/mahasiswa/cetak_pdf.php` → `report_krs`, `report_nilai`

> Detail letak desain lembar PDF: lihat `docs/CSS_LEMBAR_PDF_REPORT.md`.

---

## Tahap 13 — Profil (semua role)

1. **`controllers/ProfileController.php`** — `getProfile`, `uploadFoto` (→ `public/uploads/avatars/`), `updateAccount`, `changePassword`
2. `public/profil.php` + `public/profil_update.php` + **`profil.css`**

---

## Tahap 14 — Email

1. **`includes/SmtpMailer.php`** — klien SMTP mandiri
2. **`helper/EmailHelper.php`** — pembungkus + membaca `SIMAK_EMAIL_*` dari `.env`; fallback mode log ke `logs/email/`
3. **`template/email/`** — template HTML (broadcast, reset password)
4. Sambungkan ke pemanggil yang sudah ada: broadcast dosen (Tahap 9), token presensi via email.

---

## Tahap 15 — Halaman report & cetak mahasiswa/dosen

Halaman dokumen read-only bergaya kertas:

- `mahasiswa/report_krs.php`
- `mahasiswa/report_nilai.php`
- `mahasiswa/khs.php`
- `dosen/laporan_nilai.php`
- `mahasiswa/irs_print.php` (cetak via print CSS browser, **bukan** Dompdf)

Semuanya memakai **`report.css`** lewat entri `$page_css_map` di `includes/header.php`.

---

## Tahap 16 — Rapikan, amankan, uji

1. `.env` nyata (diisi rahasia; sudah dijaga `.gitignore`) — pastikan `git status` tidak pernah menampilkannya.
2. `logs/` runtime (email, pdf) — tidak di-commit.
3. Uji alur saja-per-role: login → IRS → validasi admin → presensi → nilai → verifikasi → KHS → laporan → PDF → profil.
4. Halaman debug (`public/test.php`, `mahasiswa/debug.php`, `test_email.php`) — hanya untuk development, terblokir di `.gitignore`.
5. Tulis/perbarui dokumentasi di `docs/`.

---

## Aturan umum urutan pembuatan (untuk fitur baru)

1. Apa yang dibutuhkan fitur ini di **database**? — ubah skema dulu.
2. Logika bisnis masuk ke **controller**, bukan di halaman.
3. Baru buat **halaman** di `public/<role>/`, daftarkan CSS-nya di `$page_css_map` (header.php) kalau butuh CSS khusus.
4. Kalau butuh interaksi khusus, tambahkan ke **`app.js`**.
5. Laporan baru → tambahkan query ke `LaporanController` **dulu**, baru generator ke `PdfController`.

---

*Dibuat dari struktur kode aktual proyek SIMAK. November 2026.*
