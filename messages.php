<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';

$currentUser = requireActiveUser($pdo);
$academyName = $currentUser['academy_name'] ?? ($currentUser['academy'] ?? '');
$academyNameLower = strtolower((string) $academyName);
$isSalon = $academyNameLower !== '' && (
    strpos($academyNameLower, 'peluquer') !== false
    || strpos($academyNameLower, 'barber') !== false
    || strpos($academyNameLower, 'salon') !== false
    || strpos($academyNameLower, 'estetica') !== false
);
if (!$isSalon) {
    require_feature($pdo, 'core.notifications');
}
$userId = (int) $currentUser['id'];
$username = $currentUser['username'] ?? 'Usuario';
$userRole = $currentUser['role'] ?? 'usuario';

// Resolve Tenant Context
require_once __DIR__ . '/includes/tenant_context.php';
$tenantContext = new TenantContext($pdo);
try {
    $context = $tenantContext->resolveTenantContext();
    $academy_id = $context['academy_id'];
} catch (Exception $e) {
    // Fallback if context fails
    $academy_id = $currentUser['academy_id'];
}
$template = $tenantContext->getTenantTemplate($academy_id);
$templateCode = strtoupper($template['code'] ?? 'CORE_ONLY');
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);

if (empty($_SESSION['messages_csrf'])) {
    $_SESSION['messages_csrf'] = bin2hex(random_bytes(32));
}
$messagesCsrf = $_SESSION['messages_csrf'];
$initialThreadId = isset($_GET['thread']) ? max(0, (int) $_GET['thread']) : 0;

function messages_is_teacher_role(?string $role): bool
{
    $normalized = strtolower(trim((string) $role));
    return in_array($normalized, ['teacher', 'profesor', 'docente', 'teacher_admin'], true);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$isTeacher = messages_is_teacher_role($userRole);
$normalizedRole = strtolower((string) $userRole);
$isAdmin = in_array($normalizedRole, ['admin', 'administrator', 'administrador'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes privados | Learnnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/layout-core.css">
    <style>
        :root {
            --bg: #ffffff;
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --surface: #ffffff;
            --surface-muted: #f9fbff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #111827;
            --accent: #2563eb;
            --accent-strong: #4f46e5;
            --accent-soft: #dbeafe;
            --success: #10b981;
            --danger: #dc2626;
            --card-shadow: 0 10px 24px rgba(15,23,42,0.08);
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            overflow: hidden;
        }

        a { text-decoration: none; color: inherit; }
        * { box-sizing: border-box; }

        /* Top Bar */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        
        .top-bar h1 { margin: 6px 0 8px; font-size: 2rem; color: var(--text); }
        .breadcrumb { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.1em; color: var(--muted); }
        .subtitle { color: var(--muted); margin: 0; }
        
        .top-actions { display: flex; gap: 12px; }

        /* Buttons - Restored */
        .ghost-btn, .primary-btn {
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        
        .ghost-btn { background: rgba(15,23,42,0.05); color: var(--text); border: 1px solid transparent; }
        .ghost-btn:hover { transform: translateY(-1px); }
        .ghost-btn.small { padding: 8px 10px; border-radius: 8px; }
        .ghost-btn.danger { color: #dc2626; border-color: #fecaca; }
        
        .primary-btn { background: var(--primary); color: #fff; box-shadow: 0 10px 25px rgba(15,23,42,0.2); }
        .primary-btn:hover { transform: translateY(-1px); }
        .primary-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .info-banner {
            background: #ffffff;
            color: var(--text);
            padding: 20px 24px;
            border-radius: 18px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            flex-shrink: 0;
            box-shadow: var(--card-shadow);
        }
        .info-banner p { margin: 8px 0 0; color: var(--muted); }
        .info-banner strong { color: var(--text); font-size: 1rem; }
        .banner-meta { display: flex; gap: 16px; flex-wrap: wrap; color: var(--muted); }
        .banner-meta span { display: inline-flex; align-items: center; gap: 6px; }

        /* Chat Grid - Restored & Improved */
        .chat-grid {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr) 280px;
            gap: 20px;
            flex: 1;
            min-height: 0; /* Important for grid overflow */
        }
        
        .chat-column {
            background: #fff;
            border-radius: 22px;
            border: 1px solid #e4e9ff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 16px 36px rgba(15,23,42,0.06);
            transition: box-shadow 0.3s ease, transform 0.2s ease;
        }
        
        .chat-column:hover {
            box-shadow: 0 24px 48px rgba(15,23,42,0.08);
            transform: translateY(-2px);
        }
        .chat-column-list { background: #ffffff; }
        .chat-column-details { background: #ffffff; }
        
        /* Adjusted Headers for chat columns */
        .panel-header {
            padding: 24px 26px;
            border-bottom: 1px solid #e4e9ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .panel-header h2 { margin: 0 0 4px; font-size: 1.3rem; }
        .panel-header p { margin: 0; color: var(--muted); font-size: 0.9rem; }

        /* Thread List Styles */
        .search-bar {
            margin: 16px 24px;
            background: rgba(255,255,255,0.9);
            border: 1.5px solid #dfe5ff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            flex-shrink: 0;
            transition: all 0.2s ease;
            box-shadow: inset 0 1px 2px rgba(15,23,42,0.04);
        }
        
        .search-bar:focus-within {
            border-color: var(--accent-strong);
            background: #fff;
            box-shadow: 0 6px 16px rgba(79,70,229,0.15);
        }
        
        .search-bar input { 
            border: none; 
            background: transparent; 
            flex: 1; 
            font-size: 0.95rem; 
            outline: none;
            color: var(--text);
        }
        
        .search-bar input::placeholder {
            color: var(--muted);
        }
        
        .search-bar i {
            color: var(--muted);
            font-size: 0.9rem;
        }
        
        .chip-group { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 24px 16px; flex-shrink: 0; }
        .chip { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 8px 16px; 
            border-radius: 999px; 
            font-size: 0.83rem; 
            font-weight: 600;
            background: #eef1ff; 
            color: #5b6b86; 
            cursor: pointer; 
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .chip:hover {
            background: #e0e7ff;
            transform: translateY(-1px);
        }
        .chip.active { 
            background: #dee3ff; 
            border-color: #c7d2fe;
            color: var(--accent-strong);
            box-shadow: 0 4px 12px rgba(79,70,229,0.25);
        }
        
        .thread-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 8px 16px 18px;
        }
        
        .thread-item {
            border: 1px solid #e6ebff;
            padding: 16px 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 16px;
            margin: 0 6px 12px;
            background: rgba(255,255,255,0.95);
            box-shadow: 0 12px 28px rgba(15,23,42,0.05);
        }
        .thread-item:hover { 
            background: #fff;
            border-color: #c7d2fe;
            transform: translateX(4px);
        }
        .thread-item.active { 
            background: linear-gradient(135deg, #eef1ff, #dee3ff); 
            border-color: #a5b4fc;
            box-shadow: 0 16px 32px rgba(79,70,229,0.2);
        }
        .thread-name { font-weight: 600; font-size: 1rem; color: var(--text); }
        .thread-meta { font-size: 0.8rem; color: var(--muted); margin-top: 4px; text-transform: capitalize; }
        .thread-preview { font-size: 0.9rem; color: var(--muted); margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Message Area */
        .messages-wrapper {
            flex: 1;
            padding: 28px 36px;
            overflow-y: auto;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        
        .message-row { display: flex; gap: 12px; align-items: flex-end; }
        .message-row.own { flex-direction: row-reverse; }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: #eef1ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 600;
            color: #4f46e5;
            flex-shrink: 0;
            margin-bottom: 4px;
        }

        /* Polished Header & Tabs */
        .conversation-header {
            min-height: 84px;
            padding: 20px 32px 12px;
            background: #fff;
            border-bottom: 1px solid #e4e9ff;
            display: flex;
            flex-direction: column;
            gap: 12px; 
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-tabs {
            display: flex;
            gap: 24px;
            font-size: 0.95rem;
        }

        .header-tab {
            padding-bottom: 10px;
            cursor: pointer;
            color: #94a3b8;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            transition: color 0.2s, border-color 0.2s;
        }
        
        .header-tab.active {
            color: var(--accent-strong);
            border-bottom-color: #c7d2fe;
        }
        
        .header-tab:hover:not(.active) {
            color: var(--text);
        }

        /* Header Action Buttons */
        .header-action-btn {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #475569;
            background: #f8f9ff;
            border: 1px solid #dfe5ff;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.92rem;
        }

        .header-action-btn:hover:not(:disabled) {
            background: #eef1ff;
            border-color: #c7d2fe;
            color: var(--accent-strong);
        }
        
        .header-action-btn:active:not(:disabled) {
            transform: scale(0.95);
        }
        
        .header-action-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #f8fafc;
        }

        .header-action-btn.danger {
            color: #ef4444;
            border-color: #fecaca;
            background: #fff5f5;
        }
        .header-action-btn.danger:hover:not(:disabled) {
            background: #ffe4e6;
            border-color: #fda4af;
            color: #dc2626;
        }

        .header-separator {
            width: 1px;
            height: 28px;
            background: #e4e9ff;
            margin: 0 8px;
        }

        /* Polished Messages */
        .messages-wrapper {
            background: #fff; /* Ensure white bg */
            padding: 24px 32px; /* Widescreen comfort */
        }

        .message-bubble {
            padding: 12px 18px;
            border-radius: 18px;
            background: #f7f8ff; 
            box-shadow: 0 8px 18px rgba(15,23,42,0.08);
            border: 1px solid #dfe5ff;
            position: relative;
            line-height: 1.5;
            max-width: 70%;
        }
        
        .message-row.own .message-bubble {
            background: #eef1ff;
            color: var(--text);
            border-color: #c7d2fe;
        }

        .message-avatar {
            width: 38px; height: 38px;
            font-size: 0.85rem;
            border-radius: 10px; /* Squircle avatar */
            background-color: #f1f5f9; /* Fallback */
            margin-top: 2px;
        }
        
        /* Message Sender Name tweaks */
        .message-author { font-size: 0.78rem; font-weight: 600; color: #64748b; margin-bottom: 2px; }
        .message-row.own .message-author { color: #475569; }
        .message-time { font-size: 0.7rem; color: #94a3b8; font-weight: 500; margin-left: 8px; display: inline-block;}

        /* Composer Polish */
        .message-composer {
            padding: 24px 32px;
            background: linear-gradient(180deg, #f6f7ff 0%, #eef1ff 100%);
            border-top: 1px solid #e4e9ff;
        }

        .composer-container {
            border: 1px solid #d9e1ff;
            border-radius: 18px;
            background: #fff;
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        
        .composer-container:focus-within {
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px rgba(165,180,252,0.35);
        }

        .message-composer textarea {
            width: 100%;
            min-height: 110px;
            border: none;
            resize: none;
            padding: 18px 18px 0;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text);
            background: transparent;
        }
        .message-composer textarea:disabled { opacity: 0.6; }

        .composer-toolbar-row {
            padding: 10px 16px;
            border-top: 1px solid #e4e9ff;
            background: #f8f9ff;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }
        .toolbar-left,
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .format-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid #d9e1ff;
            background: #fff;
            color: #64748b;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .format-icon:hover:not(:disabled) {
            color: var(--accent-strong);
            border-color: #c7d2fe;
            box-shadow: 0 6px 18px rgba(79,70,229,0.2);
        }
        .format-icon:disabled { opacity: 0.4; cursor: not-allowed; }
        .attachment-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef1ff;
            color: var(--accent-strong);
            font-size: 0.85rem;
        }
        .attachment-pill button {
            background: transparent;
            border: none;
            color: inherit;
            cursor: pointer;
        }
        
        /* Send button cleaner look */
        .send-btn-teams {
            color: #fff;
            padding: 10px 14px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #38bdf8, #6366f1);
        }
        .send-btn-teams:hover:not(:disabled) {
            background: linear-gradient(135deg, #22d3ee, #4f46e5);
        }

        /* Right Column Details */
        .participants-list { flex: 1; overflow-y: auto; padding: 24px 26px; }
        .participant-item { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            margin-bottom: 14px;
            padding: 12px;
            border-radius: 16px;
            transition: background 0.2s ease, box-shadow 0.2s ease;
            background: rgba(255,255,255,0.9);
            border: 1px solid transparent;
        }
        .participant-item:hover {
            background: #fff;
            border-color: #c7d2fe;
            box-shadow: 0 10px 24px rgba(15,23,42,0.08);
        }
        .participant-avatar { 
            width: 40px; 
            height: 40px; 
            font-size: 0.85rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .participant-name { font-weight: 600; font-size: 0.95rem; }
        .participant-role { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
        .participants-actions {
            padding: 22px 26px;
            border-top: 1px solid #dfe5ff;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .participant-manager {
            border: 1px dashed #c7d2fe;
            border-radius: 14px;
            padding: 14px;
            background: rgba(255,255,255,0.75);
        }
        .participant-results {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 8px;
        }
        .participant-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #edf2ff;
        }
        .participant-result-item:last-child { border-bottom: none; }
        .participant-result-item button {
            border-radius: 10px;
            padding: 6px 12px;
        }
        .participant-result-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .participant-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef1ff;
            color: var(--accent-strong);
            font-size: 0.85rem;
        }
        .participant-pill button {
            background: transparent;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        .helper-text { color: var(--muted); font-size: 0.85rem; margin: 0; }

        .info-card {
            margin: 20px 26px 26px;
            padding: 18px 20px;
            border-radius: 16px;
            background: #eef1ff;
            border: 1px solid #dfe5ff;
            color: var(--text);
        }
        .info-card h3 { margin: 0 0 10px; font-size: 1rem; }
        .info-card ul { margin: 0; padding-left: 18px; color: #5b6b86; }
        .info-card li { margin-bottom: 6px; }

        /* Misc */
        .alert { 
            padding: 16px 20px; 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b; 
            border-radius: 12px; 
            margin-bottom: 16px;
            border-left: 4px solid #dc2626;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1);
        }
        .alert.hidden, .hidden { display: none !important; }
        .panel-placeholder { 
            padding: 32px 20px; 
            text-align: center; 
            color: var(--muted);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
            letter-spacing: 0.025em;
        }
        
        /* Modal - Kept same */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; display: flex; align-items: center; justify-content: center; }
        .modal-panel { background: #fff; border-radius: 16px; width: 600px; max-width: 90%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
        /* ... keeping modal internal layout simple ... */
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px; }
        .modal-footer { padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        /* Responsive */
        @media (max-width: 1100px) {
            .chat-grid { grid-template-columns: 280px 1fr 0; gap: 16px; }
            .chat-column-details { display: none; }
            .info-banner { display: none; }
        }
        @media (max-width: 900px) {
            .main {
                margin-left: 0;
                padding: 80px 16px 20px;
            }
            .top-bar {
                padding: 0 0 16px;
            }
        }
        @media (max-width: 768px) {
            .chat-grid { grid-template-columns: 1fr; }
            .chat-column-list { display: flex; width: 100%; }
            .chat-column-list.hidden { display: none; }
            .chat-column-conversation { display: none; width: 100%; }
            .chat-column-conversation.active { display: flex; }
        }

    </style>
    <style>
        /* Comfort & Usability Refinements */
        
        /* Custom Scrollbar */
        .thread-list::-webkit-scrollbar,
        .messages-wrapper::-webkit-scrollbar, 
        .participants-list::-webkit-scrollbar,
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .thread-list::-webkit-scrollbar-track,
        .messages-wrapper::-webkit-scrollbar-track, 
        .participants-list::-webkit-scrollbar-track,
        .modal-body::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .thread-list::-webkit-scrollbar-thumb,
        .messages-wrapper::-webkit-scrollbar-thumb, 
        .participants-list::-webkit-scrollbar-thumb,
        .modal-body::-webkit-scrollbar-thumb {
            background-color: rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .thread-list:hover::-webkit-scrollbar-thumb,
        .messages-wrapper:hover::-webkit-scrollbar-thumb, 
        .participants-list:hover::-webkit-scrollbar-thumb,
        .modal-body:hover::-webkit-scrollbar-thumb {
            background-color: rgba(0,0,0,0.2);
        }

        /* Empty State */
        #conversationEmpty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            opacity: 0.6;
            animation: fadeIn 0.5s ease;
        }
        
        #conversationEmpty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 0.6; transform: translateY(0); } }
        
        /* Pulse animation for call */
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Call participant styling */
        .call-participant {
            padding: 8px 12px;
            margin-top: 8px;
            background: #f8fafc;
            border-radius: 6px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .call-participant i { color: var(--muted); }

    </style>
</head>
<body class="<?php echo $isHospitality ? 'iuc-theme iuc-theme-light' : ''; ?>">
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menu">
    <i class="fas fa-bars"></i>
</button>
<div class="layout">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <main class="main">
        <div class="top-bar">
            <div>
                <p class="breadcrumb">Dashboard / Mensajes</p>
                <h1>Mensajes privados</h1>
                <p class="subtitle">
                    Coordina chats entre alumnos, profesores y equipos internos.
                </p>
            </div>
            <div class="top-actions">
                <button type="button" class="ghost-btn" id="refreshThreadsBtn">
                    <i class="fas fa-rotate"></i>
                    <span>Actualizar</span>
                </button>
                <button type="button" class="primary-btn" id="openNewChatBtn">
                    <i class="fas fa-plus"></i>
                    <span>Nuevo chat</span>
                </button>
            </div>
        </div>
        <div class="info-banner">
            <div>
                <strong>Centro de mensajeria segmentada</strong>
                <p>
                    Crea grupos mixtos y espacios solo docente. Todos los mensajes quedan ligados a tu academia.
                </p>
            </div>
            <div class="banner-meta">
                <span><i class="fas fa-building"></i><?php echo $academyName ? e($academyName) : 'Academia global'; ?></span>
                <span><i class="fas fa-user-shield"></i><?php echo e(ucfirst($userRole)); ?></span>
            </div>
        </div>
        <div id="messagingAlert" class="alert hidden"></div>
        <div class="chat-grid">
            <section class="chat-column chat-column-list">
                <div class="panel-header">
                    <div>
                        <h2>Conversaciones</h2>
                        <p>Chats fijados, grupos y mensajes directos.</p>
                    </div>
                    <div class="chip <?php echo $isTeacher ? 'chip-success' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <?php echo $isTeacher ? 'Rol docente' : 'Rol ' . e($userRole); ?>
                    </div>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="search" id="threadSearchInput" placeholder="Busca por nombre o participante">
                </div>
                <div class="chip-group compact" id="threadFilterGroup">
                    <button type="button" class="chip active" data-thread-filter="all">
                        <i class="fas fa-layer-group"></i>
                        Todos
                    </button>
                    <button type="button" class="chip" data-thread-filter="direct">
                        <i class="fas fa-user"></i>
                        Directos
                    </button>
                    <button type="button" class="chip" data-thread-filter="group">
                        <i class="fas fa-users"></i>
                        Grupos
                    </button>
                </div>
                <div id="threadsLoading" class="panel-placeholder hidden">
                    <i class="fas fa-spinner fa-spin"></i>
                    Cargando conversaciones...
                </div>
                <div id="threadsEmptyState" class="panel-placeholder hidden">
                    <p>No tienes chats activos todavia.</p>
                    <button type="button" class="ghost-btn" id="newChatEmptyBtn">
                        <i class="fas fa-plus"></i>Crear primer chat
                    </button>
                </div>
                <div id="threadsList" class="thread-list"></div>
            </section>
            <section class="chat-column chat-column-conversation">
                <div class="conversation-header">
                    <div class="header-top">
                        <div style="display:flex; flex-direction:column; justify-content:center; gap:2px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <h2 id="threadTitle" style="margin:0; font-size:1.1rem;">Selecciona una conversacion</h2>
                                <span class="badge" id="segmentBadge" style="display:none;">Segmento</span>
                            </div>
                            <p id="threadMeta" style="margin:0; font-size:0.85rem; color:var(--muted);">Elige un chat para ver el historial.</p>
                        </div>
                        <div class="header-actions" style="display:flex; gap:8px; align-items: center;">
                            <button type="button" class="header-action-btn" id="startCallBtn" title="Iniciar llamada">
                                <i class="fas fa-phone"></i>
                            </button>
                            
                            <div class="header-separator"></div>
                            
                            <button type="button" class="header-action-btn" id="manageParticipantsBtn" title="Anadir personas" disabled>
                                <i class="fas fa-user-plus"></i>
                            </button>
                            <button type="button" class="header-action-btn danger" id="deleteThreadBtn" title="Eliminar chat" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                            <button type="button" class="header-action-btn" id="refreshThreadBtn" title="Actualizar chat">
                                <i class="fas fa-sync"></i>
                            </button>
                        </div>
                    </div>
                    <div class="header-tabs">
                        <div class="header-tab active">Chat</div>
                        <div class="header-tab">Archivos</div>
                        <div class="header-tab"><i class="fas fa-plus"></i></div>
                    </div>
                </div>
                
                <div class="messages-wrapper" id="messageList">
                    <div class="panel-placeholder" id="conversationEmpty">
                        <i class="fas fa-comments"></i>
                        <p>Selecciona un chat o crea una nueva conversacion.</p>
                    </div>
                </div>

                <form id="messageComposer" class="message-composer">
                    <div class="composer-container">
                        <textarea id="messageInput" placeholder="Escribe un mensaje" disabled></textarea>
                        
                        <div class="attachment-pill hidden" id="attachmentPreview" style="margin: 0 12px 0;">
                            <i class="fas fa-image"></i>
                            <span id="attachmentPreviewName">imagen</span>
                            <button type="button" id="clearAttachmentBtn"><i class="fas fa-times"></i></button>
                        </div>

                        <div class="composer-toolbar-row">
                            <div class="toolbar-left">
                                <button type="button" class="format-icon" title="Formato"><i class="fas fa-font"></i></button>
                                <button type="button" class="format-icon" id="emojiToggleBtn" title="Emoji" disabled><i class="far fa-smile"></i></button>
                                <button type="button" class="format-icon" id="attachmentBtn" title="Adjuntar" disabled><i class="fas fa-paperclip"></i></button>
                                <button type="button" class="format-icon" title="Sticker"><i class="far fa-sticky-note"></i></button>
                            </div>
                            <div class="toolbar-right">
                                <button type="submit" id="messageSendBtn" class="send-btn-teams" title="Enviar" disabled>
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <input type="file" id="messageAttachment" accept="image/*" hidden />
                    <div class="emoji-panel hidden" id="emojiPanel" role="menu" aria-hidden="true"></div>
                </form>
            </section>
            <section class="chat-column chat-column-details">
                <div class="panel-header">
                    <div>
                        <h2>Participantes</h2>
                        <p>Controla quien esta dentro del hilo.</p>
                    </div>
                </div>
                <div id="participantsList" class="participants-list">
                    <div class="panel-placeholder">
                        <p>Sin chat seleccionado.</p>
                    </div>
                </div>
                <div class="participants-actions" id="participantsActions" hidden>
                    <p class="helper-text">Gestiona este grupo igual que en un chat de WhatsApp.</p>
<button type="button" class="ghost-btn" id="addParticipantBtn">
                        <i class="fas fa-user-plus"></i>
                        <span>Anadir personas</span>
                    </button>
                    <div class="participant-manager hidden" id="participantManager">
                        <div class="search-bar compact">
                            <i class="fas fa-search"></i>
                            <input type="search" id="participantManagerInput" placeholder="Buscar por nombre o email">
                        </div>
                        <div class="participant-results" id="participantManagerResults">
                            <div class="panel-placeholder">Escribe un nombre para buscar.</div>
                        </div>
                    </div>
                </div>
                <div class="info-card">
                    <h3>Consejos</h3>
                    <ul>
                        <li>Activa segmento docentes para coordinaciones internas.</li>
                        <li>Escala grupos por asignatura o proyecto.</li>
                        <li>Los historiales se guardan por academia.</li>
                    </ul>
                </div>
            </section>
        </div>
    </main>
</div>
<div class="modal-overlay hidden" id="newChatModal" aria-hidden="true">
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="newChatTitle">
        <div class="modal-header">
            <div>
                <h2 id="newChatTitle">Nuevo chat</h2>
                <p class="subtitle">Define el tipo, segmento y las personas que necesitan hablar.</p>
            </div>
            <button type="button" class="ghost-btn small" id="closeNewChatBtn" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div>
                <label class="form-label">Tipo de chat</label>
                <div class="chip-group" id="chatTypeGroup">
                    <button type="button" class="chip active" data-chat-type="direct">
                        <i class="fas fa-user"></i>
                        1 a 1
                    </button>
                    <button type="button" class="chip" data-chat-type="group">
                        <i class="fas fa-users"></i>
                        Grupo
                    </button>
                </div>
            </div>
            <div class="form-group hidden" id="groupNameWrapper">
                <label class="form-label" for="groupNameInput">Nombre del grupo</label>
                <input type="text" id="groupNameInput" placeholder="Ej. Taller ciencia 3B">
            </div>
            <div>
                <label class="form-label">Segmento</label>
                <div class="chip-group" id="segmentGroup">
                    <button type="button" class="chip active" data-segment="general">
                        <i class="fas fa-people-group"></i>
                        Alumnos y profesores
                    </button>
                    <button type="button" class="chip <?php echo $isTeacher ? '' : 'chip-disabled'; ?>" data-segment="teachers" <?php echo $isTeacher ? '' : 'disabled'; ?>>
                        <i class="fas fa-chalkboard-teacher"></i>
                        Solo profesores
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="participantSearchInput">Participantes</label>
                <div class="search-bar compact">
                    <i class="fas fa-search"></i>
                    <input type="search" id="participantSearchInput" placeholder="Escribe al menos 2 caracteres">
                </div>
                <div id="participantResults" class="participant-results panel-placeholder">
                    <p>Usa el buscador para encontrar alumnos o profesores.</p>
                </div>
                <div class="selected-participants" id="selectedParticipants">
                    <p class="helper-text">Todavia no has agregado participantes.</p>
                </div>
            </div>
            <div id="modalError" class="alert hidden"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="ghost-btn" id="cancelNewChatBtn">Cancelar</button>
            <button type="button" class="primary-btn" id="createChatBtn">
                <i class="fas fa-check"></i>
                <span>Crear chat</span>
            </button>
        </div>
    </div>
</div>
<script>
const MESSAGES_CONFIG = {
    csrfToken: '<?php echo $messagesCsrf; ?>',
    isTeacher: <?php echo $isTeacher ? 'true' : 'false'; ?>,
    initialThreadId: <?php echo $initialThreadId; ?>,
    currentUser: <?php echo json_encode([
        'id' => $userId,
        'name' => $username,
        'role' => $userRole,
        'role_label' => ucfirst($userRole),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script>
(function () {
    const state = {
        threads: [],
        searchTerm: '',
        selectedThreadId: null,
        lastMessageId: 0,
        pollTimer: null,
        creatingChat: false,
        newChat: {
            type: 'direct',
            segment: 'general',
            participants: new Map(),
            groupName: '',
        },
        participantSearchTimer: null,
        participantSearchSeq: 0,
        threadFilter: 'all',
        threadPollTimer: null,
        isLoadingThreads: false,
        activeThread: null,
        isParticipantManagerOpen: false,
        pendingAttachment: null,
        sendingMessage: false,
        isDeletingThread: false,
        isAddingParticipants: false,
    };
    const POLL_INTERVALS = {
        messages: 3000,
        threads: 5000,
    };
    const EMOJI_SET = [
        0x1F600, 0x1F601, 0x1F602, 0x1F923, 0x1F60A, 0x1F60D,
        0x1F618, 0x1F60E, 0x1F929, 0x1F607, 0x1F64C, 0x1F44F,
        0x1F44D, 0x1F64F, 0x1F91D, 0x1F525, 0x2728, 0x1F389,
        0x1F973, 0x2764, 0x1F499, 0x1F49A, 0x1F49B,
        0x1F4AC, 0x1F4F7, 0x1F680, 0x2615,
    ].map((code) => {
        try {
            return String.fromCodePoint(code);
        } catch (_) {
            if (code <= 0xffff) {
                return String.fromCharCode(code);
            }
            const offset = code - 0x10000;
            return String.fromCharCode((offset >> 10) + 0xd800, (offset % 0x400) + 0xdc00);
        }
    }).filter(Boolean);

    const elements = {
        sidebar: document.getElementById('sidebar'),
        menuToggle: document.getElementById('menuToggle'),
        alert: document.getElementById('messagingAlert'),
        threadsList: document.getElementById('threadsList'),
        threadsLoading: document.getElementById('threadsLoading'),
        threadsEmpty: document.getElementById('threadsEmptyState'),
        threadSearch: document.getElementById('threadSearchInput'),
        threadFilterGroup: document.getElementById('threadFilterGroup'),
        threadTitle: document.getElementById('threadTitle'),
        threadMeta: document.getElementById('threadMeta'),
        segmentBadge: document.getElementById('segmentBadge'),
        participantsList: document.getElementById('participantsList'),
        messageList: document.getElementById('messageList'),
        conversationEmpty: document.getElementById('conversationEmpty'),
        messageComposer: document.getElementById('messageComposer'),
        messageInput: document.getElementById('messageInput'),
        messageSendBtn: document.getElementById('messageSendBtn'),
        emojiToggleBtn: document.getElementById('emojiToggleBtn'),
        emojiPanel: document.getElementById('emojiPanel'),
        attachmentInput: document.getElementById('messageAttachment'),
        attachmentBtn: document.getElementById('attachmentBtn'),
        attachmentPreview: document.getElementById('attachmentPreview'),
        attachmentPreviewName: document.getElementById('attachmentPreviewName'),
        clearAttachmentBtn: document.getElementById('clearAttachmentBtn'),
        refreshThreadsBtn: document.getElementById('refreshThreadsBtn'),
        refreshThreadBtn: document.getElementById('refreshThreadBtn'),
        openNewChatBtn: document.getElementById('openNewChatBtn'),
        newChatEmptyBtn: document.getElementById('newChatEmptyBtn'),
        newChatModal: document.getElementById('newChatModal'),
        closeNewChatBtn: document.getElementById('closeNewChatBtn'),
        cancelNewChatBtn: document.getElementById('cancelNewChatBtn'),
        chatTypeGroup: document.getElementById('chatTypeGroup'),
        segmentGroup: document.getElementById('segmentGroup'),
        groupNameWrapper: document.getElementById('groupNameWrapper'),
        groupNameInput: document.getElementById('groupNameInput'),
        participantSearchInput: document.getElementById('participantSearchInput'),
        participantResults: document.getElementById('participantResults'),
        selectedParticipants: document.getElementById('selectedParticipants'),
        modalError: document.getElementById('modalError'),
        createChatBtn: document.getElementById('createChatBtn'),
        deleteThreadBtn: document.getElementById('deleteThreadBtn'),
        participantsActions: document.getElementById('participantsActions'),
        addParticipantBtn: document.getElementById('addParticipantBtn'),
        manageParticipantsBtn: document.getElementById('manageParticipantsBtn'),
        participantManager: document.getElementById('participantManager'),
        participantManagerInput: document.getElementById('participantManagerInput'),
        participantManagerResults: document.getElementById('participantManagerResults'),
        startCallBtn: document.getElementById('startCallBtn'),
    };

    function init() {
        attachEvents();
        updateSendButtonState();
        buildEmojiPanel();
        const shouldSelectFirstThread = !MESSAGES_CONFIG.initialThreadId;
        loadThreads(shouldSelectFirstThread).then(() => {
            if (MESSAGES_CONFIG.initialThreadId) {
                const targetId = Number(MESSAGES_CONFIG.initialThreadId);
                const exists = state.threads.some((thread) => thread.id === targetId);
                if (exists) {
                    setActiveThread(targetId);
                } else if (state.threads.length > 0) {
                    setActiveThread(state.threads[0].id);
                }
            }
            startThreadPolling();
        });
        window.addEventListener('beforeunload', () => {
            stopThreadPolling();
            stopPolling();
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                loadThreads();
                if (state.selectedThreadId) {
                    loadMessages(true);
                }
            }
        });
    }

    function buildEmojiPanel() {
        if (!elements.emojiPanel) return;
        elements.emojiPanel.innerHTML = '';
        EMOJI_SET.forEach((emoji) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = emoji;
            button.addEventListener('click', () => insertEmoji(emoji));
            elements.emojiPanel.appendChild(button);
        });
    }

    function toggleEmojiPanel(forceState) {
        if (elements.emojiToggleBtn.disabled) {
            return;
        }
        const isOpen = !elements.emojiPanel.classList.contains('hidden');
        const shouldOpen = typeof forceState === 'boolean' ? forceState : !isOpen;
        elements.emojiPanel.classList.toggle('hidden', !shouldOpen);
    }

    function closeEmojiPanel() {
        elements.emojiPanel.classList.add('hidden');
    }

    function insertEmoji(emoji) {
        const textarea = elements.messageInput;
        if (textarea.disabled) {
            return;
        }
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        const before = textarea.value.slice(0, start);
        const after = textarea.value.slice(end);
        textarea.value = before + emoji + after;
        const cursor = start + emoji.length;
        textarea.selectionStart = cursor;
        textarea.selectionEnd = cursor;
        textarea.focus();
        closeEmojiPanel();
    }

    function attachEvents() {
        elements.menuToggle.addEventListener('click', () => {
            elements.sidebar.classList.toggle('active');
        });
        elements.refreshThreadsBtn.addEventListener('click', () => loadThreads());
        elements.refreshThreadBtn.addEventListener('click', () => loadMessages(true));
        elements.threadSearch.addEventListener('input', (event) => {
            state.searchTerm = event.target.value.toLowerCase();
            renderThreads();
        });
        if (elements.threadFilterGroup) {
            elements.threadFilterGroup.addEventListener('click', (event) => {
                const button = event.target.closest('[data-thread-filter]');
                if (!button) {
                    return;
                }
                const filter = button.getAttribute('data-thread-filter');
                updateThreadFilter(filter);
            });
        }
        elements.messageComposer.addEventListener('submit', (event) => {
            event.preventDefault();
            sendMessage();
        });
        elements.messageInput.addEventListener('input', () => {
            updateSendButtonState();
        });
        elements.messageInput.addEventListener('focus', () => {
            closeEmojiPanel();
        });
        elements.messageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                sendMessage();
            }
        });
        elements.attachmentBtn.addEventListener('click', () => {
            if (elements.attachmentBtn.disabled) return;
            elements.attachmentInput.click();
        });
        elements.attachmentInput.addEventListener('change', handleAttachmentChange);
        elements.clearAttachmentBtn.addEventListener('click', () => {
            resetAttachmentField();
        });
        elements.emojiToggleBtn.addEventListener('click', toggleEmojiPanel);
        document.addEventListener('click', (event) => {
            if (!elements.emojiPanel.contains(event.target) && event.target !== elements.emojiToggleBtn) {
                closeEmojiPanel();
            }
            if (
                state.isParticipantManagerOpen &&
                elements.participantManager &&
                !elements.participantManager.contains(event.target) &&
                event.target !== elements.addParticipantBtn &&
                event.target !== elements.manageParticipantsBtn
            ) {
                toggleParticipantManager(false);
            }
            if (
                window.innerWidth <= 768 &&
                !elements.sidebar.contains(event.target) &&
                !elements.menuToggle.contains(event.target) &&
                elements.sidebar.classList.contains('active')
            ) {
                elements.sidebar.classList.remove('active');
            }
        });
        if (elements.openNewChatBtn) {
            elements.openNewChatBtn.addEventListener('click', openNewChatModal);
        }
        if (elements.newChatEmptyBtn) {
            elements.newChatEmptyBtn.addEventListener('click', openNewChatModal);
        }
        elements.closeNewChatBtn.addEventListener('click', closeNewChatModal);
        elements.cancelNewChatBtn.addEventListener('click', closeNewChatModal);
        elements.newChatModal.addEventListener('click', (event) => {
            if (event.target === elements.newChatModal) {
                closeNewChatModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !elements.newChatModal.classList.contains('hidden')) {
                closeNewChatModal();
            }
        });
        elements.chatTypeGroup.addEventListener('click', (event) => {
            const button = event.target.closest('[data-chat-type]');
            if (!button) return;
            const type = button.getAttribute('data-chat-type');
            updateChatType(type);
        });
        elements.segmentGroup.addEventListener('click', (event) => {
            const button = event.target.closest('[data-segment]');
            if (!button || button.hasAttribute('disabled')) return;
            const segment = button.getAttribute('data-segment');
            updateSegment(segment);
        });
        elements.participantSearchInput.addEventListener('input', () => {
            const term = elements.participantSearchInput.value.trim();
            if (state.participantSearchTimer) {
                clearTimeout(state.participantSearchTimer);
            }
            state.participantSearchTimer = setTimeout(() => {
                searchParticipants(term);
            }, 300);
        });
        elements.createChatBtn.addEventListener('click', createChat);
        elements.deleteThreadBtn.addEventListener('click', deleteCurrentThread);
        elements.addParticipantBtn.addEventListener('click', () => toggleParticipantManager());
        if (elements.manageParticipantsBtn) {
            elements.manageParticipantsBtn.addEventListener('click', () => toggleParticipantManager());
        }
        elements.participantManagerInput.addEventListener('input', () => {
            const term = elements.participantManagerInput.value.trim();
            if (state.participantSearchTimer) {
                clearTimeout(state.participantSearchTimer);
            }
            state.participantSearchTimer = setTimeout(() => {
                searchAdditionalParticipants(term);
            }, 300);
        });
        
        // Phone call button handler
        if (elements.startCallBtn) {
            elements.startCallBtn.addEventListener('click', () => {
                if (!state.activeThread) {
                    showAlert('Selecciona un chat primero.');
                    return;
                }
                startPhoneCall();
            });
        }
    }

    function loadThreads(selectFirst = false) {
        if (state.isLoadingThreads) {
            return Promise.resolve();
        }
        showAlert(null);
        state.isLoadingThreads = true;
        elements.threadsLoading.classList.remove('hidden');
        return fetchJSON('messages_api.php?action=list_threads')
            .then((data) => {
                state.threads = data.threads || [];
                renderThreads();
                if (state.threads.length === 0) {
                    elements.threadsEmpty.classList.remove('hidden');
                } else {
                    elements.threadsEmpty.classList.add('hidden');
                }
                if (selectFirst && state.threads.length > 0) {
                    setActiveThread(state.threads[0].id);
                } else if (state.selectedThreadId) {
                    const stillExists = state.threads.some((thread) => thread.id === state.selectedThreadId);
                    if (!stillExists) {
                        clearConversation();
                    }
                }
            })
            .catch((error) => {
                showAlert(error.message || 'No se pudo cargar la lista de chats.');
            })
            .finally(() => {
                state.isLoadingThreads = false;
                elements.threadsLoading.classList.add('hidden');
            });
    }

    function renderThreads() {
        elements.threadsList.innerHTML = '';
        const term = state.searchTerm.trim();
        let filtered = state.threads;
        if (term) {
            filtered = state.threads.filter((thread) => {
                const base = (thread.display_name || '').toLowerCase();
                const lastAuthor =
                    thread.last_message && thread.last_message.author
                        ? (thread.last_message.author.name || '').toLowerCase()
                        : '';
                return base.includes(term) || lastAuthor.includes(term);
            });
        }
        if (state.threadFilter !== 'all') {
            filtered = filtered.filter((thread) => thread.type === state.threadFilter);
        }
        if (filtered.length === 0 && state.threads.length > 0) {
            const empty = document.createElement('div');
            empty.className = 'panel-placeholder';
            empty.textContent = 'No se encontraron chats con ese criterio.';
            elements.threadsList.appendChild(empty);
            return;
        }
        filtered.forEach((thread) => {
            const item = document.createElement('div');
            item.className = 'thread-item' + (thread.id === state.selectedThreadId ? ' active' : '');
            item.addEventListener('click', () => setActiveThread(thread.id));

            const topRow = document.createElement('div');
            topRow.className = 'thread-top';

            const name = document.createElement('div');
            name.className = 'thread-name';
            name.textContent = thread.display_name || 'Conversacion';

            const time = document.createElement('div');
            time.className = 'thread-meta';
            time.textContent = formatRelative(thread.last_activity_iso) || 'Sin actividad';

            topRow.appendChild(name);
            topRow.appendChild(time);

            const metaRow = document.createElement('div');
            metaRow.className = 'thread-meta';
            const typeChip = document.createElement('span');
            typeChip.className = 'chip';
            typeChip.textContent = thread.type === 'group' ? 'Grupo' : 'Directo';
            metaRow.appendChild(typeChip);
            if (thread.is_teacher_only) {
                const teacherChip = document.createElement('span');
                teacherChip.className = 'chip chip-success';
                teacherChip.textContent = 'Solo docentes';
                metaRow.appendChild(teacherChip);
            }
            const participantsCount = document.createElement('span');
            const count = thread.participants ? thread.participants.length : 0;
            participantsCount.textContent = `${count} integrantes`;
            metaRow.appendChild(participantsCount);

            const preview = document.createElement('p');
            preview.className = 'thread-preview';
            if (thread.last_message) {
                const author =
                    thread.last_message.author && thread.last_message.author.name
                        ? thread.last_message.author.name
                        : 'Alguien';
                let text = thread.last_message.text || '';
                if (thread.last_message.has_attachment) {
                    text = text ? `${text} [Imagen]` : '[Imagen]';
                }
                preview.innerHTML = `<strong>${escapeHtml(author)}:</strong> ${escapeHtml(formatPreview(text, 80))}`;
            } else {
                preview.textContent = 'Sin mensajes recientes.';
            }

            item.appendChild(topRow);
            item.appendChild(metaRow);
            item.appendChild(preview);
            elements.threadsList.appendChild(item);
        });
    }

    function setActiveThread(threadId) {
        if (!threadId) {
            clearConversation();
            return;
        }
        if (state.selectedThreadId === threadId) {
            loadMessages(true);
            return;
        }
        state.selectedThreadId = threadId;
        state.lastMessageId = 0;
        elements.messageInput.value = '';
        resetAttachmentField();
        elements.threadsList.querySelectorAll('.thread-item').forEach((node) => node.classList.remove('active'));
        loadMessages(true);
        elements.messageInput.disabled = false;
        elements.messageSendBtn.disabled = false;
        elements.emojiToggleBtn.disabled = false;
        elements.attachmentBtn.disabled = false;
        updateSendButtonState();
        if (elements.conversationEmpty) {
            elements.conversationEmpty.classList.add('hidden');
        }
    }

    function clearConversation() {
        stopPolling();
        state.selectedThreadId = null;
        state.lastMessageId = 0;
        elements.messageList.innerHTML = '';
        if (elements.conversationEmpty) {
            elements.conversationEmpty.classList.remove('hidden');
        }
        elements.messageInput.value = '';
        elements.messageInput.disabled = true;
        elements.messageSendBtn.disabled = true;
        elements.emojiToggleBtn.disabled = true;
        elements.attachmentBtn.disabled = true;
        state.pendingAttachment = null;
        state.activeThread = null;
        toggleParticipantManager(false);
        updateThreadActions(null);
        resetAttachmentField();
        updateSendButtonState();
        elements.threadTitle.textContent = 'Selecciona una conversacion';
        elements.threadMeta.textContent = 'Elige un chat para ver el historial.';
        elements.segmentBadge.textContent = 'Segmento general';
        elements.participantsList.innerHTML = '<div class=\"panel-placeholder\"><p>Sin chat seleccionado.</p></div>';
    }

    function loadMessages(reset = true) {
        if (!state.selectedThreadId) {
            return;
        }
        const params = new URLSearchParams({
            action: 'load_messages',
            thread_id: state.selectedThreadId,
        });
        if (!reset && state.lastMessageId) {
            params.append('after_id', String(state.lastMessageId));
        }
        fetchJSON(`messages_api.php?${params.toString()}`)
            .then((data) => {
                const messages = data.messages || [];
                if (reset) {
                    elements.messageList.innerHTML = '';
                    state.lastMessageId = 0;
                }
                appendMessages(messages, reset);
                updateConversationMeta(data.thread);
                if (reset) {
                    scrollMessages(true);
                }
                if (!state.pollTimer) {
                    startPolling();
                }
            })
            .catch((error) => {
                showAlert(error.message || 'No se pudo recuperar el chat.');
            });
    }

        function appendMessages(messages, reset) {
        if (!messages.length) {
            if (reset) {
                const empty = document.createElement('div');
                empty.className = 'panel-placeholder';
                empty.innerHTML = '<p>Todavia no hay mensajes en este chat.</p>';
                elements.messageList.appendChild(empty);
            }
            return;
        }
        messages.forEach((message) => {
            const row = document.createElement('div');
            row.className = 'message-row' + (message.is_mine ? ' own' : '');

            // Avatar
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.textContent = initialsFromName(message.user && message.user.name ? message.user.name : 'U');
            row.appendChild(avatar);

            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';

            if (!message.is_mine) {
                const author = document.createElement('div');
                author.className = 'message-author';
                author.textContent = message.user && message.user.name ? message.user.name : 'Usuario';
                bubble.appendChild(author);
            }

            if (message.text) {
                const body = document.createElement('p');
                body.className = 'message-text';
                body.innerHTML = escapeHtml(message.text).replace(/\n/g, '<br>');
                bubble.appendChild(body);
            }

            if (message.attachment && message.attachment.url) {
                const link = document.createElement('a');
                link.href = message.attachment.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.className = 'message-attachment';
                const img = document.createElement('img');
                img.src = message.attachment.url;
                img.alt = 'Imagen enviada';
                link.appendChild(img);
                bubble.appendChild(link);
            }

            const time = document.createElement('div');
            time.className = 'message-time';
            // Show only time if today, else date + time could be better, but simplified for now
            time.textContent = message.created_at_human || '';
            bubble.appendChild(time);

            row.appendChild(bubble);
            elements.messageList.appendChild(row);
            state.lastMessageId = Math.max(state.lastMessageId, message.id || 0);
        });
        scrollMessages();
    }

    function updateConversationMeta(thread) {
        if (!thread) return;
        state.activeThread = thread;
        elements.threadTitle.textContent = thread.display_name || 'Conversacion';
        const typeLabel = thread.type === 'group' ? 'Grupo' : 'Chat directo';
        const count = Array.isArray(thread.participants) ? thread.participants.length : 0;
        const countLabel = count === 1 ? '1 participante' : `${count} participantes`;
        elements.threadMeta.textContent = `${typeLabel} - ${countLabel}`;
        elements.segmentBadge.textContent = thread.is_teacher_only ? 'Solo profesores' : 'Segmento general';
        updateThreadActions(thread);
        renderParticipants(thread.participants || []);
    }

    function renderParticipants(participants) {
        if (!participants.length) {
            elements.participantsList.innerHTML = '<div class="panel-placeholder"><p>Sin participantes visibles.</p></div>';
            return;
        }
        elements.participantsList.innerHTML = '';
        participants.forEach((participant) => {
            const item = document.createElement('div');
            item.className = 'participant-item';

            const avatar = document.createElement('div');
            avatar.className = 'participant-avatar';
            avatar.textContent = initialsFromName(participant.name || '');

            const details = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'participant-name';
            name.textContent = participant.name || 'Usuario';

            const role = document.createElement('div');
            role.className = 'participant-role';
            const roleLabel = participant.role_label || participant.role || 'Integrante';
            role.textContent = participant.is_admin ? `${roleLabel} - Admin` : roleLabel;

            details.appendChild(name);
            details.appendChild(role);

            item.appendChild(avatar);
            item.appendChild(details);
            elements.participantsList.appendChild(item);
        });
    }

    function scrollMessages(force = false) {
        const nearBottom = elements.messageList.scrollHeight - elements.messageList.clientHeight - elements.messageList.scrollTop < 120;
        if (force || nearBottom) {
            elements.messageList.scrollTop = elements.messageList.scrollHeight;
        }
    }

    function updateSendButtonState() {
        const hasThread = !!state.selectedThreadId;
        const hasContent = elements.messageInput.value.trim().length > 0 || !!state.pendingAttachment;
        elements.messageSendBtn.disabled = !hasThread || !hasContent || state.sendingMessage;
    }

    function sendMessage() {
        if (!state.selectedThreadId) {
            return;
        }
        if (state.sendingMessage) {
            return;
        }
        const rawValue = elements.messageInput.value;
        const text = rawValue.trim();
        const hasAttachment = !!(state.pendingAttachment && state.pendingAttachment.file);
        if (!text && !hasAttachment) {
            showAlert('Escribe un mensaje o adjunta una imagen.');
            return;
        }
        state.sendingMessage = true;
        elements.messageInput.disabled = true;
        elements.messageSendBtn.disabled = true;
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('csrf_token', MESSAGES_CONFIG.csrfToken);
        formData.append('thread_id', state.selectedThreadId);
        formData.append('message', rawValue);
        if (hasAttachment) {
            formData.append('attachment', state.pendingAttachment.file, state.pendingAttachment.file.name || 'imagen');
        }

        fetchJSON('messages_api.php', {
            method: 'POST',
            body: formData,
        })
            .then((data) => {
                if (data.message) {
                    appendMessages([data.message], false);
                }
                elements.messageInput.value = '';
                resetAttachmentField();
                elements.messageInput.focus();
                loadThreads();
            })
            .catch((error) => {
                showAlert(error.message || 'No se pudo enviar el mensaje.');
            })
            .finally(() => {
                state.sendingMessage = false;
                elements.messageInput.disabled = false;
                elements.messageSendBtn.disabled = false;
                updateSendButtonState();
            });
    }

    function handleAttachmentChange(event) {
        const files = event.target.files || [];
        if (!files.length) {
            resetAttachmentField();
            return;
        }
        const file = files[0];
        const sizeLimit = 5 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (file.size > sizeLimit) {
            showAlert('La imagen supera los 5 MB permitidos.');
            resetAttachmentField();
            return;
        }
        if (!allowedTypes.includes(file.type)) {
            showAlert('Solo se permiten imagenes JPG, PNG, GIF o WebP.');
            resetAttachmentField();
            return;
        }
        state.pendingAttachment = {
            file,
            name: file.name,
        };
        const label = file.name.length > 26 ? `${file.name.slice(0, 23)}...` : file.name;
        elements.attachmentPreviewName.textContent = label || 'imagen';
        elements.attachmentPreview.classList.remove('hidden');
        elements.attachmentInput.value = '';
        updateSendButtonState();
    }

    function resetAttachmentField() {
        state.pendingAttachment = null;
        if (elements.attachmentInput) {
            elements.attachmentInput.value = '';
        }
        if (elements.attachmentPreview) {
            elements.attachmentPreview.classList.add('hidden');
        }
        updateSendButtonState();
    }

    function openNewChatModal() {
        resetNewChatState();
        elements.newChatModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        elements.participantSearchInput.focus();
    }

    function closeNewChatModal() {
        elements.newChatModal.classList.add('hidden');
        document.body.style.overflow = '';
        clearModalError();
    }

    function resetNewChatState() {
        state.newChat = {
            type: 'direct',
            segment: 'general',
            participants: new Map(),
            groupName: '',
        };
        elements.groupNameInput.value = '';
        elements.participantSearchInput.value = '';
        elements.participantResults.innerHTML = '<div class=\"panel-placeholder\"><p>Escribe al menos 2 caracteres para buscar.</p></div>';
        renderSelectedParticipants();
        updateChipSelection(elements.chatTypeGroup, '[data-chat-type=\"direct\"]');
        updateChipSelection(elements.segmentGroup, '[data-segment=\"general\"]');
        updateNewChatUI();
        clearModalError();
    }

    function updateChatType(type) {
        if (!['direct', 'group'].includes(type)) {
            return;
        }
        state.newChat.type = type;
        if (type === 'direct' && state.newChat.participants.size > 1) {
            const firstKey = state.newChat.participants.keys().next().value;
            state.newChat.participants = new Map([[firstKey, state.newChat.participants.get(firstKey)]]);
            renderSelectedParticipants();
        }
        updateChipSelection(elements.chatTypeGroup, `[data-chat-type=\"${type}\"]`);
        updateNewChatUI();
    }

    function updateSegment(segment) {
        if (!['general', 'teachers'].includes(segment)) {
            return;
        }
        state.newChat.segment = segment;
        updateChipSelection(elements.segmentGroup, `[data-segment=\"${segment}\"]`);
        searchParticipants(elements.participantSearchInput.value.trim());
    }

    function updateChipSelection(container, selector) {
        container.querySelectorAll('.chip').forEach((chip) => chip.classList.remove('active'));
        const target = container.querySelector(selector);
        if (target) {
            target.classList.add('active');
        }
    }

    function updateNewChatUI() {
        if (state.newChat.type === 'group') {
            elements.groupNameWrapper.classList.remove('hidden');
        } else {
            elements.groupNameWrapper.classList.add('hidden');
        }
        renderSelectedParticipants();
    }

    function updateThreadFilter(filter) {
        if (!filter || (filter !== 'all' && filter !== 'direct' && filter !== 'group')) {
            return;
        }
        state.threadFilter = filter;
        if (elements.threadFilterGroup) {
            elements.threadFilterGroup.querySelectorAll('.chip').forEach((chip) => chip.classList.remove('active'));
            const target = elements.threadFilterGroup.querySelector(`[data-thread-filter="${filter}"]`);
            if (target) {
                target.classList.add('active');
            }
        }
        renderThreads();
    }

    function searchParticipants(term) {
        const trimmed = term.trim();
        if (trimmed.length < 2) {
            elements.participantResults.innerHTML = '<div class=\"panel-placeholder\"><p>Escribe al menos 2 caracteres para buscar.</p></div>';
            return;
        }
        const requestId = ++state.participantSearchSeq;
        const params = new URLSearchParams({
            action: 'search_users',
            q: trimmed,
            teacher_only: state.newChat.segment === 'teachers' ? '1' : '0',
        });
        elements.participantResults.innerHTML = '<div class=\"panel-placeholder\"><i class=\"fas fa-spinner fa-spin\"></i> Buscando personas...</div>';
        fetchJSON(`messages_api.php?${params.toString()}`)
            .then((data) => {
                if (requestId !== state.participantSearchSeq) {
                    return;
                }
                renderParticipantResults(data.users || []);
            })
            .catch((error) => {
                renderParticipantResults([]);
                showModalError(error.message || 'No se pudo buscar.');
            });
    }

    function renderParticipantResults(users) {
        if (!users.length) {
            elements.participantResults.innerHTML = '<div class=\"panel-placeholder\"><p>No se encontraron coincidencias.</p></div>';
            return;
        }
        const fragment = document.createDocumentFragment();
        users.forEach((user) => {
            const item = document.createElement('div');
            item.className = 'participant-result-item';

            const details = document.createElement('div');
            details.className = 'participant-result-details';

            const name = document.createElement('strong');
            name.textContent = user.name || 'Usuario';
            const email = document.createElement('span');
            email.className = 'helper-text';
            email.textContent = user.email || '';

            details.appendChild(name);
            details.appendChild(email);

            const addBtn = document.createElement('button');
            addBtn.className = 'primary-btn';
            addBtn.type = 'button';
            addBtn.style.padding = '6px 12px';
            addBtn.style.fontSize = '0.8rem';
            addBtn.innerHTML = '<i class="fas fa-plus"></i>';
            addBtn.addEventListener('click', () => {
                addParticipant(user);
            });

            item.appendChild(details);
            item.appendChild(addBtn);
            fragment.appendChild(item);
        });
        elements.participantResults.innerHTML = '';
        elements.participantResults.appendChild(fragment);
    }

    function addParticipant(user) {
        if (!user || !user.id) {
            return;
        }
        if (state.newChat.type === 'direct') {
            state.newChat.participants = new Map([[user.id, user]]);
        } else {
            state.newChat.participants.set(user.id, user);
        }
        renderSelectedParticipants();
    }

    function removeParticipant(id) {
        state.newChat.participants.delete(id);
        renderSelectedParticipants();
    }

    function renderSelectedParticipants() {
        const container = elements.selectedParticipants;
        if (!state.newChat.participants.size) {
            container.innerHTML = '<p class=\"helper-text\">Todavia no has agregado participantes.</p>';
            return;
        }
        const fragment = document.createDocumentFragment();
        state.newChat.participants.forEach((participant, id) => {
            const pill = document.createElement('span');
            pill.className = 'participant-pill';
            pill.innerHTML = `${escapeHtml(participant.name || 'Usuario')}`;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerHTML = '<i class=\"fas fa-times\"></i>';
            removeBtn.addEventListener('click', () => removeParticipant(id));
            pill.appendChild(removeBtn);
            fragment.appendChild(pill);
        });
        container.innerHTML = '';
        container.appendChild(fragment);
        if (state.newChat.type === 'direct') {
            const helper = document.createElement('p');
            helper.className = 'helper-text';
            helper.textContent = 'Chat directo: maximo una persona.';
            container.appendChild(helper);
        }
    }

    function createChat() {
        if (state.creatingChat) {
            return;
        }
        const participants = Array.from(state.newChat.participants.keys());
        if (!participants.length) {
            showModalError('Agrega al menos un participante.');
            return;
        }
        if (state.newChat.type === 'group') {
            const name = elements.groupNameInput.value.trim();
            if (name.length < 3) {
                showModalError('El nombre del grupo debe tener al menos 3 caracteres.');
                elements.groupNameInput.focus();
                return;
            }
            state.newChat.groupName = name;
        }
        state.creatingChat = true;
        elements.createChatBtn.disabled = true;
        fetchJSON('messages_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_thread',
                csrf_token: MESSAGES_CONFIG.csrfToken,
                type: state.newChat.type,
                name: state.newChat.type === 'group' ? state.newChat.groupName : '',
                teacher_only: state.newChat.segment === 'teachers' ? 1 : 0,
                participants,
            }),
        })
            .then((data) => {
                closeNewChatModal();
                return loadThreads().then(() => {
                    if (data.thread && data.thread.id) {
                        setActiveThread(data.thread.id);
                    }
                });
            })
            .catch((error) => {
                showModalError(error.message || 'No se pudo crear el chat.');
            })
            .finally(() => {
                state.creatingChat = false;
                elements.createChatBtn.disabled = false;
            });
    }

    function toggleParticipantManager(forceState) {
        if (!elements.participantManager) {
            return;
        }
        const canManage =
            state.activeThread &&
            state.activeThread.type === 'group' &&
            state.activeThread.can_manage;
        if (!canManage) {
            elements.participantManager.classList.add('hidden');
            state.isParticipantManagerOpen = false;
            return;
        }
        const isHidden = elements.participantManager.classList.contains('hidden');
        const shouldOpen = typeof forceState === 'boolean' ? forceState : isHidden;
        elements.participantManager.classList.toggle('hidden', !shouldOpen);
        state.isParticipantManagerOpen = shouldOpen;
        if (shouldOpen) {
            elements.participantManagerInput.value = '';
            elements.participantManagerResults.innerHTML = '<div class=\"panel-placeholder\">Escribe un nombre para buscar.</div>';
            elements.participantManagerInput.focus();
        }
    }

    function searchAdditionalParticipants(term) {
        if (!state.activeThread) {
            return;
        }
        const trimmed = term.trim();
        if (trimmed.length < 2) {
            elements.participantManagerResults.innerHTML = '<div class=\"panel-placeholder\"><p>Escribe al menos 2 caracteres para buscar.</p></div>';
            return;
        }
        const requestId = ++state.participantSearchSeq;
        const params = new URLSearchParams({
            action: 'search_users',
            q: trimmed,
        });
        if (state.activeThread.is_teacher_only) {
            params.append('teacher_only', '1');
        }
        elements.participantManagerResults.innerHTML = '<div class=\"panel-placeholder\"><i class=\"fas fa-spinner fa-spin\"></i> Buscando personas...</div>';
        fetchJSON(`messages_api.php?${params.toString()}`)
            .then((data) => {
                if (requestId !== state.participantSearchSeq) {
                    return;
                }
                renderParticipantManagerResults(data.users || []);
            })
            .catch((error) => {
                elements.participantManagerResults.innerHTML = '<div class=\"panel-placeholder\"><p>No se pudo buscar ahora mismo.</p></div>';
                showAlert(error.message || 'No se pudo buscar.');
            });
    }

    function renderParticipantManagerResults(users) {
        if (!users.length) {
            elements.participantManagerResults.innerHTML = '<div class=\"panel-placeholder\"><p>No se encontraron coincidencias.</p></div>';
            return;
        }
        const currentParticipants = state.activeThread && Array.isArray(state.activeThread.participants)
            ? state.activeThread.participants
            : [];
        const currentIds = new Set(currentParticipants.map((participant) => Number(participant.id)));
        const fragment = document.createDocumentFragment();
        users.forEach((user) => {
            const item = document.createElement('div');
            item.className = 'participant-result-item';

            const details = document.createElement('div');
            details.className = 'participant-result-details';

            const name = document.createElement('strong');
            name.textContent = user.name || 'Usuario';
            const email = document.createElement('span');
            email.className = 'helper-text';
            email.textContent = user.email || '';

            details.appendChild(name);
            details.appendChild(email);

            const actionBtn = document.createElement('button');
            actionBtn.type = 'button';
            const alreadyInside = currentIds.has(Number(user.id));
            actionBtn.textContent = alreadyInside ? 'En el chat' : 'Agregar';
            actionBtn.disabled = alreadyInside || state.isAddingParticipants;
            if (!alreadyInside) {
                actionBtn.addEventListener('click', () => {
                    actionBtn.disabled = true;
                    addUserToActiveThread(user.id);
                });
            }

            item.appendChild(details);
            item.appendChild(actionBtn);
            fragment.appendChild(item);
        });
        elements.participantManagerResults.innerHTML = '';
        elements.participantManagerResults.appendChild(fragment);
    }

    function addUserToActiveThread(userId) {
        if (!state.activeThread || !userId || state.isAddingParticipants) {
            return;
        }
        state.isAddingParticipants = true;
        const payload = {
            action: 'add_participants',
            csrf_token: MESSAGES_CONFIG.csrfToken,
            thread_id: state.activeThread.id,
            participants: [userId],
        };
        fetchJSON('messages_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then((data) => {
                showAlert('Participante agregado al chat.');
                if (data.thread) {
                    updateConversationMeta(data.thread);
                }
                loadThreads();
                const term = elements.participantManagerInput.value.trim();
                if (term.length >= 2) {
                    searchAdditionalParticipants(term);
                }
            })
            .catch((error) => {
                showAlert(error.message || 'No se pudo agregar a la persona.');
            })
            .finally(() => {
                state.isAddingParticipants = false;
            });
    }

    function deleteCurrentThread() {
        if (!state.activeThread || !state.activeThread.can_manage || state.isDeletingThread) {
            return;
        }
        const confirmDelete = window.confirm('Estas seguro de eliminar este chat y su historial?');
        if (!confirmDelete) {
            return;
        }
        state.isDeletingThread = true;
        elements.deleteThreadBtn.disabled = true;
        fetchJSON('messages_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_thread',
                csrf_token: MESSAGES_CONFIG.csrfToken,
                thread_id: state.activeThread.id,
            }),
        })
            .then(() => {
                showAlert('El chat se elimino correctamente.');
                clearConversation();
                loadThreads(true);
            })
            .catch((error) => {
                showAlert(error.message || 'No se pudo eliminar el chat.');
            })
            .finally(() => {
                state.isDeletingThread = false;
                updateThreadActions(state.activeThread);
            });
    }

    function updateThreadActions(thread) {
        const canManage = !!(thread && thread.can_manage);
        const canAdd = !!(thread && thread.type === 'group' && thread.can_manage);
        if (elements.deleteThreadBtn) {
            elements.deleteThreadBtn.disabled = !canManage;
        }
        if (elements.participantsActions) {
            elements.participantsActions.hidden = !canAdd;
        }
        [elements.addParticipantBtn, elements.manageParticipantsBtn].forEach((button) => {
            if (!button) return;
            button.disabled = !canAdd;
        });
        if (!canAdd) {
            toggleParticipantManager(false);
        }
        
        // Enable call button if there's an active thread
        if (elements.startCallBtn) {
            elements.startCallBtn.disabled = !thread;
        }
    }

    function startPhoneCall() {
        if (!state.activeThread) {
            showAlert('Selecciona un chat primero para iniciar una llamada.');
            return;
        }
        
        const participants = state.activeThread.participants || [];
        const threadName = state.activeThread.display_name || 'Conversacion';
        
        // Create call modal dynamically
        const existingModal = document.getElementById('callModal');
        if (existingModal) existingModal.remove();
        
        const participantList = participants
            .filter(p => p.id !== MESSAGES_CONFIG.currentUser.id)
            .map(p => `<div class="call-participant"><i class="fas fa-user"></i> ${escapeHtml(p.name || 'Usuario')}</div>`)
            .join('');
        
        const modalHTML = `
            <div class="modal-overlay" id="callModal">
                <div class="modal-panel" style="max-width: 400px;">
                    <div class="modal-header">
                        <div>
                            <h2><i class="fas fa-phone" style="color: #10b981;"></i> Llamada</h2>
                            <p class="subtitle">Llamando a ${escapeHtml(threadName)}</p>
                        </div>
                        <button type="button" class="ghost-btn small" onclick="document.getElementById('callModal').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body" style="text-align: center; padding: 32px;">
                        <div class="call-animation" style="margin-bottom: 24px;">
                            <i class="fas fa-phone-volume" style="font-size: 3rem; color: #10b981; animation: pulse 1.5s infinite;"></i>
                        </div>
                        <p style="font-size: 1.1rem; font-weight: 600; margin-bottom: 8px;">Conectando...</p>
                        <p style="color: var(--muted); font-size: 0.9rem;">Esta funcion estara disponible proximamente.</p>
                        
                        ${participantList ? `<div style="margin-top: 24px; text-align: left;"><strong style="font-size: 0.85rem; color: var(--muted);">Participantes:</strong>${participantList}</div>` : ''}
                    </div>
                    <div class="modal-footer" style="justify-content: center;">
                        <button type="button" class="primary-btn" style="background: #dc2626;" onclick="document.getElementById('callModal').remove()">
                            <i class="fas fa-phone-slash"></i> Finalizar
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function showAlert(message) {
        if (!message) {
            elements.alert.classList.add('hidden');
            elements.alert.textContent = '';
            return;
        }
        elements.alert.textContent = message;
        elements.alert.classList.remove('hidden');
    }

    function showModalError(message) {
        elements.modalError.textContent = message;
        elements.modalError.classList.remove('hidden');
    }

    function clearModalError() {
        elements.modalError.textContent = '';
        elements.modalError.classList.add('hidden');
    }

    function startPolling() {
        stopPolling();
        state.pollTimer = setInterval(() => loadMessages(false), POLL_INTERVALS.messages);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function startThreadPolling() {
        stopThreadPolling();
        loadThreads();
        state.threadPollTimer = setInterval(() => {
            loadThreads();
        }, POLL_INTERVALS.threads);
    }

    function stopThreadPolling() {
        if (state.threadPollTimer) {
            clearInterval(state.threadPollTimer);
            state.threadPollTimer = null;
        }
    }

    async function fetchJSON(url, options = {}) {
        const response = await fetch(url, options);
        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            // ignore
        }
        if (!response.ok || data.success === false) {
            const error = new Error(data.message || 'Ocurrio un error inesperado.');
            error.payload = data;
            throw error;
        }
        return data;
    }

    function formatPreview(text, max = 80) {
        if (!text) {
            return '';
        }
        const trimmed = text.replace(/\s+/g, ' ').trim();
        return trimmed.length > max ? `${trimmed.slice(0, max)}...` : trimmed;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return String(text).replace(/[&<>"']/g, (char) => map[char]);
    }

    function initialsFromName(name) {
        if (!name) return 'U';
        const parts = name.trim().split(/\s+/);
        const first = parts[0] ? parts[0].charAt(0) : '';
        const second = parts[1] ? parts[1].charAt(0) : '';
        return (first + second).toUpperCase() || 'U';
    }

    function formatRelative(dateString) {
        if (!dateString) return '';
        try {
            const now = new Date();
            const target = new Date(dateString);
            const diffMs = target - now;
            const diffMinutes = Math.round(diffMs / 60000);
            const diffHours = Math.round(diffMs / 3600000);
            const diffDays = Math.round(diffMs / 86400000);
            const formatter = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
            if (Math.abs(diffMinutes) < 60) {
                return formatter.format(diffMinutes, 'minute');
            }
            if (Math.abs(diffHours) < 24) {
                return formatter.format(diffHours, 'hour');
            }
            return formatter.format(diffDays, 'day');
        } catch (error) {
            return '';
        }
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && elements.sidebar.classList.contains('active')) {
            elements.sidebar.classList.remove('active');
        }
    });
    init();
})();
</script>
</body>
</html>
