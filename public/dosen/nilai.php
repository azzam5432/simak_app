<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/DosenController.php';

checkAccess(['dosen']);

$page_title = 'Input Nilai';

$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosen = $stmt->fetch();

if (!$dosen) {
    redirectWithMessage('/simak_app/public/logout.php', 'Data dosen tidak ditemukan', 'error');
}

$controller = new DosenController($pdo, $dosen['id']);

$course_id = $_GET['course_id'] ?? 0;
$semester = $_GET['semester'] ?? date('Y') . '1'; // Format: 20241

$matakuliah = $controller->getMatakuliahDosen();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_nilai') {
    $data = [
        'course_id' => $_POST['course_id'],
        'semester' => $_POST['semester'],
        'nilai' => $_POST['nilai'] ?? []
    ];
    
    $result = $controller->inputNilaiMassal($data);
    if ($result['success']) {
        redirectWithMessage('/simak_app/public/dosen/nilai.php?course_id=' . $course_id . '&semester=' . $semester, 'Nilai berhasil disimpan!', 'success');
    } else {
        redirectWithMessage('/simak_app/public/dosen/nilai.php?course_id=' . $course_id . '&semester=' . $semester, 'Gagal: ' . $result['message'], 'error');
    }
}

if ($course_id) {
    $mahasiswa_nilai = $controller->getMahasiswaForNilai($course_id, $semester);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container" style="margin-bottom: 20px;">
    <div class="table-header">
        <h3><i class="fas fa-list-ul"></i> Pilih Kelas</h3>
    </div>
    <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <select name="course_id" required style="padding: 8px 14px; border: 1px solid #ddd; border-radius: 6px; min-width: 200px;">
            <option value="">-- Pilih Mata Kuliah --</option>
            <?php foreach ($matakuliah as $mk): ?>
                <option value="<?= $mk['id'] ?>" <?= $course_id == $mk['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="semester" value="<?= $semester ?>" placeholder="Semester (contoh: 1)" style="padding: 8px 14px; border: 1px solid #ddd; border-radius: 6px;">
        <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
</div>

<?php if ($course_id && !empty($mahasiswa_nilai)): ?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-edit"></i> Input Nilai - <?= htmlspecialchars($matakuliah[0]['nama_mk'] ?? '') ?></h3>
        <div>
            <span style="color: #7f8c8d;">Semester: <?= $semester ?></span>
            <span style="color: #7f8c8d; margin-left: 15px;">Total: <?= count($mahasiswa_nilai) ?> mahasiswa</span>
        </div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="save_nilai">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="semester" value="<?= $semester ?>">
        
        <table>
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Tugas (30%)</th>
                    <th>UTS (30%)</th>
                    <th>UAS (40%)</th>
                    <th>Nilai Akhir</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mahasiswa_nilai as $mn): ?>
                    <?php 
                    $tugas = floatval($mn['nilai_tugas'] ?? 0);
                    $uts = floatval($mn['nilai_uts'] ?? 0);
                    $uas = floatval($mn['nilai_uas'] ?? 0);
                    $akhir = ($tugas * 0.3) + ($uts * 0.3) + ($uas * 0.4);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($mn['nim']) ?></strong></td>
                        <td><?= htmlspecialchars($mn['mahasiswa_nama']) ?></td>
                        <td>
                            <input type="number" name="nilai[<?= $mn['mahasiswa_id'] ?>][tugas]" 
                                   value="<?= $tugas ?>" min="0" max="100" step="0.01"
                                   style="width: 70px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"
                                   onchange="hitungNilai(this)">
                        </td>
                        <td>
                            <input type="number" name="nilai[<?= $mn['mahasiswa_id'] ?>][uts]" 
                                   value="<?= $uts ?>" min="0" max="100" step="0.01"
                                   style="width: 70px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"
                                   onchange="hitungNilai(this)">
                        </td>
                        <td>
                            <input type="number" name="nilai[<?= $mn['mahasiswa_id'] ?>][uas]" 
                                   value="<?= $uas ?>" min="0" max="100" step="0.01"
                                   style="width: 70px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"
                                   onchange="hitungNilai(this)">
                        </td>
                        <td>
                            <strong id="akhir_<?= $mn['mahasiswa_id'] ?>">
                                <?= number_format($akhir, 2) ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($mn['status_verifikasi'] === 'verified'): ?>
                                <span class="status-badge" style="background: #2ecc71; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                    <i class="fas fa-check-circle"></i> Terverifikasi
                                </span>
                            <?php else: ?>
                                <span class="status-badge" style="background: #e74c3c; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px;">
                                    <i class="fas fa-undo"></i> Dikembalikan Admin
                                </span>
                                <?php if (!empty($mn['catatan'])): ?>
                                    <div style="font-size: 12px; color: #c0392b; margin-top: 4px; max-width: 200px;">
                                        <i class="fas fa-comment-dots"></i> <?= htmlspecialchars($mn['catatan']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Nilai</button>
            <small style="color: #7f8c8d; align-self: center;">
                * Nilai langsung terverifikasi otomatis setelah disimpan. Jika menginput nilai 0 (tugas/UTS/UAS), nilai tersebut perlu diverifikasi Admin terlebih dahulu.
            </small>
        </div>
    </form>
</div>

<?php elseif ($course_id): ?>
    <div class="table-container">
        <p style="text-align: center; color: #7f8c8d; padding: 30px;">
            Belum ada mahasiswa yang terdaftar di kelas ini untuk semester <?= $semester ?>.
        </p>
    </div>
<?php else: ?>
    <div class="table-container">
        <p style="text-align: center; color: #7f8c8d; padding: 30px;">
            Silakan pilih kelas dan semester terlebih dahulu.
        </p>
    </div>
<?php endif; ?>

<script>
function hitungNilai(input) {
    const row = input.closest('tr');
    const tugas = parseFloat(row.querySelector('input[name*="[tugas]"]').value) || 0;
    const uts = parseFloat(row.querySelector('input[name*="[uts]"]').value) || 0;
    const uas = parseFloat(row.querySelector('input[name*="[uas]"]').value) || 0;
    const akhir = (tugas * 0.3) + (uts * 0.3) + (uas * 0.4);
    
    const akhirSpan = row.querySelector('strong[id^="akhir_"]');
    if (akhirSpan) {
        akhirSpan.textContent = akhir.toFixed(2);
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>