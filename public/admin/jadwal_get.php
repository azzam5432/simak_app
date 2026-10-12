<?php
// ============================================
// public/admin/jadwal_get.php
// API: Get jadwal untuk edit
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

$adminController = new AdminController($pdo);
$jd = $adminController->getJadwalById($id);

if ($jd) {
    echo json_encode([
        'success' => true,
        'id' => $jd['id'],
        'course_id' => $jd['course_id'],
        'dosen_id' => $jd['dosen_id'],
        'tingkat' => $jd['tingkat'],
        'rombel' => $jd['rombel'],
        'hari' => $jd['hari'],
        'jam_mulai' => $jd['jam_mulai'],
        'jam_selesai' => $jd['jam_selesai'],
        'ruang' => $jd['ruang'],
        'kapasitas' => $jd['kapasitas'],
        'tahun_ajaran' => $jd['tahun_ajaran'],
        'semester_ke' => $jd['semester_ke']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan']);
}
?>