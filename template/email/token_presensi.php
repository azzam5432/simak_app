<?php
// ============================================
// template/email/token_presensi.php
// Template email token/kode presensi
// ============================================

require_once __DIR__ . '/base_template.php';

function renderTokenPresensi($data) {
    $nama = htmlspecialchars($data['nama'] ?? 'Mahasiswa');
    $kode_mk = htmlspecialchars($data['kode_mk'] ?? '');
    $nama_mk = htmlspecialchars($data['nama_mk'] ?? '');
    $kode = htmlspecialchars($data['kode'] ?? '');
    $tanggal = htmlspecialchars($data['tanggal'] ?? date('d-m-Y'));
    $dosen = htmlspecialchars($data['dosen_nama'] ?? '');
    
    // Pecah kode per digit agar mudah dibaca
    $kode_spaced = implode(' ', str_split($kode));
    
    $content = "
        <p>Halo <strong>{$nama}</strong>,</p>
        <p>Dosen <strong>{$dosen}</strong> telah membuka sesi presensi untuk mata kuliah berikut:</p>
        
        <div style='background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;'>
            <table width='100%' cellpadding='0' cellspacing='0'>
                <tr>
                    <td style='padding: 8px 0;'><strong>Mata Kuliah:</strong></td>
                    <td style='padding: 8px 0; text-align: right;'>{$kode_mk} - {$nama_mk}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0;'><strong>Tanggal:</strong></td>
                    <td style='padding: 8px 0; text-align: right;'>{$tanggal}</td>
                </tr>
            </table>
        </div>
        
        <p style='text-align: center; margin: 25px 0 5px; font-size: 14px;'>
            Gunakan kode presensi berikut:
        </p>
        <div style='text-align: center; margin: 15px 0 25px;'>
            <div style='display: inline-block; background: #2c3e50; color: #ffffff; font-size: 32px; font-weight: bold; letter-spacing: 12px; padding: 18px 30px 18px 42px; border-radius: 10px;'>
                {$kode_spaced}
            </div>
        </div>
        
        <p style='font-size: 13px; color: #7f8c8d; text-align: center;'>
            Buka menu <strong>Presensi</strong> di dashboard mahasiswa Anda,<br>
            masukkan kode di atas sebelum sesi ditutup (berlaku 30 menit sejak dibuka).
        </p>
    ";
    
    return renderEmailTemplate(
        'Token Presensi ' . $kode_mk,
        $content
    );
}
?>
