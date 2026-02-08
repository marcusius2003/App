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

$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$current_user_id = (int) $currentUser['id'];

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket invÃ¡lido']);
    exit;
}

try {
    // 1. Obtener info del ticket para verificar propiedad y status
    $stmtTicket = $pdo->prepare("SELECT id, user_id, status FROM tickets WHERE id = ?");
    $stmtTicket->execute([$ticket_id]);
    $ticket = $stmtTicket->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
        exit;
    }

    // Verificar permiso (solo el dueÃ±o puede ver)
    if ($ticket['user_id'] != $current_user_id) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    // 2. Buscar mensajes nuevos desde last_id
    // is_internal = 0 (solo pÃºblicos)
    $stmtMsgs = $pdo->prepare("
        SELECT * 
        FROM ticket_responses 
        WHERE ticket_id = ? 
          AND id > ? 
          AND is_internal = 0 
        ORDER BY created_at ASC
    ");
    $stmtMsgs->execute([$ticket_id, $last_id]);
    $newMessages = $stmtMsgs->fetchAll();

    $formattedMessages = [];
    foreach ($newMessages as $msg) {
        // Determinar sender
        $isClient = ($msg['user_id'] == $ticket['user_id']);
        $sender = $isClient ? 'cliente' : 'agente';

        $formattedMessages[] = [
            'id' => $msg['id'],
            'message' => $msg['message'],
            'created_at' => $msg['created_at'],
            'sender' => $sender,
            'user_id' => $msg['user_id']
        ];
    }

    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'ticket_status' => $ticket['status']
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

