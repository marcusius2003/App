<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=iuconnect', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking billing tables...\n\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'billing_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "❌ No billing tables found. Need to run migrations.\n";
    } else {
        echo "✅ Billing tables found:\n";
        foreach ($tables as $table) {
            echo "   - $table\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
