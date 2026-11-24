<?php
// ============================================
// public/mahasiswa/dashboard.php
// Dashboard Mahasiswa
// ============================================

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

$nama_depan = explode(' ', trim($_SESSION['nama']))[0];
$ipk_class = $stats['ipk'] >= 3.5 ? 'color-success' : ($stats['ipk'] >= 2.5 ? 'color-warning' : 'color-danger');

include __DIR__ . '/../../includes/header.php';
?>

<div class="welcome-bar">
    <div>
        <h2>Selamat datang, <?= htmlspecialchars($nama_depan) ?></h2>
        <p>
            Semester <?= (int)$mahasiswa['semester'] ?> &middot;
            <?php if ($stats['tugas_mendatang'] > 0): ?>
                <strong><?= (int)$stats['tugas_mendatang'] ?> tugas</strong> mendekati tenggat.
            <?php else: ?>
                Tidak ada tugas yang mendekati tenggat.
            <?php endif; ?>
            <?php if ($stats['belum_dibaca'] > 0): ?>
                <strong><?= (int)$stats['belum_dibaca'] ?> pengumuman</strong> belum dibaca.
            <?php endif; ?>
        </p>
    </div>
    <span class="welcome-date"><i class="far fa-calendar"></i> <?= date('d F Y') ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-number"><?= $stats['total_sks'] ?></div>
        <div class="stat-label">Total SKS</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-secondary"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-number"><?= $stats['total_matakuliah'] ?></div>
        <div class="stat-label">Mata Kuliah</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $stats['tugas_mendatang'] > 0 ? 'icon-warning' : 'icon-success' ?>"><i class="fas fa-tasks"></i></div>
        <div class="stat-number <?= $stats['tugas_mendatang'] > 0 ? 'color-warning' : '' ?>"><?= $stats['tugas_mendatang'] ?></div>
        <div class="stat-label">Tugas Mendatang</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-secondary"><i class="fas fa-star"></i></div>
        <div class="stat-number <?= $ipk_class ?>"><?= number_format($stats['ipk'], 2) ?></div>
        <div class="stat-label">Indeks Prestasi Kumulatif</div>
    </div>
</div>

<div class="split-grid">
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-clock"></i> Tugas Mendekat</h3>
        </div>
        <?php if (empty($tugas_mendekat)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>Tidak ada tugas mendekat</h3>
                <p>Semua tugas masih memiliki waktu yang cukup.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
                <?php foreach ($tugas_mendekat as $t): ?>
                    <?php
                    $jam = (int)$t['jam_tersisa'];
                    $urgensi = $jam < 24 ? 'is-late' : ($jam < 48 ? 'is-soon' : 'is-normal');
                    ?>
                    <div class="feed-item">
                        <div class="feed-main">
                            <span class="feed-title"><?= htmlspecialchars($t['judul']) ?></span>
                            <span class="feed-text"><?= htmlspecialchars($t['kode_mk']) ?> &middot; <?= htmlspecialchars($t['nama_mk']) ?></span>
                        </div>
                        <span class="deadline <?= $urgensi ?>">
                            <?= floor($jam / 24) ?> hari <?= $jam % 24 ?> jam
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="feed-footer">
                <a href="/simak_app/public/mahasiswa/tugas.php">Lihat semua tugas <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-calendar-day"></i> Jadwal Hari Ini</h3>
            <span class="welcome-date"><?= date('d-m-Y') ?></span>
        </div>
        <?php if (empty($jadwal_hari_ini)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <h3>Tidak ada jadwal hari ini</h3>
                <p>Jadwal kuliah akan tampil di sini.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
                <?php foreach ($jadwal_hari_ini as $j): ?>
                    <div class="feed-item">
                        <div class="feed-main">
                            <span class="feed-title"><?= htmlspecialchars($j['kode_mk']) ?> &middot; <?= htmlspecialchars($j['nama_mk']) ?></span>
                            <span class="feed-text">
                                <?= htmlspecialchars($j['dosen_nama'] ?? '-') ?>
                                <?= !empty($j['ruang']) ? ' · ' . htmlspecialchars($j['ruang']) : '' ?>
                            </span>
                        </div>
                        <span class="feed-tag">
                            <?= date('H:i', strtotime($j['jam_mulai'])) ?>–<?= date('H:i', strtotime($j['jam_selesai'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="feed-footer">
                <a href="/simak_app/public/mahasiswa/jadwal.php">Lihat jadwal lengkap <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</h3>
        <?php if ($stats['belum_dibaca'] > 0): ?>
            <span class="feed-tag tag-new"><?= (int)$stats['belum_dibaca'] ?> belum dibaca</span>
        <?php endif; ?>
    </div>
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Belum ada pengumuman</h3>
            <p>Pengumuman dari admin dan dosen akan tampil di sini.</p>
        </div>
    <?php else: ?>
        <div class="feed-list">
            <?php foreach ($announcements as $ann): ?>
                <div class="feed-item <?= $ann['is_read'] ? '' : 'is-unread' ?>">
                    <div class="feed-main">
                        <span class="feed-title">
                            <?= htmlspecialchars($ann['judul']) ?>
                            <?php if ($ann['priority'] === 'high'): ?><span class="feed-tag tag-important">Penting</span><?php endif; ?>
                            <?php if (!$ann['is_read']): ?><span class="feed-tag tag-new">Baru</span><?php endif; ?>
                        </span>
                        <span class="feed-text"><?= nl2br(htmlspecialchars(mb_substr($ann['pesan'], 0, 180))) ?><?= mb_strlen($ann['pesan']) > 180 ? '…' : '' ?></span>
                        <span class="feed-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($ann['sender_name']) ?></span>
                            <span><i class="far fa-clock"></i> <?= date('d-m-Y H:i', strtotime($ann['created_at'])) ?></span>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
