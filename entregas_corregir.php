<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';

$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'edu.assignments');
$user_id = (int) $currentUser['id'];
$userRole = $currentUser['role'];

// Verificar que es administrador
$normalizedRole = strtolower((string) $userRole);
$isAdmin = in_array($normalizedRole, ['admin', 'administrator', 'administrador'], true);

if (!$isAdmin) {
    header('Location: entregas.php');
    exit();
}

// Obtener ID de la tarea
$assignment_id = (int) ($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    header('Location: entregas.php');
    exit();
}

// Obtener datos de la tarea
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = :id");
$stmt->execute([':id' => $assignment_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header('Location: entregas.php');
    exit();
}

// Obtener todas las entregas de esta tarea
$sql = "
    SELECT 
        s.*,
        u.username,
        u.id as student_user_id
    FROM submissions s
    JOIN users u ON u.id = s.student_id
    WHERE s.assignment_id = :assignment_id
    ORDER BY s.submitted_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':assignment_id' => $assignment_id]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corregir: <?php echo htmlspecialchars($assignment['title']); ?> - IUConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --blue-500: #3b82f6;
            --green-500: #10b981;
            --red-500: #ef4444;
            --text-main: var(--gray-900);
            --text-muted: var(--gray-600);
            --border-soft: var(--gray-200);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--text-main);
            line-height: 1.6;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        .header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--blue-500);
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .stats {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .stat-pill {
            padding: 0.5rem 1rem;
            background: var(--gray-100);
            border-radius: 999px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .stat-pill i {
            color: var(--gray-600);
        }
        table {
            width: 100%;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        thead {
            background: var(--gray-900);
            color: var(--white);
        }
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        tbody tr:hover {
            background: var(--gray-50);
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-submitted {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-graded {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-late {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--gray-900);
            color: var(--white);
        }
        .btn-primary:hover {
            background: var(--gray-700);
        }
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: var(--white);
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            font-size: 1.25rem;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .modal-body {
            padding: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-soft);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding: 1.5rem;
            border-top: 1px solid var(--border-soft);
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }
        .alert.active {
            display: block;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="entregas.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Volver a Tareas
    </a>
    
    <div class="header">
        <h1>
            <i class="fas fa-pencil-alt"></i>
            <?php echo htmlspecialchars($assignment['title']); ?>
        </h1>
        <p>Tipo: <strong><?php echo strtoupper($assignment['type']); ?></strong> · Vence: <strong><?php echo date('d/m/Y H:i', strtotime($assignment['due_date'])); ?></strong></p>
        
        <?php if ($assignment['description']): ?>
        <p style="margin-top: 1rem;"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-pill">
                <i class="fas fa-upload"></i>
                <span><?php echo count($submissions); ?> entregas recibidas</span>
            </div>
            <div class="stat-pill">
                <i class="fas fa-check-circle"></i>
                <span><?php echo count(array_filter($submissions, fn($s) => !empty($s['grade']))); ?> calificadas</span>
            </div>
            <?php if ($assignment['max_grade']): ?>
            <div class="stat-pill">
                <i class="fas fa-star"></i>
                <span>Nota máxima: <?php echo $assignment['max_grade']; ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="alertContainer"></div>
    
    <?php if (empty($submissions)): ?>
    <div class="empty-state" style="background: var(--white); border-radius: 12px; box-shadow: var(--shadow);">
        <i class="fas fa-inbox"></i>
        <h3>No hay entregas todavía</h3>
        <p>Los alumnos aún no han enviado ninguna entrega para esta tarea</p>
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Fecha de Entrega</th>
                <th>Estado</th>
                <th>Calificación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($submissions as $submission): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($submission['username']); ?></strong>
                </td>
                <td>
                    <?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?>
                    <?php if ($submission['is_late']): ?>
                        <span class="badge badge-late">Tardía</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($submission['grade'] !== null): ?>
                        <span class="badge badge-graded">Calificada</span>
                    <?php else: ?>
                        <span class="badge badge-submitted">Enviada</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($submission['grade'] !== null): ?>
                        <strong style="color: var(--green-500);"><?php echo $submission['grade']; ?></strong>
                    <?php else: ?>
                        <span style="color: var(--text-muted);">Pendiente</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($submission['url']): ?>
                    <a href="<?php echo htmlspecialchars($submission['url']); ?>" target="_blank" class="btn btn-sm" style="background: var(--blue-500); color: white; margin-right: 0.5rem;">
                        <i class="fas fa-download"></i>
                        Descargar
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-primary btn-sm" onclick="openGradeModal(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['username'], ENT_QUOTES); ?>', '<?php echo $submission['grade'] ?? ''; ?>', '<?php echo htmlspecialchars($submission['feedback'] ?? '', ENT_QUOTES); ?>')">
                        <i class="fas fa-edit"></i>
                        Calificar
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal Calificar -->
<div id="gradeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Calificar Entrega</h2>
            <button class="modal-close" onclick="closeGradeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="gradeForm" onsubmit="return handleGrade(event)">
            <input type="hidden" name="submission_id" id="gradeSubmissionId">
            <div class="modal-body">
                <p style="margin-bottom: 1.5rem;"><strong>Alumno:</strong> <span id="gradeStudentName"></span></p>
                
                <div class="form-group">
                    <label>Calificación *</label>
                    <input type="number" name="grade" id="gradeValue" step="0.01" min="0" <?php echo $assignment['max_grade'] ? 'max="'.$assignment['max_grade'].'"' : ''; ?> required>
                    <?php if ($assignment['max_grade']): ?>
                    <small style="color: var(--text-muted);">Nota máxima: <?php echo $assignment['max_grade']; ?></small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Retroalimentación</label>
                    <textarea name="feedback" id="gradeFeedback" placeholder="Comentarios para el alumno..."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" style="background: var(--gray-200);" onclick="closeGradeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Guardar Calificación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openGradeModal(submissionId, studentName, currentGrade, currentFeedback) {
    document.getElementById('gradeSubmissionId').value = submissionId;
    document.getElementById('gradeStudentName').textContent = studentName;
    document.getElementById('gradeValue').value = currentGrade || '';
    document.getElementById('gradeFeedback').value = currentFeedback || '';
    document.getElementById('gradeModal').classList.add('active');
}

function closeGradeModal() {
    document.getElementById('gradeModal').classList.remove('active');
    document.getElementById('gradeForm').reset();
}

function handleGrade(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    fetch('entregas_calificar_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('Calificación guardada correctamente', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Error al guardar la calificación', 'error');
        }
    })
    .catch(err => {
        showAlert('Error de conexión', 'error');
    });
    
    return false;
}

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} active`;
    alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    container.appendChild(alert);
    
    setTimeout(() => alert.remove(), 5000);
}

// Cerrar modal al hacer clic fuera
document.getElementById('gradeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeGradeModal();
    }
});
</script>
</body>
</html>
