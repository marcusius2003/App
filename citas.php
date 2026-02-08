<?php
// Citas module (standalone + embeddable)
$__iucCitasEmbed = defined('IUC_CITAS_EMBED') ? (bool) constant('IUC_CITAS_EMBED') : false;
$__iucCitasOptions = $iucCitasOptions ?? [];

if (($_GET['action'] ?? '') === 'send_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
  if ($sessionUserId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
  }

  $payload = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
  $to = trim((string) ($payload['to'] ?? ''));
  $subject = trim((string) ($payload['subject'] ?? ''));
  $message = trim((string) ($payload['message'] ?? ''));
  $overrides = is_array($payload['overrides'] ?? null) ? $payload['overrides'] : [];
  if ($to === '' || $subject === '' || $message === '') {
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
  }

  $envPath = __DIR__ . '/.env';
  require_once __DIR__ . '/billing_module/src/Utils/Env.php';
  Env::load($envPath);

  $isAdminUser = false;
  try {
    require_once __DIR__ . '/admin/config/db.php';
    $database = new Database();
    $pdo = $database->getConnection();
    if ($pdo) {
      $stmt = $pdo->prepare("SELECT role, tenant_role, platform_role FROM users WHERE id = ? LIMIT 1");
      $stmt->execute([$sessionUserId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
      $role = strtolower((string) ($row['role'] ?? ''));
      $tenantRole = strtolower((string) ($row['tenant_role'] ?? ''));
      $platformRole = strtolower((string) ($row['platform_role'] ?? ''));
      $isAdminUser = in_array($role, ['admin', 'administrator', 'administrador', 'owner'], true)
        || in_array($tenantRole, ['tenant_owner', 'tenant_admin'], true)
        || in_array($platformRole, ['platform_owner', 'platform_admin'], true);
    }
  } catch (Exception $e) {
    $isAdminUser = false;
  }

  $smtpHost = Env::get('SMTP_HOST', '');
  $smtpPort = (int) (Env::get('SMTP_PORT', '587') ?: 587);
  $smtpUser = Env::get('SMTP_USER', '');
  $smtpPass = Env::get('SMTP_PASS', '');
  $fromEmail = Env::get('SMTP_FROM_EMAIL', $smtpUser ?: '');
  $fromName = Env::get('SMTP_FROM_NAME', 'IUC');

  if ($isAdminUser && $overrides) {
    $smtpHost = trim((string) ($overrides['smtpHost'] ?? '')) ?: $smtpHost;
    $smtpPort = (int) (trim((string) ($overrides['smtpPort'] ?? '')) ?: $smtpPort);
    $smtpUser = trim((string) ($overrides['smtpUser'] ?? '')) ?: $smtpUser;
    $fromEmail = trim((string) ($overrides['fromEmail'] ?? '')) ?: $fromEmail;
    $fromName = trim((string) ($overrides['fromName'] ?? '')) ?: $fromName;
  }

  if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
    echo json_encode(['ok' => false, 'error' => 'SMTP no configurado']);
    exit;
  }

  $phpmailerPath = __DIR__ . '/vendor/autoload.php';
  if (!file_exists($phpmailerPath)) {
    echo json_encode(['ok' => false, 'error' => 'PHPMailer no disponible']);
    exit;
  }

  require_once $phpmailerPath;
  try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpPort;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = nl2br(htmlspecialchars($message));
    $mail->AltBody = $message;
    $mail->send();
    echo json_encode(['ok' => true]);
  } catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}
?>
<?php if (!$__iucCitasEmbed): ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IUC - Citas</title>
<?php endif; ?>
  <style>
    .iuc-citas {
      --bg: #f7f5f2;
      --bg-2: #f1ede7;
      --ink: #0f1113;
      --ink-2: #1f2328;
      --muted: #6b6460;
      --card: #ffffff;
      --accent: #0f1113;
      --accent-2: #111827;
      --accent-3: #e9e3db;
      --success: #166534;
      --warning: #b45309;
      --danger: #b91c1c;
      --border: #e7e2db;
      --shadow: 0 18px 40px rgba(15, 15, 15, 0.08);
      --radius: 16px;
      --radius-lg: 22px;
      --sidebar: #0b0d12;
      --sidebar-ink: #f8fafc;
      --focus: #111827;
      --font-title: "Playfair Display", "Georgia", serif;
      --font-body: "Suisse Int'l", "Segoe UI", system-ui, -apple-system, sans-serif;
      color: var(--ink);
      font-family: var(--font-body);
      width: 100%;
    }
    .iuc-citas.standalone {
      min-height: 100vh;
      background:
        radial-gradient(1200px 600px at 0% 0%, #fbf2e8 0%, var(--bg) 60%),
        radial-gradient(1000px 600px at 100% 0%, #f2e9df 0%, var(--bg) 50%);
    }
    .iuc-citas.embed {
      --bg: #f8fafc;
      --bg-2: #f1f5f9;
      --border: #e2e8f0;
      --shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
      --accent: #0f172a;
      --accent-2: #111827;
      --accent-3: #f8fafc;
      --radius: 18px;
      --radius-lg: 24px;
      --font-title: inherit;
      --font-body: inherit;
      background: transparent;
      width: 100%;
    }
    .iuc-citas * { box-sizing: border-box; }

    body.iuc-citas-body { margin: 0; }

    .iuc-citas .iuc-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 250px 1fr;
      gap: 0;
      width: 100%;
    }
    .iuc-citas .iuc-shell.embed {
      grid-template-columns: 1fr;
    }
    .iuc-citas .iuc-shell.embed .iuc-sidebar { display: none; }
    .iuc-citas .iuc-shell.embed .iuc-content { padding: 0; }

    .iuc-citas .iuc-sidebar {
      position: sticky;
      top: 0;
      height: 100vh;
      background: linear-gradient(180deg, #070707 0%, #0b0f1f 55%, #070707 100%);
      border-right: 1px solid rgba(255, 255, 255, 0.06);
      padding: 24px 18px;
      display: flex;
      flex-direction: column;
    }

    .iuc-citas .iuc-logo {
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: var(--font-body);
      letter-spacing: 0.28em;
      font-size: 14px;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 24px;
    }

    .iuc-citas .iuc-sidebar nav {
      display: flex;
      flex-direction: column;
      flex: 1;
    }

    .iuc-citas .nav-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .iuc-citas .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 10px;
      color: #d1d5db;
      font-weight: 600;
      text-decoration: none;
    }
    .iuc-citas .nav-item.active {
      background: rgba(255, 255, 255, 0.12);
      color: #ffffff;
      text-decoration: underline;
      text-underline-offset: 4px;
    }
    .iuc-citas .nav-item:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #ffffff;
    }
    .iuc-citas .nav-icon {
      width: 16px;
      height: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .iuc-citas .nav-icon svg {
      width: 16px;
      height: 16px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .iuc-citas .nav-footer {
      margin-top: auto;
      padding-top: 18px;
    }

    .iuc-citas .iuc-content {
      padding: 32px 38px 60px;
      max-width: 100%;
      margin: 0;
      width: 100%;
    }
    .iuc-citas.standalone .iuc-content {
      max-width: 1200px;
      margin: 0 auto;
    }
    .iuc-citas.embed .iuc-content {
      padding: 8px 0 0;
      max-width: 100%;
      margin: 0;
      width: 100%;
    }

    .iuc-citas .module-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 22px;
      padding: 18px 20px;
      border-radius: var(--radius-lg);
      border: 1px solid var(--border);
      background: linear-gradient(135deg, #ffffff 0%, var(--bg-2) 100%);
      box-shadow: var(--shadow);
    }
    .iuc-citas.embed .module-header {
      box-shadow: none;
      background: #ffffff;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 16px;
    }
    .iuc-citas .module-title {
      display: grid;
      gap: 6px;
    }
    .iuc-citas .module-title h1 {
      margin: 0;
      font-family: var(--font-title);
      font-weight: 600;
      font-size: 32px;
      letter-spacing: -0.02em;
    }
    .iuc-citas.embed .module-title h1 { font-size: 24px; }
    .iuc-citas .module-title p {
      margin: 0;
      color: var(--muted);
    }

    .iuc-citas .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(160px, 1fr));
      gap: 16px;
      margin-bottom: 22px;
      width: 100%;
    }
    .iuc-citas .kpi-card {
      position: relative;
      background: linear-gradient(180deg, #ffffff 0%, #fbfaf7 100%);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 18px;
      box-shadow: var(--shadow);
      display: grid;
      gap: 6px;
      overflow: hidden;
    }
    .iuc-citas .kpi-card::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: var(--accent);
      opacity: 0.12;
    }
    .iuc-citas.embed .kpi-card {
      box-shadow: none;
      background: #ffffff;
    }
    .iuc-citas .kpi-card span {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
    }
    .iuc-citas .kpi-card strong {
      font-size: 24px;
      letter-spacing: -0.02em;
    }

    .iuc-citas .module-grid {
      display: grid;
      grid-template-columns: minmax(320px, 1.15fr) minmax(300px, 1fr);
      gap: 22px;
      align-items: start;
      width: 100%;
    }
    .iuc-citas.embed .module-grid {
      grid-template-columns: minmax(420px, 1.35fr) minmax(360px, 1fr);
    }
    @media (min-width: 1400px) {
      .iuc-citas.embed .module-grid {
        grid-template-columns: minmax(460px, 1.4fr) minmax(380px, 1fr);
      }
    }

    .iuc-citas .card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px;
      box-shadow: var(--shadow);
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .iuc-citas .card:hover {
      transform: translateY(-1px);
      box-shadow: 0 22px 48px rgba(15, 15, 15, 0.12);
    }
    .iuc-citas.embed .card {
      border-radius: 18px;
      box-shadow: none;
    }
    .iuc-citas .card h3 {
      margin: 0 0 14px;
      font-family: var(--font-title);
      font-size: 20px;
      letter-spacing: -0.01em;
    }

    .iuc-citas .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }
    .iuc-citas .form-grid.full { grid-template-columns: 1fr; }

    .iuc-citas label {
      font-size: 12px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--muted);
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
    }
    .iuc-citas input,
    .iuc-citas select,
    .iuc-citas textarea {
      width: 100%;
      padding: 11px 12px;
      border-radius: 12px;
      border: 1px solid var(--border);
      font-family: var(--font-body);
      font-size: 14px;
      background: #fbfaf8;
      color: var(--ink);
      transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .iuc-citas.embed input,
    .iuc-citas.embed select,
    .iuc-citas.embed textarea {
      background: #f8fafc;
    }
    .iuc-citas textarea { min-height: 80px; resize: vertical; }
    .iuc-citas input::placeholder,
    .iuc-citas textarea::placeholder {
      color: #9aa0a6;
    }
    .iuc-citas input:focus,
    .iuc-citas select:focus,
    .iuc-citas textarea:focus {
      outline: none;
      border-color: var(--focus);
      box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15);
      background: #ffffff;
    }

    .iuc-citas .estimate-card {
      background: linear-gradient(135deg, #fffdf9 0%, #f5efe7 100%);
      border: 1px dashed #d9c7b6;
      border-radius: 14px;
      padding: 14px;
      display: grid;
      gap: 8px;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
    }
    .iuc-citas .estimate-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
    .iuc-citas .estimate-row strong {
      font-size: 16px;
      letter-spacing: -0.01em;
    }
    .iuc-citas .chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    .iuc-citas .chip {
      background: #f1ece4;
      color: #5a4a3c;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }

    .iuc-citas .btn-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
    }
    .iuc-citas .btn {
      border: none;
      padding: 10px 16px;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      font-family: var(--font-body);
      transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
      box-shadow: 0 8px 18px rgba(15, 15, 15, 0.12);
    }
    .iuc-citas .btn.primary { background: var(--accent); color: #fff; }
    .iuc-citas.embed .btn.primary { background: #0f172a; }
    .iuc-citas .btn.secondary { background: #efe7dd; color: #3c3127; }
    .iuc-citas.embed .btn.secondary { background: #eef2f7; color: #111827; }
    .iuc-citas .btn.ghost { background: #ffffff; border: 1px solid var(--border); box-shadow: none; }
    .iuc-citas .btn:hover { transform: translateY(-1px); }
    .iuc-citas .btn:active { transform: translateY(0); }

    .iuc-citas .tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 12px;
      padding: 6px;
      border-radius: 999px;
      background: #f4efe8;
      border: 1px solid var(--border);
      width: fit-content;
    }
    .iuc-citas .tab {
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid transparent;
      background: transparent;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      color: var(--muted);
      transition: background 0.15s ease, color 0.15s ease;
    }
    .iuc-citas.embed .tabs { background: #f1f5f9; }
    .iuc-citas .tab.active {
      background: #0f172a;
      color: #fff;
      border-color: #0f172a;
    }

    .iuc-citas .list {
      display: grid;
      gap: 10px;
    }
    .iuc-citas .list-item {
      padding: 14px;
      border: 1px solid var(--border);
      border-radius: 14px;
      display: grid;
      gap: 8px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfaf7 100%);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .iuc-citas .list-item:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    }
    .iuc-citas .list-item header {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      flex-wrap: wrap;
    }
    .iuc-citas .status {
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .iuc-citas .status.new { background: #fef3c7; color: #7c4b00; }
    .iuc-citas .status.converted { background: #d1fae5; color: #065f46; }
    .iuc-citas .status.pending { background: #e0f2fe; color: #075985; }
    .iuc-citas .status.confirmed { background: #dcfce7; color: #166534; }
    .iuc-citas .status.completed { background: #e5e7eb; color: #111827; }
    .iuc-citas .status.cancelled { background: #fee2e2; color: #991b1b; }

    .iuc-citas .list-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 4px;
    }
    .iuc-citas .link-btn {
      background: #f8fafc;
      border: 1px solid var(--border);
      color: var(--accent-2);
      font-weight: 600;
      cursor: pointer;
      padding: 6px 12px;
      border-radius: 999px;
      transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }
    .iuc-citas .link-btn:hover {
      background: #0f172a;
      color: #fff;
      border-color: #0f172a;
    }
    .iuc-citas.embed .link-btn { background: #f1f5f9; }
    .iuc-citas .link-btn.danger {
      color: var(--danger);
      border-color: rgba(185, 28, 28, 0.2);
      background: #fff5f5;
    }
    .iuc-citas .link-btn.danger:hover {
      background: #b91c1c;
      color: #fff;
      border-color: #b91c1c;
    }

    .iuc-citas .filters {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    .iuc-citas .filters select,
    .iuc-citas .filters input { max-width: 200px; }

    .iuc-citas .settings-panel {
      margin-top: 12px;
      border: 1px dashed var(--border);
      padding: 12px;
      border-radius: 14px;
      background: #fbfaf7;
      display: grid;
      gap: 10px;
    }
    .iuc-citas .settings-panel h4 {
      margin: 0;
      font-size: 12px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .iuc-citas .settings-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .iuc-citas .notice {
      font-size: 12px;
      color: var(--muted);
      line-height: 1.5;
    }
    .iuc-citas .module-header .notice {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 6px 10px;
      color: var(--ink-2);
      font-weight: 600;
    }
    .iuc-citas.embed .module-header .notice {
      background: #f1f5f9;
    }

    .iuc-citas .fade-in {
      animation: fadeIn 0.5s ease both;
    }
    .iuc-citas .modal-overlay,
    .modal-overlay.iuc-citas-modal {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2000;
    }
    .iuc-citas .modal-overlay.active,
    .modal-overlay.iuc-citas-modal.active { display: flex; }
    .iuc-citas .modal-card,
    .modal-overlay.iuc-citas-modal .modal-card {
      background: #ffffff;
      border-radius: 18px;
      padding: 18px;
      width: min(420px, 92vw);
      box-shadow: 0 26px 60px rgba(15, 23, 42, 0.22);
      border: 1px solid var(--border);
    }
    .iuc-citas .modal-card h4,
    .modal-overlay.iuc-citas-modal .modal-card h4 {
      margin: 0 0 8px;
      font-size: 16px;
    }
    .iuc-citas .modal-card .notice,
    .modal-overlay.iuc-citas-modal .modal-card .notice { margin-bottom: 10px; }
    .iuc-citas .modal-actions,
    .modal-overlay.iuc-citas-modal .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 12px;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1100px) {
      .iuc-citas .kpi-grid { grid-template-columns: repeat(2, minmax(160px, 1fr)); }
      .iuc-citas .module-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 800px) {
      .iuc-citas .iuc-shell { grid-template-columns: 1fr; }
      .iuc-citas .iuc-sidebar {
        position: relative;
        height: auto;
        display: none;
      }
      .iuc-citas .module-title h1 { font-size: 26px; }
    }
    @media (max-width: 600px) {
      .iuc-citas .kpi-grid { grid-template-columns: 1fr; }
      .iuc-citas .form-grid { grid-template-columns: 1fr; }
      .iuc-citas .settings-grid { grid-template-columns: 1fr; }
      .iuc-citas .filters select,
      .iuc-citas .filters input { max-width: none; width: 100%; }
    }
  </style>
<?php if (!$__iucCitasEmbed): ?>
</head>
<body class="iuc-citas-body">
<?php endif; ?>

  <div id="iuc-citas-root" class="iuc-citas <?php echo $__iucCitasEmbed ? 'embed' : 'standalone'; ?>"></div>

  <script>
    window.IUCModules = window.IUCModules || {};
    window.IUC_CITAS_OPTIONS = <?php echo json_encode($__iucCitasOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    window.IUC_CITAS_BASE = window.IUC_CITAS_BASE || <?php echo json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')); ?>;

    (function() {
      const DEFAULTS = {
        tenantId: "default",
        businessName: "Lumen Studio",
        whatsappNumber: "",
        currency: "EUR",
        slotSize: 30,
        embed: false,
        isAdmin: false,
        smtpHost: "",
        smtpPort: "587",
        smtpUser: "",
        smtpFromEmail: "",
        smtpFromName: ""
      };

      const SERVICE_CONFIG = {
        "Corte": { type: "fixed", timeMin: 30, timeMax: 45, priceMin: 20, priceMax: 28 },
        "Corte+Peinado": { type: "fixed", timeMin: 60, timeMax: 75, priceMin: 38, priceMax: 48 },
        "Color raíces": { type: "color", timeMin: 75, timeMax: 105, priceMin: 45, priceMax: 65 },
        "Balayage/Mechas": { type: "color", timeMin: 150, timeMax: 210, priceMin: 110, priceMax: 160 }
      };

      const FACTORS = {
        largo: {
          corto: { time: 0, price: 0 },
          medio: { time: 15, price: 8 },
          largo: { time: 30, price: 16 },
          extra: { time: 45, price: 28 }
        },
        densidad: {
          baja: { time: -10, price: -4 },
          media: { time: 0, price: 0 },
          alta: { time: 15, price: 10 }
        },
        tintePrevio: {
          si: { time: 15, price: 12 },
          no: { time: 0, price: 0 }
        },
        decoloracion: {
          no: { time: 0, price: 0 },
          "no lo se": { time: 20, price: 15 },
          si: { time: 35, price: 30 }
        }
      };

      const STATE_TEMPLATE = {
        settings: { ...DEFAULTS },
        requests: [],
        appointments: []
      };

      function mount(containerElement, options) {
        const rootFallback = document.querySelector("#iuc-citas-root");
        const container = containerElement || rootFallback || document.querySelector("#app") || document.body;
        if (container && !container.classList.contains("iuc-citas")) {
          container.classList.add("iuc-citas");
        }

        const resolvedOptions = { ...DEFAULTS, ...(options || {}) };
        const tenantId = resolvedOptions.tenantId || "default";
        const storageKey = `iuc:citas:${tenantId}:state`;
        let state = loadState(storageKey, resolvedOptions);

        container.innerHTML = buildShellHtml(state.settings, resolvedOptions.embed, resolvedOptions.isAdmin);
        const refs = mapRefs(container);

        hydrateState();
        bindEvents();
        renderAll();

        function hydrateState() {
          state.settings = { ...state.settings, ...resolvedOptions, tenantId };
        }

        let currentTab = "requests";
        let pendingRequestId = null;

        function bindEvents() {
          refs.form.addEventListener("input", handleEstimateUpdate);
          refs.form.addEventListener("submit", handleCreateRequest);
          refs.whatsappBtn.addEventListener("click", handleWhatsApp);
          refs.copyBtn.addEventListener("click", handleCopy);
          refs.demoBtn.addEventListener("click", handleDemo);
          refs.settingsForm.addEventListener("input", handleSettingsUpdate);
          if (refs.emailSettingsForm) {
            refs.emailSettingsForm.addEventListener("input", handleSettingsUpdate);
          }
          refs.tabRequests.addEventListener("click", () => setTab("requests"));
          refs.tabAgenda.addEventListener("click", () => setTab("agenda"));
          refs.filterStatus.addEventListener("change", renderAgendaList);
          refs.searchInput.addEventListener("input", renderAgendaList);
          refs.slotSizeInput.addEventListener("change", handleSettingsUpdate);
          if (refs.appointmentCancel) {
            refs.appointmentCancel.addEventListener("click", closeAppointmentModal);
          }
          if (refs.appointmentModal) {
            refs.appointmentModal.addEventListener("click", (event) => {
              if (event.target === refs.appointmentModal) closeAppointmentModal();
            });
          }
          if (refs.appointmentConfirm) {
            refs.appointmentConfirm.addEventListener("click", confirmAppointmentFromModal);
          }
        }

        function renderAll() {
          renderKpis();
          renderEstimate();
          renderRequestList();
          renderAgendaList();
          renderSettings();
          setTab(currentTab);
        }

        function renderKpis() {
          const now = new Date();
          const start7 = new Date();
          start7.setDate(now.getDate() - 7);
          const requestsNew = state.requests.filter(r => r.status === "Nueva").length;
          const todayKey = formatDateKey(now);
          const todayAppointments = state.appointments.filter(a => a.dateKey === todayKey).length;
          const pendingConfirm = state.appointments.filter(a => a.status === "Pendiente").length;
          const cancelled7 = state.appointments.filter(a => a.status === "Cancelada" && new Date(a.dateTime) >= start7).length;

          refs.kpiNew.textContent = requestsNew;
          refs.kpiToday.textContent = todayAppointments;
          refs.kpiPending.textContent = pendingConfirm;
          refs.kpiCancelled.textContent = cancelled7;
        }

        function handleEstimateUpdate() {
          renderEstimate();
        }

        function currentFormData() {
          const data = {
            service: refs.service.value,
            employee: refs.employee.value,
            largo: refs.largo.value,
            densidad: refs.densidad.value,
            tintePrevio: refs.tintePrevio.value,
            decoloracion: refs.decoloracion.value,
            name: refs.name.value.trim(),
            phone: refs.phone.value.trim(),
            email: refs.email.value.trim(),
            notes: refs.notes.value.trim(),
            preferredDate: refs.preferredDate.value,
            photosCount: parseInt(refs.photosCount.value || "0", 10) || 0,
            includeDetails: refs.includeDetails.checked
          };
          return data;
        }

        function computeEstimate(data) {
          const service = SERVICE_CONFIG[data.service];
          let timeMin = service.timeMin;
          let timeMax = service.timeMax;
          let priceMin = service.priceMin;
          let priceMax = service.priceMax;

          if (service.type === "color") {
            const largo = FACTORS.largo[data.largo];
            const densidad = FACTORS.densidad[data.densidad];
            const tinte = FACTORS.tintePrevio[data.tintePrevio];
            const deco = FACTORS.decoloracion[data.decoloracion];

            const timeDelta = largo.time + densidad.time + tinte.time + deco.time;
            const priceDelta = largo.price + densidad.price + tinte.price + deco.price;

            timeMin += Math.max(0, timeDelta - 10);
            timeMax += Math.max(15, timeDelta + 10);
            priceMin += Math.max(0, priceDelta - 6);
            priceMax += Math.max(10, priceDelta + 8);
          }

          if (timeMin < 20) timeMin = 20;
          if (priceMin < 15) priceMin = 15;

          const uncertainty = calcUncertainty(timeMin, timeMax, priceMin, priceMax);
          return { timeMin, timeMax, priceMin, priceMax, uncertainty };
        }

        function calcUncertainty(timeMin, timeMax, priceMin, priceMax) {
          const timeSpread = timeMax - timeMin;
          const priceSpread = priceMax - priceMin;
          const score = Math.min(10, Math.round((timeSpread / 15) + (priceSpread / 10)));
          return score;
        }

        function renderEstimate() {
          const data = currentFormData();
          const estimate = computeEstimate(data);
          refs.timeEstimate.textContent = `${estimate.timeMin}-${estimate.timeMax} min`;
          refs.priceEstimate.textContent = formatCurrencyRange(estimate.priceMin, estimate.priceMax, state.settings.currency);
          refs.uncertainty.textContent = `${estimate.uncertainty} / 10`;
          refs.uncertaintyBar.value = estimate.uncertainty;
          refs.chipLargo.textContent = `Largo: ${data.largo}`;
          refs.chipDensidad.textContent = `Densidad: ${data.densidad}`;
          refs.chipTinte.textContent = `Tinte previo: ${data.tintePrevio}`;
          refs.chipDeco.textContent = `Decoloración: ${data.decoloracion}`;
          refs.blockedSlot.textContent = calcSlotBlock(estimate.timeMin, state.settings.slotSize);

          const msg = buildWhatsAppMessage(data, estimate, state.settings);
          refs.whatsappPreview.textContent = msg;
        }

        function handleCreateRequest(event) {
          event.preventDefault();
          const data = currentFormData();
          if (!data.name) {
            refs.name.focus();
            return;
          }
          const estimate = computeEstimate(data);
          const item = {
            id: cryptoId(),
            createdAt: new Date().toISOString(),
            status: "Nueva",
            ...data,
            estimate
          };

          state.requests.unshift(item);
          saveState(storageKey, state);

          // POST /api/citas/requests
          resetForm();
          renderAll();
        }

        function handleWhatsApp() {
          const data = currentFormData();
          if (!data.phone) {
            alert("Introduce un teléfono para enviar por WhatsApp.");
            refs.phone.focus();
            return;
          }
          const estimate = computeEstimate(data);
          const msg = buildWhatsAppMessage(data, estimate, state.settings);
          const number = sanitizeNumber(data.phone);
          const encoded = encodeURIComponent(msg);
          const url = `https://api.whatsapp.com/send?phone=${number}&text=${encoded}`;
          window.open(url, "_blank", "noopener");
        }

        function handleCopy() {
          const data = currentFormData();
          const estimate = computeEstimate(data);
          const msg = buildWhatsAppMessage(data, estimate, state.settings);
          navigator.clipboard.writeText(msg).then(() => {
            refs.copyBtn.textContent = "Copiado";
            setTimeout(() => { refs.copyBtn.textContent = "Copiar mensaje"; }, 1200);
          });
        }

        function handleDemo() {
          state.requests = demoRequests();
          state.appointments = demoAppointments();
          state.settings.businessName = "Iris Atelier";
          state.settings.whatsappNumber = "";
          state.settings.currency = "EUR";
          state.settings.slotSize = 30;
          saveState(storageKey, state);
          renderAll();
        }

        function handleSettingsUpdate() {
          state.settings.businessName = refs.businessNameInput.value.trim() || "Negocio";
          state.settings.whatsappNumber = refs.whatsappInput.value.trim() || "";
          state.settings.currency = refs.currencyInput.value.trim() || DEFAULTS.currency;
          state.settings.slotSize = parseInt(refs.slotSizeInput.value || "30", 10) || 30;
          if (refs.smtpHostInput) {
            state.settings.smtpHost = refs.smtpHostInput.value.trim();
          }
          if (refs.smtpPortInput) {
            state.settings.smtpPort = refs.smtpPortInput.value.trim() || "587";
          }
          if (refs.smtpUserInput) {
            state.settings.smtpUser = refs.smtpUserInput.value.trim();
          }
          if (refs.smtpFromInput) {
            state.settings.smtpFromEmail = refs.smtpFromInput.value.trim();
          }
          if (refs.smtpNameInput) {
            state.settings.smtpFromName = refs.smtpNameInput.value.trim();
          }
          saveState(storageKey, state);
          renderEstimate();
        }

        function resetForm() {
          refs.form.reset();
          refs.photosCount.value = "0";
          renderEstimate();
        }

        function renderRequestList() {
          refs.requestsList.innerHTML = "";
          if (!state.requests.length) {
            refs.requestsList.innerHTML = "<p class=\"notice\">Sin solicitudes por ahora.</p>";
            return;
          }
          state.requests.forEach(item => {
            const row = document.createElement("div");
            row.className = "list-item fade-in";
            row.innerHTML = `
              <header>
                <div>
                  <strong>${escapeHtml(item.name)}</strong>
                  <div class="notice">${escapeHtml(item.service)} · ${formatEstimate(item.estimate, state.settings.currency)}</div>
                </div>
                <span class="status ${item.status === "Nueva" ? "new" : "converted"}">${item.status}</span>
              </header>
              <div class="notice">${buildRequestMeta(item)}</div>
              <div class="list-actions">
                <button class="link-btn" data-action="create" data-id="${item.id}">Crear cita</button>
                <button class="link-btn danger" data-action="delete" data-id="${item.id}">Borrar</button>
              </div>
            `;
            row.querySelectorAll("button").forEach(btn => {
              btn.addEventListener("click", handleRequestAction);
            });
            refs.requestsList.appendChild(row);
          });
        }

        function handleRequestAction(event) {
          const action = event.currentTarget.dataset.action;
          const id = event.currentTarget.dataset.id;
          const request = state.requests.find(r => r.id === id);
          if (!request) return;

          if (action === "delete") {
            state.requests = state.requests.filter(r => r.id !== id);
            saveState(storageKey, state);
            renderAll();
            return;
          }

          if (action === "create") {
            openAppointmentModal(request);
          }
        }

        function openAppointmentModal(request) {
          pendingRequestId = request.id;
          if (refs.appointmentModalTitle) {
            refs.appointmentModalTitle.textContent = `Cita para ${request.name} · ${request.service}`;
          }
          if (refs.appointmentDateInput) {
            refs.appointmentDateInput.value = request.preferredDate || "";
          }
          if (refs.appointmentModal) {
            if (refs.appointmentModal.parentElement !== document.body) {
              refs.appointmentModal.classList.add("iuc-citas-modal");
              document.body.appendChild(refs.appointmentModal);
            }
            refs.appointmentModal.classList.add("active");
          } else {
            const fallback = prompt("Introduce fecha y hora (YYYY-MM-DD HH:MM)") || "";
            if (fallback) {
              createAppointmentFromRequest(request, fallback);
            }
          }
        }

        function closeAppointmentModal() {
          if (refs.appointmentModal) {
            refs.appointmentModal.classList.remove("active");
          }
          pendingRequestId = null;
        }

        function confirmAppointmentFromModal() {
          if (!pendingRequestId) return;
          const request = state.requests.find(r => r.id === pendingRequestId);
          if (!request) {
            closeAppointmentModal();
            return;
          }
          const dateTime = refs.appointmentDateInput ? refs.appointmentDateInput.value : "";
          if (!dateTime) {
            alert("Selecciona fecha y hora.");
            return;
          }
          createAppointmentFromRequest(request, dateTime);
          currentTab = "agenda";
          closeAppointmentModal();
          renderAll();
        }

        function createAppointmentFromRequest(request, dateTimeValue) {
          const normalized = dateTimeValue.includes("T") ? dateTimeValue : dateTimeValue.replace(" ", "T");
          const date = new Date(normalized);
          if (isNaN(date.getTime())) {
            alert("Fecha inválida.");
            return;
          }

          const appointment = {
            id: cryptoId(),
            dateTime: date.toISOString(),
            dateKey: formatDateKey(date),
            status: "Confirmada",
            requestId: request.id,
            name: request.name,
            service: request.service,
            estimate: request.estimate,
            employee: request.employee,
            phone: request.phone,
            email: request.email,
            emailSent: false
          };

          state.appointments.unshift(appointment);
          request.status = "Convertida";
          saveState(storageKey, state);

          // POST /api/citas/appointments
          if (appointment.email) {
            sendAppointmentEmail(appointment);
          }
        }

        function renderAgendaList() {
          const filterStatus = refs.filterStatus.value;
          const search = refs.searchInput.value.trim().toLowerCase();
          const items = state.appointments.filter(a => {
            const matchesStatus = filterStatus === "Todos" || a.status === filterStatus;
            const matchesSearch = !search || a.name.toLowerCase().includes(search) || a.service.toLowerCase().includes(search);
            return matchesStatus && matchesSearch;
          });

          refs.agendaList.innerHTML = "";
          if (!items.length) {
            refs.agendaList.innerHTML = "<p class=\"notice\">No hay citas para este filtro.</p>";
            return;
          }

          items.forEach(item => {
            const row = document.createElement("div");
            row.className = "list-item fade-in";
            const dateKey = formatLocalDateKey(item.dateTime);
            row.innerHTML = `
              <header>
                <div>
                  <strong>${escapeHtml(item.name)}</strong>
                  <div class="notice">${escapeHtml(item.service)} · ${formatEstimate(item.estimate, state.settings.currency)}</div>
                </div>
                <span class="status ${statusClass(item.status)}">${item.status}</span>
              </header>
              <div class="notice">${formatDateTime(item.dateTime)} · ${item.employee && item.employee !== "Cualquiera" ? "Con " + escapeHtml(item.employee) : "Sin preferencia"}</div>
              <div class="list-actions">
                ${renderStatusButtons(item.id)}
                <button class="link-btn" data-action="whatsapp" data-id="${item.id}">Enviar por WASAP</button>
                <button class="link-btn" data-action="calendar" data-date="${dateKey}">Ver en calendario</button>
                <button class="link-btn" data-action="email" data-id="${item.id}">Enviar por correo</button>
              </div>
            `;
            row.querySelectorAll("button").forEach(btn => btn.addEventListener("click", handleAgendaAction));
            refs.agendaList.appendChild(row);
          });
        }

        function handleAgendaAction(event) {
          const id = event.currentTarget.dataset.id;
          const action = event.currentTarget.dataset.action;
          const status = event.currentTarget.dataset.status;
          const date = event.currentTarget.dataset.date;
          const item = state.appointments.find(a => a.id === id);
          if (!item && action !== "calendar") return;

          if (action === "whatsapp") {
            if (!item.phone) {
              alert("Este cliente no tiene teléfono asociado.");
              return;
            }
            const msg = buildReminderMessage(item, state.settings);
            const number = sanitizeNumber(item.phone);
            const encoded = encodeURIComponent(msg);
            const url = `https://api.whatsapp.com/send?phone=${number}&text=${encoded}`;
            window.open(url, "_blank", "noopener");
            return;
          }

          if (action === "email") {
            if (!item.email) {
              alert("Este cliente no tiene email asociado.");
              return;
            }
            sendAppointmentEmail(item);
            return;
          }

          if (action === "calendar" && date) {
            const parts = date.split("-");
            if (parts.length === 3) {
              const year = parts[0];
              const month = parts[1].replace(/^0/, "");
              window.location.href = `calendar.php?view=month&year=${year}&month=${month}`;
            } else {
              window.location.href = "calendar.php";
            }
            return;
          }

          if (status) {
            item.status = status;
            saveState(storageKey, state);

            // PATCH /api/citas/appointments/:id
            if (status === "Confirmada" && item.email && !item.emailSent) {
              sendAppointmentEmail(item);
            }
            renderAll();
          }
        }

        function renderSettings() {
          refs.businessNameInput.value = state.settings.businessName || "";
          refs.whatsappInput.value = state.settings.whatsappNumber || "";
          refs.currencyInput.value = state.settings.currency || "";
          refs.slotSizeInput.value = state.settings.slotSize || 30;
          if (refs.smtpHostInput) refs.smtpHostInput.value = state.settings.smtpHost || "";
          if (refs.smtpPortInput) refs.smtpPortInput.value = state.settings.smtpPort || "";
          if (refs.smtpUserInput) refs.smtpUserInput.value = state.settings.smtpUser || "";
          if (refs.smtpFromInput) refs.smtpFromInput.value = state.settings.smtpFromEmail || "";
          if (refs.smtpNameInput) refs.smtpNameInput.value = state.settings.smtpFromName || "";
        }

        function setTab(tab) {
          currentTab = tab;
          if (tab === "requests") {
            refs.tabRequests.classList.add("active");
            refs.tabAgenda.classList.remove("active");
            refs.requestsSection.style.display = "block";
            refs.agendaSection.style.display = "none";
          } else {
            refs.tabAgenda.classList.add("active");
            refs.tabRequests.classList.remove("active");
            refs.requestsSection.style.display = "none";
            refs.agendaSection.style.display = "block";
          }
        }

        function unmount(target) {
          const el = target || container;
          if (el) el.innerHTML = "";
        }

        return { unmount };
      }

      function buildShellHtml(settings, embed, isAdmin) {
        const shellClass = embed ? "iuc-shell embed" : "iuc-shell";
        const base = (window.IUC_CITAS_BASE || "").replace(/\/$/, "");
        const icons = {
          home: `<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 9l9-7 9 7"></path>
            <path d="M9 22V12h6v10"></path>
          </svg>`,
          calendar: `<svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>`,
          clock: `<svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 6v6l4 2"></path>
          </svg>`,
          book: `<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M2 4h6a4 4 0 0 1 4 4v14"></path>
            <path d="M22 4h-6a4 4 0 0 0-4 4v14"></path>
          </svg>`,
          settings: `<svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c0 .7.4 1.34 1.03 1.63.3.14.62.21.95.21H21a2 2 0 1 1 0 4h-.09c-.38 0-.74.13-1.05.36z"></path>
          </svg>`,
          logout: `<svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>`
        };
        const emailSettingsBlock = isAdmin ? `
                    <div class="settings-panel">
                      <h4>Correo (SMTP)</h4>
                      <div class="settings-grid" id="email-settings-form">
                        <div>
                          <label>smtpHost</label>
                          <input id="settings-smtp-host" placeholder="info.iuconnect.com">
                        </div>
                        <div>
                          <label>smtpPort</label>
                          <input id="settings-smtp-port" type="number" min="1" max="65535" placeholder="587">
                        </div>
                        <div>
                          <label>smtpUser</label>
                          <input id="settings-smtp-user" placeholder="info@iuconnect.com">
                        </div>
                        <div>
                          <label>smtpFromEmail</label>
                          <input id="settings-smtp-from" placeholder="info@iuconnect.com">
                        </div>
                        <div>
                          <label>smtpFromName</label>
                          <input id="settings-smtp-name" placeholder="PELUQUERIA VERO">
                        </div>
                      </div>
                      <p class="notice">La contraseña SMTP se lee desde <code>.env</code>.</p>
                    </div>
        ` : '';
        return `
          <div class="${shellClass}">
            <aside class="iuc-sidebar">
              <div class="iuc-logo">IUC</div>
              <nav>
                <ul class="nav-list">
                  <li><a class="nav-item" href="${base}/dashboard.php"><span class="nav-icon">${icons.home}</span>Dashboard</a></li>
                  <li><a class="nav-item" href="${base}/calendar.php"><span class="nav-icon">${icons.calendar}</span>Calendario</a></li>
                  <li><a class="nav-item active" href="${base}/citas.php"><span class="nav-icon">${icons.clock}</span>Citas</a></li>
                  <li><a class="nav-item" href="${base}/biblioteca.php"><span class="nav-icon">${icons.book}</span>Biblioteca</a></li>
                  <li><a class="nav-item" href="${base}/settings.php"><span class="nav-icon">${icons.settings}</span>Configuracion</a></li>
                </ul>
                <div class="nav-footer">
                  <a class="nav-item" href="${base}/logout.php"><span class="nav-icon">${icons.logout}</span>Cerrar sesion</a>
                </div>
              </nav>
            </aside>
            <main class="iuc-content">
              <section class="module-header">
                <div class="module-title">
                  <h1>Citas</h1>
                  <p>Control claro de solicitudes, estimaciones y agenda interna para ${escapeHtml(settings.businessName || "tu negocio")}.</p>
                </div>
                <div class="notice">Tenant: ${escapeHtml(settings.tenantId || "default")}</div>
              </section>

              <section class="kpi-grid">
                <div class="kpi-card"><span>Solicitudes nuevas</span><strong id="kpi-new">0</strong></div>
                <div class="kpi-card"><span>Citas hoy</span><strong id="kpi-today">0</strong></div>
                <div class="kpi-card"><span>Pendientes confirmación</span><strong id="kpi-pending">0</strong></div>
                <div class="kpi-card"><span>Canceladas 7 días</span><strong id="kpi-cancelled">0</strong></div>
              </section>

              <section class="module-grid">
                <div class="card">
                  <h3>Nueva solicitud (cliente)</h3>
                  <form id="request-form">
                    <div class="form-grid">
                      <div>
                        <label>Servicio</label>
                        <select name="service" id="service">
                          <option>Corte</option>
                          <option>Corte+Peinado</option>
                          <option>Color raíces</option>
                          <option>Balayage/Mechas</option>
                        </select>
                      </div>
                      <div>
                        <label>Empleado (opcional)</label>
                        <select name="employee" id="employee">
                          <option>Cualquiera</option>
                          <option>Ana</option>
                          <option>Mario</option>
                          <option>Lucía</option>
                        </select>
                      </div>
                      <div>
                        <label>Largo</label>
                        <select name="largo" id="largo">
                          <option>corto</option>
                          <option>medio</option>
                          <option>largo</option>
                          <option>extra</option>
                        </select>
                      </div>
                      <div>
                        <label>Densidad</label>
                        <select name="densidad" id="densidad">
                          <option>baja</option>
                          <option>media</option>
                          <option>alta</option>
                        </select>
                      </div>
                      <div>
                        <label>Tinte previo</label>
                        <select name="tintePrevio" id="tintePrevio">
                          <option>no</option>
                          <option>si</option>
                        </select>
                      </div>
                      <div>
                        <label>Decoloración</label>
                        <select name="decoloracion" id="decoloracion">
                          <option>no</option>
                          <option>no lo se</option>
                          <option>si</option>
                        </select>
                      </div>
                      <div>
                        <label>Nombre</label>
                        <input name="name" id="name" required placeholder="Nombre y apellido">
                      </div>
                      <div>
                        <label>Teléfono (opcional)</label>
                        <input name="phone" id="phone" placeholder="Ej: 600 123 123">
                      </div>
                      <div>
                        <label>Email (opcional)</label>
                        <input type="email" name="email" id="email" placeholder="cliente@email.com">
                      </div>
                    </div>
                    <div class="form-grid full" style="margin-top: 10px;">
                      <div>
                        <label>Notas (opcional)</label>
                        <textarea name="notes" id="notes" placeholder="Preferencias, historial, etc."></textarea>
                      </div>
                      <div>
                        <label>Fecha preferida (opcional)</label>
                        <input type="datetime-local" id="preferredDate">
                      </div>
                      <div>
                        <label>Fotos opcionales (número)</label>
                        <input type="number" min="0" max="6" id="photosCount" value="0">
                      </div>
                    </div>

                    <div class="estimate-card" style="margin-top: 12px;">
                      <div class="estimate-row">
                        <span>Tiempo estimado</span>
                        <strong id="time-estimate">0-0 min</strong>
                      </div>
                      <div class="estimate-row">
                        <span>Precio estimado</span>
                        <strong id="price-estimate">0</strong>
                      </div>
                      <div class="estimate-row">
                        <span>Incertidumbre</span>
                        <strong id="uncertainty">0 / 10</strong>
                      </div>
                      <input type="range" min="0" max="10" value="0" id="uncertainty-bar" disabled>
                      <div class="chips">
                        <span class="chip" id="chip-largo">Largo</span>
                        <span class="chip" id="chip-densidad">Densidad</span>
                        <span class="chip" id="chip-tinte">Tinte previo</span>
                        <span class="chip" id="chip-deco">Decoloración</span>
                      </div>
                      <div class="notice">Bloqueo mínimo recomendado: <strong id="blocked-slot">0</strong></div>
                    </div>

                    <label style="display:flex; align-items:center; gap:8px; margin-top: 10px; font-size: 12px; color: #6b7280;">
                      <input type="checkbox" id="include-details" checked>
                      Incluir largo, densidad, tinte previo y decoloración en WhatsApp
                    </label>

                    <div class="btn-row">
                      <button class="btn primary" type="submit">Crear solicitud</button>
                      <button class="btn secondary" type="button" id="btn-whatsapp">Reservar por WhatsApp</button>
                      <button class="btn ghost" type="button" id="btn-copy">Copiar mensaje</button>
                    </div>
                    <p class="notice" style="margin-top: 8px;">Mensaje WhatsApp pre-rellenado:</p>
                    <textarea id="whatsapp-preview" readonly></textarea>
                  </form>

                  <div class="settings-panel">
                    <h4>Settings del módulo</h4>
                    <div class="settings-grid" id="settings-form">
                      <div>
                        <label>businessName</label>
                        <input id="settings-business" placeholder="Negocio">
                      </div>
                      <div>
                        <label>whatsappNumber (E.164 sin +)</label>
                        <input id="settings-whatsapp" placeholder="">
                      </div>
                      <div>
                        <label>currency</label>
                        <input id="settings-currency" placeholder="EUR">
                      </div>
                      <div>
                        <label>slotSize (minutos)</label>
                        <input id="settings-slot" type="number" min="15" max="60" step="5" value="30">
                      </div>
                    </div>
                    <div class="btn-row">
                      <button class="btn secondary" type="button" id="btn-demo">Cargar datos demo</button>
                    </div>
                  </div>
                  ${emailSettingsBlock}
                </div>

                <div class="card">
                  <div class="tabs">
                    <button class="tab active" id="tab-requests">Solicitudes</button>
                    <button class="tab" id="tab-agenda">Agenda</button>
                  </div>

                  <div id="section-requests">
                    <div class="list" id="requests-list"></div>
                  </div>

                  <div id="section-agenda" style="display:none;">
                    <div class="filters">
                      <select id="filter-status">
                        <option>Todos</option>
                        <option>Pendiente</option>
                        <option>Confirmada</option>
                        <option>Completada</option>
                        <option>Cancelada</option>
                      </select>
                      <input id="search-input" placeholder="Buscar cliente o servicio">
                    </div>
                    <div class="list" id="agenda-list"></div>
                    <p class="notice">* Bloqueo mínimo recomendado por cita basado en el tiempo mínimo del servicio.</p>
                  </div>
                </div>
              </section>
              <div class="modal-overlay" id="appointment-modal">
                <div class="modal-card">
                  <h4>Confirmar cita</h4>
                  <p class="notice" id="appointment-modal-title">Selecciona fecha y hora</p>
                  <label>Fecha y hora</label>
                  <input type="datetime-local" id="appointment-date-input">
                  <div class="modal-actions">
                    <button class="btn ghost" type="button" id="appointment-cancel">Cancelar</button>
                    <button class="btn primary" type="button" id="appointment-confirm">Crear cita</button>
                  </div>
                </div>
              </div>
            </main>
          </div>
        `;
      }

      function mapRefs(container) {
        return {
          form: container.querySelector("#request-form"),
          service: container.querySelector("#service"),
          employee: container.querySelector("#employee"),
          largo: container.querySelector("#largo"),
          densidad: container.querySelector("#densidad"),
          tintePrevio: container.querySelector("#tintePrevio"),
          decoloracion: container.querySelector("#decoloracion"),
          name: container.querySelector("#name"),
          phone: container.querySelector("#phone"),
          email: container.querySelector("#email"),
          notes: container.querySelector("#notes"),
          preferredDate: container.querySelector("#preferredDate"),
          photosCount: container.querySelector("#photosCount"),
          includeDetails: container.querySelector("#include-details"),
          timeEstimate: container.querySelector("#time-estimate"),
          priceEstimate: container.querySelector("#price-estimate"),
          uncertainty: container.querySelector("#uncertainty"),
          uncertaintyBar: container.querySelector("#uncertainty-bar"),
          chipLargo: container.querySelector("#chip-largo"),
          chipDensidad: container.querySelector("#chip-densidad"),
          chipTinte: container.querySelector("#chip-tinte"),
          chipDeco: container.querySelector("#chip-deco"),
          blockedSlot: container.querySelector("#blocked-slot"),
          whatsappPreview: container.querySelector("#whatsapp-preview"),
          whatsappBtn: container.querySelector("#btn-whatsapp"),
          copyBtn: container.querySelector("#btn-copy"),
          demoBtn: container.querySelector("#btn-demo"),
          requestsList: container.querySelector("#requests-list"),
          agendaList: container.querySelector("#agenda-list"),
          tabRequests: container.querySelector("#tab-requests"),
          tabAgenda: container.querySelector("#tab-agenda"),
          requestsSection: container.querySelector("#section-requests"),
          agendaSection: container.querySelector("#section-agenda"),
          filterStatus: container.querySelector("#filter-status"),
          searchInput: container.querySelector("#search-input"),
          appointmentModal: container.querySelector("#appointment-modal"),
          appointmentModalTitle: container.querySelector("#appointment-modal-title"),
          appointmentDateInput: container.querySelector("#appointment-date-input"),
          appointmentCancel: container.querySelector("#appointment-cancel"),
          appointmentConfirm: container.querySelector("#appointment-confirm"),
          settingsForm: container.querySelector("#settings-form"),
          emailSettingsForm: container.querySelector("#email-settings-form"),
          businessNameInput: container.querySelector("#settings-business"),
          whatsappInput: container.querySelector("#settings-whatsapp"),
          currencyInput: container.querySelector("#settings-currency"),
          slotSizeInput: container.querySelector("#settings-slot"),
          smtpHostInput: container.querySelector("#settings-smtp-host"),
          smtpPortInput: container.querySelector("#settings-smtp-port"),
          smtpUserInput: container.querySelector("#settings-smtp-user"),
          smtpFromInput: container.querySelector("#settings-smtp-from"),
          smtpNameInput: container.querySelector("#settings-smtp-name"),
          kpiNew: container.querySelector("#kpi-new"),
          kpiToday: container.querySelector("#kpi-today"),
          kpiPending: container.querySelector("#kpi-pending"),
          kpiCancelled: container.querySelector("#kpi-cancelled")
        };
      }

      function loadState(key, options) {
        const raw = localStorage.getItem(key);
        if (!raw) return { ...STATE_TEMPLATE, settings: { ...STATE_TEMPLATE.settings, ...options } };
        try {
          const parsed = JSON.parse(raw);
          return { ...STATE_TEMPLATE, ...parsed, settings: { ...STATE_TEMPLATE.settings, ...parsed.settings, ...options } };
        } catch (err) {
          return { ...STATE_TEMPLATE, settings: { ...STATE_TEMPLATE.settings, ...options } };
        }
      }

      function saveState(key, state) {
        localStorage.setItem(key, JSON.stringify(state));
      }

      function buildWhatsAppMessage(data, estimate, settings) {
        const lines = [];
        const name = data.name || "";
        lines.push(`Hola ${name},`);
        lines.push(`¡Gracias por escribir a ${settings.businessName}! ✨`);
        lines.push("");
        lines.push(`✅ Te confirmamos tu reserva para el servicio ${data.service}.`);
        lines.push("");
        lines.push("Detalles de tu cita");
        lines.push("");
        lines.push(`Servicio: ${data.service}`);
        if (data.includeDetails) {
          lines.push("");
          lines.push(`Largo: ${toTitleCase(data.largo)}`);
          lines.push("");
          lines.push(`Densidad: ${toTitleCase(data.densidad)}`);
          lines.push("");
          lines.push(`Tinte previo: ${data.tintePrevio === "si" ? "Sí" : "No"}`);
          lines.push("");
          lines.push(`Decoloración: ${data.decoloracion === "no lo se" ? "Por confirmar" : (data.decoloracion === "si" ? "Sí" : "No")}`);
        }
        lines.push("");
        lines.push(`⏱️ Duración estimada: ${formatDurationRange(estimate.timeMin, estimate.timeMax)}`);
        lines.push(`💶 Precio estimado: ${formatCurrencyRangeInline(estimate.priceMin, estimate.priceMax, settings.currency)}`);
        if (data.notes) {
          lines.push("");
          lines.push(`Notas: ${data.notes}`);
        }
        if (data.photosCount > 0) {
          lines.push("");
          lines.push(`Adjunté ${data.photosCount} fotos en la solicitud.`);
        }
        lines.push("");
        lines.push("Si necesitas ajustar cualquier detalle, puedes responder a este mensaje y te ayudamos encantadas.");
        lines.push("");
        lines.push("Un saludo,");
        lines.push(`${settings.businessName} 💇‍♀️`);
        return lines.join("\n");
      }

      function formatEstimate(estimate, currency) {
        return `${estimate.timeMin}-${estimate.timeMax} min · ${formatCurrencyRange(estimate.priceMin, estimate.priceMax, currency)}`;
      }

      function formatCurrencyRange(min, max, currency) {
        return `${currencySymbol(currency)}${min}-${currencySymbol(currency)}${max}`;
      }

      function formatCurrencyRangeInline(min, max, currency) {
        const symbol = currencySymbol(currency);
        if (symbol === "€") {
          return `${min}€–${max}€`;
        }
        return `${symbol}${min}–${symbol}${max}`;
      }

      function formatDurationRange(minMinutes, maxMinutes) {
        return `${formatDuration(minMinutes)} – ${formatDuration(maxMinutes)}`;
      }

      function formatDuration(totalMinutes) {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        if (hours <= 0) {
          return `${minutes} min`;
        }
        if (minutes === 0) {
          return `${hours} h`;
        }
        return `${hours} h ${minutes} min`;
      }

      function toTitleCase(value) {
        return String(value || "")
          .split(" ")
          .map(word => word ? word.charAt(0).toUpperCase() + word.slice(1) : "")
          .join(" ");
      }

      function currencySymbol(code) {
        switch ((code || "").toUpperCase()) {
          case "USD": return "$";
          case "GBP": return "£";
          case "MXN": return "$";
          case "COP": return "$";
          case "CLP": return "$";
          case "ARS": return "$";
          case "EUR":
          default: return "€";
        }
      }

      function calcSlotBlock(minTime, slotSize) {
        const size = slotSize || 30;
        const blocks = Math.ceil(minTime / size) * size;
        return `${blocks} min`;
      }

      function sanitizeNumber(number) {
        return (number || "").replace(/\D/g, "") || "0";
      }

      function formatDateTime(value) {
        const date = new Date(value);
        if (isNaN(date.getTime())) return "Sin fecha";
        return date.toLocaleString("es-ES", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });
      }

      function formatDateKey(date) {
        return date.toISOString().slice(0, 10);
      }

      function formatLocalDateKey(value) {
        const date = new Date(value);
        if (isNaN(date.getTime())) return "";
        const pad = (n) => String(n).padStart(2, "0");
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
      }

      function statusClass(status) {
        switch (status) {
          case "Pendiente": return "pending";
          case "Confirmada": return "confirmed";
          case "Completada": return "completed";
          case "Cancelada": return "cancelled";
          default: return "pending";
        }
      }

      function renderStatusButtons(id) {
        const statuses = ["Pendiente", "Confirmada", "Completada", "Cancelada"];
        return statuses.map(status => `<button class="link-btn" data-action="status" data-id="${id}" data-status="${status}">${status}</button>`).join(" ");
      }

        function buildRequestMeta(item) {
          const parts = [
            `Largo ${item.largo}`,
            `Densidad ${item.densidad}`,
            `Tinte previo ${item.tintePrevio}`,
            `Decoloración ${item.decoloracion}`
          ];
          if (item.preferredDate) parts.push(`Fecha ${formatDateTime(item.preferredDate)}`);
          if (item.employee && item.employee !== "Cualquiera") parts.push(`Empleado ${item.employee}`);
          return parts.join(" · ");
        }

      function escapeHtml(str) {
        return String(str || "").replace(/[&<>"']/g, (m) => ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;"
        }[m]));
      }

      function cryptoId() {
        return Math.random().toString(36).slice(2, 10);
      }

      function demoRequests() {
        return [
          {
            id: cryptoId(),
            createdAt: new Date().toISOString(),
            status: "Nueva",
            service: "Balayage/Mechas",
            employee: "Ana",
            largo: "largo",
            densidad: "alta",
            tintePrevio: "si",
            decoloracion: "si",
            name: "Paula G.",
            phone: "600123321",
            notes: "Quiere tonos fríos",
            photosCount: 2,
            estimate: computeDemoEstimate("Balayage/Mechas", "largo", "alta", "si", "si")
          },
          {
            id: cryptoId(),
            createdAt: new Date().toISOString(),
            status: "Nueva",
            service: "Color raíces",
            employee: "Cualquiera",
            largo: "medio",
            densidad: "media",
            tintePrevio: "no",
            decoloracion: "no",
            name: "Marta L.",
            phone: "",
            notes: "",
            photosCount: 0,
            estimate: computeDemoEstimate("Color raíces", "medio", "media", "no", "no")
          }
        ];
      }

      function demoAppointments() {
        const date = new Date();
        date.setHours(date.getHours() + 3);
        return [
          {
            id: cryptoId(),
            dateTime: date.toISOString(),
            dateKey: formatDateKey(date),
            status: "Confirmada",
            requestId: "",
            name: "Clara R.",
            service: "Corte+Peinado",
            estimate: { timeMin: 60, timeMax: 75, priceMin: 38, priceMax: 48 },
            employee: "Lucía",
            phone: "600445566"
          }
        ];
      }

      function computeDemoEstimate(service, largo, densidad, tintePrevio, decoloracion) {
        const base = SERVICE_CONFIG[service];
        let timeMin = base.timeMin;
        let timeMax = base.timeMax;
        let priceMin = base.priceMin;
        let priceMax = base.priceMax;
        if (base.type === "color") {
          const largoF = FACTORS.largo[largo];
          const densidadF = FACTORS.densidad[densidad];
          const tinteF = FACTORS.tintePrevio[tintePrevio];
          const decoF = FACTORS.decoloracion[decoloracion];
          const timeDelta = largoF.time + densidadF.time + tinteF.time + decoF.time;
          const priceDelta = largoF.price + densidadF.price + tinteF.price + decoF.price;
          timeMin += Math.max(0, timeDelta - 10);
          timeMax += Math.max(15, timeDelta + 10);
          priceMin += Math.max(0, priceDelta - 6);
          priceMax += Math.max(10, priceDelta + 8);
        }
        return { timeMin, timeMax, priceMin, priceMax };
      }

      function buildReminderMessage(appointment, settings) {
        const lines = [];
        lines.push(`Hola ${appointment.name}, esto es un recordatorio de tu cita en ${settings.businessName}.`);
        lines.push(`Esto es un recordatorio de tu cita y su hora de la cita.`);
        lines.push(`Fecha y hora: ${formatDateTime(appointment.dateTime)}.`);
        lines.push(`Servicio: ${appointment.service}.`);
        lines.push(`Estimación: ${appointment.estimate.timeMin}-${appointment.estimate.timeMax} min, ${formatCurrencyRange(appointment.estimate.priceMin, appointment.estimate.priceMax, settings.currency)}.`);
        return lines.join("\n");
      }

      function buildEmailMessage(appointment, settings) {
        const lines = [];
        lines.push(`Hola ${appointment.name},`);
        lines.push(`¡Gracias por escribir a ${settings.businessName}! ✨`);
        lines.push("");
        lines.push(`✅ Te confirmamos tu reserva para el servicio ${appointment.service}.`);
        lines.push("");
        lines.push("Detalles de tu cita");
        lines.push("");
        lines.push(`Servicio: ${appointment.service}`);
        lines.push("");
        lines.push(`⏱️ Duración estimada: ${formatDurationRange(appointment.estimate.timeMin, appointment.estimate.timeMax)}`);
        lines.push(`💶 Precio estimado: ${formatCurrencyRangeInline(appointment.estimate.priceMin, appointment.estimate.priceMax, settings.currency)}`);
        lines.push("");
        lines.push(`Fecha y hora: ${formatDateTime(appointment.dateTime)}`);
        lines.push("");
        lines.push("Si necesitas ajustar cualquier detalle, puedes responder a este mensaje y te ayudamos encantadas.");
        lines.push("");
        lines.push("Un saludo,");
        lines.push(`${settings.businessName} 💇‍♀️`);
        return lines.join("\n");
      }

      function sendAppointmentEmail(appointment) {
        if (!appointment.email) return;
        const subject = `Confirmación de cita - ${state.settings.businessName}`;
        const message = buildEmailMessage(appointment, state.settings);
        const overrides = {
          smtpHost: state.settings.smtpHost || "",
          smtpPort: state.settings.smtpPort || "",
          smtpUser: state.settings.smtpUser || "",
          fromEmail: state.settings.smtpFromEmail || "",
          fromName: state.settings.smtpFromName || ""
        };
        const base = window.IUC_CITAS_BASE || '';
        const endpoint = `${base}/citas.php?action=send_email`;
        fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            to: appointment.email,
            subject,
            message,
            overrides
          })
        })
          .then(res => res.json())
          .then(data => {
            if (data && data.ok) {
              appointment.emailSent = true;
              saveState(storageKey, state);
              alert('Correo enviado al cliente.');
            } else {
              alert(`No se pudo enviar el correo: ${data && data.error ? data.error : 'Error desconocido'}`);
            }
          })
          .catch(() => {
            alert('No se pudo enviar el correo.');
          });
      }

      IUCModules.Citas = { mount, unmount: (container) => { if (container) container.innerHTML = ""; } };

      const initialOptions = window.IUC_CITAS_OPTIONS || {};
      const root = document.querySelector("#iuc-citas-root");
      if (root) {
        mount(root, initialOptions);
      }
    })();
  </script>
<?php if (!$__iucCitasEmbed): ?>
</body>
</html>
<?php endif; ?>
