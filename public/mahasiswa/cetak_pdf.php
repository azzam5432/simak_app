<?php
// ============================================
// public/mahasiswa/cetak_pdf.php
// Handler cetak PDF untuk mahasiswa
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/PdfController.php';

checkAccess(['mahasiswa']);

$stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$pdfController = new PdfController($pdo);

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'report_nilai':
        $semester = trim($_GET['semester'] ?? '');
        $semester = $semester !== '' ? $semester : null;
        $pdfController->generateLaporanRekapNilai($mahasiswa['id'], $semester);
        break;
        
    case 'report_krs':
        // Semester wajib ada untuk report KRS; fallback ke semester aktif mahasiswa
        $stmt = $pdo->prepare("SELECT semester FROM mahasiswa WHERE id = ?");
        $stmt->execute([$mahasiswa['id']]);
        $mhs = $stmt->fetch();
        $semester = trim($_GET['semester'] ?? '');
        $semester = ($semester !== '' && ctype_digit($semester)) ? $semester : ($mhs['semester'] ?? '1');
        $pdfController->generateReportKRS($mahasiswa['id'], $semester);
        break;
        
    default:
        header("Location: /simak_app/public/mahasiswa/khs.php");
        exit();
}
?>
