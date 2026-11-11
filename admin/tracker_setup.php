<?php
session_start();
require_once '../config.php';

// Cek autentikasi admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    $sql = "CREATE TABLE IF NOT EXISTS `visitor_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ip_address` varchar(45) NOT NULL,
      `session_id` varchar(100) NOT NULL,
      `page_url` varchar(255) NOT NULL,
      `page_name` varchar(100) DEFAULT NULL,
      `visit_date` date NOT NULL,
      `visit_time` time NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_visit_date` (`visit_date`),
      KEY `idx_session_page_date` (`session_id`, `page_url`, `visit_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql) === TRUE) {
        $message = "<div class='alert alert-success'>Tabel `visitor_logs` berhasil dibuat atau sudah ada.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error membuat tabel: " . $conn->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Tracker DB</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="font-family: 'Poppins', sans-serif; background: #f8fafc; padding: 50px;">
    <div class="container" style="max-width: 600px; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <h2 class="mb-4 text-center" style="font-weight: 700; color: #0ea5e9;">Setup Visitor Tracker DB</h2>
        <?php echo $message; ?>
        <p class="text-muted text-center">Klik tombol di bawah ini untuk membuat tabel <code>visitor_logs</code> ke dalam database. Tindakan ini hanya perlu dilakukan sekali.</p>
        <form method="POST" class="text-center mt-4">
            <button type="submit" name="setup" class="btn btn-primary px-4 py-2" style="border-radius: 50px; background: #0ea5e9; border: none; font-weight: 600;">Jalankan Migrasi SQL</button>
            <a href="index.php" class="btn btn-light px-4 py-2" style="border-radius: 50px; margin-left: 10px;">Kembali</a>
        </form>
    </div>
</body>
</html>
