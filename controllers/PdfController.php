<?php
// ============================================
// controllers/PdfController.php
// Generate PDF untuk semua laporan
// ============================================

require_once __DIR__ . '/../libraries/Dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Setup Dompdf options
     */
    private function setupDompdf() {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('chroot', __DIR__ . '/..');
        
        return new Dompdf($options);
    }
    
    /**
     * Helper: tentukan orientasi kertas. Tabel/field yang lebar otomatis landscape.
     * $is_wide = true -> landscape, false -> portrait.
     */
    private function paperOrientation($is_wide) {
        return $is_wide ? 'landscape' : 'portrait';
    }
    
    /**
     * Generate header PDF (kop surat)
     * Logo perusahaan: public/assets/img/logo_poltek.jpg — dibaca dari disk
     * dan di-embed sebagai data URI agar dompdf tidak perlu remote access.
     */
    private function getHeader($title) {
        $tanggal = date('d F Y');
        $logo_path = __DIR__ . '/../public/assets/img/logo_poltek.jpg';
        if (file_exists($logo_path)) {
            $logo_data = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
        } else {
            // fallback ke placeholder lambang "S" jika file logo tidak ada
            $logo_data = 'data:image/svg+xml;base64,' . base64_encode('
                <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#3498db"/>
                    <text x="50" y="65" font-size="40" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold">S</text>
                </svg>
            ');
        }
        
        return "
            <div style='border-bottom: 3px double #2c3e50; padding-bottom: 15px; margin-bottom: 20px;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td width='95' style='vertical-align: middle;'>
                            <img src='{$logo_data}' width='80' height='48'>
                        </td>
                        <td style='text-align: center; vertical-align: middle;'>
                            <h1 style='margin: 0; font-size: 20px; color: #2c3e50; letter-spacing: 1px;'>
                                POLITEKNIK MITRA INDUSTRI
                            </h1>
                            <p style='margin: 3px 0; font-size: 12px; color: #555;'>
                                Sistem Informasi & Manajemen Akademik Kampus
                            </p>
                            <p style='margin: 3px 0; font-size: 11px; color: #7f8c8d;'>
                                Jl. Raya Pendidikan No. 1, Kota Pendidikan | Telp: (021) 1234567
                            </p>
                        </td>
                        <td width='95' style='vertical-align: middle; text-align: right;'>
                            <div style='width: 80px; height: 48px;'></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div style='text-align: center; margin: 25px 0;'>
                <h2 style='margin: 0; font-size: 18px; color: #2c3e50; text-transform: uppercase; letter-spacing: 1px;'>
                    {$title}
                </h2>
                <p style='margin: 5px 0; font-size: 12px; color: #7f8c8d;'>
                    Dicetak: {$tanggal}
                </p>
            </div>
        ";
    }
    
    /**
     * Generate footer PDF (tanda tangan)
     */
    private function getFooter($show_signature = true) {
        if (!$show_signature) return '';
        
        $tanggal = date('d F Y');
        
        return "
            <div style='margin-top: 40px; page-break-inside: avoid;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td width='50%'></td>
                        <td width='50%' style='text-align: center;'>
                            <p style='margin: 0; font-size: 12px;'>Kota Pendidikan, {$tanggal}</p>
                            <p style='margin: 5px 0; font-size: 12px;'>Admin Akademik,</p>
                            <div style='height: 60px;'></div>
                            <p style='margin: 0; font-size: 12px; border-top: 1px solid #333; display: inline-block; padding-top: 5px;'>
                                (_________________)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div style='margin-top: 30px; padding-top: 15px; border-top: 1px solid #e9ecef; text-align: center;'>
                    <p style='margin: 0; font-size: 10px; color: #7f8c8d;'>
                        Dokumen ini dihasilkan otomatis oleh Sistem SIMAK - Politeknik Mitra Industri
                    </p>
                </div>
            </div>
        ";
    }
    
    /**
     * Base CSS untuk PDF
     */
    private function getBaseStyle() {
        return "
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 11px; 
                    color: #333; 
                    line-height: 1.5;
                    padding: 30px 40px;
                }
                h1, h2, h3 { color: #2c3e50; }
                
                table.data {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                table.data th {
                    background: #2c3e50;
                    color: #fff;
                    padding: 8px 10px;
                    text-align: left;
                    font-size: 10px;
                    text-transform: uppercase;
                    border: 1px solid #2c3e50;
                }
                table.data td {
                    padding: 7px 10px;
                    border: 1px solid #ddd;
                    font-size: 10px;
                }
                table.data tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                table.data tbody tr:hover {
                    background: #e8f4fd;
                }
                
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                
                .note {
                    font-weight: bold;
                }
                
                .info-box {
                    background: #f8f9fa;
                    border-left: 4px solid #3498db;
                    padding: 12px 15px;
                    margin: 15px 0;
                    border-radius: 4px;
                }
                .info-box table {
                    width: 100%;
                }
                .info-box td {
                    padding: 3px 0;
                    font-size: 11px;
                }
                
                .stat-box {
                    display: inline-block;
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    padding: 10px 15px;
                    margin: 5px;
                    border-radius: 4px;
                    min-width: 100px;
                }
                .stat-box .stat-num {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2c3e50;
                }
                .stat-box .stat-label {
                    font-size: 9px;
                    color: #7f8c8d;
                }
            </style>
        ";
    }
    
    // ============================================
    // LAPORAN NILAI
    // ============================================
    
    public function generateLaporanNilai($course_id = null, $semester = null) {
        require_once __DIR__ . '/LaporanController.php';
        $laporanController = new LaporanController($this->pdo);
        
        $data = $laporanController->getLaporanNilai($course_id, $semester);
        $statistik = $laporanController->getStatistikNilai($course_id);
        
        // Info filter
        $filter_info = '';
        if ($course_id) {
            $stmt = $this->pdo->prepare("SELECT kode_mk, nama_mk FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $mk = $stmt->fetch();
            if ($mk) $filter_info .= "Mata Kuliah: {$mk['kode_mk']} - {$mk['nama_mk']}<br>";
        }
        if ($semester) {
            $filter_info .= "Semester: {$semester}<br>";
        }
        if (!$filter_info) {
            $filter_info = "Semua Mata Kuliah & Semester";
        }
        
        // Statistik HTML
        $statistik_html = '';
        if (!empty($statistik)) {
            $statistik_html = "
                <h3 style='font-size: 13px; margin: 20px 0 10px;'>📊 Statistik Nilai per Mata Kuliah</h3>
                <table class='data'>
                    <thead>
                        <tr>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th class='text-center'>Jumlah</th>
                            <th class='text-center'>Rata-rata</th>
                            <th class='text-center'>Max</th>
                            <th class='text-center'>Min</th>
                            <th class='text-center'>A</th>
                            <th class='text-center'>B</th>
                            <th class='text-center'>C</th>
                            <th class='text-center'>D</th>
                            <th class='text-center'>E</th>
                        </tr>
                    </thead>
                    <tbody>
            ";
            foreach ($statistik as $s) {
                $statistik_html .= "
                    <tr>
                        <td class='text-bold'>{$s['kode_mk']}</td>
                        <td>{$s['nama_mk']}</td>
                        <td class='text-center'>{$s['total_mahasiswa']}</td>
                        <td class='text-center text-bold'>" . number_format($s['rata_rata'], 2) . "</td>
                        <td class='text-center' style='color: #2ecc71;'>" . number_format($s['nilai_max'], 1) . "</td>
                        <td class='text-center' style='color: #e74c3c;'>" . number_format($s['nilai_min'], 1) . "</td>
                        <td class='text-center'>{$s['jumlah_a']}</td>
                        <td class='text-center'>{$s['jumlah_b']}</td>
                        <td class='text-center'>{$s['jumlah_c']}</td>
                        <td class='text-center'>{$s['jumlah_d']}</td>
                        <td class='text-center'>{$s['jumlah_e']}</td>
                    </tr>
                ";
            }
            $statistik_html .= "</tbody></table>";
        }
        
        // Detail nilai
        $detail_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Detail Nilai (" . count($data) . " data)</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Mahasiswa</th>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th class='text-center'>SKS</th>
                        <th class='text-center'>Smt</th>
                        <th class='text-center'>Tugas</th>
                        <th class='text-center'>UTS</th>
                        <th class='text-center'>UAS</th>
                        <th class='text-center'>Akhir</th>
                        <th class='text-center'>Huruf</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($data)) {
            $detail_html .= "<tr><td colspan='11' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada data nilai</td></tr>";
        } else {
            foreach ($data as $row) {
                $nilai = floatval($row['nilai_akhir']);
                if ($nilai >= 85) { $huruf = 'A'; $warna = '#2ecc71'; }
                elseif ($nilai >= 75) { $huruf = 'B'; $warna = '#3498db'; }
                elseif ($nilai >= 65) { $huruf = 'C'; $warna = '#f39c12'; }
                elseif ($nilai >= 50) { $huruf = 'D'; $warna = '#e67e22'; }
                else { $huruf = 'E'; $warna = '#e74c3c'; }
                
                $detail_html .= "
                    <tr>
                        <td class='text-bold'>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td class='text-center'>{$row['sks']}</td>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-center'>" . number_format($row['nilai_tugas'], 1) . "</td>
                        <td class='text-center'>" . number_format($row['nilai_uts'], 1) . "</td>
                        <td class='text-center'>" . number_format($row['nilai_uas'], 1) . "</td>
                        <td class='text-center text-bold'>" . number_format($nilai, 1) . "</td>
                        <td class='text-center' style='color: {$warna}; font-weight: bold;'>{$huruf}</td>
                    </tr>
                ";
            }
        }
        $detail_html .= "</tbody></table>";
        
        // Build HTML
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Laporan Nilai Mahasiswa')}
                
                <div class='info-box'>
                    <strong>Filter:</strong><br>
                    {$filter_info}
                    <strong>Total Data:</strong> " . count($data) . " nilai
                </div>
                
                {$statistik_html}
                {$detail_html}
                
                {$this->getFooter()}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'laporan_nilai', true);
    }
    
    // ============================================
    // LAPORAN REKAP NILAI MAHASISWA (PER SEMESTER)
    // ============================================
    
    public function generateLaporanRekapNilai($mahasiswa_id, $semester = null) {
        require_once __DIR__ . '/LaporanController.php';
        require_once __DIR__ . '/MahasiswaController.php';
        $laporanController = new LaporanController($this->pdo);
        
        $mhsController = new MahasiswaController($this->pdo, $mahasiswa_id);
        $rows = $mhsController->getKHS(null);
        
        if ($semester) {
            $rows = array_values(array_filter($rows, function($r) use ($semester) {
                return $r['semester'] == $semester;
            }));
        }
        
        $mhs = null;
        foreach ($laporanController->getMahasiswaList() as $m) {
            if (intval($m['id']) === intval($mahasiswa_id)) { $mhs = $m; break; }
        }
        
        $ips_data = LaporanController::agregasiPerSemester($rows);
        
        // Hitung IPK/IPS dari baris yang tampil
        $total_sks = 0; $total_bobot = 0.0;
        foreach ($rows as $r) {
            $tp = intval($r['total_pertemuan']);
            $kh = ($tp > 0) ? ((intval($r['p_hadir']) + intval($r['p_izin']) + intval($r['p_sakit'])) / $tp) * 100 : null;
            $nf = LaporanController::hitungNilaiFinal(floatval($r['nilai_akhir']), $kh);
            $b = LaporanController::nilaiKeBobot($nf);
            $total_sks += intval($r['sks']);
            $total_bobot += ($b * intval($r['sks']));
        }
        $gpa = ($total_sks > 0) ? round($total_bobot / $total_sks, 2) : 0;
        $gpa_label = $semester ? ('IPS Semester ' . $semester) : 'IPK Semua Semester';
        
        $filter_info = 'Mahasiswa: ' . ($mhs['nim'] ?? '-') . ' - ' . ($mhs['mahasiswa_nama'] ?? '-')
            . ' | ' . ($mhs['program_studi'] ?? '-') . '<br><strong>' . $gpa_label . ': ' . number_format($gpa, 2) . '</strong>';
        
        // Tabel IPS per semester (hanya jika semua semester)
        $ips_html = '';
        if (!$semester && !empty($ips_data)) {
            $ips_html = "
                <h3 style='font-size: 13px; margin: 20px 0 10px;'>📊 IPS per Semester</h3>
                <table class='data'>
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th class='text-center'>Jumlah MK</th>
                            <th class='text-center'>Total SKS</th>
                            <th class='text-center'>IPS</th>
                        </tr>
                    </thead>
                    <tbody>
            ";
            foreach ($ips_data as $sem => $info) {
                $ips_html .= "
                    <tr>
                        <td class='text-bold'>Semester {$sem}</td>
                        <td class='text-center'>{$info['jumlah_mk']}</td>
                        <td class='text-center'>{$info['total_sks']}</td>
                        <td class='text-center text-bold' style='color: " . ($info['ips'] >= 3.0 ? '#2ecc71' : ($info['ips'] >= 2.5 ? '#f39c12' : '#e74c3c')) . ";'>
                            " . number_format($info['ips'], 2) . "
                        </td>
                    </tr>
                ";
            }
            $ips_html .= "
                    <tr style='background: #ecf0f1;'>
                        <td class='text-bold' colspan='2' style='text-align: right;'>IPK Keseluruhan:</td>
                        <td class='text-center text-bold'>{$total_sks}</td>
                        <td class='text-center text-bold' style='color: " . ($gpa >= 3.0 ? '#2ecc71' : ($gpa >= 2.5 ? '#f39c12' : '#e74c3c')) . ";'>
                            " . number_format($gpa, 2) . "
                        </td>
                    </tr>
                </tbody></table>";
        }
        
        // Detail nilai
        $detail_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Detail Nilai (" . ($semester ? 'Semester ' . $semester : 'Semua Semester') . ")</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>Smt</th>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th class='text-center'>SKS</th>
                        <th class='text-center'>Tugas</th>
                        <th class='text-center'>UTS</th>
                        <th class='text-center'>UAS</th>
                        <th class='text-center'>Akhir</th>
                        <th class='text-center'>Huruf</th>
                        <th class='text-center'>Bobot</th>
                        <th class='text-center'>Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($rows)) {
            $detail_html .= "<tr><td colspan='11' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada data nilai</td></tr>";
        } else {
            foreach ($rows as $row) {
                $tp = intval($row['total_pertemuan']);
                $kh = ($tp > 0) ? round(((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100, 1) . '%' : '-';
                $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']),
                    ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null);
                $nilai = floatval($nf);
                if ($nilai >= 85) { $huruf = 'A'; $warna = '#2ecc71'; }
                elseif ($nilai >= 75) { $huruf = 'B'; $warna = '#3498db'; }
                elseif ($nilai >= 65) { $huruf = 'C'; $warna = '#f39c12'; }
                elseif ($nilai >= 50) { $huruf = 'D'; $warna = '#e67e22'; }
                else { $huruf = 'E'; $warna = '#e74c3c'; }
                $bobot = LaporanController::nilaiKeBobot($nilai);
                
                $detail_html .= "
                    <tr>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-bold'>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td class='text-center'>{$row['sks']}</td>
                        <td class='text-center'>" . number_format($row['nilai_tugas'], 1) . "</td>
                        <td class='text-center'>" . number_format($row['nilai_uts'], 1) . "</td>
                        <td class='text-center'>" . number_format($row['nilai_uas'], 1) . "</td>
                        <td class='text-center text-bold'>" . number_format($nilai, 1) . "</td>
                        <td class='text-center' style='color: {$warna}; font-weight: bold;'>{$huruf}</td>
                        <td class='text-center'>" . number_format($bobot, 1) . "</td>
                        <td class='text-center'>{$kh}</td>
                    </tr>
                ";
            }
        }
        $detail_html .= "</tbody></table>";
        
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Report Nilai Mahasiswa')}
                
                <div class='info-box'>
                    {$filter_info}
                </div>
                
                {$ips_html}
                {$detail_html}
                
                {$this->getFooter()}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'report_nilai_mahasiswa', true);
    }
    
    /**
     * Generate PDF laporan nilai mahasiswa yang diampu dosen.
     */
    public function generateLaporanNilaiDosen($dosen_id, $course_id = null, $semester = null) {
        require_once __DIR__ . '/DosenController.php';
        require_once __DIR__ . '/LaporanController.php';
        $dosenController = new DosenController($this->pdo, $dosen_id);
        
        $data = $dosenController->getLaporanNilaiMahasiswaAmpu($course_id, $semester);
        
        $filter_info = '';
        if ($course_id) {
            $stmt = $this->pdo->prepare("SELECT kode_mk, nama_mk FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $mk = $stmt->fetch();
            if ($mk) $filter_info .= "Mata Kuliah: {$mk['kode_mk']} - {$mk['nama_mk']}<br>";
        } else {
            $filter_info .= "Semua Mata Kuliah yang Diampu<br>";
        }
        if ($semester) {
            $filter_info .= "Semester: {$semester}<br>";
        } else {
            $filter_info .= "Semua Semester<br>";
        }
        
        // Statistik
        $jumlah = count($data);
        $sum_akhir = 0; $tot_sks = 0; $tot_bobot = 0.0;
        $count_a = 0; $count_b = 0; $count_c = 0; $count_d = 0; $count_e = 0;
        foreach ($data as $row) {
            $tp = intval($row['total_pertemuan']);
            $kh = ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null;
            $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']), $kh);
            if ($nf >= 85) $count_a++;
            elseif ($nf >= 75) $count_b++;
            elseif ($nf >= 65) $count_c++;
            elseif ($nf >= 50) $count_d++;
            else $count_e++;
            $sum_akhir += $nf;
            $tot_sks += intval($row['sks']);
            $tot_bobot += (LaporanController::nilaiKeBobot($nf) * intval($row['sks']));
        }
        $rata = ($jumlah > 0) ? round($sum_akhir / $jumlah, 2) : null;
        $ips = ($tot_sks > 0) ? round($tot_bobot / $tot_sks, 2) : null;
        
        // Statistik HTML
        $statistik_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📊 Ringkasan</h3>
            <table class='data' style='width: auto;'>
                <tr>
                    <td style='padding: 6px 15px;'><strong>Jumlah Nilai</strong></td>
                    <td style='padding: 6px 15px;'>{$jumlah}</td>
                </tr>
                <tr>
                    <td style='padding: 6px 15px;'><strong>Rata-rata</strong></td>
                    <td style='padding: 6px 15px;'>" . ($rata !== null ? number_format($rata, 2) : '-') . "</td>
                </tr>
                <tr>
                    <td style='padding: 6px 15px;'><strong>IPS / Bobot</strong></td>
                    <td style='padding: 6px 15px;'>" . ($ips !== null ? number_format($ips, 2) : '-') . "</td>
                </tr>
                <tr>
                    <td style='padding: 6px 15px;'><strong>A / B / C / D / E</strong></td>
                    <td style='padding: 6px 15px;'>{$count_a} / {$count_b} / {$count_c} / {$count_d} / {$count_e}</td>
                </tr>
            </table>
        ";
        
        // Detail nilai
        $detail_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Detail Nilai Mahasiswa (" . $jumlah . " data)</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Mahasiswa</th>
                        <th>Smt</th>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th class='text-center'>SKS</th>
                        <th class='text-center'>Tugas</th>
                        <th class='text-center'>UTS</th>
                        <th class='text-center'>UAS</th>
                        <th class='text-center'>Akhir</th>
                        <th class='text-center'>Huruf</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($data)) {
            $detail_html .= "<tr><td colspan='11' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada data nilai</td></tr>";
        } else {
            foreach ($data as $row) {
                $tp = intval($row['total_pertemuan']);
                $kh = ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null;
                $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']), $kh);
                $nilai = floatval($nf);
                if ($nilai >= 85) { $huruf = 'A'; $warna = '#2ecc71'; }
                elseif ($nilai >= 75) { $huruf = 'B'; $warna = '#3498db'; }
                elseif ($nilai >= 65) { $huruf = 'C'; $warna = '#f39c12'; }
                elseif ($nilai >= 50) { $huruf = 'D'; $warna = '#e67e22'; }
                else { $huruf = 'E'; $warna = '#e74c3c'; }
                
                $detail_html .= "
                    <tr>
                        <td class='text-bold'>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-bold'>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td class='text-center'>{$row['sks']}</td>
                        <td class='text-center'>" . number_format($row['nilai_tugas'], 1) . "</td>
                        <td class='text-center'>" . number_format($row['nilai_uts'], 1) . "</td>
                        <td class='text-center'>" . number_format($row['nilai_uas'], 1) . "</td>
                        <td class='text-center text-bold'>" . number_format($nilai, 1) . "</td>
                        <td class='text-center' style='color: {$warna}; font-weight: bold;'>{$huruf}</td>
                    </tr>
                ";
            }
        }
        $detail_html .= "</tbody></table>";
        
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Laporan Nilai Mahasiswa Diampu')}
                
                <div class='info-box'>
                    <strong>Filter:</strong><br>
                    {$filter_info}
                </div>
                
                {$statistik_html}
                {$detail_html}
                
                {$this->getFooter(false)}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'laporan_nilai_dosen', true);
    }
    
    // ============================================
    // LAPORAN IRS
    // ============================================
    
    public function generateLaporanIRS($semester = null, $status = null) {
        require_once __DIR__ . '/LaporanController.php';
        $laporanController = new LaporanController($this->pdo);
        
        $data = $laporanController->getLaporanIRS($semester, $status);
        
        $filter_info = '';
        if ($semester) $filter_info .= "Semester: {$semester}<br>";
        if ($status) $filter_info .= "Status: " . ucfirst($status) . "<br>";
        if (!$filter_info) $filter_info = "Semua Semester & Status";
        
        // Status color
        $status_color = [
            'pending' => '#f39c12',
            'approved' => '#2ecc71',
            'rejected' => '#e74c3c'
        ];
        
        $table_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Detail IRS (" . count($data) . " data)</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Mahasiswa</th>
                        <th>Prodi</th>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th class='text-center'>SKS</th>
                        <th class='text-center'>Smt</th>
                        <th class='text-center'>Status</th>
                        <th class='text-center'>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($data)) {
            $table_html .= "<tr><td colspan='9' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada data IRS</td></tr>";
        } else {
            foreach ($data as $row) {
                $color = $status_color[$row['status']] ?? '#7f8c8d';
                $table_html .= "
                    <tr>
                        <td class='text-bold'>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td>" . htmlspecialchars($row['program_studi']) . "</td>
                        <td>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td class='text-center'>{$row['sks']}</td>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-center' style='color: {$color}; font-weight: bold;'>" . strtoupper($row['status']) . "</td>
                        <td class='text-center'>" . date('d-m-Y', strtotime($row['created_at'])) . "</td>
                    </tr>
                ";
            }
        }
        $table_html .= "</tbody></table>";
        
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Laporan Rencana Studi (IRS)')}
                
                <div class='info-box'>
                    <strong>Filter:</strong><br>
                    {$filter_info}
                    <strong>Total Data:</strong> " . count($data) . " IRS
                </div>
                
                {$table_html}
                
                {$this->getFooter()}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'laporan_irs', true);
    }
    
    // ============================================
    // LAPORAN PRESENSI
    // ============================================
    
    public function generateLaporanPresensi($course_id = null) {
        require_once __DIR__ . '/LaporanController.php';
        $laporanController = new LaporanController($this->pdo);
        
        $data = $laporanController->getLaporanPresensi($course_id);
        $statistik = $laporanController->getStatistikPresensi($course_id);
        
        $filter_info = '';
        if ($course_id) {
            $stmt = $this->pdo->prepare("SELECT kode_mk, nama_mk FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $mk = $stmt->fetch();
            if ($mk) $filter_info = "Mata Kuliah: {$mk['kode_mk']} - {$mk['nama_mk']}";
        }
        if (!$filter_info) $filter_info = "Semua Mata Kuliah";
        
        // Statistik
        $statistik_html = '';
        if (!empty($statistik)) {
            $statistik_html = "
                <h3 style='font-size: 13px; margin: 20px 0 10px;'>📊 Statistik Presensi per Mata Kuliah</h3>
                <table class='data'>
                    <thead>
                        <tr>
                            <th>Kode MK</th>
                            <th>Mata Kuliah</th>
                            <th class='text-center'>Mahasiswa</th>
                            <th class='text-center'>Total</th>
                            <th class='text-center'>Hadir</th>
                            <th class='text-center'>Izin</th>
                            <th class='text-center'>Sakit</th>
                            <th class='text-center'>Alpa</th>
                            <th class='text-center'>%</th>
                        </tr>
                    </thead>
                    <tbody>
            ";
            foreach ($statistik as $s) {
                $persen_color = $s['persentase'] >= 75 ? '#2ecc71' : ($s['persentase'] >= 50 ? '#f39c12' : '#e74c3c');
                $statistik_html .= "
                    <tr>
                        <td class='text-bold'>{$s['kode_mk']}</td>
                        <td>{$s['nama_mk']}</td>
                        <td class='text-center'>{$s['total_mahasiswa']}</td>
                        <td class='text-center'>{$s['total_presensi']}</td>
                        <td class='text-center' style='color: #2ecc71;'>{$s['hadir']}</td>
                        <td class='text-center' style='color: #f39c12;'>{$s['izin']}</td>
                        <td class='text-center' style='color: #3498db;'>{$s['sakit']}</td>
                        <td class='text-center' style='color: #e74c3c;'>{$s['alpa']}</td>
                        <td class='text-center text-bold' style='color: {$persen_color};'>{$s['persentase']}%</td>
                    </tr>
                ";
            }
            $statistik_html .= "</tbody></table>";
        }
        
        // Detail
        $detail_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Detail Presensi (" . count($data) . " data)</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th>NIM</th>
                        <th>Mahasiswa</th>
                        <th class='text-center'>Hadir</th>
                        <th class='text-center'>Izin</th>
                        <th class='text-center'>Sakit</th>
                        <th class='text-center'>Alpa</th>
                        <th class='text-center'>Total</th>
                        <th class='text-center'>%</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($data)) {
            $detail_html .= "<tr><td colspan='10' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada data presensi</td></tr>";
        } else {
            foreach ($data as $row) {
                $persen_color = $row['persentase_hadir'] >= 75 ? '#2ecc71' : ($row['persentase_hadir'] >= 50 ? '#f39c12' : '#e74c3c');
                $detail_html .= "
                    <tr>
                        <td class='text-bold'>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td class='text-center' style='color: #2ecc71;'>{$row['hadir']}</td>
                        <td class='text-center' style='color: #f39c12;'>{$row['izin']}</td>
                        <td class='text-center' style='color: #3498db;'>{$row['sakit']}</td>
                        <td class='text-center' style='color: #e74c3c;'>{$row['alpa']}</td>
                        <td class='text-center text-bold'>{$row['total_pertemuan']}</td>
                        <td class='text-center text-bold' style='color: {$persen_color};'>{$row['persentase_hadir']}%</td>
                    </tr>
                ";
            }
        }
        $detail_html .= "</tbody></table>";
        
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Laporan Presensi Mahasiswa')}
                
                <div class='info-box'>
                    <strong>Filter:</strong><br>
                    {$filter_info}<br>
                    <strong>Total Data:</strong> " . count($data) . " presensi
                </div>
                
                {$statistik_html}
                {$detail_html}
                
                {$this->getFooter()}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'laporan_presensi', true);
    }
    
    // ============================================
    // LAPORAN MAHASISWA
    // ============================================
    
    public function generateLaporanMahasiswa($jurusan_id = null) {
        require_once __DIR__ . '/LaporanController.php';
        $laporanController = new LaporanController($this->pdo);
        
        $data = $laporanController->getLaporanMahasiswa($jurusan_id);
        
        // Info filter
        $filter_info = '';
        if ($jurusan_id) {
            $stmt = $this->pdo->prepare("SELECT kode, nama FROM jurusan WHERE id = ?");
            $stmt->execute([$jurusan_id]);
            $jr = $stmt->fetch();
            if ($jr) $filter_info .= "Jurusan: {$jr['kode']} - {$jr['nama']}<br>";
        }
        if (!$filter_info) {
            $filter_info = "Semua Jurusan<br>";
        }
        
        $table_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Data Mahasiswa (" . count($data) . " mahasiswa)</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Program Studi</th>
                        <th class='text-center'>Angkatan</th>
                        <th class='text-center'>Smt</th>
                        <th class='text-center'>Total SKS</th>
                        <th class='text-center'>IPK</th>
                        <th class='text-center'>Predikat</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($data)) {
            $table_html .= "<tr><td colspan='8' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada data mahasiswa</td></tr>";
        } else {
            foreach ($data as $row) {
                $ipk = $row['ipk'] ?? 0;
                if ($ipk >= 3.5) { $predikat = 'Cumlaude'; $warna = '#2ecc71'; }
                elseif ($ipk >= 3.0) { $predikat = 'Sangat Baik'; $warna = '#3498db'; }
                elseif ($ipk >= 2.5) { $predikat = 'Baik'; $warna = '#f39c12'; }
                elseif ($ipk >= 2.0) { $predikat = 'Cukup'; $warna = '#e67e22'; }
                else { $predikat = 'Perlu Perbaikan'; $warna = '#e74c3c'; }
                
                $table_html .= "
                    <tr>
                        <td class='text-bold'>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td>" . htmlspecialchars($row['program_studi']) . "</td>
                        <td class='text-center'>{$row['angkatan']}</td>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-center text-bold'>{$row['total_sks']}</td>
                        <td class='text-center text-bold' style='color: {$warna};'>" . number_format($ipk, 2) . "</td>
                        <td class='text-center' style='color: {$warna}; font-weight: bold;'>{$predikat}</td>
                    </tr>
                ";
            }
        }
        $table_html .= "</tbody></table>";
        
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Laporan Data Mahasiswa')}
                
                <div class='info-box'>
                    <strong>Filter:</strong> {$filter_info}<br>
                    <strong>Total:</strong> " . count($data) . " mahasiswa
                </div>
                
                {$table_html}
                
                {$this->getFooter()}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'laporan_mahasiswa', false);
    }
    
    // ============================================
    // RENDER PDF
    // ============================================
    
    /**
     * Render HTML ke PDF
     * $is_wide: true -> kertas A4 landscape (untuk tabel/field lebar),
     *           false -> kertas A4 portrait.
     */
    private function renderPdf($html, $filename_prefix = 'laporan', $is_wide = false) {
        try {
            $dompdf = $this->setupDompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', $this->paperOrientation($is_wide));
            $dompdf->render();
            
            $filename = $filename_prefix . '_' . date('Ymd_His') . '.pdf';
            
            // Output ke browser (inline)
            $dompdf->stream($filename, ['Attachment' => false]);
            exit();
            
        } catch (Exception $e) {
            die('Error generate PDF: ' . $e->getMessage());
        }
    }
    
    // ============================================
    // REPORT KRS (MAHASISWA)
    // ============================================
    
    public function generateReportKRS($mahasiswa_id, $semester) {
        require_once __DIR__ . '/MahasiswaController.php';
        $mhsController = new MahasiswaController($this->pdo, $mahasiswa_id);
        
        $irs = $mhsController->getIRS($semester);
        
        // Info mahasiswa
        $stmt = $this->pdo->prepare("
            SELECT m.nim, m.semester as semester_aktif, u.nama as mahasiswa_nama,
                   j.nama as program_studi, f.nama as fakultas_nama
            FROM mahasiswa m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            LEFT JOIN fakultas f ON m.fakultas_id = f.id
            WHERE m.id = ?
        ");
        $stmt->execute([$mahasiswa_id]);
        $mhs = $stmt->fetch() ?: ['nim' => '-', 'mahasiswa_nama' => '-', 'program_studi' => '-', 'fakultas_nama' => '-', 'semester_aktif' => '-'];
        
        $total_sks = 0;
        $status_map = ['pending' => 'Menunggu Validasi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
        $status_color_map = [
            'pending' => '#f39c12',
            'approved' => '#2ecc71',
            'rejected' => '#e74c3c',
        ];
        
        foreach ($irs as $row) {
            $total_sks += intval($row['sks']);
        }
        
        $status_irs = $mhsController->getIRSStatus($semester);
        $status_irs_label = $status_map[$status_irs] ?? 'Belum Ada IRS';
        $status_irs_color = $status_color_map[$status_irs] ?? '#7f8c8d';
        
        $filter_info = "
            <strong style='font-size: 11px;'>Nama:</strong> {$mhs['mahasiswa_nama']}<br>
            <strong style='font-size: 11px;'>NIM:</strong> {$mhs['nim']}<br>
            <strong style='font-size: 11px;'>Program Studi:</strong> " . htmlspecialchars($mhs['program_studi']) . "<br>
            <strong style='font-size: 11px;'>Fakultas:</strong> " . htmlspecialchars($mhs['fakultas_nama']) . "<br>
            <strong style='font-size: 11px;'>Semester:</strong> {$semester}<br>
            <strong style='font-size: 11px;'>Semester Aktif:</strong> {$mhs['semester_aktif']}<br>
            <strong style='font-size: 11px;'>Status IRS:</strong>
                <span style='color: {$status_irs_color}; font-weight: bold;'>{$status_irs_label}</span><br>
            <strong style='font-size: 11px;'>Total SKS:</strong> {$total_sks}
        ";
        
        // Detail KRS
        $detail_html = "
            <h3 style='font-size: 13px; margin: 20px 0 10px;'>📋 Detail Mata Kuliah Diambil (" . count($irs) . " data)</h3>
            <table class='data'>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th class='text-center'>SKS</th>
                        <th>Dosen Pengampu</th>
                        <th>Jadwal</th>
                        <th class='text-center'>Status</th>
                    </tr>
                </thead>
                <tbody>
        ";
        
        if (empty($irs)) {
            $detail_html .= "<tr><td colspan='7' class='text-center' style='padding: 20px; color: #7f8c8d;'>Tidak ada mata kuliah di IRS semester ini</td></tr>";
        } else {
            $no = 1;
            foreach ($irs as $row) {
                $color = $status_color_map[$row['status']] ?? '#7f8c8d';
                $status_label = $status_map[$row['status']] ?? ucfirst($row['status']);
                $dosen_nama = htmlspecialchars($row['dosen_nama'] ?? '-');
                $jadwal_text = htmlspecialchars(!empty($row['jadwal_info']) ? $row['jadwal_info'] : '-');
                $detail_html .= "
                    <tr>
                        <td class='text-center'>{$no}</td>
                        <td class='text-bold'>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td class='text-center'>{$row['sks']}</td>
                        <td>{$dosen_nama}</td>
                        <td>{$jadwal_text}</td>
                        <td class='text-center' style='color: {$color}; font-weight: bold;'>{$status_label}</td>
                    </tr>
                ";
                $no++;
            }
        }
        $detail_html .= "
                    <tr style='background: #ecf0f1;'>
                        <td class='text-bold' colspan='3' style='text-align: right;'>Total SKS:</td>
                        <td class='text-center text-bold'>{$total_sks}</td>
                        <td colspan='3'></td>
                    </tr>
                </tbody></table>
        ";
        
        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Report Kartu Rencana Studi (KRS)')}
                
                <div class='info-box'>
                    {$filter_info}
                </div>
                
                {$detail_html}
                
                {$this->getFooter()}
            </body>
            </html>
        ";
        
        return $this->renderPdf($html, 'report_krs', false);
    }
}
?>