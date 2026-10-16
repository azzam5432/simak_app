<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>SIMAK - System Test</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 900px; margin: 0 auto; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .test-section h3 { margin-top: 0; }
        .pass { color: #2ecc71; font-weight: bold; }
        .fail { color: #e74c3c; font-weight: bold; }
        .info { color: #3498db; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px 12px; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .badge { padding: 0; border-radius: 0; font-size: 12px; background: none; }
        .badge-success { color: #27ae60; }
        .badge-danger { color: #c0392b; }
        .badge-warning { color: #d68910; }
    </style>
</head>
<body>
    <h1>SIMAK System Test</h1>
    <p>Testing semua komponen sistem...</p>";

$tests_passed = 0;
$tests_failed = 0;

echo "<div class='test-section'>";
echo "<h3>1. Database Connection</h3>";

try {
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "<p class='pass'>Database connection successful</p>";
        $tests_passed++;
    } else {
        echo "<p class='fail'>Database connection failed</p>";
        $tests_failed++;
    }
} catch (PDOException $e) {
    echo "<p class='fail'>Database error: " . $e->getMessage() . "</p>";
    $tests_failed++;
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>2. Database Tables</h3>";

$tables = ['users', 'mahasiswa', 'dosen', 'courses', 'irs', 'presensi', 'presensi_sessions', 'tasks', 'task_submissions', 'grades', 'announcements', 'announcement_reads'];
$table_results = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $table_results[$table] = '';
        } else {
            $table_results[$table] = '';
        }
    } catch (PDOException $e) {
        $table_results[$table] = '';
    }
}

echo "<table>";
echo "<tr><th>Table</th><th>Status</th></tr>";
foreach ($table_results as $table => $status) {
    $class = $status === '' ? 'pass' : ($status === '' ? 'fail' : 'info');
    echo "<tr><td>$table</td><td class='$class'>$status</td></tr>";
}
echo "</table>";

$all_exist = !in_array('', $table_results);
if ($all_exist) {
    $tests_passed++;
} else {
    $tests_failed++;
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>3. Data Count</h3>";

$data_counts = [];
$data_counts['users'] = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$data_counts['mahasiswa'] = $pdo->query("SELECT COUNT(*) as total FROM mahasiswa")->fetch()['total'];
$data_counts['dosen'] = $pdo->query("SELECT COUNT(*) as total FROM dosen")->fetch()['total'];
$data_counts['courses'] = $pdo->query("SELECT COUNT(*) as total FROM courses")->fetch()['total'];

echo "<table>";
echo "<tr><th>Table</th><th>Total Records</th></tr>";
foreach ($data_counts as $table => $count) {
    echo "<tr><td>$table</td><td>$count</td></tr>";
}
echo "</table>";

if (array_sum($data_counts) > 0) {
    $tests_passed++;
} else {
    $tests_failed++;
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>4. Session & Authentication</h3>";

if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='pass'>Session is active</p>";
    $tests_passed++;
} else {
    echo "<p class='fail'>Session is not active</p>";
    $tests_failed++;
}

if (isLoggedIn()) {
    echo "<p class='pass'>User is logged in as: " . htmlspecialchars($_SESSION['username']) . " (" . htmlspecialchars($_SESSION['role']) . ")</p>";
    $tests_passed++;
} else {
    echo "<p class='info'>No user logged in (testing mode)</p>";
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>5. File Structure</h3>";

$directories = [
    '/../config/',
    '/../controllers/',
    '/../includes/',
    '/public/assets/css/',
    '/public/assets/js/',
    '/public/admin/',
    '/public/dosen/',
    '/public/mahasiswa/'
];

$dir_results = [];
foreach ($directories as $dir) {
    $path = __DIR__ . $dir;
    if (is_dir($path)) {
        $dir_results[$dir] = '';
    } else {
        $dir_results[$dir] = '';
    }
}

echo "<table>";
echo "<tr><th>Directory</th><th>Status</th></tr>";
foreach ($dir_results as $dir => $status) {
    $class = $status === '' ? 'pass' : 'fail';
    echo "<tr><td>$dir</td><td class='$class'>$status</td></tr>";
}
echo "</table>";

$all_exist = !in_array('', $dir_results);
if ($all_exist) {
    $tests_passed++;
} else {
    $tests_failed++;
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>6. Core Files</h3>";

$files = [
    '../config/database.php',
    '../config/session.php',
    '../controllers/AdminController.php',
    '../controllers/DosenController.php',
    '../controllers/MahasiswaController.php',
    '../includes/header.php',
    '../includes/footer.php',
    'index.php',
    'logout.php',
    'assets/css/base.css',
    'assets/js/app.js'
];

$file_results = [];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $file_results[$file] = '';
    } else {
        $file_results[$file] = '';
    }
}

echo "<table>";
echo "<tr><th>File</th><th>Status</th></tr>";
foreach ($file_results as $file => $status) {
    $class = $status === '' ? 'pass' : 'fail';
    echo "<tr><td>$file</td><td class='$class'>$status</td></tr>";
}
echo "</table>";

$all_exist = !in_array('', $file_results);
if ($all_exist) {
    $tests_passed++;
} else {
    $tests_failed++;
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>7. PHP Configuration</h3>";

$configs = [
    'PHP Version' => phpversion(),
    'PDO Enabled' => extension_loaded('pdo_mysql') ? '' : '',
    'Session Enabled' => extension_loaded('session') ? '' : '',
    'Display Errors' => ini_get('display_errors') ? ' (Enabled - Set to Off in Production)' : '',
];

echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
foreach ($configs as $key => $value) {
    $class = strpos($value, '') !== false ? 'pass' : (strpos($value, '') !== false ? 'fail' : 'info');
    echo "<tr><td>$key</td><td class='$class'>$value</td></tr>";
}
echo "</table>";

$php_ok = extension_loaded('pdo_mysql') && extension_loaded('session');
if ($php_ok) {
    $tests_passed++;
} else {
    $tests_failed++;
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h3>8. Security Check</h3>";

$security_checks = [];

$session_cookie_secure = ini_get('session.cookie_secure');
$security_checks['Session Cookie Secure'] = $session_cookie_secure ? ' (Enabled)' : ' (Not Set - Enable in Production)';

$display_errors = ini_get('display_errors');
$security_checks['Display Errors'] = $display_errors ? ' (Enabled - Disable in Production)' : '';

$config_path = __DIR__ . '/../config/';
$security_checks['Config outside public'] = is_dir($config_path) ? '' : '';

echo "<table>";
echo "<tr><th>Check</th><th>Status</th></tr>";
foreach ($security_checks as $key => $value) {
    $class = strpos($value, '') !== false ? 'pass' : (strpos($value, '') !== false ? 'fail' : 'info');
    echo "<tr><td>$key</td><td class='$class'>$value</td></tr>";
}
echo "</table>";

echo "</div>";

$total_tests = $tests_passed + $tests_failed;
$percentage = ($tests_passed / $total_tests) * 100;

echo "<div class='test-section' style='border: 2px solid " . ($percentage >= 80 ? '#2ecc71' : '#e74c3c') . ";'>";
echo "<h3>Test Summary</h3>";
echo "<p><strong>Tests Passed:</strong> <span class='pass'>$tests_passed</span></p>";
echo "<p><strong>Tests Failed:</strong> <span class='fail'>$tests_failed</span></p>";
echo "<p><strong>Total Tests:</strong> $total_tests</p>";
echo "<p><strong>Pass Rate:</strong> <span class='" . ($percentage >= 80 ? 'pass' : 'fail') . "'>" . number_format($percentage, 1) . "%</span></p>";

if ($percentage >= 80) {
    echo "<p class='pass' style='font-size: 18px;'>System is ready for production!</p>";
} else {
    echo "<p class='fail' style='font-size: 18px;'>Some issues need to be fixed.</p>";
}
echo "</div>";

echo "<p style='margin-top: 20px; color: #7f8c8d;'>Test completed at: " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='/simak_app/public/admin/dashboard.php'>Back to Dashboard</a></p>";

echo "</body></html>";
?>