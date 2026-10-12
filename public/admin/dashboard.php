<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Dashboard Admin';
$adminController = new AdminController($pdo);
$stats = $adminController->getDashboardStats();

include __DIR__ . '/../../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-number"><?= $stats['total_mahasiswa'] ?></div>
        <div class="stat-label">Mahasiswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="stat-number"><?= $stats['total_dosen'] ?></div>
        <div class="stat-label">Dosen</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        <div class="stat-number"><?= $stats['total_matakuliah'] ?></div>
        <div class="stat-label">Mata Kuliah</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-number <?= $stats['irs_pending'] > 0 ? 'color-warning' : '' ?>">
            <?= $stats['irs_pending'] ?>
        </div>
        <div class="stat-label">IRS Menunggu Validasi</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="stat-number <?= $stats['nilai_draft'] > 0 ? 'color-warning' : '' ?>">
            <?= $stats['nilai_draft'] ?>
        </div>
        <div class="stat-label">Nilai 0 Perlu Verifikasi</div>
    </div>
</div>

<div class="row row-2">
    <!-- Aksi Cepat -->
    <div class="card">
        <div class="card-title"><i class="fas fa-bolt"></i> Aksi Cepat</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
            <a href="/simak_app/public/admin/validasi_irs.php" class="quick-action">
                <i class="fas fa-file-signature"></i>
                <span>Validasi IRS</span>
            </a>
            <a href="/simak_app/public/admin/verifikasi_nilai.php" class="quick-action">
                <i class="fas fa-clipboard-check"></i>
                <span>Verifikasi Nilai 0</span>
            </a>
            <a href="/simak_app/public/admin/mahasiswa.php" class="quick-action">
                <i class="fas fa-user-graduate"></i>
                <span>Data Mahasiswa</span>
            </a>
            <a href="/simak_app/public/admin/jadwal.php" class="quick-action">
                <i class="fas fa-calendar-alt"></i>
                <span>Data Jadwal</span>
            </a>
            <a href="/simak_app/public/admin/laporan.php" class="quick-action">
                <i class="fas fa-table"></i>
                <span>Laporan Akademik</span>
            </a>
        </div>
    </div>

    <!-- Sambutan -->
    <div class="card">
        <div class="card-title"><i class="fas fa-user-circle"></i> Selamat Datang</div>
        <p class="text-muted" style="font-size: 14px;">
            Halo, <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong>!
        </p>
        <p class="text-muted" style="font-size: 14px;">
            <?php if ($stats['irs_pending'] > 0 || $stats['nilai_draft'] > 0): ?>
                Ada <strong><?= $stats['irs_pending'] ?></strong> IRS dan
                <strong><?= $stats['nilai_draft'] ?></strong> nilai 0 yang menunggu diverifikasi.
            <?php else: ?>
                Semua pengajuan IRS dan nilai 0 sudah diproses. Kerja bagus! 🎉
            <?php endif; ?>
        </p>
        <?php if ($stats['irs_pending'] > 0 || $stats['nilai_draft'] > 0): ?>
            <a href="/simak_app/public/admin/validasi_irs.php" class="btn btn-primary btn-sm" style="margin-right: 8px;">
                <i class="fas fa-file-signature"></i> Proses IRS
            </a>
            <a href="/simak_app/public/admin/verifikasi_nilai.php" class="btn btn-primary btn-sm">
                <i class="fas fa-clipboard-check"></i> Verifikasi Nilai 0
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
.quick-action {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 18px 10px;
    background: #f8f9fa;
    border-radius: 10px;
    text-decoration: none;
    color: var(--secondary);
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    transition: var(--transition);
}

.quick-action i {
    font-size: 22px;
    color: var(--primary);
    transition: var(--transition);
}

.quick-action:hover {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary-dark);
    transform: translateY(-2px);
}

.quick-action:hover i {
    transform: scale(1.1);
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
