<?php
// Script para verificar el estado real de la base de datos
session_start();
require_once __DIR__ . '/includes/db.php';

echo "<h1>VERIFICACIÓN DE DATOS REALES</h1>";
echo "<style>
body { font-family: Arial; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #333; color: white; }
.error { color: red; }
.success { color: green; }
</style>";

// Ver todas las tareas
echo "<h2>TAREAS EXISTENTES EN LA BASE DE DATOS</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM assignments ORDER BY created_at DESC");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assignments)) {
        echo "<p class='error'>⚠️ NO HAY TAREAS EN LA BASE DE DATOS</p>";
    } else {
        echo "<p class='success'>✓ Se encontraron " . count($assignments) . " tareas</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Fecha límite</th><th>Estado</th><th>Creado</th></tr>";
        foreach ($assignments as $a) {
            echo "<tr>";
            echo "<td>" . $a['id'] . "</td>";
            echo "<td>" . htmlspecialchars($a['title']) . "</td>";
            echo "<td>" . $a['type'] . "</td>";
            echo "<td>" . $a['due_date'] . "</td>";
            echo "<td>" . $a['status'] . "</td>";
            echo "<td>" . $a['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>ERROR: " . $e->getMessage() . "</p>";
}

// Ver entregas
echo "<h2>ENTREGAS EXISTENTES</h2>";
try {
    $stmt = $pdo->query("SELECT s.*, u.username FROM submissions s JOIN users u ON s.student_id = u.id ORDER BY s.submitted_at DESC");
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($submissions)) {
        echo "<p class='error'>⚠️ NO HAY ENTREGAS EN LA BASE DE DATOS</p>";
    } else {
        echo "<p class='success'>✓ Se encontraron " . count($submissions) . " entregas</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Alumno</th><th>Tarea ID</th><th>Fecha</th><th>Calificación</th><th>Estado</th></tr>";
        foreach ($submissions as $s) {
            echo "<tr>";
            echo "<td>" . $s['id'] . "</td>";
            echo "<td>" . htmlspecialchars($s['username']) . "</td>";
            echo "<td>" . $s['assignment_id'] . "</td>";
            echo "<td>" . $s['submitted_at'] . "</td>";
            echo "<td>" . ($s['grade'] ?? 'Sin calificar') . "</td>";
            echo "<td>" . $s['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>ERROR: " . $e->getMessage() . "</p>";
}

// Verificar sesión
echo "<h2>SESIÓN ACTUAL</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='success'>✓ Usuario en sesión: ID = " . $_SESSION['user_id'] . "</p>";
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>Username</td><td>" . htmlspecialchars($user['username']) . "</td></tr>";
        echo "<tr><td>Role</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
        echo "<tr><td>Academy ID</td><td>" . ($user['academy_id'] ?? 'NULL') . "</td></tr>";
        echo "</table>";
    }
} else {
    echo "<p class='error'>⚠️ NO HAY SESIÓN ACTIVA</p>";
}

echo "<hr>";
echo "<h2>ACCIONES</h2>";
echo "<p><a href='entregas.php' style='padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px;'>Ir a Entregas</a></p>";
?>
