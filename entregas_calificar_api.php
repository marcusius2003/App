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

// Verificar que es administrador
$normalizedRole = strtolower((string) $userRole);
$isAdmin = in_array($normalizedRole, ['admin', 'administrator', 'administrador'], true);

if (!$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden calificar']);
    exit();
}

// Validar datos
$submission_id = (int) ($_POST['submission_id'] ?? 0);
$grade = !empty($_POST['grade']) ? (float) $_POST['grade'] : null;
$feedback = trim($_POST['feedback'] ?? '');

if ($submission_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de entrega inválido']);
    exit();
}

if ($grade === null) {
    echo json_encode(['success' => false, 'message' => 'La calificación es obligatoria']);
    exit();
}

if ($grade < 0) {
    echo json_encode(['success' => false, 'message' => 'La calificación no puede ser negativa']);
    exit();
}

try {
    // Actualizar la entrega
    $sql = "
        UPDATE submissions
        SET 
            grade = :grade,
            feedback = :feedback,
            graded_by = :graded_by,
            graded_at = NOW(),
            status = 'graded'
        WHERE id = :submission_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':grade' => $grade,
        ':feedback' => $feedback,
        ':graded_by' => $user_id,
        ':submission_id' => $submission_id
    ]);
    
    // Obtener el assignment_id de esta entrega
    $stmt = $pdo->prepare("SELECT assignment_id FROM submissions WHERE id = :id");
    $stmt->execute([':id' => $submission_id]);
    $assignment_id = $stmt->fetchColumn();
    
    // Actualizar el estado del assignment a 'graded' si al menos una entrega está calificada
    if ($assignment_id) {
        $pdo->prepare("UPDATE assignments SET status = 'graded' WHERE id = :id")
            ->execute([':id' => $assignment_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Calificación guardada correctamente'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la calificación: ' . $e->getMessage()
    ]);
}
?>
