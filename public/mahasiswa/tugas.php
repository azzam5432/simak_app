<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'Tugas';

$stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$controller = new MahasiswaController($pdo, $mahasiswa['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $result = $controller->submitTugas($_POST['task_id'], null, $_POST['catatan'] ?? null);
    if ($result['success']) {
        redirectWithMessage('/simak_app/public/mahasiswa/tugas.php', 'Tugas berhasil dikumpulkan!', 'success');
    } else {
        redirectWithMessage('/simak_app/public/mahasiswa/tugas.php', 'Gagal: ' . $result['message'], 'error');
    }
}

$tugas = $controller->getAllTugas();

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-file-alt"></i> Daftar Tugas</h3>
        <div>
            <input class="form-control" type="text" id="tableSearch" placeholder="Cari tugas...">
        </div>
    </div>
    
    <?php if (empty($tugas)): ?>
        <p style="text-align: center; color: #7f8c8d; padding: 30px;">
            Belum ada tugas yang diberikan
        </p>
    <?php else: ?>
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Mata Kuliah</th>
                    <th>Judul</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tugas as $t):
                    $status_tugas = $t['status_tugas'];
                    $status_label = [
                        'active' => '<i class="fas fa-circle"></i> Aktif',
                        'urgent' => '<i class="fas fa-circle"></i> Mendesak!',
                        'submitted' => '<i class="fas fa-check"></i> Dikumpulkan',
                        'expired' => '<i class="fas fa-clock"></i> Expired'
                    ];
                    $status_color = [
                        'active' => '#2ecc71',
                        'urgent' => '#e74c3c',
                        'submitted' => '#3498db',
                        'expired' => '#95a5a6'
                    ];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['kode_mk']) ?></strong></td>
                        <td><?= htmlspecialchars($t['judul']) ?></td>
                        <td>
                            <?= date('d-m-Y H:i', strtotime($t['deadline'])) ?>
                            <?php if ($status_tugas === 'urgent'): ?>
                                <br><small style="color: #e74c3c;"><i class="fas fa-hourglass-half"></i> <?= floor($t['jam_tersisa'] / 24) ?> hari <?= $t['jam_tersisa'] % 24 ?> jam lagi</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 600; font-size: 12px;">
                                <?= $status_label[$status_tugas] ?? 'Unknown' ?>
                            </span>
                            <?php if ($t['submission_nilai'] !== null): ?>
                                <br><small style="color: #2ecc71;">Nilai: <?= $t['submission_nilai'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status_tugas === 'active' || $status_tugas === 'urgent'): ?>
                                <button class="btn btn-primary btn-sm" onclick="submitTugas(<?= $t['id'] ?>, '<?= htmlspecialchars($t['judul']) ?>')">
                                    <i class="fas fa-upload"></i> Kumpulkan
                                </button>
                            <?php elseif ($status_tugas === 'submitted'): ?>
                                <span style="color: #2ecc71;"><i class="fas fa-check"></i> Tersubmit</span>
                            <?php else: ?>
                                <span style="color: #95a5a6;"><i class="fas fa-clock"></i> Expired</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="modalSubmit" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999;">
    <div class="modal-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);"></div>
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 30px; max-width: 450px; width: 90%;">
        <h3 style="margin-bottom: 10px;"><i class="fas fa-upload"></i> Kumpulkan Tugas</h3>
        <p id="submitInfo" style="color: #7f8c8d; margin-bottom: 15px;"></p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="task_id" id="submitTaskId">
            <div class="form-group">
                <label class="form-label">Catatan (opsional)</label>
                <textarea class="form-control" name="catatan" rows="3" placeholder="Tambahkan catatan untuk tugas..."></textarea>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Kumpulkan</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalSubmit').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function submitTugas(taskId, judul) {
    document.getElementById('submitTaskId').value = taskId;
    document.getElementById('submitInfo').textContent = 'Mengumpulkan tugas: ' + judul;
    document.getElementById('modalSubmit').style.display = 'block';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>