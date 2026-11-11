<?php
// Setup DB script
$host = "localhost";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create DB
$sql = "CREATE DATABASE IF NOT EXISTS bapenda_kobar_db";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

$conn->select_db("bapenda_kobar_db");

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "Table users created successfully<br>";
}

// Insert default admin (username: admin, password: admin123)
$hash = password_hash("admin123", PASSWORD_DEFAULT);
$sql = "INSERT IGNORE INTO users (id, username, password_hash) VALUES (1, 'admin', '$hash')";
$conn->query($sql);

// Create settings table
$sql = "CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Table settings created successfully<br>";
}

// Insert default settings
$default_settings = [
    'hero_tagline' => 'BADAN PENDAPATAN DAERAH',
    'hero_title' => 'Melayani dengan Integritas,<br>Membangun Daerah Bersama',
    'hero_desc' => 'Optimalisasi pendapatan daerah melalui pelayanan publik yang transparan, akuntabel, dan berbasis teknologi.',
    'hero_cta1_text' => 'Hubungi Kami',
    'hero_cta1_link' => '#kontak',
    'hero_cta2_text' => 'Tentang Kami',
    'hero_cta2_link' => '#tentang',
    'hero_bg' => 'assets/img/hero-bg.png'
];

foreach($default_settings as $key => $val) {
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $val);
    $stmt->execute();
}

// Create links table
$sql = "CREATE TABLE IF NOT EXISTS links (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon_class VARCHAR(50) DEFAULT 'fas fa-link',
    order_num INT(3) DEFAULT 0
)";
if ($conn->query($sql) === TRUE) {
    echo "Table links created successfully<br>";
}

// Insert sample links
$default_links = [
    ['Portal Pemkab Kobar', 'https://kotawaringinbaratkab.go.id/', 'fas fa-globe', 1],
    ['Layanan e-SPTPD', '#', 'fas fa-laptop-house', 2],
    ['Informasi Jadwal PBB', '#', 'fas fa-calendar-check', 3],
    ['Lapor Pajak Online', '#', 'fas fa-file-invoice-dollar', 4]
];
foreach($default_links as $link) {
    $stmt = $conn->prepare("INSERT IGNORE INTO links (title, url, icon_class, order_num) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $link[0], $link[1], $link[2], $link[3]);
    $stmt->execute();
}

// Create news table
$sql = "CREATE TABLE IF NOT EXISTS news (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    snippet TEXT,
    content TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "Table news created successfully<br>";
}

// Insert sample news
$sample_news = [
    ['Jatuh Tempo Pembayaran PBB', 'Segera lakukan pembayaran PBB sebelum jatuh tempo bulan ini. Hindari denda keterlambatan.', '<p>Detail berita panjang jatuh tempo pembayaran pajak bumi dan bangunan...</p>', 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500&q=80'],
    ['Peningkatan Layanan SmartTax Kobar', 'Bapenda Kobar meluncurkan pembaruan untuk sistem pintar perpajakan daerah yang lebih cepat.', '<p>Detail peluncuran sistem baru...</p>', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=500&q=80'],
    ['Capaian Target Pendapatan Daerah', 'Pendapatan daerah Kotawaringin Barat tahun ini mencapai target lebih awal berkat partisipasi masyarakat.', '<p>Laporan pencapaian pendapatan daerah...</p>', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&q=80']
];
foreach($sample_news as $news) {
    $stmt = $conn->prepare("INSERT IGNORE INTO news (title, snippet, content, image_url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $news[0], $news[1], $news[2], $news[3]);
    $stmt->execute();
}

// Create services table
$sql = "CREATE TABLE IF NOT EXISTS services (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-file',
    url VARCHAR(255) DEFAULT '#',
    order_num INT(3) DEFAULT 0
)";
if ($conn->query($sql) === TRUE) {
    echo "Table services created successfully<br>";
}

// Insert sample services
$sample_services = [
    ['E-SPTPD', 'Elektronik SPTPD untuk transaksi online', 'fas fa-file-invoice', '#', 1],
    ['e-SPPT PBB', 'Pengecekan SPPT secara Online', 'fas fa-building', '#', 2],
    ['LAKONE', 'Layanan Pajak Daerah Online', 'fas fa-laptop', '#', 3],
    ['E-Sumpah PBB', 'Unggah mandiri pelayanan PBB', 'fas fa-handshake', '#', 4],
    ['e-PBB', 'Cek Tagihan dan Pembayaran PBB via QRIS', 'fas fa-receipt', '#', 5],
    ['e-BPHTB', 'Sistem digital BPHTB daerah', 'fas fa-home', '#', 6]
];
foreach($sample_services as $svc) {
    $stmt = $conn->prepare("INSERT IGNORE INTO services (title, description, icon, url, order_num) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $svc[0], $svc[1], $svc[2], $svc[3], $svc[4]);
    $stmt->execute();
}

echo "Database Setup Complete (Termasuk Berita, Tautan & Layanan)! You can now <a href='admin/login.php'>Login</a> using username: <b>admin</b> and password: <b>admin123</b>.<br>";
echo "Please delete setup_db.php for security reasons.";
?>
