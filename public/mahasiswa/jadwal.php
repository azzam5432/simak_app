<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'Jadwal Kuliah';

$stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$controller = new MahasiswaController($pdo, $mahasiswa['id']);
$jadwal = $controller->getJadwalKuliah();

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
        <h3><i class="fas fa-calendar-alt"></i> Jadwal Kuliah</h3>
        <small style="color: #7f8c8d;">Semester <?= date('Y') ?></small>
    </div>
    
    <div class="jadwal-grid">
        <?php foreach ($hari_urutan as $hari): ?>
            <div class="jadwal-day<?= date('N') == array_search($hari, $hari_urutan) + 1 ? ' today' : '' ?>">
                <h4>
                    <?= $hari ?>
                    <?php if (date('N') == array_search($hari, $hari_urutan) + 1): ?>
                        <span class="jadwal-today-label">(Hari Ini)</span>
                    <?php endif; ?>
                </h4>
                
                <?php if (empty($jadwal_group[$hari])): ?>
                    <p class="jadwal-kosong">- Kosong -</p>
                <?php else: ?>
                    <?php foreach ($jadwal_group[$hari] as $j): ?>
                        <div class="jadwal-item">
                            <div class="jd-kode">
                                <?= htmlspecialchars($j['kode_mk']) ?>
                            </div>
                            <div class="jd-nama">
                                <?= htmlspecialchars($j['nama_mk']) ?>
                            </div>
                            <div class="jd-meta">
                                <i class="fas fa-clock"></i> <?= date('H:i', strtotime($j['jam_mulai'])) ?> - <?= date('H:i', strtotime($j['jam_selesai'])) ?>
                            </div>
                            <div class="jd-meta">
                                <i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($j['dosen_nama'] ?? '-') ?>
                            </div>
                            <div class="jd-meta">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($j['ruang'] ?? '-') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>