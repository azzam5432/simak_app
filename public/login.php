<?php
// ============================================
// public/login.php
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isLoggedIn()) {
    redirectToDashboard();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        header("Location: /simak_app/public/index.php?error=login_failed");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['login_time'] = time();
            
            // Debug: log session
            error_log("Login berhasil: " . $user['username'] . " | Role: " . $user['role']);
            
            // Redirect
            redirectToDashboard();
            exit();
        } else {
            error_log("Login gagal untuk: " . $username);
            header("Location: /simak_app/public/index.php?error=login_failed");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        // Error ini masalah sistem (koneksi/query), bukan kredensial salah.
        // Jangan tampilkan "username/password salah" untuk membingungkan user.
        header("Location: /simak_app/public/index.php?error=server_error");
        exit();
    }
} else {
    header("Location: /simak_app/public/index.php");
    exit();
}
?>