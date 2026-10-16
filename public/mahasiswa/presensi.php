<?php
// ============================================
// public/mahasiswa/presensi.php
// Presensi Digital Mahasiswa
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'Presensi Digital';

$stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$controller = new MahasiswaController($pdo, $mahasiswa['id']);

// Proses konfirmasi presensi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'konfirmasi') {
    $result = $controller->konfirmasiPresensi($_POST['course_id'], $_POST['kode']);
    if ($result['success']) {
        redirectWithMessage('/simak_app/public/mahasiswa/presensi.php', $result['message'], 'success');
    } else {
        redirectWithMessage('/simak_app/public/mahasiswa/presensi.php', $result['message'], 'error');
    }
}

// AMBIL SESI AKTIF DARI DATABASE
$sesi_aktif = $controller->getActivePresensiSession();
$rekap = $controller->getRekapPresensi();

include __DIR__ . '/../../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <div>
        <div class="table-container">
            <h3><i class="fas fa-check-circle"></i> Presensi Digital</h3>
            
            <?php if ($sesi_aktif): ?>
                <div style="padding: 15px; background: #d4edda; border-radius: 6px; margin-bottom: 15px;">
                    <h4 style="margin: 0; color: #155724;">
                        <i class="fas fa-circle" style="color: #2ecc71;"></i> Sesi Presensi Aktif
                    </h4>
                    <p style="margin: 8px 0; color: #155724;">
                        <strong><?= htmlspecialchars($sesi_aktif['kode_mk']) ?></strong> - 
                        <?= htmlspecialchars($sesi_aktif['nama_mk']) ?>
                    </p>
                    <p style="margin: 5px 0; color: #155724; font-size: 13px;">
                        <i class="fas fa-user-tie"></i> Dosen: <?= htmlspecialchars($sesi_aktif['dosen_nama'] ?? '-') ?>
                    </p>
                    <p style="margin: 5px 0; color: #155724; font-size: 13px;">
                        <i class="fas fa-map-pin"></i> Ruang: <?= htmlspecialchars($sesi_aktif['ruang'] ?? '-') ?>
                    </p>
                    <p style="margin: 5px 0; color: #155724; font-size: 13px;">
                        <i class="fas fa-clock"></i> Mulai: <?= date('H:i:s', strtotime($sesi_aktif['waktu_mulai'])) ?>
                    </p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="konfirmasi">
                    <input type="hidden" name="course_id" value="<?= $sesi_aktif['course_id'] ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Masukkan Kode Presensi dari Dosen</label>
                        <input class="form-control" type="text" name="kode" placeholder="Contoh: 123456" required 
                               style="font-size: 24px; text-align: center; letter-spacing: 8px; padding: 15px; font-weight: bold;"
                               maxlength="6" pattern="[0-9]{6}">
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100" style="padding: 14px; font-size: 16px;">
                        <i class="fas fa-check"></i> Konfirmasi Kehadiran
                    </button>
                </form>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                    <i class="fas fa-hourglass-half" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3>Belum Ada Sesi Presensi</h3>
                    <p>Silakan tunggu dosen membuka sesi presensi.</p>
                    <p style="font-size: 13px; margin-top: 10px;">
                        <i class="fas fa-sync-alt"></i> Refresh halaman ini secara berkala.
                    </p>
                    <button onclick="location.reload()" class="btn btn-primary btn-sm" style="margin-top: 15px;">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div>
        <div class="table-container">
            <h3><i class="fas fa-chart-bar"></i> Rekap Presensi</h3>
            
            <?php if (empty($rekap)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                    <i class="fas fa-inbox"></i> Belum ada data presensi
                </p>
            <?php else: ?>
                <?php foreach ($rekap as $r): ?>
                    <div style="padding: 12px 15px; border-bottom: 1px solid #e9ecef;">
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <strong><?= htmlspecialchars($r['kode_mk']) ?></strong>
                                <div style="font-size: 12px; color: #555;">
                                    <?= htmlspecialchars($r['nama_mk']) ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: bold; color: <?= $r['persentase'] >= 75 ? '#2ecc71' : ($r['persentase'] >= 50 ? '#f39c12' : '#e74c3c') ?>">
                                    <?= $r['persentase'] ?>%
                                </div>
                                <div style="font-size: 11px; color: #7f8c8d;">
                                    <?= $r['hadir'] ?>H / <?= $r['izin'] ?>I / <?= $r['sakit'] ?>S / <?= $r['alpa'] ?>A
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 5px; height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                            <div style="height: 100%; width: <?= $r['persentase'] ?>%; background: <?= $r['persentase'] >= 75 ? '#2ecc71' : ($r['persentase'] >= 50 ? '#f39c12' : '#e74c3c') ?>; border-radius: 2px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto refresh setiap 30 detik untuk cek sesi baru
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>