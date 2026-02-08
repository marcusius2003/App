<?php
require_once __DIR__ . '/admin/config/db.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
        start_date DATETIME,
        end_date DATETIME,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (active),
        INDEX (start_date),
        INDEX (end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($sql);
    echo "Table 'announcements' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
