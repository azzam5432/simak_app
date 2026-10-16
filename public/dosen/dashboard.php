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

include __DIR__ . '/../../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $stats['total_kelas'] ?></div>
        <div class="stat-label"><i class="fas fa-book"></i> Kelas Diampu</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $stats['total_mahasiswa'] ?></div>
        <div class="stat-label"><i class="fas fa-user-graduate"></i> Total Mahasiswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: <?= $stats['tugas_aktif'] > 0 ? '#f39c12' : '#2ecc71' ?>">
            <?= $stats['tugas_aktif'] ?>
        </div>
        <div class="stat-label"><i class="fas fa-file-alt"></i> Tugas Aktif</div>
    </div>
    <div class="stat-card">
        <div class="stat-number" style="color: <?= $stats['presensi_hari_ini'] > 0 ? '#2ecc71' : '#95a5a6' ?>">
            <?= $stats['presensi_hari_ini'] ?>
        </div>
        <div class="stat-label"><i class="fas fa-check"></i> Presensi Hari Ini</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-calendar-alt"></i> Jadwal Mengajar Hari Ini - <?= date('d-m-Y') ?></h3>
    </div>
    
    <?php if (empty($jadwal_hari_ini)): ?>
        <div style="text-align: center; padding: 30px; color: #7f8c8d;">
            <h3><i class="fas fa-smile"></i> Tidak ada jadwal mengajar hari ini</h3>
            <p>Silakan periksa jadwal lengkap di menu Jadwal Mengajar</p>
        </div>
    <?php else: ?>
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Mata Kuliah</th>
                    <th>SKS</th>
                    <th>Ruang</th>
                    <th>Jam</th>
                    <th>Mahasiswa</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jadwal_hari_ini as $jk): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($jk['kode_mk']) ?></strong></td>
                        <td><?= htmlspecialchars($jk['nama_mk']) ?></td>
                        <td><?= $jk['sks'] ?></td>
                        <td><?= htmlspecialchars($jk['ruang'] ?? '-') ?></td>
                        <td>
                            <?= date('H:i', strtotime($jk['jam_mulai'])) ?> - 
                            <?= date('H:i', strtotime($jk['jam_selesai'])) ?>
                        </td>
                        <td><?= $jk['jumlah_mahasiswa'] ?? 0 ?> orang</td>
                        <td>
                            <a href="/simak_app/public/dosen/presensi.php?course_id=<?= $jk['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-clipboard-list"></i> Presensi</a>
                            <a href="/simak_app/public/dosen/nilai.php?course_id=<?= $jk['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-edit"></i> Nilai</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-bullhorn"></i> Pengumuman Terbaru</h3>
    </div>
    <?php
    $announcements = $controller->getReceivedAnnouncements();
    if (empty($announcements)):
    ?>
        <p style="color: #7f8c8d; text-align: center; padding: 15px;">Belum ada pengumuman</p>
    <?php else: ?>
        <?php foreach (array_slice($announcements, 0, 3) as $ann): ?>
            <div style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; <?= !$ann['is_read'] ? 'background: #f0f7ff;' : '' ?>">
                <div style="display: flex; justify-content: space-between;">
                    <strong><?= htmlspecialchars($ann['judul']) ?></strong>
                    <small style="color: #7f8c8d;"><?= date('d-m-Y H:i', strtotime($ann['created_at'])) ?></small>
                </div>
                <p style="margin: 5px 0 0; font-size: 14px; color: #555;">
                    <?= htmlspecialchars(substr($ann['pesan'], 0, 100)) ?>...
                </p>
                <small style="color: #7f8c8d;">Dari: <?= htmlspecialchars($ann['sender_name']) ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>