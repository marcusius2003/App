<?php
$host = 'localhost';
$db   = 'iuconect';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✅ Conexión exitosa<br>";
    
    // Verificar tabla users
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'users' existe<br>";
        
        // Mostrar algunos usuarios
        $users = $pdo->query("SELECT email FROM users LIMIT 5")->fetchAll();
        echo "Usuarios encontrados: " . count($users) . "<br>";
        foreach ($users as $user) {
            echo "- " . $user['email'] . "<br>";
        }
    } else {
        echo "❌ Tabla 'users' NO existe<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>