# Laporan Keamanan SIMAK

## 🛡️ STATUS: SELESAI (Sudah Diperbaiki)

### 1. Autentikasi & Session Management ✅
- **Password hashing**: Semua password dihash menggunakan `password_hash()` dengan `PASSWORD_DEFAULT` (bcrypt). Terdapat di `config/database.php`, `controllers/AuthController.php`, `controllers/ProfileController.php`, `controllers/AdminController.php`, `controllers/DosenController.php`.
- **Password verification**: Menggunakan `password_verify()` selama proses login di `controllers/AuthController.php` baris 22.
- **Session destruction**: Logout lengkap dengan `session_destroy()` dan hapus cookie di `controllers/AuthController.php` baris 56-67 (men-set cookie dengan masa kadaluarsa lampau).
- **CSRF Protection**: Token CSRF sudah terimplementasi di `config/session.php` dengan `generateCsrfToken()` dan `validateCsrfToken()`. Setiap form POST wajib membawa CSRF token (lihat dokumentasi `docs/ALUR_PROSES.md` baris 28).
- **Role-based access control**: Fungsi `checkAccess()` di `config/session.php` memeriksa role user dan menolak akses (403) jika tidak sesuai. Setiap halaman diojek di baris atas dengan `checkAccess(['<role>'])` (docs/ALUR_PROSES.md baris 17).
- **Secure cookie flags**: Saat logout, cookie dihapus dengan parameter `secure`, `httponly`, dan `domain` yang aktif (AuthController.php baris 64-67).

### 2. Input Validation & Sanitization ✅
- **Email sanitization**: Fungsi `sanitizeEmail()` dan `sanitizeName()` di `includes/SmtpMailer.php` mencegah header injection (`\n` atau `\r`).
- **HTML escaping**: Penggunaan `htmlspecialchars()` di berbagai tempat untuk mencegah XSS, termasuk di `includes/header.php`, `profil.php`, dan email templates.
- **Empty input validation**: Validasi di `controllers/AuthController.php` baris 21 untuk memastikan username dan password diisi.
- **SQL Prepared Statements**: Semua query database menggunakan PDO Prepared Statements (tidak ada query string biasa). Terlihat di semua `controllers/*Controller.php` dan `config/database.php`.

### 3. Data Protection ✅
- **Environment configuration**: Kredensial database dan email menyimpan di `.env` yang sudah ada di `.gitignore` (tidak committed ke Git).
- **Password policy**: Validasi panjang minimal 6 karakter di `controllers/ProfileController.php` baris 401, serta penolakan password default/common di `config/email.php` baris 44-45.

---

## ⚠️ AREA PERLU DITANAMBAH KEAMANAN

### 1. Missing CSRF Validation on Forms ⚠️
**Masalah**: Meskipun token CSRF sudah dibuat di `config/session.php`, tidak semua form POST secara konsisten memvalidasi token tersebut.
- **Lokasi**: Beberapa controller tidak memanggil `validateCsrfToken()` sebelum proses penyimpanan data.
- **Files yang perlu dicek**: `controllers/*Controller.php` untuk form edit/update data.
- **Tindak Lanjut**: Pastikan setiap form yang mengubah data (especially `profil.php`, `validasi_irs.php`, `verifikasi_nilai.php`) memvalidasi CSRF token setelah submit.

### 2. Password Reset Mechanism Lemah ⚠️
**Masalah**: Sistem generate password acak 8 karakter di `controllers/AuthController.php` (jika ada method reset) dan terlihat di `docs/SETUP.md`.
- **Lokasi**: `controllers/AuthController.php` baris 1409 (`generateRandomPassword(8)`), `template/email/reset_password.php`.
- **Tindak Lanjut**: 
  - Gunakan password yang lebih kuat (16+ karakter dengan campuran huruf besar/kecil, angka, simbol).
  - Kirim link reset password via email rather than menampilkan password langsung.
  - Password acak harus dibuat satu kali dan disimpan terenkripsi, bukan ditampilkan di email.

### 3. Password Visibility di Email ⚠️
**Masalah**: Password sementara ditampilkan dalam email tanpa enkripsi tambahan.
- **Lokasi**: `template/email/akun_baru.php` baris 12-14, `template/email/reset_password.php` baris 12-14.
- **Tindak Lanjut**: 
  - Hindari menampilkan password langsung di body email.
  - Gunakan link reset password yang aman dengan token satu pakai.
  - Jika wajib kirim password, gunakan enkripsi end-to-end atau kirim hanya instruksi "ganti password segera".

### 4. Error Reporting & Information Leakage ⚠️
**Masalah**: Error PHP/koneksi database bisa menampilkan detail sistem kepada user jika `display_errors` aktif di production.
- **Lokasi**: `config/database.php` baris 27 (`PDO::ERRMODE_EXCEPTION`), konfigurasi `.env` untuk production.
- **Tindak Lanjut**: 
  - Pastikan `display_errors = Off` di production PHP.
  - Error ditangkap ke `PDO::ERRMODE_EXCEPTION` saja (sudah ada), tapi hindari menampilkan pesan error ke browser akhir.
  - Halaman error 403/404 sudah dibuat tapi pastikan tidak leak information.

### 5. File Upload Security ⚠️
**Masalah**: Validasi upload file belum divalidasi ekstensi dan tipe MIME secara ketat.
- **Lokasi**: Cek `controllers/*Controller.php` untuk form unggah foto/berkas.
- **Tindak Lanjut**: 
  - Validasi ekstensi file (hanya izinkan: .jpg, .png, .gif, .pdf).
  - Validasi MIME type di sisi server.
  - Simpan file dengan nama acak/generate baru, bukan nama asli user.
  - Batasi ukuran file (misal: max 2MB).
  - Pergunakan whitelist ekstensi, bukan hitungan hitamlist.

### 6. Missing HTTP Security Headers ⚠️
**Masalah**: Headers keamanan HTTP tidak disetel di aplikasi.
- **Lokasi**: Tidak ada pengaturan header di `index.php`, `logout.php`, atau `config/` apa pun.
- **Tindak Lanjut**: 
  - Tambahkan headers berikut di `public/index.php` atau `.htaccess`:
    - `X-Content-Type-Options: nosniff`
    - `X-Frame-Options: DENY` atau `SAMEORIGIN`
    - `X-XSS-Protection: 1; mode=block`
    - `Referrer-Policy: strict-origin-when-cross-origin`
  - Pastikan HTTPS selalu digunakan (parameter `secure` di cookie sudah sebagian ada saat logout).

### 7. Directory Listing & File Exposure ⚠️
**Masalah**: Beberapa file konfigurasi atau backup bisa diakses langsung jika URL diketahui.
- **Lokasi**: Periksa akses langsung ke `config/.env`, `.env`, `logs/`, `uploads/avatars/`.
- **Tindak Lanjut**: 
  - Pastikan `.htaccess` atau konfigurasi web server menolak akses langsung ke file `.env` dan `logs/*`.
  - Folder `uploads/` harus memiliki `.htaccess` untuk menolak eksekusi PHP/gambar saja.
  - Folder `logs/` sudah ada di `.gitignore`, pastikan juga di-level web server.

### 8. Session Fixation Risk ⚠️
**Masalah**: Session tidak di-regenerate setelah login, berisiko fixation attack.
- **Lokasi**: Proses login di `controllers/AuthController.php`.
- **Tindak Lanjut**: 
  - Setelah login sukses, baru generate CSRF token baru (sudah ada di `generateCsrfToken()`).
  - Namun pastikan `session_regenerateid(true)` dipanggil setelah `$_SESSION['user_id']` di-set untuk mencegah fixation.

### 9. API Security ⚠️
**Masalah**: Beberapa endpoint API mungkin tidak memiliki perlindungan role/autentikasi sepenuhnya.
- **Lokasi**: `public/api/check_validasi.php` dan endpoints lain tanpa `require session.php`.
- **Tindak Lanjut**: 
  - Semua endpoint API wajib memeriksa `isLoggedIn()` di awal.
  - Validasi CSRF untuk form API POST.
  - Limit rate untuk mencegah brute force login.

### 10. Backup & Recovery Security ⚠️
**Masalah**: Backup database mungkin berisi data sensitif yang disimpan di lokasi tidak aman.
- **Lokasi**: `logs/database/` dan manual backup commands di `docs/DATABASE.md`.
- **Tindak Lanjut**: 
  - Database backup tidak boleh disimpan di direktori publik/web-accessible.
  - Enkripsi backup sebelum disimpan ke drive/remote.
  - Rotasi backup rutin, jangan menumpuk lama-lama.