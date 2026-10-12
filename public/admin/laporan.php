<?php
// ============================================
// public/admin/laporan.php
// Laporan Akademik - Tab Nilai / IRS / Presensi / Mahasiswa
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/LaporanController.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['admin']);

$page_title = 'Laporan Akademik';
$laporanController = new LaporanController($pdo);

$matakuliah = $laporanController->getMatakuliahList();

// ============================================
// AMBIL DATA BERDASARKAN TAB AKTIF
// ============================================
$tab = in_array($_GET['tab'] ?? '', ['nilai', 'rekap_nilai', 'irs', 'presensi', 'mahasiswa'], true) ? $_GET['tab'] : 'nilai';
$stats_umum = $laporanController->getStatistikUmum();

// Data untuk dropdown
$semesters = $laporanController->getSemesterList();
$prodi_list = $laporanController->getProgramStudiList();

// Filter
$course_id = intval($_GET['course_id'] ?? 0) ?: null;
$semester = trim($_GET['semester'] ?? '');
$semester = $semester !== '' ? $semester : null;
$status = trim($_GET['status'] ?? '');
$status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : null;
$jurusan_id = intval($_GET['jurusan_id'] ?? 0) ?: null;
$mahasiswa_id = intval($_GET['mahasiswa_id'] ?? 0) ?: null;

// Data sesuai tab
$data = [];
$statistik = [];
$rekap_irs = [];

switch ($tab) {
    case 'nilai':
        $data = $laporanController->getLaporanNilai($course_id, $semester);
        $statistik = $laporanController->getStatistikNilai($course_id);
        break;
    case 'rekap_nilai':
        $mahasiswa_list = $laporanController->getMahasiswaList();
        // Pastikan mahasiswa yang dipilih valid (exists)
        if ($mahasiswa_id) {
            $found = false;
            foreach ($mahasiswa_list as $m) {
                if (intval($m['id']) === intval($mahasiswa_id)) { $found = true; break; }
            }
            if (!$found) $mahasiswa_id = null;
        }
        $semester_options = [];
        $ips_data = [];
        $ipk_mahasiswa = null;
        $mhs_info = null;
        
        if ($mahasiswa_id) {
            require_once __DIR__ . '/../../controllers/LaporanController.php';
            $mhsController = new MahasiswaController($pdo, $mahasiswa_id);
            $nilai_rows = $mhsController->getKHS(null); // semua semester
            $semester_options = $mhsController->getSemestersWithGrades();
            
            // Info mahasiswa
            foreach ($mahasiswa_list as $m) {
                if (intval($m['id']) === intval($mahasiswa_id)) { $mhs_info = $m; break; }
            }
            
            // Filter rows per semester jika dipilih
            if ($semester) {
                $nilai_rows = array_values(array_filter($nilai_rows, function($r) use ($semester) {
                    return $r['semester'] == $semester;
                }));
            }
            
            // IPS per semester (selalu dari semua data)
            $ips_data = LaporanController::agregasiPerSemester($nilai_rows);
            
            // Hitung IPK dari baris yang tampil
            if ($semester) {
                // hanya semester terpilih -> berarti ini IPS
                $ipk_mahasiswa = $ips_data[$semester]['ips'] ?? 0;
            } else {
                // semua semester -> IPK
                $total_sks = 0; $total_bobot = 0.0;
                foreach ($nilai_rows as $r) {
                    $tp = intval($r['total_pertemuan']);
                    $kh = ($tp > 0) ? ((intval($r['p_hadir']) + intval($r['p_izin']) + intval($r['p_sakit'])) / $tp) * 100 : null;
                    $nf = LaporanController::hitungNilaiFinal(floatval($r['nilai_akhir']), $kh);
                    $b = LaporanController::nilaiKeBobot($nf);
                    $total_sks += intval($r['sks']);
                    $total_bobot += ($b * intval($r['sks']));
                }
                $ipk_mahasiswa = ($total_sks > 0) ? round($total_bobot / $total_sks, 2) : 0;
            }
            
            // Gabungkan jumlah_mk per IPS data
            $jumlah_mk_per_sem = [];
            foreach ($nilai_rows as $r) {
                $jumlah_mk_per_sem[$r['semester']] = ($jumlah_mk_per_sem[$r['semester']] ?? 0) + 1;
            }
        }
        $data = $nilai_rows ?? [];
        break;
    case 'irs':
        $data = $laporanController->getLaporanIRS($semester, $status);
        $rekap_irs = $laporanController->getRekapIRSPerMahasiswa($semester);
        $statistik = $laporanController->getStatistikIRS();
        break;
    case 'presensi':
        $data = $laporanController->getLaporanPresensi($course_id);
        $statistik = $laporanController->getStatistikPresensi($course_id);
        break;
    case 'mahasiswa':
        $data = $laporanController->getLaporanMahasiswa($jurusan_id);
        break;
}

// Helper: Nilai ke Huruf (null-safe)
function nilaiHuruf($nilai) {
    if ($nilai === null || $nilai === '') return ['-', '#95a5a6'];
    if ($nilai >= 85) return ['A', '#27ae60'];
    elseif ($nilai >= 75) return ['B', '#2980b9'];
    elseif ($nilai >= 65) return ['C', '#d68910'];
    elseif ($nilai >= 50) return ['D', '#ca6f1e'];
    else return ['E', '#c0392b'];
}

// Helper: Format angka aman dari null
function fmtNilai($nilai, $desimal = 1) {
    return $nilai !== null ? number_format((float)$nilai, $desimal) : '–';
}

// Helper: Status IRS dengan indikator halus (teks berwarna + titik, tanpa badge)
function statusIRS($status) {
    $warna = ['pending' => '#d68910', 'approved' => '#27ae60', 'rejected' => '#c0392b'][$status] ?? '#7f8c8d';
    $label = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$status] ?? ucfirst($status);
    return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . $warna . ';font-weight:600;font-size:13px;">'
        . '<span style="width:8px;height:8px;border-radius:50%;background:' . $warna . ';display:inline-block;"></span>'
        . $label . '</span>';
}

// Helper: status verifikasi singkat
function statusVerifikasi($status) {
    $verified = ($status === 'verified');
    return '<span style="display:inline-flex;align-items:center;gap:6px;color:' . ($verified ? '#27ae60' : '#95a5a6') . ';font-weight:600;font-size:13px;">'
        . '<i class="fas ' . ($verified ? 'fa-check-circle' : 'fa-circle-notch') . '"></i>'
        . ($verified ? 'Terverifikasi' : 'Draft') . '</span>';
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.tabs-container {
    display: flex;
    gap: 5px;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
    overflow-x: auto;
}

.tab-btn {
    padding: 12px 25px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: #7f8c8d;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: #3498db;
    background: #f8f9fa;
}

.tab-btn.active {
    color: #3498db;
    border-bottom-color: #3498db;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-bar .form-group {
    margin: 0;
}

.filter-bar select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    width: 100%;
    background: #fff;
    font-size: 14px;
}

.section-title {
    margin: 18px 0 10px;
    font-size: 15px;
    color: var(--secondary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--primary);
}
</style>

<!-- Statistik Umum -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-number"><?= $stats_umum['total_mahasiswa'] ?></div>
        <div class="stat-label">Total Mahasiswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="stat-number"><?= $stats_umum['total_dosen'] ?></div>
        <div class="stat-label">Total Dosen</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        <div class="stat-number"><?= $stats_umum['total_matakuliah'] ?></div>
        <div class="stat-label">Mata Kuliah</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-number"><?= number_format($stats_umum['rata_nilai'], 2) ?></div>
        <div class="stat-label">Rata-rata Nilai</div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="table-container">
    <div class="tabs-container">
        <a href="?tab=nilai" class="tab-btn <?= $tab === 'nilai' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i> Nilai
        </a>
        <a href="?tab=rekap_nilai" class="tab-btn <?= $tab === 'rekap_nilai' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Nilai Mhs
        </a>
        <a href="?tab=irs" class="tab-btn <?= $tab === 'irs' ? 'active' : '' ?>">
            <i class="fas fa-file-signature"></i> IRS
        </a>
        <a href="?tab=presensi" class="tab-btn <?= $tab === 'presensi' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-check"></i> Presensi
        </a>
        <a href="?tab=mahasiswa" class="tab-btn <?= $tab === 'mahasiswa' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Mahasiswa
        </a>
    </div>

    <!-- ============================================ -->
    <!-- TAB 1: LAPORAN NILAI -->
    <!-- ============================================ -->
    <?php if ($tab === 'nilai'): ?>

        <!-- Filter -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="nilai">

            <div class="form-group" style="min-width: 220px; flex: 1;">
                <label style="font-size: 13px;">Mata Kuliah</label>
                <select name="course_id">
                    <option value="">-- Semua Mata Kuliah --</option>
                    <?php foreach ($matakuliah as $mk): ?>
                        <option value="<?= $mk['id'] ?>" <?= $course_id == $mk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="min-width: 130px;">
                <label style="font-size: 13px;">Semester</label>
                <select name="semester">
                    <option value="">-- Semua --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['semester'] ?>" <?= $semester == $s['semester'] ? 'selected' : '' ?>>
                            Smt <?= $s['semester'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="/simak_app/public/admin/cetak_pdf.php?type=nilai&course_id=<?= urlencode($course_id ?? '') ?>&semester=<?= urlencode($semester ?? '') ?>" target="_blank"
                    class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </form>

        <!-- Statistik Nilai -->
        <?php if (!empty($statistik)): ?>
            <h4 class="section-title"><i class="fas fa-chart-bar"></i> Statistik per Mata Kuliah</h4>
            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th>Jumlah</th>
                            <th>Rata-rata</th>
                            <th>Max</th>
                            <th>Min</th>
                            <th>A</th>
                            <th>B</th>
                            <th>C</th>
                            <th>D</th>
                            <th>E</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistik as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($s['nama_mk']) ?></td>
                                <td><?= $s['total_mahasiswa'] ?></td>
                                <td><strong><?= number_format($s['rata_rata'], 2) ?></strong></td>
                                <td><?= number_format($s['nilai_max'], 1) ?></td>
                                <td><?= number_format($s['nilai_min'], 1) ?></td>
                                <td><?= $s['jumlah_a'] ?></td>
                                <td><?= $s['jumlah_b'] ?></td>
                                <td><?= $s['jumlah_c'] ?></td>
                                <td><?= $s['jumlah_d'] ?></td>
                                <td><?= $s['jumlah_e'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Detail Nilai -->
        <h4 class="section-title"><i class="fas fa-list"></i> Detail Nilai <span class="text-muted" style="font-weight: 400;">(<?= count($data) ?> data)</span></h4>
        <?php if (empty($data)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak ada data nilai</h3>
                <p>Coba ubah filter atau pastikan dosen sudah menginput nilai.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Mahasiswa</th>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th>SKS</th>
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
                            list($huruf, $warna) = nilaiHuruf($row['nilai_akhir']);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                                <td><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                                <td><strong><?= htmlspecialchars($row['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_mk']) ?></td>
                                <td><?= $row['sks'] ?></td>
                                <td><?= htmlspecialchars($row['semester'] ?? '-') ?></td>
                                <td><?= fmtNilai($row['nilai_tugas']) ?></td>
                                <td><?= fmtNilai($row['nilai_uts']) ?></td>
                                <td><?= fmtNilai($row['nilai_uas']) ?></td>
                                <td><strong><?= fmtNilai($row['nilai_akhir']) ?></strong></td>
                                <td><strong style="color: <?= $warna ?>;"><?= $huruf ?></strong></td>
                                <td><?= statusVerifikasi($row['status_verifikasi']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB 1B: REKAP NILAI MAHASISWA (PER SEMESTER) -->
    <!-- ============================================ -->
    <?php elseif ($tab === 'rekap_nilai'): ?>

        <!-- Filter -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="rekap_nilai">

            <div class="form-group" style="min-width: 260px; flex: 1;">
                <label style="font-size: 13px;">Mahasiswa</label>
                <select name="mahasiswa_id">
                    <option value="">-- Pilih Mahasiswa --</option>
                    <?php foreach ($mahasiswa_list as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $mahasiswa_id == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nim']) ?> - <?= htmlspecialchars($m['mahasiswa_nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="min-width: 130px;">
                <label style="font-size: 13px;">Semester</label>
                <select name="semester">
                    <option value="">-- Semua Semester (IPK) --</option>
                    <?php foreach ($semester_options as $s): ?>
                        <option value="<?= $s['semester'] ?>" <?= $semester == $s['semester'] ? 'selected' : '' ?>>
                            Smt <?= $s['semester'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <?php if ($mahasiswa_id && !empty($data)): ?>
                    <a href="/simak_app/public/admin/cetak_pdf.php?type=rekap_nilai&mahasiswa_id=<?= urlencode($mahasiswa_id) ?>&semester=<?= urlencode($semester ?? '') ?>" target="_blank"
                        class="btn btn-outline btn-sm">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$mahasiswa_id): ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h3>Pilih Mahasiswa</h3>
                <p>Pilih mahasiswa di atas untuk melihat report nilai per semester dan IPK semua semester.</p>
            </div>
        <?php elseif (empty($data)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum ada nilai terverifikasi</h3>
                <p>Mahasiswa ini belum memiliki nilai yang terverifikasi.</p>
            </div>
        <?php else: ?>

            <!-- Ringkasan IPK / IPS -->
            <h4 class="section-title"><i class="fas fa-chart-line"></i> Ringkasan</h4>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-number" style="font-size: 22px;">
                        <?= htmlspecialchars($mhs_info['mahasiswa_nama'] ?? '-') ?>
                    </div>
                    <div class="stat-label">
                        NIM <?= htmlspecialchars($mhs_info['nim'] ?? '-') ?> · <?= htmlspecialchars($mhs_info['program_studi'] ?? '-') ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                    <div class="stat-number" style="color: <?= $ipk_mahasiswa >= 3.0 ? '#27ae60' : ($ipk_mahasiswa >= 2.5 ? '#d68910' : '#c0392b') ?>;">
                        <?= number_format($ipk_mahasiswa, 2) ?>
                    </div>
                    <div class="stat-label">
                        <?= $semester ? 'IPS Semester ' . htmlspecialchars($semester) : 'IPK Semua Semester' ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?= count($data) ?></div>
                    <div class="stat-label">Mata Kuliah dengan Nilai</div>
                </div>
            </div>

            <!-- IPS per semester -->
            <?php if (!$semester): ?>
                <h4 class="section-title"><i class="fas fa-chart-bar"></i> IPS per Semester</h4>
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
                            <?php foreach ($ips_data as $sem => $info):
                                $w_ips = $info['ips'] >= 3.0 ? '#27ae60' : ($info['ips'] >= 2.5 ? '#d68910' : '#c0392b');
                                $pred = $info['ips'] >= 3.5 ? 'Sangat Baik' : ($info['ips'] >= 3.0 ? 'Baik' : ($info['ips'] >= 2.5 ? 'Cukup' : 'Kurang'));
                            ?>
                                <tr>
                                    <td><strong>Semester <?= $sem ?></strong></td>
                                    <td><?= $info['jumlah_mk'] ?></td>
                                    <td><?= $info['total_sks'] ?></td>
                                    <td><strong style="color: <?= $w_ips ?>; font-size: 15px;">
                                        <?= number_format($info['ips'], 2) ?>
                                    </strong></td>
                                    <td class="text-muted"><?= $pred ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f8f9fa;">
                                <td colspan="2" style="text-align: right;"><strong>IPK Keseluruhan:</strong></td>
                                <td><strong><?= array_sum(array_column($ips_data, 'total_sks')) ?></strong></td>
                                <td><strong style="color: <?= $ipk_mahasiswa >= 3.0 ? '#27ae60' : ($ipk_mahasiswa >= 2.5 ? '#d68910' : '#c0392b') ?>; font-size: 15px;">
                                    <?= number_format($ipk_mahasiswa, 2) ?>
                                </strong></td>
                                <td class="text-muted">Semua Semester</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Detail nilai -->
            <h4 class="section-title"><i class="fas fa-list"></i> Detail Nilai <span class="text-muted" style="font-weight: 400;">(<?= $semester ? 'Semester ' . htmlspecialchars($semester) : 'Semua Semester' ?>)</span></h4>
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
                            <th>Akhir</th>
                            <th>Huruf</th>
                            <th>Bobot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row):
                            $tp = intval($row['total_pertemuan']);
                            $kh = ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null;
                            $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']), $kh);
                            $bobot = LaporanController::nilaiKeBobot($nf);
                            list($huruf, $warna) = nilaiHuruf($nf);
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['semester']) ?></td>
                                <td><strong><?= htmlspecialchars($row['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_mk']) ?></td>
                                <td><?= $row['sks'] ?></td>
                                <td><?= fmtNilai($row['nilai_tugas']) ?></td>
                                <td><?= fmtNilai($row['nilai_uts']) ?></td>
                                <td><?= fmtNilai($row['nilai_uas']) ?></td>
                                <td><strong><?= fmtNilai($nf) ?></strong></td>
                                <td><strong style="color: <?= $warna ?>;"><?= $huruf ?></strong></td>
                                <td><?= number_format($bobot, 1) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="3" style="text-align: right;">Total</td>
                            <td>
                                <?php
                                $t_sks = 0;
                                foreach ($data as $row) $t_sks += intval($row['sks']);
                                echo $t_sks;
                                ?>
                            </td>
                            <td colspan="7"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB 2: LAPORAN IRS -->
    <!-- ============================================ -->
    <?php elseif ($tab === 'irs'): ?>

        <!-- Filter -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="irs">

            <div class="form-group" style="min-width: 130px;">
                <label style="font-size: 13px;">Semester</label>
                <select name="semester">
                    <option value="">-- Semua --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['semester'] ?>" <?= $semester == $s['semester'] ? 'selected' : '' ?>>
                            Smt <?= $s['semester'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="min-width: 150px;">
                <label style="font-size: 13px;">Status</label>
                <select name="status">
                    <option value="">-- Semua --</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="/simak_app/public/admin/cetak_pdf.php?type=irs&semester=<?= urlencode($semester ?? '') ?>&status=<?= urlencode($status ?? '') ?>" target="_blank"
                    class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </form>

        <!-- Rekap IRS per Mahasiswa -->
        <?php if (!empty($rekap_irs)): ?>
            <h4 class="section-title"><i class="fas fa-users"></i> Rekap IRS per Mahasiswa</h4>
            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Mahasiswa</th>
                            <th>Smt</th>
                            <th>Jumlah MK</th>
                            <th>Total SKS</th>
                            <th>Disetujui</th>
                            <th>Menunggu</th>
                            <th>Ditolak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekap_irs as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['nim']) ?></strong></td>
                                <td><?= htmlspecialchars($r['mahasiswa_nama']) ?></td>
                                <td><?= $r['semester'] ?></td>
                                <td><?= $r['jumlah_mk'] ?></td>
                                <td><strong><?= $r['total_sks'] ?></strong></td>
                                <td class="text-success"><?= $r['approved'] ?></td>
                                <td class="text-warning"><?= $r['pending'] ?></td>
                                <td class="text-danger"><?= $r['rejected'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Detail IRS -->
        <h4 class="section-title"><i class="fas fa-list"></i> Detail IRS <span class="text-muted" style="font-weight: 400;">(<?= count($data) ?> data)</span></h4>
        <?php if (empty($data)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak ada data IRS</h3>
                <p>Coba ubah filter semester atau status.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Mahasiswa</th>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th>SKS</th>
                            <th>Smt</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                                <td><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                                <td><strong><?= htmlspecialchars($row['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_mk']) ?></td>
                                <td><?= $row['sks'] ?></td>
                                <td><?= $row['semester'] ?></td>
                                <td><?= statusIRS($row['status']) ?></td>
                                <td><small><?= date('d-m-Y', strtotime($row['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB 3: LAPORAN PRESENSI -->
    <!-- ============================================ -->
    <?php elseif ($tab === 'presensi'): ?>

        <!-- Filter -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="presensi">

            <div class="form-group" style="min-width: 220px; flex: 1;">
                <label style="font-size: 13px;">Mata Kuliah</label>
                <select name="course_id">
                    <option value="">-- Semua Mata Kuliah --</option>
                    <?php foreach ($matakuliah as $mk): ?>
                        <option value="<?= $mk['id'] ?>" <?= $course_id == $mk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="/simak_app/public/admin/cetak_pdf.php?type=presensi&course_id=<?= urlencode($course_id ?? '') ?>" target="_blank"
                    class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </form>

        <!-- Statistik Presensi -->
        <?php if (!empty($statistik)): ?>
            <h4 class="section-title"><i class="fas fa-chart-bar"></i> Statistik Presensi per Mata Kuliah</h4>
            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th>Mahasiswa</th>
                            <th>Total</th>
                            <th>Hadir</th>
                            <th>Izin</th>
                            <th>Sakit</th>
                            <th>Alpa</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistik as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($s['nama_mk']) ?></td>
                                <td><?= $s['total_mahasiswa'] ?></td>
                                <td><?= $s['total_presensi'] ?></td>
                                <td><?= $s['hadir'] ?></td>
                                <td><?= $s['izin'] ?></td>
                                <td><?= $s['sakit'] ?></td>
                                <td><?= $s['alpa'] ?></td>
                                <td>
                                    <strong style="color: <?= $s['persentase'] >= 75 ? '#27ae60' : ($s['persentase'] >= 50 ? '#d68910' : '#c0392b') ?>">
                                        <?= $s['persentase'] ?>%
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Detail Presensi -->
        <h4 class="section-title"><i class="fas fa-list"></i> Detail Presensi <span class="text-muted" style="font-weight: 400;">(<?= count($data) ?> data)</span></h4>
        <?php if (empty($data)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak ada data presensi</h3>
                <p>Belum ada sesi presensi yang dijalankan dosen.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th>NIM</th>
                            <th>Mahasiswa</th>
                            <th>Hadir</th>
                            <th>Izin</th>
                            <th>Sakit</th>
                            <th>Alpa</th>
                            <th>Total</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_mk']) ?></td>
                                <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                                <td><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                                <td><?= $row['hadir'] ?></td>
                                <td><?= $row['izin'] ?></td>
                                <td><?= $row['sakit'] ?></td>
                                <td><?= $row['alpa'] ?></td>
                                <td><strong><?= $row['total_pertemuan'] ?></strong></td>
                                <td>
                                    <strong style="color: <?= $row['persentase_hadir'] >= 75 ? '#27ae60' : ($row['persentase_hadir'] >= 50 ? '#d68910' : '#c0392b') ?>">
                                        <?= $row['persentase_hadir'] ?>%
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <!-- ============================================ -->
    <!-- TAB 4: LAPORAN MAHASISWA -->
    <!-- ============================================ -->
    <?php elseif ($tab === 'mahasiswa'): ?>

        <!-- Filter -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="tab" value="mahasiswa">

            <div class="form-group" style="min-width: 220px; flex: 1;">
                <label style="font-size: 13px;">Jurusan</label>
                <select name="jurusan_id">
                    <option value="">-- Semua Jurusan --</option>
                    <?php foreach ($prodi_list as $p): ?>
                        <option value="<?= $p['jurusan_id'] ?>" <?= $jurusan_id == $p['jurusan_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['kode']) ?> - <?= htmlspecialchars($p['program_studi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="/simak_app/public/admin/cetak_pdf.php?type=mahasiswa&jurusan_id=<?= urlencode($jurusan_id ?? '') ?>" target="_blank"
                class="btn btn-outline btn-sm">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </form>

        <!-- Data Mahasiswa -->
        <h4 class="section-title"><i class="fas fa-list"></i> Data Mahasiswa & IPK <span class="text-muted" style="font-weight: 400;">(<?= count($data) ?> mahasiswa)</span></h4>
        <?php if (empty($data)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Tidak ada data mahasiswa</h3>
                <p>Coba ubah filter jurusan.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Prodi</th>
                            <th>Angkatan</th>
                            <th>Smt</th>
                            <th>Total SKS</th>
                            <th>IPK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row):
                            $ipk = (float)($row['ipk'] ?? 0);
                            $warna_ipk = $ipk >= 3.0 ? '#27ae60' : ($ipk >= 2.5 ? '#d68910' : ($ipk > 0 ? '#c0392b' : '#95a5a6'));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nim']) ?></strong></td>
                                <td><?= htmlspecialchars($row['mahasiswa_nama']) ?></td>
                                <td><small><?= htmlspecialchars($row['email']) ?></small></td>
                                <td><?= htmlspecialchars($row['program_studi'] ?? '-') ?></td>
                                <td><?= $row['angkatan'] ?></td>
                                <td><?= $row['semester'] ?></td>
                                <td><?= $row['total_sks'] ?></td>
                                <td>
                                    <strong style="color: <?= $warna_ipk ?>; font-size: 14px;">
                                        <?= number_format($ipk, 2) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
