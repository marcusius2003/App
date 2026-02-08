<?php
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

$currentUser = requireActiveUser($pdo, ['response' => 'json']);
$userId = (int) $currentUser['id'];
$academyId = $currentUser['academy_id'] ?? null;
$isTeacher = isTeacherRole($currentUser['role'] ?? '');

header('Content-Type: application/json');

ensureChatTables($pdo);

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$isJsonRequest = stripos($contentType, 'application/json') !== false;
$payload = [];
if ($isJsonRequest) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = $decoded;
        }
    }
} else {
    $payload = $_POST ?: [];
}

$action = $_GET['action'] ?? ($payload['action'] ?? null);
if (!$action) {
    respondError('Accion no especificada.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'search_users') {
    $submittedToken = $payload['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    $sessionToken = $_SESSION['messages_csrf'] ?? '';
    if (!$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
        respondError('Token de seguridad no valido.', 403);
    }
}

try {
    switch ($action) {
        case 'list_threads':
            handleListThreads($pdo, $userId, $academyId, $isTeacher);
            break;
        case 'load_messages':
            handleLoadMessages($pdo, $userId, $academyId, $isTeacher);
            break;
        case 'send_message':
            handleSendMessage($pdo, $userId, $academyId, $isTeacher, $payload, $_FILES ?? []);
            break;
        case 'create_thread':
            handleCreateThread($pdo, $userId, $academyId, $isTeacher, $payload);
            break;
        case 'add_participants':
            handleAddParticipants($pdo, $userId, $academyId, $isTeacher, $payload);
            break;
        case 'delete_thread':
            handleDeleteThread($pdo, $userId, $academyId, $isTeacher, $payload);
            break;
        case 'search_users':
            handleSearchUsers($pdo, $userId, $academyId, $isTeacher);
            break;
        default:
            respondError('Accion no reconocida.');
    }
} catch (PDOException $e) {
    respondError('Error interno al acceder a la base de datos.', 500);
}

function handleListThreads(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher): void
{
    $params = [':user_id' => $userId];
    $conditions = [];
    addAcademyFilter($conditions, $params, $academyId);
    if (!$isTeacher) {
        $conditions[] = "ct.visibility = 'general'";
    }
    $whereClause = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("
        SELECT ct.id, ct.name, ct.type, ct.visibility, ct.created_at, ct.last_activity_at, ct.created_by
        FROM chat_threads ct
        JOIN chat_participants cp ON cp.thread_id = ct.id
        WHERE cp.user_id = :user_id {$whereClause}
        GROUP BY ct.id
        ORDER BY ct.last_activity_at DESC, ct.id DESC
    ");
    $stmt->execute($params);
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $threadIds = array_map('intval', array_column($threads, 'id'));
    $participantsMap = fetchParticipantsMap($pdo, $threadIds);
    $lastMessageMap = fetchLastMessageMap($pdo, $threadIds, $userId);

    $formatted = [];
    foreach ($threads as $thread) {
        $threadId = (int) $thread['id'];
        $participants = $participantsMap[$threadId] ?? [];
        $formatted[] = [
            'id' => $threadId,
            'name' => $thread['name'],
            'display_name' => resolveThreadName($thread, $participants, $userId),
            'type' => $thread['type'],
            'visibility' => $thread['visibility'],
            'is_teacher_only' => $thread['visibility'] === 'teachers',
            'created_at' => $thread['created_at'],
            'last_activity_at' => $thread['last_activity_at'],
            'last_activity_iso' => formatIso($thread['last_activity_at']),
            'last_activity_human' => humanizeDateTime($thread['last_activity_at']),
            'participants' => $participants,
            'last_message' => $lastMessageMap[$threadId] ?? null,
        ];
    }

    respond([
        'success' => true,
        'threads' => $formatted,
    ]);
}

function handleLoadMessages(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher): void
{
    $threadId = isset($_GET['thread_id']) ? (int) $_GET['thread_id'] : 0;
    $afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;
    if ($threadId <= 0) {
        respondError('Chat no valido.');
    }

    $thread = getThreadContext($pdo, $threadId, $userId, $academyId, $isTeacher);
    if (!$thread) {
        respondError('No tienes acceso a este chat.', 403);
    }

    $params = [':thread_id' => $threadId];
    $sql = "
        SELECT cm.id, cm.message, cm.attachment_path, cm.attachment_type, cm.created_at, u.id AS user_id, u.username, u.role
        FROM chat_messages cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.thread_id = :thread_id
    ";
    if ($afterId > 0) {
        $sql .= " AND cm.id > :after_id";
        $params[':after_id'] = $afterId;
    }
    $sql .= " ORDER BY cm.id ASC LIMIT 250";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $messages[] = formatMessageRow($row, $userId);
    }

    respond([
        'success' => true,
        'thread' => $thread,
        'messages' => $messages,
    ]);
}

function handleSendMessage(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher, ?array $payload, array $files = []): void
{
    if (!$payload) {
        respondError('Datos incompletos.');
    }
    $threadId = isset($payload['thread_id']) ? (int) $payload['thread_id'] : 0;
    $message = trim((string) ($payload['message'] ?? ''));
    if ($threadId <= 0) {
        respondError('Chat no valido.');
    }
    $messageLength = $message !== '' ? (function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message)) : 0;
    if ($messageLength > 2000) {
        respondError('El mensaje es demasiado largo (max 2000 caracteres).');
    }

    $thread = getThreadContext($pdo, $threadId, $userId, $academyId, $isTeacher);
    if (!$thread) {
        respondError('No tienes permiso para escribir en este chat.', 403);
    }

    $attachmentMeta = null;
    if (!empty($files['attachment']) && is_array($files['attachment']) && !empty($files['attachment']['tmp_name'])) {
        try {
            $attachmentMeta = saveChatAttachment($files['attachment'], $threadId);
        } catch (RuntimeException $e) {
            respondError($e->getMessage());
        }
    }
    if ($message === '' && !$attachmentMeta) {
        respondError('Escribe un mensaje o adjunta una imagen.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (thread_id, user_id, message, attachment_path, attachment_type, created_at)
        VALUES (:thread_id, :user_id, :message, :attachment_path, :attachment_type, NOW())
    ");
    $stmt->execute([
        ':thread_id' => $threadId,
        ':user_id' => $userId,
        ':message' => $message,
        ':attachment_path' => $attachmentMeta['path'] ?? null,
        ':attachment_type' => $attachmentMeta['type'] ?? null,
    ]);
    $messageId = (int) $pdo->lastInsertId();

    updateThreadActivity($pdo, $threadId);

    $stmt = $pdo->prepare("
        SELECT cm.id, cm.message, cm.attachment_path, cm.attachment_type, cm.created_at, u.id AS user_id, u.username, u.role
        FROM chat_messages cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $messageId]);
    $messageRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($messageRow) {
        try {
            notifyChatParticipants($pdo, $thread, $messageRow);
        } catch (Exception $e) {
            // Evitar que un error de notificaciones bloquee el chat
        }
    }

    respond([
        'success' => true,
        'message' => formatMessageRow($messageRow, $userId),
    ]);
}

function handleCreateThread(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher, ?array $payload): void
{
    if (!$payload) {
        respondError('Datos incompletos.');
    }
    $type = $payload['type'] ?? 'direct';
    $type = in_array($type, ['direct', 'group'], true) ? $type : 'direct';
    $teacherOnly = !empty($payload['teacher_only']);
    $name = trim((string) ($payload['name'] ?? ''));
    $participantsRaw = $payload['participants'] ?? [];
    if (!is_array($participantsRaw)) {
        $participantsRaw = [];
    }
    $participantIds = [];
    foreach ($participantsRaw as $candidateId) {
        $candidateId = (int) $candidateId;
        if ($candidateId > 0 && $candidateId !== $userId && !in_array($candidateId, $participantIds, true)) {
            $participantIds[] = $candidateId;
        }
    }
    if (empty($participantIds)) {
        respondError('Selecciona al menos una persona para conversar.');
    }
    if ($type === 'direct' && count($participantIds) !== 1) {
        respondError('El chat individual solo permite una persona adicional.');
    }
    if ($type === 'group' && count($participantIds) < 2) {
        respondError('Los grupos necesitan al menos tres integrantes.');
    }
    if ($type === 'group') {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($nameLength < 3) {
            respondError('Define un nombre descriptivo de al menos 3 caracteres.');
        }
    }

    $allUserIds = array_merge([$userId], $participantIds);
    $usersInfo = fetchUsersByIds($pdo, $allUserIds);
    if (count($usersInfo) !== count($allUserIds)) {
        respondError('Hay usuarios que ya no estan disponibles.');
    }

    foreach ($allUserIds as $id) {
        $info = $usersInfo[$id] ?? null;
        if (!$info) {
            respondError('Hay participantes desconocidos en la seleccion.');
        }
        if (($info['status'] ?? 'inactive') !== 'active') {
            respondError('Todos los participantes deben tener la cuenta activa.');
        }
        if ($academyId && (int) ($info['academy_id'] ?? 0) !== (int) $academyId) {
            respondError('Solo puedes chatear con miembros de tu academia.');
        }
    }

    if ($teacherOnly) {
        if (!$isTeacher) {
            respondError('Solo los profesores pueden crear espacios privados.');
        }
        foreach ($allUserIds as $id) {
            if (!isTeacherRole($usersInfo[$id]['role'] ?? '')) {
                respondError('Todos los participantes deben ser profesores para este segmento.');
            }
        }
    }

    $visibility = $teacherOnly ? 'teachers' : 'general';
    if ($type === 'direct') {
        $existingId = findDirectThread($pdo, $allUserIds, $visibility, $academyId);
        if ($existingId) {
            $thread = getThreadContext($pdo, $existingId, $userId, $academyId, $isTeacher);
            respond([
                'success' => true,
                'thread' => $thread,
                'already_exists' => true,
            ]);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO chat_threads (academy_id, name, type, visibility, created_by, created_at, last_activity_at)
        VALUES (:academy_id, :name, :type, :visibility, :created_by, NOW(), NOW())
    ");
    $stmt->execute([
        ':academy_id' => $academyId,
        ':name' => $type === 'direct' ? null : $name,
        ':type' => $type,
        ':visibility' => $visibility,
        ':created_by' => $userId,
    ]);
    $threadId = (int) $pdo->lastInsertId();

    $insertParticipant = $pdo->prepare("
        INSERT INTO chat_participants (thread_id, user_id, is_admin, joined_at)
        VALUES (:thread_id, :user_id, :is_admin, NOW())
    ");
    foreach ($allUserIds as $id) {
        $insertParticipant->execute([
            ':thread_id' => $threadId,
            ':user_id' => $id,
            ':is_admin' => $id === $userId ? 1 : 0,
        ]);
    }

    $thread = getThreadContext($pdo, $threadId, $userId, $academyId, $isTeacher);
    respond([
        'success' => true,
        'thread' => $thread,
        'already_exists' => false,
    ]);
}

function handleAddParticipants(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher, ?array $payload): void
{
    $threadId = isset($payload['thread_id']) ? (int) $payload['thread_id'] : 0;
    $participantsRaw = $payload['participants'] ?? [];
    if ($threadId <= 0 || empty($participantsRaw) || !is_array($participantsRaw)) {
        respondError('Selecciona al menos una persona para añadir.');
    }
    $participantIds = [];
    foreach ($participantsRaw as $candidateId) {
        $candidateId = (int) $candidateId;
        if ($candidateId > 0 && !in_array($candidateId, $participantIds, true)) {
            $participantIds[] = $candidateId;
        }
    }
    if (empty($participantIds)) {
        respondError('Selecciona participantes válidos.');
    }

    $thread = getThreadContext($pdo, $threadId, $userId, $academyId, $isTeacher);
    if (!$thread) {
        respondError('Chat no disponible.', 404);
    }
    if ($thread['type'] !== 'group') {
        respondError('Solo los grupos aceptan nuevos integrantes.');
    }
    if (empty($thread['can_manage'])) {
        respondError('No tienes permisos para editar este grupo.', 403);
    }

    $currentParticipantIds = array_map(static fn($p) => (int) $p['id'], $thread['participants'] ?? []);
    $newIds = array_values(array_diff($participantIds, $currentParticipantIds));
    if (empty($newIds)) {
        respond([
            'success' => true,
            'thread' => $thread,
            'added' => 0,
        ]);
    }

    $usersInfo = fetchUsersByIds($pdo, $newIds);
    if (count($usersInfo) !== count($newIds)) {
        respondError('Algunos usuarios ya no están disponibles.');
    }
    foreach ($usersInfo as $info) {
        if (($info['status'] ?? 'inactive') !== 'active') {
            respondError('Todos los participantes deben tener la cuenta activa.');
        }
        if ($academyId && (int) ($info['academy_id'] ?? 0) !== (int) $academyId) {
            respondError('Solo puedes añadir personas de tu academia.');
        }
        if ($thread['is_teacher_only'] && !isTeacherRole($info['role'] ?? '')) {
            respondError('Este grupo es solo para docentes.');
        }
    }

    $insertParticipant = $pdo->prepare("
        INSERT IGNORE INTO chat_participants (thread_id, user_id, is_admin, joined_at)
        VALUES (:thread_id, :user_id, 0, NOW())
    ");
    foreach ($newIds as $id) {
        $insertParticipant->execute([
            ':thread_id' => $threadId,
            ':user_id' => $id,
        ]);
    }

    $updatedThread = getThreadContext($pdo, $threadId, $userId, $academyId, $isTeacher);
    respond([
        'success' => true,
        'thread' => $updatedThread,
        'added' => count($newIds),
    ]);
}

function handleDeleteThread(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher, ?array $payload): void
{
    $threadId = isset($payload['thread_id']) ? (int) $payload['thread_id'] : 0;
    if ($threadId <= 0) {
        respondError('Chat no válido.');
    }
    $thread = getThreadContext($pdo, $threadId, $userId, $academyId, $isTeacher);
    if (!$thread) {
        respondError('Chat no disponible.', 404);
    }
    if (empty($thread['can_manage'])) {
        respondError('No tienes permisos para eliminar este chat.', 403);
    }
    $stmt = $pdo->prepare("DELETE FROM chat_threads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $threadId]);
    respond([
        'success' => true,
        'deleted' => true,
    ]);
}

function handleSearchUsers(PDO $pdo, int $userId, ?int $academyId, bool $isTeacher): void
{
    $term = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
    $teacherOnly = isset($_GET['teacher_only']) && (int) $_GET['teacher_only'] === 1;
    if ($teacherOnly && !$isTeacher) {
        respondError('No tienes permiso para buscar en ese segmento.', 403);
    }

    $conditions = ["u.id != :current_id", "u.status = 'active'"];
    $params = [':current_id' => $userId];
    if ($term !== '') {
        $conditions[] = "(u.username LIKE :term OR u.email LIKE :term)";
        $params[':term'] = '%' . $term . '%';
    }
    if ($academyId) {
        $conditions[] = 'u.academy_id = :academy_id';
        $params[':academy_id'] = $academyId;
    }
    if ($teacherOnly) {
        $conditions[] = "LOWER(u.role) IN ('teacher','profesor','docente','teacher_admin')";
    }

    $sql = "
        SELECT u.id, u.username, u.email, u.role
        FROM users u
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY u.username ASC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => (int) $row['id'],
            'name' => $row['username'],
            'email' => $row['email'],
            'role' => $row['role'],
            'role_label' => roleLabel($row['role']),
        ];
    }

    respond([
        'success' => true,
        'users' => $users,
    ]);
}

function fetchParticipantsMap(PDO $pdo, array $threadIds): array
{
    if (empty($threadIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
    $stmt = $pdo->prepare("
        SELECT cp.thread_id, u.id, u.username, u.role, u.email, cp.is_admin
        FROM chat_participants cp
        JOIN users u ON u.id = cp.user_id
        WHERE cp.thread_id IN ($placeholders)
        ORDER BY u.username ASC
    ");
    $stmt->execute($threadIds);

    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int) $row['thread_id']][] = [
            'id' => (int) $row['id'],
            'name' => $row['username'],
            'role' => $row['role'],
            'role_label' => roleLabel($row['role']),
            'email' => $row['email'],
            'is_admin' => (bool) ($row['is_admin'] ?? 0),
        ];
    }

    return $map;
}

function fetchLastMessageMap(PDO $pdo, array $threadIds, int $currentUserId): array
{
    if (empty($threadIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($threadIds), '?'));
    $stmt = $pdo->prepare("
        SELECT cm.thread_id, cm.id, cm.message, cm.attachment_path, cm.attachment_type, cm.created_at, u.id AS user_id, u.username, u.role
        FROM chat_messages cm
        JOIN (
            SELECT thread_id, MAX(id) AS last_id
            FROM chat_messages
            WHERE thread_id IN ($placeholders)
            GROUP BY thread_id
        ) last_msg ON last_msg.thread_id = cm.thread_id AND last_msg.last_id = cm.id
        JOIN users u ON u.id = cm.user_id
    ");
    $stmt->execute($threadIds);

    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hasAttachment = !empty($row['attachment_path']);
        $textPreview = $row['message'];
        if ($hasAttachment && trim((string) $textPreview) === '') {
            $textPreview = '[Imagen]';
        }
        $map[(int) $row['thread_id']] = [
            'id' => (int) $row['id'],
            'text' => $textPreview,
            'created_at' => $row['created_at'],
            'created_at_iso' => formatIso($row['created_at']),
            'created_at_human' => humanizeDateTime($row['created_at']),
            'has_attachment' => $hasAttachment,
            'attachment_type' => $row['attachment_type'] ?? null,
            'author' => [
                'id' => (int) $row['user_id'],
                'name' => $row['username'],
                'role' => $row['role'],
                'role_label' => roleLabel($row['role']),
            ],
            'is_mine' => ((int) $row['user_id'] === $currentUserId),
        ];
    }

    return $map;
}

function formatMessageRow(array $row, int $currentUserId): array
{
    $attachmentPath = $row['attachment_path'] ?? null;
    $attachmentType = $row['attachment_type'] ?? null;
    return [
        'id' => (int) $row['id'],
        'text' => $row['message'],
        'created_at' => $row['created_at'],
        'created_at_iso' => formatIso($row['created_at']),
        'created_at_human' => humanizeDateTime($row['created_at']),
        'attachment' => $attachmentPath ? [
            'path' => $attachmentPath,
            'type' => $attachmentType,
            'url' => buildAttachmentUrl($attachmentPath),
        ] : null,
        'user' => [
            'id' => (int) $row['user_id'],
            'name' => $row['username'],
            'role' => $row['role'],
            'role_label' => roleLabel($row['role']),
        ],
        'is_mine' => ((int) $row['user_id'] === $currentUserId),
    ];
}

function getThreadContext(PDO $pdo, int $threadId, int $userId, ?int $academyId, bool $isTeacher): ?array
{
    $params = [
        ':thread_id' => $threadId,
        ':user_id' => $userId,
    ];
    $conditions = [];
    addAcademyFilter($conditions, $params, $academyId);
    if (!$isTeacher) {
        $conditions[] = "ct.visibility = 'general'";
    }
    $whereClause = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
    $stmt = $pdo->prepare("
        SELECT ct.id, ct.name, ct.type, ct.visibility, ct.created_at, ct.last_activity_at, ct.created_by
        FROM chat_threads ct
        JOIN chat_participants cp ON cp.thread_id = ct.id
        WHERE ct.id = :thread_id
          AND cp.user_id = :user_id
          {$whereClause}
        LIMIT 1
    ");
    $stmt->execute($params);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$thread) {
        return null;
    }

    $participantsMap = fetchParticipantsMap($pdo, [$threadId]);
    $participants = $participantsMap[$threadId] ?? [];
    $currentIsAdmin = false;
    foreach ($participants as $participant) {
        if ((int) ($participant['id'] ?? 0) === $userId && !empty($participant['is_admin'])) {
            $currentIsAdmin = true;
            break;
        }
    }
    $canManage = canManageThread($thread, $userId, $isTeacher, $currentIsAdmin);

    return [
        'id' => (int) $thread['id'],
        'name' => $thread['name'],
        'display_name' => resolveThreadName($thread, $participants, $userId),
        'type' => $thread['type'],
        'visibility' => $thread['visibility'],
        'is_teacher_only' => $thread['visibility'] === 'teachers',
        'segment_label' => $thread['visibility'] === 'teachers' ? 'Solo profesores' : 'Alumnos y profesores',
        'created_by' => (int) $thread['created_by'],
        'created_at' => $thread['created_at'],
        'last_activity_at' => $thread['last_activity_at'],
        'last_activity_iso' => formatIso($thread['last_activity_at']),
        'last_activity_human' => humanizeDateTime($thread['last_activity_at']),
        'participants' => $participants,
        'current_is_admin' => $currentIsAdmin,
        'can_manage' => $canManage,
        'last_message' => null,
    ];
}

function findDirectThread(PDO $pdo, array $userIds, string $visibility, ?int $academyId): ?int
{
    sort($userIds);
    $conditions = ["ct.type = 'direct'", "ct.visibility = :visibility"];
    $params = [':visibility' => $visibility];
    if ($academyId) {
        $conditions[] = 'ct.academy_id = :academy_id';
        $params[':academy_id'] = $academyId;
    } else {
        $conditions[] = 'ct.academy_id IS NULL';
    }

    $stmt = $pdo->prepare("
        SELECT ct.id
        FROM chat_threads ct
        WHERE " . implode(' AND ', $conditions) . "
    ");
    $stmt->execute($params);
    $candidateIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($candidateIds)) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
    $stmt = $pdo->prepare("
        SELECT thread_id, GROUP_CONCAT(user_id ORDER BY user_id ASC) AS members
        FROM chat_participants
        WHERE thread_id IN ($placeholders)
        GROUP BY thread_id
    ");
    $stmt->execute(array_map('intval', $candidateIds));
    $targetKey = implode(',', $userIds);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['members'] === $targetKey) {
            return (int) $row['thread_id'];
        }
    }
    return null;
}

function fetchUsersByIds(PDO $pdo, array $userIds): array
{
    if (empty($userIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, username, role, academy_id, status
        FROM users
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($userIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['id']] = $row;
    }
    return $map;
}

function canManageThread(array $thread, int $userId, bool $isTeacher, bool $currentIsAdmin = false): bool
{
    if ($currentIsAdmin) {
        return true;
    }
    if (!empty($thread['created_by']) && (int) $thread['created_by'] === $userId) {
        return true;
    }
    if ($isTeacher) {
        return true;
    }
    return false;
}

function addAcademyFilter(array &$conditions, array &$params, ?int $academyId, string $column = 'ct.academy_id'): void
{
    if ($academyId) {
        $conditions[] = "({$column} = :academy_id OR {$column} IS NULL)";
        $params[':academy_id'] = $academyId;
    } else {
        $conditions[] = "{$column} IS NULL";
    }
}

function updateThreadActivity(PDO $pdo, int $threadId): void
{
    $stmt = $pdo->prepare("UPDATE chat_threads SET last_activity_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $threadId]);
}

function ensureChatTables(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $tables = [
        "CREATE TABLE IF NOT EXISTS `chat_threads` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `academy_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(191) DEFAULT NULL,
            `type` VARCHAR(32) NOT NULL DEFAULT 'direct',
            `visibility` VARCHAR(32) NOT NULL DEFAULT 'general',
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_chat_threads_academy` (`academy_id`),
            INDEX `idx_chat_threads_activity` (`last_activity_at`),
            INDEX `idx_chat_threads_creator` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `chat_participants` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `thread_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
            `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_chat_thread_user` (`thread_id`, `user_id`),
            INDEX `idx_chat_participants_user` (`user_id`),
            CONSTRAINT `fk_chat_participants_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `thread_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_chat_messages_thread` (`thread_id`),
            INDEX `idx_chat_messages_user` (`user_id`),
            CONSTRAINT `fk_chat_messages_thread` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    ensureChatMessageColumns($pdo);
    $ensured = true;
}

function ensureChatMessageColumns(PDO $pdo): void
{
    try {
        $columns = [];
        $colsStmt = $pdo->query("SHOW COLUMNS FROM chat_messages");
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[strtolower($col['Field'])] = true;
        }
        if (!isset($columns['attachment_path'])) {
            $pdo->exec("ALTER TABLE chat_messages ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL AFTER message");
        }
        if (!isset($columns['attachment_type'])) {
            $pdo->exec("ALTER TABLE chat_messages ADD COLUMN attachment_type VARCHAR(32) DEFAULT NULL AFTER attachment_path");
        }
    } catch (PDOException $e) {
        // Ignore schema errors to avoid blocking runtime usage
    }
}

function notifyChatParticipants(PDO $pdo, array $thread, array $messageRow): void
{
    $threadId = (int)($thread['id'] ?? 0);
    $senderId = (int)($messageRow['user_id'] ?? 0);
    if ($threadId <= 0 || $senderId <= 0) {
        return;
    }

    $participantsMap = fetchParticipantsMap($pdo, [$threadId]);
    $participants = $participantsMap[$threadId] ?? [];
    if (empty($participants)) {
        return;
    }

    $recipients = [];
    foreach ($participants as $participant) {
        $participantId = (int)($participant['id'] ?? 0);
        if ($participantId > 0 && $participantId !== $senderId) {
            $recipients[] = $participantId;
        }
    }
    if (empty($recipients)) {
        return;
    }

    $notificationText = buildChatNotificationText($thread, $messageRow);
    if ($notificationText === '') {
        return;
    }

    $linkUrl = 'messages.php?thread=' . $threadId;
    $canStoreLink = ensureNotificationsLinkColumn($pdo);
    $sql = $canStoreLink
        ? "INSERT INTO notifications (user_id, message, link_url, is_read, created_at) VALUES (:user_id, :message, :link_url, 0, NOW())"
        : "INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())";
    $stmt = $pdo->prepare($sql);

    foreach ($recipients as $recipientId) {
        $params = [
            ':user_id' => $recipientId,
            ':message' => $notificationText,
        ];
        if ($canStoreLink) {
            $params[':link_url'] = $linkUrl;
        }
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            // Ignorar errores individuales para no frenar el flujo del chat
        }
    }
}

function buildChatNotificationText(array $thread, array $messageRow): string
{
    $senderName = trim((string)($messageRow['username'] ?? ''));
    if ($senderName === '') {
        $senderName = 'Alguien';
    }
    $threadLabel = trim((string)($thread['display_name'] ?? $thread['name'] ?? 'Chat'));
    if ($threadLabel === '') {
        $threadLabel = 'Chat';
    }
    $messageText = trim((string)($messageRow['message'] ?? ''));
    $hasAttachment = !empty($messageRow['attachment_path']);
    $preview = truncateText($messageText);
    if ($preview === '') {
        $preview = $hasAttachment ? 'Imagen enviada' : 'Nuevo mensaje';
    } elseif ($hasAttachment) {
        $preview .= ' [Imagen]';
    }
    return sprintf('%s escribio en "%s": %s', $senderName, $threadLabel, $preview);
}

function truncateText(string $text, int $limit = 80): string
{
    $clean = preg_replace('/\s+/', ' ', trim($text));
    if ($clean === null) {
        $clean = '';
    }
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
        $hasColumn = (bool)($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        $hasColumn = false;
    }
    return $hasColumn;
}

function saveChatAttachment(array $file, int $threadId): array
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Adjunto invalido.');
    }
    $sizeLimit = 5 * 1024 * 1024; // 5MB
    if (!empty($file['size']) && (int) $file['size'] > $sizeLimit) {
        throw new RuntimeException('La imagen supera los 5 MB permitidos.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: ($file['type'] ?? '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Solo se permiten imagenes JPG, PNG, GIF o WebP.');
    }
    $baseDir = __DIR__ . '/uploads/chat';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0775, true);
    }
    if (!is_dir($baseDir)) {
        throw new RuntimeException('No se pudo preparar la carpeta de adjuntos.');
    }
    $fileName = 'chat_' . $threadId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $targetPath = $baseDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }
    $relativePath = 'uploads/chat/' . $fileName;
    return [
        'path' => $relativePath,
        'type' => 'image',
    ];
}

function buildAttachmentUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    $normalized = str_replace('\\', '/', $path);
    if (preg_match('#^https?://#i', $normalized)) {
        return $normalized;
    }
    return '/' . ltrim($normalized, '/');
}

function resolveThreadName(array $thread, array $participants, int $currentUserId): string
{
    if (!empty($thread['name'])) {
        return $thread['name'];
    }
    if ($thread['type'] === 'direct') {
        foreach ($participants as $participant) {
            if ($participant['id'] !== $currentUserId) {
                return $participant['name'];
            }
        }
    }
    $names = [];
    foreach ($participants as $participant) {
        if ($participant['id'] === $currentUserId) {
            continue;
        }
        $names[] = $participant['name'];
        if (count($names) === 3) {
            break;
        }
    }
    if (empty($names) && !empty($participants)) {
        $names[] = $participants[0]['name'];
    }
    if (empty($names)) {
        return 'Chat sin nombre';
    }
    $label = implode(', ', $names);
    if (count($participants) - 1 > count($names)) {
        $label .= ' +';
    }
    return $label;
}

function roleLabel(?string $role): string
{
    $map = [
        'teacher' => 'Profesor',
        'profesor' => 'Profesor',
        'docente' => 'Profesor',
        'teacher_admin' => 'Profesor',
        'student' => 'Estudiante',
        'alumno' => 'Estudiante',
        'admin' => 'Administrador',
        'superadmin' => 'Super admin',
        'support' => 'Soporte',
        'usuario' => 'Usuario',
    ];
    $key = strtolower((string) $role);
    return $map[$key] ?? ucfirst($key ?: 'Usuario');
}

function isTeacherRole(?string $role): bool
{
    $normalized = strtolower(trim((string) $role));
    return in_array($normalized, ['teacher', 'profesor', 'docente', 'teacher_admin'], true);
}

function humanizeDateTime(?string $dateTime): string
{
    if (!$dateTime) {
        return '';
    }
    try {
        $dt = new DateTime($dateTime);
        $now = new DateTime('now', $dt->getTimezone());
    } catch (Exception $e) {
        return (string) $dateTime;
    }
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60) {
        return 'Hace instantes';
    }
    $minutes = floor($diff / 60);
    if ($minutes < 60) {
        return 'Hace ' . $minutes . ' min';
    }
    $hours = floor($minutes / 60);
    if ($hours < 24) {
        return 'Hace ' . $hours . ' h';
    }
    $days = floor($hours / 24);
    if ($days === 1) {
        return 'Ayer';
    }
    if ($days < 7) {
        return 'Hace ' . $days . ' dias';
    }
    return $dt->format('d/m/Y H:i');
}

function formatIso(?string $dateTime): ?string
{
    if (!$dateTime) {
        return null;
    }
    try {
        $dt = new DateTime($dateTime);
        return $dt->format(DateTime::ATOM);
    } catch (Exception $e) {
        return null;
    }
}

function respond(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function respondError(string $message, int $status = 400): void
{
    http_response_code($status);
    respond([
        'success' => false,
        'message' => $message,
    ]);
}
