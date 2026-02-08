<?php
require_once __DIR__ . '/admin/config/db.php';
$database = new Database();
$pdo = $database->getConnection();

echo "=== USERS TABLE ===\n";
try {
    $stmt = $pdo->query("DESCRIBE users");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "{$col['Field']} ({$col['Type']}) - Null: {$col['Null']}\n";
    }
} catch (Exception $e) { echo $e->getMessage(); }

echo "\n=== ROLES SAMPLE ===\n";
try {
    $stmt = $pdo->query("SELECT * FROM roles LIMIT 10");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        print_r($row);
    }
} catch (Exception $e) { echo $e->getMessage(); }
?>
