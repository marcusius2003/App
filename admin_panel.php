<?php
/**
 * Admin Panel Controller - Learnnect
 * Redesigned 2024
 */

session_start();
// Evitar cache en opcode para desarrollo.
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
    @opcache_invalidate(__DIR__ . '/billing_module/src/Views/Admin/invoice_studio.php', true);
}
require_once 'admin/config/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$database = new Database();
$conn = $database->getConnection();

$currentUser = requireActiveUser($conn);
if (!is_platform_admin($conn, (int) $currentUser['id'])) {
    header('Location: dashboard.php');
    exit;
}
if (isset($_GET['academy_id'])) {
    $requestedAcademyId = (int) $_GET['academy_id'];
    if ($requestedAcademyId > 0) {
        $stmt = $conn->prepare("SELECT id, name FROM academies WHERE id = ? LIMIT 1");
        $stmt->execute([$requestedAcademyId]);
        $academyRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($academyRow) {
            $_SESSION['academy_id'] = (int) $academyRow['id'];
            $_SESSION['academy_name'] = $academyRow['name'] ?? null;
        }
    } elseif ($requestedAcademyId === 0) {
        unset($_SESSION['academy_id'], $_SESSION['academy_name']);
    }
}

// Helper to check active features
function getAllTenantFeatures($conn, $academyId) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0 && is_platform_admin($conn, $userId)) {
        // Si el platform admin estÃ¡ viendo un tenant concreto, reflejar sus features (para que el sidebar sea coherente).
        if ($academyId) {
            $stmt = $conn->query("SELECT code FROM features");
            $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $enabled = [];
            foreach ($codes as $code) {
                if (tenant_has_feature($conn, (int) $academyId, (string) $code)) {
                    $enabled[] = $code;
                }
            }
            return $enabled;
        }

        // Sin tenant seleccionado: mostrar todo.
        $stmt = $conn->query("SELECT code FROM features");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!$academyId) return [];

    $stmt = $conn->query("SELECT code FROM features");
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $enabled = [];
    foreach ($codes as $code) {
        if (tenant_has_feature($conn, $academyId, $code)) {
            $enabled[] = $code;
        }
    }
    return $enabled;
}

// Determine current academy context
$currentAcademyId = $_SESSION['academy_id'] ?? 0;
$activeFeatures = getAllTenantFeatures($conn, $currentAcademyId);

function hasFeature($code) {
    global $activeFeatures;
    if (strpos($code, 'core.') === 0) return true;

    foreach ($activeFeatures as $f) {
        if ($f === $code) return true;
        if (strpos($code, '.*') !== false) {
             $prefix = str_replace('.*', '', $code);
             if (strpos($f, $prefix) === 0) return true;
        }
    }
    return false;
}

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';

function view($name) {
    return __DIR__ . "/admin/views/{$name}.php";
}

// Include Classes
require_once __DIR__ . '/admin/classes/TenantManager.php';
require_once __DIR__ . '/billing_module/routes/web.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnnect Admin Center</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
        
        /* Smooth transitions */
        .transition-all { transition-property: all; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 200ms; }
        
        /* Focus styles */
        input:focus, select:focus, textarea:focus, button:focus { outline: none; }
        
        /* Animations */
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(8px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        .animate-fadeIn { animation: fadeIn 0.25s ease-out; }
        
        @keyframes slideInLeft { 
            from { opacity: 0; transform: translateX(-16px); } 
            to { opacity: 1; transform: translateX(0); } 
        }
        .animate-slideIn { animation: slideInLeft 0.25s ease-out; }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-scaleIn { animation: scaleIn 0.2s ease-out; }
        
        /* Glass effect */
        .glass { background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); }
        
        /* Line clamp */
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        /* Card hover effect */
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1); }
        
        /* Table styles */
        .table-container { overflow-x: auto; }
        .table-container::-webkit-scrollbar { height: 4px; }

        body.dark-mode {
            background-color: #0b1120;
            color: #e2e8f0;
        }
        .dark-mode .bg-white { background-color: #0f172a !important; }
        .dark-mode .bg-white\/95 { background-color: rgba(15, 23, 42, 0.9) !important; }
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
        .dark-mode .glass { background: rgba(15, 23, 42, 0.75); }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gray: {
                            50: '#fafafa', 100: '#f4f4f5', 200: '#e4e4e7', 300: '#d4d4d8',
                            400: '#a1a1aa', 500: '#71717a', 600: '#52525b', 700: '#3f3f46',
                            800: '#27272a', 900: '#18181b', 950: '#09090b'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    <?php include 'admin/includes/header.php'; ?>

    <div class="flex pt-14 min-h-screen">
        <?php include 'admin/includes/sidebar.php'; ?>

        <main class="flex-1 md:ml-64">
            <div class="h-[calc(100vh-3.5rem)] overflow-y-auto">
                <?php
                switch ($page) {
                    case 'dashboard':
                        include view('dashboard');
                        break;
                    case 'tenants':
                        if ($action === 'create') include view('tenants/create_wizard');
                        elseif ($action === 'view' && isset($_GET['id'])) include view('tenants/view');
                        else include view('tenants/index');
                        break;
                    case 'users':
                        if ($action === 'create') include view('users/create');
                        else include view('users/index');
                        break;
                    case 'plans':
                        if ($action === 'edit_features' && isset($_GET['id'])) include view('plans/edit_features');
                        elseif ($action === 'create') include view('plans/create');
                        else include view('plans/index');
                        break;
                    case 'exams':
                        require_feature($conn, 'edu.exams');
                        if ($action === 'grade' && isset($_GET['id'])) include view('exams/grade');
                        else include view('exams/index');
                        break;
                    case 'support':
                        if ($action === 'create') include view('support/create');
                        elseif ($action === 'view' && isset($_GET['id'])) include view('support/view');
                        else include view('support/index');
                        break;
                    case 'logs':
                        include view('logs/index');
                        break;
                    case 'settings':
                        include view('settings/index');
                        break;
                    
                    // --- RESTAURANT PACK ROUTES ---
                    case 'floorplan':
                        require_feature($conn, 'rest.floorplan');
                        include view('restaurant/floorplan');
                        break;
                    case 'reservations':
                        require_feature($conn, 'rest.reservations');
                        include view('restaurant/reservations');
                        break;
                    case 'waitlist':
                        require_feature($conn, 'rest.waitlist');
                        include view('restaurant/waitlist');
                        break;
                    case 'analytics':
                        require_feature($conn, 'rest.analytics');
                        include view('restaurant/analytics');
                        break;
                    case 'shifts':
                        require_feature($conn, 'rest.shifts');
                        include view('restaurant/shifts');
                        break;
                    case 'menu':
                        require_feature($conn, 'rest.menu');
                        include view('restaurant/menu');
                        break;
                    case 'kds':
                        require_feature($conn, 'rest.kds');
                        include view('restaurant/kds');
                        break;
                    case 'announcements':
                        if ($action === 'create') include view('announcements/create');
                        else include view('announcements/index');
                        break;
                    case 'billing':
                        $billingController = new BillingController($conn);
                        $subpage = $_GET['subpage'] ?? '';
                        switch ($subpage) {
                            case 'subscriptions':
                                $billingController->adminSubscriptions();
                                break;
                            case 'invoices':
                                $billingController->adminInvoices();
                                break;
                            case 'payments':
                                $billingController->adminPayments();
                                break;
                            case 'webhooks':
                                $billingController->adminWebhooks();
                                break;
                            case 'customers':
                                $billingController->adminCustomers();
                                break;
                            default:
                                // Show the invoice studio even when an academy is selected in session.
                                $billingController->adminInvoiceStudio();
                                break;
                        }
                        break;
                    default:
                        echo "<div class='p-8'><div class='bg-red-50 text-red-700 p-6 rounded-2xl border border-red-200'>
                            <h3 class='font-bold mb-1'>P�gina no encontrada</h3>
                            <p class='text-sm'>La p�gina solicitada no existe.</p>
                            <a href='?page=dashboard' class='inline-block mt-3 text-sm font-medium text-red-700 hover:text-red-900'>? Volver al Dashboard</a>
                        </div></div>";
                }
                ?>
            </div>
        </main>
    </div>

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

        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        let isDark = (readStored() ?? (prefersDark ? 'dark' : 'light')) === 'dark';

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




