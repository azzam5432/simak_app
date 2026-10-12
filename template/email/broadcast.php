<?php
// ============================================
// template/email/broadcast.php
// Template email notifikasi broadcast pengumuman
// ============================================

require_once __DIR__ . '/base_template.php';

function renderBroadcastEmail($data) {
    $nama = htmlspecialchars($data['nama'] ?? 'Pengguna');
    $judul = htmlspecialchars($data['judul'] ?? '');
    $pesan = nl2br(htmlspecialchars($data['pesan'] ?? ''));
    $sender = htmlspecialchars($data['sender_name'] ?? 'Admin Akademik');
    $sender_role = $data['sender_role'] ?? 'admin';
    
    $label_role = ($sender_role === 'admin') ? 'Admin Akademik' : 'Dosen';
    $warna_priority = '#3498db';
    $label_priority = '';
    
    if (($data['priority'] ?? 'medium') === 'high') {
        $warna_priority = '#e74c3c';
        $label_priority = "<div style='background: #fdedec; color: #c0392b; border-radius: 6px; padding: 8px 14px; margin-bottom: 15px; font-weight: bold; font-size: 13px;'>
            &#9888; PENTING — Mohon dibaca segera
        </div>";
    } elseif (($data['priority'] ?? 'medium') === 'low') {
        $warna_priority = '#95a5a6';
    }
    
    $content = "
        <p>Halo <strong>{$nama}</strong>,</p>
        {$label_priority}
        <p>Ada pengumuman baru dari <strong>{$label_role}</strong> untuk Anda:</p>
        
        <div style='background: #f8f9fa; border-left: 4px solid {$warna_priority}; border-radius: 8px; padding: 20px; margin: 20px 0;'>
            <h3 style='margin: 0 0 12px; color: #2c3e50; font-size: 16px;'>
                {$judul}
            </h3>
            <div style='color: #555; font-size: 14px; line-height: 1.7;'>
                {$pesan}
            </div>
        </div>
        
        <p style='font-size: 12px; color: #7f8c8d; margin-top: 20px;'>
            Dikirim oleh: {$sender} &middot; " . date('d-m-Y H:i') . "
        </p>
    ";
    
    return renderEmailTemplate(
        'Pengumuman: ' . $judul,
        $content
    );
}
?>
