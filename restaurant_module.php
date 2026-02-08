<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$allowedPages = ['floorplan', 'reservations', 'waitlist', 'analytics', 'shifts', 'menu', 'kds'];
$requestedPage = strtolower(trim((string) ($_GET['page'] ?? '')));
$redirectParams = [];
foreach ($_GET as $key => $value) {
    if ($key === 'page' || !is_scalar($value)) {
        continue;
    }
    $redirectParams[$key] = (string) $value;
}
if (in_array($requestedPage, $allowedPages, true)) {
    $redirectParams['restaurant_page'] = $requestedPage;
}
$redirectTarget = 'dashboard.php';
if ($redirectParams) {
    $redirectTarget .= '?' . http_build_query($redirectParams);
}
header('Location: ' . $redirectTarget);
exit();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_context.php';

$currentUser = requireActiveUser($pdo);
$academy_id = $currentUser['academy_id'] ?? null;
$academyName = $currentUser['academy_name'] ?? $currentUser['academy'] ?? 'IUConnect';

$tenantContext = new TenantContext($pdo);
try {
    $context = $tenantContext->resolveTenantContext();
    if (!empty($context['academy_id'])) {
        $academy_id = $context['academy_id'];
    }
} catch (Exception $e) {
    // Mantener la academia del usuario si el contexto falla.
}

$template = $tenantContext->getTenantTemplate($academy_id);
$templateCode = strtoupper($template['code'] ?? 'CORE_ONLY');
if ($templateCode !== 'RESTAURANT') {
    header('Location: dashboard.php');
    exit();
}

$conn = $pdo;
$currentAcademyId = $academy_id;

$normalizedRole = strtolower(trim((string) ($currentUser['role'] ?? '')));
$isAdmin = in_array($normalizedRole, ['admin', 'administrator', 'administrador', 'chef', 'manager'], true);

$modules = [
    'floorplan' => [
        'label' => 'Plano de Sala',
        'icon' => 'fas fa-couch',
        'view' => 'floorplan.php',
        'description' => 'Mapa activo de mesas y zonas con acciones rápidas.',
    ],
    'reservations' => [
        'label' => 'Reservas',
        'icon' => 'fas fa-calendar-check',
        'view' => 'reservations.php',
        'description' => 'Controla reservas, estados y asignaciones de mesa.',
    ],
    'waitlist' => [
        'label' => 'Lista de Espera',
        'icon' => 'fas fa-list-ul',
        'view' => 'waitlist.php',
        'description' => 'Gestiona la cola y comunica avances con la sala.',
    ],
    'analytics' => [
        'label' => 'Métricas HORECA',
        'icon' => 'fas fa-chart-line',
        'view' => 'analytics.php',
        'description' => 'Resumen operativo con alertas, ingresos y ocupación.',
    ],
    'shifts' => [
        'label' => 'Turnos',
        'icon' => 'fas fa-user-clock',
        'view' => 'shifts.php',
        'description' => 'Control de entradas/salidas y turnos activos.',
    ],
    'menu' => [
        'label' => 'Carta Digital',
        'icon' => 'fas fa-utensils',
        'view' => 'menu.php',
        'description' => 'Presentación de la carta digital con precios.',
    ],
    'kds' => [
        'label' => 'Comandas (KDS)',
        'icon' => 'fas fa-fire',
        'view' => 'kds.php',
        'description' => 'Comandas en curso, cambios de estado y nuevas órdenes.',
    ],
];

$defaultPage = 'floorplan';
$requestedPage = strtolower(trim((string) ($_GET['page'] ?? $defaultPage)));
$page = isset($modules[$requestedPage]) ? $requestedPage : $defaultPage;
$moduleMeta = $modules[$page];
$moduleView = __DIR__ . '/admin/views/restaurant/' . $moduleMeta['view'];
$moduleAvailable = is_file($moduleView);

require_once __DIR__ . '/admin/views/restaurant/_helpers.php';

$totalTables = $occupiedTables = $todayReservations = $activeOrders = 0;
$nextReservationTime = null;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE academy_id = :aid");
    $stmt->execute([':aid' => $currentAcademyId]);
    $totalTables = (int) ($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE academy_id = :aid AND status = 'occupied'");
    $stmt->execute([':aid' => $currentAcademyId]);
    $occupiedTables = (int) ($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_reservations WHERE academy_id = :aid AND DATE(reservation_time) = CURDATE()");
    $stmt->execute([':aid' => $currentAcademyId]);
    $todayReservations = (int) ($stmt->fetchColumn() ?? 0);

    $nextStmt = $pdo->prepare("SELECT reservation_time FROM restaurant_reservations WHERE academy_id = :aid AND reservation_time >= NOW() ORDER BY reservation_time ASC LIMIT 1");
    $nextStmt->execute([':aid' => $currentAcademyId]);
    $nextReservationTime = $nextStmt->fetchColumn() ?: null;

    $ordersStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_kds_orders WHERE academy_id = :aid AND status NOT IN ('served', 'cancelled')");
    $ordersStmt->execute([':aid' => $currentAcademyId]);
    $activeOrders = (int) ($ordersStmt->fetchColumn() ?? 0);
} catch (Exception $e) {
    // Silenciar errores de esquemas faltantes.
}

$occupancyPercent = $totalTables > 0 ? (int) round(($occupiedTables / $totalTables) * 100) : 0;
$nextReservationLabel = 'Sin reserva';
if (!empty($nextReservationTime)) {
    try {
        $dt = new DateTime($nextReservationTime);
        $nextReservationLabel = $dt->format('H:i');
    } catch (Exception $e) {
        $nextReservationLabel = 'Sin reserva';
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IUConnect Operaciones Restaurant</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-p6P0vF+s2G6a23vnM0/UV7uMG9gOYhAqOYQn/uDP7gYQGy9KWM56x0i6m0E2mwS2ZEyZk91RNo6Vq940bV1gHg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f5f7fb;
            color: #0f172a;
        }
        .restaurant-shell {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: 100vh;
        }
        .module-nav {
            background: linear-gradient(180deg, #0f172a, #111827);
            color: #e2e8f0;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
        }
        .module-nav .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #fcd34d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #111827;
        }
        .module-nav nav {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            flex: 1;
        }
        .module-nav a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.75rem 0.9rem;
            border-radius: 12px;
            color: inherit;
            font-weight: 500;
            letter-spacing: 0.02em;
            transition: background 0.2s ease;
        }
        .module-nav a.active,
        .module-nav a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }
        .module-nav .nav-icon {
            width: 1.2rem;
            display: inline-flex;
            justify-content: center;
        }
        .module-nav-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .module-nav-footer a {
            display: inline-flex;
            gap: 0.5rem;
            align-items: center;
            font-size: 0.85rem;
            color: #cbd5f5;
            padding: 0.45rem 0.65rem;
            border-radius: 999px;
            border: 1px solid transparent;
        }
        .module-nav-footer a.secondary {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .module-main {
            padding: 2rem;
            overflow-x: hidden;
        }
        .module-main-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .module-main-header .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 0.75rem;
            color: #475569;
        }
        .module-main-header h1 {
            margin: 0.25rem 0;
            font-size: clamp(1.8rem, 2.4vw, 2.4rem);
        }
        .module-main-header .description {
            color: #475569;
            margin: 0;
            max-width: 40rem;
        }
        .module-quick {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        .module-quick-btn {
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            background: #0ea5e9;
            color: white;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            text-decoration: none;
        }
        .module-quick-btn i {
            font-size: 0.9rem;
        }
        .module-quick .turno-pill {
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #d1fae5;
        }
        .kpi-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .kpi-card {
            background: white;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            box-shadow: 0 20px 45px -24px rgba(15, 23, 42, 0.5);
        }
        .kpi-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #94a3b8;
        }
        .kpi-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0.4rem 0;
        }
        .kpi-note {
            margin: 0;
            color: #475569;
            font-size: 0.85rem;
        }
        .module-content {
            background: #ffffff;
            border-radius: 22px;
            padding: 1.5rem 1.75rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 30px 65px -35px rgba(15, 23, 42, 0.4);
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            border: 1px dashed #cbd5f5;
            border-radius: 18px;
            color: #475569;
        }
        .empty-state a {
            color: #0ea5e9;
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .restaurant-shell {
                grid-template-columns: 1fr;
            }
            .module-nav {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            .module-nav nav {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .module-main {
                padding: 1.25rem;
            }
            .module-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="restaurant-shell">
        <aside class="module-nav">
            <div class="brand">
                <div class="brand-icon">IU</div>
                <div>
                    <strong>IUConnect</strong>
                    <p style="margin: 0; font-size: 0.8rem;">Tenant Restauración</p>
                </div>
            </div>
            <nav>
                <?php foreach ($modules as $slug => $info): ?>
                    <a href="?page=<?php echo $slug; ?>" class="<?php echo $slug === $page ? 'active' : ''; ?>">
                        <i class="<?php echo $info['icon']; ?> nav-icon"></i>
                        <span><?php echo e($info['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="module-nav-footer">
                <a href="dashboard.php" class="secondary">
                    <i class="fas fa-chart-pie"></i>
                    <span>Volver al Dashboard</span>
                </a>
                <a href="messages.php">
                    <i class="fas fa-comments"></i>
                    <span>Mensajes</span>
                </a>
            </div>
        </aside>
        <main class="module-main">
            <header class="module-main-header">
                <div>
                    <p class="eyebrow"><?php echo e($academyName); ?> · Restauración</p>
                    <h1><?php echo e($moduleMeta['label']); ?></h1>
                    <p class="description"><?php echo e($moduleMeta['description']); ?></p>
                </div>
                <div class="module-quick">
                    <span class="turno-pill">Turno Activo</span>
                    <a class="module-quick-btn" href="restaurant_module.php?page=floorplan">
                        <i class="fas fa-eye"></i>
                        <span>Ver sala</span>
                    </a>
                </div>
            </header>
            <div class="kpi-strip">
                <div class="kpi-card">
                    <p class="kpi-label">Mesas ocupadas</p>
                    <p class="kpi-value"><?php echo $occupiedTables; ?> / <?php echo $totalTables ?: '--'; ?></p>
                    <p class="kpi-note"><?php echo $occupancyPercent; ?>% ocupación</p>
                </div>
                <div class="kpi-card">
                    <p class="kpi-label">Reservas hoy</p>
                    <p class="kpi-value"><?php echo $todayReservations; ?></p>
                    <p class="kpi-note">Próxima <?php echo e($nextReservationLabel); ?></p>
                </div>
                <div class="kpi-card">
                    <p class="kpi-label">Tickets activos</p>
                    <p class="kpi-value"><?php echo $activeOrders; ?></p>
                    <p class="kpi-note">Comandas sin servir</p>
                </div>
            </div>
            <section class="module-content">
                <?php if ($moduleAvailable): ?>
                    <?php require $moduleView; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Esta sección no está disponible en este momento.</p>
                        <p>Regresa al <a href="?page=floorplan">Plano de Sala</a> o al <a href="dashboard.php">dashboard principal</a>.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
