<?php
require_once __DIR__ . '/../config/session.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    $redirect = match($role) {
        'admin' => '/simak_app/public/admin/dashboard.php',
        'dosen' => '/simak_app/public/dosen/dashboard.php',
        'mahasiswa' => '/simak_app/public/mahasiswa/dashboard.php',
        default => '/simak_app/public/logout.php'
    };
    header("Location: " . $redirect);
    exit();
}

$error = $_GET['error'] ?? '';
$errors = [
    'login_failed' => 'Username atau password salah!',
    'session_expired' => 'Sesi Anda telah berakhir. Silakan login kembali.',
    'access_denied' => 'Akses ditolak. Silakan login dengan akun yang valid.'
];
$errorMessage = $errors[$error] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMAK - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/simak_app/public/assets/css/base.css?v=1">
    <link rel="stylesheet" href="/simak_app/public/assets/css/login.css?v=1">
    <link rel="icon" href="/simak_app/public/assets/favicon.ico" type="image/x-icon">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1><span>SIM</span>AK</h1>
                <p>Sistem Informasi &amp; Manajemen Akademik Kampus</p>
            </div>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="fas fa-times-circle me-2"></i>
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>
            
            <form action="/simak_app/public/login.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
                        <button type="button" class="password-toggle toggle-password" data-target="password" tabindex="-1" aria-label="Lihat / sembunyikan password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2 mt-3 mb-1">
                    <i class="fas fa-sign-in-alt"></i> Login Sekarang
                </button>
            </form>
            
            <div class="login-roles">
                <span class="login-role-chip"><i class="fas fa-user-shield"></i> Admin</span>
                <span class="login-role-chip"><i class="fas fa-chalkboard-teacher"></i> Dosen</span>
                <span class="login-role-chip"><i class="fas fa-user-graduate"></i> Mahasiswa</span>
            </div>
            
            <div class="login-footer">
                <i class="fas fa-copyright"></i> 2026 SIMAK - Politeknik Mitra Industri
            </div>
        </div>
    </div>

    <script>
    // Toggle lihat/sembunyikan password (login page tidak memuat app.js)
    document.querySelectorAll('.toggle-password').forEach(function(el) {
        el.addEventListener('click', function() {
            const input = document.getElementById(this.dataset.target);
            if (!input) return;
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            this.innerHTML = type === 'password'
                ? '<i class="fas fa-eye"></i>'
                : '<i class="fas fa-eye-slash"></i>';
        });
    });
    </script>
</body>
</html>