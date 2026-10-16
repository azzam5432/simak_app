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

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <div>
        <div class="table-container">
            <h3><i class="fas fa-bullhorn"></i> Kirim Pengumuman</h3>
            <form method="POST">
                <input type="hidden" name="action" value="send">
                
                <div class="form-group">
                    <label class="form-label">Judul Pengumuman</label>
                    <input class="form-control" type="text" name="judul" required placeholder="Masukkan judul pengumuman">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Target</label>
                    <select class="form-select" name="target_role" required>
                        <option value="all">Semua Pengguna</option>
                        <option value="mahasiswa">Mahasiswa</option>
                        <option value="dosen">Dosen</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Prioritas</label>
                    <select class="form-select" name="priority">
                        <option value="low">Rendah</option>
                        <option value="medium" selected>Sedang</option>
                        <option value="high">Tinggi</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Pesan</label>
                    <textarea class="form-control" name="pesan" rows="5" required placeholder="Tulis pesan pengumuman..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal;">
                        <input class="form-check-input" type="checkbox" name="kirim_email" value="1" style="width: 16px; height: 16px;">
                        <i class="fas fa-envelope"></i> Kirim juga via email ke penerima
                        <small style="color: #7f8c8d;">(butuh waktu tergantung jumlah penerima)</small>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane"></i> Kirim Pengumuman</button>
            </form>
        </div>
    </div>
    
    <div>
        <div class="table-container">
            <h3><i class="fas fa-history"></i> Riwayat Pengumuman</h3>
            
            <?php if (empty($history)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                    Belum ada pengumuman yang dikirim
                </p>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <div style="padding: 12px 15px; border-bottom: 1px solid #e9ecef;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong><?= htmlspecialchars($h['judul']) ?></strong>
                            <small style="color: #7f8c8d;">
                                <?= date('d-m-Y H:i', strtotime($h['created_at'])) ?>
                            </small>
                        </div>
                        <div style="font-size: 13px; color: #555; margin: 5px 0;">
                            <?= htmlspecialchars(substr($h['pesan'], 0, 80)) ?>...
                        </div>
                        <div style="display: flex; gap: 10px; font-size: 12px; color: #7f8c8d;">
                            <span><i class="fas fa-bullseye"></i> Target: <?= ucfirst($h['target_role']) ?></span>
                            <span><i class="fas fa-star"></i> Prioritas: <?= ucfirst($h['priority']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>