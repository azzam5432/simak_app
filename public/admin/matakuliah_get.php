<?php
// ============================================
// public/admin/matakuliah_get.php
// API: Get mata kuliah untuk edit
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
$mk = $adminController->getMatakuliahById($id);

if ($mk) {
    echo json_encode([
        'success' => true,
        'id' => $mk['id'],
        'kode_mk' => $mk['kode_mk'],
        'nama_mk' => $mk['nama_mk'],
        'sks' => $mk['sks'],
        'semester' => $mk['semester'],
        'jurusan_id' => $mk['jurusan_id'],
        'deskripsi' => $mk['deskripsi']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Mata kuliah tidak ditemukan']);
}
?>