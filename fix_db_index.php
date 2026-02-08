<?php
require_once __DIR__ . '/admin/config/db.php';
$database = new Database();
$pdo = $database->getConnection();

echo "Modifying users table indices...\n";

try {
    // 1. Drop existing global unique index
    // Note: The error message cited 'idx_users_email', but standard might be 'email'. 
    // We try to drop specific one first, or check info schema. 
    // Let's rely on the error message naming: 'idx_users_email'
    // But commonly in these setups it might be just 'email'.
    // Let's try to find it first.
    
    $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_email'");
    if ($stmt->fetch()) {
        $pdo->exec("ALTER TABLE users DROP INDEX idx_users_email");
        echo "Dropped index 'idx_users_email'.\n";
    } else {
        // Try 'email' just in case
        $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'email'");
        if ($stmt->fetch()) {
            $pdo->exec("ALTER TABLE users DROP INDEX email");
            echo "Dropped index 'email'.\n";
        } else {
             // Try 'unique_email'
            $stmt = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'unique_email'");
            if ($stmt->fetch()) {
                $pdo->exec("ALTER TABLE users DROP INDEX unique_email");
                echo "Dropped index 'unique_email'.\n";
            }
        }
    }

    // 2. Add composite unique index
    $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX idx_user_email_academy (email, academy_id)");
    echo "Added composite unique index 'idx_user_email_academy'.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
