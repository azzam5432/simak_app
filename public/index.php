<?php
require_once __DIR__ . '/../config/session.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    'server_error' => 'Terjadi kesalahan pada server. Silakan coba lagi atau hubungi administrator.',
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
    <link rel="stylesheet" href="/simak_app/public/assets/css/base.css?v=6">
    <link rel="stylesheet" href="/simak_app/public/assets/css/login.css?v=3">
    <link rel="icon" href="/simak_app/public/assets/favicon.ico" type="image/x-icon">
</head>
<body class="login-page">
    <div class="login-shell">
        <aside class="login-brand">
            <div>
                <img src="/simak_app/public/assets/img/poltek.png" alt="Politeknik Mitra Industri" class="login-brand-logo">
                <div class="login-wordmark">SIMAK</div>
                <p>Sistem Informasi &amp; Manajemen Akademik</p>
            </div>
            <div class="login-brand-foot">
                <span>Politeknik Mitra Industri</span>
                Portal akademik terpadu
            </div>
        </aside>

        <main class="login-panel">
            <div class="login-form-wrap">
                <h1>Masuk ke akun</h1>
                <p class="login-sub">Gunakan username dan password yang diberikan administrator.</p>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="fas fa-circle-exclamation me-2"></i>
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

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-arrow-right-to-bracket"></i> Masuk
                    </button>
                </form>

                <p class="login-help">Mengalami kendala masuk? Hubungi bagian administrasi.</p>

                <div class="login-footer">
                    &copy; <?= date('Y') ?> SIMAK &middot; Politeknik Mitra Industri
                </div>
            </div>
        </main>
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