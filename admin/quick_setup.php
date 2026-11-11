<?php
require_once '../config.php';

// Reset admin lockout
$conn->query("UPDATE users SET failed_attempts = 0, last_failed_at = NULL WHERE username = 'admin'");
echo "Admin lockout reset.\n";

// Create visitor_logs table
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
    echo "Table visitor_logs created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
