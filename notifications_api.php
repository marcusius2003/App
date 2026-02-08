<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

$currentUser = requireActiveUser($pdo, ['response' => 'json']);
$userId = (int) $currentUser['id'];

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
$limit = max(1, min($limit, 20));

try {
    $hasLinkColumn = notificationsLinkColumnExists($pdo);
    $fields = "id, message, is_read, created_at";
    if ($hasLinkColumn) {
        $fields .= ", link_url";
    }

    $sql = "
        SELECT {$fields}
        FROM notifications
        WHERE user_id = :user_id
          AND (is_read = 0 OR is_read IS NULL)
        ORDER BY created_at DESC
        LIMIT :limit
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];
    foreach ($rows as $row) {
        $notifications[] = [
            'id' => (int) $row['id'],
            'message' => (string) $row['message'],
            'created_at' => (string) $row['created_at'],
            'is_read' => (bool) $row['is_read'],
            'link_url' => $hasLinkColumn ? ($row['link_url'] ?? '') : '',
        ];
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
          AND (is_read = 0 OR is_read IS NULL)
    ");
    $countStmt->execute([':user_id' => $userId]);
    $totalUnread = (int) $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total_unread' => $totalUnread,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudieron recuperar las notificaciones.',
    ]);
}

function notificationsLinkColumnExists(PDO $pdo): bool
{
    static $checked = false;
    static $hasColumn = false;
    if ($checked) {
        return $hasColumn;
    }
    $checked = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'link_url'");
        $hasColumn = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        $hasColumn = false;
    }
    return $hasColumn;
}
