<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/MahasiswaController.php';

checkAccess(['mahasiswa']);

$stmt = $pdo->prepare("SELECT id, semester FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    die('Data mahasiswa tidak ditemukan');
}

$mahasiswa_id = $mahasiswa['id'];
$controller = new MahasiswaController($pdo, $mahasiswa_id);

$semester = $_GET['semester'] ?? $mahasiswa['semester'];
$irs_data = $controller->getIRSForPrint($semester);

if (empty($irs_data)) {
    die('Tidak ada data IRS untuk semester ini');
}

$total_sks = array_sum(array_column($irs_data, 'sks'));
$mahasiswa_nama = $irs_data[0]['mahasiswa_nama'] ?? '';
$nim = $irs_data[0]['nim'] ?? '';
$program_studi = $irs_data[0]['jurusan_nama'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>IRS - Semester <?= $semester ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #555;
            font-size: 14px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-row .label {
            font-weight: bold;
            min-width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #333;
            padding: 8px 12px;
            text-align: left;
            font-size: 13px;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            text-align: center;
            width: 45%;
        }
        .signature .line {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 12px;
        }
        .status-print {
            display: inline;
            padding: 0;
            border-radius: 0;
            font-size: 11px;
            font-weight: bold;
        }
        .status-approved {
            color: #1e8449;
        }
        .status-pending {
            color: #b9770e;
        }
        .status-rejected {
            color: #c0392b;
        }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RENCANA STUDI (IRS)</h1>
        <p>Politeknik Mitra Industri</p>
        <p>Semester <?= $semester ?> - Tahun Akademik <?= date('Y') ?></p>
    </div>
    
    <div class="info-row">
        <span><span class="label">Nama Mahasiswa</span> : <?= htmlspecialchars($mahasiswa_nama) ?></span>
        <span><span class="label">NIM</span> : <?= htmlspecialchars($nim) ?></span>
    </div>
    <div class="info-row">
        <span><span class="label">Program Studi</span> : <?= htmlspecialchars($program_studi) ?></span>
        <span><span class="label">Status IRS</span> : 
            <span class="status-print status-<?= $irs_data[0]['status'] ?>">
                <?= strtoupper($irs_data[0]['status']) ?>
            </span>
        </span>
    </div>
    
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th width="15%">Kode MK</th>
                <th width="40%">Nama Mata Kuliah</th>
                <th width="10%" class="text-center">SKS</th>
                <th width="20%">Dosen Pengampu</th>
                <th width="15%">Ruang</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($irs_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['kode_mk']) ?></td>
                    <td><?= htmlspecialchars($row['nama_mk']) ?></td>
                    <td class="text-center"><?= $row['sks'] ?></td>
                    <td><?= htmlspecialchars($row['dosen_nama'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['ruang'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align: right; font-weight: bold;">Total SKS</td>
                <td class="text-center" style="font-weight: bold;"><?= $total_sks ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        <div class="signature">
            <p>Mahasiswa,</p>
            <div class="line"><?= htmlspecialchars($mahasiswa_nama) ?></div>
            <div style="font-size: 11px; color: #555;">(<?= htmlspecialchars($nim) ?>)</div>
        </div>
        <div class="signature">
            <p>Validasi Admin,</p>
            <div class="line">_____________________</div>
            <div style="font-size: 11px; color: #555;">Admin Akademik</div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; font-size: 11px; color: #7f8c8d;">
        Dicetak pada: <?= date('d-m-Y H:i:s') ?>
    </div>
    
    <div style="text-align: center; margin-top: 15px;" class="no-print">
        <button onclick="window.print()" style="padding: 8px 20px; border: none; background: #3498db; color: #fff; border-radius: 6px; cursor: pointer; font-size: 14px;">
            <i class="fas fa-print"></i> Cetak / Print
        </button>
        <button onclick="window.close()" style="padding: 8px 20px; border: none; background: #95a5a6; color: #fff; border-radius: 6px; cursor: pointer; font-size: 14px;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
</body>
</html>