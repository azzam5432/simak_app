<?php
// ============================================
// public/dosen/cetak_pdf.php
// Handler cetak PDF untuk dosen
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/PdfController.php';

checkAccess(['dosen']);

// Ambil dosen_id dari session
require_once __DIR__ . '/../../controllers/DosenController.php';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$pdfController = new PdfController($pdo);

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'nilai_ampu':
        $course_id = intval($_GET['course_id'] ?? 0) ?: null;
        $semester = $_GET['semester'] ?? null;
        $semester = ($semester !== '' && $semester !== null) ? $semester : null;
        $pdfController->generateLaporanNilaiDosen($dosen['id'], $course_id, $semester);
        break;
        
    default:
        header("Location: /simak_app/public/dosen/laporan_nilai.php");
        exit();
}
?>
