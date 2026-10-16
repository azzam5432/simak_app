<?php
// ============================================
// public/admin/matakuliah.php
// CRUD Mata Kuliah
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

checkAccess(['admin']);

$page_title = 'Data Mata Kuliah';
$adminController = new AdminController($pdo);

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $result = $adminController->createMatakuliah($_POST);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/admin/matakuliah.php', 'Mata kuliah berhasil ditambahkan!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/admin/matakuliah.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'update':
            $result = $adminController->updateMatakuliah($_POST['id'], $_POST);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/admin/matakuliah.php', 'Mata kuliah berhasil diupdate!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/admin/matakuliah.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;

        case 'delete':
            $result = $adminController->deleteMatakuliah($_POST['id']);
            if ($result['success']) {
                redirectWithMessage('/simak_app/public/admin/matakuliah.php', 'Mata kuliah berhasil dihapus!', 'success');
            } else {
                redirectWithMessage('/simak_app/public/admin/matakuliah.php', 'Gagal: ' . $result['message'], 'error');
            }
            break;
    }
}

$matakuliah = $adminController->getAllMatakuliah();
$jurusan_list = $adminController->getJurusanList();

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-book-open"></i> Data Mata Kuliah</h3>
        <div class="table-actions">
            <input class="form-control" type="text" id="tableSearch" placeholder="Cari kode atau nama..." style="min-width: 200px;">
            <button class="btn btn-success" onclick="openModal('modalTambah')">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Mata Kuliah</th>
                    <th>SKS / Smt</th>
                    <th>Jurusan</th>
                    <th>Kelas</th>
                    <th class="th-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matakuliah as $mk): ?>
                    <tr>
                        <td><span class="cell-strong"><?= htmlspecialchars($mk['kode_mk']) ?></span></td>
                        <td>
                            <span class="cell-main"><?= htmlspecialchars($mk['nama_mk']) ?></span>
                            <?php if (!empty($mk['deskripsi'])): ?>
                                <span class="cell-sub"><?= htmlspecialchars(mb_strimwidth($mk['deskripsi'], 0, 60, '…')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="count-item"><i class="fas fa-coins"></i> <?= $mk['sks'] ?> SKS</span>
                            <span class="cell-sub">Semester <?= $mk['semester'] ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($mk['jurusan_nama']) ?>
                            <span class="cell-sub"><?= htmlspecialchars($mk['jurusan_kode']) ?> · <?= $mk['jenjang'] ?></span>
                        </td>
                        <td>
                            <span class="count-item <?= $mk['total_kelas'] > 0 ? '' : 'count-zero' ?>">
                                <i class="fas fa-chalkboard"></i> <?= $mk['total_kelas'] ?> kelas
                            </span>
                        </td>
                        <td class="td-actions">
                            <span class="row-actions">
                                <button class="btn btn-sm btn-icon btn-icon-light" title="Edit" onclick="editMatakuliah(<?= $mk['id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-icon btn-icon-danger-light" title="Hapus" onclick="deleteMatakuliah(<?= $mk['id'] ?>, '<?= htmlspecialchars($mk['kode_mk']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($matakuliah)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <h3>Belum ada data mata kuliah</h3>
                                <p>Tambahkan mata kuliah pertama melalui tombol "Tambah".</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL TAMBAH MATA KULIAH -->
<!-- ============================================ -->
<div id="modalTambah" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalTambah')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-book-medical"></i> Tambah Mata Kuliah</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Kode Mata Kuliah <span class="required">*</span></label>
                    <input class="form-control" type="text" name="kode_mk" required placeholder="Contoh: TI101" style="text-transform: uppercase;">
                    <small class="help-text">Format: [KODE JURUSAN][ANGKA]</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Semester <span class="required">*</span></label>
                    <input class="form-control" type="number" name="semester" min="1" max="8" value="1" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nama Mata Kuliah <span class="required">*</span></label>
                <input class="form-control" type="text" name="nama_mk" required placeholder="Contoh: Pemrograman Web">
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">SKS <span class="required">*</span></label>
                    <input class="form-control" type="number" name="sks" min="1" max="6" value="3" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jurusan <span class="required">*</span></label>
                    <select class="form-select" name="jurusan_id" required>
                        <option value="">-- Pilih Jurusan --</option>
                        <?php foreach ($jurusan_list as $j): ?>
                            <option value="<?= $j['id'] ?>">
                                [<?= htmlspecialchars($j['fakultas_kode']) ?>]
                                <?= htmlspecialchars($j['nama']) ?>
                                (<?= $j['jenjang'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi (opsional)</label>
                <textarea class="form-control" name="deskripsi" rows="3" placeholder="Deskripsi mata kuliah..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL EDIT MATA KULIAH -->
<!-- ============================================ -->
<div id="modalEdit" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalEdit')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-book-open"></i> Edit Mata Kuliah</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>

        <form method="POST" id="formEdit">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Kode Mata Kuliah <span class="required">*</span></label>
                    <input class="form-control" type="text" name="kode_mk" id="edit_kode_mk" required style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label class="form-label">Semester <span class="required">*</span></label>
                    <input class="form-control" type="number" name="semester" id="edit_semester" min="1" max="8" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nama Mata Kuliah <span class="required">*</span></label>
                <input class="form-control" type="text" name="nama_mk" id="edit_nama_mk" required>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">SKS <span class="required">*</span></label>
                    <input class="form-control" type="number" name="sks" id="edit_sks" min="1" max="6" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jurusan <span class="required">*</span></label>
                    <select class="form-select" name="jurusan_id" id="edit_jurusan_id" required>
                        <option value="">-- Pilih Jurusan --</option>
                        <?php foreach ($jurusan_list as $j): ?>
                            <option value="<?= $j['id'] ?>">
                                [<?= htmlspecialchars($j['fakultas_kode']) ?>]
                                <?= htmlspecialchars($j['nama']) ?>
                                (<?= $j['jenjang'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi (opsional)</label>
                <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
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
// EDIT MATA KULIAH
// ============================================
function editMatakuliah(id) {
    showToast('Memuat data...', 'info');

    fetch('/simak_app/public/admin/matakuliah_get.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_kode_mk').value = data.kode_mk;
                document.getElementById('edit_nama_mk').value = data.nama_mk;
                document.getElementById('edit_sks').value = data.sks;
                document.getElementById('edit_semester').value = data.semester;
                document.getElementById('edit_jurusan_id').value = data.jurusan_id;
                document.getElementById('edit_deskripsi').value = data.deskripsi || '';

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
function deleteMatakuliah(id, kode) {
    if (confirm('Yakin hapus mata kuliah ' + kode + '?\n\nData jadwal terkait juga akan terhapus.')) {
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
