<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Dashboard Admin';
$adminController = new AdminController($pdo);
$stats = $adminController->getDashboardStats();

// Nama depan untuk sambutan
$nama_depan = explode(' ', trim($_SESSION['nama']))[0];

// Total tugas menunggu diproses
$total_pending = (int)$stats['irs_pending'] + (int)$stats['nilai_draft'];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Banner sambutan -->
<div class="dash-hero">
    <div>
        <h2><i class="fas fa-hand-sparkles"></i> Halo, <?= htmlspecialchars($nama_depan) ?>!</h2>
        <p class="dash-hero-sub">
            <?php if ($total_pending > 0): ?>
                Ada <strong><?= (int)$stats['irs_pending'] ?></strong> IRS dan
                <strong><?= (int)$stats['nilai_draft'] ?></strong> nilai 0 yang menunggu diverifikasi.
                Silakan proses lewat menu <strong>Persetujuan</strong>.
            <?php else: ?>
                Semua pengajuan IRS dan nilai 0 sudah diproses. Kerja bagus! 🎉
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Statistik -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-number"><?= $stats['total_mahasiswa'] ?></div>
        <div class="stat-label">Mahasiswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-secondary"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="stat-number"><?= $stats['total_dosen'] ?></div>
        <div class="stat-label">Dosen</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-success"><i class="fas fa-book-open"></i></div>
        <div class="stat-number"><?= $stats['total_matakuliah'] ?></div>
        <div class="stat-label">Mata Kuliah</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $stats['irs_pending'] > 0 ? 'icon-warning' : 'icon-danger' ?>">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-number <?= $stats['irs_pending'] > 0 ? 'color-warning' : '' ?>">
            <?= $stats['irs_pending'] ?>
        </div>
        <div class="stat-label">IRS Menunggu Validasi</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $stats['nilai_draft'] > 0 ? 'icon-warning' : 'icon-danger' ?>">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-number <?= $stats['nilai_draft'] > 0 ? 'color-warning' : '' ?>">
            <?= $stats['nilai_draft'] ?>
        </div>
        <div class="stat-label">Nilai 0 Perlu Verifikasi</div>
    </div>
</div>

<!-- Aksi Cepat -->
<div class="card">
    <div class="card-body">
        <div class="table-header">
            <h3><i class="fas fa-bolt"></i> Aksi Cepat</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;">
            <a href="/simak_app/public/admin/validasi_irs.php" class="quick-action qa-warning">
                <i class="fas fa-file-signature"></i>
                <span>Validasi IRS</span>
            </a>
            <a href="/simak_app/public/admin/verifikasi_nilai.php" class="quick-action qa-danger">
                <i class="fas fa-clipboard-check"></i>
                <span>Verifikasi Nilai 0</span>
            </a>
            <a href="/simak_app/public/admin/mahasiswa.php" class="quick-action">
                <i class="fas fa-user-graduate"></i>
                <span>Data Mahasiswa</span>
            </a>
            <a href="/simak_app/public/admin/dosen.php" class="quick-action qa-secondary">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Data Dosen</span>
            </a>
            <a href="/simak_app/public/admin/jadwal.php" class="quick-action qa-secondary">
                <i class="fas fa-calendar-alt"></i>
                <span>Data Jadwal</span>
            </a>
            <a href="/simak_app/public/admin/laporan.php" class="quick-action qa-success">
                <i class="fas fa-table"></i>
                <span>Laporan Akademik</span>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
