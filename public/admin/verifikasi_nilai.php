<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Verifikasi Nilai 0';
$adminController = new AdminController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect = '/simak_app/public/admin/verifikasi_nilai.php';

    // Validasi CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage($redirect, 'Sesi tidak valid, silakan coba lagi.', 'error');
    }

    switch ($action) {
        case 'verify':
            // Validasi: grade_id wajib angka
            $grade_id = intval($_POST['grade_id'] ?? 0);
            if ($grade_id <= 0) {
                redirectWithMessage($redirect, 'ID nilai tidak valid.', 'error');
            }

            $result = $adminController->verifyGrade($grade_id);
            if ($result['success']) {
                redirectWithMessage($redirect, 'Nilai berhasil diverifikasi!', 'success');
            } else {
                redirectWithMessage($redirect, 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'verify_all':
            // Validasi: course_id wajib angka
            $course_id = intval($_POST['course_id'] ?? 0);
            if ($course_id <= 0) {
                redirectWithMessage($redirect, 'ID mata kuliah tidak valid.', 'error');
            }

            $result = $adminController->verifyAllGrades($course_id);
            if ($result['success']) {
                redirectWithMessage($redirect, $result['message'] ?? ($result['affected'] . ' nilai berhasil diverifikasi!'), 'success');
            } else {
                redirectWithMessage($redirect, 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'reject':
            // Kembalikan nilai ke dosen dengan catatan
            $grade_id = intval($_POST['grade_id'] ?? 0);
            if ($grade_id <= 0) {
                redirectWithMessage($redirect, 'ID nilai tidak valid.', 'error');
            }

            $catatan = trim($_POST['catatan'] ?? '');
            $result = $adminController->rejectGrade($grade_id, $catatan !== '' ? $catatan : null);
            if ($result['success']) {
                redirectWithMessage($redirect, 'Nilai dikembalikan ke dosen.', 'success');
            } else {
                redirectWithMessage($redirect, 'Gagal: ' . $result['message'], 'error');
            }
            break;

        default:
            redirectWithMessage($redirect, 'Aksi tidak dikenali.', 'error');
    }
}

$stats = $adminController->getNilaiStats();
$courses_with_draft = $adminController->getCoursesWithDraft();

// CSRF token untuk form verifikasi
$csrf_token = generateCsrfToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="alert alert-info">
    <span class="alert-icon"><i class="fas fa-info-circle"></i></span>
    Nilai yang diinput dosen langsung terverifikasi otomatis. Hanya nilai dengan komponen <strong>0 (nol)</strong> yang memerlukan verifikasi Admin di halaman ini.
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-pen"></i></div>
        <div class="stat-number color-warning"><?= $stats['draft'] ?></div>
        <div class="stat-label">Nilai 0 Perlu Verifikasi</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-number color-success"><?= $stats['verified'] ?></div>
        <div class="stat-label">Terverifikasi</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        <div class="stat-number"><?= $stats['courses_with_draft'] ?></div>
        <div class="stat-label">Mata Kuliah</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-clipboard-check"></i> Nilai 0 Menunggu Verifikasi</h3>
        <?php if (!empty($courses_with_draft)): ?>
            <input class="form-control" type="text" id="verifSearch" placeholder="Cari mata kuliah atau dosen..." style="max-width: 280px;">
        <?php endif; ?>
    </div>

    <?php if (empty($courses_with_draft)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>Tidak ada nilai 0 yang menunggu verifikasi</h3>
            <p>Semua nilai yang diinput dosen sudah diproses.</p>
        </div>
    <?php else: ?>
        <?php foreach ($courses_with_draft as $course): ?>
            <?php
            $draft_grades = $adminController->getDraftGradesByCourse($course['id']);
            $jumlah_lengkap = 0;
            $jumlah_belum = 0;
            foreach ($draft_grades as $g) {
                $lengkap = ($g['nilai_tugas'] !== null && $g['nilai_uts'] !== null && $g['nilai_uas'] !== null);
                if ($lengkap) $jumlah_lengkap++; else $jumlah_belum++;
            }
            ?>
            <div class="irs-group" data-name="<?= htmlspecialchars(strtolower($course['kode_mk'] . ' ' . $course['nama_mk'] . ' ' . ($course['dosen_nama'] ?? ''))) ?>">
                <div class="irs-group-header">
                    <div>
                        <h4 class="irs-group-title">
                            <?= htmlspecialchars($course['kode_mk']) ?> · <?= htmlspecialchars($course['nama_mk']) ?>
                        </h4>
                        <small class="irs-group-meta">
                            Dosen: <?= htmlspecialchars($course['dosen_nama'] ?? '-') ?>
                            · <?= $jumlah_lengkap ?> siap diverifikasi
                            <?php if ($jumlah_belum > 0): ?>
                                · <span class="text-warning"><?= $jumlah_belum ?> belum lengkap</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if ($jumlah_lengkap > 0): ?>
                        <form method="POST" onsubmit="return confirm('Verifikasi semua nilai lengkap untuk <?= htmlspecialchars($course['kode_mk'], ENT_QUOTES) ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="verify_all">
                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check-double"></i> Verifikasi Semua
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Mahasiswa</th>
                            <th>Semester</th>
                            <th>Tugas</th>
                            <th>UTS</th>
                            <th>UAS</th>
                            <th>Akhir</th>
                            <th class="th-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($draft_grades as $g):
                            $belum_lengkap = ($g['nilai_tugas'] === null || $g['nilai_uts'] === null || $g['nilai_uas'] === null);
                            // Komponen yang bernilai 0 (penyebab nilai perlu diverifikasi)
                            $komponen_nol = [];
                            if (floatval($g['nilai_tugas']) == 0) $komponen_nol[] = 'Tugas';
                            if (floatval($g['nilai_uts']) == 0) $komponen_nol[] = 'UTS';
                            if (floatval($g['nilai_uas']) == 0) $komponen_nol[] = 'UAS';
                        ?>
                            <tr<?= $belum_lengkap ? ' class="row-muted"' : '' ?>>
                                <td><strong><?= htmlspecialchars($g['nim']) ?></strong></td>
                                <td><?= htmlspecialchars($g['mahasiswa_nama']) ?></td>
                                <td><?= htmlspecialchars($g['semester'] ?? '-') ?></td>
                                <td><?= $g['nilai_tugas'] !== null ? number_format((float)$g['nilai_tugas'], 1) : '–' ?></td>
                                <td><?= $g['nilai_uts'] !== null ? number_format((float)$g['nilai_uts'], 1) : '–' ?></td>
                                <td><?= $g['nilai_uas'] !== null ? number_format((float)$g['nilai_uas'], 1) : '–' ?></td>
                                <td><strong><?= $g['nilai_akhir'] !== null ? number_format((float)$g['nilai_akhir'], 1) : '–' ?></strong></td>
                                <td class="td-actions">
                                    <?php if ($belum_lengkap): ?>
                                        <span class="text-muted" style="font-size: 12px;">Belum lengkap</span>
                                    <?php else: ?>
                                        <?php if (!empty($komponen_nol)): ?>
                                            <span style="font-size: 11px; margin-right: 6px; color: #7f8c8d; font-weight: 600;">
                                                Nilai 0: <?= htmlspecialchars(implode(', ', $komponen_nol)) ?>
                                            </span>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <input type="hidden" name="grade_id" value="<?= $g['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Verifikasi</button>
                                        </form>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                                onclick="showRejectGrade(<?= $g['id'] ?>, '<?= htmlspecialchars($g['nim'] . ' — ' . $g['mahasiswa_nama'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Kembalikan Nilai ke Dosen -->
<div id="modalRejectGrade" class="modal">
    <div class="modal-overlay" onclick="closeRejectGradeModal()"></div>
    <div class="modal-content" style="max-width: 460px;">
        <div class="modal-header">
            <h3><i class="fas fa-undo" style="color: var(--warning);"></i> Kembalikan ke Dosen</h3>
            <button type="button" class="modal-close" onclick="closeRejectGradeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="grade_id" id="rejectGradeId">
            <div class="form-group">
                <label class="form-label">Mahasiswa</label>
                <input class="form-control" type="text" id="rejectGradeInfo" value="" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan <span class="text-muted" style="font-weight: 400;">(opsional, untuk dosen)</span></label>
                <textarea class="form-control" name="catatan" id="rejectGradeCatatan" placeholder="Contoh: Nilai UAS mahasiswa ini perlu dicek ulang" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeRejectGradeModal()">Batal</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> Kembalikan</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectGrade(gradeId, info) {
    document.getElementById('rejectGradeId').value = gradeId;
    document.getElementById('rejectGradeInfo').value = info;
    document.getElementById('rejectGradeCatatan').value = '';
    document.getElementById('modalRejectGrade').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRejectGradeModal() {
    document.getElementById('modalRejectGrade').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRejectGradeModal();
});

// Filter kartu mata kuliah
const verifSearch = document.getElementById('verifSearch');
if (verifSearch) {
    verifSearch.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.irs-group').forEach(function(card) {
            const match = !q || card.dataset.name.includes(q);
            card.style.display = match ? '' : 'none';
        });
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
