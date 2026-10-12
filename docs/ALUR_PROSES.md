# Cara Kerja / Alur Proses Aplikasi SIMAK

Dokumen ini menjelaskan bagaimana aplikasi memproses setiap fitur, dari request sampai response.

---

## 1. Pola Arsitektur

SIMAK memakai pola **Front Controller terdistribusi** (setiap halaman adalah entry point sendiri) dengan pemisahan tugas:

```
Browser
   │  HTTP request
   ▼
public/<role>/<halaman>.php        ← view + controller tipis: cek akses, panggil controller
   │  include
   ├─ config/session.php           ← session, checkAccess() role guard, flash message, CSRF
   ├─ config/database.php          ← PDO koneksi (baca .env)
   ├─ controllers/*.php            ← LOGIKA BISNIS (SQL, transaksi, validasi)
   ▼
controllers/XxxController.php      ← mengembalikan array / mengubah DB
   ▼
public/<role>/<halaman>.php        ← render HTML (header.php + konten + footer.php)
```

**Aturan penting:**

- Setiap halaman diojeg begini di baris atas: `checkAccess(['<role>'])` — jika role tidak cocok, halaman melempar 403 dengan halaman error bawaan; jika belum login, redirect ke `index.php`.
- Konvensi URL: semua hard-coded, memakai prefix `/simak_app/public/...` (bukan relative).
- Pesan notifikasi antar halaman lewat **flash message** di `$_SESSION` (`redirectWithMessage()`), ditampilkan oleh `includes/header.php`.
- Form POST dengan aksi destruktif wajib membawa **CSRF token** (`generateCsrfToken()` / `validateCsrfToken()` di `config/session.php`).

---

## 2. Alur Proses Login / Logout

```
GET public/index.php
  ├─ sudah login? → match($role) redirect ke dashboard admin/dosen/mahasiswa
  └─ belum? → tampilkan form login (index.php + login.php)
POST public/login.php
  ├─ Ambil user by username
  ├─ password_verify($password, $user['password']) → bcrypt
  ├─ Sukses → isi $_SESSION: user_id, role, nama → redirect dashboard sesuai role
  └─ Gagal → redirect kembali dengan ?error=login_failed (flash alert)
Logout → hapus session → redirect index.php
```

---

## 3. Alur Fitur Mahasiswa

### 3.1 IRS / KRS (isian rencana studi)

Halaman: `public/mahasiswa/irs.php` · Controller: `MahasiswaController`

```
Mahasiswa masuk halaman IRS
  ├─ Pilih pill semester (1-8). Hanya semester AKTIF mahasiswa (kolom mahasiswa.semester)
  │   yang bisa diedit — sisanya read-only ("Mode Lihat Saja") via validateAksesSemester().
  ├─ POST action=add_paket  → createIRSFromPaket():
  │     1. validateAksesSemester() - pastikan semester = semester aktif
  │     2. getPaketCourses()       - MK yang belum diambil di semester tsb
  │     3. Validasi total SKS ≤ 24
  │     4. BEGIN TRANSACTION
  │     5. INSERT INTO irs ... one-by-one (dengan NOT EXISTS guard)
  │     6. COMMIT → redirect sukses (flash)
  ├─ POST action=delete     → deleteIRS(): hanya baris status='pending' & semester aktif
  └─ Setiap baris IRS punya status enum: pending | approved | rejected
```

Status alur IRS (`irs.status`):

```
pending ──admin approve──► approved   (terkunci, tidak bisa dihapus)
   └──── admin reject ────► rejected  (mhs boleh susun ulang)
```

**Report KRS** (`public/mahasiswa/report_krs.php`): tampilan read-only KRS per semester
+ tombol Cetak PDF (`cetak_pdf.php?type=report_krs`) → `PdfController::generateReportKRS()`.

### 3.2 Konfirmasi Presensi

Halaman: `public/mahasiswa/presensi.php`

```
1. Dosen membuka sesi presensi di kelasnya → baris presensi_sessions
   (kode unik, is_active=1, tanggal=hari ini)
2. Mahasiswa mengetik kode → POST konfirmasiPresensi(course_id, kode)
3. Controller cek:
   a. Mhs terdaftar MK itu?        (irs.status = 'approved')
   b. Sesi aktif & kode cocok?     (presensi_sessions, window 30 menit)
   c. Belum absen di MK tsb hari ini?
4. Semua ok → INSERT presensi (status='hadir')
```

### 3.3 KHS, IPK & Report Nilai

Data bertumpuk di atas `grades` (nilai) + `presensi` (absensi).

```
Rumus Nilai Final per MK  (sama dipakai KHS, IPK, dan semua laporan):
    kehadiran % = (hadir + izin + sakit) / total_pertemuan × 100   (alpa tidak dihitung)
    nilai_final = 75% nilai_akhir  +  25% kehadiran_persen
    (tanpa data presensi → pakai nilai_akhir apa adanya, netral)

Konversi bobot:   ≥85 → A (4.0) | ≥75 → B (3.0) | ≥65 → C (2.0) | ≥50 → D (1.0) | <50 → E (0.0)

IPK  = Σ (bobot × sks) / Σ sks      — semua semester
IPS  = idem, per 1 semester
```

---

## 4. Alur Fitur Dosen

### 4.1 Sesi Presensi

`public/dosen/presensi.php` → `DosenController::bukaPresensi()`

```
1. Dosen pilih MK yang diampu → generate kode 6 char random
2. INSERT presensi_sessions (is_active=1)
3. Kode ditampilkan utk dibaca mahasiswa (lihat 3.2)
4. Sesi bisa ditutup (is_active=0) — timeout otomatis 30 menit via query waktu_mulai
```

### 4.2 Input Nilai

`public/dosen/nilai.php`

```
1. Pilih MK yang diampu → daftar mahasiswa IRS approved di MK itu
2. Input tugas / UTS / UAS per mhs
3. POST → DosenController::simpanNilai()
   Untuk tiap mhs: UPSERT ke grades (nilai_tugas, uts, uas, nilai_akhir,
   status_verifikasi)
4. status_verifikasi:
   - Mhs punya nilai 0 salah satu komponen → 'draft' → harus diverifikasi Admin
     via public/admin/verifikasi_nilai.php
   - Lainnya → 'verified' langsung
5. Hanya nilai 'verified' yang muncul di KHS/laporan mahasiswa
```

### 4.3 Tugas

`public/dosen/tugas.php`

```
Dosen membuat tasks (judul, deskripsi, deadline, bobot)
   → tampil ke mhs MK tsb (IRS approved)
Mhs submit → task_submissions (status = draft | submitted | late, nilai dari dosen)
```

---

## 5. Alur Fitur Admin

### 5.1 Master Data

Semua CRUD (fakultas, jurusan, mahasiswa, dosen, MK, jadwal) ada di
`public/admin/*.php` + `controllers/AdminController.php` & `MasterDataController.php`:

- **Mahasiswa**: 1 form → buat `users` (password_hash) + `mahasiswa` dalam satu transaksi; auto NIM.
- **Dosen**: idem (`users` + `dosen` + FK fakultas/jurusan).
- **Mata Kuliah / Jadwal / Jurusan / Fakultas**: CRUD biasa dengan FK guard
  (hapus yang masih dirujuk akan ditolak oleh foreign key).

### 5.2 Validasi IRS & Verifikasi Nilai

```
validasi_irs.php  → admin lihat IRS pending seluruh kampus
                    → approve ALL / reject ALL / per-MK
verifikasi_nilai.php → admin lihat grades status='draft'
                    → verifikasi → status → 'verified' → muncul di KHS mhs
```

### 5.3 Broadcast

`public/admin/broadcast.php` & `public/dosen/broadcast.php` → tabel `announcements`
(sender admin → target all/admin/dosen/mahasiswa). `announcement_reads` mencatat
siapa yang sudah baca → badge "belum dibaca" di dashboard.

---

## 6. Alur Laporan & PDF

Halaman admin: `public/admin/laporan.php` (5 tab: Nilai / Nilai Mhs / IRS / Presensi / Mahasiswa)
Halaman mhs: `public/mahasiswa/report_krs.php`, `report_nilai.php`

```
Filter (form GET) → LaporanController::getXxx() → tabel HTML
                                         ↘ tombol "PDF" → public/<role>/cetak_pdf.php
                                              → PdfController::generateXxx()
                                              → HTML template + getHeader() + getFooter()
                                              → Dompdf render → stream ke browser (inline)
```

**Konvensi PDF** (`PdfController`):

| Hal | Kebijakan |
|---|---|
| Kop surat | Logo perusahaan `public/assets/img/logo_poltek.jpg` (di-embed base64), nama institusi, judul laporan, tanggal cetak |
| Orientasi | Tabel lebar (11–12 kolom) → **A4 landscape**; tabel ringkas (≤8 kolom) → portrait |
| Footer | Tanda tangan Admin Akademik + tanggal + watermark "dihasilkan otomatis oleh SIMAK" |
| Data | Semua HTML di-escape `htmlspecialchars` untuk kolom user-input |
| Nama file | `<tipe>_<YYYYmmdd_His>.pdf` |

---

## 7. Katalog Endpoint Halaman

| Path | Role | Fungsi |
|---|---|---|
| `public/index.php` | semua | Landing / redirect dashboard |
| `public/login.php` `logout.php` | - | Sesi |
| `public/admin/dashboard.php` | admin | Statistik ringkas |
| `public/admin/{fakultas,jurusan,mahasiswa,dosen,matakuliah,jadwal}.php` | admin | CRUD master |
| `public/admin/validasi_irs.php` | admin | Approve/reject IRS |
| `public/admin/verifikasi_nilai.php` | admin | Verifikasi draft → verified |
| `public/admin/laporan.php` `cetak_pdf.php` | admin | 5 tab laporan + PDF |
| `public/admin/broadcast.php` | admin | Broadcast semua/tujuan role |
| `public/dosen/{dashboard,jadwal,presensi,nilai,tugas,broadcast,laporan_nilai}.php` | dosen | Workflow dosen |
| `public/mahasiswa/{dashboard,jadwal,tugas,irs,khs,report_krs,report_nilai,presensi}.php` | mahasiswa | Workflow mahasiswa |
| `public/mahasiswa/cetak_pdf.php` | mahasiswa | PDF report_nilai, report_krs |
| `public/mahasiswa/irs_print.php` | mahasiswa | Bukti IRS print (bukan Dompdf, print CSS) |
| `public/api/check_validasi.php` | ajax | Cek status sesi presensi |

---

## 8. Keamanan yang Dipakai

| Mekanisme | Implementasi | Lokasi |
|---|---|---|
| Password hash | `password_hash(PASSWORD_DEFAULT)` / `password_verify` | AuthController, AdminController |
| Role guard | `checkAccess([...])` | config/session.php, semua halaman |
| SQL injection | PDO + prepared statements di semua query | controllers |
| XSS | `htmlspecialchars` untuk output user | semua view |
| CSRF | token per session, `validateCsrfToken()` | config/session.php |
| Validate anti-duplikat IRS | `NOT EXISTS (mahasiswa, course, semester)` | MahasiswaController |
| Transaksi multi-insert | `beginTransaction()` / `commit()` / `rollBack()` | AdminController, MahasiswaController |
