<?php
/**
 * Script to fix and populate the tickets table
 */

require_once 'admin/config/db.php';

$database = new Database();
$conn = $database->getConnection();

echo "<pre style='font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; font-size: 14px;'>";

// 1. Drop and recreate tickets table to ensure correct structure
echo "🔧 Reconstruyendo tabla tickets...\n";

try {
    $conn->exec("DROP TABLE IF EXISTS ticket_responses");
    $conn->exec("DROP TABLE IF EXISTS tickets");
    echo "   ✅ Tablas antiguas eliminadas\n";
} catch (PDOException $e) {
    echo "   ⚠️ " . $e->getMessage() . "\n";
}

// Create tickets table with correct structure including 'waiting_response'
$sql = "CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'normal', 'medium', 'high') DEFAULT 'normal',
    category VARCHAR(100) DEFAULT 'general',
    status VARCHAR(50) DEFAULT 'open',
    user_id INT NULL,
    requester_email VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->exec($sql);
echo "   ✅ Tabla 'tickets' creada\n";

// Create ticket_responses table
$sql = "CREATE TABLE ticket_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    message TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->exec($sql);
echo "   ✅ Tabla 'ticket_responses' creada\n\n";

// 2. Insert sample tickets
echo "📝 Insertando tickets de ejemplo...\n";

$sampleTickets = [
    ['Error al iniciar sesión', 'Los usuarios no pueden acceder. Error 500.', 'high', 'technical', 'open', 'usuario1@academia.edu'],
    ['Problema con notificaciones', 'No llegan los emails de notificación.', 'high', 'technical', 'open', 'admin@colegio.com'],
    ['Solicitud: Exportar PDF', 'Queremos exportar reportes a PDF.', 'medium', 'feature_request', 'waiting_response', 'director@instituto.org'],
    ['Pregunta sobre facturación', '¿Cuándo se cobra el siguiente mes?', 'normal', 'billing', 'waiting_response', 'contabilidad@academia.edu'],
    ['Calendario no funciona', 'Los eventos recurrentes no aparecen.', 'high', 'technical', 'in_progress', 'profesor@colegio.com'],
    ['Integración Google Classroom', '¿Podemos sincronizar con Google?', 'medium', 'feature_request', 'in_progress', 'tech@escuela.edu'],
    ['Bug en el chat interno', 'Los mensajes no se envían a veces.', 'medium', 'technical', 'in_progress', 'soporte@academia.edu'],
    ['Gracias por el soporte', 'Excelente servicio, todo resuelto!', 'low', 'general', 'closed', 'feliz@cliente.com'],
    ['Problema resuelto', 'Ya funciona correctamente.', 'normal', 'technical', 'closed', 'usuario2@colegio.com'],
];

$stmt = $conn->prepare("INSERT INTO tickets (subject, description, priority, category, status, requester_email, created_at) VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY))");

foreach ($sampleTickets as $t) {
    $stmt->execute($t);
    echo "   ✅ Ticket: {$t[0]}\n";
}

echo "\n📊 Resumen:\n";
$stats = $conn->query("SELECT status, COUNT(*) as c FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($stats as $status => $count) {
    echo "   - $status: $count\n";
}

echo "\n✅ ¡LISTO! Ahora ve a:\n";
echo "   <a href='admin_panel.php?page=support&view=kanban' style='color: cyan;'>→ Ver Kanban</a>\n";
echo "</pre>";
echo "<br><a href='admin_panel.php?page=support&view=kanban' style='padding: 10px 20px; background: black; color: white; text-decoration: none; border-radius: 5px;'>Ir a Tickets (Kanban) →</a>";
?>
