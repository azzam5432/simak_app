<?php
// ============================================
// public/mahasiswa/dashboard.php
// Dashboard Mahasiswa
// ============================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'Dashboard Mahasiswa';

// Get mahasiswa_id dari user_id
$stmt = $pdo->prepare("SELECT id, semester FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan. Silakan hubungi admin.', 'error');
}

$mahasiswa_id = $mahasiswa['id'];
$controller = new MahasiswaController($pdo, $mahasiswa_id);

// Ambil data
$stats = $controller->getDashboardStats();
$jadwal_hari_ini = $controller->getJadwalHariIni();
$announcements = $controller->getAnnouncements(5);
$tugas_mendekat = $controller->getTugasMendekat();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-number"><?= $stats['total_sks'] ?></div>
        <div class="stat-label">Total SKS</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-number"><?= $stats['total_matakuliah'] ?></div>
        <div class="stat-label">Mata Kuliah</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-tasks"></i></div>
        <div class="stat-number" style="color: <?= $stats['tugas_mendatang'] > 0 ? '#e74c3c' : '#2ecc71' ?>">
            <?= $stats['tugas_mendatang'] ?>
        </div>
        <div class="stat-label">Tugas Mendatang</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-number" style="color: <?= $stats['ipk'] >= 3.5 ? '#2ecc71' : ($stats['ipk'] >= 2.5 ? '#f39c12' : '#e74c3c') ?>">
            <?= number_format($stats['ipk'], 2) ?>
        </div>
        <div class="stat-label">IPK</div>
    </div>
</div>

<!-- Tugas Mendekat & Jadwal -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-clock"></i> Tugas Mendekat</h3>
        </div>
        <?php if (empty($tugas_mendekat)): ?>
            <p style="color: #7f8c8d; text-align: center; padding: 15px;">
                <i class="fas fa-check-circle"></i> Tidak ada tugas yang mendekat
            </p>
        <?php else: ?>
            <?php foreach ($tugas_mendekat as $t): ?>
                <?php 
                $jam = $t['jam_tersisa'];
                $warna = $jam < 24 ? '#e74c3c' : ($jam < 48 ? '#f39c12' : '#3498db');
                ?>
                <div style="padding: 10px 15px; border-bottom: 1px solid #e9ecef; border-left: 3px solid <?= $warna ?>;">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?= htmlspecialchars($t['judul']) ?></strong>
                        <small style="color: <?= $warna ?>; font-weight: bold;">
                            <?= floor($jam / 24) ?> hari <?= $jam % 24 ?> jam
                        </small>
                    </div>
                    <div style="font-size: 13px; color: #555;">
                        <?= htmlspecialchars($t['kode_mk']) ?> - <?= htmlspecialchars($t['nama_mk']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div style="text-align: center; padding: 10px;">
            <a href="/simak_app/public/mahasiswa/tugas.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-right"></i> Lihat Semua Tugas
            </a>
        </div>
    </div>
    
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-calendar-day"></i> Jadwal Hari Ini - <?= date('d-m-Y') ?></h3>
        </div>
        <?php if (empty($jadwal_hari_ini)): ?>
            <p style="color: #7f8c8d; text-align: center; padding: 15px;">
                <i class="fas fa-calendar-check"></i> Tidak ada jadwal hari ini
            </p>
        <?php else: ?>
            <?php foreach ($jadwal_hari_ini as $j): ?>
                <div style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?= htmlspecialchars($j['kode_mk']) ?></strong>
                        <span style="color: #2c3e50;">
                            <?= date('H:i', strtotime($j['jam_mulai'])) ?> - 
                            <?= date('H:i', strtotime($j['jam_selesai'])) ?>
                        </span>
                    </div>
                    <div style="font-size: 14px; color: #555;">
                        <?= htmlspecialchars($j['nama_mk']) ?>
                    </div>
                    <div style="font-size: 12px; color: #7f8c8d;">
                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($j['dosen_nama'] ?? '-') ?> 
                        <i class="fas fa-map-pin"></i> <?= htmlspecialchars($j['ruang'] ?? '-') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div style="text-align: center; padding: 10px;">
            <a href="/simak_app/public/mahasiswa/jadwal.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-right"></i> Lihat Semua Jadwal
            </a>
        </div>
    </div>
</div>

<!-- Pengumuman -->
<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-bullhorn"></i> Pengumuman Terbaru 
            <?php if ($stats['belum_dibaca'] > 0): ?>
                <span style="color: #c0392b; font-size: 12px; font-weight: 600;">
                    (<?= $stats['belum_dibaca'] ?> baru)
                </span>
            <?php endif; ?>
        </h3>
    </div>
    <?php if (empty($announcements)): ?>
        <p style="color: #7f8c8d; text-align: center; padding: 15px;">
            <i class="fas fa-inbox"></i> Belum ada pengumuman
        </p>
    <?php else: ?>
        <?php foreach ($announcements as $ann): ?>
            <div style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; <?= !$ann['is_read'] ? 'background: #f0f7ff;' : '' ?>">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong><?= htmlspecialchars($ann['judul']) ?></strong>
                        <?php if ($ann['priority'] === 'high'): ?>
                            <span style="color: #c0392b; font-weight: 600; font-size: 10px;">Penting</span>
                        <?php endif; ?>
                        <?php if (!$ann['is_read']): ?>
                            <span style="color: #2980b9; font-weight: 600; font-size: 10px;">Baru</span>
                        <?php endif; ?>
                    </div>
                    <small style="color: #7f8c8d;"><?= date('d-m-Y H:i', strtotime($ann['created_at'])) ?></small>
                </div>
                <p style="margin: 5px 0 0; font-size: 14px; color: #555;">
                    <?= nl2br(htmlspecialchars(substr($ann['pesan'], 0, 150))) ?>
                    <?php if (strlen($ann['pesan']) > 150): ?>...<?php endif; ?>
                </p>
                <small style="color: #7f8c8d;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($ann['sender_name']) ?>
                </small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>