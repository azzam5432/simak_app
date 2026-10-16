<?php
// ============================================
// public/profil_update.php
// Handler Update Profil
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../controllers/ProfileController.php';

header('Content-Type: application/json');

// Cek login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Anda belum login']);
    exit();
}

// Hak akses per aksi:
// - change_password, update_account : semua role (hanya data akun sendiri)
// - upload_foto / delete_foto       : hanya admin
$own_account_actions = ['change_password', 'update_account'];
$action = $_POST['action'] ?? '';

if (in_array($action, $own_account_actions, true)) {
    // Semua role boleh mengubah akunnya sendiri, userId selalu dari session
} elseif (in_array($action, ['upload_foto', 'delete_foto'], true)) {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Foto hanya dapat diubah oleh Admin']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
    exit();
}

$profileController = new ProfileController($pdo, $_SESSION['user_id']);

switch ($action) {
    // ============================================
    // UPLOAD FOTO
    // ============================================
    case 'upload_foto':
        if (!isset($_FILES['foto'])) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload']);
            exit();
        }
        
        $result = $profileController->uploadFoto($_FILES['foto']);
        echo json_encode($result);
        break;
    
    // ============================================
    // DELETE FOTO
    // ============================================
    case 'delete_foto':
        $result = $profileController->deleteFoto();
        
        if ($result['success']) {
            redirectWithMessage('/simak_app/public/profil.php', $result['message'], 'success');
        } else {
            redirectWithMessage('/simak_app/public/profil.php', $result['message'], 'error');
        }
        break;
    
    // ============================================
    // CHANGE PASSWORD
    // ============================================
    case 'change_password':
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        $result = $profileController->changePassword($current, $new, $confirm);
        
        if ($result['success']) {
            redirectWithMessage('/simak_app/public/profil.php', $result['message'], 'success');
        } else {
            redirectWithMessage('/simak_app/public/profil.php', $result['message'], 'error');
        }
        break;
    
    // ============================================
    // UPDATE AKUN (username / nama / email)
    // ============================================
    case 'update_account':
        $result = $profileController->updateAccount($_POST);
        
        if ($result['success']) {
            redirectWithMessage('/simak_app/public/profil.php', $result['message'], 'success');
        } else {
            redirectWithMessage('/simak_app/public/profil.php', $result['message'], 'error');
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
        break;
}
?>