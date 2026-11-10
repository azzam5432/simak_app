# Panduan File & Struktur Kode SIMAK

Dokumen ini menjelaskan **file per file**: isinya apa saja, memanggil controller apa, dan **letak desain (CSS) tiap fitur ada di file mana**.

> Pendamping: `docs/DOKUMENTASI_SIMAK.md` (file, role, fitur, laporan, database) dan `docs/ALUR_PROSES.md` (alur teknis tiap proses).

---

## 1. Peta Besar Proyek

```
simak_app/
├── .env                       # Kredensial DB & email (TIDAK di-commit)
├── .env.example               # Template .env untuk tim
├── config/                    # Konfigurasi inti (env, database, session)
├── controllers/               # SEMUA logika bisnis (8 controller)
├── includes/                  # Header/sidebar & footer bersama + SMTP mailer
├── helper/                    # EmailHelper (utilitas kirim email)
├── template/email/            # Template HTML email (broadcast, reset password)
├── libraries/dompdf/          # Librari PDF (bundel)
├── docs/                      # Dokumentasi proyek
├── logs/                      # Log runtime (email/, pdf/, css_audit.php)
└── public/                    # SEMUA yang diakses browser (entry points)
    ├── index.php, login.php, logout.php, profil.php, profil_update.php
    ├── admin/                 # 12 halaman utama + 4 *_get.php (ajax)
    ├── dosen/                 # 8 halaman
    ├── mahasiswa/             # 11 halaman
    ├── api/check_validasi.php # Ajax status sesi presensi
    └── assets/                # css/, js/, img/, fontawesome/, favicon.ico, uploads/
```

**Konvensi penting:** setiap halaman di `public/` adalah *view + controller tipis* — cek `checkAccess(['role'])`, panggil controller, lalu include `includes/header.php`, render HTML, include `includes/footer.php`. Logika SQL tidak pernah ditulis langsung di halaman.

---

## 2. Folder `config/`

| File | Isi |
|---|---|
| `config/env.php` | Loader file `.env`. Fungsi `env($key, $default)`, `env_bool()`, `loadEnvFile()`. Prioritas: env OS → file `.env` → default kode. |
| `config/database.php` | Koneksi PDO MySQL dari env (`SIMAK_DB_*`). Set ERRMODE exception, fetch assoc, `SET NAMES utf8mb4`. Gagal koneksi → die dengan pesan. Menyediakan helper `debugQuery()`. Variable `$pdo` dipakai semua halaman. |
| `config/session.php` | Jantung sesi & keamanan: `session_start()`, **`checkAccess($roles)`** (role guard + halaman 403 + redirect login), `isLoggedIn()`, flash message (`redirectWithMessage()`, `getFlashMessage()`), **CSRF** (`generateCsrfToken()`, `validateCsrfToken()`), `getCurrentUser($pdo)`, dan **`redirectToDashboard()`** (map role → URL dashboard). |

---

## 3. Folder `controllers/` (logika bisnis)

| File | Isi (grup fungsi) |
|---|---|
| `AuthController.php` | `login($username, $password)` (verify bcrypt + isi `$_SESSION`: user_id, username, nama, role, email, login_time), `logout()`, `checkSession()`, `getCurrentUser()`. |
| `AdminController.php` | Terbesar (~1400 baris). CRUD **mahasiswa & dosen** (`createMahasiswa/update/delete`, `createDosen/...` — masing-masing membuat `users` + baris data role dalam **satu transaksi**, password di-hash), CRUD **mata kuliah**, CRUD **jadwal** dengan deteksi bentrok `cekBentrokJadwal()`, **validasi IRS** (`getPendingIRS`, `approveIRS`, `rejectIRS`, `approveAllIRS`, `getIRSStats`), **verifikasi nilai** (`getDraftGrades`, `verifyGrade`, `verifyAllGrades`, `rejectGrade`, `getNilaiStats`), `getDashboardStats()`, list hari/fakultas/jurusan. |
| `MasterDataController.php` | CRUD **fakultas** & **jurusan** (+ FK guard: data yang masih dirujuk tidak bisa dihapus). |
| `DosenController.php` | `getDashboardStats`, `getJadwalMengajar/getJadwalHariIni`, `getMatakuliahDosen`, **presensi** (`bukaSesiPresensi` — kode 6 char, `tutupSesiPresensi`, `cekSesiPresensi`, `getAllActiveSessions`, `inputPresensiMassal`, `getRekapPresensi`), **nilai** (`getMahasiswaForNilai`, `inputNilaiMassal` — UPSERT `grades`, status draft/verified), **tugas** (`createTugas`, `deleteTugas`, `getSubmissions`, `nilaiSubmission`), **broadcast** (`sendBroadcast`, `kirimBroadcastViaEmail`, `getBroadcastHistory`, `kirimTokenPresensiViaEmail`). |
| `MahasiswaController.php` | **IRS** (`getIRS`, `getPaketBySemester`, `getPaketCourses`, `createIRSFromPaket` — transaksi + maks 24 SKS + anti-duplikat, `deleteIRS` (hanya `pending`), `validateAksesSemester`, `getIRSForPrint`), **KHS/IPK** (`getKHS`, `hitungIPS`, `hitungIPK`, `getDetailIPK`, `getSemestersWithGrades`), **presensi** (`getActivePresensiSession`, `konfirmasiPresensi` — triple validasi), **tugas** (`getAllTugas`, `submitTugas`, `getTugasMendekat`), `getDashboardStats`, `getRekapPresensi`. |
| `LaporanController.php` | Semua query laporan HTML admin: `getLaporanNilai` + `getStatistikNilai`, `getRekapNilaiMahasiswa`, `getLaporanIRS` + `getStatistikIRS` + `getRekapIRSPerMahasiswa`, `getLaporanPresensi` + `getStatistikPresensi`, `getLaporanMahasiswa`, plus pengisi dropdown (`getMatakuliahList`, `getMahasiswaList`, `getProgramStudiList`, `getSemesterList`). |
| `PdfController.php` | Generator **7 PDF Dompdf** (`generateLaporanNilai`, `generateLaporanRekapNilai`, `generateLaporanNilaiDosen`, `generateLaporanIRS`, `generateLaporanPresensi`, `generateLaporanMahasiswa`, `generateReportKRS`) + util kop surat (logo base64, orientasi otomatis portrait/landscape, footer ttd & tanggal, nama file `tipe_<YYYYmmdd_His>.pdf`). |
| `ProfileController.php` | `getProfile()`, `getFotoUrl()`, `uploadFoto()` (validasi tipe/ukuran → `public/uploads/avatars/avatar_<id>_<timestamp>`), `deleteFoto()`, `updateAccount()`, `changePassword()` (verifikasi password lama). Dipakai semua role lewat `profil.php`. |

---

## 4. File root `public/`

| File | Isi |
|---|---|
| `public/index.php` | **Halaman login** (satu-satunya yang tidak memakai `includes/header.php`). Sudah login → redirect dashboard sesuai role (`match`). Belum → render form login, alert error dari `?error=` (`login_failed`, `server_error`, `session_expired`, `access_denied`). Load `login.css` sendiri + script toggle password inline. |
| `public/login.php` | Handler POST login (tanpa HTML): ambil username/password, query `users`, `password_verify` bcrypt, isi sesi, redirect `redirectToDashboard()`; gagal → `?error=login_failed`; error PDO → `?error=server_error`. |
| `public/logout.php` | Hancurkan session + cookie → redirect `index.php`. |
| `public/profil.php` | Halaman profil **semua role** (via `checkAccess` dengan semua role): data diri, upload/hapus foto, ubah nama/email. Logika: `ProfileController`. CSS: `profil.css` (dimuat otomatis oleh header). |
| `public/profil_update.php` | Handler POST form profil & ganti password → `ProfileController::updateAccount()` / `changePassword()`, lalu flash + redirect kembali ke `profil.php`. |
| `public/test.php` | Halaman debug/dev (jangan di-commit ke produksi). |
| `public/api/check_validasi.php` | Endpoint Ajax: cek apakah sesi presensi MK tertentu masih aktif + beri kode — dipolling oleh `mahasiswa/presensi.php`. |

---

## 5. Halaman `public/admin/`

| File | Isi / controller yang dipanggil |
|---|---|
| `dashboard.php` | Kartu statistik dari `AdminController::getDashboardStats()` + badge IRS/nilai menunggu tindakan. |
| `fakultas.php` | CRUD fakultas (list + form modal) → `MasterDataController`. |
| `jurusan.php` | CRUD jurusan (dropdown fakultas, FK guard saat hapus) → `MasterDataController`. |
| `mahasiswa.php` | CRUD mahasiswa: form 1 tombol buat `users`+`mahasiswa` transaksional, NIM otomatis → `AdminController`. |
| `mahasiswa_get.php` | Ajax: ambil 1 data mahasiswa (ubah form) → `AdminController::getMahasiswaById()`. Pola sama untuk `dosen_get.php`, `matakuliah_get.php`, `jadwal_get.php`. |
| `dosen.php` | CRUD dosen (`users` + `dosen` transaksional, FK fakultas/jurusan) → `AdminController`. |
| `matakuliah.php` | CRUD MK (kode, nama, SKS, semester, jurusan) → `AdminController`. |
| `jadwal.php` | CRUD jadwal + deteksi bentrok ruang/dosen → `AdminController::createJadwal/updateJadwal/cekBentrokJadwal`. |
| `validasi_irs.php` | Tabel IRS `pending`, approve/reject per baris MK, approve all / reject all per mahasiswa, catatan penolakan → `AdminController::approveIRS/rejectIRS/approveAllIRS`. |
| `verifikasi_nilai.php` | Daftar nilai `draft` per MK → verifikasi massal/per baris/reject → `AdminController::verifyGrade/verifyAllGrades/rejectGrade`. |
| `broadcast.php` | Form broadcast (judul, isi, target role) + riwayat + siapa yang belum baca → tabel `announcements` & `announcement_reads`. |
| `laporan.php` | **5 tab laporan** (Nilai MK / Rekap Nilai Mhs / IRS / Presensi / Mahasiswa) dengan form filter GET → `LaporanController::getXxx()`. Tombol PDF → `cetak_pdf.php`. |
| `cetak_pdf.php` | Router PDF: terima `?type=...&filter...` → `PdfController::generateXxx()` → stream PDF inline. |

---

## 6. Halaman `public/dosen/`

| File | Isi / controller yang dipanggil |
|---|---|
| `dashboard.php` | Kartu ringkas `DosenController::getDashboardStats()`, jadwal hari ini, pengumuman masuk. |
| `jadwal.php` | Tabel jadwal mengajar per hari + jadwal hari ini ditonjolkan. |
| `presensi.php` | Pilih MK diampu → buka/tutup sesi (kode 6 char ditampilkan besar utk dibaca mhs) → input presensi massal (hadir/izin/sakit/alpa) → `DosenController::bukaSesiPresensi/dll`. Ada tombol kirim token via email. |
| `nilai.php` | Pilih MK → tabel mahasiswa (IRS approved) → input tugas/UTS/UAS massal → `inputNilaiMassal()` (UPSERT `grades`, status draft bila ada komponen kosong). |
| `tugas.php` | Buat/hapus tugas (judul, deskripsi, deadline, bobot), lihat submission tiap mhs, beri nilai → `createTugas/getSubmissions/nilaiSubmission`. |
| `broadcast.php` | Broadcast ke peserta MK + kirim email → `sendBroadcast/kirimBroadcastViaEmail`; riwayat broadcast. |
| `laporan_nilai.php` | Laporan nilai MK yang diampu (`getLaporanNilaiMahasiswaAmpu`) — hanya MK miliknya. Tombol PDF. |
| `cetak_pdf.php` | Router PDF versi dosen → `PdfController::generateLaporanNilaiDosen()`. |

---

## 7. Halaman `public/mahasiswa/`

| File | Isi / controller yang dipanggil |
|---|---|
| `dashboard.php` | IPS/IPK, jadwal hari ini, pengumuman belum-dibaca, tugas mendekat. |
| `jadwal.php` | Jadwal kuliah dari IRS approved semester aktif. |
| `irs.php` | Pilih pill semester 1–8 (`irs.css` pill/grid): semester aktif bisa "Ambil Paket" (transaksi, maks 24 SKS) & hapus baris `pending`; semester lain "Mode Lihat Saja". Status per baris pending/approved/rejected. |
| `irs_print.php` | Bukti IRS siap-print (CSS print browser, bukan Dompdf) → `getIRSForPrint()`. |
| `presensi.php` | Masukkan kode sesi dosen → polling Ajax ke `api/check_validasi.php` → `konfirmasiPresensi()` (validasi IRS approved + sesi aktif 30 menit + belum absen). Rekap kehadiran miliknya. |
| `khs.php` | KHS per semester (pill semester) + IPS/IPK → `getKHS/hitungIPS/hitungIPK/getDetailIPK`. |
| `report_krs.php` | KRS read-only per semester → `getIRS()`. Tombol PDF → `cetak_pdf.php?type=report_krs`. |
| `report_nilai.php` | Rekap nilai per/all semester + IPS/IPK → `getRekapPresensi`-grade formula. Tombol PDF. |
| `tugas.php` | Daftar tugas MK yang diikuti, status deadline, submit (catatan/file) → `submitTugas`. |
| `cetak_pdf.php` | Router PDF mahasiswa (`report_krs`, `report_nilai`) → `PdfController::generateReportKRS/generateLaporanRekapNilai`. |
| `debug.php` | Halaman debug/dev (jangan di-commit ke produksi). |

---

## 8. Folder `includes/` — kerangka tampilan

| File | Isi |
|---|---|
| `includes/header.php` | Di-include semua halaman (kecuali login). Isinya: guard login, deteksi halaman (`$current_page`), sidebar navigasi per role (menu & submenu Admin/Dosen/Mahasiswa), alert flash message, badge "belum dibaca", **dan pemetaan CSS per halaman** (`$page_css_map` — lihat §10). App shell: `<div class="app-container">` + sidebar + konten. |
| `includes/footer.php` | Footer copyright, penutup app shell, load **`app.js`**, dan slot untuk script tambahan per halaman. |
| `includes/SmtpMailer.php` | Klien SMTP mandiri (tanpa librari eksternal) untuk kirim email — dipakai `helper/EmailHelper.php`. |

`helper/`:

| File | Isi |
|---|---|
| `helper/EmailHelper.php` | Pembungkus SmtpMailer: baca konfigurasi `.env` (`SIMAK_EMAIL_*`), kirim email broadcast / token presensi / reset password, mode log ke folder `logs/email/` bila belum dikonfigurasi. |

`template/email/` — template HTML email (broadcast, reset password) dengan placeholder data.

`libraries/dompdf/` — bundel Dompdf; hanya di-require oleh `PdfController`.

---

## 9. Folder `public/assets/`

| Path | Isi |
|---|---|
| `assets/css/` | 8 file CSS desain (detail §10) |
| `assets/js/app.js` | Satu-satunya file JS aplikasi (detail §11) |
| `assets/fontawesome/` | Bundel Font Awesome lokal (ikon semua halaman) |
| `assets/img/logo_poltek.jpg` | Logo institusi — dipakai sidebar & **kop semua PDF** |
| `assets/favicon.ico` | Ikon tab browser |
| `uploads/avatars/` | Foto profil `avatar_<user_id>_<timestamp>.jpg` (implisit: `.gitignore`, jangan commit isi avatar) |

Bootstrap 5.3.3 sendiri dimuat dari CDN (`cdn.jsdelivr.net`) di setiap halaman.

---

## 10. Letak Desain CSS per Fitur (peta CSS)

Semua halaman (kecuali login) memakai pola: **`base.css` selalu dimuat + 1 file CSS khusus halaman**. Pemilihannya otomatis oleh `includes/header.php`:

```php
// includes/header.php
$page_css_map = [
    'dashboard.php'       => ['dashboard.css'],
    'profil.php'          => ['profil.css'],
    'jadwal.php'          => ['jadwal.css'],
    'irs.php'             => ['irs.css'],
    'validasi_irs.php'    => ['irs.css'],
    'verifikasi_nilai.php'=> ['irs.css'],
    'laporan.php'         => ['laporan.css'],
    'report_krs.php'      => ['report.css'],
    'report_nilai.php'    => ['report.css'],
    'khs.php'             => ['report.css'],
    'laporan_nilai.php'   => ['report.css'],
];
$page_extra_css = $page_css_map[$current_page] ?? [];
```

Keuntungan: style satu halaman tidak menabrak halaman lain. **Catatan** — file name (`dashboard.php`, `jadwal.php`, dll.) sama di semua role, jadi admin, dosen, dan mahasiswa memakai file CSS yang sama untuk halaman dengan nama sama.

### 10.1 File CSS per fitur

| File CSS | Fitur / halaman yang memakai | Keterangan |
|---|---|---|
| `public/assets/css/base.css` | **Semua halaman** (selalu dimuat); juga jadi satu-satunya CSS untuk halaman tanpa entri map: master data admin (`fakultas`, `jurusan`, `mahasiswa`, `dosen`, `matakuliah`, `jadwal`), tugas, presensi, broadcast. Berisi layout aplikasi keseluruhan: **sidebar + toggle + overlay, topbar, tombol, kartu, tabel, form, badge status, modal, flash alert, scrollbar**. | Tempat edit tema global. |
| `public/assets/css/dashboard.css` | `*/dashboard.php` (admin, dosen, mahasiswa) — kartu statistik, grid, chart mini. | |
| `public/assets/css/login.css` | `public/index.php` (halaman login) — **dimuat langsung oleh index.php**, bukan lewat header (login tak punya sidebar). Desain box login, gradient, logo, chip role, toggle mata. | |
| `public/assets/css/irs.css` | `mahasiswa/irs.php` (pilih semester pill, kartu MK, progress SKS), **dan** `admin/validasi_irs.php` + `admin/verifikasi_nilai.php` (tabel persetujuan pakai komponen IRS). | |
| `public/assets/css/jadwal.css` | `*/jadwal.php` (tabel jadwal per hari, kartu jam). | |
| `public/assets/css/laporan.css` | `admin/laporan.php` (5 tab laporan, filter bar). | |
| `public/assets/css/report.css` | Semua halaman laporan mahasiswa & dosen: `report_krs.php`, `report_nilai.php`, `khs.php`, `laporan_nilai.php` — tampilan dokumen read-only (dokumen gaya kertas). | |
| `public/assets/css/profil.css` | `public/profil.php` (kartu profil, avatar, form keamanan). | |

### 10.2 Cara mengubah desain

1. **Tema global (sidebar, tombol, tabel, modal)** → edit `base.css`.
2. **Desain satu fitur saja** → edit file CSS fitur di tabel §10.1; kalau halamannya belum ada di map, tambahkan entri baru ke `$page_css_map` di `includes/header.php`.
3. **Warna/status badge** → kelas `.status-badge` (ada pemetaan warna di `app.js` juga, §11).
4. Versi file CSS di-cache — naikkan `$css_v` (di `includes/header.php`, juga di `index.php` untuk `login.css`) setelah mengedit CSS agar browser memuat versi baru.

---

## 11. `public/assets/js/app.js` (JS tunggal)

Dimuat oleh `includes/footer.php` di semua halaman (login tidak memuatnya — ia punya skrip inline sendiri untuk toggle password). Fitur yang ditangani:

| Fitur | Cara kerja |
|---|---|
| Auto-dismiss alert | `.alert` fade & hilang otomatis |
| Konfirmasi hapus | `.btn-delete`, `.confirm-action` → confirm dialog sebelum submit |
| Sidebar mobile | Toggle `#sidebarToggle` + overlay, submenu toggle `.submenu-toggle` (rekursif buka-tutup) |
| Pencarian tabel realtime | Input pencarian → filter baris tabel berdasarkan kata kunci |
| Badge status warna | `.status-badge`: pending=warning, approved=success, rejected=danger, dll. |
| Modal tutup manual | `.modal`: tombol close, klik overlay, tombol **ESC** |
| Countdown deadline | `.deadline-countdown` → hitung sisa waktu & beri kelas "terlambat" |
| Tooltip | `[data-tooltip]` |
| Cetak halaman | `.btn-print` → `window.print()` |
| Auto-submit filter | `.auto-submit` (`select`/`input` berubah → form filter langsung submit; dipakai bar filter laporan) |
| Toggle password | `.toggle-password` (ikon mata di form profil) |
| Select all checkbox | `.select-all` → cek semua checkbox sebaris tabel |
| Navigasi URL | Semua link memang hard-coded `/simak_app/public/...` (bukan relative) |

---

## 12. Folder `logs/` & file lain

| Path | Isi |
|---|---|
| `logs/email/` | File log kirim email (karena `SIMAK_EMAIL_LOG=true` / mode debug) |
| `logs/pdf/` | Log/error render Dompdf |
| `logs/css_audit.php` | Skrip dev untuk audit pemakaian kelas CSS (jangan di-commit) |
| `.env` | Nilai rahasia nyata; **`.gitignore`** — jangan pernah di-commit |
| `.env.example` | Template yang boleh di-commit |
| `SECURITY_AUDIT.md` | Catatan audit keamanan (review manual) |
| `FILES_NOT_TO_COMMIT.md` | Daftar file yang tidak boleh masuk git |
| `test_email.php` | Script tes SMTP dari CLI (jangan terekspos di produksi) |

---

## 13. Cheat Sheet: "Saya mau ubah X, edit di mana?"

| Saya mau ubah | Edit file |
|---|---|
| Desain/tampilan halaman login | `public/index.php` (markup) + `public/assets/css/login.css` |
| Sidebar & menu per role | `includes/header.php` |
| Formula nilai/IPK/presensi | `MahasiswaController.php` (hitungIPS/IPK, konfirmasiPresensi) |
| Ketentuan IRS (maks SKS, semester) | `MahasiswaController::createIRSFromPaket()` / `validateAksesSemester()` |
| Kode presensi & window sesi | `DosenController::bukaSesiPresensi()` + `MahasiswaController::konfirmasiPresensi()` |
| Logika laporan (query) | `LaporanController.php` |
| Tampilan & isi PDF (kop, orientasi, footer) | `PdfController.php` |
| Menu map CSS per halaman | `$page_css_map` di `includes/header.php` |
| Perilaku interaktif (modal, filter, dsb.) | `public/assets/js/app.js` |
| Kredensial DB/email | file `.env` (bukan kode) |
| URL base aplikasi | Prefix hard-coded `/simak_app/public/...` di semua halaman — ubah semua kalau folder proyek diganti |

---

*Dibuat dari kode aktual: daftar halaman diverifikasi dari struktur `public/<role>/`, peta CSS dibaca langsung dari `$page_css_map` di `includes/header.php`, dan ringkasan `app.js` dari kode sumber. November 2026.*
