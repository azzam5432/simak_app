<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Broadcast Pengumuman';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    try {
        $sender_id = $_SESSION['user_id'];
        $target_role = $_POST['target_role'];
        $judul = trim($_POST['judul']);
        $pesan = trim($_POST['pesan']);
        $priority = $_POST['priority'] ?? 'medium';
        
        if (empty($judul) || empty($pesan)) {
            redirectWithMessage('/simak_app/public/admin/broadcast.php', 'Judul dan pesan harus diisi!', 'error');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO announcements (sender_id, sender_role, target_role, judul, pesan, priority)
            VALUES (?, 'admin', ?, ?, ?, ?)
        ");
        $stmt->execute([$sender_id, $target_role, $judul, $pesan, $priority]);
        $announcement_id = $pdo->lastInsertId();
        
        $pesan_sukses = 'Pengumuman berhasil dikirim ke semua pengguna!';
        
        // Kirim juga via email jika dicentang
        if (!empty($_POST['kirim_email'])) {
            $controller = new AdminController($pdo);
            $email_result = $controller->kirimBroadcastViaEmail($announcement_id);
            
            if ($email_result['success'] && $email_result['sent'] > 0) {
                $pesan_sukses .= " Email terkirim ke {$email_result['sent']} penerima.";
            } elseif (!$email_result['success']) {
                redirectWithMessage(
                    '/simak_app/public/admin/broadcast.php',
                    'Pengumuman tersimpan, tapi email gagal: ' . ($email_result['message'] ?? 'unknown'),
                    'warning'
                );
            }
        }
        
        redirectWithMessage('/simak_app/public/admin/broadcast.php', $pesan_sukses, 'success');
    } catch (PDOException $e) {
        redirectWithMessage('/simak_app/public/admin/broadcast.php', 'Gagal: ' . $e->getMessage(), 'error');
    }
}

$stmt = $pdo->prepare("
    SELECT a.*, u.nama as sender_name
    FROM announcements a
    JOIN users u ON a.sender_id = u.id
    WHERE a.sender_role = 'admin'
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt->execute();
$history = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="split-grid">
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-bullhorn"></i> Kirim Pengumuman Kampus</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="send">

            <div class="form-group">
                <label class="form-label" for="judul">Judul Pengumuman <span class="req">*</span></label>
                <input class="form-control" type="text" id="judul" name="judul" required placeholder="Masukkan judul pengumuman">
            </div>

            <div class="form-group">
                <label class="form-label" for="target_role">Target Penerima <span class="req">*</span></label>
                <select class="form-select" id="target_role" name="target_role" required>
                    <option value="all">Semua Pengguna</option>
                    <option value="admin">Admin</option>
                    <option value="dosen">Dosen</option>
                    <option value="mahasiswa">Mahasiswa</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="priority">Prioritas</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="low">Rendah</option>
                    <option value="medium" selected>Sedang</option>
                    <option value="high">Tinggi (Penting)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="pesan">Pesan <span class="req">*</span></label>
                <textarea class="form-control" id="pesan" name="pesan" rows="6" required placeholder="Tulis pesan pengumuman..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label d-flex align-items-center gap-2 fw-normal" style="cursor: pointer;">
                    <input class="form-check-input m-0" type="checkbox" name="kirim_email" value="1">
                    <span><i class="fas fa-envelope"></i> Kirim juga via email
                        <span class="text-muted small d-block">Butuh waktu tergantung jumlah penerima.</span>
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-paper-plane"></i> Kirim Pengumuman
            </button>
        </form>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-history"></i> Riwayat Pengumuman</h3>
        </div>

        <?php if (empty($history)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum ada pengumuman</h3>
                <p>Pengumuman yang Anda kirim akan tampil di sini.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
                <?php foreach ($history as $h): ?>
                    <div class="feed-item<?= $h['priority'] === 'high' ? ' is-important' : '' ?>">
                        <div class="feed-main">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="feed-title"><?= htmlspecialchars($h['judul']) ?></span>
                                <span class="feed-meta"><?= date('d-m-Y H:i', strtotime($h['created_at'])) ?></span>
                            </div>
                            <span class="feed-text"><?= htmlspecialchars(substr($h['pesan'], 0, 100)) ?>...</span>
                            <div class="feed-meta">
                                <?php if ($h['priority'] === 'high'): ?><span class="feed-tag">Penting</span><?php endif; ?>
                                <span>Target: <?= ucfirst($h['target_role']) ?></span>
                                <span>Oleh: <?= htmlspecialchars($h['sender_name']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>