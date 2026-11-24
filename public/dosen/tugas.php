<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';
require_once __DIR__ . '/../../controllers/NotifikasiController.php';

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
            
        case 'validasi_submission':
            $result = $controller->validasiSubmission($_POST['submission_id']);
            if ($result['success']) {
                NotifikasiController::tugasDivalidasi($pdo, intval($_POST['submission_id']), $dosen['id']);
                redirectWithMessage('/simak_app/public/dosen/tugas.php?detail=' . $_POST['task_id'], 'Tugas mahasiswa berhasil divalidasi!', 'success');
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
    $detail_submissions = $controller->getStatusPengumpulanTugas($detail_id);
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
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>Belum ada tugas</h3>
            <p>Buat tugas baru untuk mulai mengumpulkan pekerjaan mahasiswa.</p>
        </div>
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
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['judul']) ?></strong></td>
                        <td><?= htmlspecialchars($t['kode_mk']) ?></td>
                        <td><?= date('d-m-Y H:i', strtotime($t['deadline'])) ?></td>
                        <td><?= $t['jumlah_submit'] ?? 0 ?></td>
                        <td>
                            <span class="status-badge <?= $is_expired ? 'status-expired' : 'status-active' ?>">
                                <?= $is_expired ? 'Expired' : 'Aktif' ?>
                            </span>
                        </td>
                        <td class="td-actions">
                            <div class="row-actions">
                                <a href="?detail=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-list-ul"></i> Detail</a>
                                <button class="btn btn-danger btn-sm btn-delete" onclick="deleteTugas(<?= $t['id'] ?>)" aria-label="Hapus tugas"><i class="fas fa-trash-alt"></i></button>
                            </div>
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
    
    <div class="mb-3">
        <div class="info-row"><span>Mata Kuliah</span><span><?= htmlspecialchars($detail_tugas['kode_mk']) ?> &middot; <?= htmlspecialchars($detail_tugas['nama_mk']) ?></span></div>
        <div class="info-row"><span>Deadline</span><span><?= date('d-m-Y H:i', strtotime($detail_tugas['deadline'])) ?></span></div>
        <div class="info-row"><span>Bobot Nilai</span><span><?= $detail_tugas['bobot_nilai'] ?>%</span></div>
        <?php if (!empty($detail_tugas['deskripsi'])): ?>
            <div class="mt-3">
                <div class="form-label">Deskripsi</div>
                <p class="feed-text"><?= nl2br(htmlspecialchars($detail_tugas['deskripsi'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <h4 class="section-title"><i class="fas fa-download"></i> Pengumpulan Mahasiswa</h4>
    <?php if (empty($detail_submissions)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>Belum ada mahasiswa</h3>
            <p>Belum ada mahasiswa terdaftar di mata kuliah ini.</p>
        </div>
    <?php else: ?>
        <?php
        $status_pengumpulan = [
            'draft' => ['Draft', 'status-draft'],
            'submitted' => ['Terkirim', 'status-submitted'],
            'late' => ['Terlambat', 'status-pending'],
            'validated' => ['Tervalidasi', 'status-verified'],
        ];
        ?>
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Bukti</th>
                    <th>Nilai</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail_submissions as $sub): ?>
                    <?php
                    $st = $sub['status'] ?? null;
                    list($status_label, $status_class) = $status_pengumpulan[$st] ?? ['Belum mengumpulkan', 'status-draft'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sub['nim']) ?></strong></td>
                        <td><?= htmlspecialchars($sub['mahasiswa_nama']) ?></td>
                        <td>
                            <span class="status-badge <?= $status_class ?>">
                                <?= $status_label ?>
                            </span>
                            <?php if (!empty($sub['submitted_at'])): ?>
                                <div class="stat-sub"><?= date('d-m-Y H:i', strtotime($sub['submitted_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($sub['file_path'])): ?>
                                <button type="button" class="btn btn-secondary btn-sm btn-preview-bukti"
                                        data-bukti-url="/simak_app/public/<?= htmlspecialchars($sub['file_path']) ?>"
                                        data-bukti-label="<?= htmlspecialchars($sub['mahasiswa_nama'] . ' (' . $sub['nim'] . ')') ?>">
                                    <i class="fas fa-image"></i> Lihat Screenshot
                                </button>
                            <?php else: ?>
                                <span class="cell-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sub['nilai'] !== null): ?>
                                <strong><?= $sub['nilai'] ?></strong>
                            <?php else: ?>
                                <span class="cell-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($sub['submission_id']) && $st !== 'draft'): ?>
                                <button class="btn btn-primary btn-sm" onclick="nilaiSubmission(<?= $sub['submission_id'] ?>, <?= $detail_id ?>)"><i class="fas fa-edit"></i> Nilai</button>
                                <?php if ($st === 'submitted' || $st === 'late'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Validasi hasil tugas mahasiswa ini?')">
                                        <input type="hidden" name="action" value="validasi_submission">
                                        <input type="hidden" name="submission_id" value="<?= $sub['submission_id'] ?>">
                                        <input type="hidden" name="task_id" value="<?= $detail_id ?>">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-double"></i> Validasi</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="cell-muted">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div id="modalTambah" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Buat Tugas Baru</h3>
            <button type="button" class="modal-close" aria-label="Tutup" onclick="document.getElementById('modalTambah').style.display='none'">&times;</button>
        </div>
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
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="this.closest('.modal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div id="modalBukti" class="modal" style="z-index: 1000;">
    <div class="modal-overlay" style="background: rgba(15, 23, 32, 0.78);" onclick="tutupModalBukti()"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <strong id="buktiTitle"></strong>
            <button type="button" class="modal-close" onclick="tutupModalBukti()" aria-label="Tutup">&times;</button>
        </div>
        <div style="overflow: auto; text-align: center;">
            <img id="buktiImage" src="" alt="Bukti tugas" style="max-width: 100%; max-height: 72vh; border-radius: 6px;">
        </div>
        <div class="modal-footer">
            <a id="buktiLink" href="#" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-external-link-alt"></i> Buka di tab baru
            </a>
        </div>
    </div>
</div>

<div id="modalNilai" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Beri Nilai</h3>
            <button type="button" class="modal-close" aria-label="Tutup" onclick="document.getElementById('modalNilai').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="nilai_submission">
            <input type="hidden" name="submission_id" id="nilaiSubmissionId">
            <input type="hidden" name="task_id" id="nilaiTaskId">
            <div class="form-group">
                <label class="form-label">Nilai (0-100)</label>
                <input class="form-control" type="number" name="nilai" min="0" max="100" step="0.01" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('modalNilai').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
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

document.querySelectorAll('.btn-preview-bukti').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('buktiTitle').textContent = 'Bukti tugas - ' + this.dataset.buktiLabel;
        document.getElementById('buktiImage').src = this.dataset.buktiUrl;
        document.getElementById('buktiLink').href = this.dataset.buktiUrl;
        document.getElementById('modalBukti').style.display = 'block';
    });
});

function tutupModalBukti() {
    document.getElementById('modalBukti').style.display = 'none';
    document.getElementById('buktiImage').src = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') tutupModalBukti();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>