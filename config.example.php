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
    die("Connection failed: " . $conn->connect_error);
}

// Select database, suppress error if it doesn't exist yet (useful during setup)
@$conn->select_db($dbname);
?>

