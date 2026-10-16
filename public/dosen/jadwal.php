<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';

checkAccess(['dosen']);

$page_title = 'Jadwal Mengajar';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$controller = new DosenController($pdo, $dosen['id']);
$jadwal = $controller->getJadwalMengajar();

$hari_urutan = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$jadwal_group = [];

foreach ($hari_urutan as $hari) {
    $jadwal_group[$hari] = array_filter($jadwal, function($item) use ($hari) {
        return $item['hari'] === $hari;
    });
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-calendar-alt"></i> Jadwal Mengajar</h3>
        <small style="color: #7f8c8d;">Semester <?= date('Y') ?></small>
    </div>
    
    <div class="jadwal-grid">
        <?php foreach ($hari_urutan as $hari): ?>
            <div class="jadwal-day">
                <h4>
                    <?= $hari ?>
                </h4>
                
                <?php if (empty($jadwal_group[$hari])): ?>
                    <p class="jadwal-kosong">- Kosong -</p>
                <?php else: ?>
                    <?php foreach ($jadwal_group[$hari] as $jk): ?>
                        <div class="jadwal-item">
                            <div class="jd-kode">
                                <?= htmlspecialchars($jk['kode_mk']) ?>
                            </div>
                            <div class="jd-nama">
                                <?= htmlspecialchars($jk['nama_mk']) ?>
                            </div>
                            <div class="jd-meta">
                                <i class="fas fa-clock"></i> <?= date('H:i', strtotime($jk['jam_mulai'])) ?> - <?= date('H:i', strtotime($jk['jam_selesai'])) ?>
                            </div>
                            <div class="jd-meta">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($jk['ruang'] ?? 'TBD') ?>
                            </div>
                            <div class="jd-meta">
                                <i class="fas fa-users"></i> <?= $jk['jumlah_mahasiswa'] ?? 0 ?> mahasiswa
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>