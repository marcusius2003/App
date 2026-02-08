<?php
// settings.php - Configuración Integrada en el Dashboard
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/admin/config/db.php';

$database = new Database();
$pdo = $database->getConnection();

require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';
require_once __DIR__ . '/includes/tenant_context.php';

$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'core.settings');

// Normalizar rol
$normalizedRole = strtolower(trim($currentUser['role'] ?? ''));
$isAdmin = in_array($normalizedRole, ['admin', 'administrator', 'administrador'], true);
$isChef  = ($normalizedRole === 'chef'); 

// Si es CHEF, también lo tratamos como admin para efectos de configuración
if ($isChef) {
    $isAdmin = true; 
}

// Cargar contexto del tenant
$tenantContext = new TenantContext($pdo);
try {
    $context = $tenantContext->resolveTenantContext();
    $academy_id = $context['academy_id'];
} catch (Exception $e) {
    // Fallback
    $academy_id = (int) ($currentUser['academy_id'] ?? 0);
}

if (!empty($academy_id)) {
    $template = $tenantContext->getTenantTemplate((int) $academy_id);
} else {
    $template = ['code' => 'CORE_ONLY'];
}

$templateCode = strtoupper((string) ($template['code'] ?? 'CORE_ONLY'));
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);
if ($academy_id > 0 && $templateCode !== 'EDUCATION' && !$isHospitality) {
    $eduSignals = ['edu.courses', 'edu.assignments', 'edu.exams', 'edu.library'];
    foreach ($eduSignals as $signal) {
        if (tenant_has_feature($pdo, (int) $academy_id, $signal)) {
            $templateCode = 'EDUCATION';
            $template['code'] = 'EDUCATION';
            break;
        }
    }
}
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);
$isTenantAdmin = ($academy_id > 0 && !empty($currentUser['id']))
    ? is_tenant_admin($pdo, (int) $currentUser['id'], (int) $academy_id)
    : false;
$hasSidebar = ($academy_id > 0);

// Datos para la UI
$username = $currentUser['username'] ?? 'Usuario';
$academyName = $currentUser['academy_name'] ?? 'Mi Academia';
$userRole = ucfirst($normalizedRole);

// --- HANDLER: POST REQUESTS ---
$message = '';
$messageType = ''; // success | error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_global' && $isAdmin) {
        // 1. Update Global Settings (Academy)
        $newName = trim($_POST['academy_name'] ?? '');
        $newWebsite = trim($_POST['company_website'] ?? ''); // Note: We might not have a column for this yet, so we'll just acknowledge it or store if possible. 
        // For now, let's strictly update the NAME which we know exists.
        
        if (!empty($newName)) {
            try {
                $stmt = $pdo->prepare("UPDATE academies SET name = :name WHERE id = :id");
                $stmt->execute([':name' => $newName, ':id' => $academy_id]);
                
                // Update session variable to reflect change immediately if needed, though mostly fetched fresh.
                $message = "Configuración global actualizada correctamente.";
                $messageType = "success";
                
                // Refresh local var
                $academyName = $newName;
                
            } catch (PDOException $e) {
                $message = "Error al actualizar la configuración: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "El nombre de la academia no puede estar vacío.";
            $messageType = "error";
        }
        
    } elseif ($action === 'update_profile') {
        // 2. Update User Profile
        $newEmail = trim($_POST['email'] ?? '');
        
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET email = :email WHERE id = :id");
                $stmt->execute([':email' => $newEmail, ':id' => $currentUser['id']]);
                
                $message = "Perfil actualizado correctamente.";
                $messageType = "success";
                
                // Update local var
                $currentUser['email'] = $newEmail;
                
            } catch (PDOException $e) {
                 $message = "Error al actualizar el perfil: " . $e->getMessage();
                 $messageType = "error";
            }
        } else {
            $message = "Por favor, introduce un email válido.";
            $messageType = "error";
        }
    }
}


// --- HANDLER: FLOORPLAN ACTIONS (Hoisted) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['tab']) && $_GET['tab'] === 'floorplan') {
    // Variables necesarias
    $conn = $pdo;
    $currentAcademyId = $academy_id;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_table') {
        $name = $_POST['name'];
        $zoneId = $_POST['zone_id'];
        $capacity = $_POST['capacity'];
        
        $stmt = $conn->prepare("INSERT INTO restaurant_tables (academy_id, zone_id, name, capacity, status) VALUES (?, ?, ?, ?, 'free')");
        $stmt->execute([$currentAcademyId, $zoneId, $name, $capacity]);
        // Redirect to same tab
        header("Location: ?tab=floorplan&msg=created");
        exit;
    }
    
    if ($action === 'toggle_status') {
        $tableId = $_POST['table_id'];
        $newStatus = $_POST['status']; // free, occupied
        $stmt = $conn->prepare("UPDATE restaurant_tables SET status = ? WHERE id = ? AND academy_id = ?");
        $stmt->execute([$newStatus, $tableId, $currentAcademyId]);
        exit; // Ajax
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | Learnnect</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS (via CDN para compatibilidad con el estilo de admin settings) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/layout-core.css">

    <!-- Estilos base del Dashboard para mantener consistencia en Sidebar/Layout -->
    <style>
        :root {
            --bg-body: #f3f4f6;
            --bg-sidebar: #0f172a;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --primary: #2563eb; 
            --white: #ffffff;
            --border-soft: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            font-size: 15px; 
        }
        
        /* Main Content */
        .main {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        
        /* Ajustes de Tailwind conflicto con estilos base */
        a { text-decoration: none; }
    </style>
</head>
<body class="<?php echo $isHospitality ? 'iuc-theme iuc-theme-light' : (($templateCode ?? '') === 'EDUCATION' ? 'edu-sidebar-theme' : ''); ?> bg-gray-50 text-gray-900">

<button class="menu-toggle" id="menuToggle" aria-label="Abrir menu">
    <i class="fas fa-bars"></i>
</button>

<div class="layout">
    <?php if ($hasSidebar): ?>
        <?php include __DIR__ . '/includes/navigation.php'; ?>
    <?php endif; ?>

    <?php if (false): ?>
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
            
            <?php 
            $templateCode = strtoupper($template['code'] ?? 'CORE_ONLY');
            if ($templateCode === 'EDUCATION'): 
            ?>
                <a href="calendar.php"><i class="fas fa-calendar-alt"></i><span>Calendario</span></a>
                <a href="biblioteca.php"><i class="fas fa-book"></i><span>Biblioteca</span></a>
                <a href="entregas.php"><i class="fas fa-tasks"></i><span>Exámenes y Tareas</span></a>
                <a href="pizarra.php"><i class="fas fa-chalkboard"></i><span>Pizarra</span></a>
            <?php elseif ($templateCode === 'RESTAURANT'): ?>
                <a href="dashboard.php?restaurant_page=floorplan"><i class="fas fa-couch"></i><span>Plano de Sala</span></a>
                <a href="dashboard.php?restaurant_page=reservations"><i class="fas fa-calendar-check"></i><span>Reservas</span></a>
                <a href="dashboard.php?restaurant_page=waitlist"><i class="fas fa-list-ul"></i><span>Lista de Espera</span></a>
                <a href="dashboard.php?restaurant_page=analytics"><i class="fas fa-chart-line"></i><span>Métricas HORECA</span></a>
                <a href="dashboard.php?restaurant_page=shifts"><i class="fas fa-user-clock"></i><span>Turnos</span></a>
                <a href="dashboard.php?restaurant_page=menu"><i class="fas fa-utensils"></i><span>Carta Digital</span></a>
                <a href="dashboard.php?restaurant_page=kds"><i class="fas fa-fire"></i><span>Comandas (KDS)</span></a>
            <?php endif; ?>
            
            <a href="tickets.php"><i class="fas fa-ticket-alt"></i><span>Tickets</span></a>
            <a href="soporte.php"><i class="fas fa-headset"></i><span>Soporte</span></a>
            <a href="messages.php"><i class="fas fa-comments"></i><span>Mensajes</span></a>
            
            <?php if ($isAdmin): ?>
            <a href="admin_panel.php"><i class="fas fa-user-shield"></i><span>Admin Panel</span></a>
            <?php endif; ?>
            
            <a href="settings.php" class="active"><i class="fas fa-cog"></i><span>Configuración</span></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span></a>
        </nav>
    </aside>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="main bg-gray-50 min-h-screen" <?php echo $hasSidebar ? '' : 'style="margin-left:0"'; ?>>
        
        <!-- Top Bar Simplificado -->
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center sticky top-0 z-40">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
                <p class="text-sm text-gray-500">Administra tu perfil <?php echo $isAdmin ? 'y la plataforma' : ''; ?></p>
            </div>
            <div class="flex items-center gap-4">
                 <div class="text-right hidden sm:block">
                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></div>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($academyName); ?></div>
                </div>
                <div class="h-10 w-10 rounded-full bg-gray-800 flex items-center justify-center text-white font-bold">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-8 max-w-7xl mx-auto w-full">
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 rounded-md p-4 <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($isAdmin): ?>
            <!-- Admin View: Configuración Global (Embebida) -->
            <?php
            $activeTab = $_GET['tab'] ?? 'general';
            ?>
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-12 min-h-[600px]">
                    
                    <!-- Settings Sidebar -->
                    <div class="md:col-span-3 bg-gray-50 border-r border-gray-200 p-4">
                        <nav class="space-y-1">
                            <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 mt-2">Global</h3>
                            <a href="?tab=general" class="<?php echo $activeTab === 'general' ? 'bg-white text-gray-900 shadow-sm border-gray-200' : 'text-gray-600 hover:bg-white hover:text-gray-900 border-transparent'; ?> group flex items-center px-3 py-2 text-sm font-medium rounded-md border">
                                <i class="fas fa-sliders-h w-6 text-gray-500"></i> General
                            </a>
                            <a href="?tab=tenants" class="<?php echo $activeTab === 'tenants' ? 'bg-white text-gray-900 shadow-sm border-gray-200' : 'text-gray-600 hover:bg-white hover:text-gray-900 border-transparent'; ?> group flex items-center px-3 py-2 text-sm font-medium rounded-md border">
                                <i class="fas fa-globe w-6 text-gray-400 group-hover:text-gray-500"></i> Tenants
                            </a>
                            <a href="?tab=roles" class="<?php echo $activeTab === 'roles' ? 'bg-white text-gray-900 shadow-sm border-gray-200' : 'text-gray-600 hover:bg-white hover:text-gray-900 border-transparent'; ?> group flex items-center px-3 py-2 text-sm font-medium rounded-md border">
                                <i class="fas fa-shield-alt w-6 text-gray-400 group-hover:text-gray-500"></i> Roles y Permisos
                            </a>
                            
                            <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 mt-6">Restaurante</h3>
                            <a href="?tab=menu" class="<?php echo $activeTab === 'menu' ? 'bg-white text-gray-900 shadow-sm border-gray-200' : 'text-gray-600 hover:bg-white hover:text-gray-900 border-transparent'; ?> group flex items-center px-3 py-2 text-sm font-medium rounded-md border">
                                <i class="fas fa-utensils w-6 text-gray-400 group-hover:text-gray-500"></i> Menú Digital
                            </a>
                            <a href="?tab=floorplan" class="<?php echo $activeTab === 'floorplan' ? 'bg-white text-gray-900 shadow-sm border-gray-200' : 'text-gray-600 hover:bg-white hover:text-gray-900 border-transparent'; ?> group flex items-center px-3 py-2 text-sm font-medium rounded-md border">
                                <i class="fas fa-couch w-6 text-gray-400 group-hover:text-gray-500"></i> Distribución Sala
                            </a>
                            <a href="?tab=reservations" class="<?php echo $activeTab === 'reservations' ? 'bg-white text-gray-900 shadow-sm border-gray-200' : 'text-gray-600 hover:bg-white hover:text-gray-900 border-transparent'; ?> group flex items-center px-3 py-2 text-sm font-medium rounded-md border">
                                <i class="fas fa-calendar-alt w-6 text-gray-400 group-hover:text-gray-500"></i> Reglas Reservas
                            </a>
                        </nav>
                    </div>

                    <!-- Settings Content -->
                    <div class="md:col-span-9 p-8">
                        <?php if ($activeTab === 'general'): ?>
                            <h2 class="text-lg leading-6 font-medium text-gray-900 mb-6 border-b pb-4">Configuración General de la Plataforma</h2>
                            
                            <form class="space-y-6 max-w-2xl" method="POST">
                                <input type="hidden" name="action" value="update_global">
                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                    <div class="sm:col-span-4">
                                        <label class="block text-sm font-medium text-gray-700">Nombre de la Academia / Restaurante</label>
                                        <div class="mt-1">
                                            <input type="text" name="academy_name" value="<?php echo htmlspecialchars($academyName); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-6">
                                        <label class="block text-sm font-medium text-gray-700">URL del Tenant</label>
                                        <div class="mt-1 flex rounded-md shadow-sm">
                                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">https://</span>
                                            <input type="text" name="company_website" value="iuconnect.net/<?php echo strtolower(str_replace(' ', '-', $academyName)); ?>" class="flex-1 focus:ring-blue-500 focus:border-blue-500 block w-full min-w-0 rounded-none rounded-r-md sm:text-sm border-gray-300 p-2 border">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-6">
                                        <label class="block text-sm font-medium text-gray-700">Logo URL</label>
                                        <div class="mt-1 flex items-center gap-4">
                                            <span class="h-12 w-12 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center border border-gray-200">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </span>
                                            <button type="button" class="bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                                                Cambiar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-5 flex justify-end">
                                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none mr-3">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
                                        Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        
                        <?php elseif ($activeTab === 'floorplan'): ?>
                            
                            <?php
                            // Configurar entorno para el módulo
                            $conn = $pdo; 
                            $currentAcademyId = $academy_id;
                            
                            // Ajustar script de JS para que sepa dónde hacer post
                            // El módulo floorplan.php hace fetch a '?page=floorplan'. Aquí debe ser '?tab=floorplan'.
                            // Como es un include, podemos definir una variable base o parchear el JS en el output buffer, 
                            // o simplemente dejar que floorplan.php detecte el entorno.
                            // Por ahora, incluimos el archivo. Nota: floorplan.php tiene lógica de POST al inicio.
                            // Esa lógica de POST debe ejecutarse ANTES de imprimir HTML si queremos redirección limpia.
                            // Pero settings.php ya imprimió headers.
                            // Revisitaremos la lógica POST en settings.php al principio del archivo si es necesario.
                            
                            include __DIR__ . '/admin/views/restaurant/floorplan.php';
                            ?>
                            
                            <!-- Parche JS para que el módulo use la URL correcta en settings -->
                            <script>
                                // Override del fetch original si es necesario
                                const originalFetch = window.fetch;
                                window.fetch = function(url, options) {
                                    if (url === '?page=floorplan') {
                                        url = '?tab=floorplan';
                                    }
                                    return originalFetch(url, options);
                                };
                            </script>

                        <?php elseif ($activeTab === 'menu'): ?>
                             <div class="space-y-6">
                                <div>
                                    <h2 class="text-lg leading-6 font-medium text-gray-900 border-b pb-4">Carta Digital y QR</h2>
                                    <p class="mt-4 text-sm text-gray-500">
                                        Genera un código QR único para que tus clientes accedan a tu menú digital escaneándolo con su móvil.
                                    </p>
                                </div>

                                <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                                    <div class="flex flex-col md:flex-row gap-8 items-center">
                                        <!-- QR Display -->
                                        <div class="flex-shrink-0 text-center">
                                            <?php 
                                            // URL simulada del menú público
                                            $menuUrl = "https://iuconnect.net/menu/" . $academy_id; // Ejemplo
                                            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($menuUrl);
                                            ?>
                                            <div class="bg-white p-4 rounded-xl border-2 border-gray-900 shadow-lg inline-block">
                                                <img src="<?php echo $qrUrl; ?>" alt="Código QR Menú" class="w-48 h-48">
                                            </div>
                                            <p class="mt-2 text-xs text-gray-500 font-mono">Escanea para probar</p>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex-1 space-y-4">
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900">Tu Menú es Público</h3>
                                                <p class="text-sm text-gray-600">Este código QR dirige a tu carta digital actualizada.</p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <code class="bg-gray-100 px-2 py-1 rounded text-xs text-gray-700 select-all"><?php echo $menuUrl; ?></code>
                                                    <button onclick="navigator.clipboard.writeText('<?php echo $menuUrl; ?>')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Copiar</button>
                                                </div>
                                            </div>

                                            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                                                <button onclick="window.open('<?php echo $qrUrl; ?>', '_blank')" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    <i class="fas fa-download mr-2"></i> Descargar QR
                                                </button>
                                                <button type="button" class="inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-black hover:bg-gray-800">
                                                    <i class="fas fa-edit mr-2"></i> Editar Carta
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI Menu Importer -->
                                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-100 rounded-lg p-6 relative overflow-hidden">
                                     <div class="absolute top-0 right-0 -mt-2 -mr-2 bg-purple-600 text-white text-xs font-bold px-2 py-1 rounded-bl-lg">
                                        NUEVO
                                    </div>
                                    <div class="flex flex-col md:flex-row items-center gap-6">
                                        <div class="flex-shrink-0 bg-white p-4 rounded-full shadow-lg">
                                            <i class="fas fa-camera text-3xl text-purple-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-bold text-gray-900 mb-1">¿Tienes tu carta en papel o PDF?</h3>
                                            <p class="text-gray-600 text-sm mb-4">
                                                Nuestra IA puede leer una foto de tu menú y crear la carta digital automáticamente en segundos.
                                            </p>
                                            
                                            <div class="flex gap-3">
                                                <button type="button" onclick="alert('Esta función abrirá la cámara o el selector de archivos para subir una foto de la carta. La IA procesará el texto y estructurará platos y precios automáticamente.')" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-md transition transform hover:-translate-y-0.5">
                                                    <i class="fas fa-magic mr-2"></i> Escanear Carta con IA
                                                </button>
                                                <button type="button" class="text-purple-600 hover:text-purple-800 text-sm font-medium underline">
                                                    Ver cómo funciona
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                             </div>

                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-tools text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900">En desarrollo</h3>
                                <p class="text-gray-500 mt-2">Estamos trabajando en las opciones de configuración para <strong><?php echo htmlspecialchars($activeTab); ?></strong>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- User Profile View (Non-Admin) -->
            <div class="bg-white shadow rounded-lg p-6 max-w-3xl mx-auto">
                <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-4 mb-6">Mi Perfil</h3>
                <form class="space-y-6" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-gray-700">Nombre de Usuario</label>
                            <input type="text" value="<?php echo htmlspecialchars($username); ?>" disabled class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md bg-gray-50 p-2 border">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-gray-700">Rol</label>
                            <input type="text" value="<?php echo htmlspecialchars($userRole); ?>" disabled class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md bg-gray-50 p-2 border">
                        </div>
                         <div class="sm:col-span-6">
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border">
                        </div>
                    </div>
                     <div class="pt-4 flex justify-end">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Actualizar Perfil
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

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

</body>
</html>
