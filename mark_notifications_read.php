<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$host = 'localhost';
$db   = 'iuconect';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexiÃ³n']);
    exit;
}

require_once __DIR__ . '/includes/auth_guard.php';
$currentUser = requireActiveUser($pdo, ['response' => 'json']);
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = [];
}
$notificationId = isset($payload['notification_id']) ? (int) $payload['notification_id'] : 0;
$userId = (int) $currentUser['id'];

try {
    if ($notificationId > 0) {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = :notification_id AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId
        ]);
        echo json_encode([
            'success' => true,
            'cleared' => $stmt->rowCount() > 0
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = :user_id
          AND (is_read = 0 OR is_read IS NULL)
    ");
    $stmt->execute([':user_id' => $userId]);
    echo json_encode([
        'success' => true,
        'cleared' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar']);
}

