<?php
// ============================================
// public/admin/mahasiswa.php
// CRUD Mahasiswa V2 - Fakultas/Jurusan
// ============================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../controllers/MasterDataController.php';

checkAccess(['admin']);

$page_title = 'Data Mahasiswa';
$adminController = new AdminController($pdo);
$masterController = new MasterDataController($pdo);

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $result = $adminController->createMahasiswa($_POST);
            redirectWithMessage(
                '/simak_app/public/admin/mahasiswa.php',
                $result['success'] ? 'Mahasiswa berhasil ditambahkan!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;

        case 'update':
            $result = $adminController->updateMahasiswa($_POST['mahasiswa_id'], $_POST);
            redirectWithMessage(
                '/simak_app/public/admin/mahasiswa.php',
                $result['success'] ? 'Mahasiswa berhasil diupdate!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;

        case 'delete':
            $result = $adminController->deleteMahasiswa($_POST['mahasiswa_id']);
            redirectWithMessage(
                '/simak_app/public/admin/mahasiswa.php',
                $result['success'] ? 'Mahasiswa berhasil dihapus!' : 'Gagal: ' . $result['message'],
                $result['success'] ? 'success' : 'error'
            );
            break;
    }
}

$mahasiswa_list = $adminController->getAllMahasiswa();
$fakultas_list = $masterController->getFakultasList();
$jurusan_list = $masterController->getJurusanList();
$tahun_ajaran_otomatis = MasterDataController::getTahunAjaranOtomatis();
$semester_label = MasterDataController::getSemesterLabel();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Info Tahun Ajaran Aktif -->
<div class="period-banner">
    <div>
        <div class="period-title"><i class="fas fa-calendar-alt"></i> Periode Akademik Aktif</div>
        <div class="period-value"><?= $tahun_ajaran_otomatis ?> · Semester <?= $semester_label ?></div>
    </div>
    <div class="period-note">
        <i class="fas fa-info-circle"></i> Semester mahasiswa dihitung otomatis dari tingkat
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h3><i class="fas fa-user-graduate"></i> Data Mahasiswa</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" id="tableSearch" placeholder="Cari NIM, nama, jurusan..." style="min-width: 220px;">
            <button class="btn btn-primary" onclick="openModal('modalTambah')">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Nama & Email</th>
                    <th>Jurusan</th>
                    <th>Tahun Ajaran</th>
                    <th>Tingkat / Semester</th>
                    <th class="th-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mahasiswa_list as $m): ?>
                    <?php
                    $sem = $m['semester'] ?? 1;
                    ?>
                    <tr>
                        <td><span class="cell-strong"><?= htmlspecialchars($m['nim']) ?></span></td>
                        <td>
                            <span class="cell-main"><?= htmlspecialchars($m['nama']) ?></span>
                            <span class="cell-sub"><?= htmlspecialchars($m['email'] ?: '–') ?></span>
                        </td>
                        <td>
                            <?php if ($m['jurusan_nama']): ?>
                                <?= htmlspecialchars($m['jurusan_nama']) ?>
                                <span class="cell-sub"><?= htmlspecialchars($m['fakultas_nama'] ?? '') ?> · <?= $m['jenjang'] ?></span>
                            <?php else: ?>
                                <span class="cell-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($m['tahun_ajaran'] ?? '–') ?></td>
                        <td>
                            <span class="count-item"><i class="fas fa-layer-group"></i> Tingkat <?= $m['tingkat'] ?? 1 ?> · Smt <?= $sem ?></span>
                        </td>
                        <td class="td-actions">
                            <span class="row-actions">
                                <button class="btn btn-sm btn-icon btn-icon-light" title="Edit" onclick="editMahasiswa(<?= $m['mahasiswa_id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-icon btn-icon-danger-light" title="Hapus" onclick="deleteMahasiswa(<?= $m['mahasiswa_id'] ?>, '<?= htmlspecialchars($m['nama'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($mahasiswa_list)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h3>Belum ada data mahasiswa</h3>
                                <p>Tambahkan mahasiswa pertama melalui tombol "Tambah".</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="modal">
    <div class="modal-overlay" onclick="closeModal('modalTambah')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Tambah Mahasiswa</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <h4 class="section-title" style="margin-top: 0;"><i class="fas fa-user"></i> Data Pribadi</h4>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>NIM <span class="required">*</span></label>
                    <input type="text" name="nim" required placeholder="Contoh: 202401001">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="nama" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="email@domain.com">
            </div>

            <h4 class="section-title"><i class="fas fa-university"></i> Data Akademik</h4>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Fakultas <span class="required">*</span></label>
                    <select name="fakultas_id" id="tambah_fakultas" required onchange="filterJurusan('tambah')">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach ($fakultas_list as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jurusan <span class="required">*</span></label>
                    <select name="jurusan_id" id="tambah_jurusan" required>
                        <option value="">-- Pilih Fakultas Dulu --</option>
                    </select>
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Tahun Ajaran <span class="required">*</span></label>
                    <input type="text" name="tahun_ajaran" value="<?= $tahun_ajaran_otomatis ?>" required placeholder="2024/2025">
                    <small class="help-text">Otomatis mengikuti periode aktif, bisa diubah</small>
                </div>
                <div class="form-group">
                    <label>Tingkat <span class="required">*</span></label>
                    <select name="tingkat" id="tambah_tingkat" required onchange="hitungSemester('tambah')">
                        <option value="1">Tingkat 1</option>
                        <option value="2">Tingkat 2</option>
                        <option value="3">Tingkat 3</option>
                        <option value="4">Tingkat 4</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Semester (Otomatis)</label>
                <input type="text" id="tambah_semester_info" readonly style="background: #f4f6f8; font-weight: 600;">
                <small class="help-text"><i class="fas fa-info-circle"></i> Semester <?= $semester_label ?> (<?= date('n') ?>/<?= date('m') ?> bulan ini)</small>
            </div>

            <h4 class="section-title"><i class="fas fa-key"></i> Akun Login</h4>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="text" name="password" value="password123" required>
                    <small class="help-text">Default: password123</small>
                </div>
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
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Mahasiswa</h3>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">&times;</button>
        </div>

        <form method="POST" id="formEdit">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="mahasiswa_id" id="edit_mahasiswa_id">

            <h4 class="section-title" style="margin-top: 0;"><i class="fas fa-user"></i> Data Pribadi</h4>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>NIM <span class="required">*</span></label>
                    <input type="text" name="nim" id="edit_nim" required>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="nama" id="edit_nama" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit_email">
            </div>

            <h4 class="section-title"><i class="fas fa-university"></i> Data Akademik</h4>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Fakultas <span class="required">*</span></label>
                    <select name="fakultas_id" id="edit_fakultas" required onchange="filterJurusan('edit')">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach ($fakultas_list as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jurusan <span class="required">*</span></label>
                    <select name="jurusan_id" id="edit_jurusan" required>
                        <option value="">-- Pilih Jurusan --</option>
                    </select>
                </div>
            </div>

            <div class="row row-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Tahun Ajaran <span class="required">*</span></label>
                    <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" required>
                </div>
                <div class="form-group">
                    <label>Tingkat <span class="required">*</span></label>
                    <select name="tingkat" id="edit_tingkat" required onchange="hitungSemester('edit')">
                        <option value="1">Tingkat 1</option>
                        <option value="2">Tingkat 2</option>
                        <option value="3">Tingkat 3</option>
                        <option value="4">Tingkat 4</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Semester (Otomatis)</label>
                <input type="text" id="edit_semester_info" readonly style="background: #f4f6f8; font-weight: 600;">
            </div>

            <h4 class="section-title"><i class="fas fa-key"></i> Akun Login</h4>

            <div class="form-group">
                <label>Password (kosongkan jika tidak diubah)</label>
                <input type="text" name="password" placeholder="Kosongkan jika tidak diubah">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
// Data jurusan (untuk filter)
const jurusanData = <?= json_encode($jurusan_list) ?>;
const fakultasData = <?= json_encode($fakultas_list) ?>;
const semesterLabel = '<?= $semester_label ?>';

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

// Filter jurusan berdasarkan fakultas
function filterJurusan(mode) {
    const fakultasSelect = document.getElementById(mode + '_fakultas');
    const jurusanSelect = document.getElementById(mode + '_jurusan');
    const fakultasId = fakultasSelect.value;

    // Reset
    jurusanSelect.innerHTML = '<option value="">-- Pilih Jurusan --</option>';

    if (!fakultasId) return;

    // Filter
    jurusanData.forEach(j => {
        if (j.fakultas_id == fakultasId) {
            const opt = document.createElement('option');
            opt.value = j.id;
            opt.textContent = j.nama + ' (' + j.jenjang + ')';
            jurusanSelect.appendChild(opt);
        }
    });
}

// Hitung semester otomatis
function hitungSemester(mode) {
    const tingkat = parseInt(document.getElementById(mode + '_tingkat').value);
    const isGanjil = semesterLabel === 'Ganjil';

    const semester = isGanjil ? (tingkat * 2) - 1 : tingkat * 2;
    const label = semester % 2 === 1 ? 'Ganjil' : 'Genap';

    document.getElementById(mode + '_semester_info').value =
        'Semester ' + semester + ' (' + label + ')';
}

// Edit mahasiswa via AJAX
function editMahasiswa(id) {
    fetch('/simak_app/public/admin/mahasiswa_get.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Gagal memuat data', 'error');
                return;
            }

            document.getElementById('edit_mahasiswa_id').value = data.mahasiswa_id;
            document.getElementById('edit_nim').value = data.nim;
            document.getElementById('edit_nama').value = data.nama;
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_tahun_ajaran').value = data.tahun_ajaran || '';
            document.getElementById('edit_tingkat').value = data.tingkat || 1;

            // Set fakultas & jurusan
            document.getElementById('edit_fakultas').value = data.fakultas_id || '';
            filterJurusan('edit');

            // Setelah filter jurusan selesai, set jurusan
            setTimeout(() => {
                document.getElementById('edit_jurusan').value = data.jurusan_id || '';
                hitungSemester('edit');
            }, 100);

            openModal('modalEdit');
        })
        .catch(err => {
            showToast('Terjadi kesalahan: ' + err, 'error');
        });
}

function deleteMahasiswa(id, nama) {
    if (confirm('Yakin hapus mahasiswa "' + nama + '"?\n\nAkun login dan data terkait akan ikut terhapus.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="mahasiswa_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Init semester saat halaman load
document.addEventListener('DOMContentLoaded', function() {
    hitungSemester('tambah');
});

// ESC menutup modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('modalTambah');
        closeModal('modalEdit');
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
