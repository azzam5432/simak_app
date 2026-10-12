<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Validasi IRS';
$adminController = new AdminController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect = '/simak_app/public/admin/validasi_irs.php';

    // Validasi CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage($redirect, 'Sesi tidak valid, silakan coba lagi.', 'error');
    }

    switch ($action) {
        case 'approve':
            $irs_id = intval($_POST['irs_id'] ?? 0);
            if ($irs_id <= 0) {
                redirectWithMessage($redirect, 'ID IRS tidak valid.', 'error');
            }

            $result = $adminController->approveIRS($irs_id);
            if ($result['success']) {
                redirectWithMessage($redirect, 'IRS berhasil disetujui!', 'success');
            } else {
                redirectWithMessage($redirect, 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'reject':
            $irs_id = intval($_POST['irs_id'] ?? 0);
            if ($irs_id <= 0) {
                redirectWithMessage($redirect, 'ID IRS tidak valid.', 'error');
            }

            $catatan = trim($_POST['catatan'] ?? '');
            $result = $adminController->rejectIRS($irs_id, $catatan !== '' ? $catatan : null);
            if ($result['success']) {
                redirectWithMessage($redirect, 'IRS ditolak.', 'success');
            } else {
                redirectWithMessage($redirect, 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'approve_all':
            $mahasiswa_id = intval($_POST['mahasiswa_id'] ?? 0);
            $semester = trim($_POST['semester'] ?? '');
            if ($mahasiswa_id <= 0 || $semester === '') {
                redirectWithMessage($redirect, 'Data tidak valid.', 'error');
            }

            $result = $adminController->approveAllIRS($mahasiswa_id, $semester);
            if ($result['success']) {
                redirectWithMessage($redirect, $result['affected'] . ' IRS berhasil disetujui!', 'success');
            } else {
                redirectWithMessage($redirect, 'Gagal: ' . $result['message'], 'error');
            }
            break;

        default:
            redirectWithMessage($redirect, 'Aksi tidak dikenali.', 'error');
    }
}

$pending_irs = $adminController->getPendingIRS();
$stats = $adminController->getIRSStats();

$grouped_irs = [];
foreach ($pending_irs as $irs) {
    $key = $irs['mahasiswa_id'] . '_' . $irs['semester'];
    if (!isset($grouped_irs[$key])) {
        $grouped_irs[$key] = [
            'mahasiswa_id' => $irs['mahasiswa_id'],
            'mahasiswa_nama' => $irs['mahasiswa_nama'],
            'nim' => $irs['nim'],
            'semester' => $irs['semester'],
            'jurusan_nama' => $irs['jurusan_nama'] ?? null,
            'items' => []
        ];
    }
    $grouped_irs[$key]['items'][] = $irs;
}

// CSRF token untuk semua form
$csrf_token = generateCsrfToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-number color-warning"><?= $stats['pending'] ?></div>
        <div class="stat-label">Menunggu Validasi</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-number color-success"><?= $stats['approved'] ?></div>
        <div class="stat-label">Disetujui</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div class="stat-number color-danger"><?= $stats['rejected'] ?></div>
        <div class="stat-label">Ditolak</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-file-signature"></i> Pengajuan IRS</h3>
        <?php if (!empty($grouped_irs)): ?>
            <input type="text" id="irsSearch" placeholder="Cari nama atau NIM..." style="max-width: 260px;">
        <?php endif; ?>
    </div>

    <?php if (empty($grouped_irs)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>Semua IRS telah divalidasi</h3>
            <p>Tidak ada pengajuan IRS yang menunggu persetujuan.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_irs as $group): ?>
            <?php $total_sks_group = array_sum(array_column($group['items'], 'sks')); ?>
            <div class="irs-group" data-name="<?= htmlspecialchars(strtolower($group['mahasiswa_nama'] . ' ' . $group['nim'])) ?>">
                <div class="irs-group-header">
                    <div>
                        <h4 class="irs-group-title">
                            <?= htmlspecialchars($group['mahasiswa_nama']) ?>
                            <span class="irs-group-nim"><?= htmlspecialchars($group['nim']) ?></span>
                        </h4>
                        <small class="irs-group-meta">
                            Semester <?= htmlspecialchars($group['semester']) ?>
                            <?= $group['jurusan_nama'] ? ' · ' . htmlspecialchars($group['jurusan_nama']) : '' ?>
                            · <?= count($group['items']) ?> mata kuliah
                            · Total <strong><?= $total_sks_group ?> SKS</strong>
                        </small>
                    </div>
                    <form method="POST" onsubmit="return confirm('Setujui semua IRS <?= htmlspecialchars($group['mahasiswa_nama'], ENT_QUOTES) ?> (Semester <?= htmlspecialchars($group['semester'], ENT_QUOTES) ?>)?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="approve_all">
                        <input type="hidden" name="mahasiswa_id" value="<?= $group['mahasiswa_id'] ?>">
                        <input type="hidden" name="semester" value="<?= htmlspecialchars($group['semester']) ?>">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-check-double"></i> Setujui Semua
                        </button>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Mata Kuliah</th>
                            <th>SKS</th>
                            <th class="th-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['items'] as $irs): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($irs['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($irs['nama_mk']) ?></td>
                                <td><?= $irs['sks'] ?></td>
                                <td class="td-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="irs_id" value="<?= $irs['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Setuju</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="showRejectForm(<?= $irs['id'] ?>, '<?= htmlspecialchars($irs['kode_mk'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Tolak IRS -->
<div id="modalReject" class="modal">
    <div class="modal-overlay" onclick="closeRejectModal()"></div>
    <div class="modal-content" style="max-width: 460px;">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color: var(--danger);"></i> Tolak IRS</h3>
            <button type="button" class="modal-close" onclick="closeRejectModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="irs_id" id="rejectIrsId">
            <div class="form-group">
                <label>Mata Kuliah</label>
                <input type="text" id="rejectInfo" value="" disabled>
            </div>
            <div class="form-group">
                <label>Catatan <span class="text-muted" style="font-weight: 400;">(opsional, diteruskan ke mahasiswa)</span></label>
                <textarea name="catatan" id="rejectCatatan" placeholder="Contoh: Bentrok dengan jadwal kelas lain" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Tolak IRS</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectForm(irsId, kodeMk) {
    document.getElementById('rejectIrsId').value = irsId;
    document.getElementById('rejectInfo').value = kodeMk;
    document.getElementById('rejectCatatan').value = '';
    document.getElementById('modalReject').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRejectModal() {
    document.getElementById('modalReject').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRejectModal();
});

// Filter kartu IRS berdasarkan nama/NIM
const irsSearch = document.getElementById('irsSearch');
if (irsSearch) {
    irsSearch.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.irs-group').forEach(function(card) {
            const match = !q || card.dataset.name.includes(q);
            card.style.display = match ? '' : 'none';
        });
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
