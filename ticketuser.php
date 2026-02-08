<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
 * CONEXIÓN DIRECTA A LA BASE DE DATOS
 * Si tu base se llama "iuconect" o similar, cambia la variable $db.
*/
$host = 'localhost';
$db   = 'iuconect'; // <-- CAMBIA AQUÍ si tu BD tiene otro nombre
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

// Verificar sesión (basta con que haya user_id)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/auth_guard.php';
$currentUser = requireActiveUser($pdo);

require_once __DIR__ . '/includes/tenant_access.php';
require_once __DIR__ . '/includes/tenant_context.php';

require_feature($pdo, 'core.tickets');

$user_id   = (int) $currentUser['id'];

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
$isTenantAdmin = ($academy_id > 0 && $user_id > 0) ? is_tenant_admin($pdo, $user_id, $academy_id) : false;
$ticket_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Si no hay id válido, redirige
if ($ticket_id <= 0) {
    header('Location: tickets.php');
    exit();
}

// Obtener información del ticket (solo si pertenece al usuario)
$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.email 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = :ticket_id AND t.user_id = :user_id
");
$stmt->execute([
    'ticket_id' => $ticket_id,
    'user_id'   => $user_id
]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}

// Obtener mensajes públicos del ticket (respuestas del admin)
$stmt = $pdo->prepare("
    SELECT * 
    FROM ticket_responses 
    WHERE ticket_id = :ticket_id 
      AND is_internal = 0 
    ORDER BY created_at ASC
");
$stmt->execute(['ticket_id' => $ticket_id]);
$messages = $stmt->fetchAll();


// Obtener archivos adjuntos
$attachments = [];
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM ticket_attachments 
        WHERE ticket_id = :ticket_id 
        ORDER BY created_at ASC
    ");
    $stmt->execute(['ticket_id' => $ticket_id]);
    $attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tabla ticket_attachments no existe, continuar sin adjuntos
    $attachments = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= htmlspecialchars($ticket['ticket_id'] ?? $ticket['id']) ?> - Learnnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/layout-core.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1F2937;
            --primary-dark: #111827;
            --secondary: #6B7280;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --light: #F9FAFB;
            --dark: #111827;
            --border: #E5E7EB;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f5f7fa;
            color: #1F2937;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .ticket-id {
            color: var(--primary);
            font-weight: 700;
        }
        
        .btn-secondary {
            background-color: white;
            color: var(--dark);
            border: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.95rem;
        }
        
        .btn-secondary:hover {
            background-color: var(--light);
            box-shadow: var(--shadow);
        }
        
        .ticket-header-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .ticket-subject {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
            line-height: 1.3;
        }
        
        .ticket-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .meta-label {
            font-size: 0.75rem;
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .meta-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .badge {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        
        .badge-nuevo { background-color: #DBEAFE; color: #1E40AF; }
        .badge-en_progreso { background-color: #FEF3C7; color: #92400E; }
        .badge-pendiente { background-color: #FEF3C7; color: #92400E; }
        .badge-espera_cliente { background-color: #E0E7FF; color: #3730A3; }
        .badge-espera_agente { background-color: #FEF3C7; color: #92400E; }
        .badge-resuelto { background-color: #D1FAE5; color: #065F46; }
        .badge-cerrado { background-color: #F3F4F6; color: #374151; }
        
        .priority-badge {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .priority-low { background-color: #D1FAE5; color: #065F46; }
        .priority-normal { background-color: #DBEAFE; color: #1E40AF; }
        .priority-medium { background-color: #FEF3C7; color: #92400E; }
        .priority-high { background-color: #FEE2E2; color: #991B1B; }
        
        .conversation-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .conversation-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .messages-container {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .messages-container::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }
        
        .messages-container::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }
        
        .message {
            display: flex;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-agent {
            flex-direction: row;
        }
        
        .message-client {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .avatar-agent {
            background-color: var(--light);
            color: var(--secondary);
            border: 2px solid var(--border);
        }
        
        .avatar-client {
            background: linear-gradient(135deg, #1F2937 0%, #111827 100%);
            color: white;
        }
        
        .message-content {
            max-width: 70%;
            margin: 0 1rem;
        }
        
        .message-bubble {
            padding: 1rem 1.25rem;
            border-radius: 16px;
            position: relative;
            box-shadow: var(--shadow);
        }
        
        .bubble-agent {
            background-color: white;
            border: 1px solid var(--border);
            border-top-left-radius: 4px;
        }
        
        .bubble-client {
            background: linear-gradient(135deg, #1F2937 0%, #111827 100%);
            color: white;
            border-top-right-radius: 4px;
        }
        
        .message-sender {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .sender-agent {
            color: var(--secondary);
        }
        
        .sender-client {
            color: rgba(255,255,255,0.9);
        }
        
        .message-text {
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--secondary);
            margin-top: 0.5rem;
            text-align: right;
        }
        
        .time-client {
            color: rgba(255,255,255,0.7);
        }
        
        .attachments-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .attachment-item {
            display: inline-flex;
            align-items: center;
            background-color: rgba(255,255,255,0.1);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .attachment-item:hover {
            background-color: rgba(255,255,255,0.15);
        }
        
        .attachment-icon {
            margin-right: 0.5rem;
        }
        
        .attachment-link {
            color: inherit;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .reply-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .reply-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 41, 55, 0.1);
        }
        
        .form-textarea {
            min-height: 140px;
            resize: vertical;
        }
        
        .file-upload {
            position: relative;
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.2s;
            margin-top: 0.5rem;
            background: var(--light);
        }
        
        .file-upload:hover {
            border-color: var(--primary);
            background: white;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload-text {
            color: var(--secondary);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1F2937 0%, #111827 100%);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            background: #9CA3AF;
            cursor: not-allowed;
            transform: none;
        }
        
        #fileName {
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: var(--secondary);
            font-weight: 500;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: var(--light);
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--secondary);
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-dot-active {
            background-color: var(--success);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .ticket-meta {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .message-content {
                max-width: 85%;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .ticket-header-card,
            .conversation-card,
            .reply-card {
                padding: 1.25rem;
            }
        }
        .ticket-layout {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            align-items: start;
        }

        .ticket-main {
            min-width: 0; /* Prevent grid blowout */
        }

        .ticket-sidebar {
            position: sticky;
            top: 2rem;
        }

        .ticket-info-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .info-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .info-group:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            display: block;
            font-size: 0.75rem;
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
        }

        @media (max-width: 992px) {
            .ticket-layout {
                grid-template-columns: 1fr;
            }
            
            .ticket-sidebar {
                position: static;
                /* Natural order is now Sidebar then Main, which is what we want on mobile (Info first) */
            }
        }

        /* Existing styles adjustments */
        .ticket-subject {
            margin-bottom: 0.5rem;
        }
        
        /* Updated header to include subject */
        .header-content {
            margin-bottom: 2rem;
        }

        /* Adjust card padding for cleaner look */
        .conversation-card {
            padding: 0;
            border-radius: 16px;
            overflow: hidden; /* Ensure children don't overflow rounded corners */
        }
        
        .chat-header {
            padding: 1.5rem 2rem;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .messages-wrapper {
            padding: 2rem;
            background: #F9FAFB; /* Slight contrast for message area */
        }

        .reply-section {
            padding: 2rem;
            background: white;
            border-top: 1px solid var(--border);
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
        <div class="container">
        <div class="header">
            <h1 class="page-title">
                Ticket <span class="ticket-id">#<?= htmlspecialchars($ticket['ticket_id'] ?? $ticket['id']) ?></span>
            </h1>
            <a href="tickets.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Tickets
            </a>
        </div>
        
        <div class="header-content">
            <h2 class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></h2>
        </div>

        <div class="ticket-layout">
            <!-- Sidebar: Ticket Info (Left) -->
            <aside class="ticket-sidebar">
                <div class="ticket-info-card">
                    <div class="info-group">
                        <span class="info-label">Estado</span>
                        <?php
                        $status = $ticket['status'] ?? 'nuevo';
                        $statusClass = 'badge-' . str_replace(' ', '_', $status);
                        ?>
                        <span class="badge <?= $statusClass ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?>
                        </span>
                        
                        <div class="status-indicator" id="statusIndicator" style="margin-top: 10px; padding: 5px 10px; font-size: 0.75rem;">
                            <div class="status-dot status-dot-active" style="width: 6px; height: 6px;"></div>
                            <span>Actualizado</span>
                        </div>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Prioridad</span>
                        <?php
                        $priority = $ticket['priority'] ?? 'normal';
                        $priorityClass = 'priority-' . $priority;
                        ?>
                        <span class="priority-badge <?= $priorityClass ?>">
                            <?= htmlspecialchars(ucfirst($priority)) ?>
                        </span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Categoría</span>
                        <div class="info-value">
                            <i class="fas fa-tag" style="color: var(--secondary); margin-right: 5px;"></i>
                            <?= htmlspecialchars($ticket['category']) ?>
                        </div>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Fechas</span>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <div>
                                <i class="far fa-calendar-alt" style="color: var(--secondary); margin-right: 5px; width: 14px;"></i>
                                <span class="info-value" style="font-size: 0.85rem;">
                                    Creado: <?= !empty($ticket['created_at']) ? date('d/m/Y', strtotime($ticket['created_at'])) : '-' ?>
                                </span>
                            </div>
                            <div>
                                <i class="far fa-clock" style="color: var(--secondary); margin-right: 5px; width: 14px;"></i>
                                <span class="info-value" style="font-size: 0.85rem;">
                                    Actualizado: <?= !empty($ticket['updated_at']) ? date('d/m/Y', strtotime($ticket['updated_at'])) : '-' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content: Conversation & Reply (Right) -->
                <div class="conversation-card">
                    <div class="chat-header">
                        <h3 class="conversation-title" style="margin:0">
                            <i class="fas fa-comments"></i> Conversación
                        </h3>
                    </div>
                    
                    <div class="messages-wrapper">
                        <div class="messages-container" id="messagesContainer">
                            <?php foreach ($messages as $message): 
                                $isClient = ($message['user_id'] == $ticket['user_id']);
                                $senderType = $isClient ? 'cliente' : 'agente';
                            ?>
                                <div class="message <?= $senderType === 'cliente' ? 'message-client' : 'message-agent' ?>">
                                    <div class="message-avatar <?= $senderType === 'cliente' ? 'avatar-client' : 'avatar-agent' ?>">
                                        <?= $senderType === 'cliente'
                                            ? strtoupper(substr($ticket['username'], 0, 1))
                                            : 'A' ?>
                                    </div>
                                    
                                    <div class="message-content">
                                        <div class="message-bubble <?= $senderType === 'cliente' ? 'bubble-client' : 'bubble-agent' ?>">
                                            <div class="message-sender <?= $senderType === 'cliente' ? 'sender-client' : 'sender-agent' ?>">
                                                <?= $senderType === 'cliente'
                                                    ? htmlspecialchars($ticket['username'])
                                                    : 'Agente de Soporte' ?>
                                            </div>
                                            
                                            <div class="message-text">
                                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                                            </div>
                                            
                                            <div class="message-time <?= $senderType === 'cliente' ? 'time-client' : '' ?>">
                                                <?= !empty($message['created_at'])
                                                    ? date('d/m/Y H:i', strtotime($message['created_at']))
                                                    : '' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (!empty($attachments)): ?>
                            <div class="attachments-section">
                                <h4 style="font-size: 14px; margin-bottom: 10px; color: var(--secondary);">
                                    <i class="fas fa-paperclip"></i> Archivos adjuntos
                                </h4>
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="attachment-item">
                                        <i class="fas fa-file attachment-icon"></i>
                                        <a href="<?= htmlspecialchars($attachment['path']) ?>" 
                                           target="_blank" 
                                           class="attachment-link">
                                            <?= htmlspecialchars($attachment['filename']) ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($ticket['status'] !== 'cerrado' && $ticket['status'] !== 'resuelto'): ?>
                        <div class="reply-section">
                            <h3 class="reply-title">Agregar Respuesta</h3>
                            <form id="replyForm" enctype="multipart/form-data">
                                <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                                
                                <div class="form-group">
                                    <textarea id="message" 
                                              name="message" 
                                              class="form-control form-textarea" 
                                              placeholder="Escribe tu respuesta aquí..."
                                              required></textarea>
                                </div>
                                
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                                    <div class="form-group" style="margin-bottom:0; flex-grow: 1;">
                                        <!-- Simplified file upload for cleaner look -->
                                        <label for="attachment" style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; color: var(--secondary); font-size: 0.9rem;">
                                            <i class="fas fa-paperclip"></i> Adjuntar archivo
                                            <input type="file" name="attachment" id="attachment" style="display: none;">
                                        </label>
                                        <span id="fileName" style="font-size: 0.85rem; color: var(--dark); margin-left: 0.5rem;"></span>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary" id="submitBtn">
                                        <i class="fas fa-paper-plane"></i> Enviar
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="reply-section" style="background-color: #F3F4F6; text-align: center; padding: 40px; border-top: 1px solid var(--border);">
                            <i class="fas fa-lock" style="font-size: 48px; color: #9CA3AF; margin-bottom: 20px;"></i>
                            <h3 style="color: #6B7280; margin-bottom: 10px;">Ticket Cerrado</h3>
                            <p style="color: #9CA3AF;">Este ticket ha sido cerrado. No se pueden agregar más respuestas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mostrar nombre del archivo seleccionado
        document.getElementById('attachment')?.addEventListener('change', function(e) {
            const fileNameDiv = document.getElementById('fileName');
            if (this.files.length > 0) {
                fileNameDiv.innerHTML = '<i class="fas fa-paperclip"></i> Archivo seleccionado: ' + this.files[0].name;
            } else {
                fileNameDiv.innerHTML = '';
            }
        });
        
        // Enviar respuesta mediante AJAX
        document.getElementById('replyForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form     = e.target;
            const formData = new FormData(form);
            const submitBtn = document.getElementById('submitBtn');
            
            // Validación básica
            const message = formData.get('message');
            if (!message || message.trim().length < 1) {
                alert('Por favor, escribe un mensaje');
                return;
            }
            
            // Deshabilitar botón y cambiar texto
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            try {
                const response = await fetch('ticket_reply_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Limpiar formulario
                    form.reset();
                    const fileNameDiv = document.getElementById('fileName');
                    if (fileNameDiv) fileNameDiv.innerHTML = '';
                    
                    // Agregar mensaje a la conversación
                    addMessageToConversation({
                        sender: 'cliente',
                        message: message,
                        created_at: new Date().toISOString()
                    });
                    
                    // Actualizar estado del ticket si viene del backend
                    if (result.newStatus) {
                        updateTicketStatus(result.newStatus);
                    }
                    
                    alert('Respuesta enviada exitosamente');
                } else {
                    alert('Error: ' + (result.message || 'No se pudo enviar la respuesta'));
                }
            } catch (error) {
                alert('Error de conexión');
                console.error('Error:', error);
            } finally {
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Respuesta';
            }
        });
        
        // Función para agregar mensaje a la conversación
        function addMessageToConversation(messageData) {
            const messagesContainer = document.getElementById('messagesContainer');
            const username = '<?= addslashes($ticket['username']) ?>';
            const now = new Date(messageData.created_at);
            const formattedTime = now.toLocaleDateString('es-ES') + ' ' + now.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute:'2-digit'
            });
            
            const messageHTML = `
                <div class="message message-client" style="animation: fadeIn 0.3s ease;">
                    <div class="message-avatar avatar-client">
                        ${username.charAt(0).toUpperCase()}
                    </div>
                    
                    <div class="message-content">
                        <div class="message-bubble bubble-client">
                            <div class="message-sender sender-client">
                                ${username}
                            </div>
                            
                            <div class="message-text">
                                ${escapeHtml(messageData.message).replace(/\n/g, '<br>')}
                            </div>
                            
                            <div class="message-time time-client">
                                ${formattedTime}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Función para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Función para actualizar estado del ticket
        function updateTicketStatus(newStatus) {
            const statusElement = document.querySelector('.badge');
            if (statusElement && newStatus) {
                // Limpiar clases previas conservando "badge"
                statusElement.className = 'badge';
                
                // Añadir nueva clase basada en el estado
                const statusClass = 'badge-' + newStatus.replace(' ', '_');
                statusElement.classList.add(statusClass);
                
                // Actualizar texto
                statusElement.textContent = newStatus
                    .charAt(0).toUpperCase() + newStatus.slice(1).replace('_', ' ');
            }
        }
        
        // Polling para actualizar mensajes cada 5 segundos
        let lastMessageId = <?= !empty($messages) ? (int) end($messages)['id'] : 0 ?>;
        
        async function checkForNewMessages() {
            try {
                const response = await fetch(
                    `ticket_messages_api.php?ticket_id=<?= (int) $ticket['id'] ?>&last_id=${lastMessageId}`
                );
                const data = await response.json();
                
                if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
                    const messagesContainer = document.getElementById('messagesContainer');
                    
                    data.messages.forEach(message => {
                        const now = new Date(message.created_at);
                        const formattedTime = now.toLocaleDateString('es-ES') + ' ' + now.toLocaleTimeString('es-ES', {
                            hour: '2-digit',
                            minute:'2-digit'
                        });
                        
                        if (message.sender === 'agente') {
                            const messageHTML = `
                                <div class="message message-agent" style="animation: fadeIn 0.3s ease;">
                                    <div class="message-avatar avatar-agent">
                                        A
                                    </div>
                                    
                                    <div class="message-content">
                                        <div class="message-bubble bubble-agent">
                                            <div class="message-sender sender-agent">
                                                Agente de Soporte
                                            </div>
                                            
                                            <div class="message-text">
                                                ${escapeHtml(message.message).replace(/\n/g, '<br>')}
                                            </div>
                                            
                                            <div class="message-time">
                                                ${formattedTime}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
                        } else if (message.sender === 'cliente') {
                            // Opcional: si quisieras mostrar mensajes del cliente llegados desde otro lado
                            addMessageToConversation(message);
                        }
                        
                        // Actualizar último ID
                        lastMessageId = Math.max(lastMessageId, Number(message.id));
                    });
                    
                    // Actualizar estado si cambió
                    if (data.ticket_status) {
                        updateTicketStatus(data.ticket_status);
                    }
                    
                    // Desplazar hacia abajo
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Error al verificar nuevos mensajes:', error);
            }
        }
        
        // Iniciar polling
        setInterval(checkForNewMessages, 5000);
        checkForNewMessages();
    </script>

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
    </main>
</div>
</body>
</html>

