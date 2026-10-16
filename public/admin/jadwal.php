<?php
// ============================================
// public/admin/jadwal.php
// CRUD Jadwal (Kelas Perkuliahan)
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Data Jadwal';
$adminController = new AdminController($pdo);

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $result = $adminController->createJadwal($_POST);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/admin/jadwal.php', 'Jadwal berhasil ditambahkan!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/admin/jadwal.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'update':
            $result = $adminController->updateJadwal($_POST['id'], $_POST);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/admin/jadwal.php', 'Jadwal berhasil diupdate!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/admin/jadwal.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'delete':
            $result = $adminController->deleteJadwal($_POST['id']);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/admin/jadwal.php', 'Jadwal berhasil dihapus!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/admin/jadwal.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;
    }
}

// Filter
$filter = [
    'course_id' => $_GET['course_id'] ?? '',
    'dosen_id' => $_GET['dosen_id'] ?? '',
    'tingkat' => $_GET['tingkat'] ?? '',
    'hari' => $_GET['hari'] ?? ''
];

$jadwal = $adminController->getAllJadwal($filter);
$matakuliah = $adminController->getAllMatakuliah();
$dosen_list = $adminController->getAllDosen();
$hari_list = $adminController->getHariList();

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-calendar-alt"></i> Data Jadwal Perkuliahan</h3>
        <div>
            <button class="btn btn-success" onclick="openModal('modalTambah')">
                <i class="fas fa-plus"></i> Tambah Jadwal
            </button>
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
        <div class="form-group" style="margin: 0; min-width: 200px; flex: 1;">
            <label class="form-label" style="font-size: 13px;">Mata Kuliah</label>
            <select class="form-select" name="course_id">
                <option value="">-- Semua --</option>
                <?php foreach ($matakuliah as $mk): ?>
                    <option value="<?= $mk['id'] ?>" <?= $filter['course_id'] == $mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin: 0; min-width: 180px;">
            <label class="form-label" style="font-size: 13px;">Dosen</label>
            <select class="form-select" name="dosen_id">
                <option value="">-- Semua --</option>
                <?php foreach ($dosen_list as $d): ?>
                    <option value="<?= $d['dosen_id'] ?>" <?= $filter['dosen_id'] == $d['dosen_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin: 0; min-width: 140px;">
            <label class="form-label" style="font-size: 13px;">Tingkat</label>
            <select class="form-select" name="tingkat">
                <option value="">-- Semua --</option>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= $i ?>" <?= $filter['tingkat'] == $i ? 'selected' : '' ?>>Tingkat <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group" style="margin: 0; min-width: 130px;">
            <label class="form-label" style="font-size: 13px;">Hari</label>
            <select class="form-select" name="hari">
                <option value="">-- Semua --</option>
                <?php foreach ($hari_list as $h): ?>
                    <option value="<?= $h ?>" <?= $filter['hari'] == $h ? 'selected' : '' ?>><?= $h ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            <a href="/simak_app/public/admin/jadwal.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>

    <div style="overflow-x: auto;">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Kode MK</th>
                    <th>Mata Kuliah</th>
                    <th>Dosen</th>
                    <th>Kelas</th>
                    <th>Jadwal</th>
                    <th>Ruang</th>
                    <th>Pendaftar</th>
                    <th class="th-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jadwal as $jd): ?>
                    <tr>
                        <td><span class="cell-strong"><?= htmlspecialchars($jd['kode_mk']) ?></span></td>
                        <td><?= htmlspecialchars($jd['nama_mk']) ?></td>
                        <td>
                            <span class="cell-main"><?= htmlspecialchars($jd['dosen_nama']) ?></span>
                            <span class="cell-sub"><?= htmlspecialchars($jd['nid']) ?></span>
                        </td>
                        <td>
                            <span class="cell-strong"><?= htmlspecialchars($jd['jurusan_kode']) ?>-<?= $jd['tingkat'] ?><?= $jd['rombel'] ?></span>
                        </td>
                        <td>
                            <span class="count-item"><i class="fas fa-clock"></i> <?= htmlspecialchars($jd['hari']) ?></span>
                            <span class="cell-sub"><?= date('H:i', strtotime($jd['jam_mulai'])) ?> – <?= date('H:i', strtotime($jd['jam_selesai'])) ?></span>
                        </td>
                        <td><?= htmlspecialchars($jd['ruang'] ?? '–') ?></td>
                        <td>
                            <?php $penuh = $jd['total_pendaftar'] >= $jd['kapasitas']; ?>
                            <span class="count-item <?= $penuh ? '' : '' ?>" style="<?= $penuh ? 'color: var(--danger);' : '' ?>">
                                <i class="fas fa-users"></i> <?= $jd['total_pendaftar'] ?>/<?= $jd['kapasitas'] ?>
                            </span>
                        </td>
                        <td class="td-actions">
                            <span class="row-actions">
                                <button class="btn btn-sm btn-icon btn-icon-light" title="Edit" onclick="editJadwal(<?= $jd['id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-icon btn-icon-danger-light" title="Hapus" onclick="deleteJadwal(<?= $jd['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($jadwal)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <h3>Belum ada data jadwal</h3>
                                <p>Tambahkan jadwal pertama atau ubah filter pencarian.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL TAMBAH JADWAL -->
<!-- ============================================ -->
<div id="modalTambah" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalTambah')"></div>
    <div class="modal-content modal-lg" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> Tambah Jadwal</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Mata Kuliah <span class="required">*</span></label>
                <select class="form-select" name="course_id" required>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php foreach ($matakuliah as $mk): ?>
                        <option value="<?= $mk['id'] ?>">
                            <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                            (<?= $mk['sks'] ?> SKS)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Dosen <span class="required">*</span></label>
                <select class="form-select" name="dosen_id" required>
                    <option value="">-- Pilih Dosen --</option>
                    <?php foreach ($dosen_list as $d): ?>
                        <option value="<?= $d['dosen_id'] ?>">
                            <?= htmlspecialchars($d['nid']) ?> - <?= htmlspecialchars($d['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Tingkat <span class="required">*</span></label>
                    <select class="form-select" name="tingkat" required>
                        <option value="">-- Pilih --</option>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>">Tingkat <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Rombel <span class="required">*</span></label>
                    <select class="form-select" name="rombel" required>
                        <option value="">-- Pilih --</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Hari <span class="required">*</span></label>
                    <select class="form-select" name="hari" required>
                        <option value="">-- Pilih Hari --</option>
                        <?php foreach ($hari_list as $h): ?>
                            <option value="<?= $h ?>"><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ruang</label>
                    <input class="form-control" type="text" name="ruang" placeholder="Contoh: Lab 1">
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Jam Mulai <span class="required">*</span></label>
                    <input class="form-control" type="time" name="jam_mulai" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jam Selesai <span class="required">*</span></label>
                    <input class="form-control" type="time" name="jam_selesai" required>
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Kapasitas <span class="required">*</span></label>
                    <input class="form-control" type="number" name="kapasitas" value="30" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tahun Ajaran</label>
                    <input class="form-control" type="text" name="tahun_ajaran" value="<?= date('Y') ?>/<?= date('Y')+1 ?>" placeholder="2026/2027">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Semester Ke</label>
                <select class="form-select" name="semester_ke">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL EDIT JADWAL -->
<!-- ============================================ -->
<div id="modalEdit" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalEdit')"></div>
    <div class="modal-content modal-lg" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-alt"></i> Edit Jadwal</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>

        <form method="POST" id="formEdit">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label">Mata Kuliah <span class="required">*</span></label>
                <select class="form-select" name="course_id" id="edit_course_id" required>
                    <?php foreach ($matakuliah as $mk): ?>
                        <option value="<?= $mk['id'] ?>">
                            <?= htmlspecialchars($mk['kode_mk']) ?> - <?= htmlspecialchars($mk['nama_mk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Dosen <span class="required">*</span></label>
                <select class="form-select" name="dosen_id" id="edit_dosen_id" required>
                    <?php foreach ($dosen_list as $d): ?>
                        <option value="<?= $d['dosen_id'] ?>">
                            <?= htmlspecialchars($d['nid']) ?> - <?= htmlspecialchars($d['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Tingkat <span class="required">*</span></label>
                    <select class="form-select" name="tingkat" id="edit_tingkat" required>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>">Tingkat <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Rombel <span class="required">*</span></label>
                    <select class="form-select" name="rombel" id="edit_rombel" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Hari <span class="required">*</span></label>
                    <select class="form-select" name="hari" id="edit_hari" required>
                        <?php foreach ($hari_list as $h): ?>
                            <option value="<?= $h ?>"><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ruang</label>
                    <input class="form-control" type="text" name="ruang" id="edit_ruang">
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Jam Mulai <span class="required">*</span></label>
                    <input class="form-control" type="time" name="jam_mulai" id="edit_jam_mulai" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jam Selesai <span class="required">*</span></label>
                    <input class="form-control" type="time" name="jam_selesai" id="edit_jam_selesai" required>
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Kapasitas <span class="required">*</span></label>
                    <input class="form-control" type="number" name="kapasitas" id="edit_kapasitas" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tahun Ajaran</label>
                    <input class="form-control" type="text" name="tahun_ajaran" id="edit_tahun_ajaran">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Semester Ke</label>
                <select class="form-select" name="semester_ke" id="edit_semester_ke">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// MODAL
// ============================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================
// EDIT JADWAL
// ============================================
function editJadwal(id) {
    showToast('Memuat data...', 'info');

    fetch('/simak_app/public/admin/jadwal_get.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_course_id').value = data.course_id;
                document.getElementById('edit_dosen_id').value = data.dosen_id;
                document.getElementById('edit_tingkat').value = data.tingkat;
                document.getElementById('edit_rombel').value = data.rombel;
                document.getElementById('edit_hari').value = data.hari;
                document.getElementById('edit_jam_mulai').value = data.jam_mulai;
                document.getElementById('edit_jam_selesai').value = data.jam_selesai;
                document.getElementById('edit_ruang').value = data.ruang || '';
                document.getElementById('edit_kapasitas').value = data.kapasitas;
                document.getElementById('edit_tahun_ajaran').value = data.tahun_ajaran || '';
                document.getElementById('edit_semester_ke').value = data.semester_ke || 1;

                openModal('modalEdit');
            } else {
                showToast(data.message || 'Gagal memuat data', 'error');
            }
        })
        .catch(error => {
            showToast('Terjadi kesalahan: ' + error, 'error');
        });
}

// ============================================
// DELETE
// ============================================
function deleteJadwal(id) {
    if (confirm('Yakin hapus jadwal ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================
// ESC CLOSE MODAL
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('modalTambah');
        closeModal('modalEdit');
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
