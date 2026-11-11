<?php
// Salin file ini menjadi config.php dan sesuaikan kredensial database Anda
date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$user = "db_user";
$pass = "db_password";
$dbname = "db_name";

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(503);
    die("Layanan database sedang tidak tersedia.");
}

// Select database, suppress error if it doesn't exist yet (useful during setup)
@$conn->select_db($dbname);

// Konfigurasi koneksi realtime PostgreSQL SINPELAJA (read-only untuk Dashboard Pajak)
// Ubah 'enabled' => true dan isi kredensial server SINPELAJA jika ekstensi PHP pdo_pgsql aktif.
$sinpelaja_config = [
    'enabled'      => false,
    'host'         => '127.0.0.1',
    'port'         => 5432,
    'dbname'       => 'sinpelaja_db',
    'user'         => 'sinpelaja_readonly',
    'password'     => 'sinpelaja_password',
    'sslmode'      => 'prefer',
    'timeout'      => 6,
    'statement_ms' => 8000,
];
?>

