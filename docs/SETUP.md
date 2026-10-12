# Setup Aplikasi SIMAK

Panduan instalasi lengkap sistem SIMAK (Sistem Informasi & Manajemen Akademik Kampus) — Politeknik Mitra Industri.

---

## 1. Kebutuhan Sistem

| Komponen | Minimum | Keterangan |
|---|---|---|
| PHP | 8.1+ | Dikembangkan & diuji dengan PHP 8.4 (Laragon). Butuh ekstensi `pdo_mysql`. |
| MySQL / MariaDB | 5.7+ / 10.4+ | Database `simak_db` |
| Web server | Apache / Nginx | XAMPP atau Laragon |
| Dompdf | ✓ | Sudah bundel di `libraries/dompdf/` — tidak perlu install |

---

## 2. Struktur Proyek

```
simak_app/
├── .env                  # Konfigurasi (database, email) — TIDAK di-commit
├── .env.example          # Template .env
├── config/               # Koneksi DB, session, env loader, email
├── controllers/          # Logika bisnis (Auth, Admin, Dosen, Mahasiswa, Laporan, PDF, MasterData)
├── includes/             # header.php (menu/sidebar), footer.php (template)
├── libraries/
│   └── dompdf/           Library PDF (sudah bundel, vendor di dalamnya)
├── public/               # DOCROOT — satu-satunya folder yang diakses browser
│   ├── assets/
│   │   ├── css/ js/      Aplikasi (style.css, app.js)
│   │   ├── fontawesome/  Ikon (di-bundle lokal)
│   │   └── img/          Logo perusahaan (logo_poltek.jpg dipakai PDF)
│   ├── admin/           Halaman role admin
│   ├── dosen/           Halaman role dosen
│   ├── mahasiswa/       Halaman role mahasiswa
│   ├── api/             Endpoint ajax (check_validasi.php)
│   ├── index.php        Entry: redirect sesuai role / halaman login
│   ├── login.php        Proses POST login
│   ├── logout.php       Hapus session
│   └── test.php         Halaman debug (hapus di production)
└── docs/                 Dokumentasi project (file ini, ARSITEKTUR.md, DATABASE.md)
```

> **Penting**: virtual host / document root harus mengarah ke folder `simak_app/`
> (parent dari `public/`), karena semua link internal memakai prefix `/simak_app/...`.

---

## 3. Langkah Setup (Laragon — Windows)

### 3.1 Clone / letakkan proyek

Letakkan folder `simak_app` di:

```
C:\laragon\www\simak_app
```

### 3.2 Buat database

Buka HeidiSQL / phpMyAdmin / CLI, lalu jalankan:

```sql
CREATE DATABASE simak_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu import struktur tabel — lihat **[DATABASE.md](DATABASE.md)** untuk daftar 15 tabel beserta kolomnya:

```bash
# via CLI MySQL
mysql -u root simak_db < simak_db.sql
```

> Struktur tabel lengkap dan penjelasan tiap kolom ada di `docs/DATABASE.md`.

### 3.3 Konfigurasi `.env`

Salin template lalu sesuaikan:

```bash
cp .env.example .env
```

Isi minimal bagian Database:

```env
SIMAK_DB_HOST=localhost
SIMAK_DB_NAME=simak_db
SIMAK_DB_USER=root
SIMAK_DB_PASS=
```

Bagian Email (SMTP) opsional — hanya dipakai fitur kirim email. Contoh Gmail:

```env
SIMAK_EMAIL_HOST=smtp.gmail.com
SIMAK_EMAIL_PORT=587
SIMAK_EMAIL_ENCRYPTION=tls
SIMAK_EMAIL_USERNAME=emailanda@gmail.com
SIMAK_EMAIL_PASSWORD=app_password_16_karakter   # Gmail App Password, bukan password akun
SIMAK_EMAIL_FROM_NAME=SIMAK - Politeknik Mitra Industri
```

Prioritas pembacaan nilai: **environment asli (OS/server) → file `.env` → default di kode**.

### 3.4 Jalankan

1. Start Laragon (Apache + MySQL)
2. Buka browser: `http://localhost/simak_app/public/` atau `http://simak_app.test/public/`
3. Login dengan akun yang ada di tabel `users`

> Jika halaman blank / "Koneksi Database Gagal", cek: MySQL sudah jalan, nama DB
> di `.env` benar, dan file `.env` readable oleh web server.

### 3.5 Setup XAMPP (alternatif)

1. Letakkan proyek di `C:\xampp\htdocs\simak_app`
2. Start **Apache** & **MySQL** dari XAMPP Control Panel
3. Buat DB & import tabel (langkah 3.2)
4. Buka `http://localhost/simak_app/public/`

---

## 4. Akun & Role

Aplikasi punya 3 role. Login dimulai dari `public/login.php`
(username + password, diverifikasi `password_verify` terhadap kolom `users.password` — hash bcrypt).

| Role | Akses utama |
|---|---|
| **admin** | Master data (fakultas, jurusan, mahasiswa, dosen, MK, jadwal), validasi IRS, verifikasi nilai, semua laporan + PDF, broadcast |
| **dosen** | Jadwal mengajar, buka sesi presensi, input/verifikasi nilai, tugas, laporan nilai MK yang diampu, broadcast kelas |
| **mahasiswa** | Dashboard, jadwal kuliah, IRS (ambil paket), KHS & IPK, Report KRS, Report Nilai, presensi (konfirmasi kode), tugas |

Untuk membuat akun pertama, insert manual ke `users` (hash password dengan
`password_hash('pass', PASSWORD_DEFAULT)` di PHP CLI):

```bash
php -r "echo password_hash('admin123', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
INSERT INTO users (username, password, nama, role)
VALUES ('admin', '<hasil_hash>', 'Administrator', 'admin');
```

Role lain (dosen/mahasiswa) dibuat dari menu Master Data admin — otomatis
membuat baris `users`, `dosen`/`mahasiswa`, dan relasinya sekaligus (transaksional).

---

## 5. Fitur PDF & Logo

- Semua laporan PDF memakai **Dompdf** (bundel) — konfigurasi ada di `controllers/PdfController.php`.
- Laporan ber-tabel lebar otomatis **A4 landscape**; laporan ringkas tetap portrait.
- Logo perusahaan di kop surat dibaca dari `public/assets/img/logo_poltek.jpg`.
  Ganti logo cukup timpa file tersebut dengan nama sama.
- Jika file logo hilang, PDF otomatis memakai placeholder dan tidak error.

---

## 6. Masalah Umum (Troubleshooting)

| Gejala | Penyebab & solusi |
|---|---|
| "Koneksi Database Gagal" | MySQL belum jalan / `.env` salah → cek `SIMAK_DB_*` |
| Halaman redirect terus ke login | Session expiry / cookie browser → cek `session.save_path` writable |
| 403/404 halaman role | URL harus `.../public/admin/...`, `.../public/dosen/...`, `.../public/mahasiswa/...` sesuai role — `checkAccess()` blok akses lintas role |
| PDF gagal / "headers already sent" | Ada `echo` sebelum generate PDF → jangan output apapun sebelum `$pdf->...` |
| Email tidak terkirim | Cek `SIMAK_EMAIL_*` di `.env`; Gmail wajib App Password (bukan password biasa), aktifkan 2FA dulu |
| Ikon Font Awesome tidak muncul | Pastikan folder `public/assets/fontawesome/` ikut ter-copy |
