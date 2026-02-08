
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
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
    die('Error de conexión: ' . $e->getMessage());
}
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';
$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'core.calendar');
$user_id     = (int) $currentUser['id'];
$username    = $currentUser['username'];
$userRole    = $currentUser['role'];
$academy_id  = $currentUser['academy_id'];
$academyName = $currentUser['academy_name'] ?: ($currentUser['academy'] ?? 'Mi academia');
function findColumnByPreference(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($columns[$key])) {
            return $columns[$key];
        }
    }
    return null;
}
function quoteIdentifier(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}
function humanizeDueDate(?string $dateString): string {
    if (empty($dateString)) {
        return 'Sin fecha definida';
    }
    try {
        $dueDate = new DateTime($dateString);
        $today = new DateTime('today');
    } catch (Exception $e) {
        return $dateString;
    }
    $diffDays = (int) $today->diff($dueDate)->format('%r%a');
    if ($diffDays === 0) {
        return 'Para hoy';
    }
    if ($diffDays === 1) {
        return 'Para mañana';
    }
    if ($diffDays === -1) {
        return 'Venció ayer';
    }
    if ($diffDays > 1 && $diffDays <= 7) {
        return 'En ' . $diffDays . ' días';
    }
    if ($diffDays < -1 && $diffDays >= -7) {
        return 'Hace ' . abs($diffDays) . ' días';
    }
    return $dueDate->format('d M Y');
}
function formatEventTimeLabel(?string $dateTimeString): string {
    if (empty($dateTimeString)) {
        return 'Sin hora';
    }
    try {
        $dateTime = new DateTime($dateTimeString);
        return $dateTime->format('H:i');
    } catch (Exception $e) {
        return $dateTimeString;
    }
}
function getHeatClassByCount(int $count): string {
    if ($count >= 5) {
        return 'heat-high';
    }
    if ($count >= 3) {
        return 'heat-medium';
    }
    if ($count >= 1) {
        return 'heat-low';
    }
    return '';
}
function getEntryDotClass(array $item): string {
    return $item['type'] === 'task' ? 'dot-task' : 'dot-event';
}
function getCalendarKey(array $item): string {
    $type = $item['type'] ?? 'event';
    if ($type === 'task') {
        return 'task-default';
    }
    $priority = strtolower(trim($item['priority'] ?? ''));
    if (in_array($priority, ['alta', 'high', 'urgente'], true)) {
        return 'event-high';
    }
    if (in_array($priority, ['baja', 'low'], true)) {
        return 'event-low';
    }
    return 'event-normal';
}
$calendarLegend = [
    'event-high' => [
        'label' => 'Eventos prioridad alta',
        'pill_class' => 'pill-high',
        'week_class' => 'week-high',
        'dot_class' => 'dot-high'
    ],
    'event-normal' => [
        'label' => 'Eventos prioridad normal',
        'pill_class' => 'pill-normal',
        'week_class' => 'week-normal',
        'dot_class' => 'dot-normal'
    ],
    'event-low' => [
        'label' => 'Eventos prioridad baja',
        'pill_class' => 'pill-low',
        'week_class' => 'week-low',
        'dot_class' => 'dot-low'
    ],
    'task-default' => [
        'label' => 'Tareas',
        'pill_class' => 'pill-task',
        'week_class' => 'week-task',
        'dot_class' => 'dot-task-color'
    ],
];
$flashMessages = $_SESSION['calendar_flash'] ?? [];
if (!empty($flashMessages)) {
    unset($_SESSION['calendar_flash']);
}
$eventsSchema = [
    'available' => false,
    'user_column' => null,
    'academy_column' => null,
    'title_column' => null,
    'start_column' => null,
    'end_column' => null,
    'description_column' => null,
    'priority_column' => null,
];
try {
    $eventsColumns = [];
    $eventsColsStmt = $pdo->query('SHOW COLUMNS FROM events');
    while ($col = $eventsColsStmt->fetch(PDO::FETCH_ASSOC)) {
        $eventsColumns[strtolower($col['Field'])] = $col['Field'];
    }
    if (!empty($eventsColumns)) {
        $eventsSchema['user_column'] = $eventsColumns['user_id']
            ?? findColumnByPreference($eventsColumns, ['owner_id','student_id','created_by']);
        $eventsSchema['academy_column'] = $eventsColumns['academy_id']
            ?? findColumnByPreference($eventsColumns, ['academia_id','school_id','campus_id']);
        $eventsSchema['title_column'] = $eventsColumns['title']
            ?? findColumnByPreference($eventsColumns, ['name','event_title','subject']);
        $eventsSchema['start_column'] = $eventsColumns['start']
            ?? findColumnByPreference($eventsColumns, ['start_date','start_at','fecha_inicio']);
        $eventsSchema['end_column'] = $eventsColumns['end']
            ?? findColumnByPreference($eventsColumns, ['end_date','end_at','fecha_fin','finish']);
        $eventsSchema['description_column'] = $eventsColumns['description']
            ?? findColumnByPreference($eventsColumns, ['details','detail','content','body','notes']);
        $eventsSchema['priority_column'] = $eventsColumns['priority']
            ?? findColumnByPreference($eventsColumns, ['importance','nivel','tipo']);
        if ($eventsSchema['user_column'] && $eventsSchema['title_column'] && $eventsSchema['start_column']) {
            $eventsSchema['available'] = true;
        }
    }
} catch (PDOException $e) {
    $eventsSchema['available'] = false;
}
$tasksSchema = [
    'available' => false,
    'user_column' => null,
    'academy_column' => null,
    'title_column' => null,
    'description_column' => null,
    'due_date_column' => null,
    'status_column' => null,
    'completed_column' => null,
    'is_completed_column' => null,
];
try {
    $taskColumns = [];
    $taskColsStmt = $pdo->query('SHOW COLUMNS FROM tasks');
    while ($col = $taskColsStmt->fetch(PDO::FETCH_ASSOC)) {
        $taskColumns[strtolower($col['Field'])] = $col['Field'];
    }
    if (!empty($taskColumns)) {
        $tasksSchema['user_column'] = $taskColumns['user_id']
            ?? findColumnByPreference($taskColumns, ['owner_id','student_id','created_by']);
        $tasksSchema['academy_column'] = $taskColumns['academy_id']
            ?? findColumnByPreference($taskColumns, ['academia_id','school_id','campus_id']);
        $tasksSchema['title_column'] = $taskColumns['title']
            ?? findColumnByPreference($taskColumns, ['name','task_title','subject']);
        $tasksSchema['description_column'] = $taskColumns['description']
            ?? findColumnByPreference($taskColumns, ['details','detail','body','notes']);
        $tasksSchema['due_date_column'] = $taskColumns['due_date']
            ?? findColumnByPreference($taskColumns, ['deadline','delivery_date','end_date']);
        $tasksSchema['status_column'] = $taskColumns['status']
            ?? findColumnByPreference($taskColumns, ['state','stage']);
        $tasksSchema['completed_column'] = $taskColumns['completed'] ?? null;
        $tasksSchema['is_completed_column'] = $taskColumns['is_completed']
            ?? findColumnByPreference($taskColumns, ['done','finalizado']);
        if ($tasksSchema['user_column'] && $tasksSchema['title_column']) {
            $tasksSchema['available'] = true;
        }
    }
} catch (PDOException $e) {
    $tasksSchema['available'] = false;
}
$eventErrors = [];
$taskErrors = [];
$todayDate = date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_event') {
        $eventTitle = trim($_POST['event_title'] ?? '');
        $eventPriority = trim($_POST['event_priority'] ?? 'Normal');
        $eventDescription = trim($_POST['event_description'] ?? '');
        $eventStartInput = trim($_POST['event_start'] ?? '');
        $eventEndInput = trim($_POST['event_end'] ?? '');
        if ($eventTitle === '') {
            $eventErrors[] = 'El título del evento es obligatorio.';
        }
        $eventStart = null;
        if ($eventStartInput !== '') {
            $startDate = DateTime::createFromFormat('Y-m-d\TH:i', $eventStartInput);
            if ($startDate) {
                $eventStart = $startDate->format('Y-m-d H:i:s');
            } else {
                $eventErrors[] = 'La fecha y hora de inicio no tienen un formato válido.';
            }
        } else {
            $eventErrors[] = 'Debes indicar una fecha y hora de inicio.';
        }
        $eventEnd = null;
        if ($eventEndInput !== '') {
            $endDate = DateTime::createFromFormat('Y-m-d\TH:i', $eventEndInput);
            if ($endDate) {
                $eventEnd = $endDate->format('Y-m-d H:i:s');
            } else {
                $eventErrors[] = 'La fecha y hora de fin no tienen un formato válido.';
            }
        }
        if (!$eventsSchema['available']) {
            $eventErrors[] = 'La tabla de eventos no está disponible.';
        }
        if (!$eventErrors) {
            try {
                $insertColumns = [
                    $eventsSchema['user_column'] => ':user_id',
                    $eventsSchema['title_column'] => ':title',
                    $eventsSchema['start_column'] => ':start_at',
                ];
                $params = [
                    ':user_id' => $user_id,
                    ':title' => $eventTitle,
                    ':start_at' => $eventStart
                ];
                if ($eventsSchema['academy_column']) {
                    $insertColumns[$eventsSchema['academy_column']] = ':academy_id';
                    $params[':academy_id'] = $academy_id;
                }
                if ($eventsSchema['end_column']) {
                    $insertColumns[$eventsSchema['end_column']] = ':end_at';
                    $params[':end_at'] = $eventEnd ?: $eventStart;
                }
                if ($eventsSchema['description_column']) {
                    $insertColumns[$eventsSchema['description_column']] = ':description';
                    $params[':description'] = $eventDescription;
                }
                if ($eventsSchema['priority_column']) {
                    $insertColumns[$eventsSchema['priority_column']] = ':priority';
                    $params[':priority'] = $eventPriority !== '' ? $eventPriority : 'Normal';
                }
                $columnsSql = [];
                $valuesSql = [];
                foreach ($insertColumns as $column => $placeholder) {
                    $columnsSql[] = quoteIdentifier($column);
                    $valuesSql[] = $placeholder;
                }
                $sqlInsertEvent = "
                    INSERT INTO events (" . implode(', ', $columnsSql) . ")
                    VALUES (" . implode(', ', $valuesSql) . ")
                ";
                $stmt = $pdo->prepare($sqlInsertEvent);
                $stmt->execute($params);
                if (!isset($_SESSION['calendar_flash']) || !is_array($_SESSION['calendar_flash'])) {
                    $_SESSION['calendar_flash'] = [];
                }
                $_SESSION['calendar_flash'][] = [
                    'type' => 'success',
                    'message' => 'Evento creado correctamente.'
                ];
                header('Location: calendar.php');
                exit();
            } catch (PDOException $e) {
                $eventErrors[] = 'No se pudo guardar el evento.';
            }
        }
    } elseif ($action === 'update_event') {
        $eventId = (int) ($_POST['item_id'] ?? 0);
        $eventTitle = trim($_POST['event_title'] ?? '');
        $eventPriority = trim($_POST['event_priority'] ?? 'Normal');
        $eventDescription = trim($_POST['event_description'] ?? '');
        $eventStartInput = trim($_POST['event_start'] ?? '');
        $eventEndInput = trim($_POST['event_end'] ?? '');
        if ($eventId <= 0) {
            $eventErrors[] = 'No se pudo identificar el evento a editar.';
        }
        if ($eventTitle === '') {
            $eventErrors[] = 'El título del evento es obligatorio.';
        }
        $eventStart = null;
        if ($eventStartInput !== '') {
            $startDate = DateTime::createFromFormat('Y-m-d\TH:i', $eventStartInput);
            if ($startDate) {
                $eventStart = $startDate->format('Y-m-d H:i:s');
            } else {
                $eventErrors[] = 'La fecha y hora de inicio no tienen un formato válido.';
            }
        } else {
            $eventErrors[] = 'Debes indicar una fecha y hora de inicio.';
        }
        $eventEnd = null;
        if ($eventEndInput !== '') {
            $endDate = DateTime::createFromFormat('Y-m-d\TH:i', $eventEndInput);
            if ($endDate) {
                $eventEnd = $endDate->format('Y-m-d H:i:s');
            } else {
                $eventErrors[] = 'La fecha y hora de fin no tienen un formato válido.';
            }
        }
        if (!$eventsSchema['available']) {
            $eventErrors[] = 'La tabla de eventos no está disponible.';
        }
        if (!$eventsSchema['user_column']) {
            $eventErrors[] = 'No se puede editar este evento.';
        }
        if (!$eventErrors) {
            try {
                $setParts = [];
                $params = [
                    ':id' => $eventId,
                    ':user_id' => $user_id
                ];
                if ($eventsSchema['title_column']) {
                    $setParts[] = quoteIdentifier($eventsSchema['title_column']) . ' = :title';
                    $params[':title'] = $eventTitle;
                }
                if ($eventsSchema['start_column']) {
                    $setParts[] = quoteIdentifier($eventsSchema['start_column']) . ' = :start_at';
                    $params[':start_at'] = $eventStart;
                }
                if ($eventsSchema['end_column']) {
                    $setParts[] = quoteIdentifier($eventsSchema['end_column']) . ' = :end_at';
                    $params[':end_at'] = $eventEnd ?: $eventStart;
                }
                if ($eventsSchema['priority_column']) {
                    $setParts[] = quoteIdentifier($eventsSchema['priority_column']) . ' = :priority';
                    $params[':priority'] = $eventPriority !== '' ? $eventPriority : 'Normal';
                }
                if ($eventsSchema['description_column']) {
                    $setParts[] = quoteIdentifier($eventsSchema['description_column']) . ' = :description';
                    $params[':description'] = $eventDescription;
                }
                $userColumn = quoteIdentifier($eventsSchema['user_column']);
                $sqlUpdateEvent = "
                    UPDATE events
                    SET " . implode(', ', $setParts) . "
                    WHERE id = :id AND {$userColumn} = :user_id
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sqlUpdateEvent);
                $stmt->execute($params);
                if (!isset($_SESSION['calendar_flash']) || !is_array($_SESSION['calendar_flash'])) {
                    $_SESSION['calendar_flash'] = [];
                }
                $_SESSION['calendar_flash'][] = [
                    'type' => 'success',
                    'message' => 'Evento actualizado correctamente.'
                ];
                header('Location: calendar.php');
                exit();
            } catch (PDOException $e) {
                $eventErrors[] = 'No se pudo actualizar el evento.';
            }
        }
    } elseif ($action === 'create_task') {
        $taskTitle = trim($_POST['task_title'] ?? '');
        $taskDescription = trim($_POST['task_description'] ?? '');
        $taskDueDateInput = trim($_POST['task_due_date'] ?? '');
        $taskStatus = trim($_POST['task_status'] ?? 'Pendiente');
        if ($taskTitle === '') {
            $taskErrors[] = 'El título de la tarea es obligatorio.';
        }
        $taskDueDate = null;
        if ($taskDueDateInput !== '') {
            $dueDate = DateTime::createFromFormat('Y-m-d', $taskDueDateInput);
            if ($dueDate) {
                $taskDueDate = $dueDate->format('Y-m-d');
            } else {
                $taskErrors[] = 'La fecha límite no tiene un formato válido.';
            }
        }
        if (!$tasksSchema['available']) {
            $taskErrors[] = 'La tabla de tareas no está disponible.';
        }
        if (!$taskErrors) {
            try {
                $insertColumns = [
                    $tasksSchema['user_column'] => ':user_id',
                    $tasksSchema['title_column'] => ':task_title',
                ];
                $params = [
                    ':user_id' => $user_id,
                    ':task_title' => $taskTitle
                ];
                if ($tasksSchema['academy_column']) {
                    $insertColumns[$tasksSchema['academy_column']] = ':academy_id';
                    $params[':academy_id'] = $academy_id;
                }
                if ($tasksSchema['description_column']) {
                    $insertColumns[$tasksSchema['description_column']] = ':description';
                    $params[':description'] = $taskDescription;
                }
                if ($tasksSchema['due_date_column']) {
                    $insertColumns[$tasksSchema['due_date_column']] = ':due_date';
                    $params[':due_date'] = $taskDueDate;
                }
                if ($tasksSchema['status_column']) {
                    $insertColumns[$tasksSchema['status_column']] = ':status';
                    $params[':status'] = $taskStatus !== '' ? $taskStatus : 'Pendiente';
                }
                if ($tasksSchema['completed_column']) {
                    $insertColumns[$tasksSchema['completed_column']] = ':completed';
                    $params[':completed'] = 0;
                }
                if ($tasksSchema['is_completed_column'] && $tasksSchema['is_completed_column'] !== $tasksSchema['completed_column']) {
                    $insertColumns[$tasksSchema['is_completed_column']] = ':is_completed';
                    $params[':is_completed'] = 0;
                }
                $columnsSql = [];
                $valuesSql = [];
                foreach ($insertColumns as $column => $placeholder) {
                    $columnsSql[] = quoteIdentifier($column);
                    $valuesSql[] = $placeholder;
                }
                $sqlInsertTask = "
                    INSERT INTO tasks (" . implode(', ', $columnsSql) . ")
                    VALUES (" . implode(', ', $valuesSql) . ")
                ";
                $stmt = $pdo->prepare($sqlInsertTask);
                $stmt->execute($params);
                if (!isset($_SESSION['calendar_flash']) || !is_array($_SESSION['calendar_flash'])) {
                    $_SESSION['calendar_flash'] = [];
                }
                $_SESSION['calendar_flash'][] = [
                    'type' => 'success',
                    'message' => 'Tarea creada correctamente.'
                ];
                header('Location: calendar.php');
                exit();
            } catch (PDOException $e) {
                $taskErrors[] = 'No se pudo guardar la tarea.';
            }
        }
    } elseif ($action === 'update_task') {
        $taskId = (int) ($_POST['item_id'] ?? 0);
        $taskTitle = trim($_POST['task_title'] ?? '');
        $taskDescription = trim($_POST['task_description'] ?? '');
        $taskDueDateInput = trim($_POST['task_due_date'] ?? '');
        $taskStatus = trim($_POST['task_status'] ?? 'Pendiente');
        if ($taskId <= 0) {
            $taskErrors[] = 'No se pudo identificar la tarea a editar.';
        }
        if ($taskTitle === '') {
            $taskErrors[] = 'El título de la tarea es obligatorio.';
        }
        $taskDueDate = null;
        if ($taskDueDateInput !== '') {
            $dueDate = DateTime::createFromFormat('Y-m-d', $taskDueDateInput);
            if ($dueDate) {
                $taskDueDate = $dueDate->format('Y-m-d');
            } else {
                $taskErrors[] = 'La fecha límite no tiene un formato válido.';
            }
        }
        if (!$tasksSchema['available']) {
            $taskErrors[] = 'La tabla de tareas no está disponible.';
        }
        if (!$tasksSchema['user_column']) {
            $taskErrors[] = 'No se puede editar esta tarea.';
        }
        if (!$taskErrors) {
            try {
                $setParts = [];
                $params = [
                    ':id' => $taskId,
                    ':user_id' => $user_id
                ];
                if ($tasksSchema['title_column']) {
                    $setParts[] = quoteIdentifier($tasksSchema['title_column']) . ' = :task_title';
                    $params[':task_title'] = $taskTitle;
                }
                if ($tasksSchema['description_column']) {
                    $setParts[] = quoteIdentifier($tasksSchema['description_column']) . ' = :description';
                    $params[':description'] = $taskDescription;
                }
                if ($tasksSchema['due_date_column']) {
                    $setParts[] = quoteIdentifier($tasksSchema['due_date_column']) . ' = :due_date';
                    $params[':due_date'] = $taskDueDate;
                }
                if ($tasksSchema['status_column']) {
                    $setParts[] = quoteIdentifier($tasksSchema['status_column']) . ' = :status';
                    $params[':status'] = $taskStatus !== '' ? $taskStatus : 'Pendiente';
                }
                $userColumn = quoteIdentifier($tasksSchema['user_column']);
                $sqlUpdateTask = "
                    UPDATE tasks
                    SET " . implode(', ', $setParts) . "
                    WHERE id = :id AND {$userColumn} = :user_id
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sqlUpdateTask);
                $stmt->execute($params);
                if (!isset($_SESSION['calendar_flash']) || !is_array($_SESSION['calendar_flash'])) {
                    $_SESSION['calendar_flash'] = [];
                }
                $_SESSION['calendar_flash'][] = [
                    'type' => 'success',
                    'message' => 'Tarea actualizada correctamente.'
                ];
                header('Location: calendar.php');
                exit();
            } catch (PDOException $e) {
                $taskErrors[] = 'No se pudo actualizar la tarea.';
            }
        }
    } elseif ($action === 'move_calendar_item') {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $entryType = $_POST['entry_type'] ?? '';
        $newDate = trim($_POST['new_date'] ?? '');
        $newTime = trim($_POST['new_time'] ?? '');
        if ($entryId > 0 && $newDate !== '') {
            if ($entryType === 'event' && $eventsSchema['available'] && $eventsSchema['start_column'] && $eventsSchema['user_column']) {
                $startColumn = quoteIdentifier($eventsSchema['start_column']);
                $userColumn = quoteIdentifier($eventsSchema['user_column']);
                $endColumn = $eventsSchema['end_column'] ? quoteIdentifier($eventsSchema['end_column']) : null;
                $sqlFetchEvent = "
                    SELECT {$startColumn} AS start_value" . ($endColumn ? ", {$endColumn} AS end_value" : "") . "
                    FROM events
                    WHERE id = :id AND {$userColumn} = :user_id
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sqlFetchEvent);
                $stmt->execute([
                    ':id' => $entryId,
                    ':user_id' => $user_id
                ]);
                $eventRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($eventRow && !empty($eventRow['start_value'])) {
                    try {
                        $originalStart = new DateTime($eventRow['start_value']);
                        $durationSeconds = 0;
                        if ($endColumn && !empty($eventRow['end_value'])) {
                            $originalEnd = new DateTime($eventRow['end_value']);
                            $durationSeconds = max(0, $originalEnd->getTimestamp() - $originalStart->getTimestamp());
                        }
                        $timeComponent = $newTime !== '' ? $newTime : $originalStart->format('H:i');
                        $newStart = DateTime::createFromFormat('Y-m-d H:i', $newDate . ' ' . $timeComponent);
                        if (!$newStart) {
                            throw new Exception('invalid date');
                        }
                        $params = [
                            ':id' => $entryId,
                            ':user_id' => $user_id,
                            ':new_start' => $newStart->format('Y-m-d H:i:s')
                        ];
                        $setParts = ["{$startColumn} = :new_start"];
                        if ($endColumn) {
                            if ($durationSeconds <= 0) {
                                $newEnd = clone $newStart;
                            } else {
                                $newEnd = (clone $newStart)->modify("+{$durationSeconds} seconds");
                            }
                            $params[':new_end'] = $newEnd->format('Y-m-d H:i:s');
                            $setParts[] = "{$endColumn} = :new_end";
                        }
                        $sqlUpdateEvent = "
                            UPDATE events
                            SET " . implode(', ', $setParts) . "
                            WHERE id = :id AND {$userColumn} = :user_id
                            LIMIT 1
                        ";
                        $stmt = $pdo->prepare($sqlUpdateEvent);
                        $stmt->execute($params);
                        echo json_encode(['success' => true]);
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'Fecha u hora no válidas.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Evento no encontrado.']);
                }
                exit();
            } elseif ($entryType === 'task' && $tasksSchema['available'] && $tasksSchema['due_date_column'] && $tasksSchema['user_column']) {
                $dueColumn = quoteIdentifier($tasksSchema['due_date_column']);
                $taskUserColumn = quoteIdentifier($tasksSchema['user_column']);
                $sqlUpdateTaskDate = "
                    UPDATE tasks
                    SET {$dueColumn} = :due_date
                    WHERE id = :id AND {$taskUserColumn} = :user_id
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sqlUpdateTaskDate);
                $stmt->execute([
                    ':due_date' => $newDate,
                    ':id' => $entryId,
                    ':user_id' => $user_id
                ]);
                echo json_encode(['success' => true]);
                exit();
            }
        }
        echo json_encode(['success' => false, 'message' => 'No se pudo mover el elemento.']);
        exit();
    }
}
$eventsData = [];
if ($eventsSchema['available']) {
    $selectParts = [
        'e.id',
        $eventsSchema['title_column']
            ? "COALESCE(e." . quoteIdentifier($eventsSchema['title_column']) . ", 'Evento sin título') AS title"
            : "'Evento sin título' AS title",
        $eventsSchema['start_column']
            ? "e." . quoteIdentifier($eventsSchema['start_column']) . " AS start_date"
            : "NULL AS start_date",
        $eventsSchema['end_column']
            ? "e." . quoteIdentifier($eventsSchema['end_column']) . " AS end_date"
            : "NULL AS end_date",
        $eventsSchema['description_column']
            ? "e." . quoteIdentifier($eventsSchema['description_column']) . " AS description"
            : "'' AS description",
        $eventsSchema['priority_column']
            ? "e." . quoteIdentifier($eventsSchema['priority_column']) . " AS priority"
            : "'Normal' AS priority",
    ];
    $whereClauses = [];
    $params = [];
    if ($eventsSchema['user_column']) {
        $whereClauses[] = "e." . quoteIdentifier($eventsSchema['user_column']) . " = :user_id";
        $params[':user_id'] = $user_id;
    }
    $hasAcademyFilter = $eventsSchema['academy_column'] && $academy_id !== null && $academy_id !== '';
    if ($hasAcademyFilter) {
        $col = "e." . quoteIdentifier($eventsSchema['academy_column']);
        $whereClauses[] = "({$col} = :academy_id OR {$col} IS NULL)";
        $params[':academy_id'] = $academy_id;
    }
    $sqlEvents = "
        SELECT " . implode(', ', $selectParts) . "
        FROM events e
    ";
    if ($whereClauses) {
        $sqlEvents .= ' WHERE ' . implode(' AND ', $whereClauses);
    }
    $orderColumn = $eventsSchema['start_column']
        ? "e." . quoteIdentifier($eventsSchema['start_column'])
        : 'e.id';
    $sqlEvents .= " ORDER BY {$orderColumn} ASC";
    $stmt = $pdo->prepare($sqlEvents);
    $stmt->execute($params);
    $eventsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$tasksList = [];
if ($tasksSchema['available']) {
    $selectParts = [
        'id',
        $tasksSchema['title_column']
            ? quoteIdentifier($tasksSchema['title_column']) . ' AS title'
            : "'' AS title",
        $tasksSchema['description_column']
            ? quoteIdentifier($tasksSchema['description_column']) . ' AS description'
            : "'' AS description",
        $tasksSchema['due_date_column']
            ? quoteIdentifier($tasksSchema['due_date_column']) . ' AS due_date'
            : "NULL AS due_date",
        $tasksSchema['status_column']
            ? quoteIdentifier($tasksSchema['status_column']) . ' AS status'
            : "'' AS status",
    ];
    $userColumnSql = quoteIdentifier($tasksSchema['user_column']);
    $whereClause = "{$userColumnSql} = :user_id";
    $params = [':user_id' => $user_id];
    if ($tasksSchema['academy_column'] && $academy_id !== null && $academy_id !== '') {
        $whereClause .= " AND " . quoteIdentifier($tasksSchema['academy_column']) . " = :academy_id";
        $params[':academy_id'] = $academy_id;
    }
    $orderClause = $tasksSchema['due_date_column']
        ? quoteIdentifier($tasksSchema['due_date_column']) . " IS NULL, " . quoteIdentifier($tasksSchema['due_date_column']) . " ASC, id DESC"
        : 'id DESC';
    $sqlTasks = "
        SELECT " . implode(', ', $selectParts) . "
        FROM tasks
        WHERE {$whereClause}
        ORDER BY {$orderClause}
        LIMIT 200
    ";
    $stmt = $pdo->prepare($sqlTasks);
    $stmt->execute($params);
    $tasksList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$calendarItems = [];
$activityByDate = [];
foreach ($eventsData as $event) {
    if (empty($event['start_date'])) {
        continue;
    }
    try {
        $startDate = new DateTime($event['start_date']);
        $dateKey = $startDate->format('Y-m-d');
        $calendarKey = getCalendarKey([
            'type' => 'event',
            'priority' => $event['priority'] ?? ''
        ]);
        $calendarItems[] = [
            'type' => 'event',
            'id' => $event['id'],
            'title' => $event['title'] ?: 'Evento sin título',
            'date' => $dateKey,
            'time' => $startDate->format('H:i'),
            'priority' => $event['priority'] ?? 'Normal',
            'calendar_key' => $calendarKey,
            'raw_start' => $event['start_date'] ?? '',
            'raw_end' => $event['end_date'] ?? '',
            'description' => $event['description'] ?? '',
            'status' => $event['priority'] ?? '',
            'raw_due_date' => ''
        ];
        $activityByDate[$dateKey] = ($activityByDate[$dateKey] ?? 0) + 1;
    } catch (Exception $e) {
        continue;
    }
}
foreach ($tasksList as $task) {
    $dueDate = trim($task['due_date'] ?? '');
    if ($dueDate === '') {
        continue;
    }
    $taskTitle = trim($task['title'] ?? '') !== '' ? $task['title'] : 'Tarea sin título';
    $calendarKey = getCalendarKey([
        'type' => 'task',
        'priority' => $task['status'] ?? ''
    ]);
        $calendarItems[] = [
            'type' => 'task',
            'id' => $task['id'],
            'title' => $taskTitle,
            'date' => $dueDate,
            'time' => '',
            'priority' => $task['status'] ?? '',
            'calendar_key' => $calendarKey,
            'raw_start' => '',
            'raw_end' => '',
            'description' => $task['description'] ?? '',
            'status' => $task['status'] ?? '',
            'raw_due_date' => $dueDate
        ];
    $activityByDate[$dueDate] = ($activityByDate[$dueDate] ?? 0) + 1;
}
$itemsByDate = [];
foreach ($calendarItems as $item) {
    if (empty($item['date'])) {
        continue;
    }
    $itemsByDate[$item['date']][] = $item;
}
$allowedViews = ['month', 'year', 'week'];
$viewParam = $_GET['view'] ?? 'month';
$viewMode = in_array($viewParam, $allowedViews, true) ? $viewParam : 'month';
$selectedYear = (int) ($_GET['year'] ?? date('Y'));
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int) date('Y');
}
$selectedMonth = (int) ($_GET['month'] ?? date('n'));
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int) date('n');
}
$monthNames = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$dayShortLabels = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
$monthStart = new DateTime(sprintf('%d-%02d-01', $selectedYear, $selectedMonth));
$prevMonthDate = (clone $monthStart)->modify('-1 month');
$nextMonthDate = (clone $monthStart)->modify('+1 month');
$gridStart = (clone $monthStart);
if ($monthStart->format('N') !== '1') {
    $gridStart->modify('last monday');
}
$weeks = [];
$currentPointer = clone $gridStart;
for ($week = 0; $week < 6; $week++) {
    $weekRow = [];
    for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
        $dateKey = $currentPointer->format('Y-m-d');
        $weekRow[] = [
            'date' => $dateKey,
            'day' => $currentPointer->format('j'),
            'month' => (int) $currentPointer->format('n'),
            'is_current_month' => (int) $currentPointer->format('n') === $selectedMonth,
            'is_today' => $dateKey === $todayDate,
            'is_past' => $dateKey < $todayDate,
            'entries' => $itemsByDate[$dateKey] ?? []
        ];
        $currentPointer->modify('+1 day');
    }
    $weeks[] = $weekRow;
}
$annualMonths = [];
for ($m = 1; $m <= 12; $m++) {
    $monthDate = new DateTime(sprintf('%d-%02d-01', $selectedYear, $m));
    $daysInMonth = (int) $monthDate->format('t');
    $dayCells = [];
    $firstWeekday = (int) $monthDate->format('N');
    for ($i = 1; $i < $firstWeekday; $i++) {
        $dayCells[] = ['empty' => true];
    }
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateKey = sprintf('%d-%02d-%02d', $selectedYear, $m, $day);
        $dayCells[] = [
            'empty' => false,
            'day' => $day,
            'date' => $dateKey,
            'is_today' => $dateKey === $todayDate,
            'is_past' => $dateKey < $todayDate,
            'heat_class' => getHeatClassByCount($activityByDate[$dateKey] ?? 0)
        ];
    }
    while (count($dayCells) % 7 !== 0) {
        $dayCells[] = ['empty' => true];
    }
$annualMonths[] = [
        'name' => ucfirst($monthNames[$m]),
        'days' => $dayCells
    ];
}
$weekReferenceParam = $_GET['date'] ?? date('Y-m-d');
try {
    $weekReferenceDate = new DateTime($weekReferenceParam);
} catch (Exception $e) {
    $weekReferenceDate = new DateTime(date('Y-m-d'));
}
$weekStart = (clone $weekReferenceDate)->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+6 days');
$weekTitle = 'Semana del ' .
    $weekStart->format('j') . ' de ' . $monthNames[(int) $weekStart->format('n')] .
    ' al ' . $weekEnd->format('j') . ' de ' . $monthNames[(int) $weekEnd->format('n')] .
    ' ' . $weekEnd->format('Y');
$prevWeekDate = (clone $weekStart)->modify('-7 days');
$nextWeekDate = (clone $weekStart)->modify('+7 days');
$prevWeekUrl = 'calendar.php?' . http_build_query([
    'view' => 'week',
    'date' => $prevWeekDate->format('Y-m-d')
]);
$nextWeekUrl = 'calendar.php?' . http_build_query([
    'view' => 'week',
    'date' => $nextWeekDate->format('Y-m-d')
]);
$todayWeekUrl = 'calendar.php?' . http_build_query([
    'view' => 'week',
    'date' => date('Y-m-d')
]);
$weekDaysDetailed = [];
$weekAllDay = [];
$weekSchedule = [];
$weekHours = range(0, 23);
for ($i = 0; $i < 7; $i++) {
    $day = (clone $weekStart)->modify("+{$i} day");
    $dateKey = $day->format('Y-m-d');
    $weekDaysDetailed[] = [
        'label' => $day->format('D'),
        'short' => substr($day->format('D'), 0, 2),
        'day' => $day->format('j'),
        'date' => $dateKey,
        'is_today' => $dateKey === $todayDate,
        'is_past' => $dateKey < $todayDate
    ];
}
foreach ($calendarItems as $item) {
    if (empty($item['date'])) {
        continue;
    }
    $itemDate = $item['date'];
    if ($itemDate < $weekStart->format('Y-m-d') || $itemDate > $weekEnd->format('Y-m-d')) {
        continue;
    }
    if ($item['time'] === '') {
        $weekAllDay[$itemDate][] = $item;
        continue;
    }
    $hour = (int) substr($item['time'], 0, 2);
    $weekSchedule[$itemDate][$hour][] = $item;
}
for ($i = 0; $i < count($weekDaysDetailed); $i++) {
    $dateKey = $weekDaysDetailed[$i]['date'];
    $weekDaysDetailed[$i]['all_day'] = $weekAllDay[$dateKey] ?? [];
    if ($weekDaysDetailed[$i]['label'] === 'Mon') $weekDaysDetailed[$i]['label'] = 'Lun';
    if ($weekDaysDetailed[$i]['label'] === 'Tue') $weekDaysDetailed[$i]['label'] = 'Mar';
    if ($weekDaysDetailed[$i]['label'] === 'Wed') $weekDaysDetailed[$i]['label'] = 'Mié';
    if ($weekDaysDetailed[$i]['label'] === 'Thu') $weekDaysDetailed[$i]['label'] = 'Jue';
    if ($weekDaysDetailed[$i]['label'] === 'Fri') $weekDaysDetailed[$i]['label'] = 'Vie';
    if ($weekDaysDetailed[$i]['label'] === 'Sat') $weekDaysDetailed[$i]['label'] = 'Sáb';
    if ($weekDaysDetailed[$i]['label'] === 'Sun') $weekDaysDetailed[$i]['label'] = 'Dom';
    $weekDaysDetailed[$i]['short'] = $weekDaysDetailed[$i]['label'];
}
$monthTitle = ucfirst($monthNames[$selectedMonth]) . ' ' . $selectedYear;
$monthViewUrl = 'calendar.php?' . http_build_query([
    'view' => 'month',
    'year' => $selectedYear,
    'month' => $selectedMonth
]);
$yearViewUrl = 'calendar.php?' . http_build_query([
    'view' => 'year',
    'year' => $selectedYear
]);
$prevMonthUrl = 'calendar.php?' . http_build_query([
    'view' => 'month',
    'year' => (int) $prevMonthDate->format('Y'),
    'month' => (int) $prevMonthDate->format('n')
]);
$nextMonthUrl = 'calendar.php?' . http_build_query([
    'view' => 'month',
    'year' => (int) $nextMonthDate->format('Y'),
    'month' => (int) $nextMonthDate->format('n')
]);
$todayUrl = 'calendar.php?' . http_build_query([
    'view' => 'month',
    'year' => (int) date('Y'),
    'month' => (int) date('n')
]);
$prevYearUrl = 'calendar.php?' . http_build_query([
    'view' => 'year',
    'year' => $selectedYear - 1
]);
$nextYearUrl = 'calendar.php?' . http_build_query([
    'view' => 'year',
    'year' => $selectedYear + 1
]);
$weekViewUrl = 'calendar.php?' . http_build_query([
    'view' => 'week',
    'date' => $weekStart->format('Y-m-d')
]);
$calendarFormDefaultType = !empty($eventErrors) ? 'event' : (!empty($taskErrors) ? 'task' : 'event');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Learnnect</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-1FKxS1YtL6vhfV3hK2XxPxq8np5xpoE2mR7BncpsbR9f7DmqDveoxu48UUfGzSy0RKZ77N8DfszAKPf7E3GK5g==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --bg: #f4f5fb;
            --card: #ffffff;
            --border: #e4e7ee;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --accent: #111827;
            --shadow: 0 20px 45px rgba(15,23,42,0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(180deg, #f6f7fb 0%, #e7ebf4 100%);
            color: var(--text-main);
        }
        a { text-decoration: none; color: inherit; }
        .page { min-height: 100vh; padding: 2rem 4vw 3rem; }
        .page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .page-header h1 { font-size: 1.9rem; margin: 0; }
        .header-subtitle { color: var(--text-muted); font-size: 0.95rem; margin-top: 0.35rem; }
        .header-actions { display: flex; gap: 0.75rem; }
        .btn {
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 999px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 15px 30px rgba(15,23,42,0.2); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn:hover { transform: translateY(-1px); }
        .alert {
            border-radius: 18px;
            padding: 0.85rem 1.2rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        .alert-success { background: rgba(5,150,105,0.12); color: #064e3b; }
        .alert-error { background: rgba(220,38,38,0.13); color: #7f1d1d; }
        .calendar-grid {
            display: flex;
            gap: 1.75rem;
            align-items: stretch;
            flex-wrap: nowrap;
        }
        .card {
            background: var(--card);
            border-radius: 28px;
            box-shadow: var(--shadow);
            padding: 1.75rem;
        }
        .card h2 { margin: 0 0 0.5rem 0; font-size: 1.2rem; }
        .card p { margin: 0; color: var(--text-muted); }
        .calendar-main {
            padding: 2rem;
            border-radius: 32px;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
        }
        .calendar-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            flex: 0 0 360px;
            max-width: 420px;
        }
        .calendar-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .calendar-heading h2 { margin: 0; font-size: 1.75rem; }
        .calendar-heading span { color: var(--text-muted); font-size: 0.95rem; }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .view-toggle {
            display: flex;
            background: #f1f3f9;
            border-radius: 999px;
            padding: 0.25rem;
            gap: 0.25rem;
        }
        .view-toggle a {
            padding: 0.35rem 0.95rem;
            border-radius: 999px;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .view-toggle a.active {
            background: #fff;
            color: var(--text-main);
            box-shadow: 0 6px 18px rgba(15,23,42,0.12);
        }
        .nav-btn, .today-btn {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-main);
            border-radius: 14px;
            padding: 0.45rem 0.85rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .month-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .month-day-labels {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }
        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
        }
        .month-cell {
            background: #fff;
            border: 1px solid #ecedf4;
            border-radius: 22px;
            min-height: 130px;
            padding: 0.85rem;
            position: relative;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
            cursor: pointer;
        }
        .month-cell.past {
            opacity: 0.55;
        }
        .month-cell.outside {
            background: #f6f7fb;
            color: #c5c8d7;
        }
        .month-cell.today {
            border-color: #c7d2fe;
            box-shadow: 0 12px 26px rgba(99,102,241,0.25);
        }
        .day-number {
            position: absolute;
            top: 0.8rem;
            right: 0.9rem;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .month-cell .entries {
            margin-top: 1.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .month-entry {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            font-size: 0.85rem;
            line-height: 1.2;
            cursor: pointer;
        }
        .entry-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-top: 0.25rem;
        }
        .dot-event { background: #6366f1; }
        .dot-task { background: #f97316; }
        .month-entry-title { color: #111827; font-weight: 600; }
        .month-entry-time { color: var(--text-muted); font-size: 0.75rem; }
        .year-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }
        .year-month {
            background: #fff;
            border-radius: 26px;
            padding: 1.25rem 1.4rem;
            box-shadow: 0 20px 35px rgba(15,23,42,0.08);
        }
        .year-month h3 {
            margin: 0 0 0.75rem 0;
            font-size: 1.05rem;
            text-transform: capitalize;
        }
        .year-day-labels {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.35rem;
        }
        .year-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.18rem;
        }
        .year-day {
            text-align: center;
            padding: 0.35rem 0;
            border-radius: 10px;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .year-day.heat-low { background: #ffe0e5; color: #9f1239; }
        .year-day.heat-medium { background: #ffb3c1; color: #881337; }
        .year-day.heat-high { background: #ff8094; color: #7f1d1d; }
        .year-day.today {
            border: 1px solid #2563eb;
            color: #2563eb;
            background: #fff;
        }
        .year-day.empty { background: transparent; border: none; }
        .week-heading {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .week-all-day {
            display: grid;
            grid-template-columns: 120px repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
            margin-bottom: 1.25rem;
            align-items: stretch;
        }
        .week-all-day-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 0.5rem;
        }
        .week-all-day-cell {
            background: #fff;
            border-radius: 18px;
            min-height: 70px;
            border: 1px dashed var(--border);
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .week-all-day-cell.today {
            border-color: #c7d2fe;
            box-shadow: 0 8px 18px rgba(99,102,241,0.15);
        }
        .week-all-day-empty {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .week-all-day-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #eef2ff;
            color: #312e81;
            border-radius: 999px;
            padding: 0.25rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .week-all-day-pill.task {
            background: #fff4e6;
            color: #92400e;
        }
        .week-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, minmax(0, 1fr));
            border-top: 1px solid var(--border);
            border-left: 1px solid var(--border);
        }
        .week-grid-header {
            background: #f8fafc;
            padding: 0.65rem;
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            text-align: center;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .week-grid-header.day.today {
            color: #2563eb;
        }
        .week-day-label {
            display: block;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }
        .week-day-number {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .week-hour-label {
            padding: 0.65rem 0.5rem;
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .week-slot {
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            min-height: 60px;
            background: #fff;
            padding: 0.35rem;
            position: relative;
        }
        .week-grid-header.day.past,
        .week-all-day-cell.past,
        .week-slot.past {
            opacity: 0.55;
        }
        .week-event {
            background: #eef2ff;
            border-radius: 12px;
            padding: 0.3rem 0.55rem;
            font-size: 0.78rem;
            color: #312e81;
            margin-bottom: 0.35rem;
            box-shadow: 0 10px 20px rgba(79,70,229,0.15);
        }
        .week-event.is-task {
            background: #fff4e6;
            color: #92400e;
        }
        .week-event-time {
            display: block;
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        .week-event.week-high { background: #fee2e2; color: #991b1b; }
        .week-event.week-normal { background: #e0e7ff; color: #1e3a8a; }
        .week-event.week-low { background: #dcfce7; color: #065f46; }
        .week-event.week-task { background: #fff4e6; color: #92400e; }
        .week-all-day-pill.pill-high { background: #fee2e2; color: #991b1b; }
        .week-all-day-pill.pill-normal { background: #e0e7ff; color: #1e3a8a; }
        .week-all-day-pill.pill-low { background: #dcfce7; color: #065f46; }
        .week-all-day-pill.pill-task { background: #fff4e6; color: #92400e; }
        .entry-dot.dot-high { background: #ef4444; }
        .entry-dot.dot-normal { background: #4f46e5; }
        .entry-dot.dot-low { background: #10b981; }
        .entry-dot.dot-task-color { background: #f97316; }
        .week-slot.drag-over,
        .week-all-day-cell.drag-over {
            background: #e0e7ff;
            border-color: #818cf8;
        }
        .calendar-entry-hidden {
            display: none !important;
        }
        .calendar-legend {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .calendar-legend-item label {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.85rem;
            padding: 0.4rem 0.6rem;
            border-radius: 16px;
            border: 1px solid #e3e6f0;
            background: #f8f9fd;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }
        .calendar-legend-item label:hover {
            border-color: #cfd4e6;
            box-shadow: 0 4px 12px rgba(15,23,42,0.08);
        }
        .legend-inline-wrapper {
            margin-bottom: 1.5rem;
            overflow: visible;
        }
        .legend-inline {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.65rem;
        }
        .legend-card {
            padding: 1.25rem 1.5rem;
        }
        .legend-card h2 {
            margin: 0 0 0.4rem 0;
            font-size: 1.2rem;
        }
        .legend-card p {
            margin: 0 0 1rem 0;
            color: var(--text-muted);
        }
        .legend-card .legend-inline {
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            padding: 0;
        }
        .calendar-item-card .item-type-toggle {
            display: flex;
            gap: 0.5rem;
            background: #f1f3f9;
            border-radius: 999px;
            padding: 0.35rem;
            margin: 0.5rem 0 1rem 0;
        }
        .calendar-item-card .item-type-option {
            flex: 1;
            position: relative;
        }
        .calendar-item-card .item-type-option input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .calendar-item-card .item-type-option span {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            color: var(--text-muted);
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }
        .calendar-item-card .item-type-option input:checked + span {
            background: #fff;
            color: var(--text-main);
            box-shadow: 0 6px 15px rgba(15,23,42,0.15);
        }
        .item-section {
            display: none;
        }
        .item-section.active {
            display: block;
            margin-top: 1rem;
        }
        .legend-chip {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
        }
        .legend-chip.pill-high { background: #ef4444; }
        .legend-chip.pill-normal { background: #4f46e5; }
        .legend-chip.pill-low { background: #10b981; }
        .legend-chip.pill-task { background: #f97316; }
        .form-grid { display: grid; gap: 1rem; margin-top: 1rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .input-control {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 0.75rem 1rem;
            font-family: inherit;
            font-size: 0.95rem;
            background: #f9fafb;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }
        .input-control:focus {
            border-color: #6366f1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
            background: #fff;
        }
        textarea.input-control { min-height: 90px; resize: vertical; }
        .form-actions { display: flex; justify-content: flex-end; }
        @media (max-width: 1024px) {
            .calendar-grid {
                flex-direction: column;
            }
            .calendar-main {
                order: 1;
            }
            .calendar-sidebar {
                order: 2;
                flex: 1 1 auto;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="page-header">
            <div>
                <h1>Calendario Learnnect</h1>
                <div class="header-subtitle">
                    <?php echo htmlspecialchars($academyName, ENT_QUOTES, 'UTF-8'); ?> &middot;
                    <?php echo htmlspecialchars($username . ' (' . $userRole . ')', ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Volver al dashboard
                </a>
            </div>
        </header>
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>
        <main class="calendar-grid">
            <aside class="calendar-sidebar">
                <div class="card legend-card">
                    <h2><i class="fas fa-sliders-h"></i> Filtros</h2>
                    <p>Selecciona los elementos visibles en el calendario.</p>
                    <div class="legend-inline-wrapper">
                        <ul class="calendar-legend legend-inline">
                            <?php foreach ($calendarLegend as $key => $meta): ?>
                                <li class="calendar-legend-item">
                                    <label>
                                        <input type="checkbox" data-calendar-toggle value="<?php echo $key; ?>" checked>
                                        <span class="legend-chip <?php echo $meta['pill_class']; ?>"></span>
                                        <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php $isEventDefault = $calendarFormDefaultType === 'event'; ?>
                <?php $isTaskDefault = $calendarFormDefaultType === 'task'; ?>
                <div class="card calendar-item-card">
                    <h2><i class="fas fa-plus-circle"></i> Nuevo elemento</h2>
                    <p>Planifica eventos o registra tareas sin cambiar de panel.</p>
                    <?php if ($eventErrors): ?>
                        <div class="alert alert-error">
                            <?php foreach ($eventErrors as $error): ?>
                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($taskErrors): ?>
                        <div class="alert alert-error">
                            <?php foreach ($taskErrors as $error): ?>
                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="item-type-toggle" role="radiogroup">
                        <label class="item-type-option">
                            <input type="radio" name="calendar_item_type" value="event" data-calendar-type <?php echo $isEventDefault ? 'checked' : ''; ?>>
                            <span><i class="fas fa-calendar-day"></i> Evento</span>
                        </label>
                        <label class="item-type-option">
                            <input type="radio" name="calendar_item_type" value="task" data-calendar-type <?php echo $isTaskDefault ? 'checked' : ''; ?>>
                            <span><i class="fas fa-list-check"></i> Tarea</span>
                        </label>
                    </div>
                    <form method="POST" class="form-grid calendar-item-form">
                        <input type="hidden" name="action" id="calendar-item-action" value="<?php echo $isTaskDefault ? 'create_task' : 'create_event'; ?>">
                        <input type="hidden" name="item_mode" id="calendar-item-mode" value="create">
                        <input type="hidden" name="item_id" id="calendar-item-id" value="">
                        <div class="item-section <?php echo $isEventDefault ? 'active' : ''; ?>" data-item-section="event" <?php echo $isEventDefault ? '' : 'hidden'; ?>>
                            <div class="form-group">
                                <label for="event_title">T&iacute;tulo</label>
                                <input type="text" id="event_title" name="event_title" class="input-control" placeholder="Ej. Entrega proyecto" data-item-field="event" data-field-required="true" <?php echo $isEventDefault ? 'required' : 'disabled'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="event_start">Inicio</label>
                                <input type="datetime-local" id="event_start" name="event_start" class="input-control" data-item-field="event" data-field-required="true" <?php echo $isEventDefault ? 'required' : 'disabled'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="event_end">Fin (opcional)</label>
                                <input type="datetime-local" id="event_end" name="event_end" class="input-control" data-item-field="event" <?php echo $isEventDefault ? '' : 'disabled'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="event_priority">Prioridad</label>
                                <select id="event_priority" name="event_priority" class="input-control" data-item-field="event" <?php echo $isEventDefault ? '' : 'disabled'; ?>>
                                    <option value="Normal">Normal</option>
                                    <option value="Alta">Alta</option>
                                    <option value="Baja">Baja</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="event_description">Descripci&oacute;n</label>
                                <textarea id="event_description" name="event_description" class="input-control" placeholder="Detalles adicionales..." data-item-field="event" <?php echo $isEventDefault ? '' : 'disabled'; ?>></textarea>
                            </div>
                        </div>
                        <div class="item-section <?php echo $isTaskDefault ? 'active' : ''; ?>" data-item-section="task" <?php echo $isTaskDefault ? '' : 'hidden'; ?>>
                            <div class="form-group">
                                <label for="task_title">T&iacute;tulo</label>
                                <input type="text" id="task_title" name="task_title" class="input-control" placeholder="Ej. Preparar examen" data-item-field="task" data-field-required="true" <?php echo $isTaskDefault ? 'required' : 'disabled'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="task_description">Descripci&oacute;n</label>
                                <textarea id="task_description" name="task_description" class="input-control" placeholder="Detalles opcionales" data-item-field="task" <?php echo $isTaskDefault ? '' : 'disabled'; ?>></textarea>
                            </div>
                            <div class="form-group">
                                <label for="task_due_date">Fecha l&iacute;mite</label>
                                <input type="date" id="task_due_date" name="task_due_date" class="input-control" data-item-field="task" <?php echo $isTaskDefault ? '' : 'disabled'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="task_status">Estado</label>
                                <select id="task_status" name="task_status" class="input-control" data-item-field="task" <?php echo $isTaskDefault ? '' : 'disabled'; ?>>
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="En progreso">En progreso</option>
                                    <option value="Completada">Completada</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <span class="btn-label">Guardar</span>
                            </button>
                        </div>
                    </form>
                </div>
            </aside>
            <section class="calendar-main card">
                <div class="calendar-toolbar">
                    <div class="calendar-heading">
                        <?php if ($viewMode === 'year'): ?>
                            <h2><?php echo $selectedYear; ?></h2>
                            <span>Vista anual</span>
                        <?php else: ?>
                            <h2><?php echo htmlspecialchars($monthTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                            <span>Eventos y tareas</span>
                        <?php endif; ?>
                    </div>
                    <div class="toolbar-actions">
                        <?php if ($viewMode === 'year'): ?>
                            <div class="month-nav">
                                <a class="nav-btn" href="<?php echo htmlspecialchars($prevYearUrl, ENT_QUOTES, 'UTF-8'); ?>">&lsaquo;</a>
                                <a class="today-btn" href="<?php echo htmlspecialchars($yearViewUrl, ENT_QUOTES, 'UTF-8'); ?>">Este a&ntilde;o</a>
                                <a class="nav-btn" href="<?php echo htmlspecialchars($nextYearUrl, ENT_QUOTES, 'UTF-8'); ?>">&rsaquo;</a>
                            </div>
                        <?php elseif ($viewMode === 'week'): ?>
                            <div class="month-nav">
                                <a class="nav-btn" href="<?php echo htmlspecialchars($prevWeekUrl, ENT_QUOTES, 'UTF-8'); ?>">&lsaquo;</a>
                                <a class="today-btn" href="<?php echo htmlspecialchars($todayWeekUrl, ENT_QUOTES, 'UTF-8'); ?>">Esta semana</a>
                                <a class="nav-btn" href="<?php echo htmlspecialchars($nextWeekUrl, ENT_QUOTES, 'UTF-8'); ?>">&rsaquo;</a>
                            </div>
                        <?php else: ?>
                            <div class="month-nav">
                                <a class="nav-btn" href="<?php echo htmlspecialchars($prevMonthUrl, ENT_QUOTES, 'UTF-8'); ?>">&lsaquo;</a>
                                <a class="today-btn" href="<?php echo htmlspecialchars($todayUrl, ENT_QUOTES, 'UTF-8'); ?>">Hoy</a>
                                <a class="nav-btn" href="<?php echo htmlspecialchars($nextMonthUrl, ENT_QUOTES, 'UTF-8'); ?>">&rsaquo;</a>
                            </div>
                        <?php endif; ?>
                        <div class="view-toggle">
                            <a class="<?php echo $viewMode === 'month' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($monthViewUrl, ENT_QUOTES, 'UTF-8'); ?>">Mes</a>
                            <a class="<?php echo $viewMode === 'week' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($weekViewUrl, ENT_QUOTES, 'UTF-8'); ?>">Semana</a>
                            <a class="<?php echo $viewMode === 'year' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($yearViewUrl, ENT_QUOTES, 'UTF-8'); ?>">A&ntilde;o</a>
                        </div>
                    </div>
                </div>
                <?php if (!$eventsSchema['available']): ?>
                    <div class="alert alert-error">
                        No se ha encontrado la tabla de eventos. Revisa la base de datos para habilitar esta sección.
                    </div>
                <?php elseif ($viewMode === 'year'): ?>
                    <div class="year-grid">
                        <?php foreach ($annualMonths as $monthBlock): ?>
                            <div class="year-month">
                                <h3><?php echo htmlspecialchars($monthBlock['name'] . ' ' . $selectedYear, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="year-day-labels">
                                    <?php foreach ($dayShortLabels as $label): ?>
                                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="year-days">
                                    <?php foreach ($monthBlock['days'] as $dayCell): ?>
                                        <?php if ($dayCell['empty']): ?>
                                            <div class="year-day empty"></div>
                                        <?php else: ?>
                                            <div class="year-day <?php echo $dayCell['heat_class']; ?> <?php echo $dayCell['is_today'] ? 'today' : ''; ?>">
                                                <?php echo $dayCell['day']; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($viewMode === 'week'): ?>
                    <div class="week-heading"><?php echo htmlspecialchars($weekTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="week-all-day">
                        <div class="week-all-day-label">Todo el día</div>
                        <?php foreach ($weekDaysDetailed as $day): ?>
                            <div
                                class="week-all-day-cell <?php echo $day['is_today'] ? 'today' : ''; ?> <?php echo $day['is_past'] ? 'past' : ''; ?>"
                                data-drop-target="1"
                                data-drop-date="<?php echo htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-drop-time=""
                            >
                                <?php if (empty($day['all_day'])): ?>
                                    <span class="week-all-day-empty">Sin tareas</span>
                                <?php else: ?>
                                    <?php foreach ($day['all_day'] as $entry):
                                        $calendarKey = $entry['calendar_key'] ?? 'event-normal';
                                        $legendInfo = $calendarLegend[$calendarKey] ?? $calendarLegend['event-normal'];
                                    ?>
                                        <span
                                            class="week-all-day-pill <?php echo $legendInfo['pill_class']; ?>"
                                            data-calendar-entry="1"
                                            data-entry-id="<?php echo $entry['id']; ?>"
                                            data-entry-type="<?php echo $entry['type']; ?>"
                                            data-calendar-key="<?php echo $calendarKey; ?>"
                                            data-entry-title="<?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-description="<?php echo htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-start="<?php echo htmlspecialchars($entry['raw_start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-end="<?php echo htmlspecialchars($entry['raw_end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-priority="<?php echo htmlspecialchars($entry['priority'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-due="<?php echo htmlspecialchars($entry['raw_due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-status="<?php echo htmlspecialchars($entry['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="week-grid">
                        <div class="week-grid-header hour-cell"></div>
                        <?php foreach ($weekDaysDetailed as $day): ?>
                            <div class="week-grid-header day <?php echo $day['is_today'] ? 'today' : ''; ?> <?php echo $day['is_past'] ? 'past' : ''; ?>">
                                <span class="week-day-label"><?php echo htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="week-day-number"><?php echo htmlspecialchars($day['day'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($weekHours as $hour): ?>
                            <div class="week-hour-label"><?php echo sprintf('%02d:00', $hour); ?></div>
                            <?php foreach ($weekDaysDetailed as $day):
                                $slotEvents = $weekSchedule[$day['date']][$hour] ?? [];
                            ?>
                                <div
                                    class="week-slot <?php echo $day['is_past'] ? 'past' : ''; ?>"
                                    data-drop-target="1"
                                    data-drop-date="<?php echo htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-drop-time="<?php echo sprintf('%02d:00', $hour); ?>"
                                >
                                    <?php foreach ($slotEvents as $event):
                                        $calendarKey = $event['calendar_key'] ?? 'event-normal';
                                        $legendInfo = $calendarLegend[$calendarKey] ?? $calendarLegend['event-normal'];
                                    ?>
                                        <div
                                            class="week-event <?php echo $legendInfo['week_class']; ?>"
                                            data-calendar-entry="1"
                                            data-entry-id="<?php echo $event['id']; ?>"
                                            data-entry-type="<?php echo $event['type']; ?>"
                                            data-calendar-key="<?php echo $calendarKey; ?>"
                                            data-entry-title="<?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-description="<?php echo htmlspecialchars($event['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-start="<?php echo htmlspecialchars($event['raw_start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-end="<?php echo htmlspecialchars($event['raw_end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-priority="<?php echo htmlspecialchars($event['priority'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-due="<?php echo htmlspecialchars($event['raw_due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-entry-status="<?php echo htmlspecialchars($event['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <span class="week-event-title"><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if ($event['time'] !== ''): ?>
                                                <span class="week-event-time"><?php echo htmlspecialchars($event['time'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="month-day-labels">
                        <?php foreach ($dayShortLabels as $label): ?>
                            <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="month-grid">
                        <?php foreach ($weeks as $week): ?>
                            <?php foreach ($week as $dayCell):
                                $entries = array_slice($dayCell['entries'], 0, 4);
                            ?>
                                <div
                                    class="month-cell <?php echo $dayCell['is_current_month'] ? '' : 'outside'; ?> <?php echo $dayCell['is_today'] ? 'today' : ''; ?> <?php echo $dayCell['is_past'] ? 'past' : ''; ?>"
                                    data-date="<?php echo htmlspecialchars($dayCell['date'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <div class="day-number"><?php echo $dayCell['day']; ?></div>
                                    <div class="entries">
                                        <?php if (empty($entries)): ?>
                                            <div class="month-entry-time" style="color: transparent;">.</div>
                                        <?php else: ?>
                                            <?php foreach ($entries as $entry):
                                                $calendarKey = $entry['calendar_key'] ?? 'event-normal';
                                                $legendInfo = $calendarLegend[$calendarKey] ?? $calendarLegend['event-normal'];
                                            ?>
                                                <div
                                                    class="month-entry"
                                                    data-calendar-entry="1"
                                                    data-calendar-key="<?php echo $calendarKey; ?>"
                                                    data-entry-type="<?php echo htmlspecialchars($entry['type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-id="<?php echo (int) $entry['id']; ?>"
                                                    data-entry-title="<?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-description="<?php echo htmlspecialchars($entry['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-start="<?php echo htmlspecialchars($entry['raw_start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-end="<?php echo htmlspecialchars($entry['raw_end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-priority="<?php echo htmlspecialchars($entry['priority'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-due="<?php echo htmlspecialchars($entry['raw_due_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-entry-status="<?php echo htmlspecialchars($entry['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    <span class="entry-dot <?php echo $legendInfo['dot_class']; ?>"></span>
                                                    <div>
                                                        <div class="month-entry-title"><?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php if ($entry['time'] !== ''): ?>
                                                            <div class="month-entry-time"><?php echo htmlspecialchars($entry['time'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php elseif (!empty($entry['priority'])): ?>
                                                            <div class="month-entry-time"><?php echo htmlspecialchars($entry['priority'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarForm = document.querySelector('.calendar-item-form');
            const itemTypeRadios = document.querySelectorAll('[data-calendar-type]');
            const itemSections = document.querySelectorAll('[data-item-section]');
            const itemFields = document.querySelectorAll('[data-item-field]');
            const itemActionInput = document.getElementById('calendar-item-action');
            const formModeInput = document.getElementById('calendar-item-mode');
            const formIdInput = document.getElementById('calendar-item-id');
            const submitButtonLabel = calendarForm ? calendarForm.querySelector('.btn-label') : null;
            const eventFields = {
                title: document.getElementById('event_title'),
                start: document.getElementById('event_start'),
                end: document.getElementById('event_end'),
                priority: document.getElementById('event_priority'),
                description: document.getElementById('event_description')
            };
            const taskFields = {
                title: document.getElementById('task_title'),
                description: document.getElementById('task_description'),
                due: document.getElementById('task_due_date'),
                status: document.getElementById('task_status')
            };
            let currentType = itemTypeRadios.length ? (Array.from(itemTypeRadios).find(radio => radio.checked)?.value || 'event') : 'event';
            let currentMode = formModeInput ? formModeInput.value : 'create';
            let editingType = currentMode === 'edit' ? currentType : null;

            const setButtonLabel = () => {
                if (!submitButtonLabel) return;
                const label = currentMode === 'edit'
                    ? `Actualizar ${currentType === 'task' ? 'tarea' : 'evento'}`
                    : `Guardar ${currentType === 'task' ? 'tarea' : 'evento'}`;
                submitButtonLabel.textContent = label;
            };

            const applyActionValue = () => {
                if (itemActionInput) {
                    itemActionInput.value = currentMode === 'edit'
                        ? (currentType === 'task' ? 'update_task' : 'update_event')
                        : (currentType === 'task' ? 'create_task' : 'create_event');
                }
                setButtonLabel();
            };

            const setFormMode = (mode, forcedType = null) => {
                currentMode = mode;
                editingType = mode === 'edit' ? (forcedType || currentType) : null;
                if (formModeInput) {
                    formModeInput.value = mode;
                }
                if (mode === 'create' && formIdInput) {
                    formIdInput.value = '';
                }
                applyActionValue();
            };

            const setActiveType = (type) => {
                if (currentMode === 'edit' && editingType && type !== editingType) {
                    setFormMode('create');
                }
                currentType = type;
                itemSections.forEach(section => {
                    const match = section.dataset.itemSection === type;
                    section.classList.toggle('active', match);
                    section.hidden = !match;
                });
                itemFields.forEach(field => {
                    const match = field.dataset.itemField === type;
                    const shouldRequire = field.dataset.fieldRequired === 'true';
                    field.disabled = !match;
                    field.required = match && shouldRequire;
                });
                itemTypeRadios.forEach(radio => {
                    radio.checked = radio.value === type;
                });
                applyActionValue();
            };

            if (itemTypeRadios.length) {
                setActiveType(currentType);
                itemTypeRadios.forEach(radio => {
                    radio.addEventListener('change', () => {
                        if (radio.checked) {
                            setActiveType(radio.value);
                        }
                    });
                });
            } else {
                applyActionValue();
            }
            setFormMode(currentMode || 'create');

            const formatDateTimeLocal = (value) => {
                if (!value) return '';
                return value.replace(' ', 'T').slice(0, 16);
            };
            const scrollFormIntoView = () => {
                if (calendarForm) {
                    calendarForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };
            const clearFormFields = () => {
                Object.values(eventFields).forEach(field => {
                    if (field) field.value = '';
                });
                if (eventFields.priority) {
                    eventFields.priority.value = 'Normal';
                }
                Object.values(taskFields).forEach(field => {
                    if (field) field.value = '';
                });
                if (taskFields.status) {
                    taskFields.status.value = 'Pendiente';
                }
            };
            const prepareCreateForDate = (dateStr) => {
                setFormMode('create');
                clearFormFields();
                if (eventFields.start && dateStr) {
                    eventFields.start.value = `${dateStr}T09:00`;
                }
                if (eventFields.end) {
                    eventFields.end.value = '';
                }
                if (taskFields.due && dateStr) {
                    taskFields.due.value = dateStr;
                }
                scrollFormIntoView();
            };
            const fillFormForEntry = (dataset) => {
                const entryType = dataset.entryType === 'task' ? 'task' : 'event';
                setActiveType(entryType);
                setFormMode('edit', entryType);
                if (formIdInput) {
                    formIdInput.value = dataset.entryId || '';
                }
                if (entryType === 'event') {
                    if (eventFields.title) eventFields.title.value = dataset.entryTitle || '';
                    if (eventFields.start) eventFields.start.value = formatDateTimeLocal(dataset.entryStart || '');
                    if (eventFields.end) eventFields.end.value = formatDateTimeLocal(dataset.entryEnd || '');
                    if (eventFields.priority) eventFields.priority.value = dataset.entryPriority || 'Normal';
                    if (eventFields.description) eventFields.description.value = dataset.entryDescription || '';
                } else {
                    if (taskFields.title) taskFields.title.value = dataset.entryTitle || '';
                    if (taskFields.description) taskFields.description.value = dataset.entryDescription || '';
                    if (taskFields.due) taskFields.due.value = dataset.entryDue || '';
                    if (taskFields.status) taskFields.status.value = dataset.entryStatus || 'Pendiente';
                }
                scrollFormIntoView();
            };

            const monthCells = document.querySelectorAll('.month-cell[data-date]');
            monthCells.forEach(cell => {
                cell.addEventListener('click', (event) => {
                    if (event.target.closest('[data-calendar-entry]')) {
                        return;
                    }
                    prepareCreateForDate(cell.dataset.date || '');
                });
            });

            const citasStorageKey = 'iuc:citas:<?php echo (int) $academy_id > 0 ? (int) $academy_id : 'default'; ?>:state';
            const pad = n => String(n).padStart(2, '0');
            const toLocalDateKey = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

            const renderCitasEntries = () => {
                document.querySelectorAll('.citas-entry').forEach(node => node.remove());
                const citasRaw = localStorage.getItem(citasStorageKey);
                if (!citasRaw) return;
                try {
                    const citasState = JSON.parse(citasRaw);
                    const citasAppointments = Array.isArray(citasState.appointments) ? citasState.appointments : [];
                    const confirmed = citasAppointments.filter(item => item && item.status === 'Confirmada');
                    confirmed.forEach(item => {
                        if (!item.dateTime) return;
                        const date = new Date(item.dateTime);
                        if (Number.isNaN(date.getTime())) return;
                        const dateKey = toLocalDateKey(date);
                        const timeLabel = `${pad(date.getHours())}:${pad(date.getMinutes())}`;
                        const titleText = `Cita: ${item.name || 'Cliente'} · ${item.service || 'Servicio'}`;

                        const monthCell = document.querySelector(`.month-cell[data-date="${dateKey}"]`);
                        if (monthCell) {
                            const entries = monthCell.querySelector('.entries');
                            if (entries) {
                                const placeholder = entries.querySelector('.month-entry-time');
                                if (placeholder && placeholder.style.color === 'transparent') {
                                    placeholder.remove();
                                }
                                const entry = document.createElement('div');
                                entry.className = 'month-entry citas-entry';
                                const dot = document.createElement('span');
                                dot.className = 'entry-dot dot-event';
                                const content = document.createElement('div');
                                const title = document.createElement('div');
                                title.className = 'month-entry-title';
                                title.textContent = titleText;
                                const time = document.createElement('div');
                                time.className = 'month-entry-time';
                                time.textContent = timeLabel;
                                content.appendChild(title);
                                content.appendChild(time);
                                entry.appendChild(dot);
                                entry.appendChild(content);
                                entries.appendChild(entry);
                            }
                        }

                        const hourSlot = `${pad(date.getHours())}:00`;
                        const weekSlot = document.querySelector(`.week-slot[data-drop-date="${dateKey}"][data-drop-time="${hourSlot}"]`);
                        if (weekSlot) {
                            const weekEvent = document.createElement('div');
                            weekEvent.className = 'week-event week-normal citas-entry';
                            const label = document.createElement('span');
                            label.className = 'week-event-title';
                            label.textContent = titleText;
                            const time = document.createElement('span');
                            time.className = 'week-event-time';
                            time.textContent = timeLabel;
                            weekEvent.appendChild(label);
                            weekEvent.appendChild(time);
                            weekSlot.appendChild(weekEvent);
                        }
                    });
                } catch (err) {
                }
            };

            renderCitasEntries();
            window.addEventListener('storage', event => {
                if (event.key === citasStorageKey) {
                    renderCitasEntries();
                }
            });
            setInterval(() => {
                renderCitasEntries();
            }, 5000);

            const calendarEntries = document.querySelectorAll('[data-calendar-entry]');
            calendarEntries.forEach(entry => {
                entry.addEventListener('click', (event) => {
                    event.stopPropagation();
                    fillFormForEntry(entry.dataset);
                });
            });

            const draggableItems = document.querySelectorAll('.week-event, .week-all-day-pill');
            draggableItems.forEach(item => {
                item.setAttribute('draggable', 'true');
                item.addEventListener('dragstart', event => {
                    const payload = {
                        id: item.dataset.entryId,
                        type: item.dataset.entryType
                    };
                    event.dataTransfer.setData('application/json', JSON.stringify(payload));
                    event.dataTransfer.effectAllowed = 'move';
                });
            });
            const dropTargets = document.querySelectorAll('[data-drop-target="1"]');
            dropTargets.forEach(target => {
                target.addEventListener('dragover', event => {
                    event.preventDefault();
                    target.classList.add('drag-over');
                });
                target.addEventListener('dragleave', () => {
                    target.classList.remove('drag-over');
                });
                target.addEventListener('drop', event => {
                    event.preventDefault();
                    target.classList.remove('drag-over');
                    let payloadRaw = event.dataTransfer.getData('application/json');
                    if (!payloadRaw) {
                        payloadRaw = event.dataTransfer.getData('text/plain');
                    }
                    if (!payloadRaw) return;
                    let payload;
                    try {
                        payload = JSON.parse(payloadRaw);
                    } catch (e) {
                        return;
                    }
                    if (!payload || !payload.id || !payload.type) {
                        return;
                    }
                    const params = new URLSearchParams();
                    params.set('action', 'move_calendar_item');
                    params.set('entry_id', payload.id);
                    params.set('entry_type', payload.type);
                    params.set('new_date', target.dataset.dropDate || '');
                    params.set('new_time', target.dataset.dropTime || '');
                    fetch('calendar.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString()
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else if (data.message) {
                                alert(data.message);
                            }
                        })
                        .catch(() => {
                            alert('No se pudo mover el elemento.');
                        });
                });
            });
            const toggleStorageKey = 'calendarVisibleKeys';
            const toggles = document.querySelectorAll('[data-calendar-toggle]');
            if (toggles.length) {
                let visibleKeys = new Set();
                try {
                    const stored = localStorage.getItem(toggleStorageKey);
                    if (stored) {
                        const parsed = JSON.parse(stored);
                        if (Array.isArray(parsed) && parsed.length > 0) {
                            visibleKeys = new Set(parsed);
                        }
                    }
                } catch (e) {
                    visibleKeys = new Set();
                }
                if (visibleKeys.size === 0) {
                    toggles.forEach(input => visibleKeys.add(input.value));
                    try {
                        localStorage.setItem(toggleStorageKey, JSON.stringify(Array.from(visibleKeys)));
                    } catch (e) {}
                }
                const applyVisibility = () => {
                    document.querySelectorAll('[data-calendar-key]').forEach(el => {
                        const key = el.dataset.calendarKey || 'event-normal';
                        if (visibleKeys.has(key)) {
                            el.classList.remove('calendar-entry-hidden');
                        } else {
                            el.classList.add('calendar-entry-hidden');
                        }
                    });
                };
                toggles.forEach(input => {
                    input.checked = visibleKeys.has(input.value);
                    input.addEventListener('change', () => {
                        if (input.checked) {
                            visibleKeys.add(input.value);
                        } else {
                            visibleKeys.delete(input.value);
                        }
                        try {
                            localStorage.setItem(toggleStorageKey, JSON.stringify(Array.from(visibleKeys)));
                        } catch (e) {}
                        applyVisibility();
                    });
                });
                applyVisibility();
            }
        });
    </script>
</body>
</html>





