<?php
// ============================================
// public/dosen/laporan_absensi.php
// Report absensi mahasiswa pada mata kuliah yang diampu dosen
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';

checkAccess(['dosen']);

$page_title = 'Laporan Absensi';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$controller = new DosenController($pdo, $dosen['id']);

$semester_list = $controller->getSemesterDosen();
$semester = trim($_GET['semester'] ?? '');
if ($semester !== '' && !in_array($semester, array_map('strval', $semester_list), true)) {
    $semester = '';
}

$matakuliah = $controller->getMatakuliahDosen($semester !== '' ? $semester : null);

$course_id = intval($_GET['course_id'] ?? 0);
$selected_mk = null;
foreach ($matakuliah as $mk) {
    if (intval($mk['id']) === $course_id) {
        $selected_mk = $mk;
        break;
    }
}

// Export PDF hanya untuk mata kuliah yang dipilih
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    if (!$selected_mk) {
        redirectWithMessage('/simak_app/public/dosen/laporan_absensi.php', 'Pilih mata kuliah terlebih dahulu', 'error');
    }

    require_once __DIR__ . '/../../controllers/PdfController.php';
    $pdf = new PdfController($pdo);
    $pdf->generateLaporanAbsensiDosen($dosen['id'], $course_id, $selected_mk['semester']);
    exit;
}

$data = [];
$pertemuan = [];
$statistik = ['jumlah_mhs' => 0, 'rata_kehadiran' => null, 'total_pertemuan' => 0];

if ($selected_mk) {
    $data = $controller->getLaporanAbsensiMahasiswaAmpu($course_id);
    $pertemuan = $controller->getPertemuanAbsensi($course_id);

    $sum_persen = 0;
    $jumlah_terdata = 0;
    foreach ($data as $row) {
        $total = intval($row['total_pertemuan']);
        if ($total > 0) {
            $sum_persen += (($row['hadir'] + $row['izin'] + $row['sakit']) / $total) * 100;
            $jumlah_terdata++;
        }
    }
    $statistik['jumlah_mhs'] = count($data);
    $statistik['rata_kehadiran'] = ($jumlah_terdata > 0) ? round($sum_persen / $jumlah_terdata, 2) : null;
    $statistik['total_pertemuan'] = count($pertemuan);
}

function warnaKehadiran($persen) {
    if ($persen === null) return '#95a5a6';
    if ($persen >= 75) return '#27ae60';
    if ($persen >= 50) return '#d68910';
    return '#c0392b';
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Statistik Umum -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        <div class="stat-number"><?= count($matakuliah) ?></div>
        <div class="stat-label">Mata Kuliah Diampu</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-number"><?= $selected_mk ? $statistik['total_pertemuan'] : '–' ?></div>
        <div class="stat-label">Pertemuan Tercatat</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-number">
            <?= $statistik['rata_kehadiran'] !== null ? number_format($statistik['rata_kehadiran'], 1) . '%' : '–' ?>
        </div>
        <div class="stat-label">Rata-rata Kehadiran</div>
    </div>
</div>

<div class="table-container">
    <!-- Filter -->
    <form method="GET" class="filter-bar">
        <div class="form-group" style="min-width: 140px;">
            <label class="form-label" style="font-size: 13px;">Semester</label>
            <select class="form-select" name="semester" onchange="this.form.submit()">
                <option value="">Semua Semester</option>
                <?php foreach ($semester_list as $smt): ?>
                    <option value="<?= htmlspecialchars($smt) ?>" <?= $semester === (string) $smt ? 'selected' : '' ?>>
                        Semester <?= htmlspecialchars($smt) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex: 1; min-width: 240px;">
            <label class="form-label" style="font-size: 13px;">Mata Kuliah</label>
            <select class="form-select" name="course_id" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php foreach ($matakuliah as $mk): ?>
                    <option value="<?= $mk['id'] ?>" <?= $course_id == $mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?> (Semester <?= htmlspecialchars($mk['semester']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Tampilkan
            </button>
            <?php if ($selected_mk): ?>
                <a href="?export=pdf&amp;course_id=<?= urlencode($selected_mk['id']) ?>&amp;semester=<?= urlencode($selected_mk['semester']) ?>"
                   target="_blank" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$selected_mk): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>Pilih Mata Kuliah</h3>
            <p>Pilih mata kuliah yang Anda ampu untuk melihat report absensi mahasiswa.</p>
        </div>
    <?php elseif (empty($data)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Belum ada mahasiswa</h3>
            <p>Belum ada mahasiswa dengan IRS approved pada mata kuliah ini.</p>
        </div>
    <?php else: ?>

        <h4 class="section-title d-flex align-items-center gap-2">
            <i class="fas fa-clipboard-list"></i>
            <?= htmlspecialchars($selected_mk['kode_mk']) ?> - <?= htmlspecialchars($selected_mk['nama_mk']) ?>
            <span class="text-muted" style="font-weight: 400; font-size: 13px;">
                (Semester <?= htmlspecialchars($selected_mk['semester']) ?>)
            </span>
        </h4>

        <div style="margin-bottom: 20px;">
            <div class="stat-chip">
                <div class="num"><?= $statistik['jumlah_mhs'] ?></div>
                <div class="lbl">Mahasiswa</div>
            </div>
            <div class="stat-chip">
                <div class="num"><?= $statistik['total_pertemuan'] ?></div>
                <div class="lbl">Pertemuan</div>
            </div>
            <div class="stat-chip">
                <div class="num" style="color: <?= warnaKehadiran($statistik['rata_kehadiran']) ?>;">
                    <?= $statistik['rata_kehadiran'] !== null ? number_format($statistik['rata_kehadiran'], 1) . '%' : '–' ?>
                </div>
                <div class="lbl">Rata-rata Hadir</div>
            </div>
        </div>

        <!-- Rekap per mahasiswa -->
        <h4 class="section-title">
            <i class="fas fa-users"></i> Rekap Absensi Mahasiswa
        </h4>
        <div style="overflow-x: auto;">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Mahasiswa</th>
                        <th>Prodi</th>
                        <th class="text-center">Pertemuan</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Izin</th>
                        <th class="text-center">Sakit</th>
                        <th class="text-center">Alpa</th>
                        <th>Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row):
                        $total = intval($row['total_pertemuan']);
                        $persen = ($total > 0)
                            ? round((($row['hadir'] + $row['izin'] + $row['sakit']) / $total) * 100, 2)
                            : null;
                        $warna = warnaKehadiran($persen);
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                            <td><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                            <td><?= htmlspecialchars($row['program_studi'] ?? '-') ?></td>
                            <td class="text-center"><?= $total ?></td>
                            <td class="text-center text-success fw-semibold"><?= $row['hadir'] ?></td>
                            <td class="text-center text-info"><?= $row['izin'] ?></td>
                            <td class="text-center text-warning"><?= $row['sakit'] ?></td>
                            <td class="text-center text-danger"><?= $row['alpa'] ?></td>
                            <td>
                                <?php if ($persen === null): ?>
                                    <span class="text-muted">Belum ada data</span>
                                <?php else: ?>
                                    <strong style="color: <?= $warna ?>;"><?= number_format($persen, 1) ?>%</strong>
                                    <div class="progress-meter">
                                        <span style="width: <?= $persen ?>%; background: <?= $warna ?>;"></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Ringkasan per pertemuan -->
        <?php if (!empty($pertemuan)): ?>
            <h4 class="section-title mt-4">
                <i class="fas fa-calendar-alt"></i> Absensi per Pertemuan
            </h4>
            <div style="overflow-x: auto;">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Pertemuan</th>
                            <th>Tanggal</th>
                            <th class="text-center">Tercatat</th>
                            <th class="text-center">Hadir</th>
                            <th class="text-center">Izin</th>
                            <th class="text-center">Sakit</th>
                            <th class="text-center">Alpa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pertemuan as $index => $p): ?>
                            <tr>
                                <td><strong><?= $index + 1 ?></strong></td>
                                <td><?= date('d-m-Y', strtotime($p['tanggal'])) ?></td>
                                <td class="text-center"><?= $p['total'] ?></td>
                                <td class="text-center text-success"><?= $p['hadir'] ?></td>
                                <td class="text-center text-info"><?= $p['izin'] ?></td>
                                <td class="text-center text-warning"><?= $p['sakit'] ?></td>
                                <td class="text-center text-danger"><?= $p['alpa'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
