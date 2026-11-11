<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';

require_permission('menu_report_pengaduan');

$base_dir = __DIR__ . '/../assets/uploads/pengaduan';
bapenda_ensure_upload_dir($base_dir, true);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}

$stmt = $conn->prepare("SELECT identity_file FROM pengaduan WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    exit('Gagal memuat berkas.');
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

$raw_file = trim((string)($row['identity_file'] ?? ''));
$filename = basename(str_replace('\\', '/', $raw_file));
if ($raw_file === '' || $filename === '' || $filename === '.' || $filename === '..') {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}

$real_base = realpath($base_dir);
$candidate = $real_base !== false ? $real_base . DIRECTORY_SEPARATOR . $filename : '';
$real_file = ($candidate !== '' && !is_link($candidate)) ? realpath($candidate) : false;

$in_base = false;
if ($real_base !== false && $real_file !== false && is_file($real_file)) {
    $prefix = rtrim($real_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $in_base = (DIRECTORY_SEPARATOR === '\\')
        ? (stripos($real_file, $prefix) === 0)
        : (strpos($real_file, $prefix) === 0);
}

if (!$in_base) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $real_file);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

$inline_mimes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'application/pdf',
];

$safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
if ($safe_name === '' || $safe_name === null) {
    $safe_name = 'identitas_pengadu';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

if (in_array($mime, $inline_mimes, true)) {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $safe_name . '"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $safe_name . '"');
}

$size = @filesize($real_file);
if ($size !== false) {
    header('Content-Length: ' . $size);
}

readfile($real_file);
exit;

