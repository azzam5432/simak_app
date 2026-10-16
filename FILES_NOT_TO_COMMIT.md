# File yang Jangan Di-commit ke GitHub

Proyek ini menggunakan file `.gitignore`, tetapi daftar ini memberikan panduan tambahan untuk kontributor.

## Konfigurasi & Lingkungan Lokal

- `.env` - Variabel lingkungan lokal (sudah ada di `.gitignore`)
- `.env.example` - Hanya template (aman di-commit, tidak berisi rahasianya)

## Log & Data Runtime

- `logs/` - Direktori berisi log runtime (sudah ada di `.gitignore`)
  - `logs/css_audit.php` - Log audit CSS
  - `logs/email/` - Log pengiriman email
  - `logs/pdf/` - Log pembuatan PDF

## File Temp & Testing

- `test_output_tmp.xlsx` - Output Excel sementara
- `test_xlsx_tmp.php` - Skrip test sementara

## Aset yang Sudah Dirapah (CSS Split)

- `public/assets/css/style.css` - **Sudah dihapus**; diganti 8 file CSS per halaman (`base.css`, `dashboard.css`, `profil.css`, `irs.css`, `jadwal.css`, `laporan.css`, `login.css`, `report.css`)
- `public/test.php` - Referensi telah diperbarui ke `base.css` (dari lama `style.css`)
- File `.tmp` atau backup yang dibuat selama pengembangan

## Data Sensitif

- Kunci API, kredensial database, atau token yang disimpan di `.env` atau environment variables — **Jangan pernah commit ini**

## File Database & O-RM

- File database (`.sql`, `.db`, `.sqlite`) — handle migrasi secara terpisah melalui alur version control