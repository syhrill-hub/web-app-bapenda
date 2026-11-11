<?php
require_once 'config.php';

$messages = [];
$errors = [];

// Create pajak_jenis table
$sql = "CREATE TABLE IF NOT EXISTS `pajak_jenis` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode` VARCHAR(30) NOT NULL,
    `nama` VARCHAR(255) NOT NULL,
    `urutan` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "✅ Tabel <b>pajak_jenis</b> berhasil dibuat / sudah ada.";
} else {
    $errors[] = "❌ pajak_jenis: " . $conn->error;
}

// Create pajak_target table
$sql = "CREATE TABLE IF NOT EXISTS `pajak_target` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `jenis_id` INT NOT NULL,
    `tahun` YEAR NOT NULL,
    `target_amount` DECIMAL(18,2) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_target` (`jenis_id`, `tahun`),
    FOREIGN KEY (`jenis_id`) REFERENCES `pajak_jenis`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "✅ Tabel <b>pajak_target</b> berhasil dibuat / sudah ada.";
} else {
    $errors[] = "❌ pajak_target: " . $conn->error;
}

// Create pajak_realisasi table
$sql = "CREATE TABLE IF NOT EXISTS `pajak_realisasi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `jenis_id` INT NOT NULL,
    `tahun` YEAR NOT NULL,
    `bulan` TINYINT NOT NULL COMMENT '1=Januari ... 12=Desember',
    `realisasi_amount` DECIMAL(18,2) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_realisasi` (`jenis_id`, `tahun`, `bulan`),
    FOREIGN KEY (`jenis_id`) REFERENCES `pajak_jenis`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "✅ Tabel <b>pajak_realisasi</b> berhasil dibuat / sudah ada.";
} else {
    $errors[] = "❌ pajak_realisasi: " . $conn->error;
}

// Seed pajak_jenis (INSERT IGNORE to avoid duplicates)
$jenis_pajak = [
    ['kode' => '4.1.01.09',       'nama' => 'PAJAK REKLAME',                                             'urutan' => 1],
    ['kode' => '4.1.01.12',       'nama' => 'PAJAK AIR TANAH',                                           'urutan' => 2],
    ['kode' => '4.1.01.13',       'nama' => 'PAJAK SARANG BURUNG WALET',                                 'urutan' => 3],
    ['kode' => '4.1.01.14',       'nama' => 'PAJAK MINERAL BUKAN LOGAM DAN BATUAN',                      'urutan' => 4],
    ['kode' => '4.1.01.15',       'nama' => 'PAJAK BUMI DAN BANGUNAN PERDESAAN DAN PERKOTAAN (PBBP2)',   'urutan' => 5],
    ['kode' => '4.1.01.16',       'nama' => 'BEA PEROLEHAN HAK ATAS TANAH DAN BANGUNAN (BPHTB)',         'urutan' => 6],
    ['kode' => '4.1.01.19.01',    'nama' => 'PBJT-MAKANAN DAN/ATAU MINUMAN',                             'urutan' => 7],
    ['kode' => '4.1.01.19.02',    'nama' => 'PBJT-TENAGA LISTRIK',                                       'urutan' => 8],
    ['kode' => '4.1.01.19.03',    'nama' => 'PBJT-JASA PERHOTELAN',                                      'urutan' => 9],
    ['kode' => '4.1.01.19.04',    'nama' => 'PBJT-JASA PARKIR',                                          'urutan' => 10],
    ['kode' => '4.1.01.19.05',    'nama' => 'PBJT-JASA KESENIAN DAN HIBURAN',                            'urutan' => 11],
    ['kode' => '4.1.01.20',       'nama' => 'OPSEN PAJAK KENDARAAN BERMOTOR (PKB)',                       'urutan' => 12],
    ['kode' => '4.1.01.21',       'nama' => 'OPSEN BEA BALIK NAMA KENDARAAN BERMOTOR (BBNKB)',            'urutan' => 13],
];

$stmt = $conn->prepare("INSERT IGNORE INTO pajak_jenis (kode, nama, urutan) VALUES (?, ?, ?)");
if ($stmt) {
    $seeded = 0;
    foreach ($jenis_pajak as $j) {
        $stmt->bind_param('ssi', $j['kode'], $j['nama'], $j['urutan']);
        if ($stmt->execute()) $seeded++;
    }
    $stmt->close();
    $messages[] = "✅ Seed data <b>pajak_jenis</b>: {$seeded} dari " . count($jenis_pajak) . " jenis pajak dimasukkan (data yang sudah ada dilewati).";
} else {
    $errors[] = "❌ Gagal menyiapkan statement seed: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setup Pajak Dashboard</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 60px auto; background: #f8fafc; }
        h1 { color: #0ea5e9; }
        .msg { background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin: 8px 0; border-left: 4px solid #10b981; }
        .err { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin: 8px 0; border-left: 4px solid #ef4444; }
        a.btn { display: inline-block; margin-top: 20px; background: #0ea5e9; color: #fff; padding: 10px 22px; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    <h1>🛠️ Setup Database Pajak Dashboard</h1>
    <?php foreach ($messages as $m): ?>
        <div class="msg"><?php echo $m; ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="err"><?php echo $e; ?></div>
    <?php endforeach; ?>
    <?php if (empty($errors)): ?>
        <p style="color:#065f46;font-weight:600;">✅ Setup selesai! Semua tabel dan data dasar berhasil disiapkan.</p>
        <a class="btn" href="pajak_dashboard.php">🔗 Buka Dashboard Pajak</a>
        <a class="btn" style="background:#64748b;margin-left:10px;" href="admin/pajak_target.php">⚙️ Admin: Target Pajak</a>
    <?php endif; ?>
</body>
</html>
