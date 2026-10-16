<?php
// ============================================
// includes/header.php
// Template Header & Navigation
// ============================================

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header("Location: /simak_app/public/index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
$initial = strtoupper(substr($nama, 0, 1));

// CSS per halaman: base.css selalu dimuat, file tambahan sesuai halamannya
// supaya perubahan style satu halaman tidak menabrak halaman lain
$css_v = 1;
$page_css_map = [
    'dashboard.php'       => ['dashboard.css'],
    'profil.php'          => ['profil.css'],
    'jadwal.php'          => ['jadwal.css'],
    'irs.php'             => ['irs.css'],
    'validasi_irs.php'    => ['irs.css'],
    'verifikasi_nilai.php'=> ['irs.css'],
    'laporan.php'         => ['laporan.css'],
    'report_krs.php'      => ['report.css'],
    'report_nilai.php'    => ['report.css'],
    'khs.php'             => ['report.css'],
    'laporan_nilai.php'   => ['report.css'],
];
$page_extra_css = $page_css_map[$current_page] ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMAK - <?= ucfirst($role) ?> Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/simak_app/public/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/simak_app/public/assets/css/base.css?v=<?= $css_v ?>">
    <?php foreach ($page_extra_css as $extra_css): ?>
    <link rel="stylesheet" href="/simak_app/public/assets/css/<?= $extra_css ?>?v=<?= $css_v ?>">
    <?php endforeach; ?>
    <link rel="icon" href="/simak_app/public/assets/favicon.ico" type="image/x-icon">
</head>
<body>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-container">
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-graduation-cap brand-icon"></i>
            <h2>SIMAK</h2>
            <small>Politeknik Mitra Industri</small>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-label">Navigasi Utama</li>
            
            <?php if ($role === 'admin'): ?>
                <li>
                    <a href="/simak_app/public/admin/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                
                <!-- DROPDOWN: MASTER DATA -->
                <?php 
                $master_pages = ['mahasiswa.php', 'dosen.php', 'matakuliah.php', 'jadwal.php', 'fakultas.php', 'jurusan.php'];
                $monitoring_pages = ['validasi_irs.php', 'verifikasi_nilai.php'];
                $laporan_pages = ['laporan.php', 'cetak_pdf.php'];
                ?>
                <li class="has-submenu <?= in_array($current_page, $master_pages) ? 'open active' : '' ?>">
                    <a href="javascript:void(0)" class="submenu-toggle">
                        <i class="fas fa-database"></i> Master Data
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="/simak_app/public/admin/mahasiswa.php" class="<?= $current_page === 'mahasiswa.php' ? 'active' : '' ?>">
                                <i class="fas fa-user-graduate"></i> Mahasiswa
                            </a>
                        </li>
                        <li>
                            <a href="/simak_app/public/admin/dosen.php" class="<?= $current_page === 'dosen.php' ? 'active' : '' ?>">
                                <i class="fas fa-chalkboard-teacher"></i> Dosen
                            </a>
                        </li>
                        <li>
                            <a href="/simak_app/public/admin/matakuliah.php" class="<?= $current_page === 'matakuliah.php' ? 'active' : '' ?>">
                                <i class="fas fa-book-open"></i> Mata Kuliah
                            </a>
                        </li>
                        <li>
                            <a href="/simak_app/public/admin/jadwal.php" class="<?= $current_page === 'jadwal.php' ? 'active' : '' ?>">
                                <i class="fas fa-calendar-alt"></i> Jadwal
                            </a>
                        </li>
                        <li>
                            <a href="/simak_app/public/admin/fakultas.php" class="<?= $current_page === 'fakultas.php' ? 'active' : '' ?>">
                                <i class="fas fa-university"></i> Fakultas
                            </a>
                        </li>
                        <li>
                            <a href="/simak_app/public/admin/jurusan.php" class="<?= $current_page === 'jurusan.php' ? 'active' : '' ?>">
                                <i class="fas fa-book"></i> Jurusan
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- DROPDOWN: PERSETUJUAN & VERIFIKASI -->
                <li class="has-submenu <?= in_array($current_page, $monitoring_pages) ? 'open active' : '' ?>">
                    <a href="javascript:void(0)" class="submenu-toggle">
                        <i class="fas fa-check-double"></i> Persetujuan
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="/simak_app/public/admin/validasi_irs.php" class="<?= $current_page === 'validasi_irs.php' ? 'active' : '' ?>">
                                <i class="fas fa-file-signature"></i> Validasi IRS
                            </a>
                        </li>
                        <li>
                            <a href="/simak_app/public/admin/verifikasi_nilai.php" class="<?= $current_page === 'verifikasi_nilai.php' ? 'active' : '' ?>">
                                <i class="fas fa-clipboard-check"></i> Verifikasi Nilai 0
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- DROPDOWN: LAPORAN -->
                <li class="has-submenu <?= in_array($current_page, $laporan_pages) ? 'open active' : '' ?>">
                    <a href="javascript:void(0)" class="submenu-toggle">
                        <i class="fas fa-file-alt"></i> Laporan
                        <i class="fas fa-chevron-right arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="/simak_app/public/admin/laporan.php" class="<?= strpos($current_page, 'laporan') !== false ? 'active' : '' ?>">
                                <i class="fas fa-table"></i> Laporan Akademik
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li>
                    <a href="/simak_app/public/admin/broadcast.php" class="<?= $current_page === 'broadcast.php' ? 'active' : '' ?>">
                        <i class="fas fa-bullhorn"></i> Broadcast
                    </a>
                </li>
                
            <?php elseif ($role === 'dosen'): ?>
                <li>
                    <a href="/simak_app/public/dosen/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/dosen/jadwal.php" class="<?= $current_page === 'jadwal.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-alt"></i> Jadwal Mengajar
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/dosen/presensi.php" class="<?= $current_page === 'presensi.php' ? 'active' : '' ?>">
                        <i class="fas fa-clipboard-list"></i> Presensi
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/dosen/nilai.php" class="<?= $current_page === 'nilai.php' ? 'active' : '' ?>">
                        <i class="fas fa-edit"></i> Input Nilai
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/dosen/laporan_nilai.php" class="<?= $current_page === 'laporan_nilai.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i> Laporan Nilai
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/dosen/tugas.php" class="<?= $current_page === 'tugas.php' ? 'active' : '' ?>">
                        <i class="fas fa-tasks"></i> Tugas
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/dosen/broadcast.php" class="<?= $current_page === 'broadcast.php' ? 'active' : '' ?>">
                        <i class="fas fa-bullhorn"></i> Broadcast Kelas
                    </a>
                </li>
                
            <?php elseif ($role === 'mahasiswa'): ?>
                <li>
                    <a href="/simak_app/public/mahasiswa/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/jadwal.php" class="<?= $current_page === 'jadwal.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-alt"></i> Jadwal Kuliah
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/tugas.php" class="<?= $current_page === 'tugas.php' ? 'active' : '' ?>">
                        <i class="fas fa-tasks"></i> Tugas
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/irs.php" class="<?= $current_page === 'irs.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-signature"></i> IRS
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/khs.php" class="<?= $current_page === 'khs.php' ? 'active' : '' ?>">
                        <i class="fas fa-star"></i> KHS & IPK
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/report_krs.php" class="<?= $current_page === 'report_krs.php' ? 'active' : '' ?>">
                        <i class="fas fa-id-card"></i> Report KRS
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/report_nilai.php" class="<?= $current_page === 'report_nilai.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> Report Nilai
                    </a>
                </li>
                <li>
                    <a href="/simak_app/public/mahasiswa/presensi.php" class="<?= $current_page === 'presensi.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-circle"></i> Presensi
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="menu-label">Akun</li>
            <li>
                <a href="/simak_app/public/profil.php" class="<?= $current_page === 'profil.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> Profil Saya
                </a>
            </li>
            <li>
                <a href="/simak_app/public/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1><?= $page_title ?? 'Dashboard' ?></h1>
                <span class="breadcrumb">/ <?= ucfirst($role) ?></span>
            </div>
            <div class="user-info">
                <span class="user-badge"><i class="fas fa-user-circle"></i> <?= ucfirst($role) ?></span>
                <span class="user-name"><?= htmlspecialchars($nama) ?></span>
                <?php 
                // Cek foto profil
                $foto_profil = $_SESSION['foto_profil'] ?? null;
                $foto_path = __DIR__ . '/../public/uploads/avatars/' . $foto_profil;
                ?>
                <div class="user-avatar">
                    <?php if ($foto_profil && file_exists($foto_path)): ?>
                        <img src="/simak_app/public/uploads/avatars/<?= htmlspecialchars($foto_profil) ?>?t=<?= time() ?>" 
                            alt="Avatar" 
                            style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?= $initial ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php 
        $flash = getFlashMessage(); 
        if ($flash): 
        $flash_type = $flash['type'] === 'error' ? 'danger' : $flash['type'];
        ?>
            <div class="alert alert-<?= $flash_type ?>">
                <span class="alert-icon">
                    <?php 
                    if ($flash['type'] === 'success') {
                        echo '<i class="fas fa-check-circle"></i>';
                    } elseif ($flash['type'] === 'error') {
                        echo '<i class="fas fa-times-circle"></i>';
                    } elseif ($flash['type'] === 'warning') {
                        echo '<i class="fas fa-exclamation-triangle"></i>';
                    } else {
                        echo '<i class="fas fa-info-circle"></i>';
                    }
                    ?>
                </span>
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>