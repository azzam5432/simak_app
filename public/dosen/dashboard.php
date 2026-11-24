<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';

checkAccess(['dosen']);

$page_title = 'Dashboard Dosen';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$dosen_id = $dosen['id'];
$controller = new DosenController($pdo, $dosen_id);

$stats = $controller->getDashboardStats();
$jadwal_hari_ini = $controller->getJadwalHariIni();
$announcements = $controller->getReceivedAnnouncements();

$nama_depan = explode(' ', trim($_SESSION['nama']))[0];

include __DIR__ . '/../../includes/header.php';
?>

<div class="welcome-bar">
    <div>
        <h2>Selamat datang, <?= htmlspecialchars($nama_depan) ?></h2>
        <p>
            <?php if (!empty($jadwal_hari_ini)): ?>
                Anda memiliki <strong><?= count($jadwal_hari_ini) ?> jadwal mengajar</strong> hari ini.
            <?php else: ?>
                Tidak ada jadwal mengajar hari ini.
            <?php endif; ?>
            <?php if ($stats['tugas_aktif'] > 0): ?>
                Terdapat <strong><?= (int)$stats['tugas_aktif'] ?> tugas aktif</strong> yang perlu dipantau.
            <?php endif; ?>
        </p>
    </div>
    <span class="welcome-date"><i class="far fa-calendar"></i> <?= date('d F Y') ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-number"><?= $stats['total_kelas'] ?></div>
        <div class="stat-label">Kelas Diampu</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-secondary"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-number"><?= $stats['total_mahasiswa'] ?></div>
        <div class="stat-label">Total Mahasiswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $stats['tugas_aktif'] > 0 ? 'icon-warning' : 'icon-success' ?>"><i class="fas fa-file-alt"></i></div>
        <div class="stat-number <?= $stats['tugas_aktif'] > 0 ? 'color-warning' : '' ?>"><?= $stats['tugas_aktif'] ?></div>
        <div class="stat-label">Tugas Aktif</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-success"><i class="fas fa-check"></i></div>
        <div class="stat-number"><?= $stats['presensi_hari_ini'] ?></div>
        <div class="stat-label">Presensi Hari Ini</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-calendar-alt"></i> Jadwal Mengajar Hari Ini</h3>
        <span class="welcome-date"><?= date('d-m-Y') ?></span>
    </div>

    <?php if (empty($jadwal_hari_ini)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-check"></i>
            <h3>Tidak ada jadwal mengajar hari ini</h3>
            <p>Silakan periksa jadwal lengkap di menu Jadwal Mengajar.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Mata Kuliah</th>
                        <th>SKS</th>
                        <th>Ruang</th>
                        <th>Jam</th>
                        <th>Mahasiswa</th>
                        <th class="th-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jadwal_hari_ini as $jk): ?>
                        <tr>
                            <td><span class="cell-strong"><?= htmlspecialchars($jk['kode_mk']) ?></span></td>
                            <td><?= htmlspecialchars($jk['nama_mk']) ?></td>
                            <td><?= $jk['sks'] ?></td>
                            <td><?= htmlspecialchars($jk['ruang'] ?? '-') ?></td>
                            <td><?= date('H:i', strtotime($jk['jam_mulai'])) ?>–<?= date('H:i', strtotime($jk['jam_selesai'])) ?></td>
                            <td><?= $jk['jumlah_mahasiswa'] ?? 0 ?> orang</td>
                            <td class="td-actions">
                                <div class="row-actions">
                                    <a href="/simak_app/public/dosen/presensi.php?course_id=<?= $jk['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-clipboard-list"></i> Presensi
                                    </a>
                                    <a href="/simak_app/public/dosen/nilai.php?course_id=<?= $jk['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Nilai
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</h3>
    </div>
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Belum ada pengumuman</h3>
            <p>Pengumuman dari admin akan tampil di sini.</p>
        </div>
    <?php else: ?>
        <div class="feed-list">
            <?php foreach (array_slice($announcements, 0, 3) as $ann): ?>
                <div class="feed-item <?= $ann['is_read'] ? '' : 'is-unread' ?>">
                    <div class="feed-main">
                        <span class="feed-title">
                            <?= htmlspecialchars($ann['judul']) ?>
                            <?php if (!$ann['is_read']): ?><span class="feed-tag tag-new">Baru</span><?php endif; ?>
                        </span>
                        <span class="feed-text"><?= htmlspecialchars(mb_substr($ann['pesan'], 0, 140)) ?><?= mb_strlen($ann['pesan']) > 140 ? '…' : '' ?></span>
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
