<?php
// ============================================
// controllers/ProfileController.php
// Logika untuk fitur profil user
// ============================================

class ProfileController {
    private $pdo;
    private $user_id;
    private $upload_dir;
    private $upload_url;
    
    public function __construct($pdo, $user_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? 0);
        $this->upload_dir = __DIR__ . '/../public/uploads/avatars/';
        $this->upload_url = '/simak_app/public/uploads/avatars/';
        
        // Buat folder jika belum ada
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    // ============================================
    // GET PROFIL
    // ============================================
    
    /**
     * Get data lengkap user berdasarkan role
     */
    public function getProfile() {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                u.nama,
                u.email,
                u.role,
                u.foto_profil,
                u.created_at,
                u.updated_at
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$this->user_id]);
        $user = $stmt->fetch();
        
        if (!$user) return null;
        
        // Ambil data tambahan sesuai role
        if ($user['role'] === 'mahasiswa') {
            $stmt = $this->pdo->prepare("
                SELECT 
                    m.id, m.nim, m.tahun_ajaran, m.tingkat, m.semester,
                    f.nama as fakultas_nama, f.kode as fakultas_kode,
                    j.nama as jurusan_nama, j.kode as jurusan_kode, j.jenjang
                FROM mahasiswa m
                LEFT JOIN fakultas f ON m.fakultas_id = f.id
                LEFT JOIN jurusan j ON m.jurusan_id = j.id
                WHERE m.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $user['detail'] = $stmt->fetch();
            
        } elseif ($user['role'] === 'dosen') {
            $stmt = $this->pdo->prepare("
                SELECT 
                    d.id, d.nid,
                    f.nama as fakultas_nama, f.kode as fakultas_kode,
                    j.nama as jurusan_nama, j.kode as jurusan_kode, j.jenjang
                FROM dosen d
                LEFT JOIN fakultas f ON d.fakultas_id = f.id
                LEFT JOIN jurusan j ON d.jurusan_id = j.id
                WHERE d.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $user['detail'] = $stmt->fetch();
        }
        
        return $user;
    }
    
    /**
     * Get URL foto profil
     */
    public function getFotoUrl($user) {
        if (!empty($user['foto_profil'])) {
            $file_path = $this->upload_dir . $user['foto_profil'];
            if (file_exists($file_path)) {
                return $this->upload_url . $user['foto_profil'] . '?t=' . time();
            }
        }
        return null;
    }
    
    // ============================================
    // UPDATE FOTO PROFIL
    // ============================================
    
    /**
     * Upload foto profil
     * 
     * @param array $file $_FILES['foto']
     * @return array Hasil upload
     */
    public function uploadFoto($file) {
        try {
            // Validasi file
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Tidak ada file yang diupload'];
            }
            
            // Cek ukuran (max 2MB)
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($file['size'] > $max_size) {
                return ['success' => false, 'message' => 'Ukuran file maksimal 2MB'];
            }
            
            // Cek tipe file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowed_types)) {
                return ['success' => false, 'message' => 'Format file harus JPG, PNG, atau WEBP'];
            }
            
            // Generate nama file unik
            $ext = $this->getExtension($mime);
            $filename = 'avatar_' . $this->user_id . '_' . time() . '.' . $ext;
            $target_path = $this->upload_dir . $filename;
            
            // Hapus foto lama jika ada
            $this->deleteOldFoto();
            
            // Upload file
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                return ['success' => false, 'message' => 'Gagal mengupload file'];
            }
            
            // Resize & compress gambar (opsional, kalau GD extension ada)
            if (extension_loaded('gd')) {
                $this->resizeImage($target_path, 400, 400);
            }
            
            // Update database
            $stmt = $this->pdo->prepare("
                UPDATE users SET foto_profil = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$filename, $this->user_id]);
            
            // Update session
            $_SESSION['foto_profil'] = $filename;
            
            return [
                'success' => true,
                'message' => 'Foto profil berhasil diupdate',
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Hapus foto profil
     */
    public function deleteFoto() {
        try {
            $this->deleteOldFoto();
            
            $stmt = $this->pdo->prepare("
                UPDATE users SET foto_profil = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$this->user_id]);
            
            unset($_SESSION['foto_profil']);
            
            return ['success' => true, 'message' => 'Foto profil berhasil dihapus'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Helper: Hapus foto lama dari folder
     */
    private function deleteOldFoto() {
        $stmt = $this->pdo->prepare("SELECT foto_profil FROM users WHERE id = ?");
        $stmt->execute([$this->user_id]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['foto_profil'])) {
            $old_file = $this->upload_dir . $user['foto_profil'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
    }
    
    /**
     * Helper: Get extension from mime type
     */
    private function getExtension($mime) {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        return $map[$mime] ?? 'jpg';
    }
    
    /**
     * Helper: Resize image (butuh GD extension)
     */
    private function resizeImage($path, $max_width, $max_height) {
        try {
            $info = getimagesize($path);
            if (!$info) return false;
            
            list($width, $height, $type) = $info;
            
            // Hitung dimensi baru
            $ratio = min($max_width / $width, $max_height / $height);
            if ($ratio >= 1) return true; // Tidak perlu resize
            
            $new_width = round($width * $ratio);
            $new_height = round($height * $ratio);
            
            // Buat image source
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $src = imagecreatefromjpeg($path);
                    break;
                case IMAGETYPE_PNG:
                    $src = imagecreatefrompng($path);
                    break;
                case IMAGETYPE_WEBP:
                    $src = imagecreatefromwebp($path);
                    break;
                default:
                    return false;
            }
            
            if (!$src) return false;
            
            // Buat image baru
            $dst = imagecreatetruecolor($new_width, $new_height);
            
            // Handle transparency untuk PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
            }
            
            // Resize
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Simpan
            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($dst, $path, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($dst, $path, 8);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($dst, $path, 85);
                    break;
            }
            
            imagedestroy($src);
            imagedestroy($dst);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // ============================================
    // UPDATE AKUN (username / nama / email)
    // ============================================
    
    /**
     * Update data akun sesuai hak role:
     * - Admin      : username + nama + email (lewat profil.php sendiri)
     * - Dosen      : hanya username
     * - Mahasiswa  : hanya username
     * 
     * @param array $data $_POST
     * @return array Hasil
     */
    public function updateAccount($data) {
        try {
            $role = $_SESSION['role'] ?? '';
            
            $username = trim($data['username'] ?? '');
            if ($username === '') {
                return ['success' => false, 'message' => 'Username tidak boleh kosong'];
            }
            
            // Get user saat ini
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$this->user_id]);
            $user = $stmt->fetch();
            if (!$user) {
                return ['success' => false, 'message' => 'User tidak ditemukan'];
            }
            
            // Pastikan username unik
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $this->user_id]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username sudah digunakan oleh akun lain'];
            }
            
            $fields = ['username' => $username];
            
            if ($role === 'admin') {
                $nama = trim($data['nama'] ?? '');
                $email = trim($data['email'] ?? '');
                
                if ($nama === '') {
                    return ['success' => false, 'message' => 'Nama tidak boleh kosong'];
                }
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'message' => 'Format email tidak valid'];
                }
                
                // Email juga harus unik
                if (!empty($email)) {
                    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $this->user_id]);
                    if ($stmt->fetch()) {
                        return ['success' => false, 'message' => 'Email sudah digunakan oleh akun lain'];
                    }
                }
                
                $fields['nama'] = $nama;
                $fields['email'] = $email;
            }
            
            $setSql = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
            $fields['user_id'] = $this->user_id;
            
            $stmt = $this->pdo->prepare("UPDATE users SET $setSql, updated_at = NOW() WHERE id = :user_id");
            $stmt->execute($fields);
            
            // Sinkronkan session
            $_SESSION['username'] = $username;
            if (isset($fields['nama'])) {
                $_SESSION['nama'] = $fields['nama'];
            }
            
            $msg = ($role === 'admin') ? 'Data akun berhasil diperbarui' : 'Username berhasil diperbarui';
            return ['success' => true, 'message' => $msg];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // ============================================
    // GANTI PASSWORD
    // ============================================
    
    /**
     * Ganti password
     * 
     * @param string $current_password Password lama
     * @param string $new_password Password baru
     * @param string $confirm_password Konfirmasi password baru
     * @return array Hasil
     */
    public function changePassword($current_password, $new_password, $confirm_password) {
        try {
            // Validasi input
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                return ['success' => false, 'message' => 'Semua field harus diisi'];
            }
            
            // Cek konfirmasi
            if ($new_password !== $confirm_password) {
                return ['success' => false, 'message' => 'Password baru dan konfirmasi tidak cocok'];
            }
            
            // Cek panjang minimal
            if (strlen($new_password) < 6) {
                return ['success' => false, 'message' => 'Password minimal 6 karakter'];
            }
            
            // Cek password baru tidak sama dengan lama
            if ($current_password === $new_password) {
                return ['success' => false, 'message' => 'Password baru harus berbeda dengan password lama'];
            }
            
            // Get password hash dari database
            $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$this->user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User tidak ditemukan'];
            }
            
            // Verifikasi password lama
            if (!password_verify($current_password, $user['password'])) {
                return ['success' => false, 'message' => 'Password lama salah'];
            }
            
            // Update password baru
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                UPDATE users SET password = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hashed, $this->user_id]);
            
            return ['success' => true, 'message' => 'Password berhasil diubah'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>