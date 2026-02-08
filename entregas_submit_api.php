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
require_once __DIR__ . '/includes/assignment_blocking.php';

$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'edu.assignments', ['response' => 'json']);
$user_id = (int) $currentUser['id'];

// Validar datos
$assignment_id = (int) ($_POST['assignment_id'] ?? 0);

if ($assignment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de tarea inválido']);
    exit();
}

// Verificar que la tarea existe
ensureAssignmentBlockingColumns($pdo);

$stmt = $pdo->prepare("SELECT id, due_date, allow_late, late_until, is_blocked FROM assignments WHERE id = :id");
$stmt->execute([':id' => $assignment_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
    exit();
}

if (!empty($assignment['is_blocked'])) {
    echo json_encode(['success' => false, 'message' => 'Esta tarea ha sido bloqueada y no acepta nuevas entregas']);
    exit();
}

// Verificar si ya ha entregado
$stmt = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id = :assignment_id AND student_id = :student_id");
$stmt->execute([
    ':assignment_id' => $assignment_id,
    ':student_id' => $user_id
]);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ya has entregado esta tarea']);
    exit();
}

// Verificar archivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar un archivo']);
    exit();
}

$file = $_FILES['file'];
$maxSize = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande (máximo 10MB)']);
    exit();
}

// Validar extensión
$allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
    exit();
}

// Verificar si está fuera de plazo
$dueDate = new DateTime($assignment['due_date']);
$now = new DateTime();
$isLate = $now > $dueDate;

// Si está fuera de plazo y no permite entregas tardías
if ($isLate && !$assignment['allow_late']) {
    echo json_encode(['success' => false, 'message' => 'Esta tarea ya ha vencido']);
    exit();
}

// Si permite tardías pero ya pasó la fecha límite tardía
if ($isLate && $assignment['allow_late'] && $assignment['late_until']) {
    $lateUntil = new DateTime($assignment['late_until']);
    if ($now > $lateUntil) {
        echo json_encode(['success' => false, 'message' => 'El plazo de entrega tardía ha terminado']);
        exit();
    }
}

try {
    // Crear directorio si no existe
    $uploadDir = __DIR__ . '/uploads/entregas/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generar nombre único para el archivo
    $fileName = $assignment_id . '_' . $user_id . '_' . time() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
        exit();
    }
    
    // Guardar en la base de datos
    $sql = "
        INSERT INTO submissions (
            student_id,
            assignment_id,
            url,
            submitted_at,
            status,
            is_late
        ) VALUES (
            :student_id,
            :assignment_id,
            :url,
            NOW(),
            'submitted',
            :is_late
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':student_id' => $user_id,
        ':assignment_id' => $assignment_id,
        ':url' => 'uploads/entregas/' . $fileName,
        ':is_late' => $isLate ? 1 : 0
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tarea entregada correctamente' . ($isLate ? ' (entrega tardía)' : '')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la entrega: ' . $e->getMessage()
    ]);
}
?>
