<?php
// ============================================
// migrations/2026_11_22_create_notifications.php
// Jalankan sekali: php migrations/2026_11_22_create_notifications.php
// Membuat tabel notifications (notifikasi dalam aplikasi untuk semua role).
// ============================================

require_once __DIR__ . '/../config/database.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        judul VARCHAR(150) NOT NULL,
        pesan TEXT NOT NULL,
        link VARCHAR(255) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notifications_user (user_id, is_read, created_at),
        CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Migrasi berhasil: tabel notifications dibuat.\n";
