<?php
session_start();

// Evitar cache en navegador (y facilitar ver cambios en local).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';
require_once __DIR__ . '/includes/tenant_context.php';

$currentUser = requireActiveUser($pdo);
$userId = (int) ($currentUser['id'] ?? 0);

// Compat: este archivo usa $conn como alias de $pdo en includes.
$conn = $pdo;

$tenantContext = new TenantContext($pdo);
try {
    $context = $tenantContext->resolveTenantContext();
    $academy_id = (int) ($context['academy_id'] ?? 0);
} catch (Exception $e) {
    $academy_id = (int) ($currentUser['academy_id'] ?? 0);
}

$template = $academy_id > 0 ? $tenantContext->getTenantTemplate($academy_id) : ['code' => 'CORE_ONLY'];
$templateCode = strtoupper((string) ($template['code'] ?? 'CORE_ONLY'));
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);

if ($academy_id > 0 && $templateCode !== 'EDUCATION' && !$isHospitality) {
    $eduSignals = ['edu.courses', 'edu.assignments', 'edu.exams', 'edu.library'];
    foreach ($eduSignals as $signal) {
        if (tenant_has_feature($conn, $academy_id, $signal)) {
            $templateCode = 'EDUCATION';
            $template['code'] = 'EDUCATION';
            break;
        }
    }
}
$isTenantAdmin = ($academy_id > 0 && $userId > 0) ? is_tenant_admin($pdo, $userId, $academy_id) : false;
$hasSidebar = ($academy_id > 0);

// M?tricas principales
$total_tenants = 0;
$total_users = 0;
$open_tickets = 0;
$urgent_tickets = 0;

// Tickets este mes vs mes anterior
$tenants_this_month = 0;
$tenants_last_month = 0;

// Usuarios por rol
$users_by_role = [];
if (!function_exists('findColumnByPreference')) {
    function findColumnByPreference(array $columns, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($columns[$key])) {
                return $columns[$key];
            }
        }
        return null;
    }
}
if (!function_exists('quoteIdentifier')) {
    function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
if (!function_exists('fetchTableColumns')) {
    function fetchTableColumns(PDO $pdo, string $table): array {
        $columns = [];
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
            while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[strtolower($col['Field'])] = $col['Field'];
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
        return $columns;
    }
}

$isPlatformAdmin = ($userId > 0) ? is_platform_admin($conn, $userId) : false;
$normalizedRole = strtolower(trim((string) ($currentUser['role'] ?? '')));
$isTeacherRole = $isTenantAdmin || in_array($normalizedRole, ['admin', 'administrator', 'administrador', 'teacher', 'profesor', 'professor', 'instructor'], true);

$communityPosts = [];
$communityError = null;
$communitySuccess = null;
$announcements = [];
$nextEvent = null;
$nextEventSortKey = null;
$calendarPreviewItems = [];
$activityItems = [];
$recentFiles = [];
$educationStats = [
    'pending_tasks' => 0,
    'pending_grades' => 0,
    'late_submissions' => 0,
];
$attendanceStats = [
    'active' => 0,
    'total' => 0,
    'percent' => 0,
];
$upcomingDeliveries = [];

if ($academy_id > 0) {
    // Comunidad (muro)
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS community_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academy_id INT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            attachment_name VARCHAR(255) NULL,
            attachment_url VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_community_posts_academy (academy_id),
            INDEX idx_community_posts_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Ignorar si no se puede crear la tabla.
    }
    try {
        $conn->exec("ALTER TABLE community_posts ADD COLUMN attachment_name VARCHAR(255) NULL");
    } catch (PDOException $e) {
    }
    try {
        $conn->exec("ALTER TABLE community_posts ADD COLUMN attachment_url VARCHAR(255) NULL");
    } catch (PDOException $e) {
    }
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS community_post_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_post_user (post_id, user_id),
            INDEX idx_post_like_post (post_id),
            INDEX idx_post_like_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'community_like') {
        if (!csrf_validate($_POST['csrf_token'] ?? '')) {
            $communityError = 'Token inv?lido. Intenta de nuevo.';
        } else {
            $postId = (int) ($_POST['post_id'] ?? 0);
            if ($postId > 0) {
                try {
                    $stmt = $conn->prepare("SELECT id FROM community_post_likes WHERE post_id = :pid AND user_id = :uid LIMIT 1");
                    $stmt->execute([':pid' => $postId, ':uid' => $userId]);
                    if ($stmt->fetchColumn()) {
                        $stmt = $conn->prepare("DELETE FROM community_post_likes WHERE post_id = :pid AND user_id = :uid");
                        $stmt->execute([':pid' => $postId, ':uid' => $userId]);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO community_post_likes (post_id, user_id) VALUES (:pid, :uid)");
                        $stmt->execute([':pid' => $postId, ':uid' => $userId]);
                    }
                } catch (PDOException $e) {
                    $communityError = 'No se pudo actualizar el like.';
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'community_post') {
        if (!csrf_validate($_POST['csrf_token'] ?? '')) {
            $communityError = 'Token inv?lido. Intenta de nuevo.';
        } else {
            $content = trim((string) ($_POST['community_content'] ?? ''));
            if ($content === '') {
                $communityError = 'Escribe un mensaje antes de publicar.';
            } else {
                try {
                    $attachmentName = null;
                    $attachmentUrl = null;
                    if (!empty($_FILES['community_attachment']) && $_FILES['community_attachment']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/uploads/community/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $originalName = basename((string) $_FILES['community_attachment']['name']);
                        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                        if (in_array($ext, $allowed, true)) {
                            $fileName = $userId . '_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
                            $targetPath = $uploadDir . $fileName;
                            if (move_uploaded_file($_FILES['community_attachment']['tmp_name'], $targetPath)) {
                                $attachmentName = $originalName;
                                $attachmentUrl = 'uploads/community/' . $fileName;
                            }
                        }
                    }
                    $stmt = $conn->prepare("INSERT INTO community_posts (academy_id, user_id, content, attachment_name, attachment_url) VALUES (:aid, :uid, :content, :aname, :aurl)");
                    $stmt->execute([
                        ':aid' => $academy_id,
                        ':uid' => $userId,
                        ':content' => $content,
                        ':aname' => $attachmentName,
                        ':aurl' => $attachmentUrl,
                    ]);
                    $communitySuccess = 'Publicaci?n realizada.';
                } catch (PDOException $e) {
                    $communityError = 'No se pudo publicar. Intenta m?s tarde.';
                }
            }
        }
    }

    try {
        $stmt = $conn->prepare("SELECT p.id, p.content, p.attachment_name, p.attachment_url, p.created_at, u.username, u.email,
                (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id) AS like_count,
                (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id AND l.user_id = :uid) AS liked
            FROM community_posts p
            JOIN users u ON u.id = p.user_id
            WHERE (p.academy_id = :aid OR p.academy_id IS NULL)
            ORDER BY p.created_at DESC
            LIMIT 5");
        $stmt->execute([':aid' => $academy_id, ':uid' => $userId]);
        $communityPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            // Si falla, dejar vac?o.
        }
        $communityPosts = [];
    }

    // Anuncios
    try {
        $stmt = $conn->query("SELECT id, title, message, type, start_date, end_date, created_at
            FROM announcements
            WHERE active = 1
              AND (start_date IS NULL OR start_date <= CURDATE())
              AND (end_date IS NULL OR end_date >= CURDATE())
            ORDER BY created_at DESC
            LIMIT 3");
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            // Ignorar.
        }
        $announcements = [];
    }

    // Estad?sticas educativas
    try {
        if ($isTeacherRole) {
            $stmt = $conn->prepare("SELECT COUNT(*)
                FROM submissions s
                JOIN assignments a ON a.id = s.assignment_id
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND (s.grade IS NULL OR s.grade = '')
                  AND (s.status IS NULL OR s.status = 'submitted')");
            $stmt->execute([':aid' => $academy_id]);
            $educationStats['pending_grades'] = (int) ($stmt->fetchColumn() ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*)
                FROM assignments a
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND (a.due_date IS NULL OR a.due_date >= CURDATE())
                  AND (a.published_at IS NULL OR a.published_at <= NOW())
                  AND (a.is_blocked IS NULL OR a.is_blocked = 0)");
            $stmt->execute([':aid' => $academy_id]);
            $educationStats['pending_tasks'] = (int) ($stmt->fetchColumn() ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*)
                FROM submissions s
                JOIN assignments a ON a.id = s.assignment_id
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND s.is_late = 1");
            $stmt->execute([':aid' => $academy_id]);
            $educationStats['late_submissions'] = (int) ($stmt->fetchColumn() ?? 0);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*)
                FROM assignments a
                LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :uid
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND (a.published_at IS NULL OR a.published_at <= NOW())
                  AND (a.is_blocked IS NULL OR a.is_blocked = 0)
                  AND s.id IS NULL
                  AND (a.due_date IS NULL OR a.due_date >= CURDATE())");
            $stmt->execute([':aid' => $academy_id, ':uid' => $userId]);
            $educationStats['pending_tasks'] = (int) ($stmt->fetchColumn() ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*)
                FROM submissions s
                JOIN assignments a ON a.id = s.assignment_id
                WHERE s.student_id = :uid
                  AND (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND (s.grade IS NULL OR s.grade = '')");
            $stmt->execute([':aid' => $academy_id, ':uid' => $userId]);
            $educationStats['pending_grades'] = (int) ($stmt->fetchColumn() ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*)
                FROM assignments a
                LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :uid
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND s.id IS NULL
                  AND a.due_date IS NOT NULL
                  AND a.due_date < CURDATE()");
            $stmt->execute([':aid' => $academy_id, ':uid' => $userId]);
            $educationStats['late_submissions'] = (int) ($stmt->fetchColumn() ?? 0);
        }
    } catch (PDOException $e) {
        $educationStats = ['pending_tasks' => 0, 'pending_grades' => 0, 'late_submissions' => 0];
    }

    // Asistencia estimada (usuarios activos hoy)
    try {
        $usersColumns = fetchTableColumns($conn, 'users');
        $activityCol = $usersColumns['last_activity_at']
            ?? $usersColumns['last_seen']
            ?? $usersColumns['last_login']
            ?? $usersColumns['updated_at']
            ?? null;

        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE academy_id = :aid" . (isset($usersColumns['deleted_at']) ? " AND deleted_at IS NULL" : ""));
        $stmt->execute([':aid' => $academy_id]);
        $attendanceStats['total'] = (int) ($stmt->fetchColumn() ?? 0);

        if ($activityCol) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE academy_id = :aid AND " . quoteIdentifier($activityCol) . " >= CURDATE()" . (isset($usersColumns['deleted_at']) ? " AND deleted_at IS NULL" : ""));
            $stmt->execute([':aid' => $academy_id]);
            $attendanceStats['active'] = (int) ($stmt->fetchColumn() ?? 0);
        }
        if ($attendanceStats['total'] > 0) {
            $attendanceStats['percent'] = (int) round(($attendanceStats['active'] / $attendanceStats['total']) * 100);
        }
    } catch (PDOException $e) {
        $attendanceStats = ['active' => 0, 'total' => 0, 'percent' => 0];
    }

    // Entregas pr?ximas (7 d?as)
    try {
        $stmt = $conn->prepare("SELECT id, title, due_date
            FROM assignments
            WHERE (academy_id = :aid OR academy_id IS NULL)
              AND due_date IS NOT NULL
              AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY due_date ASC
            LIMIT 3");
        $stmt->execute([':aid' => $academy_id]);
        $upcomingDeliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $upcomingDeliveries = [];
    }

    // Actividad (tareas / entregas)
    try {
        if ($isTeacherRole) {
            $stmt = $conn->prepare("SELECT a.id, a.title, a.due_date,
                    COUNT(s.id) AS total_submissions,
                    SUM(CASE WHEN (s.grade IS NULL OR s.grade = '') AND (s.status IS NULL OR s.status = 'submitted') THEN 1 ELSE 0 END) AS pending_grades
                FROM assignments a
                LEFT JOIN submissions s ON s.assignment_id = a.id
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                GROUP BY a.id
                ORDER BY a.due_date IS NULL, a.due_date ASC, a.created_at DESC
                LIMIT 3");
            $stmt->execute([':aid' => $academy_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $dueLabel = !empty($row['due_date']) ? date('d M', strtotime($row['due_date'])) : 'Sin fecha';
                $pending = (int) ($row['pending_grades'] ?? 0);
                $statusLabel = $pending > 0 ? "Pendiente ? {$pending} por calificar" : 'Sin entregas nuevas';
                $activityItems[] = [
                    'title' => $row['title'] ?: 'Tarea sin t?tulo',
                    'status' => $statusLabel,
                    'due' => $dueLabel,
                    'url' => 'entregas.php',
                    'action' => 'Ver entregas'
                ];
            }
        } else {
            $stmt = $conn->prepare("SELECT a.id, a.title, a.due_date, s.id AS submission_id, s.grade, s.status, s.submitted_at
                FROM assignments a
                LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :uid
                WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
                  AND (a.published_at IS NULL OR a.published_at <= NOW())
                ORDER BY a.due_date IS NULL, a.due_date ASC
                LIMIT 3");
            $stmt->execute([':aid' => $academy_id, ':uid' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $status = 'Pendiente';
                if (!empty($row['submission_id'])) {
                    $status = ($row['grade'] === null || $row['grade'] === '') ? 'En revisi?n' : 'Calificada';
                }
                $dueLabel = !empty($row['due_date']) ? date('d M', strtotime($row['due_date'])) : 'Sin fecha';
                $activityItems[] = [
                    'title' => $row['title'] ?: 'Tarea sin t?tulo',
                    'status' => $status,
                    'due' => $dueLabel,
                    'url' => 'entregas.php',
                    'action' => $status === 'Pendiente' ? 'Entregar' : 'Ver'
                ];
            }
        }
    } catch (PDOException $e) {
        $activityItems = [];
    }

    $calendarDateMarks = [];

    // Pr?ximo evento + vista r?pida de calendario
    $eventsSchema = [
        'available' => false,
        'user_column' => null,
        'academy_column' => null,
        'title_column' => null,
        'start_column' => null,
    ];
    $tasksSchema = [
        'available' => false,
        'user_column' => null,
        'academy_column' => null,
        'title_column' => null,
        'due_date_column' => null,
    ];

    try {
        $eventsColumns = fetchTableColumns($conn, 'events');
        if (!empty($eventsColumns)) {
            $eventsSchema['user_column'] = $eventsColumns['user_id'] ?? findColumnByPreference($eventsColumns, ['owner_id','student_id','created_by']);
            $eventsSchema['academy_column'] = $eventsColumns['academy_id'] ?? findColumnByPreference($eventsColumns, ['academia_id','school_id','campus_id']);
            $eventsSchema['title_column'] = $eventsColumns['title'] ?? findColumnByPreference($eventsColumns, ['name','event_title','subject']);
            $eventsSchema['start_column'] = $eventsColumns['start'] ?? findColumnByPreference($eventsColumns, ['start_date','start_at','fecha_inicio']);
            if ($eventsSchema['title_column'] && $eventsSchema['start_column']) {
                $eventsSchema['available'] = true;
            }
        }
    } catch (PDOException $e) {
        $eventsSchema['available'] = false;
    }

    try {
        $tasksColumns = fetchTableColumns($conn, 'tasks');
        if (!empty($tasksColumns)) {
            $tasksSchema['user_column'] = $tasksColumns['user_id'] ?? findColumnByPreference($tasksColumns, ['owner_id','student_id','created_by']);
            $tasksSchema['academy_column'] = $tasksColumns['academy_id'] ?? findColumnByPreference($tasksColumns, ['academia_id','school_id','campus_id']);
            $tasksSchema['title_column'] = $tasksColumns['title'] ?? findColumnByPreference($tasksColumns, ['name','task_title','subject']);
            $tasksSchema['due_date_column'] = $tasksColumns['due_date'] ?? findColumnByPreference($tasksColumns, ['deadline','delivery_date','end_date']);
            if ($tasksSchema['title_column'] && $tasksSchema['due_date_column']) {
                $tasksSchema['available'] = true;
            }
        }
    } catch (PDOException $e) {
        $tasksSchema['available'] = false;
    }

    if ($eventsSchema['available']) {
        try {
            $startCol = quoteIdentifier($eventsSchema['start_column']);
            $titleCol = quoteIdentifier($eventsSchema['title_column']);
            $sql = "SELECT id, {$titleCol} AS title, {$startCol} AS start_date FROM events WHERE {$startCol} >= NOW()";
            $params = [];
            if ($eventsSchema['user_column']) {
                $sql .= " AND " . quoteIdentifier($eventsSchema['user_column']) . " = :uid";
                $params[':uid'] = $userId;
            }
            if ($eventsSchema['academy_column']) {
                $sql .= " AND (" . quoteIdentifier($eventsSchema['academy_column']) . " = :aid OR " . quoteIdentifier($eventsSchema['academy_column']) . " IS NULL)";
                $params[':aid'] = $academy_id;
            }
            $sql .= " ORDER BY {$startCol} ASC LIMIT 12";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($events as $event) {
                if (empty($event['start_date'])) {
                    continue;
                }
                $startDate = strtotime($event['start_date']);
                $label = date('d M H:i', $startDate);
                $sortKey = date('Y-m-d H:i:s', $startDate);
                if ($nextEvent === null || $nextEventSortKey === null || $sortKey < $nextEventSortKey) {
                    $nextEvent = [
                        'title' => $event['title'] ?: 'Evento sin t?tulo',
                        'label' => $label,
                    ];
                    $nextEventSortKey = $sortKey;
                }
                $dateKey = date('Y-m-d', $startDate);
                $calendarDateMarks[$dateKey] = true;
                $calendarPreviewItems[] = [
                    'title' => $event['title'] ?: 'Evento sin t?tulo',
                    'label' => $label,
                    'sort_key' => $sortKey,
                ];
            }
        } catch (PDOException $e) {
            // Ignorar.
        }
    }

    if ($tasksSchema['available']) {
        try {
            $dueCol = quoteIdentifier($tasksSchema['due_date_column']);
            $titleCol = quoteIdentifier($tasksSchema['title_column']);
            $sql = "SELECT id, {$titleCol} AS title, {$dueCol} AS due_date FROM tasks WHERE {$dueCol} >= CURDATE()";
            $params = [];
            if ($tasksSchema['user_column']) {
                $sql .= " AND " . quoteIdentifier($tasksSchema['user_column']) . " = :uid";
                $params[':uid'] = $userId;
            }
            if ($tasksSchema['academy_column']) {
                $sql .= " AND " . quoteIdentifier($tasksSchema['academy_column']) . " = :aid";
                $params[':aid'] = $academy_id;
            }
            $sql .= " ORDER BY {$dueCol} ASC LIMIT 12";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tasks as $task) {
                if (empty($task['due_date'])) {
                    continue;
                }
                $dueDate = strtotime($task['due_date']);
                $label = date('d M', $dueDate);
                $sortKey = date('Y-m-d H:i:s', $dueDate);
                if ($nextEvent === null || $nextEventSortKey === null || $sortKey < $nextEventSortKey) {
                    $nextEvent = [
                        'title' => $task['title'] ?: 'Tarea sin t?tulo',
                        'label' => $label,
                    ];
                    $nextEventSortKey = $sortKey;
                }
                $dateKey = date('Y-m-d', $dueDate);
                $calendarDateMarks[$dateKey] = true;
                $calendarPreviewItems[] = [
                    'title' => $task['title'] ?: 'Tarea sin t?tulo',
                    'label' => $label,
                    'sort_key' => $sortKey,
                ];
            }
        } catch (PDOException $e) {
            // Ignorar.
        }
    }

    // Entregas / Examenes (assignments) como eventos proximos
    try {
        $sql = "SELECT id, title, type, due_date
            FROM assignments
            WHERE (academy_id = :aid OR academy_id IS NULL)
              AND due_date IS NOT NULL
              AND due_date >= CURDATE()";
        $params = [':aid' => $academy_id];
        if (!$isTeacherRole) {
            $sql .= " AND (published_at IS NULL OR published_at <= NOW())";
        }
        $sql .= " ORDER BY due_date ASC LIMIT 12";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($assignments as $assignment) {
            if (empty($assignment['due_date'])) {
                continue;
            }
            $dueDate = strtotime($assignment['due_date']);
            $label = date('d M', $dueDate);
            $sortKey = date('Y-m-d H:i:s', $dueDate);
            $titlePrefix = '';
            if (!empty($assignment['type'])) {
                $type = strtolower((string) $assignment['type']);
                if ($type === 'examen') {
                    $titlePrefix = 'Examen: ';
                } elseif ($type === 'tarea') {
                    $titlePrefix = 'Tarea: ';
                } elseif ($type === 'practica') {
                    $titlePrefix = 'Practica: ';
                }
            }
            if ($nextEvent === null || $nextEventSortKey === null || $sortKey < $nextEventSortKey) {
                $nextEvent = [
                    'title' => $titlePrefix . ($assignment['title'] ?: 'Entrega sin t?tulo'),
                    'label' => $label,
                ];
                $nextEventSortKey = $sortKey;
            }
            $dateKey = date('Y-m-d', $dueDate);
            $calendarDateMarks[$dateKey] = true;
            $calendarPreviewItems[] = [
                'title' => $titlePrefix . ($assignment['title'] ?: 'Entrega sin t?tulo'),
                'label' => $label,
                'sort_key' => $sortKey,
            ];
        }
    } catch (PDOException $e) {
        // Ignorar.
    }

    // Entregas (assignments) como eventos del calendario
    try {
        $assignColumns = fetchTableColumns($conn, 'assignments');
        if (!empty($assignColumns)) {
            $titleCol = $assignColumns['title'] ?? findColumnByPreference($assignColumns, ['name','subject']);
            $dueCol = $assignColumns['due_date'] ?? findColumnByPreference($assignColumns, ['deadline','delivery_date']);
            $academyCol = $assignColumns['academy_id'] ?? findColumnByPreference($assignColumns, ['academia_id','school_id']);
            $publishedCol = $assignColumns['published_at'] ?? null;
            if ($titleCol && $dueCol) {
                $sql = "SELECT id, " . quoteIdentifier($titleCol) . " AS title, " . quoteIdentifier($dueCol) . " AS due_date FROM assignments WHERE " . quoteIdentifier($dueCol) . " >= CURDATE()";
                $params = [];
                if ($academyCol) {
                    $sql .= " AND (" . quoteIdentifier($academyCol) . " = :aid OR " . quoteIdentifier($academyCol) . " IS NULL)";
                    $params[':aid'] = $academy_id;
                }
                if ($publishedCol) {
                    $sql .= " AND (" . quoteIdentifier($publishedCol) . " IS NULL OR " . quoteIdentifier($publishedCol) . " <= NOW())";
                }
                $sql .= " ORDER BY " . quoteIdentifier($dueCol) . " ASC LIMIT 12";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    if (empty($row['due_date'])) {
                        continue;
                    }
                    $dueDate = strtotime($row['due_date']);
                    $label = date('d M', $dueDate);
                    $dateKey = date('Y-m-d', $dueDate);
                    $calendarDateMarks[$dateKey] = true;
                    $calendarPreviewItems[] = [
                        'title' => $row['title'] ?: 'Entrega sin t?tulo',
                        'label' => $label,
                        'sort_key' => date('Y-m-d H:i:s', $dueDate),
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        // Ignorar.
    }

    if (!empty($calendarPreviewItems)) {
        usort($calendarPreviewItems, function ($a, $b) {
            return strcmp($a['sort_key'], $b['sort_key']);
        });
        $calendarPreviewItems = array_slice($calendarPreviewItems, 0, 3);
    }

    if ($nextEvent === null && !empty($calendarPreviewItems)) {
        $first = $calendarPreviewItems[0];
        $nextEvent = [
            'title' => $first['title'],
            'label' => $first['label'],
        ];
    }
    if ($nextEvent === null && !empty($upcomingDeliveries)) {
        $first = $upcomingDeliveries[0];
        if (!empty($first['due_date'])) {
            $label = date('d M', strtotime($first['due_date']));
        } else {
            $label = '';
        }
        $nextEvent = [
            'title' => 'Entrega: ' . ($first['title'] ?? 'Entrega sin titulo'),
            'label' => $label,
        ];
    }

    $miniMonthParam = $_GET['mini_month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $miniMonthParam)) {
        $miniMonthParam = date('Y-m');
    }
    $miniCalendarMonthStart = DateTime::createFromFormat('Y-m', $miniMonthParam);
    if (!$miniCalendarMonthStart) {
        $miniCalendarMonthStart = new DateTime(date('Y-m-01'));
    } else {
        $miniCalendarMonthStart->setDate((int) $miniCalendarMonthStart->format('Y'), (int) $miniCalendarMonthStart->format('m'), 1);
    }
    $miniCalendarPrevParam = (clone $miniCalendarMonthStart)->modify('-1 month')->format('Y-m');
    $miniCalendarNextParam = (clone $miniCalendarMonthStart)->modify('+1 month')->format('Y-m');
    $miniCalendarPrevUrl = 'dashboard.php?mini_month=' . urlencode($miniCalendarPrevParam);
    $miniCalendarNextUrl = 'dashboard.php?mini_month=' . urlencode($miniCalendarNextParam);
    $miniCalendarMonthLabel = ucfirst(strftime('%B %Y', $miniCalendarMonthStart->getTimestamp()));
    $miniCalendarToday = date('Y-m-d');
    $miniCalendarStart = clone $miniCalendarMonthStart;
    if ($miniCalendarStart->format('N') !== '1') {
        $miniCalendarStart->modify('last monday');
    }
    $miniCalendarWeeks = [];
    $cursor = clone $miniCalendarStart;
    for ($week = 0; $week < 6; $week++) {
        $row = [];
        for ($day = 0; $day < 7; $day++) {
            $dateKey = $cursor->format('Y-m-d');
            $row[] = [
                'date' => $dateKey,
                'day' => $cursor->format('j'),
                'is_current' => $cursor->format('m') === $miniCalendarMonthStart->format('m'),
                'is_today' => $dateKey === $miniCalendarToday,
                'has_event' => !empty($calendarDateMarks[$dateKey])
            ];
            $cursor->modify('+1 day');
        }
        $miniCalendarWeeks[] = $row;
    }

    // Archivos recientes (entregas)
    try {
        $sql = "SELECT s.url, s.submitted_at, a.title AS assignment_title, u.username
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN users u ON u.id = s.student_id
            WHERE (a.academy_id = :aid OR a.academy_id IS NULL)
              AND s.url IS NOT NULL AND s.url <> ''";
        $params = [':aid' => $academy_id];
        if (!$isTeacherRole) {
            $sql .= " AND s.student_id = :uid";
            $params[':uid'] = $userId;
        }
        $sql .= " ORDER BY s.submitted_at DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $fileLabel = basename((string) $row['url']);
            $recentFiles[] = [
                'title' => $fileLabel !== '' ? $fileLabel : 'Archivo',
                'label' => !empty($row['submitted_at']) ? date('d M H:i', strtotime($row['submitted_at'])) : 'Sin fecha',
                'url' => $row['url'],
                'meta' => $row['assignment_title'] ?: 'Entrega',
            ];
        }
    } catch (PDOException $e) {
        $recentFiles = [];
    }
}

// ?ltimos tenants
$recent_tenants = [];

// Tickets recientes abiertos
$recent_tickets = [];

// Planes populares
$plans_usage = [];

$priorityColors = ['low' => 'gray', 'normal' => 'blue', 'medium' => 'yellow', 'high' => 'red'];
$priorityLabels = ['low' => 'Baja', 'normal' => 'Normal', 'medium' => 'Media', 'high' => 'Alta'];

// ------------------------------------------------------------
// Tenant-scoped overrides (evita mostrar datos globales/admin)
// ------------------------------------------------------------
$academyName = (string) ($currentUser['academy_name'] ?? ($_SESSION['academy_name'] ?? ''));
$academyNameLower = strtolower($academyName);
$isSalon = $academyNameLower !== '' && (
    strpos($academyNameLower, 'peluquer') !== false
    || strpos($academyNameLower, 'barber') !== false
    || strpos($academyNameLower, 'salon') !== false
    || strpos($academyNameLower, 'estetica') !== false
);
$renderSidebar = $hasSidebar && !$isSalon;
$total_tenants = $academy_id > 0 ? 1 : 0;

// Verificar si estamos en floorplan para ajustes de layout
$isFloorplan = $isHospitality && isset($_GET['restaurant_page']) && $_GET['restaurant_page'] === 'floorplan';

// Users (tenant)
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE academy_id = :aid AND deleted_at IS NULL");
    $stmt->execute([':aid' => $academy_id]);
    $total_users = (int) ($stmt->fetchColumn() ?? 0);
} catch (PDOException $e) {
    if ($e->getCode() !== '42S22') {
        throw $e;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE academy_id = :aid");
    $stmt->execute([':aid' => $academy_id]);
    $total_users = (int) ($stmt->fetchColumn() ?? 0);
}

// Tickets (tenant) via user->academy_id
$tickets_this_month = 0;
$recent_tickets = [];
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        WHERE u.academy_id = :aid
          AND t.status != 'closed'
    ");
    $stmt->execute([':aid' => $academy_id]);
    $open_tickets = (int) ($stmt->fetchColumn() ?? 0);

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        WHERE u.academy_id = :aid
          AND t.status != 'closed'
          AND t.priority = 'high'
    ");
    $stmt->execute([':aid' => $academy_id]);
    $urgent_tickets = (int) ($stmt->fetchColumn() ?? 0);

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        WHERE u.academy_id = :aid
          AND MONTH(t.created_at) = MONTH(CURRENT_DATE())
          AND YEAR(t.created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([':aid' => $academy_id]);
    $tickets_this_month = (int) ($stmt->fetchColumn() ?? 0);

    $stmt = $conn->prepare("
        SELECT t.*
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        WHERE u.academy_id = :aid
          AND t.status != 'closed'
        ORDER BY FIELD(t.priority, 'high', 'medium', 'normal', 'low'), t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':aid' => $academy_id]);
    $recent_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $open_tickets = 0;
    $urgent_tickets = 0;
    $tickets_this_month = 0;
    $recent_tickets = [];
}

// Mapear variable existente usada en UI (antes era tenants por mes)
$tenants_this_month = $tickets_this_month;

// Roles (tenant)
try {
    $stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users WHERE academy_id = :aid AND deleted_at IS NULL GROUP BY role");
    $stmt->execute([':aid' => $academy_id]);
    $users_by_role = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    if ($e->getCode() !== '42S22') {
        throw $e;
    }
    $stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users WHERE academy_id = :aid GROUP BY role");
    $stmt->execute([':aid' => $academy_id]);
    $users_by_role = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Usuarios recientes (tenant)
$recent_users = [];
try {
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE academy_id = :aid ORDER BY id DESC LIMIT 5");
    $stmt->execute([':aid' => $academy_id]);
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_users = [];
}

// Plan activo del tenant (si existe)
$active_plan_name = null;
$active_plan_ends_at = null;
$active_plan_id = null;
try {
    $stmt = $conn->prepare("
        SELECT p.name, ap.ends_at, ap.plan_id
        FROM academy_plan ap
        JOIN plans p ON p.id = ap.plan_id
        WHERE ap.academy_id = :aid
          AND (ap.ends_at IS NULL OR ap.ends_at >= CURDATE())
        ORDER BY ap.starts_at DESC, ap.created_at DESC, ap.id DESC
        LIMIT 1
    ");
    $stmt->execute([':aid' => $academy_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $active_plan_id = (int) ($row['plan_id'] ?? 0);
        $active_plan_name = (string) ($row['name'] ?? '');
        $active_plan_ends_at = $row['ends_at'] ?? null;
    }
} catch (PDOException $e) {
    $active_plan_name = null;
    $active_plan_ends_at = null;
    $active_plan_id = null;
}

$planEducationComponents = [];
if ($active_plan_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT f.code, f.label, f.description
            FROM features f
            JOIN plan_features pf ON pf.feature_id = f.id
            WHERE pf.plan_id = :plan_id
              AND f.code LIKE 'edu.%'
            ORDER BY f.label ASC
        ");
        $stmt->execute([':plan_id' => $active_plan_id]);
        $planEducationComponents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            throw $e;
        }
        $planEducationComponents = [];
    }
}

if ($academy_id > 0 && $templateCode !== 'EDUCATION' && !$isHospitality && !empty($planEducationComponents)) {
    $templateCode = 'EDUCATION';
    $template['code'] = 'EDUCATION';
}

$educationChatThreads = [];
if ($academy_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT id, name, type, last_activity_at
            FROM chat_threads
            WHERE academy_id = :aid
            ORDER BY last_activity_at DESC
            LIMIT 5
        ");
        $stmt->execute([':aid' => $academy_id]);
        $educationChatThreads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') {
            throw $e;
        }
        $educationChatThreads = [];
    }
}

$tenantModules = [];
if ($academy_id > 0) {
    $featureMatrix = get_tenant_feature_matrix($conn, $academy_id);
    foreach ($featureMatrix as $feature) {
        $code = trim((string) ($feature['code'] ?? ''));
        if ($code === '') {
            continue;
        }
        if (function_exists('feature_allowed_for_template') && $templateCode !== '' && !feature_allowed_for_template($templateCode, $code)) {
            continue;
        }
        $overrideMode = (string) ($feature['override_mode'] ?? 'inherit');
        $tenantEnabledValue = $feature['tenant_enabled'] ?? null;
        $visibleByOverride = $overrideMode === 'force_on';
        $visibleByTenantToggle = $tenantEnabledValue !== null && (bool) $tenantEnabledValue;
        $visibleByPlan = !empty($feature['plan_allowed']);
        if (!$visibleByPlan && !$visibleByTenantToggle && !$visibleByOverride) {
            continue;
        }
        $tenantModules[$code] = [
            'code' => $code,
            'label' => (string) ($feature['label'] ?? $code),
            'category' => (string) ($feature['category'] ?? ''),
            'plan_allowed' => !empty($feature['plan_allowed']),
            'override_mode' => (string) ($feature['override_mode'] ?? 'inherit'),
            'tenant_enabled' => array_key_exists('tenant_enabled', $feature) ? $feature['tenant_enabled'] : null,
        ];
    }
    if (empty($tenantModules)) {
        $fallbackModules = [
            'core.calendar' => 'Calendario Global',
            'core.notifications' => 'Notificaciones',
            'core.tickets' => 'Soporte/Incidencias',
            'core.documents' => 'Documentos/Drive',
            'core.users' => 'Usuarios y Roles',
            'core.settings' => 'Configuraci?n',
            'edu.courses' => 'Cursos y Aulas',
            'edu.assignments' => 'Entregas/Tareas',
            'edu.exams' => 'Ex?menes',
            'edu.library' => 'Biblioteca',
        ];
        foreach ($fallbackModules as $code => $label) {
            if (function_exists('feature_allowed_for_template') && $templateCode !== '' && !feature_allowed_for_template($templateCode, $code)) {
                continue;
            }
            if (!tenant_has_feature($conn, $academy_id, $code)) {
                continue;
            }
            $tenantModules[$code] = [
                'code' => $code,
                'label' => $label,
                'category' => '',
                'plan_allowed' => true,
                'override_mode' => 'inherit',
                'tenant_enabled' => null,
            ];
        }
    }
    if (!empty($tenantModules)) {
        uasort($tenantModules, fn($a, $b) => strcasecmp($a['label'], $b['label']));
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard</title>

    <script src="assets/vendor/tailwindcss.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout-core.css">

    <style>
    body { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    #citas-module { display: none; }
    body.show-citas #dashboard-content > :not(#citas-module) { display: none; }
    body.show-citas #citas-module { margin-top: 0; }
    body.show-citas #citas-module { display: block; width: 100%; }
    body.show-citas #dashboard-content {
        padding: 0 !important;
    }
    body.show-citas .main {
        padding: 0 !important;
    }
    body.show-citas #citas-module.edu-card {
        background: transparent;
        border: none;
        box-shadow: none;
    }
    body.show-citas #citas-module .edu-card-body {
        padding: 0;
    }
    .edu-dashboard {
        --edu-bg: #f5f6f8;
        --edu-card: #ffffff;
        --edu-border: #e5e7eb;
        --edu-muted: #6b7280;
        --edu-primary: #111827;
        --edu-accent: #1f2937;
        --edu-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        color: #1f2937;
    }
    .edu-dashboard .edu-shell {
        position: relative;
        background: transparent;
        border-radius: 0;
        padding: 0;
    }
    .edu-dashboard .edu-shell::before {
        content: none;
    }
    .edu-dashboard .edu-shell > * { position: relative; }
    .edu-app { font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    .edu-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 10px;
    }
    .edu-title {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    .edu-userbar {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .edu-notify { position: relative; }
    .edu-notify-panel {
        position: absolute;
        top: 46px;
        right: 0;
        width: 260px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        padding: 8px;
        display: none;
        z-index: 30;
    }
    .edu-notify-panel.active,
    .edu-notify-panel.is-open { display: block; }
    .edu-notify-item {
        padding: 8px 10px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        color: #475569;
    }
    .edu-notify-item:last-child { border-bottom: none; }
    .iu-notify { position: relative; }
    .iu-notify-panel {
        position: absolute;
        top: 46px;
        right: 0;
        width: 280px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        padding: 0;
        display: none;
        z-index: 40;
    }
    .iu-notify-panel.is-open { display: block; }
    .iu-notify-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        font-weight: 600;
        color: #111827;
    }
    .iu-notify-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 500;
        color: #475569;
    }
    .iu-notify-toggle input { accent-color: #111827; }
    .iu-notify-list {
        max-height: 240px;
        overflow-y: auto;
    }
    .iu-notify-item {
        padding: 8px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        color: #475569;
    }
    .iu-notify-item:last-child { border-bottom: none; }
    .iu-notify-link {
        color: inherit;
        text-decoration: none;
        display: block;
    }
    .iu-notify-link:hover { color: #111827; }
    .iu-notify-time {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 4px;
    }
    .iu-notify-footer {
        padding: 8px 12px;
        text-align: right;
        border-top: 1px solid #f1f5f9;
        background: #fafafa;
        border-bottom-left-radius: 12px;
        border-bottom-right-radius: 12px;
    }
    .iu-notify-action {
        font-size: 11px;
        font-weight: 600;
        color: #111827;
        background: transparent;
        border: none;
        cursor: pointer;
    }
    .iu-notify-badge {
        position: absolute;
        top: 3px;
        right: 3px;
        width: 10px;
        height: 10px;
        background: #ef4444;
        border-radius: 999px;
        border: 2px solid #ffffff;
    }
    .iu-toast-stack {
        position: fixed;
        top: 16px;
        right: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 60;
        pointer-events: none;
    }
    .iu-toast {
        width: 280px;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 16px;
        padding: 12px 14px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.2);
        backdrop-filter: blur(10px);
        color: #0f172a;
        font-size: 13px;
        line-height: 1.35;
        opacity: 0;
        transform: translateY(-8px);
        animation: iuToastIn 220ms ease-out forwards;
    }
    .iu-toast-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        margin-bottom: 4px;
    }
    .iu-toast-time {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 6px;
    }
    .iu-toast.is-hiding {
        animation: iuToastOut 180ms ease-in forwards;
    }
    @keyframes iuToastIn {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes iuToastOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-8px); }
    }
    .edu-icon-btn {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        border: 1px solid var(--edu-border);
        background: #ffffff;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .edu-user {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 6px 12px;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid var(--edu-border);
        color: #334155;
        font-size: 12px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    }
    .edu-avatar {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #1f2937;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
    }
    .edu-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 10px 0 18px;
    }
    .edu-tab-item {
        font-size: 12px;
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        color: #5b6476;
        box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .edu-tab-item:hover { border-color: #d1d5db; color: #0f172a; }
    .edu-tab-active {
        background: #111827;
        border-color: #111827;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
    }
    .edu-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #0f172a 0%, #1f2937 100%);
        border-radius: 24px;
        padding: 18px;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.2);
    }
    .edu-stat-card {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
        border-radius: 16px;
        padding: 16px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .edu-stat-head {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.78);
    }
    .edu-stat-icon {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    .edu-stat-value { font-size: 26px; font-weight: 700; }
    .edu-stat-sub { font-size: 11px; color: rgba(255, 255, 255, 0.7); }
    .edu-layout {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 18px;
    }
    .edu-left,
    .edu-right {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .edu-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 18px;
        align-items: stretch;
    }
    .edu-row > .edu-card { height: 100%; }
    .edu-card {
        background: var(--edu-card);
        border: 1px solid var(--edu-border);
        border-radius: 18px;
        box-shadow: var(--edu-shadow);
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .edu-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
    }
    .edu-card-header {
        padding: 14px 18px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #f3f4f6;
    }
    .edu-card-body { padding: 0 18px 18px; }
    .edu-card-muted { color: var(--edu-muted); font-size: 12px; }
    .edu-btn {
        padding: 6px 12px;
        border-radius: 8px;
        background: #111827;
        color: #fff;
        font-size: 12px;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }
    .edu-btn:hover { background: #0b0f1a; }
    .edu-btn-secondary {
        background: #ffffff;
        color: #1f2937;
        border: 1px solid #e5e7eb;
    }
    .edu-link {
        color: #111827;
        font-size: 12px;
    }
    .edu-community-form {
        margin-top: 6px;
    }
    .edu-community-input {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 8px 10px;
    }
    .edu-community-input input[type="text"] {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 12px;
        color: #334155;
        outline: none;
    }
    .edu-attach {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        overflow: hidden;
        position: relative;
    }
    .edu-attach input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .edu-post {
        display: flex;
        gap: 12px;
        padding: 12px 0;
        border-top: 1px solid #eef2f7;
        font-size: 12px;
        color: #475569;
    }
    .edu-post:first-of-type { border-top: none; }
    .edu-post-avatar {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        background: #111827;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 12px;
    }
    .edu-post-meta { color: #94a3b8; font-size: 11px; }
    .edu-post-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;
    }
    .edu-pill {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10px;
        border: 1px solid #e5e7eb;
        color: #64748b;
        background: #ffffff;
    }
    .edu-pill-active {
        background: #111827;
        border-color: #111827;
        color: #ffffff;
        font-weight: 600;
    }
    .edu-empty {
        text-align: center;
        padding: 18px;
        color: #94a3b8;
        font-size: 12px;
        border: 1px dashed #e5e7eb;
        border-radius: 12px;
        background: #fafafa;
    }
    .edu-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        font-size: 12px;
        color: #475569;
    }
    .edu-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
    }
    .edu-list-title { font-weight: 600; color: #1f2937; }
    .edu-next-event {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }
    .edu-date-badge {
        width: 68px;
        height: 68px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        font-weight: 600;
        color: #111827;
    }
    .edu-date-day { font-size: 20px; }
    .edu-date-month { font-size: 11px; text-transform: uppercase; color: #64748b; }
    .edu-next-title { font-weight: 600; }
    .edu-next-meta { font-size: 11px; color: #94a3b8; }
    .edu-cal {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .edu-cal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;
        color: #64748b;
    }
    .edu-cal-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 6px;
        font-size: 11px;
    }
    .edu-cal-day {
        text-align: center;
        padding: 6px 0;
        border-radius: 8px;
        color: #475569;
    }
    .edu-cal-day--muted { color: #d1d5db; }
    .edu-cal-day--today {
        background: #111827;
        color: #ffffff;
        font-weight: 600;
    }
    .edu-cal-day--marked {
        border: 1px solid #111827;
        color: #111827;
        background: #f3f4f6;
        font-weight: 600;
    }
    .edu-chat-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-top: 1px solid #eef2f7;
        font-size: 12px;
        color: #475569;
    }
    .edu-chat-item:first-of-type { border-top: none; }
    .edu-chat-meta { font-size: 11px; color: #94a3b8; }
    .edu-chat-input {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #ffffff;
        margin-top: 8px;
    }
    .edu-chat-input input {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 12px;
        outline: none;
    }
    .edu-chat-send {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        border: none;
        background: #111827;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        cursor: pointer;
    }
    @media (max-width: 1024px) {
        .edu-layout { grid-template-columns: 1fr; }
        .edu-row { grid-template-columns: 1fr; }
    }
    body.dark-mode {
        background-color: #0b1120;
        color: #e2e8f0;
    }
    .dark-mode .bg-white { background-color: #0f172a !important; }
    .dark-mode .bg-gray-50 { background-color: #0b1120 !important; }
    .dark-mode .bg-gray-100 { background-color: #111827 !important; }
    .dark-mode .bg-gray-200 { background-color: #1f2937 !important; }
    .dark-mode .text-gray-900 { color: #f8fafc !important; }
    .dark-mode .text-gray-800 { color: #e2e8f0 !important; }
    .dark-mode .text-gray-700 { color: #cbd5f5 !important; }
    .dark-mode .text-gray-600 { color: #94a3b8 !important; }
    .dark-mode .text-gray-500 { color: #94a3b8 !important; }
    .dark-mode .text-gray-400 { color: #64748b !important; }
    .dark-mode .border-gray-200 { border-color: #1f2937 !important; }
    .dark-mode .border-gray-100 { border-color: #1f2937 !important; }
    .dark-mode .divide-gray-100 > :not([hidden]) ~ :not([hidden]) { border-color: #1f2937 !important; }
    .dark-mode .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: #1f2937 !important; }
    .dark-mode .hover\:bg-gray-50:hover { background-color: #111827 !important; }
    .dark-mode .hover\:bg-gray-100:hover { background-color: #1f2937 !important; }
    .dark-mode .hover\:border-gray-300:hover { border-color: #334155 !important; }
    .dark-mode .edu-dashboard {
        --edu-bg: #0b1120;
        --edu-card: #0f172a;
        --edu-border: #1f2937;
        --edu-muted: #94a3b8;
        --edu-primary: #f8fafc;
        --edu-accent: #e2e8f0;
        --edu-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
        color: #e2e8f0;
    }
    .dark-mode .edu-icon-btn,
    .dark-mode .edu-user,
    .dark-mode .edu-notify-panel,
    .dark-mode .iu-notify-panel,
    .dark-mode .edu-community-input,
    .dark-mode .edu-chat-input,
    .dark-mode .edu-list-item,
    .dark-mode .edu-next-event,
    .dark-mode .edu-date-badge,
    .dark-mode .edu-pill {
        background: #0f172a;
        border-color: #1f2937;
        color: #e2e8f0;
    }
    .dark-mode .edu-pill-active {
        background: #1f2937;
        border-color: #1f2937;
        color: #ffffff;
    }
    .dark-mode .edu-empty {
        background: #0b1120;
        border-color: #1f2937;
        color: #94a3b8;
    }
    .dark-mode .edu-card-header {
        color: #e2e8f0;
        border-bottom-color: #1f2937;
    }
    .dark-mode .edu-tab-item {
        background: #0f172a;
        border-color: #1f2937;
        color: #cbd5f5;
    }
    .dark-mode .edu-tab-item:hover { border-color: #334155; color: #f8fafc; }
    .dark-mode .edu-list-title,
    .dark-mode .edu-title,
    .dark-mode .edu-user-name,
    .dark-mode .edu-next-title { color: #f8fafc; }
    .dark-mode .edu-post,
    .dark-mode .edu-list,
    .dark-mode .edu-chat-item {
        color: #cbd5f5;
        border-top-color: #1f2937;
    }
    .dark-mode .edu-post-meta,
    .dark-mode .edu-next-meta,
    .dark-mode .edu-chat-meta,
    .dark-mode .edu-card-muted { color: #94a3b8; }
    .dark-mode .edu-link { color: #e2e8f0; }
    .dark-mode .edu-community-input input,
    .dark-mode .edu-chat-input input { color: #e2e8f0; }
    .dark-mode .edu-cal-day { color: #cbd5f5; }
    .dark-mode .edu-cal-day--muted { color: #475569; }
    .dark-mode .edu-cal-day--marked {
        background: #111827;
        border-color: #94a3b8;
        color: #f8fafc;
    }
    .dark-mode .iu-notify-header {
        color: #e2e8f0;
        border-bottom-color: #1f2937;
    }
    .dark-mode .iu-notify-item {
        color: #cbd5f5;
        border-bottom-color: #1f2937;
    }
    .dark-mode .iu-notify-footer {
        background: #0b1120;
        border-top-color: #1f2937;
    }
    .dark-mode .iu-notify-action { color: #e2e8f0; }
    .dark-mode .iu-notify-badge { border-color: #0f172a; }
    .dark-mode .iu-toast {
        background: rgba(15, 23, 42, 0.92);
        border-color: rgba(148, 163, 184, 0.2);
        color: #e2e8f0;
    }
    .dark-mode .iu-toast-title,
    .dark-mode .iu-toast-time { color: #94a3b8; }
    </style>
</head>
<body class="<?php echo $isHospitality ? 'iuc-theme iuc-theme-light' : (($templateCode ?? '') === 'EDUCATION' ? 'edu-sidebar-theme' : ''); ?> bg-gray-50 text-gray-900">

<button class="menu-toggle" id="menuToggle" aria-label="Abrir men?">
    <i class="fas fa-bars"></i>
</button>

<div class="layout">
    <?php if ($renderSidebar): ?>
        <?php include __DIR__ . '/includes/navigation.php'; ?>
    <?php endif; ?>
    <?php
    // Aplicar estilos para fullscreen en floorplan
    $mainStyle = $renderSidebar ? '' : 'margin-left:0;';
    if ($isFloorplan) {
        $mainStyle .= 'padding:0;';
    }
    ?>
    <main class="main" <?php if ($mainStyle) echo 'style="' . $mainStyle . '"'; ?>>
        <?php
        $contentClasses = $isFloorplan ? '' : 'p-6 lg:p-8 space-y-6';
        ?>
        <div class="<?php echo $contentClasses; ?>" id="dashboard-content" <?php if ($isFloorplan) echo 'style="padding:0;margin:0;height:100%;"'; ?>>
        <?php if ($isHospitality): ?>
            <?php
            $currentAcademyId = (int) $academy_id;
            $restaurantPage = strtolower(trim((string) ($_GET['restaurant_page'] ?? 'home')));
            $allowedRestaurantPages = ['home', 'floorplan', 'reservations', 'waitlist', 'shifts', 'menu', 'kds'];
            if (!in_array($restaurantPage, $allowedRestaurantPages, true)) {
                $restaurantPage = 'home';
            }

            $restaurantViews = [
                'floorplan' => __DIR__ . '/admin/views/restaurant/floorplan.php',
                'reservations' => __DIR__ . '/admin/views/restaurant/reservations.php',
                'waitlist' => __DIR__ . '/admin/views/restaurant/waitlist.php',
                'shifts' => __DIR__ . '/admin/views/restaurant/shifts.php',
                'menu' => __DIR__ . '/admin/views/restaurant/menu.php',
                'kds' => __DIR__ . '/admin/views/restaurant/kds.php',
            ];

            $normalizedRole = strtolower(trim((string) ($currentUser['role'] ?? '')));
            $isAdmin = $isTenantAdmin || in_array($normalizedRole, ['admin', 'administrator', 'administrador', 'chef', 'manager', 'owner'], true);

            if ($restaurantPage === 'home') {
                include __DIR__ . '/tenant/restaurant/dashboard_feed.php';
            } else {
                $view = $restaurantViews[$restaurantPage] ?? null;
                if ($view && is_file($view)) {
                    include $view;
                } else {
                    include __DIR__ . '/tenant/restaurant/dashboard_feed.php';
                }
            }
            ?>

        <?php else: ?>

                                                <?php if ($templateCode === 'EDUCATION'): ?>

            <div class="edu-app edu-dashboard">
<?php
            $userLabel = (string) (($currentUser['email'] ?? '') ?: ($currentUser['username'] ?? 'Usuario'));
            $academyLabel = $academyName !== '' ? $academyName : 'ACADEMIA';
            $pendingTasks = (int) ($educationStats['pending_tasks'] ?? 0);
            $pendingGrades = (int) ($educationStats['pending_grades'] ?? 0);
            $upcomingClasses = is_array($calendarPreviewItems) ? count($calendarPreviewItems) : 0;
            $upcomingDue = is_array($upcomingDeliveries) ? count($upcomingDeliveries) : 0;
            $nextEventTitle = (string) ($nextEvent['title'] ?? '');
            $nextEventLabel = (string) ($nextEvent['label'] ?? '');
            $nextEventTimestamp = $nextEventLabel != '' ? strtotime($nextEventLabel) : false;
            $monthLabels = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
            $nextEventDay = $nextEventTimestamp ? date('d', $nextEventTimestamp) : '--';
            $nextEventMonth = $nextEventTimestamp ? ($monthLabels[(int) date('n', $nextEventTimestamp)] ?? date('M', $nextEventTimestamp)) : '--';
            $nextEventTime = $nextEventTimestamp ? date('H:i', $nextEventTimestamp) : '';
            $nextEventList = $calendarPreviewItems;
            if ($nextEventTitle !== '' && !empty($nextEventList)) {
                $first = $nextEventList[0];
                if (($first['title'] ?? '') === $nextEventTitle) {
                    array_shift($nextEventList);
                }
            }
            $isSalonDashboard = $isSalon;
            $ui = $isSalonDashboard ? [
                'tab_calendar' => 'Agenda',
                'tab_classes' => 'Citas',
                'tab_courses' => 'Servicios',
                'tab_deliveries' => 'Pagos',
                'stat_pending_title' => 'Citas pendientes',
                'stat_pending_sub' => $pendingTasks > 0 ? 'Tienes citas por confirmar' : 'No hay citas pendientes',
                'stat_notify_title' => 'Solicitudes nuevas',
                'stat_notify_sub' => $pendingGrades > 0 ? 'Nuevas solicitudes' : 'No hay solicitudes nuevas',
                'stat_upcoming_title' => 'Proximas citas',
                'stat_upcoming_sub' => $upcomingClasses > 0 ? 'Citas por iniciar' : 'Sin proximas citas',
                'stat_due_title' => 'Pagos por cobrar',
                'stat_due_sub' => $upcomingDue > 0 ? 'Cobros por vencer' : 'Sin cobros pendientes',
                'community_title' => 'Novedades del salon',
                'community_sub' => 'Comparte novedades',
                'community_placeholder' => 'Escribe una nota para el equipo...',
                'announcements_title' => 'Avisos del salon',
                'activity_title' => 'Agenda del dia',
                'activity_label' => 'Cita',
                'files_title' => 'Fichas recientes',
                'files_button' => '+ Subir foto',
                'files_empty' => 'No hay fichas recientes',
                'next_event_title' => 'Proxima cita',
                'next_event_empty' => 'No hay citas proximas',
                'next_event_more_empty' => 'No hay mas citas programadas',
                'next_event_badge' => 'En agenda',
                'calendar_title' => 'Agenda mensual',
                'chat_title' => 'Chat del equipo',
                'chat_button' => 'Ver mensajes',
                'chat_empty' => 'No hay conversaciones activas',
                'chat_placeholder' => 'Escribe un mensaje...'
            ] : [
                'tab_calendar' => 'Calendario',
                'tab_classes' => 'Clases',
                'tab_courses' => 'Cursos',
                'tab_deliveries' => 'Entregas',
                'stat_pending_title' => 'Tareas pendientes',
                'stat_pending_sub' => $pendingTasks > 0 ? 'Tienes tareas pendientes' : 'No hay tareas pendientes',
                'stat_notify_title' => 'Notificaciones',
                'stat_notify_sub' => $pendingGrades > 0 ? 'Nuevas notificaciones' : 'No hay notificaciones nuevas',
                'stat_upcoming_title' => 'Proximas clases',
                'stat_upcoming_sub' => $upcomingClasses > 0 ? 'Clases por iniciar' : 'Sin proximas clases',
                'stat_due_title' => 'Proximos vencimientos',
                'stat_due_sub' => $upcomingDue > 0 ? 'Entregas por vencer' : 'Sin vencimientos proximos',
                'community_title' => 'Comunidad',
                'community_sub' => 'Comparte actualizaciones',
                'community_placeholder' => 'Escribe algo a tus companeros...',
                'announcements_title' => 'Anuncios del centro',
                'activity_title' => 'Tu actividad',
                'activity_label' => 'Actividad',
                'files_title' => 'Archivos recientes',
                'files_button' => '+ Subir archivo',
                'files_empty' => 'No hay archivos recientes',
                'next_event_title' => 'Proximo evento',
                'next_event_empty' => 'No hay eventos proximos',
                'next_event_more_empty' => 'No hay mas eventos proximos',
                'next_event_badge' => 'Proximo',
                'calendar_title' => 'Proximo evento',
                'chat_title' => 'Chat de comunidad',
                'chat_button' => '+ Subir archivo',
                'chat_empty' => 'No hay conversaciones activas',
                'chat_placeholder' => 'Escribe un mensaje...'
            ];
            ?>
            <?php if ($isSalonDashboard): ?>
                <?php
                $manifestPath = __DIR__ . '/assets/frontend/.vite/manifest.json';
                $manifest = [];
                if (is_file($manifestPath)) {
                    $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
                }
                $entry = $manifest['src/main.tsx'] ?? null;
                $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                if ($basePath === '/') {
                    $basePath = '';
                }
                $assetBase = $basePath . '/assets/frontend/';
                $devBase = $basePath . '/frontend/src/';
                $activeKey = 'dashboard';
                $currentPage = basename($_SERVER['PHP_SELF']);
                if ($currentPage === 'calendar.php') {
                    $activeKey = 'calendario';
                } elseif ($currentPage === 'citas.php') {
                    $activeKey = 'citas';
                } elseif ($currentPage === 'biblioteca.php') {
                    $activeKey = 'biblioteca';
                } elseif ($currentPage === 'settings.php') {
                    $activeKey = 'configuracion';
                }
                $salonStats = [
                    [
                        'title' => $ui['stat_pending_title'] ?? 'Citas',
                        'value' => $pendingTasks,
                        'color' => 'bg-blue-100 text-blue-600',
                    ],
                    [
                        'title' => $ui['stat_notify_title'] ?? 'Solicitudes',
                        'value' => $pendingGrades,
                        'color' => 'bg-emerald-100 text-emerald-600',
                    ],
                    [
                        'title' => $ui['stat_upcoming_title'] ?? 'Proximas citas',
                        'value' => $upcomingClasses,
                        'color' => 'bg-amber-100 text-amber-600',
                    ],
                    [
                        'title' => $ui['stat_due_title'] ?? 'Pagos por cobrar',
                        'value' => $upcomingDue,
                        'color' => 'bg-gray-100 text-gray-900',
                    ],
                ];
                $salonAppointments = [];
                if ($nextEventTitle !== '') {
                    $salonAppointments[] = [
                        'title' => $nextEventTitle,
                        'label' => $nextEventLabel !== '' ? $nextEventLabel : 'Horario pendiente',
                        'time' => $nextEventTime !== '' ? $nextEventTime : null,
                        'status' => $ui['next_event_badge'] ?? null,
                        'url' => '',
                    ];
                }
                if (!empty($nextEventList)) {
                    foreach (array_slice($nextEventList, 0, 3) as $item) {
                        $salonAppointments[] = [
                            'title' => (string) ($item['title'] ?? 'Evento'),
                            'label' => (string) ($item['label'] ?? ''),
                            'time' => '',
                            'status' => '',
                            'url' => (string) ($item['url'] ?? ''),
                        ];
                    }
                }
                $salonGallery = [];
                if (!empty($recentFiles)) {
                    foreach (array_slice($recentFiles, 0, 4) as $file) {
                        $url = (string) ($file['url'] ?? '');
                        $isImage = preg_match('/\.(jpe?g|png|gif|webp)$/i', $url) === 1;
                        $salonGallery[] = [
                            'title' => (string) ($file['meta'] ?? $file['title'] ?? 'Ficha'),
                            'subtitle' => (string) ($file['label'] ?? ''),
                            'image' => $isImage ? $url : '',
                        ];
                    }
                }
                $salonPayload = [
                    'userName' => (string) ($currentUser['username'] ?? ''),
                    'userRole' => $normalizedRole !== '' ? ucfirst($normalizedRole) : '',
                    'academyName' => $academyName,
                    'greetingName' => (string) ($currentUser['username'] ?? ''),
                    'notificationsCount' => 0,
                    'stats' => $salonStats,
                    'appointments' => $salonAppointments,
                    'gallery' => $salonGallery,
                    'calendarWeeks' => $miniCalendarWeeks,
                    'calendarLabel' => $miniCalendarMonthLabel,
                    'calendarPrev' => $miniCalendarPrevUrl,
                    'calendarNext' => $miniCalendarNextUrl,
                    'activeKey' => $activeKey,
                    'links' => [
                        'dashboard' => $basePath . '/dashboard.php',
                        'calendar' => $basePath . '/calendar.php',
                        'appointments' => $basePath . '/citas.php',
                        'library' => $basePath . '/biblioteca.php',
                        'messages' => $basePath . '/messages.php',
                        'settings' => $basePath . '/settings.php',
                        'newAppointment' => $basePath . '/citas.php',
                        'logout' => $basePath . '/logout.php',
                    ],
                ];
                if (!empty($entry['css']) && is_array($entry['css'])) {
                    foreach ($entry['css'] as $cssFile) {
                        echo '<link rel="stylesheet" href="' . htmlspecialchars($assetBase . $cssFile) . '">';
                    }
                }
                ?>
                <style>
                    #dashboard-content {
                        padding: 0 !important;
                    }
                    #dashboard-content > * {
                        margin: 0 !important;
                    }
                    .main {
                        padding: 0 !important;
                    }
                    .iuconnect-react {
                        width: 100%;
                        min-height: 100vh;
                    }
                </style>
                <script>
                    window.IUC_SALON_DATA = <?php echo json_encode($salonPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                </script>
                <div id="iuconnect-salon-dashboard-root" class="iuconnect-react min-h-screen"></div>
                <?php if (!empty($entry['file'])): ?>
                    <script type="module" src="<?php echo htmlspecialchars($assetBase . $entry['file']); ?>"></script>
                <?php else: ?>
                    <script type="module" src="<?php echo htmlspecialchars($devBase . 'main.tsx'); ?>"></script>
                <?php endif; ?>
            <?php else: ?>
                <div class="edu-shell">
                    <div class="edu-topbar">
                        <div>
                            <h1 class="edu-title">Bienvenido <?php echo htmlspecialchars($academyLabel); ?></h1>
                        </div>
                        <div class="edu-userbar">
                            <button class="edu-icon-btn iu-theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo oscuro" title="Cambiar a modo oscuro">
                                <i class="fas fa-moon" data-theme-icon></i>
                            </button>
                            <div class="edu-notify iu-notify" data-notify data-notify-user="<?php echo (int) $userId; ?>">
                                <button class="edu-icon-btn" type="button" data-notify-toggle aria-label="Notificaciones">
                                    <i class="fas fa-bell"></i>
                                    <span class="iu-notify-badge" data-notify-badge hidden></span>
                                </button>
                                <div class="edu-notify-panel iu-notify-panel" data-notify-panel>
                                    <div class="iu-notify-header">
                                        <span>Notificaciones</span>
                                        <label class="iu-notify-toggle">
                                            <input type="checkbox" data-notify-enabled>
                                            <span data-notify-status>Activadas</span>
                                        </label>
                                    </div>
                                    <div class="iu-notify-list" data-notify-list>
                                        <div class="iu-notify-item">Cargando...</div>
                                    </div>
                                    <div class="iu-notify-footer">
                                        <button type="button" class="iu-notify-action" data-notify-clear>Marcar como leídas</button>
                                    </div>
                                </div>
                            </div>
                            <div class="edu-user">
                                <span class="edu-avatar"><?php echo strtoupper(substr($userLabel, 0, 1)); ?></span>
                                <span class="edu-user-name"><?php echo htmlspecialchars($userLabel ?: 'Usuario'); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="edu-tabs">
                        <a href="dashboard.php" class="edu-tab-item edu-tab-active"><i class="fas fa-th-large"></i> Dashboard</a>
                        <a href="calendar.php" class="edu-tab-item"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($ui['tab_calendar']); ?></a>
                        <a href="calendar.php" class="edu-tab-item"><i class="far fa-clock"></i> <?php echo htmlspecialchars($ui['tab_classes']); ?></a>
                        <a href="admin_panel.php?page=courses" class="edu-tab-item"><i class="fas fa-book"></i> <?php echo htmlspecialchars($ui['tab_courses']); ?></a>
                        <a href="entregas.php" class="edu-tab-item"><i class="fas fa-upload"></i> <?php echo htmlspecialchars($ui['tab_deliveries']); ?></a>
                    </div>

                    <div class="edu-stat-grid">
                        <div class="edu-stat-card">
                            <div class="edu-stat-head">
                                <span class="edu-stat-icon"><i class="far fa-clipboard"></i></span>
                                <span><?php echo htmlspecialchars($ui['stat_pending_title']); ?></span>
                            </div>
                            <div class="edu-stat-value"><?php echo number_format($pendingTasks); ?></div>
                            <div class="edu-stat-sub"><?php echo htmlspecialchars($ui['stat_pending_sub']); ?></div>
                        </div>
                        <div class="edu-stat-card">
                            <div class="edu-stat-head">
                                <span class="edu-stat-icon"><i class="far fa-bell"></i></span>
                                <span><?php echo htmlspecialchars($ui['stat_notify_title']); ?></span>
                            </div>
                            <div class="edu-stat-value"><?php echo number_format($pendingGrades); ?></div>
                            <div class="edu-stat-sub"><?php echo htmlspecialchars($ui['stat_notify_sub']); ?></div>
                        </div>
                        <div class="edu-stat-card">
                            <div class="edu-stat-head">
                                <span class="edu-stat-icon"><i class="far fa-calendar-check"></i></span>
                                <span><?php echo htmlspecialchars($ui['stat_upcoming_title']); ?></span>
                            </div>
                            <div class="edu-stat-value"><?php echo number_format($upcomingClasses); ?></div>
                            <div class="edu-stat-sub"><?php echo htmlspecialchars($ui['stat_upcoming_sub']); ?></div>
                        </div>
                        <div class="edu-stat-card">
                            <div class="edu-stat-head">
                                <span class="edu-stat-icon"><i class="far fa-file-alt"></i></span>
                                <span><?php echo htmlspecialchars($ui['stat_due_title']); ?></span>
                            </div>
                            <div class="edu-stat-value"><?php echo number_format($upcomingDue); ?></div>
                            <div class="edu-stat-sub"><?php echo htmlspecialchars($ui['stat_due_sub']); ?></div>
                        </div>
                    </div>

                    <?php if ($isSalonDashboard): ?>
                        <div class="edu-card" id="citas-module" style="margin-bottom: 18px;">
                            <div class="edu-card-body">
                                <?php
                                if (!defined('IUC_CITAS_EMBED')) {
                                    define('IUC_CITAS_EMBED', true);
                                }
                                $iucCitasOptions = [
                                    'tenantId' => $academy_id > 0 ? (string) $academy_id : 'default',
                                    'businessName' => $academyLabel,
                                    'whatsappNumber' => '',
                                    'currency' => 'EUR',
                                    'slotSize' => 30,
                                    'isAdmin' => $isTenantAdmin,
                                    'embed' => true,
                                ];
                                include __DIR__ . '/citas.php';
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isSalonDashboard): ?>
                        <div class="edu-card">
                            <div class="edu-card-header">
                                <span><?php echo htmlspecialchars($ui['next_event_title']); ?></span>
                            </div>
                            <div class="edu-card-body">
                                <?php if ($nextEventTitle == ''): ?>
                                    <div class="edu-empty"><?php echo htmlspecialchars($ui['next_event_empty']); ?></div>
                                <?php else: ?>
                                    <div class="edu-next-event">
                                        <div class="edu-date-badge">
                                            <div class="edu-date-day"><?php echo htmlspecialchars($nextEventDay); ?></div>
                                            <div class="edu-date-month"><?php echo htmlspecialchars($nextEventMonth); ?></div>
                                        </div>
                                        <div>
                                            <div class="edu-next-title"><?php echo htmlspecialchars($nextEventTitle); ?></div>
                                            <div class="edu-next-meta">
                                                <?php echo htmlspecialchars($nextEventLabel != '' ? $nextEventLabel : 'Horario pendiente'); ?>
                                                <?php if ($nextEventTime != ''): ?>
                                                    - <?php echo htmlspecialchars($nextEventTime); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-arrow-right" style="margin-left:auto; color:#94a3b8;"></i>
                                    </div>
                                    <?php if (!empty($nextEventList)): ?>
                                        <div class="edu-list" style="margin-top: 12px;">
                                            <?php foreach ($nextEventList as $item): ?>
                                                <div class="edu-list-item">
                                                    <div>
                                                        <div class="edu-list-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                        <div class="edu-card-muted"><?php echo htmlspecialchars($item['label']); ?></div>
                                                    </div>
                                                    <span class="edu-card-muted"><?php echo htmlspecialchars($ui['next_event_badge']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="edu-card-muted" style="margin-top: 10px;">
                                            <i class="far fa-calendar"></i> <?php echo htmlspecialchars($ui['next_event_more_empty']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="edu-layout">
                            <div class="edu-left">
                                <div class="edu-card">
                                    <div class="edu-card-header">
                                        <span><?php echo htmlspecialchars($ui['community_title']); ?></span>
                                        <span class="edu-card-muted"><?php echo htmlspecialchars($ui['community_sub']); ?></span>
                                    </div>
                                    <div class="edu-card-body">
                                        <?php if (!empty($communityError)): ?>
                                            <div class="edu-empty" style="border-style: solid; background: #fef2f2; color: #b91c1c;">
                                                <?php echo htmlspecialchars($communityError); ?>
                                            </div>
                                        <?php elseif (!empty($communitySuccess)): ?>
                                            <div class="edu-empty" style="border-style: solid; background: #ecfdf3; color: #047857;">
                                                <?php echo htmlspecialchars($communitySuccess); ?>
                                            </div>
                                        <?php endif; ?>
                                        <form class="edu-community-form" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="community_post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <div class="edu-community-input">
                                                <input type="text" name="community_content" placeholder="<?php echo htmlspecialchars($ui['community_placeholder']); ?>" />
                                                <label class="edu-attach" title="Adjuntar archivo">
                                                    <input type="file" name="community_attachment">
                                                    <i class="fas fa-paperclip"></i>
                                                </label>
                                                <button class="edu-btn" type="submit">Publicar</button>
                                            </div>
                                        </form>
                                        <?php if (empty($communityPosts)): ?>
                                            <div class="edu-empty" style="margin-top: 12px;">No hay publicaciones recientes.</div>
                                        <?php else: ?>
                                            <?php foreach ($communityPosts as $post): ?>
                                                <div class="edu-post">
                                                    <div class="edu-post-avatar"><?php echo strtoupper(substr((string) ($post['username'] ?? 'U'), 0, 1)); ?></div>
                                                    <div>
                                                        <div class="edu-list-title"><?php echo htmlspecialchars($post['username'] ?: $post['email']); ?></div>
                                                        <div class="edu-post-meta"><?php echo htmlspecialchars(date('d M H:i', strtotime((string) $post['created_at']))); ?></div>
                                                        <div style="margin-top: 6px;"><?php echo nl2br(htmlspecialchars((string) $post['content'])); ?></div>
                                                        <?php if (!empty($post['attachment_url'])): ?>
                                                            <div style="margin-top: 6px;">
                                                                <a class="edu-link" href="<?php echo htmlspecialchars($post['attachment_url']); ?>" target="_blank" rel="noopener">
                                                                    <i class="fas fa-paperclip"></i>
                                                                    <?php echo htmlspecialchars($post['attachment_name'] ?? 'Adjunto'); ?>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                        <form class="edu-post-actions" method="POST">
                                                            <input type="hidden" name="action" value="community_like">
                                                            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                                            <button class="edu-pill <?php echo ((int) $post['liked'] > 0) ? 'edu-pill-active' : ''; ?>" type="submit">
                                                                <i class="far fa-heart"></i>
                                                                <?php echo (int) $post['like_count']; ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="edu-card">
                                    <div class="edu-card-header">
                                        <span><?php echo htmlspecialchars($ui['chat_title']); ?></span>
                                        <a class="edu-btn" href="messages.php"><?php echo htmlspecialchars($ui['chat_button']); ?></a>
                                    </div>
                                    <div class="edu-card-body">
                                        <?php if (empty($educationChatThreads)): ?>
                                            <div class="edu-empty"><?php echo htmlspecialchars($ui['chat_empty']); ?></div>
                                        <?php else: ?>
                                            <?php foreach ($educationChatThreads as $thread): ?>
                                                <div class="edu-chat-item">
                                                    <div class="edu-post-avatar"><?php echo strtoupper(substr((string) ($thread['name'] ?? 'C'), 0, 1)); ?></div>
                                                    <div>
                                                        <div class="edu-list-title"><?php echo htmlspecialchars($thread['name'] ?: 'Chat'); ?></div>
                                                        <div class="edu-chat-meta">
                                                            <?php echo htmlspecialchars($thread['type'] ?: 'grupo'); ?>
                                                            - <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $thread['last_activity_at']))); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <div class="edu-chat-input">
                                            <input type="text" placeholder="<?php echo htmlspecialchars($ui['chat_placeholder']); ?>" />
                                            <button type="button" class="edu-chat-send" aria-label="Enviar">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="edu-card">
                                    <div class="edu-card-header">
                                        <span><?php echo htmlspecialchars($ui['files_title']); ?></span>
                                        <a class="edu-btn" href="biblioteca.php"><?php echo htmlspecialchars($ui['files_button']); ?></a>
                                    </div>
                                    <div class="edu-card-body">
                                        <?php if (empty($recentFiles)): ?>
                                            <div class="edu-empty"><?php echo htmlspecialchars($ui['files_empty']); ?></div>
                                        <?php else: ?>
                                            <div class="edu-list">
                                                <?php foreach (array_slice($recentFiles, 0, 4) as $file): ?>
                                                    <div class="edu-list-item">
                                                        <div>
                                                            <div class="edu-list-title"><?php echo htmlspecialchars($file['title']); ?></div>
                                                            <div class="edu-card-muted"><?php echo htmlspecialchars($file['label']); ?></div>
                                                        </div>
                                                        <i class="fas fa-download"></i>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="edu-right">
                                <div class="edu-card">
                                    <div class="edu-card-header">
                                        <span><?php echo htmlspecialchars($ui['calendar_title']); ?></span>
                                        <div class="edu-card-muted">
                                            <a class="edu-link" href="<?php echo htmlspecialchars($miniCalendarPrevUrl); ?>"><i class="fas fa-chevron-left"></i></a>
                                            <span style="margin: 0 6px;"><?php echo htmlspecialchars($miniCalendarMonthLabel); ?></span>
                                            <a class="edu-link" href="<?php echo htmlspecialchars($miniCalendarNextUrl); ?>"><i class="fas fa-chevron-right"></i></a>
                                        </div>
                                    </div>
                                    <div class="edu-card-body">
                                        <div class="edu-cal">
                                            <div class="edu-cal-grid" style="font-weight:600; color:#94a3b8;">
                                                <span class="edu-cal-day">Lun</span>
                                                <span class="edu-cal-day">Mar</span>
                                                <span class="edu-cal-day">Mie</span>
                                                <span class="edu-cal-day">Jue</span>
                                                <span class="edu-cal-day">Vie</span>
                                                <span class="edu-cal-day">Sab</span>
                                                <span class="edu-cal-day">Dom</span>
                                            </div>
                                            <?php foreach ($miniCalendarWeeks as $week): ?>
                                                <div class="edu-cal-grid">
                                                    <?php foreach ($week as $day): ?>
                                                        <?php
                                                        $dayClasses = 'edu-cal-day';
                                                        if (empty($day['is_current'])) {
                                                            $dayClasses .= ' edu-cal-day--muted';
                                                        }
                                                        if (!empty($day['is_today'])) {
                                                            $dayClasses .= ' edu-cal-day--today';
                                                        } elseif (!empty($day['has_event'])) {
                                                            $dayClasses .= ' edu-cal-day--marked';
                                                        }
                                                        ?>
                                                        <span class="<?php echo $dayClasses; ?>"><?php echo (int) $day['day']; ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="edu-row">
                                    <div class="edu-card" id="edu-announcements">
                                        <div class="edu-card-header">
                                            <span><?php echo htmlspecialchars($ui['announcements_title']); ?></span>
                                        </div>
                                        <div class="edu-card-body">
                                            <?php if (!empty($announcements)): ?>
                                                <div class="edu-list">
                                                    <?php foreach ($announcements as $announcement): ?>
                                                        <div class="edu-list-item">
                                                            <div>
                                                                <div class="edu-list-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                                                <div class="edu-card-muted"><?php echo htmlspecialchars($announcement['message']); ?></div>
                                                            </div>
                                                            <span class="edu-card-muted"><?php echo htmlspecialchars(date('d M', strtotime((string) $announcement['created_at']))); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="edu-empty">No hay avisos por el momento</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="edu-card">
                                        <div class="edu-card-header">
                                            <span><?php echo htmlspecialchars($ui['activity_title']); ?></span>
                                        </div>
                                        <div class="edu-card-body">
                                            <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                                                <span class="edu-pill edu-pill-active">Pendiente</span>
                                                <span class="edu-pill">En progreso</span>
                                                <span class="edu-pill">Completado</span>
                                            </div>
                                            <?php if (empty($activityItems)): ?>
                                                <div class="edu-empty">Sin actividad reciente</div>
                                            <?php else: ?>
                                                <div class="edu-list">
                                                    <?php foreach (array_slice($activityItems, 0, 3) as $item): ?>
                                                        <div class="edu-list-item">
                                                            <div>
                                                                <div class="edu-list-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                                <div class="edu-card-muted"><?php echo htmlspecialchars($ui['activity_label']); ?></div>
                                                            </div>
                                                            <div style="text-align: right;">
                                                                <div class="edu-card-muted"><?php echo htmlspecialchars($item['due'] ?? 'Sin fecha'); ?></div>
                                                                <a class="edu-link" href="<?php echo htmlspecialchars($item['url']); ?>">Ver</a>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                    <div class="edu-layout">
                        <div class="edu-left">
                            <div class="edu-card">
                                <div class="edu-card-header">
                                    <span><?php echo htmlspecialchars($ui['community_title']); ?></span>
                                    <span class="edu-card-muted"><?php echo htmlspecialchars($ui['community_sub']); ?></span>
                                </div>
                                <div class="edu-card-body">
                                    <?php if (!empty($communityError)): ?>
                                        <div class="edu-empty" style="border-style: solid; background: #fef2f2; color: #b91c1c;">
                                            <?php echo htmlspecialchars($communityError); ?>
                                        </div>
                                    <?php elseif (!empty($communitySuccess)): ?>
                                        <div class="edu-empty" style="border-style: solid; background: #ecfdf3; color: #047857;">
                                            <?php echo htmlspecialchars($communitySuccess); ?>
                                        </div>
                                    <?php endif; ?>
                                    <form class="edu-community-form" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="community_post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <div class="edu-community-input">
                                            <input type="text" name="community_content" placeholder="<?php echo htmlspecialchars($ui['community_placeholder']); ?>" />
                                            <label class="edu-attach" title="Adjuntar archivo">
                                                <input type="file" name="community_attachment">
                                                <i class="fas fa-paperclip"></i>
                                            </label>
                                            <button class="edu-btn" type="submit">Publicar</button>
                                        </div>
                                    </form>
                                    <?php if (empty($communityPosts)): ?>
                                        <div class="edu-empty" style="margin-top: 12px;">No hay publicaciones recientes.</div>
                                    <?php else: ?>
                                        <?php foreach ($communityPosts as $post): ?>
                                            <div class="edu-post">
                                                <div class="edu-post-avatar"><?php echo strtoupper(substr((string) ($post['username'] ?? 'U'), 0, 1)); ?></div>
                                                <div>
                                                    <div class="edu-list-title"><?php echo htmlspecialchars($post['username'] ?: $post['email']); ?></div>
                                                    <div class="edu-post-meta"><?php echo htmlspecialchars(date('d M H:i', strtotime((string) $post['created_at']))); ?></div>
                                                    <div style="margin-top: 6px;"><?php echo nl2br(htmlspecialchars((string) $post['content'])); ?></div>
                                                    <?php if (!empty($post['attachment_url'])): ?>
                                                        <div style="margin-top: 6px;">
                                                            <a class="edu-link" href="<?php echo htmlspecialchars($post['attachment_url']); ?>" target="_blank" rel="noopener">
                                                                <i class="fas fa-paperclip"></i>
                                                                <?php echo htmlspecialchars($post['attachment_name'] ?? 'Adjunto'); ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                    <form class="edu-post-actions" method="POST">
                                                        <input type="hidden" name="action" value="community_like">
                                                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                                        <button class="edu-pill <?php echo ((int) $post['liked'] > 0) ? 'edu-pill-active' : ''; ?>" type="submit">
                                                            <i class="far fa-heart"></i>
                                                            <?php echo (int) $post['like_count']; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="edu-row">
                                <div class="edu-card" id="edu-announcements">
                                    <div class="edu-card-header">
                                        <span><?php echo htmlspecialchars($ui['announcements_title']); ?></span>
                                    </div>
                                    <div class="edu-card-body">
                                        <?php if (!empty($announcements)): ?>
                                            <div class="edu-list">
                                                <?php foreach ($announcements as $announcement): ?>
                                                    <div class="edu-list-item">
                                                        <div>
                                                            <div class="edu-list-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                                            <div class="edu-card-muted"><?php echo htmlspecialchars($announcement['message']); ?></div>
                                                        </div>
                                                        <span class="edu-card-muted"><?php echo htmlspecialchars(date('d M', strtotime((string) $announcement['created_at']))); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="edu-empty">No hay avisos por el momento</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="edu-card">
                                    <div class="edu-card-header">
                                        <span><?php echo htmlspecialchars($ui['activity_title']); ?></span>
                                    </div>
                                    <div class="edu-card-body">
                                        <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                                            <span class="edu-pill edu-pill-active">Pendiente</span>
                                            <span class="edu-pill">En progreso</span>
                                            <span class="edu-pill">Completado</span>
                                        </div>
                                        <?php if (empty($activityItems)): ?>
                                            <div class="edu-empty">Sin actividad reciente</div>
                                        <?php else: ?>
                                            <div class="edu-list">
                                                <?php foreach (array_slice($activityItems, 0, 3) as $item): ?>
                                                    <div class="edu-list-item">
                                                        <div>
                                                            <div class="edu-list-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                            <div class="edu-card-muted"><?php echo htmlspecialchars($ui['activity_label']); ?></div>
                                                        </div>
                                                        <div style="text-align: right;">
                                                            <div class="edu-card-muted"><?php echo htmlspecialchars($item['due'] ?? 'Sin fecha'); ?></div>
                                                            <a class="edu-link" href="<?php echo htmlspecialchars($item['url']); ?>">Ver</a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="edu-card">
                                <div class="edu-card-header">
                                    <span><?php echo htmlspecialchars($ui['files_title']); ?></span>
                                    <a class="edu-btn" href="biblioteca.php"><?php echo htmlspecialchars($ui['files_button']); ?></a>
                                </div>
                                <div class="edu-card-body">
                                    <?php if (empty($recentFiles)): ?>
                                        <div class="edu-empty"><?php echo htmlspecialchars($ui['files_empty']); ?></div>
                                    <?php else: ?>
                                        <div class="edu-list">
                                            <?php foreach (array_slice($recentFiles, 0, 4) as $file): ?>
                                                <div class="edu-list-item">
                                                    <div>
                                                        <div class="edu-list-title"><?php echo htmlspecialchars($file['title']); ?></div>
                                                        <div class="edu-card-muted"><?php echo htmlspecialchars($file['label']); ?></div>
                                                    </div>
                                                    <i class="fas fa-download"></i>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="edu-right">
                            <div class="edu-card">
                                <div class="edu-card-header">
                                    <span><?php echo htmlspecialchars($ui['next_event_title']); ?></span>
                                </div>
                                <div class="edu-card-body">
                                    <?php if ($nextEventTitle == ''): ?>
                                        <div class="edu-empty"><?php echo htmlspecialchars($ui['next_event_empty']); ?></div>
                                    <?php else: ?>
                                        <div class="edu-next-event">
                                            <div class="edu-date-badge">
                                                <div class="edu-date-day"><?php echo htmlspecialchars($nextEventDay); ?></div>
                                                <div class="edu-date-month"><?php echo htmlspecialchars($nextEventMonth); ?></div>
                                            </div>
                                            <div>
                                                <div class="edu-next-title"><?php echo htmlspecialchars($nextEventTitle); ?></div>
                                                <div class="edu-next-meta">
                                                    <?php echo htmlspecialchars($nextEventLabel != '' ? $nextEventLabel : 'Horario pendiente'); ?>
                                                    <?php if ($nextEventTime != ''): ?>
                                                        � <?php echo htmlspecialchars($nextEventTime); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <i class="fas fa-arrow-right" style="margin-left:auto; color:#94a3b8;"></i>
                                        </div>
                                        <?php if (!empty($nextEventList)): ?>
                                            <div class="edu-list" style="margin-top: 12px;">
                                                <?php foreach ($nextEventList as $item): ?>
                                                    <div class="edu-list-item">
                                                        <div>
                                                            <div class="edu-list-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                            <div class="edu-card-muted"><?php echo htmlspecialchars($item['label']); ?></div>
                                                        </div>
                                                        <span class="edu-card-muted"><?php echo htmlspecialchars($ui['next_event_badge']); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="edu-card-muted" style="margin-top: 10px;">
                                                <i class="far fa-calendar"></i> <?php echo htmlspecialchars($ui['next_event_more_empty']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="edu-card">
                                <div class="edu-card-header">
                                    <span><?php echo htmlspecialchars($ui['calendar_title']); ?></span>
                                    <div class="edu-card-muted">
                                        <a class="edu-link" href="<?php echo htmlspecialchars($miniCalendarPrevUrl); ?>"><i class="fas fa-chevron-left"></i></a>
                                        <span style="margin: 0 6px;"><?php echo htmlspecialchars($miniCalendarMonthLabel); ?></span>
                                        <a class="edu-link" href="<?php echo htmlspecialchars($miniCalendarNextUrl); ?>"><i class="fas fa-chevron-right"></i></a>
                                    </div>
                                </div>
                                <div class="edu-card-body">
                                    <div class="edu-cal">
                                        <div class="edu-cal-grid" style="font-weight:600; color:#94a3b8;">
                                            <span class="edu-cal-day">Lun</span>
                                            <span class="edu-cal-day">Mar</span>
                                            <span class="edu-cal-day">Mie</span>
                                            <span class="edu-cal-day">Jue</span>
                                            <span class="edu-cal-day">Vie</span>
                                            <span class="edu-cal-day">Sab</span>
                                            <span class="edu-cal-day">Dom</span>
                                        </div>
                                        <?php foreach ($miniCalendarWeeks as $week): ?>
                                            <div class="edu-cal-grid">
                                                <?php foreach ($week as $day): ?>
                                                    <?php
                                                    $dayClasses = 'edu-cal-day';
                                                    if (empty($day['is_current'])) {
                                                        $dayClasses .= ' edu-cal-day--muted';
                                                    }
                                                    if (!empty($day['is_today'])) {
                                                        $dayClasses .= ' edu-cal-day--today';
                                                    } elseif (!empty($day['has_event'])) {
                                                        $dayClasses .= ' edu-cal-day--marked';
                                                    }
                                                    ?>
                                                    <span class="<?php echo $dayClasses; ?>"><?php echo (int) $day['day']; ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="edu-card">
                                <div class="edu-card-header">
                                    <span><?php echo htmlspecialchars($ui['chat_title']); ?></span>
                                    <a class="edu-btn" href="messages.php"><?php echo htmlspecialchars($ui['chat_button']); ?></a>
                                </div>
                                <div class="edu-card-body">
                                    <?php if (empty($educationChatThreads)): ?>
                                        <div class="edu-empty"><?php echo htmlspecialchars($ui['chat_empty']); ?></div>
                                    <?php else: ?>
                                        <?php foreach ($educationChatThreads as $thread): ?>
                                            <div class="edu-chat-item">
                                                <div class="edu-post-avatar"><?php echo strtoupper(substr((string) ($thread['name'] ?? 'C'), 0, 1)); ?></div>
                                                <div>
                                                    <div class="edu-list-title"><?php echo htmlspecialchars($thread['name'] ?: 'Chat'); ?></div>
                                                    <div class="edu-chat-meta">
                                                        <?php echo htmlspecialchars($thread['type'] ?: 'grupo'); ?>
                                                        � <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $thread['last_activity_at']))); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <div class="edu-chat-input">
                                        <input type="text" placeholder="<?php echo htmlspecialchars($ui['chat_placeholder']); ?>" />
                                        <button type="button" class="edu-chat-send" aria-label="Enviar">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
<?php else: ?>
                <?php include __DIR__ . '/admin/views/dashboard.php'; ?>
            <?php endif; ?>

<?php if ($templateCode === 'EDUCATION'): ?>
<!-- Drawer detalle entregas -->
<div id="adminDeliveriesDrawer" class="hidden fixed inset-0 z-40">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" data-deliveries-close></div>
    <div class="absolute right-0 top-0 bottom-0 w-full max-w-2xl bg-white shadow-2xl flex flex-col">
        <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">Detalle de entrega</p>
                <h3 id="adminDeliveriesDetailTitle" class="text-2xl font-bold text-gray-900 mt-1">-</h3>
                <div class="flex items-center gap-2 mt-2 text-sm text-gray-500">
                    <span id="adminDeliveriesDetailType" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">-</span>
                    <span id="adminDeliveriesDetailDue" class="flex items-center gap-1">
                        <i class="fas fa-clock text-gray-400"></i>
                        -
                    </span>
                </div>
            </div>
            <button class="text-gray-400 hover:text-gray-900" id="adminDeliveriesCloseDrawer">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6">
            <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-1">Descripci?n</h4>
                <p id="adminDeliveriesDetailDescription" class="text-sm text-gray-600">Sin descripci?n.</p>
            </div>
            <div id="adminDeliveriesAttachmentRow" class="hidden">
                <h4 class="text-sm font-semibold text-gray-700 mb-1">Adjunto del profesor</h4>
                <div class="inline-flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-100 rounded-xl px-3 py-2">
                    <i class="fas fa-paperclip text-gray-400"></i>
                    <span id="adminDeliveriesDetailAttachment">archivo.pdf</span>
                </div>
            </div>
            <div class="border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-users text-gray-400"></i>
                        Entregas recibidas (demo)
                    </h4>
                    <button id="adminDeliveriesResetSubmissions" class="text-xs text-gray-400 hover:text-red-600">
                        Limpiar env?os
                    </button>
                </div>
                <div id="adminDeliveriesSubmissionsList" class="space-y-3 text-sm text-gray-600">
                    <p class="text-gray-400 text-sm">Nadie ha subido todav?a.</p>
                </div>
            </div>
            <div class="border-t border-gray-100 pt-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i class="fas fa-upload text-gray-400"></i>
                    Simular env?o como alumno
                </h4>
                <form id="adminDeliveriesSubmissionForm" class="space-y-3">
                    <input type="hidden" id="adminDeliveriesActiveId">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase">Nombre del alumno</label>
                            <input type="text" id="adminDeliveriesStudentField" class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-gray-900 focus:border-gray-900 text-sm" placeholder="Ej. Ana P?rez">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase">Archivo</label>
                            <input type="file" id="adminDeliveriesStudentFile" class="w-full mt-1 text-sm text-gray-600">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 uppercase">Comentario</label>
                        <textarea id="adminDeliveriesStudentNote" rows="2" class="w-full mt-1 px-3 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-gray-900 focus:border-gray-900 text-sm" placeholder="Comentario opcional..."></textarea>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 bg-gray-900 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-800 transition">
                            <i class="fas fa-paper-plane"></i>
                            Subir demo
                        </button>
                        <span class="text-xs text-gray-400">Los archivos reales no se guardan; solo el nombre.</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('adminDeliveriesCard');
    if (!card) return;

    const STORAGE_KEYS = {
        ASSIGNMENTS: 'admin_deliveries_assignments',
        SUBMISSIONS: 'admin_deliveries_submissions'
    };

    const alertBox = document.getElementById('adminDeliveriesAlert');
    const form = document.getElementById('adminDeliveriesForm');
    const toggleFormBtn = document.getElementById('adminDeliveriesToggleForm');
    const cancelFormBtn = document.getElementById('adminDeliveriesCancelForm');
    const titleField = document.getElementById('adminDeliveriesTitle');
    const typeField = document.getElementById('adminDeliveriesType');
    const dueField = document.getElementById('adminDeliveriesDue');
    const descField = document.getElementById('adminDeliveriesDescription');
    const attachmentField = document.getElementById('adminDeliveriesAttachment');
    const listContainer = document.getElementById('adminDeliveriesList');
    const counter = document.getElementById('adminDeliveriesCounter');

    const drawer = document.getElementById('adminDeliveriesDrawer');
    const drawerCloseBtn = document.getElementById('adminDeliveriesCloseDrawer');
    const drawerOverlay = drawer.querySelector('[data-deliveries-close]');
    const detailTitle = document.getElementById('adminDeliveriesDetailTitle');
    const detailType = document.getElementById('adminDeliveriesDetailType');
    const detailDue = document.getElementById('adminDeliveriesDetailDue');
    const detailDescription = document.getElementById('adminDeliveriesDetailDescription');
    const attachmentRow = document.getElementById('adminDeliveriesAttachmentRow');
    const detailAttachment = document.getElementById('adminDeliveriesDetailAttachment');
    const submissionsList = document.getElementById('adminDeliveriesSubmissionsList');
    const submissionForm = document.getElementById('adminDeliveriesSubmissionForm');
    const submissionName = document.getElementById('adminDeliveriesStudentField');
    const submissionFile = document.getElementById('adminDeliveriesStudentFile');
    const submissionNote = document.getElementById('adminDeliveriesStudentNote');
    const activeAssignmentInput = document.getElementById('adminDeliveriesActiveId');
    const resetSubmissionsBtn = document.getElementById('adminDeliveriesResetSubmissions');

    const requiredElements = [
        form,
        toggleFormBtn,
        cancelFormBtn,
        titleField,
        typeField,
        dueField,
        listContainer,
        counter,
        drawer,
        drawerCloseBtn,
        drawerOverlay,
        detailTitle,
        detailType,
        detailDue,
        detailDescription,
        submissionsList,
        submissionForm,
        submissionName,
        submissionFile,
        activeAssignmentInput,
        resetSubmissionsBtn
    ];
    if (requiredElements.some(el => !el)) {
        return;
    }

    const loadFromStorage = (key, fallback) => {
        try {
            const raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (err) {
            return fallback;
        }
    };
    const saveToStorage = (key, value) => {
        localStorage.setItem(key, JSON.stringify(value));
    };

    let assignments = loadFromStorage(STORAGE_KEYS.ASSIGNMENTS, []);
    let submissions = loadFromStorage(STORAGE_KEYS.SUBMISSIONS, []);

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.textContent = message;
        alertBox.className = 'mb-4 text-sm rounded-xl px-3 py-2 border';
        alertBox.classList.remove('hidden');
        if (type === 'error') {
            alertBox.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
        } else {
            alertBox.classList.add('border-green-200', 'bg-green-50', 'text-green-700');
        }
        setTimeout(() => alertBox.classList.add('hidden'), 3500);
    };

    const getNextId = list => {
        let max = 0;
        list.forEach(item => {
            if (item.id > max) max = item.id;
        });
        return max + 1;
    };

    const formatDateShort = value => {
        if (!value) return 'Sin fecha';
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return date.toLocaleString('es-ES', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const diffInfo = value => {
        if (!value) return { label: 'Sin fecha', className: 'text-gray-400' };
        const due = new Date(value);
        if (isNaN(due.getTime())) return { label: value, className: 'text-gray-400' };
        const now = new Date();
        const diffMs = due.getTime() - now.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        if (diffMs < 0) {
            return { label: 'Vencida', className: 'text-red-600' };
        }
        if (diffDays === 0) {
            return { label: 'Para hoy', className: 'text-orange-500' };
        }
        if (diffDays === 1) {
            return { label: 'Ma?ana', className: 'text-amber-500' };
        }
        if (diffDays < 5) {
            return { label: `En ${diffDays} d?as`, className: 'text-yellow-500' };
        }
        return { label: 'Programada', className: 'text-green-600' };
    };

    const typeLabel = type => {
        if (type === 'examen') return 'Examen';
        if (type === 'practica') return 'Pr?ctica';
        return 'Tarea';
    };

    const typeBadgeClasses = type => {
        if (type === 'examen') return 'bg-red-50 text-red-700';
        if (type === 'practica') return 'bg-emerald-50 text-emerald-700';
        return 'bg-gray-900 text-white';
    };

    const renderAssignments = () => {
        if (!listContainer) return;
        listContainer.innerHTML = '';
        if (!assignments.length) {
            listContainer.innerHTML = '<p class="text-gray-400 text-center py-3 text-sm">No hay entregas creadas.</p>';
            counter.textContent = '0 activas';
            return;
        }
        const sorted = [...assignments].sort((a, b) => {
            const da = a.dueDate ? new Date(a.dueDate).getTime() : Infinity;
            const db = b.dueDate ? new Date(b.dueDate).getTime() : Infinity;
            return da - db;
        });
        sorted.forEach(assignment => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left border border-gray-200 rounded-2xl px-4 py-3 hover:border-gray-900 hover:shadow-md transition bg-white';
            const status = diffInfo(assignment.dueDate);
            const submissionsCount = submissions.filter(sub => sub.assignmentId === assignment.id).length;
            btn.innerHTML = `
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-900 line-clamp-1">${assignment.title}</p>
                        <p class="text-xs text-gray-500">${formatDateShort(assignment.dueDate)}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${typeBadgeClasses(assignment.type)}">
                            ${typeLabel(assignment.type)}
                        </span>
                        <p class="text-xs ${status.className} mt-1">${status.label}</p>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                    <span>${assignment.description ? assignment.description.substring(0, 70) + (assignment.description.length > 70 ? '?' : '') : 'Sin descripci?n'}</span>
                    <span class="flex items-center gap-1 text-gray-400">
                        <i class="fas fa-inbox"></i>
                        ${submissionsCount}
                    </span>
                </div>
            `;
            btn.addEventListener('click', () => openDrawer(assignment.id));
            listContainer.appendChild(btn);
        });
        counter.textContent = `${assignments.length} activa${assignments.length === 1 ? '' : 's'}`;
    };

    const openDrawer = assignmentId => {
        const assignment = assignments.find(item => item.id === assignmentId);
        if (!assignment) return;
        activeAssignmentInput.value = assignment.id;
        detailTitle.textContent = assignment.title;
        detailType.textContent = typeLabel(assignment.type);
        detailType.className = `inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${typeBadgeClasses(assignment.type)}`;
        const status = diffInfo(assignment.dueDate);
        detailDue.innerHTML = `<i class="fas fa-clock text-gray-400"></i> ${formatDateShort(assignment.dueDate)} ? <span class="${status.className}">${status.label}</span>`;
        detailDescription.textContent = assignment.description || 'Sin descripci?n.';
        if (assignment.attachmentName) {
            attachmentRow.classList.remove('hidden');
            detailAttachment.textContent = assignment.attachmentName;
        } else {
            attachmentRow.classList.add('hidden');
        }
        submissionName.value = '';
        submissionFile.value = '';
        submissionNote.value = '';
        renderSubmissionsForAssignment(assignment.id);
        drawer.classList.remove('hidden');
    };

    const closeDrawer = () => {
        drawer.classList.add('hidden');
        activeAssignmentInput.value = '';
    };

    const renderSubmissionsForAssignment = assignmentId => {
        if (!submissionsList) return;
        const list = submissions
            .filter(sub => sub.assignmentId === assignmentId)
            .sort((a, b) => new Date(b.submittedAt) - new Date(a.submittedAt));
        if (!list.length) {
            submissionsList.innerHTML = '<p class="text-gray-400 text-sm">Nadie ha subido todav?a.</p>';
            return;
        }
        submissionsList.innerHTML = '';
        list.forEach(sub => {
            const item = document.createElement('div');
            item.className = 'border border-gray-100 rounded-2xl px-4 py-3 bg-gray-50';
            item.innerHTML = `
                <div class="flex items-center justify-between text-sm">
                    <div class="font-semibold text-gray-900">${sub.studentName || 'Alumno demo'}</div>
                    <button type="button" class="text-xs text-red-500 hover:text-red-700" data-remove-sub="${sub.id}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                    <i class="fas fa-paperclip"></i>
                    ${sub.fileName || 'Archivo sin nombre'}
                </div>
                <div class="text-xs text-gray-400 mt-1">
                    ${formatDateShort(sub.submittedAt)}
                </div>
                ${sub.note ? `<p class="text-xs text-gray-600 mt-2 bg-white px-3 py-2 rounded-xl">${sub.note}</p>` : ''}
            `;
            item.querySelector('[data-remove-sub]').addEventListener('click', () => {
                submissions = submissions.filter(record => record.id !== sub.id);
                saveToStorage(STORAGE_KEYS.SUBMISSIONS, submissions);
                renderSubmissionsForAssignment(assignmentId);
                renderAssignments();
                showAlert('Entrega eliminada de la demo.', 'success');
            });
            submissionsList.appendChild(item);
        });
    };

    toggleFormBtn.addEventListener('click', () => {
        form.classList.toggle('hidden');
    });

    cancelFormBtn.addEventListener('click', () => {
        form.classList.add('hidden');
        form.reset();
        if (attachmentField) attachmentField.value = '';
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        const title = titleField.value.trim();
        const type = typeField.value;
        const dueDate = dueField.value;
        if (!title || !dueDate) {
            showAlert('T?tulo y fecha l?mite son obligatorios.', 'error');
            return;
        }
        const newAssignment = {
            id: getNextId(assignments),
            title,
            type,
            dueDate,
            description: descField.value.trim(),
            attachmentName: attachmentField && attachmentField.files.length ? attachmentField.files[0].name : ''
        };
        assignments.push(newAssignment);
        saveToStorage(STORAGE_KEYS.ASSIGNMENTS, assignments);
        form.reset();
        if (attachmentField) attachmentField.value = '';
        form.classList.add('hidden');
        renderAssignments();
        showAlert('Entrega creada correctamente.', 'success');
    });

    drawerCloseBtn.addEventListener('click', closeDrawer);
    drawerOverlay.addEventListener('click', closeDrawer);

    submissionForm.addEventListener('submit', event => {
        event.preventDefault();
        const assignmentId = parseInt(activeAssignmentInput.value, 10);
        if (!assignmentId) {
            showAlert('Selecciona una entrega para subir la tarea.', 'error');
            return;
        }
        const fileName = submissionFile.files.length ? submissionFile.files[0].name : '';
        if (!fileName) {
            showAlert('Selecciona un archivo para simular la subida.', 'error');
            return;
        }
        const newSubmission = {
            id: getNextId(submissions),
            assignmentId,
            studentName: submissionName.value.trim() || 'Alumno demo',
            fileName,
            note: submissionNote.value.trim(),
            submittedAt: new Date().toISOString()
        };
        submissions.push(newSubmission);
        saveToStorage(STORAGE_KEYS.SUBMISSIONS, submissions);
        submissionName.value = '';
        submissionNote.value = '';
        submissionFile.value = '';
        renderSubmissionsForAssignment(assignmentId);
        renderAssignments();
        showAlert('Entrega registrada (simulaci?n).', 'success');
    });

    resetSubmissionsBtn.addEventListener('click', () => {
        const assignmentId = parseInt(activeAssignmentInput.value, 10);
        if (!assignmentId) return;
        if (!confirm('?Seguro que deseas limpiar las entregas de esta tarea?')) return;
        submissions = submissions.filter(sub => sub.assignmentId !== assignmentId);
        saveToStorage(STORAGE_KEYS.SUBMISSIONS, submissions);
        renderSubmissionsForAssignment(assignmentId);
        renderAssignments();
        showAlert('Todas las entregas de la demo fueron eliminadas.', 'success');
    });

    renderAssignments();
});
</script>
<?php endif; ?>
<?php endif; ?>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target) &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
            }
        });
    }
</script>
</div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('section') === 'citas') {
        const target = document.getElementById('citas-module');
        if (target) {
            const container = document.getElementById('dashboard-content');
            if (container && target.parentElement !== container) {
                container.prepend(target);
            }
            document.body.classList.add('show-citas');
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }

    const list = document.getElementById('calendarPreviewList');
    const expandBtn = document.querySelector('[data-calendar-action="expand"]');
    const collapseBtn = document.querySelector('[data-calendar-action="collapse"]');
    if (list) {
        const setVisible = visible => {
            if (visible) {
                list.removeAttribute('hidden');
            } else {
                list.setAttribute('hidden', '');
            }
        };
        if (expandBtn) {
            expandBtn.addEventListener('click', () => setVisible(true));
        }
        if (collapseBtn) {
            collapseBtn.addEventListener('click', () => setVisible(false));
        }
    }

    const notifyContainers = document.querySelectorAll('[data-notify]');
    if (notifyContainers.length) {
        const userId = notifyContainers[0].dataset.notifyUser || '0';
        const enabledKey = `iu_notifications_enabled_${userId}`;
        const toastKey = `iu_notifications_toast_${userId}`;
        const getEnabled = () => localStorage.getItem(enabledKey) !== '0';
        const setEnabled = (value) => {
            localStorage.setItem(enabledKey, value ? '1' : '0');
        };

        let cached = null;
        let lastFetch = 0;
        let toastStack = null;

        const ensureToastStack = () => {
            if (toastStack) return toastStack;
            toastStack = document.getElementById('iuToastStack');
            if (!toastStack) {
                toastStack = document.createElement('div');
                toastStack.id = 'iuToastStack';
                toastStack.className = 'iu-toast-stack';
                document.body.appendChild(toastStack);
            }
            return toastStack;
        };

        const loadShown = () => {
            try {
                const raw = localStorage.getItem(toastKey);
                const list = raw ? JSON.parse(raw) : [];
                return new Set(Array.isArray(list) ? list : []);
            } catch (err) {
                return new Set();
            }
        };
        const saveShown = (set) => {
            const list = Array.from(set).slice(-120);
            localStorage.setItem(toastKey, JSON.stringify(list));
        };

        const formatDate = (value) => {
            if (!value) return '';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString('es-ES', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        const showToast = (notification) => {
            if (!notification || !notification.message) return;
            const stack = ensureToastStack();
            const toast = document.createElement('div');
            toast.className = 'iu-toast';

            const title = document.createElement('div');
            title.className = 'iu-toast-title';
            title.textContent = 'Notificación';

            const body = document.createElement('div');
            body.textContent = notification.message;

            const time = document.createElement('div');
            time.className = 'iu-toast-time';
            time.textContent = formatDate(notification.created_at);

            toast.appendChild(title);
            toast.appendChild(body);
            if (time.textContent) {
                toast.appendChild(time);
            }

            stack.prepend(toast);

            window.setTimeout(() => {
                toast.classList.add('is-hiding');
            }, 4200);
            window.setTimeout(() => {
                toast.remove();
            }, 4600);
        };

        const showToasts = (notifications) => {
            if (!getEnabled()) return;
            const list = Array.isArray(notifications) ? notifications : [];
            if (!list.length) return;
            const shown = loadShown();
            const fresh = list.filter((item) => item && item.id && !shown.has(item.id));
            fresh.slice(0, 3).forEach((item) => {
                shown.add(item.id);
                showToast(item);
            });
            if (fresh.length) {
                saveShown(shown);
            }
        };

        const renderList = (container, data, enabled) => {
            const list = container.querySelector('[data-notify-list]');
            const badge = container.querySelector('[data-notify-badge]');
            const clearBtn = container.querySelector('[data-notify-clear]');
            if (!list) return;

            list.innerHTML = '';

            if (!enabled) {
                const item = document.createElement('div');
                item.className = 'iu-notify-item';
                item.textContent = 'Notificaciones desactivadas.';
                list.appendChild(item);
                if (badge) badge.hidden = true;
                if (clearBtn) clearBtn.disabled = true;
                return;
            }

            const notifications = (data && Array.isArray(data.notifications)) ? data.notifications : [];
            if (!notifications.length) {
                const item = document.createElement('div');
                item.className = 'iu-notify-item';
                item.textContent = 'No hay notificaciones nuevas.';
                list.appendChild(item);
            } else {
                notifications.forEach((notification) => {
                    const item = document.createElement('div');
                    item.className = 'iu-notify-item';

                    const message = document.createElement('div');
                    message.textContent = notification.message || 'Notificación';

                    const time = document.createElement('div');
                    time.className = 'iu-notify-time';
                    time.textContent = formatDate(notification.created_at);

                    if (notification.link_url) {
                        const link = document.createElement('a');
                        link.className = 'iu-notify-link';
                        link.href = notification.link_url;
                        link.appendChild(message);
                        link.appendChild(time);
                        item.appendChild(link);
                    } else {
                        item.appendChild(message);
                        item.appendChild(time);
                    }

                    list.appendChild(item);
                });
            }

            const totalUnread = Math.max(0, parseInt((data && data.total_unread != null) ? data.total_unread : 0, 10));
            if (badge) badge.hidden = totalUnread === 0;
            if (clearBtn) clearBtn.disabled = totalUnread === 0;
        };

        const updateStatus = (container, enabled) => {
            const toggle = container.querySelector('[data-notify-enabled]');
            const status = container.querySelector('[data-notify-status]');
            if (toggle) toggle.checked = enabled;
            if (status) status.textContent = enabled ? 'Activadas' : 'Desactivadas';
        };

        const fetchNotifications = async (force = false) => {
            if (!getEnabled()) {
                return { notifications: [], total_unread: 0 };
            }
            const now = Date.now();
            if (!force && cached && (now - lastFetch) < 30000) {
                return cached;
            }
            try {
                const response = await fetch('notifications_api.php?limit=5', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data && data.success) {
                    cached = data;
                    lastFetch = now;
                    return data;
                }
            } catch (err) {
            }
            return cached || { notifications: [], total_unread: 0 };
        };

        const refreshAll = async (force = false) => {
            const enabled = getEnabled();
            if (!enabled) {
                notifyContainers.forEach((container) => {
                    updateStatus(container, false);
                    renderList(container, { notifications: [], total_unread: 0 }, false);
                });
                return;
            }
            const hadCache = !!cached;
            const data = await fetchNotifications(force);
            if (force || !hadCache) {
                showToasts(data ? data.notifications : []);
            }
            notifyContainers.forEach((container) => {
                updateStatus(container, true);
                renderList(container, data, true);
            });
        };

        const clearAll = async () => {
            try {
                await fetch('mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({})
                });
                cached = { notifications: [], total_unread: 0 };
                lastFetch = Date.now();
            } catch (err) {
            }
            refreshAll(true);
        };

        notifyContainers.forEach((container) => {
            const toggleBtn = container.querySelector('[data-notify-toggle]');
            const panel = container.querySelector('[data-notify-panel]');
            const enabledToggle = container.querySelector('[data-notify-enabled]');
            const clearBtn = container.querySelector('[data-notify-clear]');

            const enabled = getEnabled();
            updateStatus(container, enabled);
            renderList(container, cached || { notifications: [], total_unread: 0 }, enabled);

            if (toggleBtn && panel) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    panel.classList.toggle('is-open');
                    panel.classList.toggle('active');
                    if (panel.classList.contains('is-open') || panel.classList.contains('active')) {
                        refreshAll(true);
                    }
                });
            }

            if (enabledToggle) {
                enabledToggle.addEventListener('change', () => {
                    const isEnabled = enabledToggle.checked;
                    setEnabled(isEnabled);
                    updateStatus(container, isEnabled);
                    if (isEnabled) {
                        refreshAll(true);
                    } else {
                        renderList(container, { notifications: [], total_unread: 0 }, false);
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    clearAll();
                });
            }
        });

        const POLL_INTERVAL = 20000;
        let pollId = null;

        const startPolling = () => {
            if (pollId) return;
            pollId = window.setInterval(() => {
                if (document.hidden) return;
                refreshAll(true);
            }, POLL_INTERVAL);
        };

        const stopPolling = () => {
            if (!pollId) return;
            window.clearInterval(pollId);
            pollId = null;
        };

        refreshAll();
        startPolling();

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPolling();
                return;
            }
            startPolling();
            refreshAll(true);
        });

        document.addEventListener('click', (e) => {
            notifyContainers.forEach((container) => {
                const panel = container.querySelector('[data-notify-panel]');
                const toggleBtn = container.querySelector('[data-notify-toggle]');
                if (!panel || !toggleBtn) return;
                if (!panel.contains(e.target) && !toggleBtn.contains(e.target)) {
                    panel.classList.remove('is-open', 'active');
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            notifyContainers.forEach((container) => {
                const panel = container.querySelector('[data-notify-panel]');
                if (panel) {
                    panel.classList.remove('is-open', 'active');
                }
            });
        });
    }
});
</script>
<script>
(() => {
    const storageKey = 'iu_theme_mode';
    const darkClass = 'dark-mode';
    const body = document.body;
    if (!body) return;

    const readStored = () => {
        try {
            return localStorage.getItem(storageKey);
        } catch (err) {
            return null;
        }
    };

    const storedTheme = readStored();
    let isDark = storedTheme === 'dark';

    const updateButtons = () => {
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            const nextLabel = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
            btn.setAttribute('aria-label', nextLabel);
            btn.setAttribute('title', nextLabel);
            const label = btn.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = isDark ? 'Claro' : 'Oscuro';
            }
            const icon = btn.querySelector('[data-theme-icon]');
            if (icon) {
                icon.classList.toggle('fa-sun', isDark);
                icon.classList.toggle('fa-moon', !isDark);
            }
        });
    };

    const applyTheme = () => {
        body.classList.toggle(darkClass, isDark);
        if (body.classList.contains('iuc-theme')) {
            body.classList.toggle('iuc-theme-light', !isDark);
        }
        updateButtons();
    };

    const persistTheme = () => {
        try {
            localStorage.setItem(storageKey, isDark ? 'dark' : 'light');
        } catch (err) {
        }
    };

    const toggleTheme = () => {
        isDark = !isDark;
        applyTheme();
        persistTheme();
    };

    applyTheme();
    let updateScheduled = false;
    const scheduleUpdate = () => {
        if (updateScheduled) return;
        updateScheduled = true;
        setTimeout(() => {
            updateScheduled = false;
            updateButtons();
        }, 0);
    };
    if (window.MutationObserver) {
        const observer = new MutationObserver(() => {
            scheduleUpdate();
        });
        observer.observe(body, { childList: true, subtree: true });
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-theme-toggle]');
        if (!trigger) return;
        event.preventDefault();
        toggleTheme();
    });
})();
</script>
</body>
</html>
