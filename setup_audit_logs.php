<?php
require_once __DIR__ . '/admin/config/db.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS access_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('success', 'failure') DEFAULT 'success',
        INDEX (user_id),
        INDEX (login_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($sql);
    echo "Table 'access_logs' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
