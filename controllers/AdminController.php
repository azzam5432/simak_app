<?php
// ============================================
// controllers/AdminController.php
// Logika bisnis master data, approval, verifikasi
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

class AdminController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================
    // MANAJEMEN MAHASISWA (V2 - Fakultas/Jurusan)
    // ============================================
    
    /**
     * Get semua mahasiswa dengan join fakultas & jurusan
     */
    public function getAllMahasiswa() {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.nama,
                u.email,
                u.role,
                m.id as mahasiswa_id,
                m.nim,
                m.tahun_ajaran,
                m.tingkat,
                m.semester,
                m.ipk,
                f.id as fakultas_id,
                f.kode as fakultas_kode,
                f.nama as fakultas_nama,
                j.id as jurusan_id,
                j.kode as jurusan_kode,
                j.nama as jurusan_nama,
                j.jenjang
            FROM users u
            JOIN mahasiswa m ON u.id = m.user_id
            LEFT JOIN fakultas f ON m.fakultas_id = f.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            ORDER BY m.nim ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get mahasiswa by ID
     */
    public function getMahasiswaById($mahasiswa_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.nama,
                u.email,
                u.role,
                m.id as mahasiswa_id,
                m.nim,
                m.tahun_ajaran,
                m.tingkat,
                m.semester,
                m.ipk,
                m.fakultas_id,
                m.jurusan_id,
                f.kode as fakultas_kode,
                f.nama as fakultas_nama,
                j.kode as jurusan_kode,
                j.nama as jurusan_nama,
                j.jenjang
            FROM users u
            JOIN mahasiswa m ON u.id = m.user_id
            LEFT JOIN fakultas f ON m.fakultas_id = f.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            WHERE m.id = ?
        ");
        $stmt->execute([$mahasiswa_id]);
        return $stmt->fetch();
    }
    
    /**
     * Create mahasiswa baru
     * Semester otomatis dihitung dari tingkat + bulan
     */
    public function createMahasiswa($data) {
        try {
            $this->pdo->beginTransaction();
            
            $nim = trim(preg_replace('/[^a-zA-Z0-9]/', '', $data['nim']));
            $username = trim($data['username']);
            
            // Cek duplikat
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Username sudah digunakan!'];
            }
            
            $stmt = $this->pdo->prepare("SELECT id FROM mahasiswa WHERE nim = ?");
            $stmt->execute([$nim]);
            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'NIM sudah terdaftar!'];
            }
            
            // Hitung tahun ajaran & semester otomatis
            $tahun_ajaran = $data['tahun_ajaran'] ?? MasterDataController::getTahunAjaranOtomatis();
            $tingkat = intval($data['tingkat'] ?? 1);
            $semester = MasterDataController::getSemesterOtomatis($tingkat);
            
            // Insert ke users
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password, nama, nip_nim, email, role)
                VALUES (?, ?, ?, ?, ?, 'mahasiswa')
            ");
            $stmt->execute([
                $username,
                $hashedPassword,
                trim($data['nama']),
                $nim,
                trim($data['email'] ?? '')
            ]);
            $userId = $this->pdo->lastInsertId();
            
            // Insert ke mahasiswa
            $stmt = $this->pdo->prepare("
                INSERT INTO mahasiswa (
                    user_id, fakultas_id, jurusan_id, nim, 
                    tahun_ajaran, tingkat, semester
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                intval($data['fakultas_id']) ?: null,
                intval($data['jurusan_id']) ?: null,
                $nim,
                $tahun_ajaran,
                $tingkat,
                $semester
            ]);
            
            $this->pdo->commit();
            return ['success' => true, 'id' => $this->pdo->lastInsertId()];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update mahasiswa
     * Semester otomatis dihitung ulang jika tingkat berubah
     */
    public function updateMahasiswa($mahasiswa_id, $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Get user_id
            $stmt = $this->pdo->prepare("SELECT user_id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$mahasiswa_id]);
            $mahasiswa = $stmt->fetch();
            
            if (!$mahasiswa) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Mahasiswa tidak ditemukan'];
            }
            
            $userId = $mahasiswa['user_id'];
            $nim = trim(preg_replace('/[^a-zA-Z0-9]/', '', $data['nim']));
            
            // Update users
            $sql = "UPDATE users SET nama = ?, email = ?";
            $params = [trim($data['nama']), trim($data['email'] ?? '')];
            
            if (!empty($data['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Hitung semester otomatis
            $tingkat = intval($data['tingkat']);
            $semester = MasterDataController::getSemesterOtomatis($tingkat);
            
            // Update mahasiswa
            $stmt = $this->pdo->prepare("
                UPDATE mahasiswa 
                SET nim = ?, 
                    fakultas_id = ?, 
                    jurusan_id = ?, 
                    tahun_ajaran = ?, 
                    tingkat = ?, 
                    semester = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nim,
                intval($data['fakultas_id']) ?: null,
                intval($data['jurusan_id']) ?: null,
                trim($data['tahun_ajaran']),
                $tingkat,
                $semester,
                $mahasiswa_id
            ]);
            
            $this->pdo->commit();
            return ['success' => true];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete mahasiswa (sama seperti sebelumnya)
     */
    public function deleteMahasiswa($mahasiswa_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$mahasiswa_id]);
            $mahasiswa = $stmt->fetch();
            
            if (!$mahasiswa) {
                return ['success' => false, 'message' => 'Mahasiswa tidak ditemukan'];
            }
            
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("DELETE FROM mahasiswa WHERE id = ?");
            $stmt->execute([$mahasiswa_id]);
            
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$mahasiswa['user_id']]);
            
            $this->pdo->commit();
            return ['success' => true];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // MANAJEMEN DOSEN (REVISI)
    // ============================================
    
    /**
     * Get semua dosen dengan join fakultas & jurusan
     */
    public function getAllDosen() {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.nama,
                u.email,
                d.id as dosen_id,
                d.nid,
                d.fakultas_id,
                d.jurusan_id,
                f.nama as fakultas_nama,
                f.kode as fakultas_kode,
                j.nama as jurusan_nama,
                j.kode as jurusan_kode,
                j.jenjang
            FROM users u
            JOIN dosen d ON u.id = d.user_id
            LEFT JOIN fakultas f ON d.fakultas_id = f.id
            LEFT JOIN jurusan j ON d.jurusan_id = j.id
            WHERE u.role = 'dosen'
            ORDER BY d.nid ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get dosen by ID
     */
    public function getDosenById($dosen_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.nama,
                u.email,
                d.id as dosen_id,
                d.nid,
                d.fakultas_id,
                d.jurusan_id,
                f.nama as fakultas_nama,
                j.nama as jurusan_nama
            FROM users u
            JOIN dosen d ON u.id = d.user_id
            LEFT JOIN fakultas f ON d.fakultas_id = f.id
            LEFT JOIN jurusan j ON d.jurusan_id = j.id
            WHERE d.id = ? AND u.role = 'dosen'
        ");
        $stmt->execute([$dosen_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get fakultas list untuk dropdown
     */
    public function getFakultasList() {
        $stmt = $this->pdo->prepare("
            SELECT id, kode, nama 
            FROM fakultas 
            ORDER BY nama ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get jurusan list by fakultas
     */
    public function getJurusanByFakultas($fakultas_id) {
        $stmt = $this->pdo->prepare("
            SELECT id, kode, nama, jenjang 
            FROM jurusan 
            WHERE fakultas_id = ?
            ORDER BY nama ASC
        ");
        $stmt->execute([$fakultas_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Tambah dosen baru
     */
    public function createDosen($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Validasi input
            $username = trim($data['username']);
            $nid = trim($data['nid']);
            $nama = trim($data['nama']);
            $email = trim($data['email']);
            $fakultas_id = intval($data['fakultas_id']);
            $jurusan_id = intval($data['jurusan_id']);
            
            // Cek NID hanya angka
            if (!preg_match('/^[0-9]+$/', $nid)) {
                return ['success' => false, 'message' => 'NID hanya boleh berisi angka'];
            }
            
            // Cek duplikat username
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username sudah digunakan!'];
            }
            
            // Cek duplikat NID
            $stmt = $this->pdo->prepare("SELECT id FROM dosen WHERE nid = ?");
            $stmt->execute([$nid]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'NID sudah terdaftar!'];
            }
            
            // Cek duplikat email
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND email != ''");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email sudah digunakan!'];
            }
            
            // Insert ke users
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password, nama, nip_nim, email, role)
                VALUES (?, ?, ?, ?, ?, 'dosen')
            ");
            $stmt->execute([$username, $hashedPassword, $nama, $nid, $email]);
            $userId = $this->pdo->lastInsertId();
            
            // Insert ke dosen
            $stmt = $this->pdo->prepare("
                INSERT INTO dosen (user_id, nid, fakultas_id, jurusan_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $nid, $fakultas_id, $jurusan_id]);
            $dosenId = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'dosen_id' => $dosenId,
                'user_id' => $userId
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update dosen
     */
    public function updateDosen($dosen_id, $data) {
        try {
            $this->pdo->beginTransaction();
            
            // Get user_id
            $stmt = $this->pdo->prepare("SELECT user_id FROM dosen WHERE id = ?");
            $stmt->execute([$dosen_id]);
            $dosen = $stmt->fetch();
            
            if (!$dosen) {
                return ['success' => false, 'message' => 'Dosen tidak ditemukan'];
            }
            
            $userId = $dosen['user_id'];
            
            // Validasi input
            $nid = trim($data['nid']);
            $nama = trim($data['nama']);
            $email = trim($data['email']);
            $fakultas_id = intval($data['fakultas_id']);
            $jurusan_id = intval($data['jurusan_id']);
            
            // Cek NID hanya angka
            if (!preg_match('/^[0-9]+$/', $nid)) {
                return ['success' => false, 'message' => 'NID hanya boleh berisi angka'];
            }
            
            // Cek duplikat NID (selain diri sendiri)
            $stmt = $this->pdo->prepare("SELECT id FROM dosen WHERE nid = ? AND id != ?");
            $stmt->execute([$nid, $dosen_id]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'NID sudah digunakan dosen lain!'];
            }
            
            // Cek duplikat email (selain diri sendiri)
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND email != ''");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email sudah digunakan user lain!'];
            }
            
            // Update users
            $sql = "UPDATE users SET nama = ?, email = ?";
            $params = [$nama, $email];
            
            if (!empty($data['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            // Update dosen
            $stmt = $this->pdo->prepare("
                UPDATE dosen 
                SET nid = ?, fakultas_id = ?, jurusan_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$nid, $fakultas_id, $jurusan_id, $dosen_id]);
            
            $this->pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Hapus dosen
     */
    public function deleteDosen($dosen_id) {
        try {
            // Cek dosen ada
            $stmt = $this->pdo->prepare("SELECT user_id FROM dosen WHERE id = ?");
            $stmt->execute([$dosen_id]);
            $dosen = $stmt->fetch();
            
            if (!$dosen) {
                return ['success' => false, 'message' => 'Dosen tidak ditemukan'];
            }
            
            $userId = $dosen['user_id'];
            
            $this->pdo->beginTransaction();
            
            // Hapus dari dosen
            $stmt = $this->pdo->prepare("DELETE FROM dosen WHERE id = ?");
            $stmt->execute([$dosen_id]);
            
            // Hapus dari users
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $this->pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // MANAJEMEN MATA KULIAH
    // ============================================
    
    /**
     * Get semua mata kuliah dengan info jurusan
     */
    public function getAllMatakuliah() {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                j.kode as jurusan_kode,
                j.nama as jurusan_nama,
                j.jenjang,
                f.nama as fakultas_nama,
                (SELECT COUNT(*) FROM jadwal WHERE course_id = c.id) as total_kelas
            FROM courses c
            JOIN jurusan j ON c.jurusan_id = j.id
            JOIN fakultas f ON j.fakultas_id = f.id
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get mata kuliah by ID
     */
    public function getMatakuliahById($id) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, j.kode as jurusan_kode, j.nama as jurusan_nama
            FROM courses c
            JOIN jurusan j ON c.jurusan_id = j.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get jurusan list untuk dropdown
     */
    public function getJurusanList() {
        $stmt = $this->pdo->prepare("
            SELECT j.*, f.nama as fakultas_nama, f.kode as fakultas_kode
            FROM jurusan j
            JOIN fakultas f ON j.fakultas_id = f.id
            ORDER BY f.nama ASC, j.nama ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create mata kuliah
     */
    public function createMatakuliah($data) {
        try {
            $kode_mk = trim(strtoupper($data['kode_mk']));
            $nama_mk = trim($data['nama_mk']);
            $sks = intval($data['sks']);
            $semester = intval($data['semester']);
            $jurusan_id = intval($data['jurusan_id']);
            $deskripsi = trim($data['deskripsi'] ?? '');
            
            // Cek duplikat kode
            $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE kode_mk = ?");
            $stmt->execute([$kode_mk]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Kode mata kuliah sudah digunakan!'];
            }
            
            // Cek duplikat nama di jurusan yang sama
            $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE nama_mk = ? AND jurusan_id = ?");
            $stmt->execute([$nama_mk, $jurusan_id]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Nama mata kuliah sudah ada di jurusan ini!'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO courses (kode_mk, nama_mk, sks, semester, jurusan_id, deskripsi)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$kode_mk, $nama_mk, $sks, $semester, $jurusan_id, $deskripsi]);
            
            return ['success' => true, 'id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update mata kuliah
     */
    public function updateMatakuliah($id, $data) {
        try {
            $kode_mk = trim(strtoupper($data['kode_mk']));
            $nama_mk = trim($data['nama_mk']);
            $sks = intval($data['sks']);
            $semester = intval($data['semester']);
            $jurusan_id = intval($data['jurusan_id']);
            $deskripsi = trim($data['deskripsi'] ?? '');
            
            // Cek duplikat kode (selain diri sendiri)
            $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE kode_mk = ? AND id != ?");
            $stmt->execute([$kode_mk, $id]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Kode mata kuliah sudah digunakan!'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE courses SET
                    kode_mk = ?, nama_mk = ?, sks = ?, 
                    semester = ?, jurusan_id = ?, deskripsi = ?
                WHERE id = ?
            ");
            $stmt->execute([$kode_mk, $nama_mk, $sks, $semester, $jurusan_id, $deskripsi, $id]);
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete mata kuliah
     */
    public function deleteMatakuliah($id) {
        try {
            // Cek apakah masih dipakai di jadwal
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM jadwal WHERE course_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                return [
                    'success' => false, 
                    'message' => "Mata kuliah masih digunakan di {$total} jadwal. Hapus jadwal terlebih dahulu."
                ];
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // MANAJEMEN JADWAL
    // ============================================
    
    /**
     * Get semua jadwal dengan join lengkap
     */
    public function getAllJadwal($filter = []) {
        $sql = "
            SELECT 
                jd.*,
                c.kode_mk, c.nama_mk, c.sks, c.semester,
                d.nid,
                u.nama as dosen_nama,
                jr.kode as jurusan_kode,
                jr.nama as jurusan_nama,
                (SELECT COUNT(*) FROM irs WHERE course_id = jd.course_id AND status = 'approved') as total_pendaftar
            FROM jadwal jd
            JOIN courses c ON jd.course_id = c.id
            JOIN dosen d ON jd.dosen_id = d.id
            JOIN users u ON d.user_id = u.id
            JOIN jurusan jr ON c.jurusan_id = jr.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filter['course_id'])) {
            $sql .= " AND jd.course_id = ?";
            $params[] = $filter['course_id'];
        }
        
        if (!empty($filter['dosen_id'])) {
            $sql .= " AND jd.dosen_id = ?";
            $params[] = $filter['dosen_id'];
        }
        
        if (!empty($filter['tingkat'])) {
            $sql .= " AND jd.tingkat = ?";
            $params[] = $filter['tingkat'];
        }
        
        if (!empty($filter['hari'])) {
            $sql .= " AND jd.hari = ?";
            $params[] = $filter['hari'];
        }
        
        $sql .= " ORDER BY 
            FIELD(jd.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'),
            jd.jam_mulai ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get jadwal by ID
     */
    public function getJadwalById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                jd.*,
                c.kode_mk, c.nama_mk,
                u.nama as dosen_nama
            FROM jadwal jd
            JOIN courses c ON jd.course_id = c.id
            JOIN dosen d ON jd.dosen_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE jd.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Cek bentrok jadwal
     * 
     * @param array $data Data jadwal
     * @param int $exclude_id ID jadwal yang dikecualikan (untuk update)
     * @return array ['bentrok' => bool, 'message' => string]
     */
    public function cekBentrokJadwal($data, $exclude_id = null) {
        $hari = $data['hari'];
        $jam_mulai = $data['jam_mulai'];
        $jam_selesai = $data['jam_selesai'];
        $dosen_id = intval($data['dosen_id']);
        $ruang = trim($data['ruang'] ?? '');
        $tingkat = intval($data['tingkat']);
        $rombel = trim($data['rombel']);
        $tahun_ajaran = trim($data['tahun_ajaran'] ?? '');
        $course_id = intval($data['course_id']);
        
        // Query dasar: cek bentrok di hari yang sama
        $sql = "
            SELECT 
                jd.id,
                c.kode_mk, c.nama_mk,
                u.nama as dosen_nama,
                jd.ruang, jd.tingkat, jd.rombel,
                jd.jam_mulai, jd.jam_selesai
            FROM jadwal jd
            JOIN courses c ON jd.course_id = c.id
            JOIN dosen d ON jd.dosen_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE jd.hari = ?
            AND (
                (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                OR (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                OR (jd.jam_mulai >= ? AND jd.jam_selesai <= ?)
            )
        ";
        $params = [$hari, $jam_selesai, $jam_mulai, $jam_selesai, $jam_mulai, $jam_mulai, $jam_selesai];
        
        // Exclude ID saat update
        if ($exclude_id) {
            $sql .= " AND jd.id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $bentrok_list = $stmt->fetchAll();
        
        // Cek setiap bentrok
        foreach ($bentrok_list as $b) {
            // 1. Cek bentrok dosen
            if ($b['dosen_nama'] === $data['dosen_nama'] || 
                $this->getDosenIdByName($b['dosen_nama']) == $dosen_id) {
                
                // Cek apakah benar-benar dosen yang sama
                $stmt2 = $this->pdo->prepare("
                    SELECT COUNT(*) as total FROM jadwal jd
                    JOIN dosen d ON jd.dosen_id = d.id
                    JOIN users u ON d.user_id = u.id
                    WHERE jd.hari = ?
                    AND jd.id = ?
                    AND u.nama = (SELECT u2.nama FROM dosen d2 JOIN users u2 ON d2.user_id = u2.id WHERE d2.id = ?)
                ");
                // Skip, kita cek langsung
            }
        }
        
        // Cek bentrok dosen
        $stmt = $this->pdo->prepare("
            SELECT c.kode_mk, c.nama_mk, jd.jam_mulai, jd.jam_selesai, jd.ruang
            FROM jadwal jd
            JOIN courses c ON jd.course_id = c.id
            WHERE jd.dosen_id = ?
            AND jd.hari = ?
            AND (
                (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                OR (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                OR (jd.jam_mulai >= ? AND jd.jam_selesai <= ?)
            )
            " . ($exclude_id ? "AND jd.id != ?" : "") . "
            LIMIT 1
        ");
        $params = [$dosen_id, $hari, $jam_selesai, $jam_mulai, $jam_selesai, $jam_mulai, $jam_mulai, $jam_selesai];
        if ($exclude_id) $params[] = $exclude_id;
        $stmt->execute($params);
        $bentrok_dosen = $stmt->fetch();
        
        if ($bentrok_dosen) {
            return [
                'bentrok' => true,
                'message' => "Dosen sudah mengajar {$bentrok_dosen['kode_mk']} - {$bentrok_dosen['nama_mk']} pada jam " . 
                            date('H:i', strtotime($bentrok_dosen['jam_mulai'])) . "-" . 
                            date('H:i', strtotime($bentrok_dosen['jam_selesai']))
            ];
        }
        
        // Cek bentrok ruang
        if (!empty($ruang)) {
            $stmt = $this->pdo->prepare("
                SELECT c.kode_mk, c.nama_mk, jd.jam_mulai, jd.jam_selesai
                FROM jadwal jd
                JOIN courses c ON jd.course_id = c.id
                WHERE jd.ruang = ?
                AND jd.hari = ?
                AND (
                    (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                    OR (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                    OR (jd.jam_mulai >= ? AND jd.jam_selesai <= ?)
                )
                " . ($exclude_id ? "AND jd.id != ?" : "") . "
                LIMIT 1
            ");
            $params = [$ruang, $hari, $jam_selesai, $jam_mulai, $jam_selesai, $jam_mulai, $jam_mulai, $jam_selesai];
            if ($exclude_id) $params[] = $exclude_id;
            $stmt->execute($params);
            $bentrok_ruang = $stmt->fetch();
            
            if ($bentrok_ruang) {
                return [
                    'bentrok' => true,
                    'message' => "Ruang {$ruang} sudah dipakai {$bentrok_ruang['kode_mk']} - {$bentrok_ruang['nama_mk']} pada jam " . 
                                date('H:i', strtotime($bentrok_ruang['jam_mulai'])) . "-" . 
                                date('H:i', strtotime($bentrok_ruang['jam_selesai']))
                ];
            }
        }
        
        // Cek bentrok kelas (tingkat + rombel)
        if (!empty($tahun_ajaran)) {
            $stmt = $this->pdo->prepare("
                SELECT c.kode_mk, c.nama_mk, jd.jam_mulai, jd.jam_selesai
                FROM jadwal jd
                JOIN courses c ON jd.course_id = c.id
                WHERE jd.tingkat = ?
                AND jd.rombel = ?
                AND jd.tahun_ajaran = ?
                AND jd.hari = ?
                AND (
                    (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                    OR (jd.jam_mulai < ? AND jd.jam_selesai > ?)
                    OR (jd.jam_mulai >= ? AND jd.jam_selesai <= ?)
                )
                " . ($exclude_id ? "AND jd.id != ?" : "") . "
                LIMIT 1
            ");
            $params = [$tingkat, $rombel, $tahun_ajaran, $hari, $jam_selesai, $jam_mulai, $jam_selesai, $jam_mulai, $jam_mulai, $jam_selesai];
            if ($exclude_id) $params[] = $exclude_id;
            $stmt->execute($params);
            $bentrok_kelas = $stmt->fetch();
            
            if ($bentrok_kelas) {
                return [
                    'bentrok' => true,
                    'message' => "Kelas {$tingkat}{$rombel} sudah ada jadwal {$bentrok_kelas['kode_mk']} - {$bentrok_kelas['nama_mk']} pada jam " . 
                                date('H:i', strtotime($bentrok_kelas['jam_mulai'])) . "-" . 
                                date('H:i', strtotime($bentrok_kelas['jam_selesai']))
                ];
            }
        }
        
        return ['bentrok' => false, 'message' => ''];
    }
    
    /**
     * Helper: Get dosen_id by name
     */
    private function getDosenIdByName($nama) {
        $stmt = $this->pdo->prepare("
            SELECT d.id FROM dosen d
            JOIN users u ON d.user_id = u.id
            WHERE u.nama = ?
        ");
        $stmt->execute([$nama]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : 0;
    }
    
    /**
     * Create jadwal
     */
    public function createJadwal($data) {
        try {
            // Cek bentrok
            $bentrok = $this->cekBentrokJadwal($data);
            if ($bentrok['bentrok']) {
                return ['success' => false, 'message' => 'BENTROK! ' . $bentrok['message']];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO jadwal (
                    course_id, dosen_id, tingkat, rombel,
                    hari, jam_mulai, jam_selesai, ruang,
                    kapasitas, tahun_ajaran, semester_ke
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                intval($data['course_id']),
                intval($data['dosen_id']),
                intval($data['tingkat']),
                strtoupper(trim($data['rombel'])),
                trim($data['hari']),
                $data['jam_mulai'],
                $data['jam_selesai'],
                trim($data['ruang'] ?? ''),
                intval($data['kapasitas'] ?? 30),
                trim($data['tahun_ajaran'] ?? ''),
                intval($data['semester_ke'] ?? 1)
            ]);
            
            return ['success' => true, 'id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update jadwal
     */
    public function updateJadwal($id, $data) {
        try {
            // Cek bentrok (exclude diri sendiri)
            $bentrok = $this->cekBentrokJadwal($data, $id);
            if ($bentrok['bentrok']) {
                return ['success' => false, 'message' => 'BENTROK! ' . $bentrok['message']];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE jadwal SET
                    course_id = ?, dosen_id = ?, tingkat = ?, rombel = ?,
                    hari = ?, jam_mulai = ?, jam_selesai = ?, ruang = ?,
                    kapasitas = ?, tahun_ajaran = ?, semester_ke = ?
                WHERE id = ?
            ");
            $stmt->execute([
                intval($data['course_id']),
                intval($data['dosen_id']),
                intval($data['tingkat']),
                strtoupper(trim($data['rombel'])),
                trim($data['hari']),
                $data['jam_mulai'],
                $data['jam_selesai'],
                trim($data['ruang'] ?? ''),
                intval($data['kapasitas'] ?? 30),
                trim($data['tahun_ajaran'] ?? ''),
                intval($data['semester_ke'] ?? 1),
                $id
            ]);
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete jadwal
     */
    public function deleteJadwal($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM jadwal WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get daftar hari untuk dropdown
     */
    public function getHariList() {
        return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    }
    
    // ============================================
    // VALIDASI IRS
    // ============================================
    
    public function getPendingIRS() {
        $stmt = $this->pdo->prepare("
            SELECT irs.*, 
                   c.kode_mk, c.nama_mk, c.sks,
                   m.nim, j.nama as jurusan_nama, m.semester as mhs_semester,
                   u.nama as mahasiswa_nama
            FROM irs
            JOIN courses c ON irs.course_id = c.id
            JOIN mahasiswa m ON irs.mahasiswa_id = m.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            JOIN users u ON m.user_id = u.id
            WHERE irs.status = 'pending'
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function approveIRS($irs_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE irs SET status = 'approved', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$irs_id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function rejectIRS($irs_id, $catatan = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE irs SET status = 'rejected', catatan = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$catatan, $irs_id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function approveAllIRS($mahasiswa_id, $semester) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE irs 
                SET status = 'approved', updated_at = NOW()
                WHERE mahasiswa_id = ? AND semester = ? AND status = 'pending'
            ");
            $stmt->execute([$mahasiswa_id, $semester]);
            
            $this->pdo->commit();
            return ['success' => true, 'affected' => $stmt->rowCount()];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getIRSStats() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM irs WHERE status = 'pending'");
        $stats['pending'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM irs WHERE status = 'approved'");
        $stats['approved'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM irs WHERE status = 'rejected'");
        $stats['rejected'] = $stmt->fetch()['total'];
        
        return $stats;
    }
    
    // ============================================
    // VERIFIKASI NILAI
    // ============================================
    
    public function getDraftGrades() {
        $stmt = $this->pdo->prepare("
            SELECT g.*, 
                   c.kode_mk, c.nama_mk, c.sks,
                   u.nama as mahasiswa_nama, m.nim,
                   dosu.nama as dosen_nama
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            JOIN mahasiswa m ON g.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            LEFT JOIN dosen dos ON EXISTS (
                SELECT 1 FROM jadwal jd WHERE jd.course_id = c.id AND jd.dosen_id = dos.id
            )
            LEFT JOIN users dosu ON dos.user_id = dosu.id
            WHERE g.status_verifikasi = 'draft'
            ORDER BY g.created_at ASC, m.nim ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getDraftGradesByCourse($course_id) {
        $stmt = $this->pdo->prepare("
            SELECT g.*, 
                   u.nama as mahasiswa_nama, m.nim,
                   c.kode_mk, c.nama_mk, c.sks
            FROM grades g
            JOIN mahasiswa m ON g.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            JOIN courses c ON g.course_id = c.id
            WHERE g.course_id = ? AND g.status_verifikasi = 'draft'
            ORDER BY m.nim ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get satu data nilai by ID (untuk validasi sebelum verifikasi)
     */
    public function getGradeById($grade_id) {
        $stmt = $this->pdo->prepare("
            SELECT g.*, 
                   u.nama as mahasiswa_nama, m.nim,
                   c.kode_mk, c.nama_mk
            FROM grades g
            JOIN mahasiswa m ON g.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            JOIN courses c ON g.course_id = c.id
            WHERE g.id = ?
        ");
        $stmt->execute([$grade_id]);
        return $stmt->fetch();
    }
    
    /**
     * Verifikasi satu nilai
     * Validasi: grade harus ada & masih berstatus draft
     */
    public function verifyGrade($grade_id) {
        try {
            $grade_id = intval($grade_id);
            
            if ($grade_id <= 0) {
                return ['success' => false, 'message' => 'ID nilai tidak valid'];
            }
            
            $grade = $this->getGradeById($grade_id);
            if (!$grade) {
                return ['success' => false, 'message' => 'Data nilai tidak ditemukan'];
            }
            
            if ($grade['status_verifikasi'] !== 'draft') {
                return ['success' => false, 'message' => 'Nilai sudah diverifikasi sebelumnya'];
            }
            
            // Validasi: komponen nilai harus lengkap sebelum diverifikasi
            if ($grade['nilai_tugas'] === null || $grade['nilai_uts'] === null || $grade['nilai_uas'] === null) {
                return ['success' => false, 'message' => 'Nilai belum lengkap (tugas/UTS/UAS) dan tidak bisa diverifikasi'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE grades SET status_verifikasi = 'verified', updated_at = NOW()
                WHERE id = ? AND status_verifikasi = 'draft'
            ");
            $stmt->execute([$grade_id]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Gagal memverifikasi: status nilai sudah berubah'];
            }
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Verifikasi semua nilai draft pada satu mata kuliah
     * Validasi: course harus ada & ada nilai draft
     */
    public function verifyAllGrades($course_id) {
        try {
            $course_id = intval($course_id);
            
            if ($course_id <= 0) {
                return ['success' => false, 'message' => 'ID mata kuliah tidak valid'];
            }
            
            // Cek mata kuliah ada
            $stmt = $this->pdo->prepare("SELECT id, kode_mk, nama_mk FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
            
            if (!$course) {
                return ['success' => false, 'message' => 'Mata kuliah tidak ditemukan'];
            }
            
            // Hitung nilai draft yang valid (komponen lengkap)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM grades 
                WHERE course_id = ? AND status_verifikasi = 'draft'
                AND nilai_tugas IS NOT NULL AND nilai_uts IS NOT NULL AND nilai_uas IS NOT NULL
            ");
            $stmt->execute([$course_id]);
            $total_draft = intval($stmt->fetch()['total']);
            
            // Hitung nilai draft yang belum lengkap
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM grades 
                WHERE course_id = ? AND status_verifikasi = 'draft'
                AND (nilai_tugas IS NULL OR nilai_uts IS NULL OR nilai_uas IS NULL)
            ");
            $stmt->execute([$course_id]);
            $total_belum_lengkap = intval($stmt->fetch()['total']);
            
            if ($total_draft === 0) {
                return ['success' => false, 'message' => 'Tidak ada nilai draft yang bisa diverifikasi untuk mata kuliah ini'];
            }
            
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE grades 
                SET status_verifikasi = 'verified', updated_at = NOW()
                WHERE course_id = ? AND status_verifikasi = 'draft'
                AND nilai_tugas IS NOT NULL AND nilai_uts IS NOT NULL AND nilai_uas IS NOT NULL
            ");
            $stmt->execute([$course_id]);
            $affected = $stmt->rowCount();
            
            $this->pdo->commit();
            
            $message = $affected . ' nilai berhasil diverifikasi';
            if ($total_belum_lengkap > 0) {
                $message .= '. ' . $total_belum_lengkap . ' nilai dilewati karena komponen nilai belum lengkap';
            }
            
            return ['success' => true, 'affected' => $affected, 'message' => $message];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Tolak nilai draft (kembalikan ke perhatian dosen)
     */
    public function rejectGrade($grade_id, $catatan = null) {
        try {
            $grade_id = intval($grade_id);
            
            if ($grade_id <= 0) {
                return ['success' => false, 'message' => 'ID nilai tidak valid'];
            }
            
            $grade = $this->getGradeById($grade_id);
            if (!$grade) {
                return ['success' => false, 'message' => 'Data nilai tidak ditemukan'];
            }
            
            if ($grade['status_verifikasi'] !== 'draft') {
                return ['success' => false, 'message' => 'Hanya nilai berstatus draft yang bisa ditolak'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE grades SET status_verifikasi = 'draft', catatan = ?, updated_at = NOW()
                WHERE id = ? AND status_verifikasi = 'draft'
            ");
            $stmt->execute([$catatan, $grade_id]);
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getCoursesWithDraft() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT 
                c.id, c.kode_mk, c.nama_mk, c.sks,
                u.nama as dosen_nama,
                COUNT(g.id) as total_draft
            FROM grades g
            JOIN courses c ON g.course_id = c.id
            LEFT JOIN dosen d ON EXISTS (
                SELECT 1 FROM jadwal jd WHERE jd.course_id = c.id AND jd.dosen_id = d.id
            )
            LEFT JOIN users u ON d.user_id = u.id
            WHERE g.status_verifikasi = 'draft'
            GROUP BY c.id, c.kode_mk, c.nama_mk, c.sks, u.nama
            ORDER BY c.kode_mk ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getNilaiStats() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM grades WHERE status_verifikasi = 'draft'");
        $stats['draft'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM grades WHERE status_verifikasi = 'verified'");
        $stats['verified'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("
            SELECT COUNT(DISTINCT course_id) as total 
            FROM grades 
            WHERE status_verifikasi = 'draft'
        ");
        $stats['courses_with_draft'] = $stmt->fetch()['total'];
        
        // Nilai draft dengan komponen belum lengkap (tugas/UTS/UAS kosong)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total 
            FROM grades 
            WHERE status_verifikasi = 'draft'
            AND (nilai_tugas IS NULL OR nilai_uts IS NULL OR nilai_uas IS NULL)
        ");
        $stats['belum_lengkap'] = $stmt->fetch()['total'];
        
        return $stats;
    }

    
    
    // ============================================
    // DASHBOARD STATISTICS
    // ============================================
    
    public function getDashboardStats() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM mahasiswa");
        $stats['total_mahasiswa'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM dosen");
        $stats['total_dosen'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM courses");
        $stats['total_matakuliah'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM irs WHERE status = 'pending'");
        $stats['irs_pending'] = $stmt->fetch()['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM grades WHERE status_verifikasi = 'draft'");
        $stats['nilai_draft'] = $stmt->fetch()['total'];
        
        return $stats;
    }

    /**
 * Reset password user dan kirim email notifikasi
 */
public function resetUserPassword($user_id) {
    try {
        // Get data user
        $stmt = $this->pdo->prepare("
            SELECT u.*, 
                   COALESCE(m.nim, d.nid) as identifier
            FROM users u
            LEFT JOIN mahasiswa m ON u.id = m.user_id
            LEFT JOIN dosen d ON u.id = d.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User tidak ditemukan'];
        }
        
        // Generate password baru (8 karakter acak)
        $new_password = $this->generateRandomPassword(8);
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        
        // Kirim email notifikasi (jika ada email)
        $email_sent = false;
        if (!empty($user['email'])) {
            require_once __DIR__ . '/../helper/EmailHelper.php';
            require_once __DIR__ . '/../template/email/reset_password.php';
            
            $emailHelper = new EmailHelper();
            $body = renderResetPassword([
                'nama' => $user['nama'],
                'username' => $user['username'],
                'new_password' => $new_password,
                'reset_by' => $_SESSION['nama'] ?? 'Admin'
            ]);
            
            $result = $emailHelper->send(
                $user['email'],
                $user['nama'],
                'Password Akun SIMAK Anda Direset',
                $body
            );
            
            $email_sent = $result['success'];
        }
        
        return [
            'success' => true,
            'new_password' => $new_password,
            'email_sent' => $email_sent,
            'message' => 'Password berhasil direset' . ($email_sent ? ' dan email terkirim' : '')
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Generate random password
 */
private function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Approve IRS dan kirim email notifikasi
 */
public function approveIRSDenganEmail($irs_id, $mahasiswa_id, $semester) {
    try {
        // Approve IRS
        $stmt = $this->pdo->prepare("
            UPDATE irs 
            SET status = 'approved', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$irs_id]);
        
        // Get data mahasiswa & IRS
        $stmt = $this->pdo->prepare("
            SELECT u.nama, u.email, m.nim
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $stmt->execute([$mahasiswa_id]);
        $mahasiswa = $stmt->fetch();
        
        // Get daftar MK yang di-approve di semester ini
        $stmt = $this->pdo->prepare("
            SELECT c.kode_mk, c.nama_mk, c.sks
            FROM irs i
            JOIN courses c ON i.course_id = c.id
            WHERE i.mahasiswa_id = ? AND i.semester = ? AND i.status = 'approved'
        ");
        $stmt->execute([$mahasiswa_id, $semester]);
        $matakuliah = $stmt->fetchAll();
        
        $total_sks = array_sum(array_column($matakuliah, 'sks'));
        
        // Kirim email
        $email_sent = false;
        if ($mahasiswa && !empty($mahasiswa['email'])) {
            require_once __DIR__ . '/../helper/EmailHelper.php';
            require_once __DIR__ . '/../template/email/irs_notification.php';
            
            $emailHelper = new EmailHelper();
            $body = renderIRSNotification([
                'nama' => $mahasiswa['nama'],
                'nim' => $mahasiswa['nim'],
                'semester' => $semester,
                'status' => 'approved',
                'catatan' => null,
                'total_sks' => $total_sks,
                'matakuliah' => $matakuliah
            ]);
            
            $result = $emailHelper->send(
                $mahasiswa['email'],
                $mahasiswa['nama'],
                'IRS Anda Telah Disetujui',
                $body
            );
            
            $email_sent = $result['success'];
        }
        
        return [
            'success' => true,
            'email_sent' => $email_sent
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Reject IRS dan kirim email notifikasi
 */
public function rejectIRSDenganEmail($irs_id, $mahasiswa_id, $semester, $catatan = null) {
    try {
        // Reject IRS
        $stmt = $this->pdo->prepare("
            UPDATE irs 
            SET status = 'rejected', catatan = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$catatan, $irs_id]);
        
        // Get data mahasiswa
        $stmt = $this->pdo->prepare("
            SELECT u.nama, u.email, m.nim
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $stmt->execute([$mahasiswa_id]);
        $mahasiswa = $stmt->fetch();
        
        // Get daftar MK
        $stmt = $this->pdo->prepare("
            SELECT c.kode_mk, c.nama_mk, c.sks
            FROM irs i
            JOIN courses c ON i.course_id = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$irs_id]);
        $matakuliah = $stmt->fetchAll();
        
        // Kirim email
        $email_sent = false;
        if ($mahasiswa && !empty($mahasiswa['email'])) {
            require_once __DIR__ . '/../helper/EmailHelper.php';
            require_once __DIR__ . '/../template/email/irs_notification.php';
            
            $emailHelper = new EmailHelper();
            $body = renderIRSNotification([
                'nama' => $mahasiswa['nama'],
                'nim' => $mahasiswa['nim'],
                'semester' => $semester,
                'status' => 'rejected',
                'catatan' => $catatan,
                'total_sks' => 0,
                'matakuliah' => $matakuliah
            ]);
            
            $result = $emailHelper->send(
                $mahasiswa['email'],
                $mahasiswa['nama'],
                'IRS Anda Ditolak',
                $body
            );
            
            $email_sent = $result['success'];
        }
        
        return [
            'success' => true,
            'email_sent' => $email_sent
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Verifikasi nilai dan kirim email notifikasi
 */
public function verifyGradeDenganEmail($grade_id) {
    try {
        // Verify grade
        $stmt = $this->pdo->prepare("
            UPDATE grades 
            SET status_verifikasi = 'verified', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$grade_id]);
        
        // Get detail nilai
        $stmt = $this->pdo->prepare("
            SELECT 
                g.*,
                u.nama, u.email,
                m.nim,
                c.kode_mk, c.nama_mk, c.sks
            FROM grades g
            JOIN mahasiswa m ON g.mahasiswa_id = m.id
            JOIN users u ON m.user_id = u.id
            JOIN courses c ON g.course_id = c.id
            WHERE g.id = ?
        ");
        $stmt->execute([$grade_id]);
        $nilai = $stmt->fetch();
        
        // Kirim email
        $email_sent = false;
        if ($nilai && !empty($nilai['email'])) {
            require_once __DIR__ . '/../helper/EmailHelper.php';
            require_once __DIR__ . '/../template/email/nilai_notification.php';
            
            $emailHelper = new EmailHelper();
            $body = renderNilaiNotification([
                'nama' => $nilai['nama'],
                'nim' => $nilai['nim'],
                'kode_mk' => $nilai['kode_mk'],
                'nama_mk' => $nilai['nama_mk'],
                'sks' => $nilai['sks'],
                'nilai_tugas' => $nilai['nilai_tugas'],
                'nilai_uts' => $nilai['nilai_uts'],
                'nilai_uas' => $nilai['nilai_uas'],
                'nilai_akhir' => $nilai['nilai_akhir']
            ]);
            
            $result = $emailHelper->send(
                $nilai['email'],
                $nilai['nama'],
                'Nilai Anda Telah Diverifikasi',
                $body
            );
            
            $email_sent = $result['success'];
        }
        
        return [
            'success' => true,
            'email_sent' => $email_sent
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Kirim email akun baru
 */
public function sendAkunBaruEmail($user_id, $plain_password) {
    try {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['email'])) {
            return ['success' => false, 'message' => 'User tidak ditemukan atau email kosong'];
        }
        
        require_once __DIR__ . '/../helper/EmailHelper.php';
        require_once __DIR__ . '/../template/email/akun_baru.php';
        
        $emailHelper = new EmailHelper();
        $body = renderAkunBaru([
            'nama' => $user['nama'],
            'username' => $user['username'],
            'password' => $plain_password,
            'role' => $user['role']
        ]);
        
        return $emailHelper->send(
            $user['email'],
            $user['nama'],
            'Akun SIMAK Anda Telah Dibuat',
            $body
        );
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Kirim broadcast pengumuman via email ke semua user sesuai target_role.
 Taxahorn dan dipanggil dari public/admin/broadcast.php (checkbox "kirim via email").
 */
public function kirimBroadcastViaEmail($announcement_id) {
    try {
        // Ambil data pengumuman + pengirim
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
        
        // Ambil semua email penerima sesuai target_role (yang memiliki email)
        $sql = "SELECT nama, email FROM users";
        if ($announcement['target_role'] !== 'all') {
            $sql .= " WHERE role = ?";
        }
        $stmt = $this->pdo->prepare($sql);
        
        if ($announcement['target_role'] !== 'all') {
            $stmt->execute([$announcement['target_role']]);
        } else {
            $stmt->execute();
        }
        
        $recipients = $stmt->fetchAll();
        
        if (empty($recipients)) {
            return ['success' => true, 'sent' => 0, 'failed' => 0, 'message' => 'Tidak ada penerima dengan email'];
        }
        
        require_once __DIR__ . '/../helper/EmailHelper.php';
        require_once __DIR__ . '/../template/email/broadcast.php';
        
        $emailHelper = new EmailHelper();
        $sent = 0;
        $failed = 0;
        $failed_emails = [];
        
        foreach ($recipients as $r) {
            if (empty($r['email'])) {
                $failed++;
                continue;
            }
            
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
            else { $failed++; $failed_emails[] = $r['email']; }
        }
        
        return [
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'message' => "Email terkirim ke {$sent} penerima" . ($failed > 0 ? ", {$failed} gagal" : '')
        ];
        
    } catch (PDOException $e) {
        return ['success' => false, 'sent' => 0, 'failed' => 0, 'message' => $e->getMessage()];
    }
}
}
?>