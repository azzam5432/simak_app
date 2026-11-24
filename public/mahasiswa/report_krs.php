<?php
// ============================================
// public/mahasiswa/report_krs.php
// Report KRS (Kartu Rencana Studi) mahasiswa per semester + cetak PDF
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'Report KRS';

$stmt = $pdo->prepare("SELECT id, semester as semester_aktif FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$mahasiswa_id = $mahasiswa['id'];
$semester_aktif = intval($mahasiswa['semester_aktif']);
$controller = new MahasiswaController($pdo, $mahasiswa_id);

// Semester yang dipilih (default: semester aktif)
$semester = $_GET['semester'] ?? $semester_aktif;
$semester = ($semester !== '' && ctype_digit((string)$semester) && (int)$semester >= 1 && (int)$semester <= 8)
    ? (string)$semester : (string)$semester_aktif;

$irs_list = $controller->getIRS($semester);
$total_sks = $controller->getTotalSksIRS($semester);
$status_irs = $controller->getIRSStatus($semester);

$akses = $controller->validateAksesSemester($semester);
$adalah_semester_aktif = $akses['boleh'];

$status_meta = [
    'pending'  => ['label' => 'Menunggu Validasi', 'color' => '#d68910'],
    'approved' => ['label' => 'Disetujui',         'color' => '#27ae60'],
    'rejected' => ['label' => 'Ditolak',           'color' => '#c0392b'],
];
$status_akhir = $status_meta[$status_irs] ?? null;

include __DIR__ . '/../../includes/header.php';
?>

<style>
.krs-semester-picker {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    padding: 12px 14px;
    background: var(--surface-muted);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    margin-bottom: 18px;
}
.krs-semester-picker span.krs-title {
    font-weight: 600;
    color: var(--text);
    margin-right: 6px;
    font-size: 12.5px;
}
.krs-sem-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 30px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--gray-dark);
    font-size: 12.5px;
    text-decoration: none;
    font-weight: 600;
}
.krs-sem-btn:hover { border-color: var(--primary); color: var(--primary-dark); }
.krs-sem-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.krs-sem-btn.locked { opacity: 0.55; cursor: not-allowed; color: var(--gray-dark); }
.krs-sem-btn.current { border-color: var(--success); }
</style>

<div class="table-container">

    <div class="table-header">
        <h3><i class="fas fa-id-card"></i> Report Kartu Rencana Studi (KRS)</h3>
    </div>

    <!-- Pilih semester -->
    <div class="krs-semester-picker">
        <span class="krs-title">Semester:</span>
        <?php for ($s = 1; $s <= 8; $s++): ?>
            <?php $is_aktif = ($s == $semester_aktif); ?>
            <?php if ($is_aktif): ?>
                <a href="?semester=<?= $s ?>"
                   class="krs-sem-btn <?= $s == $semester ? 'active' : '' ?> current"
                   title="Semester aktif Anda — IRS dapat diisi di sini">
                    <?= $s ?>
                </a>
            <?php else: ?>
                <span class="krs-sem-btn locked" title="Terkunci — IRS hanya dapat diisi pada semester aktif (<?= $semester_aktif ?>)">
                    <i class="fas fa-lock" style="font-size: 10px;"></i> <?= $s ?>
                </span>
            <?php endif; ?>
        <?php endfor; ?>
        <span class="text-muted small ms-auto">
            <i class="fas fa-info-circle"></i> IRS hanya dapat diisi pada semester aktif Anda
        </span>
    </div>

    <!-- Ringkasan -->
    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            <div class="stat-number"><?= count($irs_list) ?></div>
            <div class="stat-label">Mata Kuliah</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ruler"></i></div>
            <div class="stat-number"><?= $total_sks ?></div>
            <div class="stat-label">Total SKS</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas <?= $status_akhir ? 'fa-check-double' : 'fa-inbox' ?>"></i></div>
            <div class="stat-number" style="font-size: 18px; color: <?= $status_akhir['color'] ?? '#95a5a6' ?>;">
                <?= $status_akhir['label'] ?? 'Belum Ada IRS' ?>
            </div>
            <div class="stat-label">Status IRS Semester <?= $semester ?></div>
        </div>
    </div>

    <?php if (!$adalah_semester_aktif): ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock"></i>
            Mode lihat saja — IRS hanya dapat diisi pada semester aktif Anda (Semester <?= $semester_aktif ?>).
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
        <a href="/simak_app/public/mahasiswa/cetak_pdf.php?type=report_krs&semester=<?= urlencode($semester) ?>" target="_blank"
           class="btn btn-outline-danger btn-sm">
            <i class="fas fa-file-pdf"></i> Cetak PDF KRS
        </a>
    </div>

    <!-- Detail KRS -->
    <?php if (empty($irs_list)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Belum ada mata kuliah di IRS</h3>
            <p>Ambil Paket Pembelajaran pada halaman IRS untuk menyusun KRS semester <?= $semester ?>.</p>
            <a href="/simak_app/public/mahasiswa/irs.php?semester=<?= $semester ?>" class="btn btn-primary btn-sm" style="margin-top: 10px;">
                <i class="fas fa-arrow-right"></i> Ke Halaman IRS
            </a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode</th>
                        <th>Mata Kuliah</th>
                        <th>SKS</th>
                        <th>Dosen Pengampu</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($irs_list as $irs): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($irs['kode_mk']) ?></strong></td>
                            <td>
                                <div><?= htmlspecialchars($irs['nama_mk']) ?></div>
                            </td>
                            <td><?= $irs['sks'] ?></td>
                            <td><?= htmlspecialchars($irs['dosen_nama'] ?? '-') ?></td>
                            <td style="font-size: 12px;">
                                <?php if (!empty($irs['jadwal_info'])): ?>
                                    <?= htmlspecialchars($irs['jadwal_info']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Belum dijadwalkan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?= $status_meta[$irs['status']]['color'] ?? '#7f8c8d' ?>;">
                                    <?= $status_meta[$irs['status']]['label'] ?? ucfirst($irs['status']) ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="row-total">
                        <td colspan="3" style="text-align: right;">Total</td>
                        <td><?= $total_sks ?> SKS</td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
