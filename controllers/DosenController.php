<?php
// ============================================
// controllers/DosenController.php
// Logika presensi, input nilai, tugas, broadcast
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

class DosenController {
    private $pdo;
    private $dosen_id;
    
    public function __construct($pdo, $dosen_id = null) {
        $this->pdo = $pdo;
        $this->dosen_id = $dosen_id;
    }
    
    // ============================================
    // DASHBOARD & JADWAL
    // ============================================
    
    public function getDashboardStats() {
        $stats = [];
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM jadwal WHERE dosen_id = ?");
        $stmt->execute([$this->dosen_id]);
        $stats['total_kelas'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT irs.mahasiswa_id) as total
            FROM irs
            JOIN jadwal jd ON irs.course_id = jd.course_id
            WHERE jd.dosen_id = ? AND irs.status = 'approved'
        ");
        $stmt->execute([$this->dosen_id]);
        $stats['total_mahasiswa'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tasks 
            WHERE dosen_id = ? AND deadline > NOW()
        ");
        $stmt->execute([$this->dosen_id]);
        $stats['tugas_aktif'] = $stmt->fetch()['total'];
        
        $hariIni = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT p.mahasiswa_id) as total
            FROM presensi p
            JOIN jadwal jd ON p.course_id = jd.course_id
            WHERE jd.dosen_id = ? AND p.tanggal = ?
        ");
        $stmt->execute([$this->dosen_id, $hariIni]);
        $stats['presensi_hari_ini'] = $stmt->fetch()['total'];
        
        return $stats;
    }
    
    public function getJadwalMengajar() {
        $stmt = $this->pdo->prepare("
            SELECT jd.*, c.kode_mk, c.nama_mk, c.sks, c.semester,
                   COUNT(DISTINCT irs.mahasiswa_id) as jumlah_mahasiswa
            FROM jadwal jd
            JOIN courses c ON jd.course_id = c.id
            LEFT JOIN irs ON jd.course_id = irs.course_id AND irs.status = 'approved'
            WHERE jd.dosen_id = ?
            GROUP BY jd.id, jd.course_id, jd.dosen_id, jd.tingkat, jd.rombel, jd.hari,
                     jd.jam_mulai, jd.jam_selesai, jd.ruang, jd.kapasitas,
                     jd.tahun_ajaran, jd.semester_ke, jd.created_at, jd.updated_at,
                     c.kode_mk, c.nama_mk, c.sks, c.semester
            ORDER BY FIELD(jd.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jd.jam_mulai
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
    
    public function getJadwalHariIni() {
        $hari = $this->getHariIndonesia(date('N'));
        $stmt = $this->pdo->prepare("
            SELECT jd.*, c.kode_mk, c.nama_mk, c.sks, c.semester,
                   COUNT(DISTINCT irs.mahasiswa_id) as jumlah_mahasiswa
            FROM jadwal jd
            JOIN courses c ON jd.course_id = c.id
            LEFT JOIN irs ON jd.course_id = irs.course_id AND irs.status = 'approved'
            WHERE jd.dosen_id = ? AND jd.hari = ?
            GROUP BY jd.id, jd.course_id, jd.dosen_id, jd.tingkat, jd.rombel, jd.hari,
                     jd.jam_mulai, jd.jam_selesai, jd.ruang, jd.kapasitas,
                     jd.tahun_ajaran, jd.semester_ke, jd.created_at, jd.updated_at,
                     c.kode_mk, c.nama_mk, c.sks, c.semester
            ORDER BY jd.jam_mulai ASC
        ");
        $stmt->execute([$this->dosen_id, $hari]);
        return $stmt->fetchAll();
    }
    
    private function getHariIndonesia($dayNumber) {
        $hari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 
                 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        return $hari[$dayNumber] ?? 'Senin';
    }
    
    public function getMatakuliahDosen() {
        $stmt = $this->pdo->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT irs.mahasiswa_id) as jumlah_mahasiswa
            FROM courses c
            JOIN jadwal jd ON jd.course_id = c.id AND jd.dosen_id = ?
            LEFT JOIN irs ON c.id = irs.course_id AND irs.status = 'approved'
            GROUP BY c.id, c.kode_mk, c.nama_mk, c.sks, c.semester, c.jurusan_id, c.deskripsi,
                     c.created_at, c.updated_at
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // LAPORAN NILAI MAHASISWA YANG DIAMPU
    // ============================================
    
    /**
     * List mata kuliah + semester yang pernah diampu dosen ini (untuk dropdown filter).
     */
    public function getMatakuliahDenganSemester() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT c.id, c.kode_mk, c.nama_mk, c.sks, g.semester
            FROM courses c
            JOIN jadwal jd ON jd.course_id = c.id AND jd.dosen_id = ?
            JOIN grades g ON g.course_id = c.id
            ORDER BY c.kode_mk ASC, g.semester ASC
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Nilai semua mahasiswa pada mata kuliah yang diampu dosen ini.
     * Filter opsional: course_id dan/atau semester.
     * Rumus nilai final konsisten dengan KHS: nilai komponen 75% + kehadiran 25%.
     */
    public function getLaporanNilaiMahasiswaAmpu($course_id = null, $semester = null) {
        $sql = "
            SELECT 
                g.id, c.kode_mk, c.nama_mk, c.sks, g.semester,
                g.nilai_tugas, g.nilai_uts, g.nilai_uas, g.nilai_akhir,
                g.status_verifikasi,
                m.nim, u.nama as mahasiswa_nama, j.nama as program_studi,
                COALESCE(p.total, 0) as total_pertemuan,
                COALESCE(p.hadir, 0) as p_hadir,
                COALESCE(p.izin, 0) as p_izin,
                COALESCE(p.sakit, 0) as p_sakit,
                COALESCE(p.alpa, 0) as p_alpa
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            JOIN jadwal jd ON jd.course_id = c.id AND jd.dosen_id = ?
            JOIN mahasiswa m ON g.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            LEFT JOIN (
                SELECT course_id, mahasiswa_id, COUNT(*) as total,
                    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                    SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                    SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                    SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as alpa
                FROM presensi GROUP BY course_id, mahasiswa_id
            ) p ON p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id
            WHERE 1=1
        ";
        $params = [$this->dosen_id];
        
        if ($course_id) {
            $sql .= " AND g.course_id = ?";
            $params[] = $course_id;
        }
        
        if ($semester) {
            $sql .= " AND g.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " GROUP BY g.id, c.kode_mk, c.nama_mk, c.sks, g.semester, g.nilai_tugas, g.nilai_uts,
                          g.nilai_uas, g.nilai_akhir, g.status_verifikasi, m.nim, u.nama, j.nama,
                          p.total, p.hadir, p.izin, p.sakit, p.alpa
                  ORDER BY c.kode_mk ASC, g.semester ASC, m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // PRESENSI
    // ============================================
    
    public function getMahasiswaPerKelas($course_id) {
        $stmt = $this->pdo->prepare("
            SELECT m.id AS mahasiswa_id, u.nama, m.nim, j.nama as program_studi, m.semester
            FROM irs
            JOIN mahasiswa m ON irs.mahasiswa_id = m.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            JOIN users u ON m.user_id = u.id
            WHERE irs.course_id = ? AND irs.status = 'approved'
            ORDER BY m.nim ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll();
    }
    
    public function getPresensiByCourse($course_id, $tanggal = null) {
        $sql = "
            SELECT p.*, u.nama as mahasiswa_nama, m.nim
            FROM presensi p
            JOIN mahasiswa m ON p.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            WHERE p.course_id = ?
        ";
        $params = [$course_id];
        
        if ($tanggal) {
            $sql .= " AND p.tanggal = ?";
            $params[] = $tanggal;
        }
        
        $sql .= " ORDER BY m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getRekapPresensi($course_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                m.id as mahasiswa_id,
                u.nama as mahasiswa_nama,
                m.nim,
                COUNT(p.id) as total_pertemuan,
                SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN p.status = 'alpa' THEN 1 ELSE 0 END) as alpa
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN presensi p ON m.id = p.mahasiswa_id AND p.course_id = ?
            JOIN irs ON m.id = irs.mahasiswa_id AND irs.course_id = ?
            WHERE irs.status = 'approved'
            GROUP BY m.id, u.nama, m.nim
            ORDER BY m.nim ASC
        ");
        $stmt->execute([$course_id, $course_id]);
        return $stmt->fetchAll();
    }
    
    public function inputPresensiMassal($data) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($data['presensi'] as $mahasiswa_id => $status) {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM presensi 
                    WHERE course_id = ? AND mahasiswa_id = ? AND tanggal = ?
                ");
                $stmt->execute([$data['course_id'], $mahasiswa_id, $data['tanggal']]);
                
                if ($stmt->fetch()) {
                    $stmt = $this->pdo->prepare("
                        UPDATE presensi SET status = ?
                        WHERE course_id = ? AND mahasiswa_id = ? AND tanggal = ?
                    ");
                    $stmt->execute([$status, $data['course_id'], $mahasiswa_id, $data['tanggal']]);
                } else {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO presensi (course_id, mahasiswa_id, tanggal, status)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$data['course_id'], $mahasiswa_id, $data['tanggal'], $status]);
                }
            }
            
            $this->pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // SESI PRESENSI - VERSI DATABASE (HANYA SATU VERSI)
    // ============================================
    
    /**
     * Buka sesi presensi - SIMPAN KE DATABASE
     */
    public function bukaSesiPresensi($course_id) {
        try {
            // Cek apakah sudah ada sesi aktif untuk course ini
            $stmt = $this->pdo->prepare("
                SELECT id FROM presensi_sessions 
                WHERE course_id = ? AND is_active = 1
            ");
            $stmt->execute([$course_id]);
            
            if ($stmt->fetch()) {
                // Tutup sesi lama dulu
                $stmt = $this->pdo->prepare("
                    UPDATE presensi_sessions 
                    SET is_active = 0, waktu_selesai = NOW()
                    WHERE course_id = ? AND is_active = 1
                ");
                $stmt->execute([$course_id]);
            }
            
            // Generate kode 6 digit
            $kode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Insert sesi baru
            $stmt = $this->pdo->prepare("
                INSERT INTO presensi_sessions (course_id, dosen_id, kode, tanggal, waktu_mulai, is_active)
                VALUES (?, ?, ?, CURDATE(), NOW(), 1)
            ");
            $stmt->execute([$course_id, $this->dosen_id, $kode]);
            
            return [
                'success' => true,
                'kode' => $kode,
                'session_id' => $this->pdo->lastInsertId()
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Tutup sesi presensi - UPDATE DATABASE
     */
    public function tutupSesiPresensi($course_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE presensi_sessions 
                SET is_active = 0, waktu_selesai = NOW()
                WHERE course_id = ? AND is_active = 1
            ");
            $stmt->execute([$course_id]);
            
            return ['success' => true, 'affected' => $stmt->rowCount()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Cek sesi presensi aktif - DARI DATABASE
     */
    public function cekSesiPresensi($course_id = null) {
        $sql = "
            SELECT ps.*, c.kode_mk, c.nama_mk
            FROM presensi_sessions ps
            JOIN courses c ON ps.course_id = c.id
            WHERE ps.is_active = 1 
            AND ps.waktu_mulai > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ";
        $params = [];
        
        if ($course_id) {
            $sql .= " AND ps.course_id = ?";
            $params[] = $course_id;
        } else {
            $sql .= " AND ps.dosen_id = ?";
            $params[] = $this->dosen_id;
        }
        
        $sql .= " ORDER BY ps.waktu_mulai DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Cek semua sesi aktif untuk dosen
     */
    public function getAllActiveSessions() {
        $stmt = $this->pdo->prepare("
            SELECT ps.*, c.kode_mk, c.nama_mk
            FROM presensi_sessions ps
            JOIN courses c ON ps.course_id = c.id
            WHERE ps.dosen_id = ? 
                AND ps.is_active = 1
                AND ps.waktu_mulai > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ORDER BY ps.waktu_mulai DESC
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // INPUT NILAI
    // ============================================
    
    public function getMahasiswaForNilai($course_id, $semester) {
        $stmt = $this->pdo->prepare("
            SELECT 
                m.id as mahasiswa_id,
                u.nama as mahasiswa_nama,
                m.nim,
                g.id as grade_id,
                g.nilai_tugas,
                g.nilai_uts,
                g.nilai_uas,
                g.nilai_akhir,
                g.status_verifikasi,
                g.catatan
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            JOIN irs ON m.id = irs.mahasiswa_id
            LEFT JOIN grades g ON m.id = g.mahasiswa_id 
                AND g.course_id = ? 
                AND g.semester = ?
            WHERE irs.course_id = ? 
                AND irs.status = 'approved'
                AND irs.semester = ?
            GROUP BY m.id
            ORDER BY m.nim ASC
        ");
        $stmt->execute([$course_id, $semester, $course_id, $semester]);
        return $stmt->fetchAll();
    }
    
    public function inputNilaiMassal($data) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($data['nilai'] as $mahasiswa_id => $nilai) {
                $nilai_tugas = floatval($nilai['tugas'] ?? 0);
                $nilai_uts = floatval($nilai['uts'] ?? 0);
                $nilai_uas = floatval($nilai['uas'] ?? 0);
                $nilai_akhir = ($nilai_tugas * 0.3) + ($nilai_uts * 0.3) + ($nilai_uas * 0.4);
                
                // Simpan ulang akan menghapus catatan pengembalian admin (nilai diperbaiki dosen)
                
                // Nilai otomatis terverifikasi, KECUALI ada komponen bernilai 0
                // (nilai 0 dianggap perlu konfirmasi Admin sebelum tampil ke mahasiswa)
                $status_verifikasi = ($nilai_tugas == 0 || $nilai_uts == 0 || $nilai_uas == 0) 
                    ? 'draft' 
                    : 'verified';
                
                $stmt = $this->pdo->prepare("
                    SELECT id FROM grades 
                    WHERE mahasiswa_id = ? AND course_id = ? AND semester = ?
                ");
                $stmt->execute([$mahasiswa_id, $data['course_id'], $data['semester']]);
                
                if ($stmt->fetch()) {
                    $stmt = $this->pdo->prepare("
                        UPDATE grades SET
                            nilai_tugas = ?,
                            nilai_uts = ?,
                            nilai_uas = ?,
                            nilai_akhir = ?,
                            status_verifikasi = ?,
                            catatan = NULL
                        WHERE mahasiswa_id = ? AND course_id = ? AND semester = ?
                    ");
                    $stmt->execute([
                        $nilai_tugas, $nilai_uts, $nilai_uas, $nilai_akhir, $status_verifikasi,
                        $mahasiswa_id, $data['course_id'], $data['semester']
                    ]);
                } else {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO grades (
                            mahasiswa_id, course_id, semester,
                            nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, status_verifikasi
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $mahasiswa_id, $data['course_id'], $data['semester'],
                        $nilai_tugas, $nilai_uts, $nilai_uas, $nilai_akhir, $status_verifikasi
                    ]);
                }
            }
            
            $this->pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // MANAJEMEN TUGAS
    // ============================================
    
    public function getAllTugas() {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.kode_mk, c.nama_mk,
                   COUNT(DISTINCT ts.mahasiswa_id) as jumlah_submit
            FROM tasks t
            JOIN courses c ON t.course_id = c.id
            LEFT JOIN task_submissions ts ON t.id = ts.task_id
            WHERE t.dosen_id = ?
            GROUP BY t.id
            ORDER BY t.deadline ASC
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
    
    public function getTugasById($id) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.kode_mk, c.nama_mk
            FROM tasks t
            JOIN courses c ON t.course_id = c.id
            WHERE t.id = ? AND t.dosen_id = ?
        ");
        $stmt->execute([$id, $this->dosen_id]);
        return $stmt->fetch();
    }
    
    public function getSubmissions($task_id) {
        $stmt = $this->pdo->prepare("
            SELECT ts.*, u.nama as mahasiswa_nama, m.nim
            FROM task_submissions ts
            JOIN mahasiswa m ON ts.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            WHERE ts.task_id = ?
            ORDER BY m.nim ASC
        ");
        $stmt->execute([$task_id]);
        return $stmt->fetchAll();
    }
    
    public function createTugas($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO tasks (course_id, dosen_id, judul, deskripsi, deadline, bobot_nilai)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['course_id'],
                $this->dosen_id,
                trim($data['judul']),
                trim($data['deskripsi']),
                $data['deadline'],
                floatval($data['bobot_nilai'] ?? 0)
            ]);
            
            return ['success' => true, 'id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteTugas($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ? AND dosen_id = ?");
            $stmt->execute([$id, $this->dosen_id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function nilaiSubmission($submission_id, $nilai) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE task_submissions 
                SET nilai = ?, status = 'submitted'
                WHERE id = ?
            ");
            $stmt->execute([floatval($nilai), $submission_id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // BROADCAST
    // ============================================
    
    public function sendBroadcast($data) {
        try {
            $stmtUser = $this->pdo->prepare("SELECT user_id FROM dosen WHERE id = ?");
            $stmtUser->execute([$this->dosen_id]);
            $user = $stmtUser->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User dosen tidak ditemukan'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (sender_id, sender_role, target_role, judul, pesan, priority)
                VALUES (?, 'dosen', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['user_id'],
                $data['target_role'],
                trim($data['judul']),
                trim($data['pesan']),
                $data['priority'] ?? 'medium'
            ]);
            
            return ['success' => true, 'id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Kirim broadcast pengumuman via email ke mahasiswa sesuai target.
     * Dipanggil dari public/dosen/broadcast.php (checkbox "kirim via email")
     * setelah pengumuman tersimpan di tabel announcements.
     */
    public function kirimBroadcastViaEmail($announcement_id) {
        try {
            // Ambil data pengumuman
            $stmt = $this->pdo->prepare("
                SELECT a.*, u.nama as sender_name
                FROM announcements a
                JOIN users u ON a.sender_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$announcement_id]);
            $announcement = $stmt->fetch();
            
            if (!$announcement) {
                return ['success' => false, 'sent' => 0, 'failed' => 0, 'message' => 'Pengumuman tidak ditemukan'];
            }
            
            // Penerima: mahasiswa sesuai target (target_role dosen = mahasiswa kelas dosen ini)
            if ($announcement['target_role'] === 'mahasiswa') {
                // Semua mahasiswa yang diampu dosen ini (IRS approved pada MK dosen)
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT u.nama, u.email
                    FROM users u
                    JOIN mahasiswa m ON m.user_id = u.id
                    JOIN irs i ON i.mahasiswa_id = m.id AND i.status = 'approved'
                    JOIN jadwal jd ON jd.course_id = i.course_id
                    JOIN dosen d ON d.id = jd.dosen_id
                    WHERE d.id = ? AND u.email IS NOT NULL AND u.email != ''
                ");
                $stmt->execute([$this->dosen_id]);
            } else {
                // 'all' atau role lain
                $sql = "SELECT nama, email FROM users WHERE role = 'mahasiswa' AND email IS NOT NULL AND email != ''";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
            }
            
            $recipients = $stmt->fetchAll();
            
            if (empty($recipients)) {
                return ['success' => true, 'sent' => 0, 'failed' => 0, 'message' => 'Tidak ada mahasiswa dengan email'];
            }
            
            require_once __DIR__ . '/../helper/EmailHelper.php';
            require_once __DIR__ . '/../template/email/broadcast.php';
            
            $emailHelper = new EmailHelper();
            $sent = 0;
            $failed = 0;
            
            foreach ($recipients as $r) {
                $body = renderBroadcastEmail([
                    'nama'        => $r['nama'],
                    'judul'       => $announcement['judul'],
                    'pesan'       => $announcement['pesan'],
                    'priority'    => $announcement['priority'],
                    'sender_name' => $announcement['sender_name'],
                    'sender_role' => $announcement['sender_role']
                ]);
                
                $result = $emailHelper->send(
                    $r['email'],
                    $r['nama'],
                    'Pengumuman: ' . $announcement['judul'],
                    $body
                );
                
                if ($result['success']) $sent++;
                else $failed++;
            }
            
            return [
                'success' => true,
                'sent'    => $sent,
                'failed'  => $failed,
                'message' => "Email terkirim ke {$sent} mahasiswa" . ($failed > 0 ? ", {$failed} gagal" : '')
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Kirim kode/token presensi aktif via email ke semua mahasiswa
     * yang mengambil mata kuliah tersebut (IRS approved).
     Taxahorn dan dipanggil dari public/dosen/presensi.php (tombol "Kirim Token via Email").
     */
    public function kirimTokenPresensiViaEmail($course_id) {
        try {
            // Ambil sesi presensi aktif untuk MK ini
            $stmt = $this->pdo->prepare("
                SELECT ps.*, c.kode_mk, c.nama_mk, u.nama as dosen_nama
                FROM presensi_sessions ps
                JOIN courses c ON ps.course_id = c.id
                JOIN dosen d ON d.id = ps.dosen_id
                JOIN users u ON u.id = d.user_id
                WHERE ps.course_id = ? AND ps.is_active = 1
                    AND ps.waktu_mulai > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ORDER BY ps.waktu_mulai DESC
                LIMIT 1
            ");
            $stmt->execute([$course_id]);
            $sesi = $stmt->fetch();
            
            if (!$sesi) {
                return ['success' => false, 'sent' => 0, 'failed' => 0, 'message' => 'Tidak ada sesi presensi aktif untuk MK ini'];
            }
            
            // Ambil daftar mahasiswa yang mengambil MK ini (IRS approved)
            $stmt = $this->pdo->prepare("
                SELECT u.nama, u.email
                FROM users u
                JOIN mahasiswa m ON m.user_id = u.id
                JOIN irs i ON i.mahasiswa_id = m.id AND i.status = 'approved'
                WHERE i.course_id = ?
                    AND u.email IS NOT NULL AND u.email != ''
            ");
            $stmt->execute([$course_id]);
            $recipients = $stmt->fetchAll();
            
            if (empty($recipients)) {
                return ['success' => true, 'sent' => 0, 'failed' => 0, 'message' => 'Tidak ada mahasiswa dengan email di MK ini'];
            }
            
            require_once __DIR__ . '/../helper/EmailHelper.php';
            require_once __DIR__ . '/../template/email/token_presensi.php';
            
            $emailHelper = new EmailHelper();
            $sent = 0;
            $failed = 0;
            
            foreach ($recipients as $r) {
                $body = renderTokenPresensi([
                    'nama'       => $r['nama'],
                    'kode_mk'    => $sesi['kode_mk'],
                    'nama_mk'    => $sesi['nama_mk'],
                    'kode'       => $sesi['kode'],
                    'tanggal'    => date('d-m-Y', strtotime($sesi['tanggal'])),
                    'dosen_nama' => $sesi['dosen_nama']
                ]);
                
                $result = $emailHelper->send(
                    $r['email'],
                    $r['nama'],
                    'Token Presensi ' . $sesi['kode_mk'] . ' - Kode: ' . $sesi['kode'],
                    $body
                );
                
                if ($result['success']) $sent++;
                else $failed++;
            }
            
            return [
                'success' => true,
                'sent'    => $sent,
                'failed'  => $failed,
                'message' => "Token terkirim ke {$sent} mahasiswa" . ($failed > 0 ? ", {$failed} gagal" : '')
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'message' => $e->getMessage()];
        }
    }
    
    public function getBroadcastHistory() {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.nama as sender_name
            FROM announcements a
            JOIN users u ON a.sender_id = u.id
            WHERE a.sender_id = (SELECT user_id FROM dosen WHERE id = ?)
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
    
    public function getReceivedAnnouncements() {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.nama as sender_name, u.role as sender_role,
                   CASE WHEN ar.id IS NOT NULL THEN true ELSE false END as is_read
            FROM announcements a
            JOIN users u ON a.sender_id = u.id
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = (SELECT user_id FROM dosen WHERE id = ?)
            WHERE a.target_role IN ('all', 'dosen')
            ORDER BY a.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$this->dosen_id]);
        return $stmt->fetchAll();
    }
}
?>