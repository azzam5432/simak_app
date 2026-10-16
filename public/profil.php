<?php
// ============================================
// public/profil.php
// Halaman Profil User (Semua Role)
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/ProfileController.php';

// Cek login (semua role bisa akses)
if (!isLoggedIn()) {
    header("Location: /simak_app/public/index.php");
    exit();
}

$page_title = 'Profil Saya';
$profileController = new ProfileController($pdo, $_SESSION['user_id']);

$user = $profileController->getProfile();

if (!$user) {
    redirectWithMessage('/simak_app/public/logout.php', 'User tidak ditemukan', 'error');
}

$foto_url = $profileController->getFotoUrl($user);
$initial = strtoupper(substr($user['nama'], 0, 1));

// Hak edit:
// - Admin     : semua data (nama, username, email, foto, password) tanpa persetujuan
// - Dosen     : hanya username + password
// - Mahasiswa : hanya username + password
$role = $_SESSION['role'];
$can_edit_full = ($role === 'admin'); // profile avatar & nama/email
$can_edit_username = true;   // semua role: username
$can_change_password = true;

include __DIR__ . '/../includes/header.php';
?>


<div class="profile-container">
    
    <!-- SIDEBAR: Foto & Info -->
    <div class="profile-card">
        <!-- Avatar + tombol kamera overlay -->
        <div class="profile-avatar-wrap">
            <div class="profile-avatar" id="avatarContainer">
                <?php if ($foto_url): ?>
                    <img src="<?= htmlspecialchars($foto_url) ?>" alt="Foto Profil" id="avatarImage">
                <?php else: ?>
                    <span id="avatarInitial"><?= $initial ?></span>
                <?php endif; ?>
            </div>
            <?php if ($can_edit_full): ?>
                <button type="button" class="profile-avatar-edit" title="Ganti foto profil" onclick="document.getElementById('fotoInput').click()">
                    <i class="fas fa-camera"></i>
                </button>
            <?php endif; ?>
        </div>
        <?php if ($can_edit_full): ?>
            <input type="file" id="fotoInput" accept="image/jpeg,image/jpg,image/png,image/webp" style="display: none;">
        
        <?php if ($can_edit_full && $foto_url): ?>
            <div class="avatar-actions mb-2">
                <button class="btn-delete-photo" onclick="hapusFoto()">
                    <i class="fas fa-trash"></i> Hapus Foto
                </button>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- Nama -->
        <div class="profile-name"><?= htmlspecialchars($user['nama']) ?></div>
        
        <!-- Info -->
        <div class="profile-info">
            <div class="profile-info-item">
                <span class="label">Username</span>
                <span class="value"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            
            <?php if ($user['role'] === 'mahasiswa' && $user['detail']): ?>
                <div class="profile-info-item">
                    <span class="label">NIM</span>
                    <span class="value"><?= htmlspecialchars($user['detail']['nim']) ?></span>
                </div>
                <div class="profile-info-item">
                    <span class="label">Fakultas</span>
                    <span class="value"><?= htmlspecialchars($user['detail']['fakultas_nama'] ?: '-') ?></span>
                </div>
                <div class="profile-info-item">
                    <span class="label">Jurusan</span>
                    <span class="value"><?= htmlspecialchars($user['detail']['jurusan_nama'] ?: '-') ?></span>
                </div>
                <div class="profile-info-item">
                    <span class="label">Tingkat</span>
                    <span class="value"><?= $user['detail']['tingkat'] ?></span>
                </div>
                <div class="profile-info-item">
                    <span class="label">Semester</span>
                    <span class="value">Semester <?= $user['detail']['semester'] ?></span>
                </div>
                
            <?php elseif ($user['role'] === 'dosen' && $user['detail']): ?>
                <div class="profile-info-item">
                    <span class="label">NID</span>
                    <span class="value"><?= htmlspecialchars($user['detail']['nid']) ?></span>
                </div>
                <div class="profile-info-item">
                    <span class="label">Fakultas</span>
                    <span class="value"><?= htmlspecialchars($user['detail']['fakultas_nama'] ?: '-') ?></span>
                </div>
                <div class="profile-info-item">
                    <span class="label">Jurusan</span>
                    <span class="value"><?= htmlspecialchars($user['detail']['jurusan_nama'] ?: '-') ?></span>
                </div>
            <?php endif; ?>
            
            <div class="profile-info-item">
                <span class="label">Email</span>
                <span class="value" style="word-break: break-all;"><?= htmlspecialchars($user['email']) ?></span>
            </div>
        </div>
    </div>
    
    <!-- MAIN: Tabs -->
    <div class="tab-panel-card">
        <div class="tab-nav">
            <button type="button" class="tab-nav-btn active" onclick="switchTab('info', this)">
                <i class="fas fa-user"></i> <?= $can_edit_full ? 'Ubah Data Akun' : 'Informasi Akun' ?>
            </button>
            <?php if ($can_change_password): ?>
                <button type="button" class="tab-nav-btn" onclick="switchTab('password', this)">
                    <i class="fas fa-lock"></i> Ganti Password
                </button>
            <?php endif; ?>
        </div>
        
        <!-- TAB 1: Informasi / Ubah Data Akun -->
        <div class="tab-content active" id="tab-info">
            <?= $can_edit_full ? '<h3><i class="fas fa-user-edit"></i> Ubah Data Akun</h3>' : '<h3><i class="fas fa-user-circle"></i> Informasi Akun</h3>' ?>
            
            <div class="form-info">
                <i class="fas fa-info-circle"></i>
                <?php if ($can_edit_full): ?>
                    <span><strong>Admin dapat mengubah semua data</strong> (nama, username, email) langsung tanpa perlu persetujuan. Ubah data lalu klik Simpan.</span>
                <?php else: ?>
                    <span><strong>Semua data terkunci kecuali username.</strong> Hanya username yang dapat diubah dari halaman ini. Hubungi Admin jika data lain perlu diperbarui.</span>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="/simak_app/public/profil_update.php" id="formAccount">
                <input type="hidden" name="action" value="update_account">
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <?php if ($can_edit_full): ?>
                        <input type="text" class="form-control" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" readonly>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <?php if ($can_edit_full): ?>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>">
                    <?php else: ?>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Terdaftar Sejak</label>
                    <input type="text" class="form-control" value="<?= date('d F Y', strtotime($user['created_at'])) ?>" readonly>
                </div>
                
                <div class="profile-submit">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($can_change_password): ?>
        <!-- TAB 2: Ganti Password -->
        <div class="tab-content" id="tab-password">
            <h3><i class="fas fa-key"></i> Ganti Password</h3>
            
            <div class="form-info">
                <i class="fas fa-shield-alt"></i>
                <strong>Tips Keamanan:</strong> Gunakan password minimal 6 karakter,
                kombinasi huruf besar, huruf kecil, dan angka.
            </div>
            
            <form method="POST" action="/simak_app/public/profil_update.php" id="formPassword">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Password Lama <span style="color: #e74c3c;">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" name="current_password" id="current_password" required>
                        <button type="button" class="password-toggle toggle-password" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password Baru <span style="color: #e74c3c;">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6">
                        <button type="button" class="password-toggle toggle-password" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="password-strength" data-target="new_password"></span>
                    <span class="password-hint">Minimal 6 karakter</span>
                </div>
                
                <div class="form-group">
                    <label>Konfirmasi Password Baru <span style="color: #e74c3c;">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="6">
                        <button type="button" class="password-toggle toggle-password" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Password Baru
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
</div>

<?php if ($can_edit_full): ?>
<!-- Form Delete Foto (Hidden) -->
<form method="POST" action="/simak_app/public/profil_update.php" id="formDeleteFoto" style="display: none;">
    <input type="hidden" name="action" value="delete_foto">
</form>
<?php endif; ?>

<script>
// ============================================
// TAB SWITCHING
// ============================================

function switchTab(tab, btn) {
    // Simpan posisi scroll agar halaman tidak bergeser saat pindah tab
    const scrollY = window.scrollY;
    
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-nav-btn').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById('tab-' + tab);
    if (target) target.classList.add('active');
    if (btn) btn.classList.add('active');
    
    // Kembalikan posisi scroll persis seperti sebelum pindah tab
    window.scrollTo({ top: scrollY, left: window.scrollX, behavior: 'instant' });
}

<?php if ($can_edit_full): ?>
// ============================================
// HAPUS FOTO PROFIL
// ============================================

function hapusFoto() {
    if (confirm('Yakin ingin menghapus foto profil?')) {
        document.getElementById('formDeleteFoto').submit();
    }
}

// ============================================
// UPLOAD FOTO PROFIL
// ============================================

document.getElementById('fotoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    
    if (!file) return;
    
    // Validasi ukuran (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        showToast('Ukuran file maksimal 2MB', 'error');
        this.value = '';
        return;
    }
    
    // Validasi tipe
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showToast('Format file harus JPG, PNG, atau WEBP', 'error');
        this.value = '';
        return;
    }
    
    // Preview foto
    const reader = new FileReader();
    reader.onload = function(e) {
        const avatarContainer = document.getElementById('avatarContainer');
        avatarContainer.innerHTML = `<img src="${e.target.result}" alt="Preview" id="avatarImage">`;
    };
    reader.readAsDataURL(file);
    
    // Submit via fetch
    const formData = new FormData();
    formData.append('action', 'upload_foto');
    formData.append('foto', file);
    
    showToast('Mengupload foto...', 'info');
    
    fetch('/simak_app/public/profil_update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Foto profil berhasil diupdate!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Gagal upload foto', 'error');
        }
    })
    .catch(() => {
        showToast('Terjadi kesalahan saat mengupload foto', 'error');
    });
});

// ============================================
// VALIDASI FORM DATA AKUN & PASSWORD
// ============================================

document.getElementById('formAccount')?.addEventListener('submit', function(e) {
    const username = this.username.value.trim();
    
    if (!username) {
        e.preventDefault();
        showToast('Username tidak boleh kosong!', 'error');
        return false;
    }
});

document.getElementById('formPassword')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        showToast('Password baru dan konfirmasi tidak cocok!', 'error');
        return false;
    }
    
    if (newPass.length < 6) {
        e.preventDefault();
        showToast('Password minimal 6 karakter!', 'error');
        return false;
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>