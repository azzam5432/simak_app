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

<div class="split-grid">
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-check-circle"></i> Presensi Digital</h3>
        </div>

        <?php if ($sesi_aktif): ?>
            <div class="session-panel mb-3">
                <h4><i class="fas fa-circle" style="color: var(--success); font-size: 8px;"></i> Sesi Presensi Aktif</h4>
                <span class="cell-strong d-block"><?= htmlspecialchars($sesi_aktif['kode_mk']) ?> &middot; <?= htmlspecialchars($sesi_aktif['nama_mk']) ?></span>
                <p><i class="fas fa-user-tie"></i> Dosen: <?= htmlspecialchars($sesi_aktif['dosen_nama'] ?? '-') ?></p>
                <p><i class="fas fa-map-pin"></i> Ruang: <?= htmlspecialchars($sesi_aktif['ruang'] ?? '-') ?></p>
                <p><i class="fas fa-clock"></i> Mulai: <?= date('H:i:s', strtotime($sesi_aktif['waktu_mulai'])) ?></p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="konfirmasi">
                <input type="hidden" name="course_id" value="<?= $sesi_aktif['course_id'] ?>">

                <div class="form-group">
                    <label class="form-label" for="kodePresensi">Masukkan kode presensi dari dosen</label>
                    <input class="form-control otp-input" type="text" id="kodePresensi" name="kode"
                           placeholder="000000" required maxlength="6" pattern="[0-9]{6}"
                           inputmode="numeric" autocomplete="one-time-code">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-check"></i> Konfirmasi Kehadiran
                </button>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-hourglass-half"></i>
                <h3>Belum Ada Sesi Presensi</h3>
                <p>Silakan tunggu dosen membuka sesi presensi. Halaman ini menyegarkan otomatis.</p>
                <button onclick="location.reload()" class="btn btn-outline-primary btn-sm mt-3">
                    <i class="fas fa-sync-alt"></i> Refresh Sekarang
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-chart-bar"></i> Rekap Presensi</h3>
        </div>

        <?php if (empty($rekap)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum ada data presensi</h3>
                <p>Rekap akan muncul setelah Anda mengikuti sesi presensi.</p>
            </div>
        <?php else: ?>
            <div class="feed-list">
                <?php foreach ($rekap as $r): ?>
                    <?php
                        $persen = (int) $r['persentase'];
                        $tone = $persen >= 75 ? 'is-good' : ($persen >= 50 ? 'is-fair' : 'is-low');
                    ?>
                    <div class="feed-item">
                        <div class="feed-main">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <span class="cell-strong"><?= htmlspecialchars($r['kode_mk']) ?></span>
                                    <span class="cell-sub"><?= htmlspecialchars($r['nama_mk']) ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="grade-score <?= $tone ?>"><?= $persen ?>%</span>
                                    <span class="cell-sub"><?= $r['hadir'] ?>H / <?= $r['izin'] ?>I / <?= $r['sakit'] ?>S / <?= $r['alpa'] ?>A</span>
                                </div>
                            </div>
                            <div class="progress-meter">
                                <span class="<?= $tone ?>" style="width: <?= $persen ?>%"></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto refresh setiap 30 detik untuk cek sesi baru
setTimeout(function() {
    location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>