# Letak Desain CSS Lembar Report & PDF (Dompdf)

Pertanyaan yang paling sering muncul: **"file CSS mana yang mengatur tampilan report lembar PDF-nya?"**

## Jawaban singkat

| Yang diatur | Letak desainnya | File CSS? |
|---|---|---|
| **Lembar PDF hasil Dompdf** (file yang di-download/stream `cetak_pdf.php`) | `controllers/PdfController.php` | ❌ **Bukan file CSS** — CSS-nya **inline di dalam PHP** |
| Tampilan halaman report di **layar** (sebelum klik tombol PDF) | `public/assets/css/report.css` | ✅ File CSS, dimuat lewat `$page_css_map` di `includes/header.php` |

> Poin kunci: **`report.css` TIDAK dipakai oleh PDF Dompdf.** Dompdf merender HTML yang digenerate sendiri oleh `PdfController` lengkap dengan CSS internalnya. Mengubah `report.css` tidak akan mengubah apa pun di file PDF hasil unduhan.

---

## 1. Lembar PDF (Dompdf) — semua ada di `controllers/PdfController.php`

Tiga bagian desain di dalam satu file:

| Fungsi | Baris (kira-kira) | Mengatur apa |
|---|---|---|
| `getBaseStyle()` | ~sekitar baris 128–150 | **CSS dasar lembar PDF**: font Arial 11px, warna teks `#333`, padding halaman, `table.data` (header `#2c3e50` putih, border, uppercase), dll. Inilah "file CSS"-nya PDF. |
| `getHeader($title)` | ~baris 40–100 | **Kop surat**: logo institusi (`public/assets/img/logo_poltek.jpg`, di-embed base64 — fallback lambang "S" SVG), nama institusi, judul laporan, tanggal cetak. Style ditulis inline per elemen. |
| `getFooter()` | ~baris 100–130 | **Footer lembar PDF**: blok tanda tangan "Admin Akademik" + tanggal + garis disclaimer. |

Plus di setiap fungsi `generateXxx()`:

- **Orientasi & ukuran kertas** (A4 portrait/landscape) diset sebagai opsi Dompdf, bukan CSS.
- Beberapa laporan punya kelebihan `<style>` sendiri di template masing-masing.

### Cara mengubah desain PDF

Saya mau ubah... → edit di `PdfController.php` bagian...

| Yang ingin diubah | Edit di mana |
|---|---|
| Font, margin, ukuran teks body PDF | `getBaseStyle()` |
| Warna/bentuk header tabel `table.data` | `getBaseStyle()` |
| Nama institusi, alamat, ukuran logo, border kop | `getHeader()` |
| Blok tanda tangan / disclaimer bawah | `getFooter()` |
| Banyak kolom berdempet sampai terpotong | opsi orientasi di fungsi `generateXxx()` (kertas landscape) |
| Logo PDF | file `public/assets/img/logo_poltek.jpg` (sama dengan logo sidebar) |

**Catatan keterbatasan Dompdf:** baru mendukung subset CSS2, tidak mendukung CSS grid/layout modern, `position: fixed` terbatas, dan font di-serve dari font lokal Dompdf — jadi jangan berharap meng-copy style web apa adanya.

---

## 2. Halaman report di layar — `public/assets/css/report.css`

File CSS kecil yang dimuat untuk halaman-halaman dokumen read-only berikut (pemetaan via `$page_css_map` di `includes/header.php`):

| Halaman | Path | Role |
|---|---|---|
| Report KRS | `public/mahasiswa/report_krs.php` | mahasiswa |
| Rekap nilai | `public/mahasiswa/report_nilai.php` | mahasiswa |
| KHS | `public/mahasiswa/khs.php` | mahasiswa |
| Laporan nilai yang diampu | `public/dosen/laporan_nilai.php` | dosen |

Isi `report.css`:

- `.krs-title` — judul dokumen (bold, warna secondary)
- `.krs-sem-btn`, `.krs-semester-picker`, `.lbl` — deretan pill pilih semester + label
- `.signature-section`, `.signature` — bagian tanda tangan untuk tampilan dokumen cetak
- Sisanya (kartu, tabel, badge) memakai **`base.css`**, karena `base.css` selalu dimuat di semua halaman.

Pengecualian: `mahasiswa/irs_print.php` **tidak** memakai Dompdf — cetaknya via `window.print()` browser dengan CSS print.

---

## 3. Ringkasan sewaktu edit desain report

```
Tampilan di layar  → public/assets/css/report.css (+ base.css untuk tema umum)
File PDF unduhan   → controllers/PdfController.php  (getBaseStyle / getHeader / getFooter)
Tombol cetak irs_print → print CSS (CSS media print) — bukan Dompdf
Naikkan versi cache $css_v di includes/header.php setelah mengedit report.css
```

*Dibuat dari kode aktual `PdfController.php` dan `$page_css_map` di `includes/header.php`. November 2026.*
