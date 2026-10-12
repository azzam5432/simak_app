<?php
// ============================================
// public/mahasiswa/report_nilai.php
// Report nilai mahasiswa per semester & semua semester + cetak PDF
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'Report Nilai';

$stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$controller = new MahasiswaController($pdo, $mahasiswa['id']);

$semesters = $controller->getSemestersWithGrades();

// Semester filter (kosong = semua semester -> IPK)
$semester = trim($_GET['semester'] ?? '');
$semester = $semester !== '' ? $semester : null;

$rows = $controller->getKHS(null);
if ($semester) {
    $rows = array_values(array_filter($rows, function ($r) use ($semester) {
        return $r['semester'] == $semester;
    }));
}

// IPS per semester (dari semua data, seperti tampilan KHS)
$ips_data = [];
$total_sks = 0;
$total_bobot = 0.0;
$jumlah_mk = count($rows);

$per_sem = [];
foreach ($rows as $r) {
    $nf = floatval($r['nilai_final']);
    $bobot = ($nf >= 85) ? 4.0 : (($nf >= 75) ? 3.0 : (($nf >= 65) ? 2.0 : (($nf >= 50) ? 1.0 : 0.0)));
    $sem = $r['semester'];
    if (!isset($per_sem[$sem])) {
        $per_sem[$sem] = ['sks' => 0, 'bobot' => 0.0, 'mk' => 0];
    }
    $per_sem[$sem]['sks'] += intval($r['sks']);
    $per_sem[$sem]['bobot'] += $bobot * intval($r['sks']);
    $per_sem[$sem]['mk'] += 1;
    $total_sks += intval($r['sks']);
    $total_bobot += $bobot * intval($r['sks']);
}
ksort($per_sem);
foreach ($per_sem as $s => &$d) {
    $d['ips'] = ($d['sks'] > 0) ? round($d['bobot'] / $d['sks'], 2) : 0;
    unset($d['bobot']);
}
unset($d);
$ips_data = $per_sem;

// IPS / IPK yang ditampilkan
if ($semester) {
    $nilai_gpa = $ips_data[$semester]['ips'] ?? 0;
    $nilai_gpa_label = 'IPS Semester ' . htmlspecialchars($semester);
} else {
    $nilai_gpa = ($total_sks > 0) ? round($total_bobot / $total_sks, 2) : 0;
    $nilai_gpa_label = 'IPK Semua Semester';
}

function warnaGpa($gpa) {
    if ($gpa >= 3.0) return '#27ae60';
    if ($gpa >= 2.5) return '#d68910';
    return '#c0392b';
}

function predikatGpa($gpa) {
    if ($gpa >= 3.5) return 'Sangat Baik';
    if ($gpa >= 3.0) return 'Baik';
    if ($gpa >= 2.5) return 'Cukup';
    return 'Kurang';
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.filter-bar-mhs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}
.filter-bar-mhs select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    min-width: 180px;
    background: #fff;
    font-size: 14px;
}
</style>

<div class="table-container">

    <!-- Filter -->
    <form method="GET" class="filter-bar-mhs">
        <div class="form-group" style="min-width: 160px;">
            <label style="font-size: 13px;">Semester</label>
            <select name="semester" onchange="this.form.submit()">
                <option value="">-- Semua Semester (IPK) --</option>
                <?php foreach ($semesters as $s): ?>
                    <option value="<?= $s['semester'] ?>" <?= $semester == $s['semester'] ? 'selected' : '' ?>>
                        Semester <?= $s['semester'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Tampilkan
            </button>
            <a href="/simak_app/public/mahasiswa/cetak_pdf.php?type=report_nilai&semester=<?= urlencode($semester ?? '') ?>" target="_blank"
                class="btn btn-outline btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
        </div>
    </form>

    <!-- Ringkasan -->
    <div class="stats-grid" style="margin-bottom: 20px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-number" style="color: <?= warnaGpa($nilai_gpa) ?>;">
                <?= number_format($nilai_gpa, 2) ?>
            </div>
            <div class="stat-label"><?= $nilai_gpa_label ?> · <?= predikatGpa($nilai_gpa) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-book"></i></div>
            <div class="stat-number"><?= $jumlah_mk ?></div>
            <div class="stat-label">Mata Kuliah Tampil</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            <div class="stat-number"><?= $total_sks ?></div>
            <div class="stat-label">Total SKS Tampil</div>
        </div>
    </div>

    <?php if (!$semester && !empty($ips_data)): ?>
        <!-- IPS per semester -->
        <h4 style="display: flex; align-items: center; gap: 8px; margin: 0 0 10px; color: var(--secondary);">
            <i class="fas fa-chart-bar"></i> IPS per Semester
        </h4>
        <div style="overflow-x: auto; margin-bottom: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Semester</th>
                        <th>Jumlah MK</th>
                        <th>Total SKS</th>
                        <th>IPS</th>
                        <th>Predikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ips_data as $s => $info): ?>
                        <tr>
                            <td><strong>Semester <?= $s ?></strong></td>
                            <td><?= $info['mk'] ?></td>
                            <td><?= $info['sks'] ?></td>
                            <td><strong style="color: <?= warnaGpa($info['ips']) ?>; font-size: 15px;">
                                <?= number_format($info['ips'], 2) ?>
                            </strong></td>
                            <td class="text-muted"><?= predikatGpa($info['ips']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f8f9fa;">
                        <td colspan="2" style="text-align: right;"><strong>IPK Keseluruhan:</strong></td>
                        <td><strong><?= $total_sks ?></strong></td>
                        <td><strong style="color: <?= warnaGpa($nilai_gpa) ?>; font-size: 15px;">
                            <?= number_format($nilai_gpa, 2) ?>
                        </strong></td>
                        <td class="text-muted">Semua Semester</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Detail Nilai -->
    <h4 style="display: flex; align-items: center; gap: 8px; margin: 0 0 10px; color: var(--secondary);">
        <i class="fas fa-list"></i> Detail Nilai
        <span class="text-muted" style="font-weight: 400; font-size: 13px;">
            (<?= $semester ? 'Semester ' . htmlspecialchars($semester) : 'Semua Semester' ?>)
        </span>
    </h4>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Belum ada nilai terverifikasi</h3>
            <p>Nilai akan muncul setelah dosen menginput dan admin memverifikasi.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Smt</th>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th>SKS</th>
                        <th>Tugas</th>
                        <th>UTS</th>
                        <th>UAS</th>
                        <th>Absensi</th>
                        <th>Akhir</th>
                        <th>Huruf</th>
                        <th>Bobot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $nf = floatval($r['nilai_final']);
                        $bobot = ($nf >= 85) ? 4.0 : (($nf >= 75) ? 3.0 : (($nf >= 65) ? 2.0 : (($nf >= 50) ? 1.0 : 0.0)));
                        $huruf_warna = $nf >= 85 ? '#27ae60' : ($nf >= 75 ? '#2980b9' : ($nf >= 65 ? '#d68910' : ($nf >= 50 ? '#ca6f1e' : '#c0392b')));
                        $huruf = $nf >= 85 ? 'A' : ($nf >= 75 ? 'B' : ($nf >= 65 ? 'C' : ($nf >= 50 ? 'D' : 'E')));
                        $total_pertemuan = intval($r['total_pertemuan']);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($r['semester']) ?></td>
                            <td><strong><?= htmlspecialchars($r['kode_mk']) ?></strong></td>
                            <td><?= htmlspecialchars($r['nama_mk']) ?></td>
                            <td><?= $r['sks'] ?></td>
                            <td><?= number_format($r['nilai_tugas'], 1) ?></td>
                            <td><?= number_format($r['nilai_uts'], 1) ?></td>
                            <td><?= number_format($r['nilai_uas'], 1) ?></td>
                            <td style="white-space: nowrap;">
                                <?php if ($total_pertemuan > 0): ?>
                                    <span style="color: <?= floatval($r['kehadiran_persen']) >= 75 ? '#27ae60' : '#e74c3c' ?>;">
                                        <?= number_format($r['kehadiran_persen'], 1) ?>%
                                    </span>
                                    <span style="display: block; font-size: 11px; color: #7f8c8d;">
                                        H: <?= $r['p_hadir'] ?> · I: <?= $r['p_izin'] ?> · S: <?= $r['p_sakit'] ?> · A: <?= $r['p_alpa'] ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($nf, 1) ?></strong></td>
                            <td><strong style="color: <?= $huruf_warna ?>;"><?= $huruf ?></strong></td>
                            <td><?= number_format($bobot, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="3" style="text-align: right;">Total</td>
                        <td><?= $total_sks ?></td>
                        <td colspan="7"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
