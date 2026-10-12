<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$page_title = 'IRS - Rencana Studi';

$stmt = $pdo->prepare("
    SELECT m.id, m.semester, f.nama as fakultas_nama, j.nama as jurusan_nama
    FROM mahasiswa m
    LEFT JOIN fakultas f ON f.id = m.fakultas_id
    LEFT JOIN jurusan j ON j.id = m.jurusan_id
    WHERE m.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    redirectWithMessage('/simak_app/public/mahasiswa/dashboard.php', 'Data mahasiswa tidak ditemukan', 'error');
}

$mahasiswa_fakultas = $mahasiswa['fakultas_nama'] ?? null;
$mahasiswa_jurusan = $mahasiswa['jurusan_nama'] ?? null;

$mahasiswa_id = $mahasiswa['id'];
$controller = new MahasiswaController($pdo, $mahasiswa_id);

$semester = $_GET['semester'] ?? $mahasiswa['semester'];
$action = $_POST['action'] ?? '';

if ($action === 'add_paket') {
    $result = $controller->createIRSFromPaket($semester);

    if ($result['success']) {
        redirectWithMessage(
            '/simak_app/public/mahasiswa/irs.php?semester=' . $semester,
            "Paket berhasil diambil! {$result['added']} mata kuliah ({$result['sks']} SKS) ditambahkan ke IRS.",
            'success'
        );
    } else {
        redirectWithMessage('/simak_app/public/mahasiswa/irs.php?semester=' . $semester, 'Gagal: ' . $result['message'], 'error');
    }
}

if ($action === 'delete' && isset($_POST['irs_id'])) {
    $result = $controller->deleteIRS($_POST['irs_id']);

    if ($result['success']) {
        redirectWithMessage('/simak_app/public/mahasiswa/irs.php?semester=' . $semester, 'Mata kuliah dihapus dari IRS!', 'success');
    } else {
        redirectWithMessage('/simak_app/public/mahasiswa/irs.php?semester=' . $semester, 'Gagal: ' . $result['message'], 'error');
    }
}

$irs_list = $controller->getIRS($semester);
$total_sks = $controller->getTotalSksIRS($semester);
$status_irs = $controller->getIRSStatus($semester);

// Paket pembelajaran (fakultas & jurusan mengikuti data mahasiswa)
$max_sks = 24;

// Kontrol akses berdasarkan tingkat & semester aktif
$akses = $controller->validateAksesSemester($semester);
$semester_aktif = $akses['semester_aktif'] ?? intval($mahasiswa['semester']);
$tingkat = $akses['tingkat'] ?? null;
$adalah_semester_aktif = $akses['boleh'];

$paket_courses = $controller->getPaketCourses($semester);
$paket_sks = array_sum(array_map(function ($c) { return (int) $c['sks']; }, $paket_courses));
$boleh_edit = $adalah_semester_aktif && (!$status_irs || $status_irs === 'rejected');
$paket_bisa_diambil = $boleh_edit && !empty($paket_courses) && ($total_sks + $paket_sks <= $max_sks);
$paket_penuh = $total_sks + $paket_sks > $max_sks;
$ada_di_irs = !empty($irs_list);

// Persentase untuk bar progress SKS
$persen_sks = min(100, round(($total_sks / $max_sks) * 100));

// Label & ikon status dalam bahasa Indonesia
$status_meta = [
    'pending'  => ['label' => 'Menunggu Validasi', 'icon' => 'fa-hourglass-half'],
    'approved' => ['label' => 'Disetujui',         'icon' => 'fa-check-circle'],
    'rejected' => ['label' => 'Ditolak',           'icon' => 'fa-times-circle'],
];
$status_akhir = $status_meta[$status_irs] ?? null;

include __DIR__ . '/../../includes/header.php';
?>

<!-- ===================== TOOLBAR RINGKAS ===================== -->
<div class="irs-toolbar">

    <!-- Kiri: judul semester + pemilih pill -->
    <div class="irs-toolbar-left">
        <div class="irs-semester-picker">
            <span class="irs-sem-title">Semester</span>
            <?php for ($s = 1; $s <= 8; $s++): ?>
                <?php $is_aktif = ($s == $semester_aktif); ?>
                <?php if ($is_aktif): ?>
                    <a href="?semester=<?= $s ?>"
                       class="irs-sem-btn <?= $s == $semester ? 'active' : '' ?> current"
                       title="Semester aktif Anda — IRS dapat diisi di sini">
                        <?= $s ?>
                    </a>
                <?php else: ?>
                    <span class="irs-sem-btn locked"
                          title="Terkunci — IRS hanya dapat diisi pada semester aktif (<?= $semester_aktif ?>)">
                        <i class="fas fa-lock" style="font-size: 10px;"></i> <?= $s ?>
                    </span>
                <?php endif; ?>
            <?php endfor; ?>
            <span class="irs-sem-hint">
                <i class="fas fa-<?= $adalah_semester_aktif ? 'unlock' : 'lock' ?>"></i>
                Tingkat <?= $tingkat ?? '-' ?> · Semester aktif: <?= $semester_aktif ?>
            </span>
        </div>
    </div>

    <!-- Kanan: status + SKS dalam satu baris -->
    <div class="irs-toolbar-right">
        <span class="irs-status <?= $status_akhir ? 'irs-status-' . $status_irs : 'irs-status-empty' ?>">
            <i class="fas <?= $status_akhir['icon'] ?? 'fa-inbox' ?>"></i>
            <?= $status_akhir['label'] ?? 'Belum Ada IRS' ?>
        </span>

        <span class="irs-sks-pill">
            <i class="fas fa-layer-group"></i>
            <strong class="<?= $total_sks > $max_sks ? 'over' : '' ?>"><?= $total_sks ?></strong>
            <span class="irs-sks-track"><span class="irs-sks-fill <?= $total_sks > $max_sks ? 'over' : ($persen_sks >= 80 ? 'near' : '') ?>" style="width: <?= $persen_sks ?>%"></span></span>
            / <?= $max_sks ?> SKS
        </span>

        <?php if ($status_irs === 'pending' && $ada_di_irs): ?>
            <button class="btn btn-outline btn-sm" onclick="printIRS()">
                <i class="fas fa-print"></i> Cetak Bukti
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Banner lihat-saja saat bukan semester aktif -->
<?php if (!$adalah_semester_aktif): ?>
    <div class="irs-lock-banner">
        <i class="fas fa-lock"></i>
        <div>
            <strong>Mode Lihat Saja — Semester <?= $semester ?></strong><br>
            <small>IRS hanya dapat diisi pada semester aktif Anda (Semester <?= $semester_aktif ?>).
            Klik pill <strong>Semester <?= $semester_aktif ?></strong> untuk mengelola IRS.</small>
        </div>
        <a href="?semester=<?= $semester_aktif ?>" class="btn btn-primary btn-sm" style="margin-left: auto; flex-shrink: 0;">
            <i class="fas fa-arrow-right"></i> Ke Semester Aktif
        </a>
    </div>
<?php elseif (!empty($akses['peringatan_tingkat'])): ?>
    <div class="irs-lock-banner irs-lock-banner-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <small><?= htmlspecialchars($akses['peringatan_tingkat']) ?>.
            Data ini diatur oleh admin — jika terasa salah, hubungi bagian akademik.</small>
        </div>
    </div>
<?php endif; ?>

<!-- ===================== KONTEN UTAMA ===================== -->
<div class="irs-grid">

    <!-- KOLOM KIRI: DAFTAR IRS -->
    <div>
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-clipboard-list"></i> Mata Kuliah Diambil</h3>
            </div>

            <?php if ($irs_list): ?>
                <table class="irs-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Mata Kuliah</th>
                            <th class="text-center">SKS</th>
                            <th>Jadwal</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($irs_list as $irs): ?>
                            <tr>
                                <td><span class="irs-kode"><?= htmlspecialchars($irs['kode_mk']) ?></span></td>
                                <td>
                                    <div class="irs-mk-nama"><?= htmlspecialchars($irs['nama_mk']) ?></div>
                                    <?php if (!empty($irs['dosen_nama'])): ?>
                                        <div class="irs-mk-sub"><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($irs['dosen_nama']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><strong><?= $irs['sks'] ?></strong></td>
                                <td>
                                    <?php if (!empty($irs['jadwal_info'])): ?>
                                        <span class="irs-jadwal"><?= htmlspecialchars($irs['jadwal_info']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Belum dijadwalkan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="irs-status irs-status-<?= $irs['status'] ?>">
                                        <i class="fas <?= $status_meta[$irs['status']]['icon'] ?? 'fa-circle' ?>"></i>
                                        <?= $status_meta[$irs['status']]['label'] ?? ucfirst($irs['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($irs['status'] === 'pending' && $adalah_semester_aktif): ?>
                                        <button class="btn btn-danger btn-sm btn-delete" onclick="deleteIRS(<?= $irs['id'] ?>)" title="Hapus dari IRS">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted" title="<?= $adalah_semester_aktif ? 'Terkunci — IRS sudah diproses' : 'Terkunci — bukan semester aktif' ?>"><i class="fas fa-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="irs-empty">
                    <i class="fas fa-inbox"></i>
                    <h4>Belum ada mata kuliah di IRS</h4>
                    <p>Ambil Paket Pembelajaran di samping untuk menyusun IRS semester <?= $semester ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Panduan singkat -->
        <div class="irs-guide">
            <span><i class="fas fa-lock"></i> MK terkunci setelah divalidasi</span>
            <span><i class="fas fa-undo"></i> IRS ditolak dapat disusun ulang</span>
        </div>
    </div>

    <!-- KOLOM KANAN: PAKET -->
    <div>
        <div class="irs-paket-card">
            <div class="irs-paket-head">
                <div class="irs-paket-icon"><i class="fas fa-box-open"></i></div>
                <div>
                    <h3>Paket Semester <?= $semester ?></h3>
                    <p>
                        <i class="fas fa-university"></i> <?= htmlspecialchars($mahasiswa_fakultas ?? 'Fakultas Anda') ?>
                        · <i class="fas fa-book"></i> <?= htmlspecialchars($mahasiswa_jurusan ?? 'Jurusan Anda') ?>
                    </p>
                </div>
            </div>

            <?php if (!$adalah_semester_aktif): ?>
                <!-- Semester bukan semester aktif: paket terkunci -->
                <div class="irs-empty irs-empty-compact">
                    <i class="fas fa-lock"></i>
                    <h4>Paket Terkunci</h4>
                    <p>Paket hanya dapat diambil pada semester aktif Anda (Semester <?= $semester_aktif ?>).</p>
                    <a href="?semester=<?= $semester_aktif ?>" class="btn btn-primary btn-sm" style="margin-top: 10px;">
                        <i class="fas fa-arrow-right"></i> Ke Semester Aktif
                    </a>
                </div>

            <?php elseif (!empty($paket_courses)): ?>
                <div class="irs-paket-meta">
                    <span><strong><?= count($paket_courses) ?></strong> Mata Kuliah</span>
                    <span class="irs-paket-meta-dot"></span>
                    <span><strong><?= $paket_sks ?></strong> SKS</span>
                </div>

                <ul class="irs-paket-list">
                    <?php foreach ($paket_courses as $c): ?>
                        <li>
                            <span class="irs-kode"><?= htmlspecialchars($c['kode_mk']) ?></span>
                            <span class="irs-paket-mk"><?= htmlspecialchars($c['nama_mk']) ?></span>
                            <span class="irs-sks-chip"><?= $c['sks'] ?> SKS</span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($paket_penuh): ?>
                    <div class="irs-note irs-note-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Mengambil paket ini akan melebihi batas <?= $max_sks ?> SKS
                        (<?= $total_sks ?> + <?= $paket_sks ?>).
                    </div>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirmAmbil()">
                    <input type="hidden" name="action" value="add_paket">
                    <button type="submit" class="btn btn-primary btn-block btn-lg"
                        <?= $paket_bisa_diambil ? '' : 'disabled' ?>>
                        <i class="fas fa-cart-plus"></i>
                        Ambil Semua Paket (<?= count($paket_courses) ?> MK · <?= $paket_sks ?> SKS)
                    </button>
                    <?php if (!$boleh_edit): ?>
                        <div class="irs-note irs-note-info">
                            <i class="fas fa-lock"></i>
                            IRS status <strong><?= $status_akhir['label'] ?? ucfirst($status_irs) ?></strong> —
                            <?= $status_irs === 'pending' ? 'menunggu validasi admin.' : 'sudah disetujui dan tidak dapat diubah.' ?>
                        </div>
                    <?php endif; ?>
                </form>

            <?php else: ?>
                <div class="irs-empty irs-empty-compact">
                    <i class="fas fa-check-double"></i>
                    <h4><?= $ada_di_irs ? 'Paket Sudah Lengkap' : 'Paket Belum Tersedia' ?></h4>
                    <p>
                        <?= $ada_di_irs
                            ? 'Semua mata kuliah paket semester ini sudah ada di IRS Anda.'
                            : 'Belum ada paket mata kuliah untuk jurusan Anda di semester ini.' ?>
                    </p>
                    <small>
                        <i class="fas fa-university"></i> <?= htmlspecialchars($mahasiswa_fakultas ?? '-') ?>
                        · <i class="fas fa-book"></i> <?= htmlspecialchars($mahasiswa_jurusan ?? '-') ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteIRS(id) {
    if (confirm('Yakin hapus mata kuliah ini dari IRS?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="irs_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmAmbil() {
    return confirm('Ambil semua mata kuliah dalam paket ke IRS semester <?= $semester ?>?');
}

function printIRS() {
    window.open('/simak_app/public/mahasiswa/irs_print.php?semester=<?= $semester ?>', '_blank');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
