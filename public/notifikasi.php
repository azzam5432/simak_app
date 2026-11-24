<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/NotifikasiController.php';

checkAccess();

$page_title = 'Notifikasi';
$notif_controller = new NotifikasiController($pdo);
$user_id = $_SESSION['user_id'];

// Buka satu notifikasi: tandai dibaca lalu lanjut ke tautannya
$baca_id = intval($_GET['baca'] ?? 0);
if ($baca_id) {
    $notif = $notif_controller->getById($baca_id, $user_id);

    if ($notif) {
        $notif_controller->tandaiDibaca($baca_id, $user_id);
        $link = $notif['link'] ?? '';

        // Hanya izinkan tautan internal aplikasi
        if ($link && strpos($link, '/simak_app/public/') === 0) {
            header('Location: ' . $link);
            exit();
        }
    }

    header('Location: /simak_app/public/notifikasi.php');
    exit();
}

if (isset($_GET['baca_semua'])) {
    $notif_controller->tandaiSemuaDibaca($user_id);
    redirectWithMessage('/simak_app/public/notifikasi.php', 'Semua notifikasi ditandai sudah dibaca.', 'success');
}

$notifikasi = $notif_controller->getUntukUser($user_id, 100);
$total_notifikasi = count($notifikasi);
$belum_dibaca = $notif_controller->countBelumDibaca($user_id);

include __DIR__ . '/../includes/header.php';
?>

<div class="notif-page-card">
    <div class="table-header notif-page-header">
        <div class="notif-page-heading">
            <h3><i class="fas fa-bell"></i> Semua Notifikasi</h3>
            <p>
                <?php if ($belum_dibaca > 0): ?>
                    <span class="notif-page-count"><?= $belum_dibaca ?> belum dibaca</span>
                    dari <?= $total_notifikasi ?> notifikasi
                <?php else: ?>
                    Semua notifikasi sudah dibaca
                <?php endif; ?>
            </p>
        </div>
        <?php if ($belum_dibaca > 0): ?>
            <a class="btn btn-secondary btn-sm" href="/simak_app/public/notifikasi.php?baca_semua=1">
                <i class="fas fa-check-double"></i> Tandai semua dibaca
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifikasi)): ?>
        <div class="notif-page-empty">
            <span class="notif-empty-icon"><i class="fas fa-bell-slash"></i></span>
            <strong>Belum ada notifikasi</strong>
            <span>Pemberitahuan pengumpulan dan validasi tugas akan tampil di sini.</span>
        </div>
    <?php else: ?>
        <div class="notif-page-list">
            <?php foreach ($notifikasi as $notif): ?>
                <a class="notif-item notif-page-item <?= $notif['is_read'] ? '' : 'unread' ?>"
                   href="/simak_app/public/notifikasi.php?baca=<?= intval($notif['id']) ?>">
                    <span class="notif-icon <?= $notif['type'] === 'tugas_validated' ? 'notif-icon-success' : 'notif-icon-info' ?>">
                        <i class="fas <?= NotifikasiController::ikon($notif['type']) ?>"></i>
                    </span>
                    <span class="notif-text">
                        <span class="notif-title"><?= htmlspecialchars($notif['judul']) ?></span>
                        <span class="notif-message"><?= htmlspecialchars($notif['pesan']) ?></span>
                        <span class="notif-time">
                            <i class="far fa-clock"></i>
                            <?= date('d-m-Y H:i', strtotime($notif['created_at'])) ?>
                            <?php if (!$notif['is_read']): ?>
                                <span class="notif-status">Baru</span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <?php if (!empty($notif['link'])): ?>
                        <span class="notif-arrow"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
