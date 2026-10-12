<?php
// ============================================
// public/admin/fakultas.php
// CRUD Fakultas
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MasterDataController.php';

checkAccess(['admin']);

$page_title = 'Master Fakultas';
$masterController = new MasterDataController($pdo);

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $result = $masterController->createFakultas($_POST);
            redirectWithMessage(
                '/simak_app/public/admin/fakultas.php',
                $result['success'] ? 'Fakultas berhasil ditambahkan!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;

        case 'update':
            $result = $masterController->updateFakultas($_POST['id'], $_POST);
            redirectWithMessage(
                '/simak_app/public/admin/fakultas.php',
                $result['success'] ? 'Fakultas berhasil diupdate!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;

        case 'delete':
            $result = $masterController->deleteFakultas($_POST['id']);
            redirectWithMessage(
                '/simak_app/public/admin/fakultas.php',
                $result['success'] ? 'Fakultas berhasil dihapus!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;
    }
}

$fakultas_list = $masterController->getAllFakultas();

include __DIR__ . '/../../includes/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-university"></i> Master Fakultas</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" id="tableSearch" placeholder="Cari fakultas..." style="min-width: 200px;">
            <button class="btn btn-primary" onclick="openModal('modalTambah')">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Fakultas</th>
                <th>Dekan</th>
                <th>Jurusan</th>
                <th>Mahasiswa</th>
                <th class="th-actions">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fakultas_list as $f): ?>
                <tr>
                    <td><span class="cell-strong"><?= htmlspecialchars($f['kode']) ?></span></td>
                    <td><span class="cell-main"><?= htmlspecialchars($f['nama']) ?></span></td>
                    <td><?= htmlspecialchars($f['dekan'] ?? '–') ?></td>
                    <td>
                        <span class="count-item <?= $f['jumlah_jurusan'] > 0 ? '' : 'count-zero' ?>">
                            <i class="fas fa-book"></i> <?= $f['jumlah_jurusan'] ?> jurusan
                        </span>
                    </td>
                    <td>
                        <span class="count-item <?= $f['jumlah_mahasiswa'] > 0 ? '' : 'count-zero' ?>">
                            <i class="fas fa-user-graduate"></i> <?= $f['jumlah_mahasiswa'] ?> mhs
                        </span>
                    </td>
                    <td class="td-actions">
                        <span class="row-actions">
                            <button class="btn btn-sm btn-icon btn-icon-light" title="Edit"
                                    onclick='editFakultas(<?= json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-icon btn-icon-danger-light" title="Hapus"
                                    onclick="deleteFakultas(<?= $f['id'] ?>, '<?= htmlspecialchars($f['nama'], ENT_QUOTES) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($fakultas_list)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-university"></i>
                            <h3>Belum ada data fakultas</h3>
                            <p>Tambahkan fakultas pertama melalui tombol "Tambah".</p>
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
            <h3><i class="fas fa-plus"></i> Tambah Fakultas</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Kode Fakultas <span class="required">*</span></label>
                <input type="text" name="kode" required placeholder="Contoh: FT, FE, FIK" maxlength="10" style="text-transform: uppercase;">
                <small class="help-text">Maksimal 10 karakter, otomatis huruf kapital</small>
            </div>
            <div class="form-group">
                <label>Nama Fakultas <span class="required">*</span></label>
                <input type="text" name="nama" required placeholder="Contoh: Fakultas Teknik">
            </div>
            <div class="form-group">
                <label>Dekan</label>
                <input type="text" name="dekan" placeholder="Nama dekan">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalEdit')"></div>
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Fakultas</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label>Kode Fakultas <span class="required">*</span></label>
                <input type="text" name="kode" id="edit_kode" required maxlength="10" style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label>Nama Fakultas <span class="required">*</span></label>
                <input type="text" name="nama" id="edit_nama" required>
            </div>
            <div class="form-group">
                <label>Dekan</label>
                <input type="text" name="dekan" id="edit_dekan">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
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

function editFakultas(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_kode').value = data.kode;
    document.getElementById('edit_nama').value = data.nama;
    document.getElementById('edit_dekan').value = data.dekan || '';
    openModal('modalEdit');
}

function deleteFakultas(id, nama) {
    if (confirm('Yakin hapus fakultas "' + nama + '"?')) {
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
