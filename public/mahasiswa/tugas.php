<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';
require_once __DIR__ . '/../../controllers/NotifikasiController.php';

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
    $task_id = intval($_POST['task_id'] ?? 0);
    $upload = $controller->uploadBuktiTugas($_FILES['bukti'] ?? null, $task_id);
    
    if (!$upload['success']) {
        redirectWithMessage('/simak_app/public/mahasiswa/tugas.php', 'Gagal: ' . $upload['message'], 'error');
    }
    
    $result = $controller->submitTugas($task_id, $upload['path'], $_POST['catatan'] ?? null);
    if ($result['success']) {
        NotifikasiController::tugasDikumpulkan($pdo, $task_id, $mahasiswa['id']);
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
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>Belum ada tugas</h3>
            <p>Tugas dari dosen akan tampil di sini.</p>
        </div>
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
                        'active' => 'Aktif',
                        'urgent' => 'Mendesak',
                        'submitted' => 'Dikumpulkan',
                        'expired' => 'Expired'
                    ];
                    $status_class = [
                        'active' => 'status-active',
                        'urgent' => 'status-urgent',
                        'submitted' => 'status-submitted',
                        'expired' => 'status-expired'
                    ];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['kode_mk']) ?></strong></td>
                        <td><?= htmlspecialchars($t['judul']) ?></td>
                        <td>
                            <?= date('d-m-Y H:i', strtotime($t['deadline'])) ?>
                            <?php if ($status_tugas === 'urgent'): ?>
                                <div class="deadline is-late mt-1"><i class="fas fa-hourglass-half"></i> <?= floor($t['jam_tersisa'] / 24) ?> hari <?= $t['jam_tersisa'] % 24 ?> jam lagi</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $status_class[$status_tugas] ?? 'status-draft' ?>">
                                <?= $status_label[$status_tugas] ?? 'Unknown' ?>
                            </span>
                            <?php if (!empty($t['file_path'])): ?>
                                <div class="mt-1"><a href="/simak_app/public/<?= htmlspecialchars($t['file_path']) ?>" target="_blank" class="small">
                                    <i class="fas fa-image"></i> Lihat bukti
                                </a></div>
                            <?php endif; ?>
                            <?php if ($t['submission_nilai'] !== null): ?>
                                <div class="stat-sub">Nilai: <strong><?= $t['submission_nilai'] ?></strong></div>
                            <?php endif; ?>
                            <?php if ($t['submission_status'] === 'validated'): ?>
                                <div class="stat-sub"><i class="fas fa-check-double"></i> Tervalidasi dosen</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status_tugas === 'active' || $status_tugas === 'urgent'): ?>
                                <button class="btn btn-primary btn-sm" onclick="submitTugas(<?= $t['id'] ?>, '<?= htmlspecialchars($t['judul']) ?>')">
                                    <i class="fas fa-upload"></i> Kumpulkan
                                </button>
                            <?php elseif ($status_tugas === 'submitted'): ?>
                                <span class="status-badge status-submitted">Tersubmit</span>
                            <?php else: ?>
                                <span class="status-badge status-expired">Expired</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="modalSubmit" class="modal">
    <div class="modal-overlay" onclick="document.getElementById('modalSubmit').style.display='none'"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-upload"></i> Kumpulkan Tugas</h3>
            <button type="button" class="modal-close" aria-label="Tutup" onclick="document.getElementById('modalSubmit').style.display='none'">&times;</button>
        </div>
        <p id="submitInfo" class="text-muted mb-3"></p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="task_id" id="submitTaskId">
            <div class="form-group">
                <label class="form-label">Screenshot Bukti Tugas</label>
                <input class="form-control" type="file" name="bukti" accept="image/jpeg,image/png,image/webp">
                <span class="help-text">Format JPG, PNG, atau WEBP (maks. 5MB).</span>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan (opsional)</label>
                <textarea class="form-control" name="catatan" rows="3" placeholder="Tambahkan catatan untuk tugas..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('modalSubmit').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Kumpulkan</button>
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