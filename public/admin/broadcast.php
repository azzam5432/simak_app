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

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <div>
        <div class="table-container">
            <h3><i class="fas fa-bullhorn"></i> Kirim Pengumuman Kampus</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send">
                
                <div class="form-group">
                    <label class="form-label">Judul Pengumuman <span style="color: #e74c3c;">*</span></label>
                    <input class="form-control" type="text" name="judul" required placeholder="Masukkan judul pengumuman">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Target Penerima <span style="color: #e74c3c;">*</span></label>
                    <select class="form-select" name="target_role" required>
                        <option value="all">Semua Pengguna</option>
                        <option value="admin">Admin</option>
                        <option value="dosen">Dosen</option>
                        <option value="mahasiswa">Mahasiswa</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Prioritas</label>
                    <select class="form-select" name="priority">
                        <option value="low">Rendah</option>
                        <option value="medium" selected>Sedang</option>
                        <option value="high">Tinggi (Penting)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Pesan <span style="color: #e74c3c;">*</span></label>
                    <textarea class="form-control" name="pesan" rows="6" required placeholder="Tulis pesan pengumuman..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">
                        <input class="form-check-input" type="checkbox" name="kirim_email" value="1" style="width: 16px; height: 16px;">
                        <i class="fas fa-envelope"></i> Kirim juga via email ke penerima
                        <small style="color: #7f8c8d;">(butuh waktu tergantung jumlah penerima)</small>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" style="padding: 12px; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Kirim Pengumuman
                </button>
            </form>
        </div>
    </div>
    
    <div>
        <div class="table-container">
            <h3><i class="fas fa-history"></i> Riwayat Pengumuman</h3>
            
            <?php if (empty($history)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 30px;">
                    Belum ada pengumuman yang dikirim
                </p>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <div style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; <?= $h['priority'] === 'high' ? 'border-left: 4px solid #e74c3c;' : '' ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($h['judul']) ?></strong>
                                <?php if ($h['priority'] === 'high'): ?>
                                    <span style="color: #c0392b; font-weight: 600; font-size: 10px;">Penting</span>
                                <?php endif; ?>
                            </div>
                            <small style="color: #7f8c8d;">
                                <?= date('d-m-Y H:i', strtotime($h['created_at'])) ?>
                            </small>
                        </div>
                        <div style="font-size: 13px; color: #555; margin: 5px 0;">
                            <?= htmlspecialchars(substr($h['pesan'], 0, 100)) ?>...
                        </div>
                        <div style="display: flex; gap: 10px; font-size: 12px; color: #7f8c8d;">
                            <span>Target: <?= ucfirst($h['target_role']) ?></span>
                            <span>Oleh: <?= htmlspecialchars($h['sender_name']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>