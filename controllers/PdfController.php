<?php
// ============================================
// controllers/PdfController.php
// Generate PDF untuk semua laporan (FORMAL STYLE + FONT BESAR)
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
     * Helper: tentukan orientasi kertas.
     */
    private function paperOrientation($is_wide) {
        return $is_wide ? 'landscape' : 'portrait';
    }
    
    /**
     * Generate header PDF (kop surat formal)
     */
    private function getHeader($title) {
        $tanggal = date('d F Y');
        $logo_path = __DIR__ . '/../public/assets/img/poltek.png';
        if (file_exists($logo_path)) {
            $logo_data = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
        } else {
            $logo_data = 'data:image/svg+xml;base64,' . base64_encode('
                <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#1a3a5c"/>
                    <text x="50" y="65" font-size="40" text-anchor="middle" fill="white" font-family="Arial" font-weight="bold">S</text>
                </svg>
            ');
        }
        
        return "
            <div style='border-bottom: 3px solid #1a3a5c; padding-bottom: 12px; margin-bottom: 18px;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td width='110' style='vertical-align: middle;'>
                            <img src='{$logo_data}' width='95' height='56'>
                        </td>
                        <td style='text-align: center; vertical-align: middle;'>
                            <h1 style='margin: 0; font-size: 18px; color: #1a3a5c; letter-spacing: 1px; font-weight: bold;'>
                                POLITEKNIK MITRA INDUSTRI
                            </h1>
                            <p style='margin: 2px 0; font-size: 11px; color: #333;'>
                                Sistem Informasi &amp; Manajemen Akademik Kampus
                            </p>
                            <p style='margin: 2px 0; font-size: 10px; color: #555;'>
                                Jl. kalimantan No. 1, kab. Bekasi | Telp: (021) 1234567
                            </p>
                        </td>
                        <td width='90' style='vertical-align: middle;'>
                            <div style='width: 75px; height: 45px;'></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div style='text-align: center; margin: 22px 0 18px;'>
                <h2 style='margin: 0; font-size: 15px; color: #1a3a5c; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;'>
                    {$title}
                </h2>
                <p style='margin: 4px 0 0; font-size: 10px; color: #555;'>
                    Dicetak: {$tanggal}
                </p>
            </div>
        ";
    }
    
    /**
     * Generate footer PDF (tanda tangan formal)
     */
    private function getFooter($show_signature = true) {
        if (!$show_signature) return '';
        
        $tanggal = date('d F Y');
        
        return "
            <div style='margin-top: 35px; page-break-inside: avoid;'>
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td width='50%'></td>
                        <td width='50%' style='text-align: center;'>
                            <p style='margin: 0; font-size: 11px;'>Bekasi, {$tanggal}</p>
                            <p style='margin: 4px 0; font-size: 11px;'>Administrasi Akademik,</p>
                            <div style='height: 55px;'></div>
                            <p style='margin: 0; font-size: 11px; border-top: 1px solid #333; display: inline-block; padding: 4px 25px 0;'>
                                (_________________)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div style='margin-top: 25px; padding-top: 10px; border-top: 1px solid #999; text-align: center;'>
                    <p style='margin: 0; font-size: 9px; color: #555;'>
                        Dokumen ini dihasilkan otomatis oleh Sistem SIMAK - Politeknik Mitra Industri
                    </p>
                </div>
            </div>
        ";
    }
    
    /**
     * Base CSS untuk PDF (formal, font diperbesar)
     */
    private function getBaseStyle() {
        return "
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: Arial, Helvetica, sans-serif; 
                    font-size: 12px;
                    color: #222; 
                    line-height: 1.5;
                    padding: 25px 35px;
                }
                h1, h2, h3 { color: #1a3a5c; }
                
                table.data {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 12px 0;
                    border: 1px solid #333;
                }
                table.data th {
                    background: #1a3a5c;
                    color: #fff;
                    padding: 8px 9px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
                    border: 1px solid #1a3a5c;
                    letter-spacing: 0.3px;
                }
                table.data td {
                    padding: 7px 9px;
                    border: 1px solid #999;
                    font-size: 11px;
                }
                table.data tbody tr:nth-child(even) {
                    background: #f4f4f4;
                }
                
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                
                .section-title {
                    font-size: 13px;
                    font-weight: bold;
                    color: #1a3a5c;
                    margin: 18px 0 8px;
                    padding-bottom: 4px;
                    border-bottom: 1px solid #1a3a5c;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .info-box {
                    border: 1px solid #999;
                    padding: 10px 12px;
                    margin: 12px 0;
                    background: #fafafa;
                }
                .info-box table {
                    width: 100%;
                }
                .info-box td {
                    padding: 3px 0;
                    font-size: 12px;
                    vertical-align: top;
                }
                
                .summary-table {
                    width: auto;
                    border-collapse: collapse;
                    margin: 8px 0 12px;
                    border: 1px solid #333;
                }
                .summary-table td {
                    padding: 6px 14px;
                    font-size: 11px;
                    border: 1px solid #999;
                }
                .summary-table td.label {
                    font-weight: bold;
                    background: #f0f0f0;
                    width: 180px;
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
            $filter_info = "Semua Mata Kuliah &amp; Semester";
        }
        
        // Statistik HTML
        $statistik_html = '';
        if (!empty($statistik)) {
            $statistik_html = "
                <div class='section-title'>Statistik Nilai per Mata Kuliah</div>
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
                        <td class='text-center'>" . number_format($s['nilai_max'], 1) . "</td>
                        <td class='text-center'>" . number_format($s['nilai_min'], 1) . "</td>
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
            <div class='section-title'>Detail Nilai (" . count($data) . " data)</div>
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
            $detail_html .= "<tr><td colspan='11' class='text-center' style='padding: 20px; color: #555;'>Tidak ada data nilai</td></tr>";
        } else {
            foreach ($data as $row) {
                $nilai = floatval($row['nilai_akhir']);
                if ($nilai >= 85)      { $huruf = 'A'; }
                elseif ($nilai >= 75)  { $huruf = 'B'; }
                elseif ($nilai >= 65)  { $huruf = 'C'; }
                elseif ($nilai >= 50)  { $huruf = 'D'; }
                else                   { $huruf = 'E'; }
                
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
                        <td class='text-center text-bold'>{$huruf}</td>
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
        
        // Hitung IPK/IPS
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
        
        // Tabel IPS per semester
        $ips_html = '';
        if (!$semester && !empty($ips_data)) {
            $ips_html = "
                <div class='section-title'>IPS per Semester</div>
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
                        <td class='text-center text-bold'>" . number_format($info['ips'], 2) . "</td>
                    </tr>
                ";
            }
            $ips_html .= "
                    <tr style='background: #e8e8e8;'>
                        <td class='text-bold' colspan='2' style='text-align: right;'>IPK Keseluruhan:</td>
                        <td class='text-center text-bold'>{$total_sks}</td>
                        <td class='text-center text-bold'>" . number_format($gpa, 2) . "</td>
                    </tr>
                </tbody></table>";
        }
        
        // Detail nilai
        $detail_html = "
            <div class='section-title'>Detail Nilai (" . ($semester ? 'Semester ' . $semester : 'Semua Semester') . ")</div>
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
            $detail_html .= "<tr><td colspan='11' class='text-center' style='padding: 20px; color: #555;'>Tidak ada data nilai</td></tr>";
        } else {
            foreach ($rows as $row) {
                $tp = intval($row['total_pertemuan']);
                $kh = ($tp > 0) ? round(((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100, 1) . '%' : '-';
                $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']),
                    ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null);
                $nilai = floatval($nf);
                if ($nilai >= 85)      { $huruf = 'A'; }
                elseif ($nilai >= 75)  { $huruf = 'B'; }
                elseif ($nilai >= 65)  { $huruf = 'C'; }
                elseif ($nilai >= 50)  { $huruf = 'D'; }
                else                   { $huruf = 'E'; }
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
                        <td class='text-center text-bold'>{$huruf}</td>
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
            <div class='section-title'>Ringkasan</div>
            <table class='summary-table'>
                <tr>
                    <td class='label'>Jumlah Nilai</td>
                    <td>{$jumlah}</td>
                </tr>
                <tr>
                    <td class='label'>Rata-rata</td>
                    <td>" . ($rata !== null ? number_format($rata, 2) : '-') . "</td>
                </tr>
                <tr>
                    <td class='label'>IPS / Bobot</td>
                    <td>" . ($ips !== null ? number_format($ips, 2) : '-') . "</td>
                </tr>
                <tr>
                    <td class='label'>A / B / C / D / E</td>
                    <td>{$count_a} / {$count_b} / {$count_c} / {$count_d} / {$count_e}</td>
                </tr>
            </table>
        ";
        
        // Detail nilai
        $detail_html = "
            <div class='section-title'>Detail Nilai Mahasiswa (" . $jumlah . " data)</div>
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
            $detail_html .= "<tr><td colspan='11' class='text-center' style='padding: 20px; color: #555;'>Tidak ada data nilai</td></tr>";
        } else {
            foreach ($data as $row) {
                $tp = intval($row['total_pertemuan']);
                $kh = ($tp > 0) ? ((intval($row['p_hadir']) + intval($row['p_izin']) + intval($row['p_sakit'])) / $tp) * 100 : null;
                $nf = LaporanController::hitungNilaiFinal(floatval($row['nilai_akhir']), $kh);
                $nilai = floatval($nf);
                if ($nilai >= 85)      { $huruf = 'A'; }
                elseif ($nilai >= 75)  { $huruf = 'B'; }
                elseif ($nilai >= 65)  { $huruf = 'C'; }
                elseif ($nilai >= 50)  { $huruf = 'D'; }
                else                   { $huruf = 'E'; }
                
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
                        <td class='text-center text-bold'>{$huruf}</td>
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
    
    /**
     * Generate PDF report absensi mahasiswa pada mata kuliah yang diampu dosen.
     */
    public function generateLaporanAbsensiDosen($dosen_id, $course_id, $semester = null) {
        require_once __DIR__ . '/DosenController.php';
        $dosenController = new DosenController($this->pdo, $dosen_id);

        $mk = null;
        foreach ($dosenController->getMatakuliahDosen() as $m) {
            if (intval($m['id']) === intval($course_id)) {
                $mk = $m;
                break;
            }
        }

        if (!$mk) {
            die('Mata kuliah tidak ditemukan atau bukan mata kuliah yang Anda ampu.');
        }

        $data = $dosenController->getLaporanAbsensiMahasiswaAmpu($course_id);
        $pertemuan = $dosenController->getPertemuanAbsensi($course_id);

        // Statistik kehadiran
        $sum_persen = 0;
        $jumlah_terdata = 0;
        foreach ($data as $row) {
            $total = intval($row['total_pertemuan']);
            if ($total > 0) {
                $sum_persen += (($row['hadir'] + $row['izin'] + $row['sakit']) / $total) * 100;
                $jumlah_terdata++;
            }
        }
        $rata_kehadiran = ($jumlah_terdata > 0) ? round($sum_persen / $jumlah_terdata, 2) : null;

        $filter_info = "Mata Kuliah: " . htmlspecialchars($mk['kode_mk']) . " - " . htmlspecialchars($mk['nama_mk']) . "<br>"
            . "Semester: " . htmlspecialchars($semester ?: $mk['semester']) . "<br>"
            . "Jumlah Mahasiswa: " . count($data) . "<br>"
            . "Pertemuan Tercatat: " . count($pertemuan) . "<br>"
            . "Rata-rata Kehadiran: " . ($rata_kehadiran !== null ? number_format($rata_kehadiran, 1) . '%' : '-');

        // Rekap per mahasiswa
        $rekap_html = "
            <div class='section-title'>Rekap Absensi Mahasiswa</div>
            <table class='data'>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Mahasiswa</th>
                        <th>Program Studi</th>
                        <th class='text-center'>Pertemuan</th>
                        <th class='text-center'>Hadir</th>
                        <th class='text-center'>Izin</th>
                        <th class='text-center'>Sakit</th>
                        <th class='text-center'>Alpa</th>
                        <th class='text-center'>Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
        ";

        if (empty($data)) {
            $rekap_html .= "<tr><td colspan='9' class='text-center' style='padding: 20px; color: #555;'>Belum ada mahasiswa pada mata kuliah ini</td></tr>";
        } else {
            foreach ($data as $row) {
                $total = intval($row['total_pertemuan']);
                $persen = ($total > 0)
                    ? number_format((($row['hadir'] + $row['izin'] + $row['sakit']) / $total) * 100, 1) . '%'
                    : '-';

                $rekap_html .= "
                    <tr>
                        <td class='text-bold'>" . htmlspecialchars($row['nim']) . "</td>
                        <td>" . htmlspecialchars($row['mahasiswa_nama']) . "</td>
                        <td>" . htmlspecialchars($row['program_studi'] ?? '-') . "</td>
                        <td class='text-center'>{$total}</td>
                        <td class='text-center'>{$row['hadir']}</td>
                        <td class='text-center'>{$row['izin']}</td>
                        <td class='text-center'>{$row['sakit']}</td>
                        <td class='text-center'>{$row['alpa']}</td>
                        <td class='text-center text-bold'>{$persen}</td>
                    </tr>
                ";
            }
        }
        $rekap_html .= "</tbody></table>";

        // Ringkasan per pertemuan
        $pertemuan_html = "
            <div class='section-title'>Absensi per Pertemuan</div>
            <table class='data'>
                <thead>
                    <tr>
                        <th>Pertemuan</th>
                        <th>Tanggal</th>
                        <th class='text-center'>Tercatat</th>
                        <th class='text-center'>Hadir</th>
                        <th class='text-center'>Izin</th>
                        <th class='text-center'>Sakit</th>
                        <th class='text-center'>Alpa</th>
                    </tr>
                </thead>
                <tbody>
        ";

        if (empty($pertemuan)) {
            $pertemuan_html .= "<tr><td colspan='7' class='text-center' style='padding: 20px; color: #555;'>Belum ada data absensi per pertemuan</td></tr>";
        } else {
            foreach ($pertemuan as $index => $p) {
                $pertemuan_html .= "
                    <tr>
                        <td class='text-center'>" . ($index + 1) . "</td>
                        <td>" . date('d-m-Y', strtotime($p['tanggal'])) . "</td>
                        <td class='text-center'>{$p['total']}</td>
                        <td class='text-center'>{$p['hadir']}</td>
                        <td class='text-center'>{$p['izin']}</td>
                        <td class='text-center'>{$p['sakit']}</td>
                        <td class='text-center'>{$p['alpa']}</td>
                    </tr>
                ";
            }
        }
        $pertemuan_html .= "</tbody></table>";

        $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                {$this->getBaseStyle()}
            </head>
            <body>
                {$this->getHeader('Laporan Absensi Mahasiswa')}

                <div class='info-box'>
                    {$filter_info}
                </div>

                {$rekap_html}
                {$pertemuan_html}

                {$this->getFooter(false)}
            </body>
            </html>
        ";

        return $this->renderPdf($html, 'laporan_absensi_dosen', true);
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
        if (!$filter_info) $filter_info = "Semua Semester &amp; Status";
        
        $table_html = "
            <div class='section-title'>Detail IRS (" . count($data) . " data)</div>
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
            $table_html .= "<tr><td colspan='9' class='text-center' style='padding: 20px; color: #555;'>Tidak ada data IRS</td></tr>";
        } else {
            foreach ($data as $row) {
                $table_html .= "
                    <tr>
                        <td class='text-bold'>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td>" . htmlspecialchars($row['program_studi']) . "</td>
                        <td>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td class='text-center'>{$row['sks']}</td>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-center text-bold'>" . strtoupper($row['status']) . "</td>
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
                <div class='section-title'>Statistik Presensi per Mata Kuliah</div>
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
                $statistik_html .= "
                    <tr>
                        <td class='text-bold'>{$s['kode_mk']}</td>
                        <td>{$s['nama_mk']}</td>
                        <td class='text-center'>{$s['total_mahasiswa']}</td>
                        <td class='text-center'>{$s['total_presensi']}</td>
                        <td class='text-center'>{$s['hadir']}</td>
                        <td class='text-center'>{$s['izin']}</td>
                        <td class='text-center'>{$s['sakit']}</td>
                        <td class='text-center'>{$s['alpa']}</td>
                        <td class='text-center text-bold'>{$s['persentase']}%</td>
                    </tr>
                ";
            }
            $statistik_html .= "</tbody></table>";
        }
        
        // Detail
        $detail_html = "
            <div class='section-title'>Detail Presensi (" . count($data) . " data)</div>
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
            $detail_html .= "<tr><td colspan='10' class='text-center' style='padding: 20px; color: #555;'>Tidak ada data presensi</td></tr>";
        } else {
            foreach ($data as $row) {
                $detail_html .= "
                    <tr>
                        <td class='text-bold'>{$row['kode_mk']}</td>
                        <td>{$row['nama_mk']}</td>
                        <td>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td class='text-center'>{$row['hadir']}</td>
                        <td class='text-center'>{$row['izin']}</td>
                        <td class='text-center'>{$row['sakit']}</td>
                        <td class='text-center'>{$row['alpa']}</td>
                        <td class='text-center text-bold'>{$row['total_pertemuan']}</td>
                        <td class='text-center text-bold'>{$row['persentase_hadir']}%</td>
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
            <div class='section-title'>Data Mahasiswa (" . count($data) . " mahasiswa)</div>
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
            $table_html .= "<tr><td colspan='8' class='text-center' style='padding: 20px; color: #555;'>Tidak ada data mahasiswa</td></tr>";
        } else {
            foreach ($data as $row) {
                $ipk = $row['ipk'] ?? 0;
                if ($ipk >= 3.5)      { $predikat = 'Cumlaude'; }
                elseif ($ipk >= 3.0)  { $predikat = 'Sangat Baik'; }
                elseif ($ipk >= 2.5)  { $predikat = 'Baik'; }
                elseif ($ipk >= 2.0)  { $predikat = 'Cukup'; }
                else                  { $predikat = 'Perlu Perbaikan'; }
                
                $table_html .= "
                    <tr>
                        <td class='text-bold'>{$row['nim']}</td>
                        <td>{$row['mahasiswa_nama']}</td>
                        <td>" . htmlspecialchars($row['program_studi']) . "</td>
                        <td class='text-center'>{$row['angkatan']}</td>
                        <td class='text-center'>{$row['semester']}</td>
                        <td class='text-center text-bold'>{$row['total_sks']}</td>
                        <td class='text-center text-bold'>" . number_format($ipk, 2) . "</td>
                        <td class='text-center text-bold'>{$predikat}</td>
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
    
    private function renderPdf($html, $filename_prefix = 'laporan', $is_wide = false) {
        try {
            $dompdf = $this->setupDompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', $this->paperOrientation($is_wide));
            $dompdf->render();
            
            $filename = $filename_prefix . '_' . date('Ymd_His') . '.pdf';
            
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
        
        foreach ($irs as $row) {
            $total_sks += intval($row['sks']);
        }
        
        $status_irs = $mhsController->getIRSStatus($semester);
        $status_irs_label = $status_map[$status_irs] ?? 'Belum Ada IRS';
        
        $filter_info = "
            <strong style='font-size: 12px;'>Nama:</strong> {$mhs['mahasiswa_nama']}<br>
            <strong style='font-size: 12px;'>NIM:</strong> {$mhs['nim']}<br>
            <strong style='font-size: 12px;'>Program Studi:</strong> " . htmlspecialchars($mhs['program_studi']) . "<br>
            <strong style='font-size: 12px;'>Fakultas:</strong> " . htmlspecialchars($mhs['fakultas_nama']) . "<br>
            <strong style='font-size: 12px;'>Semester:</strong> {$semester}<br>
            <strong style='font-size: 12px;'>Semester Aktif:</strong> {$mhs['semester_aktif']}<br>
            <strong style='font-size: 12px;'>Status IRS:</strong>
                <span style='font-weight: bold;'>{$status_irs_label}</span><br>
            <strong style='font-size: 12px;'>Total SKS:</strong> {$total_sks}
        ";
        
        // Detail KRS
        $detail_html = "
            <div class='section-title'>Detail Mata Kuliah Diambil (" . count($irs) . " data)</div>
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
            $detail_html .= "<tr><td colspan='7' class='text-center' style='padding: 20px; color: #555;'>Tidak ada mata kuliah di IRS semester ini</td></tr>";
        } else {
            $no = 1;
            foreach ($irs as $row) {
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
                        <td class='text-center text-bold'>{$status_label}</td>
                    </tr>
                ";
                $no++;
            }
        }
        $detail_html .= "
                    <tr style='background: #e8e8e8;'>
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