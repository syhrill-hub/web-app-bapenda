<?php
require_once 'config.php';

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_attempts'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN failed_attempts INT(11) DEFAULT 0 AFTER password_hash");
    echo "Added failed_attempts column<br>";
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'last_failed_at'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN last_failed_at DATETIME DEFAULT NULL AFTER failed_attempts");
    echo "Added last_failed_at column<br>";
}

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER last_failed_at");
    echo "Added is_active column<br>";
}

echo "Migration completed successfully!";
