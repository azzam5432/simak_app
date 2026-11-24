<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';

checkAccess(['dosen']);

$page_title = 'Broadcast Kelas';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$controller = new DosenController($pdo, $dosen['id']);

$matakuliah = $controller->getMatakuliahDosen();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $result = $controller->sendBroadcast($_POST);
    if ($result['success']) {
        $pesan_sukses = 'Pengumuman berhasil dikirim!';
        
        // Kirim juga via email jika dicentang
        if (!empty($_POST['kirim_email']) && !empty($result['id'])) {
            $email_result = $controller->kirimBroadcastViaEmail($result['id']);
            
            if ($email_result['success'] && $email_result['sent'] > 0) {
                $pesan_sukses .= " Email terkirim ke {$email_result['sent']} mahasiswa.";
            } elseif (!$email_result['success']) {
                redirectWithMessage(
                    '/simak_app/public/dosen/broadcast.php',
                    'Pengumuman tersimpan, tapi email gagal: ' . ($email_result['message'] ?? 'unknown'),
                    'warning'
                );
            }
        }
        
        redirectWithMessage('/simak_app/public/dosen/broadcast.php', $pesan_sukses, 'success');
    } else {
        redirectWithMessage('/simak_app/public/dosen/broadcast.php', 'Gagal: ' . $result['message'], 'error');
    }
}

$history = $controller->getBroadcastHistory();

include __DIR__ . '/../../includes/header.php';
?>

<div class="split-grid">
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-bullhorn"></i> Kirim Pengumuman</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="send">

            <div class="form-group">
                <label class="form-label" for="judul">Judul Pengumuman</label>
                <input class="form-control" type="text" id="judul" name="judul" required placeholder="Masukkan judul pengumuman">
            </div>

            <div class="form-group">
                <label class="form-label" for="target_role">Target</label>
                <select class="form-select" id="target_role" name="target_role" required>
                    <option value="all">Semua Pengguna</option>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="dosen">Dosen</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="priority">Prioritas</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="low">Rendah</option>
                    <option value="medium" selected>Sedang</option>
                    <option value="high">Tinggi</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="pesan">Pesan</label>
                <textarea class="form-control" id="pesan" name="pesan" rows="5" required placeholder="Tulis pesan pengumuman..."></textarea>
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
                            <span class="feed-text"><?= htmlspecialchars(substr($h['pesan'], 0, 80)) ?>...</span>
                            <div class="feed-meta">
                                <span><i class="fas fa-bullseye"></i> Target: <?= ucfirst($h['target_role']) ?></span>
                                <span><i class="fas fa-star"></i> Prioritas: <?= ucfirst($h['priority']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>