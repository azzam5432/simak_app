<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function checkAccess($allowedRoles = []) {
    if (!isLoggedIn()) {
        header("Location: /simak_app/public/index.php?error=session_expired");
        exit();
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        
        $role = $_SESSION['role'];
        $dashboard = match($role) {
            'admin' => '/simak_app/public/admin/dashboard.php',
            'dosen' => '/simak_app/public/dosen/dashboard.php',
            'mahasiswa' => '/simak_app/public/mahasiswa/dashboard.php',
            default => '/simak_app/public/index.php'
        };
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Akses Ditolak</title>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
            <style>
                body { font-family: Arial; text-align: center; padding: 50px; background: #f8f9fa; }
                .container { max-width: 500px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #e74c3c; }
                /* role-badge dihilangkan: teks bold polos */
                .role-badge { display: inline; padding: 0; border-radius: 0; color: inherit; font-weight: bold; background: none; }
                .role-admin { color: #e74c3c; }
                .role-dosen { color: #3498db; }
                .role-mahasiswa { color: #27ae60; }
                .btn { display: inline-block; padding: 10px 25px; background: #3498db; color: #fff; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                .btn:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1><i class='fas fa-ban'></i> Akses Ditolak</h1>
                <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
                <p>
                    Role Anda: 
                    <span class='role-badge role-" . htmlspecialchars($role) . "'>
                        " . ucfirst(htmlspecialchars($role)) . "
                    </span>
                </p>
                <p><a href='" . $dashboard . "' class='btn'><i class='fas fa-arrow-left'></i> Kembali ke Dashboard</a></p>
            </div>
        </body>
        </html>";
        exit();
    }
}

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserName() {
    return $_SESSION['nama'] ?? 'Pengguna';
}

function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: " . $url);
    exit();
}

/**
 * Generate / ambil CSRF token dari session
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token dari form POST
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) 
        && !empty($token) 
        && hash_equals($_SESSION['csrf_token'], $token);
}

function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    
    if ($message) {
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

function redirectToDashboard() {
    if (!isLoggedIn()) {
        header("Location: /simak_app/public/index.php");
        exit();
    }
    
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
?>