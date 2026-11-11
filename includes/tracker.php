<?php
// includes/tracker.php
require_once __DIR__ . '/security_helpers.php';
bapenda_start_secure_session();

// Pastikan konfigurasi database sudah diinclude sebelum ini
// (biasanya di header.php sudah ada require_once 'config.php')
if (!isset($conn)) {
    return; // Keluar jika tidak ada koneksi DB
}

// Inisialisasi session ID unik kriptografis untuk pelacakan jika belum ada
if (empty($_SESSION['visitor_session_id'])) {
    $_SESSION['visitor_session_id'] = 'v_' . bin2hex(random_bytes(16));
}
$session_id = $_SESSION['visitor_session_id'];

// Dapatkan IP address dari REMOTE_ADDR yang valid (hindari header spoof HTTP_CLIENT_IP / XFF mentah)
$raw_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip_address = filter_var($raw_ip, FILTER_VALIDATE_IP) ? $raw_ip : '0.0.0.0';

// Dapatkan URL saat ini
$page_url = $_SERVER['REQUEST_URI'];
$base_name = basename(parse_url($page_url, PHP_URL_PATH));

// Mapping otomatis nama halaman yang rapi
if (empty($base_name) || $base_name == 'index.php') {
    $page_name = "Beranda";
} else {
    $mapping = [
        'profil.php' => 'Profil Instansi',
        'layanan.php' => 'Layanan Utama',
        'berita.php' => 'Berita & Artikel',
        'ppid.php' => 'PPID & Informasi Publik',
        'dokumen.php' => 'Dokumen & Peraturan',
        'galeri.php' => 'Galeri Kegiatan',
        'faq.php' => 'FAQ / Bantuan',
        'kontak.php' => 'Hubungi Kami',
        'pajak_dashboard.php' => 'Dashboard Pajak Real-Time',
        'pajak_setup.php' => 'Setup DB Pajak',
        'login.php' => 'Login Admin'
    ];
    if (isset($mapping[$base_name])) {
        $page_name = $mapping[$base_name];
    } else {
        // Fallback: hapus .php dan jadikan Title Case
        $clean_name = str_replace(['.php', '-', '_'], ['', ' ', ' '], $base_name);
        $page_name = ucwords(trim($clean_name));
    }
}

// Karena kita akan menyimpan IP address sebagai string
$ip_address = substr($ip_address, 0, 45); 
$page_url = substr($page_url, 0, 255);
$page_name = substr($page_name, 0, 100);

$visit_date = date('Y-m-d');
$visit_time = date('H:i:s');

// Cek apakah session ini sudah mengunjungi halaman ini hari ini
// Tujuannya meminimalisir spam refresh page yang berulang
$stmt_check = $conn->prepare("SELECT id FROM visitor_logs WHERE session_id = ? AND page_url = ? AND visit_date = ?");
if ($stmt_check) {
    $stmt_check->bind_param("sss", $session_id, $page_url, $visit_date);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    // Jika belum pernah dikunjungi di session hari ini, insert log baru
    if ($result_check->num_rows === 0) {
        $stmt_insert = $conn->prepare("INSERT INTO visitor_logs (ip_address, session_id, page_url, page_name, visit_date, visit_time) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_insert) {
            $stmt_insert->bind_param("ssssss", $ip_address, $session_id, $page_url, $page_name, $visit_date, $visit_time);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }
    $stmt_check->close();
}
?>
