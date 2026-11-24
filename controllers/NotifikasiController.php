<?php
// ============================================
// controllers/NotifikasiController.php
// Logika notifikasi dalam aplikasi (lonceng di header)
// ============================================

class NotifikasiController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Simpan satu notifikasi untuk user tertentu.
     * Kegagalan menyimpan notifikasi tidak boleh menggagalkan aksi utama.
     */
    public function kirim($user_id, $type, $judul, $pesan, $link = null) {
        if (empty($user_id)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, judul, pesan, link)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $type, $judul, $pesan, $link]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Notifikasi untuk dosen ketika mahasiswa mengumpulkan tugas.
     */
    public static function tugasDikumpulkan($pdo, $task_id, $mahasiswa_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT t.judul, t.dosen_id, c.kode_mk, c.nama_mk,
                       d.user_id AS dosen_user_id,
                       u.nama AS mahasiswa_nama, m.nim
                FROM tasks t
                JOIN courses c ON t.course_id = c.id
                JOIN dosen d ON t.dosen_id = d.id
                JOIN mahasiswa m ON m.id = ?
                JOIN users u ON m.user_id = u.id
                WHERE t.id = ?
            ");
            $stmt->execute([$mahasiswa_id, $task_id]);
            $data = $stmt->fetch();

            if (!$data || empty($data['dosen_user_id'])) {
                return false;
            }

            $notif = new self($pdo);
            return $notif->kirim(
                $data['dosen_user_id'],
                'tugas_submit',
                'Pengumpulan tugas baru',
                $data['mahasiswa_nama'] . ' (' . $data['nim'] . ') mengumpulkan tugas "'
                    . $data['judul'] . '" untuk ' . $data['kode_mk'] . ' - ' . $data['nama_mk'] . '.',
                '/simak_app/public/dosen/tugas.php?detail=' . intval($task_id)
            );
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Notifikasi untuk mahasiswa ketika dosen memvalidasi hasil tugasnya.
     */
    public static function tugasDivalidasi($pdo, $submission_id, $dosen_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT t.id AS task_id, t.judul, c.kode_mk, c.nama_mk,
                       m.user_id AS mahasiswa_user_id
                FROM task_submissions ts
                JOIN tasks t ON ts.task_id = t.id
                JOIN courses c ON t.course_id = c.id
                JOIN mahasiswa m ON ts.mahasiswa_id = m.id
                WHERE ts.id = ? AND t.dosen_id = ?
            ");
            $stmt->execute([$submission_id, $dosen_id]);
            $data = $stmt->fetch();

            if (!$data || empty($data['mahasiswa_user_id'])) {
                return false;
            }

            $notif = new self($pdo);
            return $notif->kirim(
                $data['mahasiswa_user_id'],
                'tugas_validated',
                'Tugas telah divalidasi',
                'Tugas "' . $data['judul'] . '" untuk ' . $data['kode_mk'] . ' - '
                    . $data['nama_mk'] . ' sudah diverifikasi oleh dosen.',
                '/simak_app/public/mahasiswa/tugas.php'
            );
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getUntukUser($user_id, $limit = 10) {
        $limit = max(1, intval($limit));

        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT " . $limit . "
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function countBelumDibaca($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function getById($id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notifications WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$id, $user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function tandaiDibaca($id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications SET is_read = 1
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$id, $user_id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function tandaiSemuaDibaca($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications SET is_read = 1
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** Ikon Font Awesome berdasarkan jenis notifikasi. */
    public static function ikon($type) {
        $icons = [
            'tugas_submit'    => 'fa-upload',
            'tugas_validated' => 'fa-check-double',
        ];
        return $icons[$type] ?? 'fa-bell';
    }

    /** Waktu relatif singkat untuk daftar notifikasi. */
    public static function waktuRelatif($datetime) {
        if (empty($datetime)) {
            return '';
        }

        $waktu = strtotime($datetime);
        $selisih = time() - $waktu;

        if ($selisih < 60) {
            return 'Baru saja';
        }
        if ($selisih < 3600) {
            return floor($selisih / 60) . ' menit lalu';
        }
        if ($selisih < 86400) {
            return floor($selisih / 3600) . ' jam lalu';
        }
        if ($selisih < 172800) {
            return 'Kemarin';
        }

        return date('d-m-Y H:i', $waktu);
    }
}
