<?php
// One-time utility: reset login rate limit on all admin users
require_once 'config.php';
$result = $conn->query("UPDATE users SET failed_attempts = 0, last_failed_at = NULL WHERE 1");
if ($result) {
    echo "<p style='color:green;font-family:sans-serif;font-size:16px;'>✅ Login rate limit direset untuk semua user. Silakan <a href='admin/login.php'>login kembali</a>.</p>";
    @unlink(__FILE__);
    echo "<p style='color:#888;font-family:sans-serif;font-size:13px;'>File ini telah terhapus otomatis.</p>";
} else {
    echo "<p style='color:red;font-family:sans-serif;'>❌ Gagal: " . $conn->error . "</p>";
}
?>
