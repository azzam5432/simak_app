<?php
// ============================================
// controllers/MahasiswaController.php
// Logika dashboard, jadwal, IRS, KHS, tugas, presensi
// ============================================

class MahasiswaController {
    private $pdo;
    private $mahasiswa_id;
    
    public function __construct($pdo, $mahasiswa_id = null) {
        $this->pdo = $pdo;
        $this->mahasiswa_id = $mahasiswa_id;
    }
    
    // ============================================
    // DASHBOARD
    // ============================================
    
    public function getDashboardStats() {
        $stats = [];
        
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(c.sks), 0) as total_sks
            FROM irs
            JOIN courses c ON irs.course_id = c.id
            WHERE irs.mahasiswa_id = ? AND irs.status = 'approved'
        ");
        $stmt->execute([$this->mahasiswa_id]);
        $stats['total_sks'] = $stmt->fetch()['total_sks'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total
            FROM irs
            WHERE mahasiswa_id = ? AND status = 'approved'
        ");
        $stmt->execute([$this->mahasiswa_id]);
        $stats['total_matakuliah'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total
            FROM tasks t
            JOIN irs i ON t.course_id = i.course_id
            WHERE i.mahasiswa_id = ? 
                AND t.deadline > NOW() 
                AND t.deadline < DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND i.status = 'approved'
        ");
        $stmt->execute([$this->mahasiswa_id]);
        $stats['tugas_mendatang'] = $stmt->fetch()['total'] ?? 0;
        
        $stats['ipk'] = $this->hitungIPK();
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total
            FROM announcements a
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = (SELECT user_id FROM mahasiswa WHERE id = ?)
            WHERE a.target_role IN ('all', 'mahasiswa')
                AND ar.id IS NULL
        ");
        $stmt->execute([$this->mahasiswa_id]);
        $stats['belum_dibaca'] = $stmt->fetch()['total'] ?? 0;
        
        return $stats;
    }
    
    public function getAnnouncements($limit = 5) {
        $limit = intval($limit);
        if ($limit < 1) $limit = 5;
        
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.nama as sender_name, u.role as sender_role,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM announcements a
            JOIN users u ON a.sender_id = u.id
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = (SELECT user_id FROM mahasiswa WHERE id = ?)
            WHERE a.target_role IN ('all', 'mahasiswa')
            ORDER BY a.created_at DESC
            LIMIT " . $limit . "
        ");
        $stmt->execute([$this->mahasiswa_id]);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // JADWAL
    // ============================================
    
    public function getJadwalKuliah() {
        $stmt = $this->pdo->prepare("
            SELECT jd.id as jadwal_id, jd.hari, jd.jam_mulai, jd.jam_selesai, jd.ruang,
                   jd.tingkat, jd.rombel,
                   c.kode_mk, c.nama_mk, c.sks, c.semester,
                   u.nama as dosen_nama, d.nid
            FROM irs
            JOIN jadwal jd ON jd.course_id = irs.course_id
            JOIN courses c ON jd.course_id = c.id
            LEFT JOIN dosen d ON jd.dosen_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE irs.mahasiswa_id = ? AND irs.status = 'approved'
            ORDER BY FIELD(jd.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jd.jam_mulai
        ");
        $stmt->execute([$this->mahasiswa_id]);
        return $stmt->fetchAll();
    }
    
    public function getJadwalHariIni() {
        $hari = $this->getHariIndonesia(date('N'));
        $stmt = $this->pdo->prepare("
            SELECT jd.hari, jd.jam_mulai, jd.jam_selesai, jd.ruang,
                   c.kode_mk, c.nama_mk, c.sks,
                   u.nama as dosen_nama
            FROM irs
            JOIN jadwal jd ON jd.course_id = irs.course_id
            JOIN courses c ON jd.course_id = c.id
            LEFT JOIN dosen d ON jd.dosen_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE irs.mahasiswa_id = ? 
                AND irs.status = 'approved'
                AND jd.hari = ?
            ORDER BY jd.jam_mulai ASC
        ");
        $stmt->execute([$this->mahasiswa_id, $hari]);
        return $stmt->fetchAll();
    }
    
    private function getHariIndonesia($dayNumber) {
        $hari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 
                 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        return $hari[$dayNumber] ?? 'Senin';
    }
    
    // ============================================
    // TUGAS
    // ============================================
    
    public function getAllTugas() {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.kode_mk, c.nama_mk,
                   ts.id as submission_id, ts.status as submission_status,
                   ts.nilai as submission_nilai, ts.submitted_at,
                   CASE 
                       WHEN t.deadline < NOW() THEN 'expired'
                       WHEN ts.id IS NOT NULL AND ts.status = 'submitted' THEN 'submitted'
                       WHEN t.deadline < DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'urgent'
                       ELSE 'active'
                   END as status_tugas,
                   TIMESTAMPDIFF(HOUR, NOW(), t.deadline) as jam_tersisa
            FROM tasks t
            JOIN courses c ON t.course_id = c.id
            JOIN irs i ON t.course_id = i.course_id
            LEFT JOIN task_submissions ts ON t.id = ts.task_id AND ts.mahasiswa_id = ?
            WHERE i.mahasiswa_id = ? AND i.status = 'approved'
            ORDER BY t.deadline ASC
        ");
        $stmt->execute([$this->mahasiswa_id, $this->mahasiswa_id]);
        return $stmt->fetchAll();
    }
    
    public function getTugasMendekat() {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.kode_mk, c.nama_mk,
                   ts.id as submission_id, ts.status as submission_status,
                   TIMESTAMPDIFF(HOUR, NOW(), t.deadline) as jam_tersisa
            FROM tasks t
            JOIN courses c ON t.course_id = c.id
            JOIN irs i ON t.course_id = i.course_id
            LEFT JOIN task_submissions ts ON t.id = ts.task_id AND ts.mahasiswa_id = ?
            WHERE i.mahasiswa_id = ? 
                AND i.status = 'approved'
                AND t.deadline > NOW()
                AND t.deadline < DATE_ADD(NOW(), INTERVAL 2 DAY)
                AND (ts.id IS NULL OR ts.status != 'submitted')
            ORDER BY t.deadline ASC
        ");
        $stmt->execute([$this->mahasiswa_id, $this->mahasiswa_id]);
        return $stmt->fetchAll();
    }
    
    public function submitTugas($task_id, $file_path = null, $catatan = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM task_submissions 
                WHERE task_id = ? AND mahasiswa_id = ?
            ");
            $stmt->execute([$task_id, $this->mahasiswa_id]);
            
            if ($stmt->fetch()) {
                $sql = "UPDATE task_submissions SET status = 'submitted', submitted_at = NOW()";
                $params = [];
                
                if ($file_path) {
                    $sql .= ", file_path = ?";
                    $params[] = $file_path;
                }
                if ($catatan) {
                    $sql .= ", catatan = ?";
                    $params[] = $catatan;
                }
                
                $sql .= " WHERE task_id = ? AND mahasiswa_id = ?";
                $params[] = $task_id;
                $params[] = $this->mahasiswa_id;
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO task_submissions (task_id, mahasiswa_id, file_path, catatan, status)
                    VALUES (?, ?, ?, ?, 'submitted')
                ");
                $stmt->execute([$task_id, $this->mahasiswa_id, $file_path, $catatan]);
            }
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // IRS
    // ============================================
    
    public function getIRS($semester = null) {
        $sql = "
            SELECT irs.*, c.kode_mk, c.nama_mk, c.sks, u.nama as dosen_nama,
                   (SELECT GROUP_CONCAT(CONCAT(jd.hari, ' ', DATE_FORMAT(jd.jam_mulai, '%H:%i'), '-', DATE_FORMAT(jd.jam_selesai, '%H:%i')) SEPARATOR ', ')
                    FROM jadwal jd WHERE jd.course_id = irs.course_id) as jadwal_info
            FROM irs
            JOIN courses c ON irs.course_id = c.id
            LEFT JOIN dosen d ON d.id = (
                SELECT jd2.dosen_id FROM jadwal jd2
                WHERE jd2.course_id = irs.course_id AND jd2.dosen_id IS NOT NULL
                LIMIT 1
            )
            LEFT JOIN users u ON d.user_id = u.id
            WHERE irs.mahasiswa_id = ?
        ";
        $params = [$this->mahasiswa_id];
        
        if ($semester) {
            $sql .= " AND irs.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY c.kode_mk ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Paket pembelajaran: seluruh mata kuliah untuk jurusan mahasiswa pada
     * semester tertentu. Lintas-jurusan ditandai dengan flag is_lintas_jurusan.
     */
    public function getPaketBySemester($semester) {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.kode_mk, c.nama_mk, c.sks,
                   jd.hari, jd.jam_mulai, jd.jam_selesai, jd.ruang,
                   j.kode as jurusan_kode, j.nama as jurusan_nama,
                   c.jurusan_id = (SELECT jurusan_id FROM mahasiswa WHERE id = ?) as is_own,
                   EXISTS (SELECT 1 FROM irs WHERE irs.course_id = c.id AND irs.mahasiswa_id = ? AND irs.semester = ?) as sudah_diambil
            FROM courses c
            LEFT JOIN jurusan j ON j.id = c.jurusan_id
            LEFT JOIN jadwal jd ON jd.id = (
                SELECT jd2.id FROM jadwal jd2 WHERE jd2.course_id = c.id ORDER BY jd2.id LIMIT 1
            )
            WHERE c.semester = ?
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute([
            $this->mahasiswa_id,
            $this->mahasiswa_id,
            $semester,
            $semester
        ]);
        $rows = $stmt->fetchAll();
        return array_map(function ($r) {
            $r['sudah_diambil'] = (bool) $r['sudah_diambil'];
            return $r;
        }, $rows);
    }
    
    public function getTotalSksIRS($semester) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(c.sks), 0) as total_sks
            FROM irs
            JOIN courses c ON irs.course_id = c.id
            WHERE irs.mahasiswa_id = ? AND irs.semester = ?
        ");
        $stmt->execute([$this->mahasiswa_id, $semester]);
        $result = $stmt->fetch();
        return $result['total_sks'] ?? 0;
    }
    
    public function getIRSStatus($semester) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT status 
            FROM irs 
            WHERE mahasiswa_id = ? AND semester = ?
            LIMIT 1
        ");
        $stmt->execute([$this->mahasiswa_id, $semester]);
        $result = $stmt->fetch();
        return $result['status'] ?? null;
    }
    
    /**
     * Validasi apakah mahasiswa boleh mengelola IRS pada semester tertentu.
     * Aturan: hanya semester AKTIF mahasiswa (kolom `semester` di tabel mahasiswa)
     * yang boleh diisi/diubah. Semester lain = arsip, baca-saja.
     */
    public function validateAksesSemester($semester) {
        $stmt = $this->pdo->prepare("SELECT semester, tingkat FROM mahasiswa WHERE id = ?");
        $stmt->execute([$this->mahasiswa_id]);
        $m = $stmt->fetch();

        if (!$m) {
            return ['boleh' => false, 'alasan' => 'Data mahasiswa tidak ditemukan'];
        }

        $semester = intval($semester);
        $semester_aktif = intval($m['semester']);

        if ($semester !== $semester_aktif) {
            return [
                'boleh' => false,
                'alasan' => "IRS hanya dapat diisi pada semester aktif Anda (Semester {$semester_aktif}).",
                'semester_aktif' => $semester_aktif,
                'tingkat' => intval($m['tingkat']),
            ];
        }

        // Semester aktif harus konsisten dengan tingkat (semester = 2*tingkat - 1)
        // untuk program reguler: tingkat 1 -> sem 1, tingkat 2 -> sem 3, dst.
        $tingkat = intval($m['tingkat']);
        $semester_valid_tingkat = (2 * $tingkat) - 1;

        return [
            'boleh' => true,
            'semester_aktif' => $semester_aktif,
            'tingkat' => $tingkat,
            'semester_valid_tingkat' => $semester_valid_tingkat,
            'peringatan_tingkat' => ($semester_aktif !== $semester_valid_tingkat)
                ? "Semester aktif ({$semester_aktif}) tidak sesuai tingkat ({$tingkat}) — biasanya tingkat {$tingkat} berada pada semester {$semester_valid_tingkat}."
                : null,
        ];
    }

    /**
     * Daftar mata kuliah dalam paket untuk jurusan mahasiswa + semester
     * (hanya yang belum diambil di IRS semester tsb).
     */
    public function getPaketCourses($semester) {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.kode_mk, c.nama_mk, c.sks
            FROM courses c
            WHERE c.semester = ?
                AND c.jurusan_id = (SELECT jurusan_id FROM mahasiswa WHERE id = ?)
                AND c.id NOT IN (
                    SELECT course_id FROM irs
                    WHERE mahasiswa_id = ? AND semester = ?
                )
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute([$semester, $this->mahasiswa_id, $this->mahasiswa_id, $semester]);
        return $stmt->fetchAll();
    }
    
    /**
     * Tambahkan seluruh paket pembelajaran (jurusan + semester) ke IRS.
     * Transaksional: semua masuk atau tidak sama sekali.
     */
    public function createIRSFromPaket($semester, $maxSks = 24) {
        try {
            // Semester harus semester aktif mahasiswa
            $akses = $this->validateAksesSemester($semester);
            if (!$akses['boleh']) {
                return ['success' => false, 'message' => $akses['alasan']];
            }

            $courses = $this->getPaketCourses($semester);
            
            if (empty($courses)) {
                return ['success' => false, 'message' => 'Semua mata kuliah paket sudah ada di IRS, atau paket belum tersedia'];
            }
            
            $total_sks = (int) $this->getTotalSksIRS($semester);
            $paket_sks = array_sum(array_map(function ($c) { return (int) $c['sks']; }, $courses));
            
            if ($total_sks + $paket_sks > $maxSks) {
                return ['success' => false, 'message' =>
                    "Total SKS akan melebihi batas maksimal ({$total_sks} + {$paket_sks} > {$maxSks} SKS)"];
            }
            
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO irs (mahasiswa_id, course_id, semester, status)
                SELECT ?, c.id, ?, 'pending'
                FROM courses c
                WHERE c.id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM irs
                    WHERE mahasiswa_id = ? AND course_id = c.id AND semester = ?
                )
            ");
            
            $added = 0;
            foreach ($courses as $c) {
                $stmt->execute([
                    $this->mahasiswa_id,
                    $semester,
                    $c['id'],
                    $this->mahasiswa_id,
                    $semester
                ]);
                $added += $stmt->rowCount();
            }
            
            $this->pdo->commit();
            
            return ['success' => true, 'added' => $added, 'sks' => $paket_sks];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteIRS($irs_id) {
        try {
            // Hanya boleh menghapus IRS pada semester aktif
            $stmt = $this->pdo->prepare("
                SELECT semester FROM irs WHERE id = ? AND mahasiswa_id = ?
            ");
            $stmt->execute([$irs_id, $this->mahasiswa_id]);
            $row = $stmt->fetch();

            if (!$row) {
                return ['success' => false, 'message' => 'Data IRS tidak ditemukan'];
            }

            $akses = $this->validateAksesSemester($row['semester']);
            if (!$akses['boleh']) {
                return ['success' => false, 'message' => $akses['alasan']];
            }

            $stmt = $this->pdo->prepare("
                DELETE FROM irs 
                WHERE id = ? AND mahasiswa_id = ? AND status = 'pending'
            ");
            $stmt->execute([$irs_id, $this->mahasiswa_id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getIRSForPrint($semester) {
        $stmt = $this->pdo->prepare("
            SELECT irs.*, 
                   c.kode_mk, c.nama_mk, c.sks,
                   u.nama as dosen_nama,
                   m.nim, j.nama as jurusan_nama, m.semester as mhs_semester,
                   us.nama as mahasiswa_nama
            FROM irs
            JOIN courses c ON irs.course_id = c.id
            LEFT JOIN jadwal jd ON jd.id = (
                SELECT jd2.id FROM jadwal jd2 WHERE jd2.course_id = c.id ORDER BY jd2.id LIMIT 1
            )
            LEFT JOIN dosen d ON jd.dosen_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            JOIN mahasiswa m ON irs.mahasiswa_id = m.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            JOIN users us ON m.user_id = us.id
            WHERE irs.mahasiswa_id = ? AND irs.semester = ?
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute([$this->mahasiswa_id, $semester]);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // KHS & IPK
    // ============================================
    
    public function getKHS($semester = null) {
        $sql = "
            SELECT g.*, c.kode_mk, c.nama_mk, c.sks,
                   (SELECT u2.nama
                    FROM jadwal jd2
                    LEFT JOIN dosen d2 ON jd2.dosen_id = d2.id
                    LEFT JOIN users u2 ON d2.user_id = u2.id
                    WHERE jd2.course_id = g.course_id AND u2.nama IS NOT NULL
                    LIMIT 1) as dosen_nama,
                   (SELECT COUNT(*) FROM presensi p
                    WHERE p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id) as total_pertemuan,
                   (SELECT COUNT(*) FROM presensi p
                    WHERE p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id AND p.status = 'hadir') as p_hadir,
                   (SELECT COUNT(*) FROM presensi p
                    WHERE p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id AND p.status = 'izin') as p_izin,
                   (SELECT COUNT(*) FROM presensi p
                    WHERE p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id AND p.status = 'sakit') as p_sakit,
                   (SELECT COUNT(*) FROM presensi p
                    WHERE p.course_id = g.course_id AND p.mahasiswa_id = g.mahasiswa_id AND p.status = 'alpa') as p_alpa
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            WHERE g.mahasiswa_id = ? AND g.status_verifikasi = 'verified'
        ";
        $params = [$this->mahasiswa_id];
        
        if ($semester) {
            $sql .= " AND g.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY c.kode_mk ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        // Gabungkan data absensi & nilai akhir yang sudah termasuk bobot kehadiran
        foreach ($rows as &$row) {
                $kehadiran = (intval($row['total_pertemuan']) > 0)
                    ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / intval($row['total_pertemuan'])) * 100
                    : null; // tidak ada data presensi -> netral
                
                $row['kehadiran_persen'] = ($kehadiran !== null) ? round($kehadiran, 2) : null;
                $row['nilai_final'] = self::hitungNilaiFinal(floatval($row['nilai_akhir']), $kehadiran);
            }
        unset($row);
        
        return $rows;
    }
    
    /**
     * Nilai akhir gabungan nilai komponen (75%) dan kehadiran (25%).
     * Kehadiran: hadir + izin + sakit dihitung hadir, alpa dianggap tidak hadir.
     * Jika tidak ada data presensi, nilai akhir asli dipakai apa adanya (netral).
     */
    public static function hitungNilaiFinal($nilai_akhir, $kehadiran_persen) {
        if ($kehadiran_persen === null)
            return $nilai_akhir;
        
        return round((0.75 * $nilai_akhir) + (0.25 * $kehadiran_persen), 2);
    }
    
    public function getSemestersWithGrades() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT semester 
            FROM grades 
            WHERE mahasiswa_id = ? AND status_verifikasi = 'verified'
            ORDER BY semester ASC
        ");
        $stmt->execute([$this->mahasiswa_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Rekap presensi per mata kuliah untuk mahasiswa ini.
     * Return: map[course_id] = ['hadir' => x, 'izin' => x, 'sakit' => x, 'alpa' => x, 'total' => x]
     */
    private function getRekapPresensiByCourse() {
        $stmt = $this->pdo->prepare("
            SELECT course_id,
                   SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                   SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                   SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                   SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as alpa,
                   COUNT(*) as total
            FROM presensi
            WHERE mahasiswa_id = ?
            GROUP BY course_id
        ");
        $stmt->execute([$this->mahasiswa_id]);
        
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[intval($row['course_id'])] = $row;
        }
        return $map;
    }
    
    public function hitungIPS($semester) {
        $stmt = $this->pdo->prepare("
            SELECT g.nilai_akhir, g.course_id, c.sks
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            WHERE g.mahasiswa_id = ? AND g.semester = ? AND g.status_verifikasi = 'verified'
        ");
        $stmt->execute([$this->mahasiswa_id, $semester]);
        $grades = $stmt->fetchAll();
        return $this->calculateGPA($grades);
    }
    
    public function hitungIPK() {
        $stmt = $this->pdo->prepare("
            SELECT g.nilai_akhir, g.course_id, c.sks
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            WHERE g.mahasiswa_id = ? AND g.status_verifikasi = 'verified'
        ");
        $stmt->execute([$this->mahasiswa_id]);
        $grades = $stmt->fetchAll();
        return $this->calculateGPA($grades);
    }
    
    /**
     * Hitung IPS/IPK. Nilai akhir gabungan nilai komponen (75%) dan kehadiran (25%):
     * kehadiran = (hadir + izin + sakit) / total pertemuan, alpa dianggap tidak hadir.
     * Mata kuliah tanpa data presensi memakai nilai akhir asli (netral, tidak di Award/penalti).
     */
    private function calculateGPA($grades) {
        $presensiMap = $this->getRekapPresensiByCourse();
        $totalSKS = 0;
        $totalBobot = 0;
        
        foreach ($grades as $row) {
            $nilai = floatval($row['nilai_akhir']);
            $sks = intval($row['sks']);
            
            $presensi = $presensiMap[intval($row['course_id'])] ?? null;
            $kehadiran = ($presensi && intval($presensi['total']) > 0)
                ? (($presensi['hadir'] + $presensi['izin'] + $presensi['sakit']) / $presensi['total']) * 100
                : null;
            $nilai = self::hitungNilaiFinal($nilai, $kehadiran);
            
            if ($nilai >= 85) $bobot = 4.0;
            elseif ($nilai >= 75) $bobot = 3.0;
            elseif ($nilai >= 65) $bobot = 2.0;
            elseif ($nilai >= 50) $bobot = 1.0;
            else $bobot = 0.0;
            
            $totalBobot += ($bobot * $sks);
            $totalSKS += $sks;
        }
        
        return ($totalSKS > 0) ? round($totalBobot / $totalSKS, 2) : 0;
    }
    
    public function getDetailIPK() {
        $semesters = $this->getSemestersWithGrades();
        $detail = [];
        
        foreach ($semesters as $s) {
            $semester = $s['semester'];
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM grades
                WHERE mahasiswa_id = ? AND semester = ? AND status_verifikasi = 'verified'
            ");
            $stmt->execute([$this->mahasiswa_id, $semester]);
            
            $detail[$semester] = [
                'ips' => $this->hitungIPS($semester),
                'total_sks' => $this->getTotalSKSBySemester($semester),
                'mata_kuliah' => intval($stmt->fetch()['total'])
            ];
        }
        
        return $detail;
    }
    
    private function getTotalSKSBySemester($semester) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(c.sks), 0) as total_sks
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            WHERE g.mahasiswa_id = ? AND g.semester = ? AND g.status_verifikasi = 'verified'
        ");
        $stmt->execute([$this->mahasiswa_id, $semester]);
        $result = $stmt->fetch();
        return $result['total_sks'] ?? 0;
    }
    
    // ============================================
    // PRESENSI
    // ============================================
    
    public function getRekapPresensi() {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.kode_mk,
                c.nama_mk,
                COUNT(p.id) as total_pertemuan,
                COALESCE(SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END), 0) as hadir,
                COALESCE(SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END), 0) as izin,
                COALESCE(SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END), 0) as sakit,
                COALESCE(SUM(CASE WHEN p.status = 'alpa' THEN 1 ELSE 0 END), 0) as alpa,
                COALESCE(ROUND(SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2), 0) as persentase
            FROM presensi p
            JOIN courses c ON p.course_id = c.id
            WHERE p.mahasiswa_id = ?
            GROUP BY p.course_id, c.kode_mk, c.nama_mk
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute([$this->mahasiswa_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Cek sesi presensi aktif untuk mahasiswa - DARI DATABASE
     */
    public function getActivePresensiSession() {
        $stmt = $this->pdo->prepare("
            SELECT ps.*, c.kode_mk, c.nama_mk, jd.ruang, jd.hari,
                   u.nama as dosen_nama
            FROM presensi_sessions ps
            JOIN courses c ON ps.course_id = c.id
            LEFT JOIN jadwal jd ON jd.course_id = ps.course_id
            LEFT JOIN dosen d ON ps.dosen_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE ps.is_active = 1 
                AND ps.tanggal = CURDATE()
                AND ps.waktu_mulai > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                AND ps.course_id IN (
                    SELECT course_id FROM irs 
                    WHERE mahasiswa_id = ? AND status = 'approved'
                )
            ORDER BY ps.waktu_mulai DESC
            LIMIT 1
        ");
        $stmt->execute([$this->mahasiswa_id]);
        return $stmt->fetch();
    }
    
    /**
     * Konfirmasi presensi - DARI DATABASE SESSION (SATU-SATUNYA VERSI)
     */
    public function konfirmasiPresensi($course_id, $kode) {
        try {
            // Pastikan mahasiswa terdaftar (IRS approved) di mata kuliah ini
            $stmt = $this->pdo->prepare("
                SELECT id FROM irs
                WHERE mahasiswa_id = ? AND course_id = ? AND status = 'approved'
            ");
            $stmt->execute([$this->mahasiswa_id, $course_id]);

            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Anda tidak terdaftar di mata kuliah ini'];
            }

            // Cek sesi aktif di database
            $stmt = $this->pdo->prepare("
                SELECT id, kode FROM presensi_sessions 
                WHERE course_id = ? 
                    AND is_active = 1 
                    AND kode = ?
                    AND tanggal = CURDATE()
                    AND waktu_mulai > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");
            $stmt->execute([$course_id, $kode]);
            $sesi = $stmt->fetch();
            
            if (!$sesi) {
                return ['success' => false, 'message' => 'Sesi presensi tidak aktif atau kode salah'];
            }
            
            // Cek apakah sudah presensi hari ini
            $tanggal = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT id FROM presensi 
                WHERE course_id = ? AND mahasiswa_id = ? AND tanggal = ?
            ");
            $stmt->execute([$course_id, $this->mahasiswa_id, $tanggal]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Anda sudah melakukan presensi hari ini'];
            }
            
            // Simpan presensi
            $stmt = $this->pdo->prepare("
                INSERT INTO presensi (course_id, mahasiswa_id, tanggal, status)
                VALUES (?, ?, ?, 'hadir')
            ");
            $stmt->execute([$course_id, $this->mahasiswa_id, $tanggal]);
            
            return ['success' => true, 'message' => 'Presensi berhasil!'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>