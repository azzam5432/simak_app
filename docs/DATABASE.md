# Database SIMAK — Struktur Tabel

Database `simak_db` — MySQL/MariaDB, 15 tabel, engine default, charset `utf8mb4`.
Dokumen ini didapat dengan membaca langsung struktur database (SHOW COLUMNS / SHOW CREATE TABLE).

---

## 1. Overview Relasi

```
users (id akun semua role)
 ├─ 1 : 1 ─ mahasiswa (profil mhs)
 ├─ 1 : 1 ─ dosen      (profil dosen)

fakultas ──< jurusan ──< courses
              │             │
              └──< mahasiswa├─< jadwal >── dosen
                            │
        ┌───────────────────┼────────────────────┐
        ▼                   ▼                    ▼
      irs                presensi              tasks
        │                   │                    │
        └── (irs & presensi juga rujuk mahasiswa └─< task_submissions >── mahasiswa

grades   (mahasiswa × course)     presensi_sessions (course × dosen, kode presensi)
announcements ──< announcement_reads (siapa sudah baca)
```

---

## 2. Daftar Tabel (15)

| # | Tabel | Fungsi | Row count (data saat ini) |
|---|---|---|---|
| 1 | users | Akun login semua role | 5 |
| 2 | fakultas | Master fakultas | 2 |
| 3 | jurusan | Master jurusan (FK fakultas) | 3 |
| 4 | mahasiswa | Profil mahasiswa (FK users, fakultas, jurusan) | 2 |
| 5 | dosen | Profil dosen (FK users, fakultas, jurusan) | 2 |
| 6 | courses | Mata kuliah (FK jurusan) | 2 |
| 7 | jadwal | Jadwal kuliah per MK (FK course, dosen) | 2 |
| 8 | irs | Kartu Rencana Studi mhs (FK mahasiswa, course) | 3 |
| 9 | grades | Nilai per mhs per MK (FK mahasiswa, course) | 3 |
| 10 | presensi | Absensi mhs per pertemuan (FK course, mahasiswa) | 3 |
| 11 | presensi_sessions | Sesi/kode presensi aktif (FK course, dosen) | 3 |
| 12 | tasks | Tugas dari dosen (FK course, dosen) | 3 |
| 13 | task_submissions | Jawaban mhs ke tugas (FK task, mahasiswa) | 3 |
| 14 | announcements | Broadcast pengumuman (FK sender → users) | 3 |
| 15 | announcement_reads | Status "sudah dibaca" per user (FK announcement, user) | 0 |

---

## 3. Struktur Detail per Tabel

Format kolom: **nama | tipe | constraint | default | keterangan**

### 3.1 `users` — akun semua role

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| username | VARCHAR(50) | NOT NULL, UNIQUE | - | Login ID |
| password | VARCHAR(255) | NOT NULL | - | Hash bcrypt (`password_hash`) |
| nama | VARCHAR(100) | NOT NULL | - | Nama lengkap |
| nip_nim | VARCHAR(20) | UNIQUE | NULL | NIP (dosen) / NIM (mhs), boleh null di users |
| email | VARCHAR(100) | - | NULL | |
| role | ENUM('admin','dosen','mahasiswa') | NOT NULL | - | Menentukan akses halaman |
| created_at / updated_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.2 `fakultas`

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| kode | VARCHAR(10) | NOT NULL, UNIQUE | - | Mis. FTIK |
| nama | VARCHAR(100) | NOT NULL | - | |
| dekan | VARCHAR(100) | NULL | NULL | Nama dekan |
| created_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.3 `jurusan`

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| fakultas_id | INT | NOT NULL, FK → fakultas.id | - | |
| kode | VARCHAR(10) | NOT NULL, UNIQUE | - | |
| nama | VARCHAR(100) | NOT NULL | - | |
| jenjang | ENUM('D3','D4','S1','S2') | NULL | 'D3' | Program jenjang |
| ketua_jurusan | VARCHAR(100) | NULL | NULL | |
| created_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.4 `mahasiswa`

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | ID dipakai di seluruh fitur mhs |
| user_id | INT | NOT NULL, UNIQUE, FK → users.id | - | 1-1 dengan akun |
| fakultas_id | INT | NULL, FK → fakultas.id | NULL | |
| jurusan_id | INT | NULL, FK → jurusan.id | NULL | Penentu paket pembelajaran IRS |
| tahun_ajaran | VARCHAR(10) | NULL | NULL | Mis. 2024/2025 (angkatan) |
| tingkat | INT | NULL | 1 | 1..4; semester aktif = 2×tingkat−1 |
| semester | INT | NULL | 1 | **Semester aktif** — IRS hanya bisa diisi di sini |
| nim | VARCHAR(20) | NOT NULL, UNIQUE | - | |
| ipk | DECIMAL(3,2) | NULL | 0.00 | Salinan IPK (perhitungan live juga ada) |

### 3.5 `dosen`

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| user_id | INT | NOT NULL, UNIQUE, FK → users.id | - | |
| nid | VARCHAR(30) | NOT NULL, UNIQUE | - | Nomor Induk Dosen |
| fakultas_id | INT | NOT NULL, FK → fakultas.id | - | |
| jurusan_id | INT | NOT NULL, FK → jurusan.id | - | |
| created_at / updated_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.6 `courses` — mata kuliah

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| kode_mk | VARCHAR(20) | NOT NULL, UNIQUE | - | Mis. TIF101 |
| nama_mk | VARCHAR(100) | NOT NULL | - | |
| sks | INT | NOT NULL | - | |
| semester | INT | NOT NULL | - | Semester ke berapa MK ini (paket) |
| jurusan_id | INT | NOT NULL, FK → jurusan.id | - | MK milik jurusan tertentu |
| deskripsi | TEXT | NULL | NULL | |
| created_at / updated_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.7 `jadwal`

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| course_id | INT | NOT NULL, FK → courses.id | - | |
| dosen_id | INT | NOT NULL, FK → dosen.id | - | Pengampu |
| tingkat | INT | NOT NULL | - | Tingkat kelas |
| rombel | CHAR(2) | NOT NULL | - | Kelas rombongan (mis. A) |
| hari | VARCHAR(20) | NOT NULL | - | Senin..Sabtu |
| jam_mulai | TIME | NOT NULL | - | |
| jam_selesai | TIME | NOT NULL | - | |
| ruang | VARCHAR(50) | NULL | NULL | |
| kapasitas | INT | NULL | 30 | Kuota peserta |
| tahun_ajaran | VARCHAR(10) | NULL | NULL | |
| semester_ke | INT | NULL | NULL | |
| created_at / updated_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.8 `irs` — Kartu Rencana Studi

| Kolom | Tipe | Constraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| mahasiswa_id | INT | NOT NULL, FK → mahasiswa.id | - | |
| course_id | INT | NOT NULL, FK → courses.id | - | 1 MK = 1 baris |
| semester | VARCHAR(10) | NOT NULL | - | Semester pengambilan |
| tahun_akademik | VARCHAR(10) | NULL | NULL | |
| status | ENUM('pending','approved','rejected') | NULL | 'pending' | Lihat alur validasi |
| catatan | TEXT | NULL | NULL | Alasan reject, dst |
| created_at / updated_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

> Anti-duplikat didukung query-level: `NOT EXISTS (mahasiswa_id, course_id, semester)` saat insert.

### 3.9 `grades` — nilai per mhs per MK

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| mahasiswa_id | INT | NOT NULL, FK → mahasiswa.id | - | |
| course_id | INT | NOT NULL, FK → courses.id | - | |
| semester | VARCHAR(10) | NOT NULL | - | |
| nilai_tugas | DECIMAL(5,2) | NULL | 0.00 | |
| nilai_uts | DECIMAL(5,2) | NULL | 0.00 | |
| nilai_uas | DECIMAL(5,2) | NULL | 0.00 | |
| nilai_akhir | DECIMAL(5,2) | NULL | 0.00 | |
| status_verifikasi | ENUM('draft','verified') | NULL | 'draft' | 'draft' harus diverifikasi admin |
| catatan | VARCHAR(255) | NULL | NULL | |
| created_at / updated_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.10 `presensi` — absensi

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| course_id | INT | NOT NULL, FK → courses.id | - | |
| mahasiswa_id | INT | NOT NULL, FK → mahasiswa.id | - | |
| tanggal | DATE | NOT NULL | - | |
| pertemuan_ke | INT | NULL | NULL | |
| status | ENUM('hadir','izin','sakit','alpa') | NULL | 'alpa' | |
| keterangan | TEXT | NULL | NULL | |
| waktu_absen | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.11 `presensi_sessions` — sesi kode presensi

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| course_id | INT | NOT NULL, FK → courses.id | - | |
| dosen_id | INT | NOT NULL, FK → dosen.id | - | Pembuka sesi |
| kode | VARCHAR(10) | NOT NULL | - | Kode yang dibacakan dosen |
| tanggal | DATE | NOT NULL | - | |
| waktu_mulai | DATETIME | NOT NULL | - | Window validasi 30 menit dari jam ini |
| waktu_selesai | DATETIME | NULL | NULL | |
| is_active | TINYINT(1) | NULL | 1 | 0 = sesi ditutup |
| created_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.12 `tasks` — tugas

| Kolom | Tipe | Constraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| course_id | INT | NOT NULL, FK → courses.id | - | Dikirim ke mhs IRS approved |
| dosen_id | INT | NOT NULL, FK → dosen.id | - | Pembuat |
| judul | VARCHAR(100) | NOT NULL | - | |
| deskripsi | TEXT | NULL | NULL | |
| deadline | DATETIME | NOT NULL | - | Basis status expired/urgent |
| bobot_nilai | DECIMAL(5,2) | NULL | 0.00 | |
| created_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.13 `task_submissions` — pengumpulan tugas

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| task_id | INT | NOT NULL, FK → tasks.id | - | |
| mahasiswa_id | INT | NOT NULL, FK → mahasiswa.id | - | 1 mhs 1 submit per task |
| file_path | VARCHAR(255) | NULL | NULL | Jalur file/unggahan |
| catatan | TEXT | NULL | NULL | |
| status | ENUM('draft','submitted','late') | NULL | 'draft' | |
| nilai | DECIMAL(5,2) | NULL | NULL | Nilai dosen ke tugas |
| submitted_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.14 `announcements` — broadcast

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| sender_id | INT | NOT NULL, FK → users.id | - | Admin atau dosen |
| sender_role | ENUM('admin','dosen') | NOT NULL | - | |
| target_role | ENUM('all','admin','dosen','mahasiswa') | NULL | 'all' | |
| judul | VARCHAR(100) | NOT NULL | - | |
| pesan | TEXT | NOT NULL | - | |
| priority | ENUM('low','medium','high') | NULL | 'medium' | |
| is_read | TINYINT(1) | NULL | 0 | Legacy (read-tracking per user lewat 3.15) |
| created_at | TIMESTAMP | - | CURRENT_TIMESTAMP | |

### 3.15 `announcement_reads`

| Kolom | Tipe | Konstraint | Default | Keterangan |
|---|---|---|---|---|
| id | INT | PK, AI | - | |
| announcement_id | INT | NOT NULL, FK → announcements.id | - | |
| user_id | INT | NOT NULL, FK → users.id | - | |
| read_at | TIMESTAMP | - | CURRENT_TIMESTAMP | Badge "belum dibaca" = announcement aktif yang belum ada di sini |

---

## 4. Foreign Key Lengkap

Semua FK adalah constraint InnoDB nyata (bukan konvensi nama saja):

| Child kolom | → Parent |
|---|---|
| mahasiswa.user_id | users.id |
| mahasiswa.fakultas_id | fakultas.id |
| mahasiswa.jurusan_id | jurusan.id |
| dosen.user_id | users.id |
| dosen.fakultas_id | fakultas.id |
| dosen.jurusan_id | jurusan.id |
| courses.jurusan_id | jurusan.id |
| jadwal.course_id | courses.id |
| jadwal.dosen_id | dosen.id |
| irs.mahasiswa_id | mahasiswa.id |
| irs.course_id | courses.id |
| grades.mahasiswa_id | mahasiswa.id |
| grades.course_id | courses.id |
| presensi.course_id | courses.id |
| presensi.mahasiswa_id | mahasiswa.id |
| presensi_sessions.course_id | courses.id |
| presensi_sessions.dosen_id | dosen.id |
| tasks.course_id | courses.id |
| tasks.dosen_id | dosen.id |
| task_submissions.task_id | tasks.id |
| task_submissions.mahasiswa_id | mahasiswa.id |
| announcements.sender_id | users.id |
| announcement_reads.announcement_id | announcements.id |
| announcement_reads.user_id | users.id |

---

## 5. Query-Query Penting yang Dipakai Aplikasi

### 5.1 Menghitung IPK mahasiswa (aturan 75% nilai + 25% kehadiran)

```sql
-- per MK: nilai_akhir + data kehadiran
SELECT g.nilai_akhir, c.sks,
       -- data kehadiran per MK untuk mhs tsb:
       SUM(p.status IN ('hadir','izin','sakit')) / COUNT(p.id) AS kehadiran
FROM grades g
JOIN courses c   ON g.course_id = c.id
LEFT JOIN presensi p ON p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id
WHERE g.mahasiswa_id = ? AND g.status_verifikasi = 'verified'
GROUP BY g.id;
-- nilai_final = 0.75*nilai_akhir + 0.25*(kehadiran*100) → bobot A/B/C/D/E
-- IPK = Σ(bobot*sks) / Σ(sks)
```

Implementasi: `MahasiswaController::hitungIPK()` & `LaporanController::hitungNilaiFinal()`.

### 5.2 Paket pembelajaran IRS (hasil `getPaketBySemester`)

```sql
-- Semua MK semester X untuk jurusan mhs + flag sudah diambil
SELECT c.*, j.*, EXISTS (
    SELECT 1 FROM irs WHERE irs.course_id = c.id
      AND irs.mahasiswa_id = ? AND irs.semester = ?
) as sudah_diambil
FROM courses c
LEFT JOIN jurusan j ON j.id = c.jurusan_id
WHERE c.semester = ?;
```

### 5.3 Sesi presensi aktif (`getActivePresensiSession`)

```sql
SELECT * FROM presensi_sessions
WHERE is_active = 1
  AND tanggal = CURDATE()
  AND waktu_mulai > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
  AND course_id IN (
      SELECT course_id FROM irs
      WHERE mahasiswa_id = ? AND status = 'approved'
  )
ORDER BY waktu_mulai DESC LIMIT 1;
```

### 5.4 Rekap presensi per MK untuk KHS

```sql
SELECT course_id,
       SUM(status='hadir') hadir,
       SUM(status='izin')  izin,
       SUM(status='sakit') sakit,
       SUM(status='alpa')  alpa,
       COUNT(*)            total
FROM presensi WHERE mahasiswa_id = ?
GROUP BY course_id;
```

---

## 6. Backup / Restore

```bash
# Backup
mysqldump -u root -p simak_db > simak_db_backup.sql

# Restore
mysql -u root -p simak_db < simak_db_backup.sql
```

Untuk reset data (hanya struktur), TRUNCATE tabel berurutan mengikuti FK:
`announcement_reads` → `announcements` → `task_submissions` → `tasks` →
`presensi_sessions` → `presensi` → `grades` → `irs` → `jadwal` → `courses` →
`mahasiswa` → `dosen` → `jurusan` → `fakultas` → `users`.
