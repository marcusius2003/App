<?php
session_start();
// =====================================
// 1. Auth & Tenant Setup
// =====================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';
require_once __DIR__ . '/includes/tenant_context.php';

$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'core.tickets');

$user_id = (int) $currentUser['id'];
$username = $currentUser['username'] ?? 'Usuario';
$userRole = $currentUser['role'] ?? '';
$academyName = $currentUser['academy_name'] ?? ($currentUser['academy'] ?? '');

$tenantContext = new TenantContext($pdo);
try {
    $context = $tenantContext->resolveTenantContext();
    $academy_id = $context['academy_id'];
} catch (Exception $e) {
    die('Error de contexto: ' . htmlspecialchars($e->getMessage()));
}

$template = $tenantContext->getTenantTemplate($academy_id);
$templateCode = strtoupper($template['code'] ?? 'CORE_ONLY');
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);
$isTenantAdmin = is_tenant_admin($pdo, $user_id, $academy_id);

// =====================================
// 2. Ticket Logic
// =====================================
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sql = "SELECT * FROM tickets WHERE user_id = :uid";
$params = [':uid' => $user_id];

if ($statusFilter !== 'all') {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Tickets - Learnnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout-core.css">

    <style>
        body {
            background-color: var(--gray-50);
        }
        .main {
            padding: 2.5rem 3rem;
        }

        /* =========================================
           Tickets UI - Table Layout
           ========================================= */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.025em;
        }
        .page-subtitle {
            color: var(--text-muted);
            margin-top: 0.25rem;
            font-size: 0.95rem;
        }

        .btn-new {
            background-color: var(--black);
            color: white;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }
        .btn-new:hover {
            background-color: var(--gray-800);
            transform: translateY(-1px);
        }

        /* Filters (Pills) */
        .filters-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            align-items: center;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        .filter-pill {
            padding: 0.4rem 1rem;
            border-radius: 999px;
            background: white;
            border: 1px solid var(--border-soft);
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .filter-pill:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--text-main);
        }
        .filter-pill.active {
            background: var(--gray-900);
            color: white;
            border-color: var(--gray-900);
        }

        /* Table Card Container */
        .table-container {
            background: white;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .tickets-table th {
            background-color: var(--gray-50);
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-soft);
        }

        .tickets-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-soft);
            vertical-align: middle;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .tickets-table tr:last-child td {
            border-bottom: none;
        }

        .tickets-table tr:hover {
            background-color: var(--gray-50);
        }

        /* Specific Column Styles */
        .col-id {
            width: 100px;
            font-family: monospace;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
        }
        .col-subject {
            font-weight: 600;
            color: var(--gray-900);
        }
        .col-category {
            color: var(--text-muted);
        }
        .col-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .col-status {
            width: 150px;
        }
        .col-action {
            width: 60px;
            text-align: right;
        }

        /* Badges */
        .badge {
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }
        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .badge-nuevo {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .badge-nuevo .badge-dot {
            background: #60a5fa;
        }

        .badge-en_progreso {
            background: #fffbeb;
            color: #b45309;
        }
        .badge-en_progreso .badge-dot {
            background: #f59e0b;
        }

        .badge-resuelto {
            background: #ecfdf5;
            color: #047857;
        }
        .badge-resuelto .badge-dot {
            background: #10b981;
        }

        .badge-cerrado {
            background: #f3f4f6;
            color: #374151;
        }
        .badge-cerrado .badge-dot {
            background: #9ca3af;
        }

        .row-link {
            display: block;
            width: 100%;
            height: 100%;
            color: inherit;
            text-decoration: none;
        }
        .tickets-table tr {
            cursor: pointer;
        }

        .priority-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .prio-high {
            background-color: #EF4444;
        }
        .prio-medium {
            background-color: #F59E0B;
        }
        .prio-normal {
            background-color: #3B82F6;
        }
        .prio-low {
            background-color: #10B981;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
        }
        .empty-icon {
            font-size: 3rem;
            color: var(--gray-200);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .table-container {
                border: none;
                box-shadow: none;
                background: transparent;
            }
            .tickets-table,
            .tickets-table thead,
            .tickets-table tbody,
            .tickets-table th,
            .tickets-table td,
            .tickets-table tr {
                display: block;
            }
            .tickets-table thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            .tickets-table tr {
                border: 1px solid var(--border-soft);
                border-radius: 12px;
                margin-bottom: 1rem;
                background: white;
                padding: 1rem;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }
            .tickets-table td {
                border: none;
                padding: 0.5rem 0;
                position: relative;
                padding-left: 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .col-action {
                text-align: left;
                margin-top: 1rem;
                padding-top: 1rem !important;
                border-top: 1px solid var(--border-soft) !important;
                width: 100%;
                justify-content: center;
            }
            .col-subject {
                font-size: 1.1rem;
                margin-bottom: 0.5rem;
                display: block;
            }
        }
    </style>
</head>
<body class="<?php echo $isHospitality ? 'iuc-theme' : ''; ?>">
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
    <i class="fas fa-bars"></i>
</button>

<div class="layout">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <main class="main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Soporte Técnico</h1>
                <p class="page-subtitle">Historial de tickets</p>
            </div>
            <a href="crear_ticket.php" class="btn-new">
                <i class="fas fa-plus"></i> Nuevo Ticket
            </a>
        </div>

        <!-- Filters (Pills) -->
        <div class="filters-container">
            <a href="?status=all" class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Todos</a>
            <a href="?status=nuevo" class="filter-pill <?= $statusFilter === 'nuevo' ? 'active' : '' ?>">Abiertos</a>
            <a href="?status=en_progreso" class="filter-pill <?= $statusFilter === 'en_progreso' ? 'active' : '' ?>">En Proceso</a>
            <a href="?status=resuelto" class="filter-pill <?= $statusFilter === 'resuelto' ? 'active' : '' ?>">Resueltos</a>
            <a href="?status=cerrado" class="filter-pill <?= $statusFilter === 'cerrado' ? 'active' : '' ?>">Cerrados</a>
        </div>

        <?php if (count($tickets) > 0): ?>
            <div class="table-container">
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Prioridad</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): 
                            $st = $ticket['status'] ?? 'nuevo';
                            $badgeClass = 'badge-' . str_replace(' ', '_', $st);
                            
                            $prio = strtolower($ticket['priority'] ?? 'normal');
                            $prioColor = match($prio) {
                                'high','alta' => 'prio-high',
                                'medium','media' => 'prio-medium',
                                'low','baja' => 'prio-low',
                                default => 'prio-normal'
                            };
                        ?>
                            <tr onclick="window.location.href='ticketuser.php?id=<?= $ticket['id'] ?>'" style="cursor: pointer;">
                                <td class="col-id">#<?= htmlspecialchars($ticket['ticket_id'] ?? $ticket['id']) ?></td>
                                <td class="col-subject"><?= htmlspecialchars($ticket['subject']) ?></td>
                                <td class="col-category"><?= htmlspecialchars($ticket['category']) ?></td>
                                <td class="col-priority">
                                    <span class="priority-indicator <?= $prioColor ?>"></span>
                                    <?= ucfirst($ticket['priority'] ?? 'Normal') ?>
                                </td>
                                <td class="col-date"><?= date('d M Y', strtotime($ticket['created_at'])) ?></td>
                                <td class="col-status">
                                    <span class="badge <?= $badgeClass ?>">
                                        <span class="badge-dot"></span>
                                        <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>
                                <td class="col-action">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-ticket-alt"></i></div>
                <h3>Sin tickets que mostrar</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; margin-bottom: 2rem;">
                    <?php if ($statusFilter !== 'all'): ?>
                        No hay tickets con este estado. <a href="?status=all" style="color:var(--primary); font-weight:600;">Ver todos</a>
                    <?php else: ?>
                        No tienes consultas abiertas.
                    <?php endif; ?>
                </p>
                <a href="crear_ticket.php" class="btn-new">Crear Ticket</a>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

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
</script>

</body>
</html>
<?php exit; ?>
            border-bottom: 1px solid var(--border-soft);
            vertical-align: middle;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .tickets-table tr:last-child td {
            border-bottom: none;
        }

        .tickets-table tr:hover {
            background-color: var(--gray-50);
        }

        /* Specific Column Styles */
        .col-id { width: 100px; font-family: monospace; color: var(--text-muted); font-weight: 600; font-size: 0.85rem; }
        .col-subject { font-weight: 600; color: var(--gray-900); }
        .col-category { color: var(--text-muted); }
        .col-date { color: var(--text-muted); font-size: 0.9rem; }
        .col-status { width: 150px; }
        .col-action { width: 60px; text-align: right; }

        /* Badges */
        .badge { padding: 0.3rem 0.7rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap; }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; }
        
        .badge-nuevo { background: #eff6ff; color: #1d4ed8; }
        .badge-nuevo .badge-dot { background: #60a5fa; }
        
        .badge-en_progreso { background: #fffbeb; color: #b45309; }
        .badge-en_progreso .badge-dot { background: #f59e0b; }
        
        .badge-resuelto { background: #ecfdf5; color: #047857; }
        .badge-resuelto .badge-dot { background: #10b981; }
        
        .badge-cerrado { background: #f3f4f6; color: #374151; }
        .badge-cerrado .badge-dot { background: #9ca3af; }

        .row-link {
            display: block; width: 100%; height: 100%; color: inherit; text-decoration: none;
        }
        /* Trick to make whole row clickable via the subject or action */
        .tickets-table tr { cursor: pointer; }
        
        .priority-indicator {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px;
        }
        .prio-high { background-color: #EF4444; }
        .prio-medium { background-color: #F59E0B; }
        .prio-normal { background-color: #3B82F6; }
        .prio-low { background-color: #10B981; }

        .empty-state {
            text-align: center; padding: 4rem 1rem;
        }
        .empty-icon { font-size: 3rem; color: var(--gray-200); margin-bottom: 1rem; }
        
        @media (max-width: 768px) {
            .table-container { border: none; box-shadow: none; background: transparent; }
            .tickets-table, .tickets-table thead, .tickets-table tbody, .tickets-table th, .tickets-table td, .tickets-table tr { 
                display: block; 
            }
            .tickets-table thead tr { position: absolute; top: -9999px; left: -9999px; }
            .tickets-table tr { 
                border: 1px solid var(--border-soft); border-radius: 12px; margin-bottom: 1rem; background: white; padding: 1rem;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }
            .tickets-table td { 
                border: none; padding: 0.5rem 0; position: relative; padding-left: 0; display: flex; justify-content: space-between; align-items: center;
            }
            .col-action { text-align: left; margin-top: 1rem; padding-top: 1rem !important; border-top: 1px solid var(--border-soft) !important; width: 100%; justify-content: center; }
            .col-subject { font-size: 1.1rem; margin-bottom: 0.5rem; display: block; }
        }
    </style>
</head>
<body>

<!-- Sidebar Toggle for Mobile -->
<button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
    <i class="fas fa-bars"></i>
</button>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>LEARNNECT</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="calendar.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendario</span>
            </a>
            <a href="biblioteca.php">
                <i class="fas fa-book"></i>
                <span>Biblioteca</span>
            </a>
            <a href="tickets.php" class="active">
                <i class="fas fa-ticket-alt"></i>
                <span>Tickets</span>
            </a>
            <a href="soporte.php">
                <i class="fas fa-headset"></i>
                <span>Soporte</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-comments"></i>
                <span>Mensajes</span>
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar sesión</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Soporte Técnico</h1>
                <p class="page-subtitle">Historial de tickets</p>
            </div>
            <a href="crear_ticket.php" class="btn-new">
                <i class="fas fa-plus"></i> Nuevo Ticket
            </a>
        </div>

        <!-- Filters (Pills) -->
        <div class="filters-container">
            <a href="?status=all" class="filter-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Todos</a>
            <a href="?status=nuevo" class="filter-pill <?= $statusFilter === 'nuevo' ? 'active' : '' ?>">Abiertos</a>
            <a href="?status=en_progreso" class="filter-pill <?= $statusFilter === 'en_progreso' ? 'active' : '' ?>">En Proceso</a>
            <a href="?status=resuelto" class="filter-pill <?= $statusFilter === 'resuelto' ? 'active' : '' ?>">Resueltos</a>
            <a href="?status=cerrado" class="filter-pill <?= $statusFilter === 'cerrado' ? 'active' : '' ?>">Cerrados</a>
        </div>

        <?php if (count($tickets) > 0): ?>
            <div class="table-container">
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asunto</th>
                            <th>Categoría</th>
                            <th>Prioridad</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): 
                            $st = $ticket['status'] ?? 'nuevo';
                            $badgeClass = 'badge-' . str_replace(' ', '_', $st);
                            
                            $prio = strtolower($ticket['priority'] ?? 'normal');
                            $prioColor = match($prio) {
                                'high','alta' => 'prio-high',
                                'medium','media' => 'prio-medium',
                                'low','baja' => 'prio-low',
                                default => 'prio-normal'
                            };
                        ?>
                            <tr onclick="window.location.href='ticketuser.php?id=<?= $ticket['id'] ?>'" style="cursor: pointer;">
                                <td class="col-id">#<?= htmlspecialchars($ticket['ticket_id'] ?? $ticket['id']) ?></td>
                                <td class="col-subject"><?= htmlspecialchars($ticket['subject']) ?></td>
                                <td class="col-category"><?= htmlspecialchars($ticket['category']) ?></td>
                                <td class="col-priority">
                                    <span class="priority-indicator <?= $prioColor ?>"></span>
                                    <?= ucfirst($ticket['priority'] ?? 'Normal') ?>
                                </td>
                                <td class="col-date"><?= date('d M Y', strtotime($ticket['created_at'])) ?></td>
                                <td class="col-status">
                                    <span class="badge <?= $badgeClass ?>">
                                        <span class="badge-dot"></span>
                                        <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>
                                <td class="col-action">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-ticket-alt"></i></div>
                <h3>Sin tickets que mostrar</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; margin-bottom: 2rem;">
                    <?php if ($statusFilter !== 'all'): ?>
                        No hay tickets con este estado. <a href="?status=all" style="color:var(--primary); font-weight:600;">Ver todos</a>
                    <?php else: ?>
                        No tienes consultas abiertas.
                    <?php endif; ?>
                </p>
                <a href="crear_ticket.php" class="btn-new">Crear Ticket</a>
            </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>



