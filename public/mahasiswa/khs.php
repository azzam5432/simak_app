<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'KHS & IPK';

$stmt = $pdo->prepare("SELECT id, semester FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$controller = new MahasiswaController($pdo, $mahasiswa['id']);

$semester = $_GET['semester'] ?? null;
$khs = $controller->getKHS($semester);
$semesters = $controller->getSemestersWithGrades();
$detail_ipk = $controller->getDetailIPK();
$ipk_total = $controller->hitungIPK();

$ips = 0;
if ($semester) {
    $ips = $controller->hitungIPS($semester);
}

function nilaiHuruf($nilai) {
    if ($nilai >= 85) return ['A', 'is-a', 4.0];
    elseif ($nilai >= 75) return ['B', 'is-b', 3.0];
    elseif ($nilai >= 65) return ['C', 'is-c', 2.0];
    elseif ($nilai >= 50) return ['D', 'is-d', 1.0];
    else return ['E', 'is-e', 0.0];
}

$ipk_class = $ipk_total >= 3.5 ? 'is-good' : ($ipk_total >= 2.5 ? 'is-fair' : 'is-low');

include __DIR__ . '/../../includes/header.php';
?>

<div class="split-grid is-wide">
    <div class="table-container">
        <div class="table-header">
            <h3><i class="fas fa-clipboard-list"></i> Kartu Hasil Studi — Semester <?= $semester ?? 'Semua' ?></h3>
            <div>
                <select class="form-select" onchange="window.location.href='?semester='+this.value">
                    <option value="">-- Semua Semester --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['semester'] ?>" <?= $semester == $s['semester'] ? 'selected' : '' ?>>
                            Semester <?= $s['semester'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (empty($khs)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>Belum ada nilai</h3>
                <p>Nilai yang sudah diverifikasi dosen akan tampil di sini.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Mata Kuliah</th>
                            <th>SKS</th>
                            <th>Tugas</th>
                            <th>UTS</th>
                            <th>UAS</th>
                            <th>Nilai Asli</th>
                            <th>Nilai Final</th>
                            <th>Huruf</th>
                            <th>Bobot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_sks = 0;
                        $total_bobot = 0;
                        foreach ($khs as $k):
                            $nilai = floatval($k['nilai_final']);
                            list($huruf, $warna, $bobot) = nilaiHuruf($nilai);
                            $skor_class = $nilai >= 85 ? 'is-good' : ($nilai >= 65 ? 'is-fair' : 'is-low');
                            $total_sks += $k['sks'];
                            $total_bobot += ($bobot * $k['sks']);
                        ?>
                            <tr>
                                <td><span class="cell-strong"><?= htmlspecialchars($k['kode_mk']) ?></span></td>
                                <td><?= htmlspecialchars($k['nama_mk']) ?></td>
                                <td><?= $k['sks'] ?></td>
                                <td><?= number_format($k['nilai_tugas'], 1) ?></td>
                                <td><?= number_format($k['nilai_uts'], 1) ?></td>
                                <td><?= number_format($k['nilai_uas'], 1) ?></td>
                                <td><?= number_format($k['nilai_akhir'], 1) ?></td>
                                <td><span class="grade-score <?= $skor_class ?>"><?= number_format($nilai, 1) ?></span></td>
                                <td><span class="grade-mark <?= $warna ?>"><?= $huruf ?></span></td>
                                <td><?= number_format($bobot, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end">Total / IPS</td>
                            <td><?= $total_sks ?></td>
                            <td colspan="6"></td>
                            <td>
                                <?php
                                $ips_calc = ($total_sks > 0) ? round($total_bobot / $total_sks, 2) : 0;
                                echo number_format($ips_calc, 2);
                                ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-chart-line"></i> Indeks Prestasi</h3>
            </div>
            <div class="text-center py-3">
                <div class="value-xl <?= $ipk_class ?>"><?= number_format($ipk_total, 2) ?></div>
                <div class="stat-sub">
                    <?php
                    if ($ipk_total >= 3.5) echo 'Cumlaude';
                    elseif ($ipk_total >= 3.0) echo 'Sangat Baik';
                    elseif ($ipk_total >= 2.5) echo 'Baik';
                    elseif ($ipk_total >= 2.0) echo 'Cukup';
                    else echo 'Perlu Perbaikan';
                    ?>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-chart-bar"></i> Detail per Semester</h3>
            </div>
            <?php if (empty($detail_ipk)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Belum ada data IPK</h3>
                    <p>Data akan muncul setelah nilai diverifikasi.</p>
                </div>
            <?php else: ?>
                <div class="feed-list">
                    <?php foreach ($detail_ipk as $sem => $data): ?>
                        <?php
                        $ips_class = $data['ips'] >= 3.5 ? 'is-good' : ($data['ips'] >= 2.5 ? 'is-fair' : 'is-low');
                        ?>
                        <div class="feed-item">
                            <div class="feed-main">
                                <span class="feed-title">Semester <?= $sem ?></span>
                                <span class="feed-text"><?= $data['mata_kuliah'] ?> MK &middot; <?= $data['total_sks'] ?> SKS</span>
                            </div>
                            <span class="ips-number <?= $ips_class ?>"><?= number_format($data['ips'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
