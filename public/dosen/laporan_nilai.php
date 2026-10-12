<?php
// ============================================
// public/dosen/laporan_nilai.php
// Laporan nilai mahasiswa pada mata kuliah yang diampu
// (per semester & semua semester) + cetak PDF
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';
require_once __DIR__ . '/../../controllers/LaporanController.php';

checkAccess(['dosen']);

$page_title = 'Laporan Nilai';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$controller = new DosenController($pdo, $dosen['id']);

$matakuliah = $controller->getMatakuliahDenganSemester();

$course_id = intval($_GET['course_id'] ?? 0) ?: null;
$semester = trim($_GET['semester'] ?? '');
$semester = $semester !== '' ? $semester : null;

// Daftar semester yang tersedia pada mata kuliah terpilih
$semester_options = [];
foreach ($matakuliah as $mk) {
    if ($course_id && intval($mk['id']) !== intval($course_id)) continue;
    if (!in_array($mk['semester'], $semester_options)) {
        $semester_options[] = $mk['semester'];
    }
}
sort($semester_options);

$data = [];
$mk_info = null;
$statistik = ['jumlah_mhs' => 0, 'rata_rata' => null, 'jumlah_a' => 0, 'jumlah_b' => 0, 'jumlah_c' => 0, 'jumlah_d' => 0, 'jumlah_e' => 0, 'ips' => null];

if ($course_id) {
    $data = $controller->getLaporanNilaiMahasiswaAmpu($course_id, $semester);

    if (!empty($data)) {
        $mk_info = $data[0];

        // Statistik
        $sum_akhir = 0;
        $total_sks = 0;
        $total_bobot = 0.0;
        foreach ($data as $row) {
            $tp = intval($row['total_pertemuan']);
            $kh = ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null;
            $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']), $kh);
            $bobot = LaporanController::nilaiKeBobot($nf);
            
            if ($nf >= 85) $statistik['jumlah_a']++;
            elseif ($nf >= 75) $statistik['jumlah_b']++;
            elseif ($nf >= 65) $statistik['jumlah_c']++;
            elseif ($nf >= 50) $statistik['jumlah_d']++;
            else $statistik['jumlah_e']++;
            
            $sum_akhir += $nf;
            $total_sks += intval($row['sks']);
            $total_bobot += ($bobot * intval($row['sks']));
        }
        $statistik['jumlah_mhs'] = count($data);
        $statistik['rata_rata'] = round($sum_akhir / $statistik['jumlah_mhs'], 2);
        $statistik['ips'] = ($total_sks > 0) ? round($total_bobot / $total_sks, 2) : null;
    }
}

function nilaiHurufDosen($nilai) {
    if ($nilai === null || $nilai === '') return ['-', '#95a5a6'];
    if ($nilai >= 85) return ['A', '#27ae60'];
    elseif ($nilai >= 75) return ['B', '#2980b9'];
    elseif ($nilai >= 65) return ['C', '#d68910'];
    elseif ($nilai >= 50) return ['D', '#ca6f1e'];
    else return ['E', '#c0392b'];
}

function warnaIps($ips) {
    if ($ips === null) return '#95a5a6';
    if ($ips >= 3.0) return '#27ae60';
    if ($ips >= 2.5) return '#d68910';
    return '#c0392b';
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.filter-bar-dosen {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}
.filter-bar-dosen select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    min-width: 200px;
    background: #fff;
    font-size: 14px;
}
.mini-stat {
    display: inline-block;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: 8px 15px;
    border-radius: 8px;
    margin: 5px 5px 0 0;
    text-align: center;
    min-width: 90px;
}
.mini-stat .num { font-size: 18px; font-weight: 700; color: var(--secondary); }
.mini-stat .lbl { font-size: 11px; color: #7f8c8d; }
</style>

<!-- Statistik Umum -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        <div class="stat-number"><?= count($matakuliah) ?></div>
        <div class="stat-label">Mata Kuliah Dinilai</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-number"><?= $course_id && $statistik['jumlah_mhs'] ? $statistik['jumlah_mhs'] : '–' ?></div>
        <div class="stat-label">Mahasiswa Dinilai (filter aktif)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-number"><?= $statistik['rata_rata'] !== null ? number_format($statistik['rata_rata'], 2) : '–' ?></div>
        <div class="stat-label">Rata-rata Nilai</div>
    </div>
</div>

<div class="table-container">

    <!-- Filter -->
    <form method="GET" class="filter-bar-dosen">
        <div class="form-group" style="flex: 1; min-width: 240px;">
            <label style="font-size: 13px;">Mata Kuliah</label>
            <select name="course_id" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php foreach ($matakuliah as $mk): ?>
                    <option value="<?= $mk['id'] ?>" <?= $course_id == $mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="min-width: 140px;">
            <label style="font-size: 13px;">Semester</label>
            <select name="semester">
                <option value="">-- Semua Semester --</option>
                <?php foreach ($semester_options as $s): ?>
                    <option value="<?= $s ?>" <?= $semester == $s ? 'selected' : '' ?>>Smt <?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Tampilkan
            </button>
            <?php if ($course_id && !empty($data)): ?>
                <a href="/simak_app/public/dosen/cetak_pdf.php?type=nilai_ampu&course_id=<?= urlencode($course_id) ?>&semester=<?= urlencode($semester ?? '') ?>" target="_blank"
                    class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$course_id): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>Pilih Mata Kuliah</h3>
            <p>Pilih mata kuliah yang Anda ampu untuk melihat report nilai mahasiswa per semester atau semua semester.</p>
        </div>
    <?php elseif (empty($data)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Tidak ada data nilai</h3>
            <p>Belum ada nilai terverifikasi untuk mata kuliah ini pada semester <?= htmlspecialchars($semester ?? 'terpilih') ?>.</p>
        </div>
    <?php else: ?>

        <!-- Info MK + statistik -->
        <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: var(--secondary);">
            <i class="fas fa-book-open"></i>
            <?= htmlspecialchars($mk_info['kode_mk']) ?> - <?= htmlspecialchars($mk_info['nama_mk']) ?>
            <span class="text-muted" style="font-weight: 400; font-size: 13px;">
                (<?= $semester ? 'Semester ' . htmlspecialchars($semester) : 'Semua Semester' ?>)
            </span>
        </h4>
        <div style="margin-bottom: 20px;">
            <div class="mini-stat">
                <div class="num"><?= $statistik['jumlah_mhs'] ?></div>
                <div class="lbl">Mahasiswa</div>
            </div>
            <div class="mini-stat">
                <div class="num" style="color: <?= warnaIps($statistik['rata_rata'] >= 75 ? 3.0 : ($statistik['rata_rata'] >= 65 ? 2.5 : 2.0)) ?>;">
                    <?= number_format($statistik['rata_rata'], 2) ?>
                </div>
                <div class="lbl">Rata-rata</div>
            </div>
            <div class="mini-stat">
                <div class="num" style="color: <?= warnaIps($statistik['ips']) ?>;">
                    <?= $statistik['ips'] !== null ? number_format($statistik['ips'], 2) : '–' ?>
                </div>
                <div class="lbl">IPS / Bobot</div>
            </div>
            <div class="mini-stat"><div class="num" style="color: #27ae60;"><?= $statistik['jumlah_a'] ?></div><div class="lbl">A</div></div>
            <div class="mini-stat"><div class="num" style="color: #2980b9;"><?= $statistik['jumlah_b'] ?></div><div class="lbl">B</div></div>
            <div class="mini-stat"><div class="num" style="color: #d68910;"><?= $statistik['jumlah_c'] ?></div><div class="lbl">C</div></div>
            <div class="mini-stat"><div class="num" style="color: #ca6f1e;"><?= $statistik['jumlah_d'] ?></div><div class="lbl">D</div></div>
            <div class="mini-stat"><div class="num" style="color: #c0392b;"><?= $statistik['jumlah_e'] ?></div><div class="lbl">E</div></div>
        </div>

        <!-- Detail Nilai -->
        <table>
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Mahasiswa</th>
                    <th>Prodi</th>
                    <th>Smt</th>
                    <th>Tugas</th>
                    <th>UTS</th>
                    <th>UAS</th>
                    <th>Akhir</th>
                    <th>Huruf</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row):
                    $tp = intval($row['total_pertemuan']);
                    $kh = ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null;
                    $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']), $kh);
                    list($huruf, $warna) = nilaiHurufDosen($nf);
                    $verified = ($row['status_verifikasi'] === 'verified');
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                        <td><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                        <td><?= htmlspecialchars($row['program_studi'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['semester']) ?></td>
                        <td><?= number_format($row['nilai_tugas'], 1) ?></td>
                        <td><?= number_format($row['nilai_uts'], 1) ?></td>
                        <td><?= number_format($row['nilai_uas'], 1) ?></td>
                        <td><strong><?= number_format($nf, 1) ?></strong></td>
                        <td><strong style="color: <?= $warna ?>;"><?= $huruf ?></strong></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:6px;color:<?= $verified ? '#27ae60' : '#95a5a6' ?>;font-weight:600;font-size:13px;">
                                <i class="fas <?= $verified ? 'fa-check-circle' : 'fa-circle-notch' ?>"></i>
                                <?= $verified ? 'Terverifikasi' : 'Draft' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa; font-weight: bold;">
                    <td colspan="7" style="text-align: right;">Rata-rata / IPS:</td>
                    <td><?= $statistik['rata_rata'] !== null ? number_format($statistik['rata_rata'], 2) : '–' ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
