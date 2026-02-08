<?php
session_start();
header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';

$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'edu.assignments', ['response' => 'json']);
$user_id = (int) $currentUser['id'];
$userRole = $currentUser['role'];
$academy_id = $currentUser['academy_id'];

// Verificar que es administrador
$normalizedRole = strtolower((string) $userRole);
$academyId = (int) $academy_id;
$isTenantAdmin = $academyId > 0 ? is_tenant_admin($pdo, $user_id, $academyId) : false;
$isAdmin = $isTenantAdmin || in_array($normalizedRole, ['admin', 'administrator', 'administrador', 'owner', 'teacher', 'profesor', 'instructor'], true);

if (!$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden crear tareas']);
    exit();
}

// Validar datos
$type = $_POST['type'] ?? 'tarea';
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$due_date = $_POST['due_date'] ?? '';
$max_grade = !empty($_POST['max_grade']) ? (float) $_POST['max_grade'] : null;

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'El título es obligatorio']);
    exit();
}

if (empty($due_date)) {
    echo json_encode(['success' => false, 'message' => 'La fecha de entrega es obligatoria']);
    exit();
}

if (!in_array($type, ['tarea', 'examen', 'practica'])) {
    $type = 'tarea';
}

try {
    // Insertar nueva tarea
    $sql = "
        INSERT INTO assignments (
            academy_id,
            created_by,
            title,
            type,
            description,
            due_date,
            max_grade,
            status,
            published_at,
            created_at
        ) VALUES (
            :academy_id,
            :created_by,
            :title,
            :type,
            :description,
            :due_date,
            :max_grade,
            'pending',
            NOW(),
            NOW()
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':academy_id' => $academy_id,
        ':created_by' => $user_id,
        ':title' => $title,
        ':type' => $type,
        ':description' => $description,
        ':due_date' => $due_date,
        ':max_grade' => $max_grade
    ]);
    
    $assignment_id = $pdo->lastInsertId();
    
    dispatchAssignmentNotifications($pdo, [
        'assignment_id' => (int) $assignment_id,
        'academy_id' => $academy_id,
        'title' => $title,
        'type' => $type,
        'creator_id' => $user_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tarea creada correctamente',
        'assignment_id' => $assignment_id
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la tarea: ' . $e->getMessage()
    ]);
}

function dispatchAssignmentNotifications(PDO $pdo, array $assignmentContext): void
{
    try {
        $assignmentId = isset($assignmentContext['assignment_id'])
            ? (int) $assignmentContext['assignment_id']
            : 0;
        $creatorId = isset($assignmentContext['creator_id'])
            ? (int) $assignmentContext['creator_id']
            : 0;
        $rawAcademyId = $assignmentContext['academy_id'] ?? null;
        $academyId = ($rawAcademyId === '' || $rawAcademyId === null)
            ? null
            : (int) $rawAcademyId;
        $title = trim((string) ($assignmentContext['title'] ?? ''));
        $type = strtolower((string) ($assignmentContext['type'] ?? 'tarea'));

        $message = buildAssignmentNotificationMessage($type, $title);
        if ($message === '') {
            return;
        }

        $sqlRecipients = "
            SELECT id
            FROM users
            WHERE status = 'active'
              AND id <> :creator_id
              AND LOWER(role) IN ('student','alumno','estudiante')
        ";
        $params = [':creator_id' => $creatorId];
        if ($academyId !== null) {
            $sqlRecipients .= " AND academy_id = :academy_id";
            $params[':academy_id'] = $academyId;
        }
        $stmtRecipients = $pdo->prepare($sqlRecipients);
        $stmtRecipients->execute($params);
        $recipientIds = $stmtRecipients->fetchAll(PDO::FETCH_COLUMN, 0);
        if (empty($recipientIds)) {
            return;
        }

        $canStoreLink = ensureNotificationsLinkColumn($pdo);
        $insertSql = $canStoreLink
            ? "INSERT INTO notifications (user_id, message, link_url, is_read, created_at) VALUES (:user_id, :message, :link_url, 0, NOW())"
            : "INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())";
        $insertStmt = $pdo->prepare($insertSql);
        $linkUrl = $assignmentId > 0 ? 'entregas.php?assignment=' . $assignmentId : 'entregas.php';

        foreach ($recipientIds as $recipientId) {
            $insertParams = [
                ':user_id' => (int) $recipientId,
                ':message' => $message,
            ];
            if ($canStoreLink) {
                $insertParams[':link_url'] = $linkUrl;
            }
            try {
                $insertStmt->execute($insertParams);
            } catch (PDOException $inner) {
                // Ignorar fallos individuales de notificaciones para no bloquear la creación.
            }
        }
    } catch (Throwable $e) {
        // Mantener la creación de tareas aunque falle la notificación.
    }
}

function buildAssignmentNotificationMessage(string $type, string $title): string
{
    $typeKey = strtolower(trim($type));
    $typeLabel = match ($typeKey) {
        'examen' => 'examen',
        'practica' => 'practica',
        default => 'tarea',
    };
    $cleanTitle = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
    if ($cleanTitle === '') {
        $cleanTitle = 'sin titulo';
    }
    $shortTitle = truncateNotificationText($cleanTitle, 90);
    return sprintf('Nueva %s publicada: %s', $typeLabel, $shortTitle);
}

function truncateNotificationText(string $text, int $limit = 90): string
{
    $limit = max(10, $limit);
    $clean = trim($text);
    if ($clean === '') {
        return '';
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($clean, 'UTF-8') <= $limit) {
            return $clean;
        }
        return rtrim(mb_substr($clean, 0, $limit - 1, 'UTF-8')) . '...';
    }
    if (strlen($clean) <= $limit) {
        return $clean;
    }
    return rtrim(substr($clean, 0, $limit - 1)) . '...';
}

if (!function_exists('ensureNotificationsLinkColumn')) {
    function ensureNotificationsLinkColumn(PDO $pdo): bool
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
}
?>
