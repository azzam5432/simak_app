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
    if ($nilai >= 85) return ['A', '#2ecc71', 4.0];
    elseif ($nilai >= 75) return ['B', '#3498db', 3.0];
    elseif ($nilai >= 65) return ['C', '#f39c12', 2.0];
    elseif ($nilai >= 50) return ['D', '#e67e22', 1.0];
    else return ['E', '#e74c3c', 0.0];
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <div>
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-clipboard-list"></i> KHS - Semester <?= $semester ?? 'Semua' ?></h3>
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
                <p style="text-align: center; color: #7f8c8d; padding: 30px;">
                    Belum ada nilai yang terverifikasi untuk semester ini
                </p>
            <?php else: ?>
                <table class="table table-striped table-hover align-middle">
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
                            $total_sks += $k['sks'];
                            $total_bobot += ($bobot * $k['sks']);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($k['kode_mk']) ?></strong></td>
                                <td><?= htmlspecialchars($k['nama_mk']) ?></td>
                                <td><?= $k['sks'] ?></td>
                                <td><?= number_format($k['nilai_tugas'], 1) ?></td>
                                <td><?= number_format($k['nilai_uts'], 1) ?></td>
                                <td><?= number_format($k['nilai_uas'], 1) ?></td>
                                <td><?= number_format($k['nilai_akhir'], 1) ?></td>
                                <td>
                                    <strong style="color: <?= $nilai >= 85 ? '#2ecc71' : ($nilai >= 65 ? '#f39c12' : '#e74c3c') ?>;">
                                        <?= number_format($nilai, 1) ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color: <?= $warna ?>; font-size: 14px;">
                                        <?= $huruf ?>
                                    </strong>
                                </td>
                                <td><?= number_format($bobot, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="2" style="text-align: right;">Total / IPS</td>
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
            <?php endif; ?>
        </div>
    </div>
    
    <div>
        <div class="table-container" style="margin-bottom: 20px;">
            <h3 style="text-align: center;"><i class="fas fa-trophy"></i> IPK</h3>
            <div style="text-align: center; padding: 10px;">
                <div style="font-size: 48px; font-weight: 700; color: <?= $ipk_total >= 3.5 ? '#2ecc71' : ($ipk_total >= 2.5 ? '#f39c12' : '#e74c3c') ?>">
                    <?= number_format($ipk_total, 2) ?>
                </div>
                <div style="color: #7f8c8d; font-size: 14px;">
                    <?php 
                    if ($ipk_total >= 3.5) echo '<i class="fas fa-award"></i> Cumlaude';
                    elseif ($ipk_total >= 3.0) echo '<i class="fas fa-thumbs-up"></i> Sangat Baik';
                    elseif ($ipk_total >= 2.5) echo '<i class="fas fa-chart-line"></i> Baik';
                    elseif ($ipk_total >= 2.0) echo '<i class="fas fa-chart-bar"></i> Cukup';
                    else echo '<i class="fas fa-chart-line"></i> Perlu Perbaikan';
                    ?>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <h3><i class="fas fa-chart-bar"></i> Detail per Semester</h3>
            <?php if (empty($detail_ipk)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 15px;">
                    Belum ada data IPK
                </p>
            <?php else: ?>
                <?php foreach ($detail_ipk as $sem => $data): ?>
                    <div style="padding: 10px 15px; border-bottom: 1px solid #e9ecef;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Semester <?= $sem ?></strong>
                                <div style="font-size: 12px; color: #7f8c8d;">
                                    <?= $data['mata_kuliah'] ?> MK · <?= $data['total_sks'] ?> SKS
                                </div>
                            </div>
                            <div style="font-size: 20px; font-weight: 700; color: <?= $data['ips'] >= 3.5 ? '#2ecc71' : ($data['ips'] >= 2.5 ? '#f39c12' : '#e74c3c') ?>">
                                <?= number_format($data['ips'], 2) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>