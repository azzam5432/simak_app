<?php
// ============================================
// migrations/2026_11_22_add_validated_status.php
// Jalankan sekali: php migrations/2026_11_22_add_validated_status.php
// Menambahkan status 'validated' pada task_submissions.status
// supaya dosen dapat memvalidasi hasil tugas mahasiswa.
// ============================================

require_once __DIR__ . '/../config/database.php';

$pdo->exec("
    ALTER TABLE task_submissions
    MODIFY COLUMN status ENUM('draft','submitted','late','validated') NULL DEFAULT 'draft'
");

echo "Migrasi berhasil: status 'validated' ditambahkan ke task_submissions.\n";
