<?php
// upgrade_v2.php
$host = "localhost";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, "bapenda_kobar_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Alter Users Table
$sql_alter_users1 = "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER username";
$conn->query($sql_alter_users1); // Ignore if exists
$sql_alter_users2 = "ALTER TABLE users ADD COLUMN email VARCHAR(100) AFTER full_name";
$conn->query($sql_alter_users2);
$sql_update_admin = "UPDATE users SET full_name = 'Super Admin', role = 'super_admin' WHERE username = 'admin'";
$conn->query($sql_update_admin);

// 2. Create Agenda Table
$sql_agenda = "CREATE TABLE IF NOT EXISTS agenda (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    location VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_agenda) === TRUE) {
    echo "Table 'agenda' is ready.<br>";
}

// Insert Sample Agenda
$conn->query("INSERT IGNORE INTO agenda (id, title, description, event_date, location) VALUES (1, 'Jatuh Tempo Pembayaran PBB', 'Batas akhir penyetoran Pajak Bumi dan Bangunan tahun berjalan bagi seluruh wajib pajak daerah.', '2026-09-30 23:59:00', 'Bank Kalteng / Loket Bapenda')");

// 3. Create Pengaduan Table
$sql_pengaduan = "CREATE TABLE IF NOT EXISTS pengaduan (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NULL,
    nik VARCHAR(20) NULL,
    phone VARCHAR(20) NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'processed', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_pengaduan) === TRUE) {
    echo "Table 'pengaduan' is ready.<br>";
}

echo "Database successfully upgraded to V2!";
$conn->close();
?>
