<?php
require_once __DIR__ . '/admin/config/db.php';
$database = new Database();
$pdo = $database->getConnection();

echo "Checking features table...\n";
try {
    $stmt = $pdo->query("DESCRIBE features");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Table features does not exist or error: " . $e->getMessage() . "\n";
}
?>
