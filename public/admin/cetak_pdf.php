<?php
// ============================================
// public/admin/cetak_pdf.php
// Handler untuk cetak PDF
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/PdfController.php';

checkAccess(['admin']);

$pdfController = new PdfController($pdo);

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'nilai':
        $course_id = $_GET['course_id'] ?? null;
        $semester = $_GET['semester'] ?? null;
        $pdfController->generateLaporanNilai($course_id, $semester);
        break;
        
    case 'rekap_nilai':
        $mahasiswa_id = intval($_GET['mahasiswa_id'] ?? 0);
        $semester = $_GET['semester'] ?? null;
        $semester = ($semester !== '' && $semester !== null) ? $semester : null;
        $pdfController->generateLaporanRekapNilai($mahasiswa_id, $semester);
        break;
        
    case 'irs':
        $semester = $_GET['semester'] ?? null;
        $status = $_GET['status'] ?? null;
        $pdfController->generateLaporanIRS($semester, $status);
        break;
        
    case 'presensi':
        $course_id = $_GET['course_id'] ?? null;
        $pdfController->generateLaporanPresensi($course_id);
        break;
        
    case 'mahasiswa':
        $jurusan_id = $_GET['jurusan_id'] ?? null;
        $pdfController->generateLaporanMahasiswa($jurusan_id);
        break;
        
    default:
        header("Location: /simak_app/public/admin/laporan.php");
        exit();
}
?>