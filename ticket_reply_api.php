<?php
session_start();

/*
 * CONEXIÃ“N DIRECTA A LA BASE DE DATOS
 */
$host = 'localhost';
$db   = 'iuconect';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexiÃ³n']);
    exit;
}

require_once __DIR__ . '/includes/auth_guard.php';
$currentUser = requireActiveUser($pdo, ['response' => 'json']);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'MÃ©todo no permitido']);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$user_id = (int) $currentUser['id'];

if ($ticket_id <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Datos invÃ¡lidos']);
    exit;
}

try {
    // 1. Verificar que el ticket pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado o acceso denegado']);
        exit;
    }

    // 2. Insertar respuesta en ticket_responses
    // is_internal = 0 (pÃºblico)
    $stmt = $pdo->prepare("
        INSERT INTO ticket_responses (ticket_id, user_id, message, is_internal, created_at) 
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$ticket_id, $user_id, $message]);

    // 3. Manejo de adjuntos (bÃ¡sico)
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        // AquÃ­ irÃ­a la lÃ³gica de subida, por ahora lo dejamos pendiente o simple
        // Si la tabla ticket_attachments existe, podrÃ­amos insertar
    }
    
    // 4. Actualizar estado del ticket (opcional, ej: ponerlo en 'abierto' si estaba en espera)
    // $pdo->prepare("UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = ?")->execute([$ticket_id]);

    echo json_encode(['success' => true, 'message' => 'Respuesta enviada']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

