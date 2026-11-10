# Dokumentasi Aplikasi SIMAK

**SIMAK** (Sistem Informasi & Manajemen Akademik Kampus) — aplikasi web manajemen akademik untuk Politeknik Mitra Industri.

> Dokumen pendamping: `docs/ALUR_PROSES.md` (alur teknis tiap fitur) dan `docs/SETUP.md` (instalasi & konfigurasi).

---

## 1. Ringkasan Aplikasi

| Aspek | Keterangan |
|---|---|
| Fungsi utama | Manajemen data akademik, IRS/KRS, presensi, nilai & KHS/IPK, tugas, broadcast, dan laporan (HTML + PDF) |
| Bahasa / stack | PHP 8+ (native, tanpa framework), MySQL/MariaDB, PDO, Bootstrap 5, Font Awesome, Dompdf |
| Pola arsitektur | Front controller terdistribusi — setiap halaman di `public/<role>/` adalah entry point sendiri, logika bisnis di `controllers/` |
| Server dev | Laragon (ini menempatkan proyek di root `simak_app`) — bisa juga XAMPP |
| Login | `public/index.php` (form) → `public/login.php` (proses) → redirect dashboard sesuai role |
| Skala proyek | 3 role pengguna, belasan halaman fitur, 15 tabel database, 7 jenis laporan PDF |

---

## 2. Hak Akses (Role)

Aplikasi punya **3 role / tingkat hak akses**, disimpan di kolom `users.role` dan dipaksa di setiap halaman lewat `checkAccess([...])` (di `config/session.php`). Role yang tidak cocok langsung dapat **HTTP 403** dengan halaman "Akses Ditolak"; belum login diarahkan ke halaman login.

| # | Role | Username default (dev) | Deskripsi pengguna |
|---|---|---|---|
| 1 | `admin` | `admin` / `admin123` | Admin Akademik — pemegang kendali penuh sistem: master data, validasi IRS, verifikasi nilai, semua laporan + PDF, broadcast ke semua role |
| 2 | `dosen` | dibuat oleh admin (menu Master Data → Dosen) | Dosen pengampu — jadwal mengajar, sesi presensi, input nilai, tugas, laporan nilai MK yang diampu, broadcast per kelas |
| 3 | `mahasiswa` | dibuat oleh admin (menu Master Data → Mahasiswa) | Mahasiswa — dashboard, jadwal kuliah, IRS (ambil paket MK), KHS & IPK, laporan KRS & nilai, konfirmasi presensi, tugas |

Landing page setelah login per role:

- admin → `public/admin/dashboard.php`
- dosen → `public/dosen/dashboard.php`
- mahasiswa → `public/mahasiswa/dashboard.php`

---

## 3. Fitur per Hak Akses

### 3.1 Admin (fitur paling banyak)

Semua halaman ada di `public/admin/`, logika di `AdminController.php`, `MasterDataController.php`, `LaporanController.php`, `PdfController.php`.

| # | Fitur | Halaman | Detail |
|---|---|---|---|
| 1 | Dashboard statistik | `dashboard.php` | Ringkasan jumlah mahasiswa, dosen, MK, IRS pending, nilai draft |
| 2 | Master Data Fakultas | `fakultas.php` | CRUD fakultas |
| 3 | Master Data Jurusan | `jurusan.php` | CRUD jurusan (relasi ke fakultas; dijaga FK guard) |
| 4 | Master Data Mahasiswa | `mahasiswa.php`, `mahasiswa_get.php` | Satu form membuat `users` + `mahasiswa` dalam satu transaksi, password di-hash, NIM otomatis |
| 5 | Master Data Dosen | `dosen.php`, `dosen_get.php` | 1 form membuat `users` + `dosen` + FK fakultas/jurusan dalam satu transaksi |
| 6 | Master Data Mata Kuliah | `matakuliah.php`, `matakuliah_get.php` | CRUD MK (kode, nama, SKS, semester, FK jurusan) |
| 7 | Master Data Jadwal | `jadwal.php`, `jadwal_get.php` | CRUD jadwal per MK dengan **deteksi bentrok** ruang/dosen (`cekBentrokJadwal()`) |
| 8 | Validasi IRS | `validasi_irs.php` | Approve / reject IRS pending seluruh kampus — per baris per-MK, atau approve all / reject all per mahasiswa; reject bisa disertai catatan |
| 9 | Verifikasi Nilai | `verifikasi_nilai.php` | Nilai berstatus `draft` (ada komponen kosong/0) dari dosen diverifikasi jadi `verified` baru tampil di KHS mahasiswa; bisa verifikasi massal per MK atau reject |
| 10 | Broadcast | `broadcast.php` | Kirim pengumuman ke target semua / admin / dosen / mahasiswa; pencatatan baca via `announcement_reads` |
| 11 | Laporan (5 tab) | `laporan.php` | Nilai per MK, Rekap Nilai Mahasiswa, IRS, Presensi, Mahasiswa — filter + tabel HTML |
| 12 | Cetak PDF semua laporan | `cetak_pdf.php` | Dompdf, inline stream, lihat bagian §4 |
| (bersama) | Profil | `public/profil.php` | Lihat/ubah profil, ganti password, upload & hapus foto profil (juga dipakai dosen & mahasiswa) |

Statistik validasi: `getIRSStats()` dan `getNilaiStats()` memberi badge jumlah pending/draft yang perlu ditindak.

### 3.2 Dosen

Halaman di `public/dosen/`, logika di `DosenController.php`.

| # | Fitur | Halaman | Detail |
|---|---|---|---|
| 1 | Dashboard | `dashboard.php` | Statistik MK diampu, jumlah mhs, jadwal hari ini |
| 2 | Jadwal mengajar | `jadwal.php` | Daftar kelas yang diampu + jadwal hari ini |
| 3 | Sesi Presensi | `presensi.php` | Buka sesi (kode 6 karakter, aktif 30 menit), tutup manual, input presensi massal (hadir/izin/sakit/alpa), kirim token presensi via email |
| 4 | Input Nilai | `nilai.php` | Input tugas/UTS/UAS massal per MK → UPSERT `grades`; bila ada komponen kosong status jadi `draft` (menunggu verifikasi admin), jika lengkap langsung `verified` |
| 5 | Tugas | `tugas.php` | Buat/hapus tugas (judul, deskripsi, deadline), lihat submission mahasiswa, beri nilai per submission |
| 6 | Broadcast | `broadcast.php` | Pengumuman ke kelas MK tertentu + kirim via email; riwayat broadcast; badge pengumuman masuk |
| 7 | Laporan Nilai | `laporan_nilai.php` + `cetak_pdf.php` | Nilai hanya untuk MK yang diampu (data tak bisa diakses lintas dosen) + versi PDF |

### 3.3 Mahasiswa

Halaman di `public/mahasiswa/`, logika di `MahasiswaController.php`.

| # | Fitur | Halaman | Detail |
|---|---|---|---|
| 1 | Dashboard | `dashboard.php` | Statistik IPS/IPK, jadwal hari ini, pengumuman belum dibaca, tugas mendekat |
| 2 | Jadwal kuliah | `jadwal.php` | Jadwal dari IRS approved semester aktif |
| 3 | IRS / KRS | `irs.php` | Pilih pill semester 1–8; hanya semester aktif `mahasiswa.semester` bisa diubah, sisanya read-only. "Ambil Paket" memasukkan semua MK belum diambil, transaksi, menjaga total SKS ≤ 24, anti-duplikat `NOT EXISTS`; hapus hanya baris `pending` |
| 4 | Bukti IRS print | `irs_print.php` | Tampilan print CSS (bukan Dompdf) |
| 5 | Konfirmasi Presensi | `presensi.php` | Ketik kode sesi dosen → validasi: IRS `approved`, sesi aktif & kode cocok (window 30 menit), belum absen hari itu → insert `hadir`. Ada polling ajax `api/check_validasi.php` untuk status sesi |
| 6 | KHS & IPK | `khs.php` | KHS per semester + IPS/IPK dihitung dari nilai A–E + bobot presensi |
| 7 | Report KRS | `report_krs.php` + `cetak_pdf.php` | Read-only KRS per semester + PDF |
| 8 | Report Nilai | `report_nilai.php` + `cetak_pdf.php` | Rekap nilai semua/tiap semester + PDF |
| 9 | Tugas | `tugas.php` | Lihat tugas MK yang diikuti, submit tugas (catatan/file), status draft/submitted/late |
| (bersama) | Profil | `public/profil.php` | Sama dengan admin/dosen |

---

## 4. Laporan (Report)

### 4.1 Pola kerja laporan

Semua laporan satu-tipe: **filter (GET) → `LaporanController::getXxx()` → tabel HTML → tombol PDF → `cetak_pdf.php` → `PdfController::generateXxx()` → Dompdf → stream inline ke browser.**

Nama file PDF: `<tipe>_<YYYYmmdd_His>.pdf`.

### 4.2 Katalog laporan PDF (7 jenis, `PdfController`)

| # | Jenis PDF | Method | Halaman pemanggil | Filter | Isi |
|---|---|---|---|---|---|
| 1 | Laporan Nilai per MK | `generateLaporanNilai()` | admin `laporan.php` | course_id, semester | Daftar mahasiswa & nilai satu MK, rata-rata & distribusi grade |
| 2 | Rekap Nilai Mahasiswa | `generateLaporanRekapNilai()` | admin `laporan.php`, mahasiswa | mahasiswa_id, semester | Nilai seluruh/tiap semester 1 mhs + IPS/IPK |
| 3 | Nilai Dosen | `generateLaporanNilaiDosen()` | dosen `laporan_nilai.php` | dosen_id, course_id, semester | Nilai MK yang diampu dosen |
| 4 | Laporan IRS | `generateLaporanIRS()` | admin `laporan.php` | semester, status | Rekap IRS per mahasiswa + status pending/approved/rejected |
| 5 | Laporan Presensi | `generateLaporanPresensi()` | admin `laporan.php` | course_id | Rekap absensi per MK per mahasiswa (hadir/izin/sakit/alpa) |
| 6 | Laporan Mahasiswa | `generateLaporanMahasiswa()` | admin `laporan.php` | jurusan_id | Data mahasiswa per jurusan |
| 7 | Report KRS Mahasiswa | `generateReportKRS()` | mahasiswa `report_krs.php` | mahasiswa_id, semester | KRS read-only bukti isi IRS per semester |

Selain Dompdf: `mahasiswa/irs_print.php` menyajikan bukti IRS lewat print CSS browser.

### 4.3 Konvensi PDF

| Aspek | Kebijakan |
|---|---|
| Kop surat | Logo `public/assets/img/logo_poltek.jpg` (embed base64), nama institusi, judul laporan, tanggal cetak |
| Orientasi | Tabel lebar (11–12 kolom) → A4 landscape; tabel ringkas (≤ 8 kolom) → portrait |
| Footer | Tanda tangan Admin Akademik + tanggal + watermark "dihasilkan otomatis oleh SIMAK" |
| Keamanan output | Semua kolom user-input di-escape `htmlspecialchars` |
| Logo hilang | Otomatis pakai placeholder, PDF tetap terbit |

---

## 5. Database (15 tabel)

| Tabel | Fungsi |
|---|---|
| `users` | Akun login semua role (username, password bcrypt, nama, email, role, foto profil) |
| `fakultas` | Master fakultas |
| `jurusan` | Master jurusan (FK fakultas) |
| `mahasiswa` | Data mahasiswa (NIM, FK users, jurusan, semester aktif) |
| `dosen` | Data dosen (NIDN, FK users, fakultas, jurusan) |
| `courses` | Mata kuliah (kode, nama, SKS, semester, FK jurusan) |
| `jadwal` | Jadwal kuliah per MK (hari, jam, ruang, FK dosen & course; deteksi bentrok) |
| `irs` | Baris IRS mahasiswa (FK mahasiswa & course, semester, status `pending`/`approved`/`rejected`, catatan admin) |
| `grades` | Nilai per mhs & MK (tugas, UTS, UAS, nilai_akhir, status_verifikasi `draft`/`verified`, catatan) |
| `presensi` | Absensi mahasiswa (FK mhs & course, tanggal, status `hadir`/`izin`/`sakit`/`alpa`) |
| `presensi_sessions` | Sesi presensi per MK (kode 6 char, is_active, token, window 30 menit) |
| `tasks` | Tugas dosen (judul, deskripsi, deadline, bobot, FK course) |
| `task_submissions` | Jawaban mhs per tugas (status `draft`/`submitted`/`late`, nilai dosen, catatan, file) |
| `announcements` | Broadcast (judul, isi, target, FK pengirim) |
| `announcement_reads` | Siapa saja yang sudah membaca pengumuman (basis badge "belum dibaca") |

---

## 6. Logika Nilai & IPK (formula inti)

```
kehadiran %     = (hadir + izin + sakit) / total_pertemuan × 100   (alpa tidak dihitung)
nilai_final     = 75% nilai_akhir  +  25% kehadiran_persen
tanpa data presensi → nilai_final = nilai_akhir apa adanya
```

| Nilai final | Grade | Bobot |
|---|---|---|
| ≥ 85 | A | 4.0 |
| ≥ 75 | B | 3.0 |
| ≥ 65 | C | 2.0 |
| ≥ 50 | D | 1.0 |
| < 50 | E | 0.0 |

```
IPS = Σ(bobot × sks) / Σsks    — per 1 semester
IPK = Σ(bobot × sks) / Σsks    — seluruh semester
```

Formula yang sama dipakai di KHS mahasiswa, laporan admin, laporan dosen, dan PDF — tidak ada duplikasi logika.

---

## 7. Alur Kunci Antar-Role

1. **IRS (KRS)** — mhs isi IRS dari paket (semester aktif, maks 24 SKS) → status `pending` → admin approve (`approved`, terkunci) atau reject (`rejected`, mhs boleh susun ulang) → jadwal & KHS mengikuti hasil validasi.
2. **Presensi** — dosen buka sesi (kode 6 char, 30 menit) → mhs konfirmasi kode → hadir tercatat → kehadiran masuk dihitung ke nilai final.
3. **Nilai** — dosen input tugas/UTS/UAS → komponen tak lengkap → `draft` → admin verifikasi di `verifikasi_nilai.php` → `verified` baru muncul di KHS/laporan mhs.
4. **Broadcast** — admin (target semua role) / dosen (per MK) kirim → target lihat badge belum-dibaca → `announcement_reads` mencatat siapa yang membaca.
5. **Tugas** — dosen membuat task → mhs submit → dosen memberi nilai → nilai masuk ke komponen tugas.

---

## 8. Keamanan

| Mekanisme | Implementasi | Lokasi |
|---|---|---|
| Password hash | `password_hash(PASSWORD_DEFAULT)` / `password_verify` | AuthController, AdminController |
| Role guard | `checkAccess([role])` di setiap halaman; 403 bila role salah | config/session.php |
| SQL injection | PDO + prepared statement di semua query | controllers |
| XSS | `htmlspecialchars` untuk semua output user | views |
| CSRF | Token per session, `validateCsrfToken()` untuk form aksi destruktif | config/session.php |
| Anti-duplikat IRS | `NOT EXISTS (mahasiswa, course, semester)` | MahasiswaController |
| Transaksi multi-insert | `beginTransaction/commit/rollBack` pada pembuatan user + data role, isi paket IRS | AdminController, MahasiswaController |
| Upload foto profil | Validasi tipe & ukuran, nama file `avatar_<id>_<timestamp>` di `public/uploads/avatars/` | ProfileController |
| Konfigurasi rahasia | Kredensial (DB/email) dari `.env`, tidak di-commit | config/env.php |

---

## 9. Peta File Proyek

```
simak_app/
├── config/
│   ├── env.php          # Loader .env
│   ├── database.php     # PDO koneksi dari env
│   └── session.php      # Session, checkAccess, flash, CSRF, redirect dashboard
├── controllers/
│   ├── AuthController.php        # Login & logout
│   ├── AdminController.php       # CRUD master, validasi IRS, verifikasi nilai, statistik
│   ├── MasterDataController.php  # CRUD fakultas, jurusan, MK, jadwal
│   ├── DosenController.php       # Jadwal, presensi, nilai, tugas, broadcast (dosen)
│   ├── MahasiswaController.php   # IRS, KHS/IPK, presensi, tugas (mhs)
│   ├── LaporanController.php     # Query semua laporan (HTML)
│   ├── PdfController.php         # Generate 7 jenis PDF (Dompdf)
│   └── ProfileController.php     # Profil, foto, akun, ganti password
├── public/
│   ├── index.php / login.php / logout.php / profil.php / profil_update.php
│   ├── admin/     # 14 halaman admin (master, validasi, laporan, broadcast)
│   ├── dosen/     # 8 halaman dosen
│   ├── mahasiswa/ # 11 halaman mahasiswa
│   ├── api/check_validasi.php  # Ajax status sesi presensi
│   ├── assets/    # CSS per halaman, js, img, favicon
│   └── uploads/avatars/
├── docs/          # ALUR_PROSES.md, SETUP.md, DOKUMENTASI_SIMAK.md (file ini)
└── libraries/     # Bundel Dompdf
```

---

*Dokumen ini dibuat dari kode aktual: role & fitur diverifikasi dari halaman `public/<role>/`, fungsi di `controllers/`, tabel DB (`SHOW TABLES`), dan metode `PdfController`. November 2026.*
