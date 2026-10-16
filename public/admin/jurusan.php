<?php
// ============================================
// public/admin/jurusan.php
// CRUD Jurusan
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MasterDataController.php';

checkAccess(['admin']);

$page_title = 'Master Jurusan';
$masterController = new MasterDataController($pdo);

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $result = $masterController->createJurusan($_POST);
            redirectWithMessage(
                '/simak_app/public/admin/jurusan.php',
                $result['success'] ? 'Jurusan berhasil ditambahkan!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;

        case 'update':
            $result = $masterController->updateJurusan($_POST['id'], $_POST);
            redirectWithMessage(
                '/simak_app/public/admin/jurusan.php',
                $result['success'] ? 'Jurusan berhasil diupdate!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;

        case 'delete':
            $result = $masterController->deleteJurusan($_POST['id']);
            redirectWithMessage(
                '/simak_app/public/admin/jurusan.php',
                $result['success'] ? 'Jurusan berhasil dihapus!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;
    }
}

$filter_fakultas = $_GET['fakultas_id'] ?? null;
$jurusan_list = $masterController->getAllJurusan($filter_fakultas);
$fakultas_list = $masterController->getFakultasList();

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* Select fakultas jangan melar penuh: tombol Tambah tetap sebaris dengan filter */
.table-header .table-actions .form-select {
    width: 280px;
    max-width: 100%;
    flex: 0 1 280px;
}

@media (max-width: 480px) {
    .table-header .table-actions .form-select {
        width: 200px;
        flex-basis: 200px;
    }
}
</style>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-book"></i> Master Jurusan</h3>
        <div class="table-actions">
            <select class="form-select" onchange="window.location.href='?fakultas_id='+this.value">
                <option value="">-- Semua Fakultas --</option>
                <?php foreach ($fakultas_list as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $filter_fakultas == $f['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-success" onclick="openModal('modalTambah')">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
    </div>

    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Jurusan</th>
                <th>Fakultas</th>
                <th>Jenjang</th>
                <th>Ketua Jurusan</th>
                <th>Mahasiswa</th>
                <th class="th-actions">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jurusan_list as $j): ?>
                <tr>
                    <td><span class="cell-strong"><?= htmlspecialchars($j['kode']) ?></span></td>
                    <td><span class="cell-main"><?= htmlspecialchars($j['nama']) ?></span></td>
                    <td><?= htmlspecialchars($j['fakultas_nama']) ?></td>
                    <td><span class="jenjang-text"><?= htmlspecialchars($j['jenjang']) ?></span></td>
                    <td><?= htmlspecialchars($j['ketua_jurusan'] ?? '–') ?></td>
                    <td>
                        <span class="count-item <?= $j['jumlah_mahasiswa'] > 0 ? '' : 'count-zero' ?>">
                            <i class="fas fa-user-graduate"></i> <?= $j['jumlah_mahasiswa'] ?> mhs
                        </span>
                    </td>
                    <td class="td-actions">
                        <span class="row-actions">
                            <button class="btn btn-sm btn-icon btn-icon-light" title="Edit"
                                    onclick='editJurusan(<?= json_encode($j, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-icon btn-icon-danger-light" title="Hapus"
                                    onclick="deleteJurusan(<?= $j['id'] ?>, '<?= htmlspecialchars($j['nama'], ENT_QUOTES) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($jurusan_list)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <h3>Belum ada data jurusan</h3>
                            <p>Tambahkan jurusan pertama melalui tombol "Tambah".</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalTambah')"></div>
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Tambah Jurusan</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Fakultas <span class="required">*</span></label>
                <select class="form-select" name="fakultas_id" required>
                    <option value="">-- Pilih Fakultas --</option>
                    <?php foreach ($fakultas_list as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Kode Jurusan <span class="required">*</span></label>
                    <input class="form-control" type="text" name="kode" required placeholder="Contoh: TI, SI" maxlength="10" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label class="form-label">Jenjang <span class="required">*</span></label>
                    <select class="form-select" name="jenjang" required>
                        <option value="D3">D3</option>
                        <option value="D4">D4</option>
                        <option value="S1">S1</option>
                        <option value="S2">S2</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nama Jurusan <span class="required">*</span></label>
                <input class="form-control" type="text" name="nama" required placeholder="Contoh: Teknik Informatika">
            </div>
            <div class="form-group">
                <label class="form-label">Ketua Jurusan</label>
                <input class="form-control" type="text" name="ketua_jurusan" placeholder="Nama ketua jurusan">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalEdit')"></div>
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Jurusan</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label">Fakultas <span class="required">*</span></label>
                <select class="form-select" name="fakultas_id" id="edit_fakultas_id" required>
                    <?php foreach ($fakultas_list as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Kode Jurusan <span class="required">*</span></label>
                    <input class="form-control" type="text" name="kode" id="edit_kode" required maxlength="10" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label class="form-label">Jenjang <span class="required">*</span></label>
                    <select class="form-select" name="jenjang" id="edit_jenjang" required>
                        <option value="D3">D3</option>
                        <option value="D4">D4</option>
                        <option value="S1">S1</option>
                        <option value="S2">S2</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nama Jurusan <span class="required">*</span></label>
                <input class="form-control" type="text" name="nama" id="edit_nama" required>
            </div>
            <div class="form-group">
                <label class="form-label">Ketua Jurusan</label>
                <input class="form-control" type="text" name="ketua_jurusan" id="edit_ketua_jurusan">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

function editJurusan(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_fakultas_id').value = data.fakultas_id;
    document.getElementById('edit_kode').value = data.kode;
    document.getElementById('edit_nama').value = data.nama;
    document.getElementById('edit_jenjang').value = data.jenjang;
    document.getElementById('edit_ketua_jurusan').value = data.ketua_jurusan || '';
    openModal('modalEdit');
}

function deleteJurusan(id, nama) {
    if (confirm('Yakin hapus jurusan "' + nama + '"?')) {
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

// ESC menutup modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('modalTambah');
        closeModal('modalEdit');
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
