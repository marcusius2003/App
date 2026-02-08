<?php
/**
 * Panel de Exámenes y Tareas - IUConnect
 * 
 * IMPORTANTE: Este panel NO tiene datos inventados.
 * - Si está vacío, es porque no hay tareas en la BD
 * - El administrador debe crear tareas primero
 * - Los alumnos solo ven tareas cuando el admin las crea
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Conexión a BD
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';
require_once __DIR__ . '/includes/assignment_blocking.php';

$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'edu.assignments');
$user_id = (int) $currentUser['id'];
$username = $currentUser['username'];
$userRole = $currentUser['role'];
$academyName = $currentUser['academy_name'] ?? '';
$academy_id = $currentUser['academy_id'] ?? null;

// Determinar si es administrador (profesor)
$normalizedRole = strtolower((string) $userRole);
$academyId = (int) $academy_id;
$isTenantAdmin = $academyId > 0 ? is_tenant_admin($pdo, $user_id, $academyId) : false;
$isAdmin = $isTenantAdmin || in_array($normalizedRole, ['admin', 'administrator', 'administrador', 'owner', 'teacher', 'profesor', 'instructor'], true);

// =====================================
// Obtener datos REALES de la base de datos
// SIN datos inventados - Solo lo que existe
// =====================================
$assignments = [];
ensureAssignmentBlockingColumns($pdo);

try {
    if ($isAdmin) {
        // VISTA PROFESOR: Todas las tareas que él ha creado
        $sql = "
            SELECT 
                a.id,
                a.title,
                a.type,
                a.description,
                a.due_date,
                a.status,
                a.is_blocked,
                a.created_at,
                COUNT(DISTINCT s.id) as total_submissions,
                COUNT(DISTINCT CASE WHEN s.grade IS NOT NULL THEN s.id END) as graded_count
            FROM assignments a
            LEFT JOIN submissions s ON s.assignment_id = a.id
            WHERE a.academy_id = :academy_id OR a.academy_id IS NULL
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':academy_id' => $academy_id]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // VISTA ALUMNO: Solo tareas publicadas
        $sql = "
            SELECT 
                a.id,
                a.title,
                a.type,
                a.description,
                a.due_date,
                a.is_blocked,
                s.id as submission_id,
                s.submitted_at,
                s.grade,
                s.feedback,
                s.status as submission_status,
                s.is_late
            FROM assignments a
            LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = :user_id
            WHERE (a.academy_id = :academy_id OR a.academy_id IS NULL)
              AND (a.published_at IS NULL OR a.published_at <= NOW())
            ORDER BY a.due_date ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':academy_id' => $academy_id
        ]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Si hay error de BD, mostrar vacío
    $assignments = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exámenes y Tareas - IUConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
            color: #111827;
            line-height: 1.6;
        }
        .layout { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: #111827;
            color: white;
            padding: 1.5rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .logo {
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0 0.5rem;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: #9ca3af;
            text-decoration: none;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: #1f2937;
            color: white;
        }
        .sidebar-nav i { width: 20px; text-align: center; }
        
        /* Main */
        .main {
            flex: 1;
            padding: 2rem;
            margin-left: 260px;
        }
        .header { margin-bottom: 2rem; }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .header p { color: #6b7280; }
        
        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #111827; color: white; }
        .btn-primary:hover { background: #374151; }
        .btn-secondary { background: #f3f4f6; color: #111827; border: 1px solid #e5e7eb; }
        
        /* Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
        .card-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }
        .type-tarea { background: #3b82f6; color: white; }
        .type-examen { background: #ef4444; color: white; }
        .type-practica { background: #f59e0b; color: white; }
        .card-desc { color: #6b7280; font-size: 0.9rem; margin-bottom: 1rem; }
        .card-meta { font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem; }
        .card-meta i { margin-right: 0.5rem; }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Badges */
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending { background: #e5e7eb; color: #374151; }
        .badge-submitted { background: #dbeafe; color: #1e40af; }
        .badge-graded { background: #d1fae5; color: #065f46; }
        .badge-late { background: #fee2e2; color: #991b1b; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e5e7eb;
        }
        .empty-state i { font-size: 4rem; color: #d1d5db; margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.25rem; color: #374151; margin-bottom: 0.5rem; }
        .empty-state p { color: #6b7280; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { font-size: 1.25rem; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        .modal-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-actions {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .cards-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            IUCONNECT
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="calendar.php"><i class="fas fa-calendar"></i> Calendario</a>
            <a href="entregas.php" class="active"><i class="fas fa-tasks"></i> Exámenes y Tareas</a>
            <a href="biblioteca.php"><i class="fas fa-book"></i> Biblioteca</a>
            <a href="tickets.php"><i class="fas fa-ticket-alt"></i> Tickets</a>
            <a href="messages.php"><i class="fas fa-comments"></i> Mensajes</a>
            <?php if ($isAdmin): ?>
            <a href="admin_panel.php"><i class="fas fa-cog"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </nav>
    </aside>

    <!-- Main -->
    <main class="main">
        <div class="header">
            <h1><i class="fas fa-tasks"></i> <?php echo $isAdmin ? 'Gestión de Exámenes y Tareas' : 'Mis Exámenes y Tareas'; ?></h1>
            <p><?php echo htmlspecialchars($username); ?> · <?php echo $isAdmin ? 'Profesor' : 'Alumno'; ?></p>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <div></div>
            <?php if ($isAdmin): ?>
            <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')">
                <i class="fas fa-plus"></i> Crear Tarea/Examen
            </button>
            <?php endif; ?>
        </div>

        <!-- Alert Container -->
        <div id="alertBox"></div>

        <!-- Cards o Estado Vacío -->
        <?php if (empty($assignments)): ?>
        <!-- ESTADO VACÍO - No hay datos -->
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <?php if ($isAdmin): ?>
            <h3>No hay tareas ni exámenes</h3>
            <p>Crea tu primera tarea o examen usando el botón de arriba.</p>
            <p style="margin-top: 1rem;">Los alumnos verán las tareas que crees aquí.</p>
            <?php else: ?>
            <h3>No hay tareas disponibles</h3>
            <p>Cuando tu profesor cree tareas o exámenes, aparecerán aquí.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- MOSTRAR TAREAS REALES DE LA BD -->
        <div class="cards-grid">
            <?php foreach ($assignments as $a): 
                $dueDate = new DateTime($a['due_date']);
                $now = new DateTime();
                $isOverdue = $now > $dueDate;
                $isBlocked = !empty($a['is_blocked']);
            ?>
            <div class="card">
                <span class="card-type type-<?php echo htmlspecialchars($a['type']); ?>">
                    <?php echo strtoupper(htmlspecialchars($a['type'])); ?>
                </span>
                <div class="card-title"><?php echo htmlspecialchars($a['title']); ?></div>
                
                <?php if ($isBlocked): ?>
                <div class="text-xs font-semibold text-red-600 flex items-center gap-1 mb-2">
                    <i class="fas fa-ban"></i> Bloqueada por el profesor
                </div>
                <?php endif; ?>
                
                <?php if (!empty($a['description'])): ?>
                <div class="card-desc"><?php echo htmlspecialchars(substr($a['description'], 0, 100)); ?></div>
                <?php endif; ?>
                
                <div class="card-meta">
                    <div><i class="fas fa-calendar"></i> Vence: <?php echo $dueDate->format('d/m/Y H:i'); ?></div>
                    <?php if ($isAdmin): ?>
                    <div><i class="fas fa-users"></i> <?php echo (int)$a['total_submissions']; ?> entregas</div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <?php if ($isAdmin): ?>
                        <span class="badge badge-pending"><?php echo (int)$a['graded_count']; ?> calificadas</span>
                        <a href="entregas_corregir.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                            <i class="fas fa-edit"></i> Corregir
                        </a>
                    <?php else: ?>
                        <?php 
                        $hasSubmission = !empty($a['submission_id']);
                        $isGraded = $hasSubmission && !empty($a['grade']);
                        ?>
                        <?php if ($isBlocked): ?>
                            <span class="badge badge-late">Bloqueada</span>
                            <p style="font-size: 0.75rem; color: #ef4444; margin-top: 0.25rem;">El profesor desactivó nuevas entregas.</p>
                        <?php elseif ($isGraded): ?>
                            <span class="badge badge-graded">Nota: <?php echo htmlspecialchars($a['grade']); ?></span>
                        <?php elseif ($hasSubmission): ?>
                            <span class="badge badge-submitted">Entregada</span>
                        <?php elseif ($isOverdue): ?>
                            <span class="badge badge-late">Vencida</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Pendiente</span>
                            <button class="btn btn-primary" onclick="openSubmit(<?php echo (int)$a['id']; ?>)" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                <i class="fas fa-upload"></i> Entregar
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal: Crear Tarea (Solo Admin) -->
<?php if ($isAdmin): ?>
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nueva Tarea o Examen</h2>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('active')">&times;</button>
        </div>
        <form id="createForm">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="type" required>
                        <option value="tarea">Tarea</option>
                        <option value="examen">Examen</option>
                        <option value="practica">Práctica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Título *</label>
                    <input type="text" name="title" required placeholder="Ej: Examen parcial de matemáticas">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description" placeholder="Instrucciones para los alumnos..."></textarea>
                </div>
                <div class="form-group">
                    <label>Fecha límite de entrega *</label>
                    <input type="datetime-local" name="due_date" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('active')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('createForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('entregas_crear_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
            document.getElementById('createModal').classList.remove('active');
            setTimeout(() => location.reload(), 1500);
        } else {
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation"></i> ' + (data.message || 'Error') + '</div>';
        }
    })
    .catch(() => {
        document.getElementById('alertBox').innerHTML = '<div class="alert alert-error">Error de conexión</div>';
    });
});
</script>
<?php endif; ?>

<!-- Modal: Entregar (Solo Alumno) -->
<?php if (!$isAdmin): ?>
<div id="submitModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Entregar Tarea</h2>
            <button class="modal-close" onclick="document.getElementById('submitModal').classList.remove('active')">&times;</button>
        </div>
        <form id="submitForm" enctype="multipart/form-data">
            <input type="hidden" name="assignment_id" id="submitAssignmentId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Archivo *</label>
                    <input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                    <small style="color: #6b7280;">PDF, Word, Imágenes o ZIP (máx 10MB)</small>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('submitModal').classList.remove('active')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Enviar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSubmit(id) {
    document.getElementById('submitAssignmentId').value = id;
    document.getElementById('submitModal').classList.add('active');
}

document.getElementById('submitForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('entregas_submit_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
            document.getElementById('submitModal').classList.remove('active');
            setTimeout(() => location.reload(), 1500);
        } else {
            document.getElementById('alertBox').innerHTML = '<div class="alert alert-error">' + (data.message || 'Error') + '</div>';
        }
    })
    .catch(() => {
        document.getElementById('alertBox').innerHTML = '<div class="alert alert-error">Error de conexión</div>';
    });
});
</script>
<?php endif; ?>

</body>
</html>
