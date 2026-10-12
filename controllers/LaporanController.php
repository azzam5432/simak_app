<?php
// ============================================
// controllers/LaporanController.php
// Logika untuk semua laporan akademik
// ============================================

class LaporanController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // ============================================
    // STATISTIK UMUM
    // ============================================
    
    public function getStatistikUmum() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM mahasiswa");
        $stats['total_mahasiswa'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM dosen");
        $stats['total_dosen'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM courses");
        $stats['total_matakuliah'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COALESCE(AVG(nilai_akhir), 0) as rata FROM grades WHERE status_verifikasi = 'verified'");
        $stats['rata_nilai'] = round($stmt->fetch()['rata'], 2);
        
        return $stats;
    }
    
    // ============================================
    // HELPER: DROPDOWN DATA
    // ============================================
    
    public function getMatakuliahList() {
        $stmt = $this->pdo->query("
            SELECT c.id, c.kode_mk, c.nama_mk, c.sks, c.semester,
                   MIN(u.nama) as dosen_nama
            FROM courses c
            LEFT JOIN jadwal jd ON jd.course_id = c.id
            LEFT JOIN dosen d ON jd.dosen_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            GROUP BY c.id, c.kode_mk, c.nama_mk, c.sks, c.semester
            ORDER BY c.kode_mk ASC
        ");
        return $stmt->fetchAll();
    }
    
    public function getProgramStudiList() {
        $stmt = $this->pdo->query("
            SELECT j.id as jurusan_id, j.kode, j.nama as program_studi 
            FROM jurusan j
            ORDER BY j.nama ASC
        ");
        return $stmt->fetchAll();
    }
    
    public function getSemesterList() {
        $stmt = $this->pdo->query("
            SELECT DISTINCT semester 
            FROM irs 
            WHERE semester IS NOT NULL AND semester != ''
            ORDER BY semester DESC
        ");
        return $stmt->fetchAll();
    }
    
    // ============================================
    // LAPORAN NILAI
    // ============================================
    
    public function getLaporanNilai($course_id = null, $semester = null) {
        $sql = "
            SELECT 
                g.id, m.nim, u.nama as mahasiswa_nama, j.nama as program_studi,
                c.kode_mk, c.nama_mk, c.sks, g.semester,
                g.nilai_tugas, g.nilai_uts, g.nilai_uas, g.nilai_akhir,
                g.status_verifikasi
            FROM grades g
            JOIN mahasiswa m ON g.mahasiswa_id = m.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            JOIN users u ON m.user_id = u.id
            JOIN courses c ON g.course_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($course_id) {
            $sql .= " AND g.course_id = ?";
            $params[] = $course_id;
        }
        
        if ($semester) {
            $sql .= " AND g.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY c.kode_mk ASC, m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStatistikNilai($course_id = null) {
        $sql = "
            SELECT 
                c.kode_mk, c.nama_mk,
                COUNT(g.id) as total_mahasiswa,
                ROUND(AVG(g.nilai_akhir), 2) as rata_rata,
                MAX(g.nilai_akhir) as nilai_max,
                MIN(g.nilai_akhir) as nilai_min,
                SUM(CASE WHEN g.nilai_akhir >= 85 THEN 1 ELSE 0 END) as jumlah_a,
                SUM(CASE WHEN g.nilai_akhir >= 75 AND g.nilai_akhir < 85 THEN 1 ELSE 0 END) as jumlah_b,
                SUM(CASE WHEN g.nilai_akhir >= 65 AND g.nilai_akhir < 75 THEN 1 ELSE 0 END) as jumlah_c,
                SUM(CASE WHEN g.nilai_akhir >= 50 AND g.nilai_akhir < 65 THEN 1 ELSE 0 END) as jumlah_d,
                SUM(CASE WHEN g.nilai_akhir < 50 THEN 1 ELSE 0 END) as jumlah_e
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            WHERE g.status_verifikasi = 'verified'
        ";
        $params = [];
        
        if ($course_id) {
            $sql .= " AND g.course_id = ?";
            $params[] = $course_id;
        }
        
        $sql .= " GROUP BY c.id, c.kode_mk, c.nama_mk ORDER BY c.kode_mk ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // LAPORAN NILAI MAHASISWA PER SEMESTER
    // ============================================
    
    /**
     * Rekap nilai per mahasiswa: IPK (semua semester) dan IPS per semester.
     * Rumus konsisten dengan KHS mahasiswa: nilai komponen 75% + kehadiran 25%.
     */
    public function getRekapNilaiMahasiswa($mahasiswa_id = null, $semester = null) {
        $sql = "
            SELECT 
                g.id, g.mahasiswa_id, g.course_id, g.semester,
                c.kode_mk, c.nama_mk, c.sks,
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
            WHERE g.status_verifikasi = 'verified'
        ";
        $params = [];
        
        if ($mahasiswa_id) {
            $sql .= " AND g.mahasiswa_id = ?";
            $params[] = $mahasiswa_id;
        }
        
        if ($semester) {
            $sql .= " AND g.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY g.semester ASC, c.kode_mk ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * List mahasiswa untuk dropdown filter report nilai
     */
    public function getMahasiswaList() {
        $stmt = $this->pdo->query("
            SELECT m.id, m.nim, u.nama as mahasiswa_nama, j.nama as program_studi
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            ORDER BY m.nim ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Hitung bobot huruf & IPS/IPK dari kumpulan baris nilai.
     * Nilai komponen 75% + kehadiran 25% (hadir+izin+sakit dibagi total pertemuan).
     */
    public static function hitungNilaiFinal($nilai_akhir, $kehadiran_persen) {
        if ($kehadiran_persen === null) return $nilai_akhir;
        return round((0.75 * $nilai_akhir) + (0.25 * $kehadiran_persen), 2);
    }
    
    public static function nilaiKeBobot($nilai) {
        if ($nilai >= 85) return 4.0;
        elseif ($nilai >= 75) return 3.0;
        elseif ($nilai >= 65) return 2.0;
        elseif ($nilai >= 50) return 1.0;
        else return 0.0;
    }
    
    /**
     * Agregasi baris nilai (dari getRekapNilaiMahasiswa) menjadi IPS per semester.
     * Return: [semester => ['ips' => x, 'total_sks' => n, 'jumlah_mk' => n]]
     */
    public static function agregasiPerSemester($rows) {
        $result = [];
        foreach ($rows as $row) {
            $sem = $row['semester'];
            if (!isset($result[$sem])) {
                $result[$sem] = ['ips' => 0.0, 'total_sks' => 0, 'jumlah_mk' => 0, 'total_bobot' => 0.0];
            }
            
            $total_pertemuan = intval($row['total_pertemuan']);
            $kehadiran = ($total_pertemuan > 0)
                ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $total_pertemuan) * 100
                : null;
            $nilai_final = self::hitungNilaiFinal(floatval($row['nilai_akhir']), $kehadiran);
            $bobot = self::nilaiKeBobot($nilai_final);
            
            $result[$sem]['total_sks'] += intval($row['sks']);
            $result[$sem]['jumlah_mk'] += 1;
            $result[$sem]['total_bobot'] += ($bobot * intval($row['sks']));
        }
        
        foreach ($result as &$r) {
            $r['ips'] = ($r['total_sks'] > 0) ? round($r['total_bobot'] / $r['total_sks'], 2) : 0.0;
            unset($r['total_bobot']);
        }
        unset($r);
        
        ksort($result);
        return $result;
    }
    
    // ============================================
    // LAPORAN IRS
    // ============================================
    
    public function getLaporanIRS($semester = null, $status = null) {
        $sql = "
            SELECT 
                i.id, m.nim, u.nama as mahasiswa_nama, j.nama as program_studi,
                c.kode_mk, c.nama_mk, c.sks, i.semester, i.status,
                i.catatan, i.created_at
            FROM irs i
            JOIN mahasiswa m ON i.mahasiswa_id = m.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            JOIN users u ON m.user_id = u.id
            JOIN courses c ON i.course_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($semester) {
            $sql .= " AND i.semester = ?";
            $params[] = $semester;
        }
        
        if ($status) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY i.semester DESC, m.nim ASC, c.kode_mk ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStatistikIRS() {
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) as total FROM irs GROUP BY status
        ");
        return $stmt->fetchAll();
    }
    
    public function getRekapIRSPerMahasiswa($semester = null) {
        $sql = "
            SELECT 
                m.nim, u.nama as mahasiswa_nama, j.nama as program_studi,
                i.semester,
                COUNT(i.id) as jumlah_mk,
                SUM(c.sks) as total_sks,
                SUM(CASE WHEN i.status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM irs i
            JOIN mahasiswa m ON i.mahasiswa_id = m.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            JOIN users u ON m.user_id = u.id
            JOIN courses c ON i.course_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($semester) {
            $sql .= " AND i.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " GROUP BY m.id, m.nim, u.nama, j.nama, i.semester
                  ORDER BY i.semester DESC, m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // LAPORAN PRESENSI
    // ============================================
    
    public function getLaporanPresensi($course_id = null) {
        $sql = "
            SELECT 
                c.kode_mk, c.nama_mk, m.nim, u.nama as mahasiswa_nama,
                COUNT(p.id) as total_pertemuan,
                SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN p.status = 'alpa' THEN 1 ELSE 0 END) as alpa,
                ROUND(SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(p.id), 0), 2) as persentase_hadir
            FROM presensi p
            JOIN courses c ON p.course_id = c.id
            JOIN mahasiswa m ON p.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($course_id) {
            $sql .= " AND p.course_id = ?";
            $params[] = $course_id;
        }
        
        $sql .= " GROUP BY c.id, c.kode_mk, c.nama_mk, m.id, m.nim, u.nama
                  ORDER BY c.kode_mk ASC, m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStatistikPresensi($course_id = null) {
        $sql = "
            SELECT 
                c.kode_mk, c.nama_mk,
                COUNT(DISTINCT p.mahasiswa_id) as total_mahasiswa,
                COUNT(p.id) as total_presensi,
                SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN p.status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN p.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN p.status = 'alpa' THEN 1 ELSE 0 END) as alpa,
                ROUND(SUM(CASE WHEN p.status = 'hadir' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(p.id), 0), 2) as persentase
            FROM presensi p
            JOIN courses c ON p.course_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($course_id) {
            $sql .= " AND p.course_id = ?";
            $params[] = $course_id;
        }
        
        $sql .= " GROUP BY c.id, c.kode_mk, c.nama_mk ORDER BY c.kode_mk ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // LAPORAN MAHASISWA
    // ============================================
    
    public function getLaporanMahasiswa($jurusan_id = null) {
        $sql = "
            SELECT 
                m.nim, u.nama as mahasiswa_nama, u.email,
                j.nama as program_studi, m.tahun_ajaran as angkatan, m.semester,
                (
                    SELECT ROUND(SUM(
                        CASE 
                            WHEN g.nilai_akhir >= 85 THEN 4.0 * c.sks
                            WHEN g.nilai_akhir >= 75 THEN 3.0 * c.sks
                            WHEN g.nilai_akhir >= 65 THEN 2.0 * c.sks
                            WHEN g.nilai_akhir >= 50 THEN 1.0 * c.sks
                            ELSE 0
                        END
                    ) / NULLIF(SUM(c.sks), 0), 2)
                    FROM grades g
                    JOIN courses c ON g.course_id = c.id
                    WHERE g.mahasiswa_id = m.id AND g.status_verifikasi = 'verified'
                ) as ipk,
                (
                    SELECT COALESCE(SUM(c.sks), 0)
                    FROM grades g
                    JOIN courses c ON g.course_id = c.id
                    WHERE g.mahasiswa_id = m.id AND g.status_verifikasi = 'verified'
                ) as total_sks
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($jurusan_id) {
            $sql .= " AND m.jurusan_id = ?";
            $params[] = $jurusan_id;
        }
        
        $sql .= " ORDER BY j.nama ASC, m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>