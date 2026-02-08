<?php
// Script para listar SOLO las tablas que existen en tu base de datos
require_once __DIR__ . '/includes/db.php';

echo "<h1>TABLAS EXISTENTES EN TU BASE DE DATOS</h1>";

// Mostrar nombre de la base de datos
$db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "<h2>Base de datos: <strong style='color: blue;'>$db_name</strong></h2>";

// Listar TODAS las tablas
echo "<h3>Lista de todas las tablas:</h3>";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    echo "<p style='color: red;'>No hay tablas en esta base de datos.</p>";
} else {
    echo "<ul style='font-size: 16px;'>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
    echo "<p><strong>Total de tablas: " . count($tables) . "</strong></p>";
}

echo "<hr>";

// Verificar tablas específicas que necesito
echo "<h3>Verificación de tablas necesarias para Exámenes y Tareas:</h3>";

$needed_tables = ['assignments', 'submissions', 'users'];

foreach ($needed_tables as $table_name) {
    if (in_array($table_name, $tables)) {
        echo "<p style='color: green;'>✓ Tabla '<strong>$table_name</strong>' EXISTE</p>";
        
        // Mostrar estructura de la tabla
        echo "<details><summary>Ver estructura de $table_name</summary>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Por defecto</th><th>Extra</th></tr>";
        
        $columns = $pdo->query("DESCRIBE $table_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . $col['Field'] . "</strong></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $col['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</details>";
    } else {
        echo "<p style='color: red;'>✗ Tabla '<strong>$table_name</strong>' NO EXISTE - Necesito crearla</p>";
    }
}
?>

