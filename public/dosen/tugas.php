<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';

checkAccess(['dosen']);

$page_title = 'Manajemen Tugas';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$controller = new DosenController($pdo, $dosen['id']);

$matakuliah = $controller->getMatakuliahDosen();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = $controller->createTugas($_POST);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/dosen/tugas.php', 'Tugas berhasil dibuat!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/dosen/tugas.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;
            
        case 'delete':
            $result = $controller->deleteTugas($_POST['id']);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/dosen/tugas.php', 'Tugas berhasil dihapus!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/dosen/tugas.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;
            
        case 'nilai_submission':
            $result = $controller->nilaiSubmission($_POST['submission_id'], $_POST['nilai']);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/dosen/tugas.php?detail=' . $_POST['task_id'], 'Nilai submission berhasil!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/dosen/tugas.php?detail=' . $_POST['task_id'], 'Gagal: ' . $result['message'], 'error');
            }
            break;
    }
}

$tugas = $controller->getAllTugas();
$detail_id = $_GET['detail'] ?? 0;
$detail_submissions = [];

if ($detail_id) {
    $detail_tugas = $controller->getTugasById($detail_id);
    $detail_submissions = $controller->getSubmissions($detail_id);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-file-alt"></i> Daftar Tugas</h3>
        <button class="btn btn-primary" onclick="document.getElementById('modalTambah').style.display='block'">
            + Buat Tugas Baru
        </button>
    </div>
    
    <?php if (empty($tugas)): ?>
        <p style="text-align: center; color: #7f8c8d; padding: 20px;">
            Belum ada tugas yang dibuat
        </p>
    <?php else: ?>
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Judul</th>
                    <th>Mata Kuliah</th>
                    <th>Deadline</th>
                    <th>Submit</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tugas as $t): 
                    $is_expired = strtotime($t['deadline']) < time();
                    $status = $is_expired ? '<i class="fas fa-clock"></i> Expired' : '<i class="fas fa-circle"></i> Aktif';
                    $status_color = $is_expired ? '#e74c3c' : '#2ecc71';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['judul']) ?></strong></td>
                        <td><?= htmlspecialchars($t['kode_mk']) ?></td>
                        <td><?= date('d-m-Y H:i', strtotime($t['deadline'])) ?></td>
                        <td><?= $t['jumlah_submit'] ?? 0 ?></td>
                        <td>
                            <span style="font-weight: 600; font-size: 12px;">
                                <?= $status ?>
                            </span>
                        </td>
                        <td>
                            <a href="?detail=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-list-ul"></i> Detail</a>
                            <button class="btn btn-danger btn-sm btn-delete" onclick="deleteTugas(<?= $t['id'] ?>)"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($detail_id && $detail_tugas): ?>
<div class="table-container" style="margin-top: 20px;">
    <div class="table-header">
        <h3><i class="fas fa-list-ul"></i> Detail Tugas: <?= htmlspecialchars($detail_tugas['judul']) ?></h3>
        <a href="?detail=0" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Tutup</a>
    </div>
    
    <div style="padding: 10px 0;">
        <p><strong>Mata Kuliah:</strong> <?= htmlspecialchars($detail_tugas['kode_mk']) ?> - <?= htmlspecialchars($detail_tugas['nama_mk']) ?></p>
        <p><strong>Deadline:</strong> <?= date('d-m-Y H:i', strtotime($detail_tugas['deadline'])) ?></p>
        <p><strong>Deskripsi:</strong> <?= nl2br(htmlspecialchars($detail_tugas['deskripsi'])) ?></p>
        <p><strong>Bobot Nilai:</strong> <?= $detail_tugas['bobot_nilai'] ?>%</p>
    </div>
    
    <h4 style="margin-top: 15px;"><i class="fas fa-download"></i> Submission Mahasiswa</h4>
    <?php if (empty($detail_submissions)): ?>
        <p style="color: #7f8c8d; padding: 15px;">Belum ada submission</p>
    <?php else: ?>
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Nilai</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail_submissions as $sub): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sub['nim']) ?></strong></td>
                        <td><?= htmlspecialchars($sub['mahasiswa_nama']) ?></td>
                        <td>
                            <span style="font-weight: 600; font-size: 12px;">
                                <?= ucfirst($sub['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sub['nilai'] !== null): ?>
                                <strong><?= $sub['nilai'] ?></strong>
                            <?php else: ?>
                                <span style="color: #7f8c8d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sub['status'] !== 'draft'): ?>
                                <button class="btn btn-primary btn-sm" onclick="nilaiSubmission(<?= $sub['id'] ?>, <?= $detail_id ?>)"><i class="fas fa-edit"></i> Nilai</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div id="modalTambah" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999;">
    <div class="modal-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);"></div>
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 30px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin-bottom: 20px;"><i class="fas fa-plus-circle"></i> Buat Tugas Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label class="form-label">Mata Kuliah</label>
                <select class="form-select" name="course_id" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($matakuliah as $mk): ?>
                        <option value="<?= $mk['id'] ?>">
                            <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Judul Tugas</label>
                <input class="form-control" type="text" name="judul" required>
            </div>
            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea class="form-control" name="deskripsi" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Deadline</label>
                <input class="form-control" type="datetime-local" name="deadline" required>
            </div>
            <div class="form-group">
                <label class="form-label">Bobot Nilai (%)</label>
                <input class="form-control" type="number" name="bobot_nilai" min="0" max="100" value="0">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<div id="modalNilai" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999;">
    <div class="modal-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);"></div>
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 30px; max-width: 400px; width: 90%;">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-edit"></i> Beri Nilai</h3>
        <form method="POST">
            <input type="hidden" name="action" value="nilai_submission">
            <input type="hidden" name="submission_id" id="nilaiSubmissionId">
            <input type="hidden" name="task_id" id="nilaiTaskId">
            <div class="form-group">
                <label class="form-label">Nilai (0-100)</label>
                <input class="form-control" type="number" name="nilai" min="0" max="100" step="0.01" required>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalNilai').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteTugas(id) {
    if (confirm('Yakin hapus tugas ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function nilaiSubmission(submissionId, taskId) {
    document.getElementById('nilaiSubmissionId').value = submissionId;
    document.getElementById('nilaiTaskId').value = taskId;
    document.getElementById('modalNilai').style.display = 'block';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>