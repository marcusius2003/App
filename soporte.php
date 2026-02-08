<?php
session_start();
// Requiere autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
// Mensajes flash para errores / éxito del ticket
$ticket_error = $_SESSION['ticket_error'] ?? '';
if (isset($_SESSION['ticket_error'])) unset($_SESSION['ticket_error']);
$ticket_success = $_SESSION['ticket_success'] ?? '';
// Conexión a la BD
$host = 'localhost';
$db   = 'iuconect';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';
$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'core.tickets');
$user_id = (int) $currentUser['id'];
// CSRF token simple
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
// Procesar actualización de perfil
$profile_error = '';
$profile_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'update_profile')) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $profile_error = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $newName = trim($_POST['username'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        if ($newName === '' || $newEmail === '') {
            $profile_error = 'Nombre y correo son obligatorios.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $profile_error = 'El correo no tiene un formato válido.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $stmt->execute([':email' => $newEmail, ':id' => $user_id]);
            $exists = $stmt->fetch();
            if ($exists) {
                $profile_error = 'Ese correo ya está en uso por otra cuenta.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email WHERE id = :id");
                $stmt->execute([':username' => $newName, ':email' => $newEmail, ':id' => $user_id]);
                $_SESSION['user_name'] = $newName;
                $_SESSION['user_email'] = $newEmail;
                $_SESSION['user_role'] = $currentUser['role']; // Mantener el rol actualizado
                $currentUser['username'] = $newName;
                $currentUser['email'] = $newEmail;
                $profile_success = 'Perfil actualizado correctamente.';
            }
        }
    }
}
// CONFIGURACIÓN DE CORREO
$mail_config = [
    'host' => 'smtp.gmail.com',
    'username' => 'soporte.iuconnect@gmail.com',
    'password' => 'jkhj vnln gnwl rgar',
    'from_email' => 'soporte.iuconnect@gmail.com',
    'from_name' => 'Soporte Learnnect',
    'support_emails' => [
        'soporte.iuconnect@gmail.com'
    ]
];
// Procesar creación de ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'create_ticket')) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['ticket_error'] = 'Token de seguridad inválido. Intenta de nuevo.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    // Recoger datos del formulario
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $priority = trim($_POST['priority'] ?? '');
    $description = trim($_POST['description'] ?? '');
    // Validaciones básicas
    if (empty($subject) || empty($category) || empty($priority) || empty($description)) {
        $_SESSION['ticket_error'] = 'Todos los campos obligatorios deben ser completados.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    try {
        // Generar ID único para el ticket
        $ticket_id = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        // DEBUG: Verificar antes de insertar
        error_log("🔄 Intentando crear ticket: {$ticket_id} para usuario: {$user_id}");
        // Insertar ticket en la base de datos
        $stmt = $pdo->prepare("INSERT INTO tickets (ticket_id, user_id, subject, category, priority, description, status, created_at) 
                              VALUES (:ticket_id, :user_id, :subject, :category, :priority, :description, 'abierto', NOW())");
        $stmt->execute([
            ':ticket_id' => $ticket_id,
            ':user_id' => $user_id,
            ':subject' => $subject,
            ':category' => $category,
            ':priority' => $priority,
            ':description' => $description
        ]);
        // Obtener el ID del ticket insertado
        $ticket_db_id = $pdo->lastInsertId();
        error_log("✅ Ticket insertado en BD. ID: {$ticket_db_id}, Ticket ID: {$ticket_id}");
        $ticket_created = true;
        // Verificar que el ticket se creó correctamente
        $check_stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $check_stmt->execute([$ticket_db_id]);
        $verified_ticket = $check_stmt->fetch();
        if (!$verified_ticket) {
            throw new Exception("El ticket no se pudo verificar en la base de datos");
        }
        // Procesar archivos adjuntos si existen
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = __DIR__ . '/uploads/tickets/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validar tipo de archivo
                    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'text/plain', 'text/log'];
                    $fileType = mime_content_type($_FILES['attachments']['tmp_name'][$i]);
                    if (in_array($fileType, $allowedTypes)) {
                        $fileName = time() . '_' . basename($_FILES['attachments']['name'][$i]);
                        $filePath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $filePath)) {
                            $attachments[] = $filePath;
                        }
                    }
                }
            }
        }
        // ENVÍO DE CORREOS MEJORADO
        $email_sent_to_support = false;
        $email_sent_to_user = false;
        $email_error_message = '';
        // Verificar si PHPMailer está disponible
        $phpmailer_path = __DIR__ . '/vendor/autoload.php';
        if (file_exists($phpmailer_path)) {
            require $phpmailer_path;
            // Verificar configuración de correo
            if (!empty($mail_config['username']) && !empty($mail_config['password']) && $mail_config['username'] !== 'tucorreo@gmail.com') {
                // CORREO AL EQUIPO DE SOPORTE
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $mail_config['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $mail_config['username'];
                    $mail->Password = $mail_config['password'];
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';
                    $mail->Timeout = 30;
                    $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
                    // Agregar múltiples emails de soporte
                    foreach ($mail_config['support_emails'] as $support_email) {
                        $mail->addAddress($support_email);
                    }
                    $mail->addReplyTo($currentUser['email'], $currentUser['username']);
                    // Adjuntar archivos
                    foreach ($attachments as $attachment) {
                        $mail->addAttachment($attachment);
                    }
                    $mail->isHTML(true);
                    $mail->Subject = "Nuevo Ticket de Soporte: {$ticket_id} - {$priority}";
                    $mail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: Arial, sans-serif; color: #0f172a; background: #f8fafc; padding: 0; margin: 0; }
                                .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
                                .header { background: #000; color: white; padding: 24px; text-align: center; }
                                .content { padding: 24px; }
                                .ticket-info { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-top: 20px; }
                                .ticket-info p { margin: 6px 0; }
                                .footer { padding: 20px; font-size: 12px; color: #64748b; text-align: center; }
                                .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #eef2ff; color: #312e81; font-size: 12px; font-weight: 600; }
                                    .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            max-width: 460px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
            border: 1px solid #e2e8f0;
        }
        .modal-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 20px;
            color: var(--dark);
        }
        .modal-card p {
            margin: 0 0 20px;
            color: #4b5563;
            line-height: 1.4;
        }
        .modal-card button {
            border: none;
            background: var(--primary);
            color: white;
            border-radius: 999px;
            padding: 10px 28px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .modal-card button:hover {
            opacity: 0.9;
        }
</style>
                        </head>
                        <body>
                            <div class='wrapper'>
                                <div class='header'>
                                    <h2>Learnnect Support</h2>
                                    <p class='badge'>Ticket {$ticket_id}</p>
                                </div>
                                <div class='content'>
                                    <h3>Nuevo ticket recibido</h3>
                                    <p><strong>Asunto:</strong> {$subject}</p>
                                    <p><strong>Prioridad:</strong> {$priority}</p>
                                    <p><strong>Departamento:</strong> {$category}</p>
                                    <div class='ticket-info'>
                                        <p><strong>Usuario:</strong> {$currentUser['username']} ({$currentUser['email']})</p>
                                        <p><strong>Descripción:</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>
                                        <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
                                    </div>
                                    <p style='margin-top:20px;'>Accede al panel de administración para gestionar el ticket.</p>
                                </div>
                                <div class='footer'>
                                    Este mensaje se generó automáticamente. No respondas a este correo.
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    if ($mail->send()) {
                        $email_sent_to_support = true;
                        error_log("Correo enviado a soporte para el ticket {$ticket_id}");
                    }
                } catch (Exception $e) {
                    $email_error_message .= "Error enviando a soporte: " . $e->getMessage() . " ";
                    error_log("Error PHPMailer soporte: " . $e->getMessage());
                }

                // Correo de confirmación al usuario
                try {
                    $userMail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $userMail->isSMTP();
                    $userMail->Host = $mail_config['host'];
                    $userMail->SMTPAuth = true;
                    $userMail->Username = $mail_config['username'];
                    $userMail->Password = $mail_config['password'];
                    $userMail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $userMail->Port = 587;
                    $userMail->CharSet = 'UTF-8';
                    $userMail->Timeout = 30;
                    $userMail->setFrom($mail_config['from_email'], $mail_config['from_name']);
                    $userMail->addAddress($currentUser['email'], $currentUser['username']);
                    $userMail->isHTML(true);
                    $userMail->Subject = "Confirmación de Ticket: {$ticket_id}";
                    $userMail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: Arial, sans-serif; color: #0f172a; background: #f8fafc; padding: 0; margin: 0; }
                                .wrapper { max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
                                .header { background: #000; color: white; padding: 22px; text-align: center; }
                                .content { padding: 22px; }
                                .ticket-info { background: #f8fafc; padding: 18px; border-radius: 10px; border: 1px solid #e2e8f0; margin-top: 16px; }
                                .footer { font-size: 12px; color: #94a3b8; padding: 20px; text-align: center; }
                                    .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            max-width: 460px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
            border: 1px solid #e2e8f0;
        }
        .modal-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 20px;
            color: var(--dark);
        }
        .modal-card p {
            margin: 0 0 20px;
            color: #4b5563;
            line-height: 1.4;
        }
        .modal-card button {
            border: none;
            background: var(--primary);
            color: white;
            border-radius: 999px;
            padding: 10px 28px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .modal-card button:hover {
            opacity: 0.9;
        }
</style>
                        </head>
                        <body>
                            <div class='wrapper'>
                                <div class='header'>
                                    <h2>Learnnect Support</h2>
                                </div>
                                <div class='content'>
                                    <p>Hola {$currentUser['username']},</p>
                                    <p>Hemos recibido tu ticket y nuestro equipo lo revisará a la brevedad.</p>
                                    <div class='ticket-info'>
                                        <p><strong>ID:</strong> {$ticket_id}</p>
                                        <p><strong>Asunto:</strong> {$subject}</p>
                                        <p><strong>Prioridad:</strong> {$priority}</p>
                                    </div>
                                    <p style='margin-top:14px;'>Te enviaremos actualizaciones por correo cuando haya cambios.</p>
                                </div>
                                <div class='footer'>
                                    Si no solicitaste este ticket, por favor contacta a soporte inmediatamente.
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    if ($userMail->send()) {
                        $email_sent_to_user = true;
                        error_log("Correo de confirmación enviado a {$currentUser['email']} para el ticket {$ticket_id}");
                    }
                } catch (Exception $e) {
                    $email_error_message .= "Error enviando confirmación al usuario: " . $e->getMessage() . " ";
                    error_log("Error PHPMailer usuario: " . $e->getMessage());
                }
            } else {
                $email_error_message .= "Configuración SMTP incompleta.";
            }
        } else {
            error_log("PHPMailer no encontrado en: " . $phpmailer_path);
        }

        // Mensaje final para la sesión
        if (!empty($ticket_created)) {
            $success_message = "Ticket <strong>{$ticket_id}</strong> creado correctamente.";
            if (isset($currentUser['role']) && ($currentUser['role'] === 'admin' || $currentUser['role'] === 'support')) {
                $success_message .= " Revisa el panel de administración para gestionarlo.";
            } else {
                $success_message .= " Recibirás actualizaciones en tu correo.";
            }
            if ($email_sent_to_support && $email_sent_to_user) {
                $success_message .= "<br>Correos enviados a soporte y al usuario.";
            } elseif ($email_sent_to_support) {
                $success_message .= "<br>Se notificó al equipo de soporte.";
            } elseif ($email_sent_to_user) {
                $success_message .= "<br>Se envió confirmación a tu correo.";
            } else {
                $success_message .= "<br><span style='color:#dc2626;'>No se pudieron enviar los correos automáticos.</span>";
            }
            $_SESSION['ticket_success'] = $success_message;
        }
    } catch (Exception $e) {
        $_SESSION['ticket_error'] = 'Error al crear el ticket: ' . $e->getMessage();
        error_log("Error creando ticket: " . $e->getMessage());
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de soporte | Learnnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #0f1225;
            --sidebar-text: #e2e8f0;
            --accent: #000000;
            --bg-right: #ffffff;
            --input-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f4f6fb;
            color: var(--text-main);
        }
        .support-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .support-top-bar {
            padding: 18px 5% 0;
            display: flex;
            justify-content: flex-start;
        }
        .support-top-bar .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #0f172a;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .support-top-bar .back-button i {
            color: #2563eb;
        }
        .support-top-bar .back-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.15);
        }
        .support-hero {
            background: linear-gradient(135deg, #050816 0%, #111827 60%, #1e293b 100%);
            color: white;
            padding: 30px 5% 28px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            font-size: 14px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #e2e8f0;
            margin-bottom: 18px;
        }
        .hero-main {
            display: flex;
            gap: 28px;
            margin-top: 10px;
            flex-wrap: wrap;
            align-items: stretch;
        }
        .hero-copy {
            flex: 1 1 320px;
        }
        .hero-copy h1 {
            font-size: 30px;
            line-height: 1.12;
            margin: 0 0 10px;
        }
        .hero-copy p {
            color: #cbd5f5;
            font-size: 15px;
            line-height: 1.4;
            margin-bottom: 18px;
        }
        .hero-highlights {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }
        .hero-highlights span {
            padding: 5px 11px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            font-size: 14px;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .hero-actions a {
            text-decoration: none;
            font-weight: 600;
            border-radius: 10px;
            padding: 9px 16px;
            transition: all 0.2s ease;
        }
        .hero-quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .hero-quick-links a {
            background: rgba(255,255,255,0.08);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 999px;
            padding: 6px 14px;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .hero-quick-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        .btn-primary {
            background: white;
            color: #0f172a;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary {
            border: 1px solid rgba(255,255,255,0.4);
            color: white;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.08); }
        .hero-support-card {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 14px;
            padding: 22px;
            flex: 1 1 280px;
            color: #e2e8f0;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.3);
        }
        .hero-support-card h3 {
            margin: 0 0 12px;
            font-size: 20px;
        }
        .hero-support-card p {
            margin: 0 0 16px;
            color: #cbd5f5;
        }
        .hero-card-list {
            list-style: none;
            padding: 0;
            margin: 0 0 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .hero-card-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            font-size: 14px;
        }
        .hero-card-list i {
            color: #22c55e;
        }
        .hero-support-card .support-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            text-decoration: none;
            color: white;
            font-weight: 600;
            background: rgba(37, 99, 235, 0.3);
        }
        .hero-support-card .support-link:hover {
            background: rgba(37, 99, 235, 0.45);
        }
        .support-stats { padding: 18px 5% 0; }
        .support-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }
        .metric-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        }
        .metric-card small {
            display: block;
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        .metric-card strong {
            display: block;
            font-size: 26px;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .metric-card span {
            font-size: 13px;
            color: #64748b;
        }
        .support-layout {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(380px, 1.35fr);
            gap: 24px;
            padding: 18px 5% 48px;
            align-items: flex-start;
        }
        .info-panel {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .info-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }
        .info-card h3 {
            margin-top: 0;
            margin-bottom: 14px;
        }
        .resource-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .resource-list a {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #0f172a;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #eef2ff;
            background: #f8fafc;
            font-weight: 500;
        }
        .contact-highlight {
            background: linear-gradient(120deg, #0f172a, #1f2937);
            color: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.35);
        }
        .contact-highlight ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .contact-highlight li {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .info-card .contact-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 16px;
        }
        .info-card .contact-row span {
            display: flex;
            gap: 10px;
            color: #475569;
            font-size: 15px;
        }
        .form-panel {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 26px;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.12);
        }
        .panel-head h2 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .panel-head p { margin: 0; color: #64748b; }
        .profile-card {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: #f8fafc;
            margin: 16px 0;
        }
        .profile-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }
        .profile-card strong {
            font-size: 18px;
            display: block;
            margin-top: 4px;
        }
        .profile-meta {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .full-width { grid-column: span 2; }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }
        .form-control, .select-control {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            transition: all 0.2s;
            color: var(--text-main);
        }
        .form-control:focus, .select-control:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }
        .file-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-dropzone:hover {
            border-color: var(--accent);
            background: #f1f5f9;
        }
        .dropzone-text { color: #64748b; font-size: 14px; margin-top: 8px; }
        .dropzone-icon { font-size: 24px; color: #94a3b8; }
        .actions {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            gap: 16px;
        }
        .btn-cancel {
            padding: 12px 24px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .btn-submit {
            padding: 12px 32px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: transform 0.1s, background 0.2s;
        }
        .btn-submit:hover {
            background: #333333;
            transform: translateY(-1px);
        }
        .notification {
            margin: 20px 0;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .notification.error {
            background: #fff1f2;
            border-left-color: #f43f5e;
            color: #b91c1c;
        }
        .notification.success {
            background: #ecfdf5;
            border-left-color: #10b981;
            color: #065f46;
        }
        .urgent-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 15px 45px rgba(15, 23, 42, 0.12);
        }
        .urgent-card p { color: #475569; line-height: 1.5; }
        .urgent-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 16px 0;
        }
        .followup-note {
            font-size: 14px;
            color: #94a3b8;
        }
        @media (max-width: 1024px) {
\n        .support-layout {
                grid-template-columns: 1fr;
                padding: 20px 6% 60px;
            }
        }
        @media (max-width: 768px) {
            .hero-main { flex-direction: column; }
            .hero-copy h1 { font-size: 34px; }
            .support-stats { padding: 30px 6% 0; }
            .form-panel { padding: 24px; }
        }
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .hero-actions { flex-direction: column; }
        }
            .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            max-width: 460px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
            border: 1px solid #e2e8f0;
        }
        .modal-card h3 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 20px;
            color: var(--dark);
        }
        .modal-card p {
            margin: 0 0 20px;
            color: #4b5563;
            line-height: 1.4;
        }
        .modal-card button {
            border: none;
            background: var(--primary);
            color: white;
            border-radius: 999px;
            padding: 10px 28px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .modal-card button:hover {
            opacity: 0.9;
        }
</style>
</head>
<body>
<?php if (!empty($ticket_success)): ?>
<div class="modal-overlay" id="ticketSuccessModal">
    <div class="modal-card">
        <h3><i class="fas fa-check-circle" style="color:#10B981;margin-right:6px;"></i> Ticket enviado</h3>
        <p><?php echo $ticket_success; ?></p>
        <button type="button" id="closeSuccessModal">OK</button>
    </div>
</div>
<?php unset($_SESSION['ticket_success']); ?>
<?php endif; ?>
<div class="support-page">
    <div class="support-top-bar">
        <a href="dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Volver al dashboard
        </a>
    </div>
    <header class="support-hero">
        <div class="hero-badge">
            <i class="fas fa-life-ring"></i>
            Learnnect Support Desk
        </div>
        <div class="hero-main">
            <div class="hero-copy">
                <h1>Gestiona tus incidencias con total tranquilidad</h1>
                <p>Comparte en pocos pasos lo que sucede y un especialista te contactara en menos de 2 horas laborales para ofrecerte la mejor solucion.</p>
                <div class="hero-highlights">
                    <span>Soporte 24/7</span>
                    <span>Ingenieros dedicados</span>
                    <span>Actualizaciones proactivas</span>
                </div>
                <div class="hero-actions">
                    <a href="#ticketForm" class="btn-primary"><i class="fas fa-paper-plane"></i> Crear ticket ahora</a>
                    <a href="mailto:soporte.iuconnect@gmail.com" class="btn-secondary"><i class="fas fa-envelope-open-text"></i> Escribir a soporte</a>
                </div>
                <div class="hero-quick-links">
                    <a href="#"><i class="fas fa-book"></i> Documentacion tecnica</a>
                    <a href="#"><i class="fas fa-question-circle"></i> Preguntas frecuentes</a>
                    <a href="#ticketForm"><i class="fas fa-comment-dots"></i> Nuevo ticket</a>
                </div>
            </div>
            <div class="hero-support-card">
                <h3>Equipo listo para ayudarte</h3>
                <p>Monitorizamos cada incidencia y priorizamos los casos criticos para darte una respuesta real en minutos.</p>
                <ul class="hero-card-list">
                    <li><i class="fas fa-bolt"></i>Enrutamiento automatico a especialistas</li>
                    <li><i class="fas fa-clock"></i>Seguimiento continuo y SLA de 2h</li>
                    <li><i class="fas fa-headset"></i>Alerta inmediata para incidencias criticas</li>
                </ul>
                <?php if (isset($currentUser['role']) && ($currentUser['role'] === 'admin' || $currentUser['role'] === 'support')): ?>
                    <a href="admin_panel.php" class="support-link"><i class="fas fa-tachometer-alt"></i> Abrir panel de administracion</a>
                <?php else: ?>
                    <a href="#ticketForm" class="support-link"><i class="fas fa-ticket"></i> Revisar tickets enviados</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="support-stats">
        <div class="support-metrics">
        </div>
    </section>

    <div class="support-layout">
        <section class="info-panel">
            <div class="info-card">
                <h3>Antes de abrir un ticket</h3>
                <p>Revisa si tu duda ya esta resuelta o si puedes aportar detalles utiles. Cuanto mas contexto aportes, mas rapido podremos ayudarte.</p>
                <ul class="resource-list">
                    <li><a href="#"><i class="fas fa-book"></i> Documentacion tecnica actualizada</a></li>
                    <li><a href="#"><i class="fas fa-graduation-cap"></i> Academia y tutoriales</a></li>
                    <li><a href="#"><i class="fas fa-bell"></i> Historial de novedades</a></li>
                </ul>
            </div>
            <div class="contact-highlight">
                <h3>Estamos a tu lado en cada incidencia</h3>
                <p>Cuantos mas detalles compartas, mas rapido podremos asignar a la persona adecuada y resolverlo a la primera.</p>
                <ul>
                    <li>
                        <i class="fas fa-headset"></i>
                        <div>
                            <strong>Soporte prioritario</strong><br>
                            Para interrupciones del servicio o lanzamientos planificados.
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-envelope-open-text"></i>
                        <div>
                            <strong>Actualizaciones proactivas</strong><br>
                            Te avisamos por correo en cada cambio de estado.
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Escalado garantizado</strong><br>
                            Ingenieros senior pendientes de incidentes criticos.
                        </div>
                    </li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Contactos directos</h3>
                <div class="contact-row">
                    <span><i class="fas fa-envelope"></i> info.iuconnect@gmail.com</span>
                    <span><i class="fas fa-phone"></i> +34 900 123 456 (Soporte de ventas)</span>
                </div>
            </div>
            <div class="urgent-card">
                <h3>Necesitas ayuda inmediata?</h3>
                <p>Para caidas completas o bloqueos operativos llama o escribe directamente a nuestro equipo 24/7.</p>
                <div class="urgent-actions">
                    <a href="tel:+34900123456" class="admin-link" style="margin-top:0;">
                        <i class="fas fa-phone-alt"></i> Llamar a soporte 24/7
                    </a>
                    <a href="mailto:soporte.iuconnect@gmail.com" class="user-link" style="margin-top:0;">
                        <i class="fas fa-envelope"></i> Escribir a soporte
                    </a>
                </div>
                <p class="followup-note">
                    Te notificaremos por correo cada vez que tu ticket cambie de estado, no tienes que consultar otros paneles.
                </p>
                <?php if (isset($currentUser['role']) && ($currentUser['role'] === 'admin' || $currentUser['role'] === 'support')): ?>
                <p style="margin-top:18px;">Eres parte del equipo? Gestiona incidencias desde el Panel de Administracion.</p>
                <a href="admin_panel.php" class="admin-link">
                    <i class="fas fa-tachometer-alt"></i> Abrir Panel de Administracion
                </a>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-panel">
            <div class="panel-head">
                <h2>Enviar un nuevo ticket</h2>
                <p>Describe tu incidencia con el mayor detalle posible; recibiras confirmacion automatica en tu correo.</p>
            </div>
            <div class="profile-card">
                <div class="profile-data">
                    <span class="profile-label">Usuario actual</span>
                    <strong><?php echo htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div class="profile-meta"><?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if (isset($currentUser['role'])): ?>
                        <div class="profile-meta">Rol: <strong><?php echo ucfirst(htmlspecialchars($currentUser['role'])); ?></strong></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-cancel" id="toggleEditProfile">Editar perfil</button>
            </div>
            <div id="editProfile" style="display:none; margin-bottom:16px;">
                <?php if ($profile_error): ?>
                    <div class="notification error" style="margin-bottom:12px;">
                        <?php echo htmlspecialchars($profile_error); ?>
                    </div>
                <?php elseif ($profile_success): ?>
                    <div class="notification success" style="margin-bottom:12px;">
                        <?php echo htmlspecialchars($profile_success); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:18px;">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div style="flex:1; min-width:220px;">
                        <label>Nombre de usuario</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control">
                    </div>
                    <div style="flex:1; min-width:220px;">
                        <label>Correo</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control">
                    </div>
                    <div style="flex:0 0 auto;">
                        <button type="submit" class="btn-submit">Guardar cambios</button>
                    </div>
                </form>
            </div>
            <form action="" method="POST" enctype="multipart/form-data" id="ticketForm">
                <input type="hidden" name="action" value="create_ticket">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tu Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>" readonly style="background:#f1f5f9; cursor:not-allowed; color:#64748b;">
                    </div>
                    <div class="form-group">
                        <label>Correo de contacto</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly style="background:#f1f5f9; cursor:not-allowed; color:#64748b;">
                    </div>
                    <div class="form-group full-width">
                        <label>Asunto <span class="required">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="Ej: Error al sincronizar base de datos v2" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Departamento <span class="required">*</span></label>
                        <select name="category" class="select-control" required>
                            <option value="">Selecciona un departamento</option>
                            <option value="Soporte Tecnico">Soporte Tecnico</option>
                            <option value="Facturacion y Licencias">Facturacion y Licencias</option>
                            <option value="Seguridad e Infraestructura">Seguridad e Infraestructura</option>
                            <option value="Solicitud de Funcionalidad">Solicitud de Funcionalidad</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Prioridad <span class="required">*</span></label>
                        <select name="priority" class="select-control" required>
                            <option value="">Selecciona prioridad</option>
                            <option value="Baja">Baja (Consulta general)</option>
                            <option value="Normal" selected>Normal</option>
                            <option value="Alta">Alta (Interrupcion parcial)</option>
                            <option value="Critica" style="color:#dc2626; font-weight:bold;">Critica (Sistema caido)</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Descripcion detallada <span class="required">*</span></label>
                        <textarea name="description" class="form-control" placeholder="Describe los pasos para reproducir el problema, mensajes de error y usuarios afectados..." required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Adjuntar capturas o logs (Opcional)</label>
                        <div class="file-dropzone">
                            <div class="dropzone-icon">??</div>
                            <div class="dropzone-text">
                                Arrastra archivos aqui o <strong>haz clic para buscar</strong><br>
                                <span style="font-size:12px; opacity:0.7">JPG, PNG, PDF, LOG (Max 10MB)</span>
                            </div>
                            <input type="file" style="display:none" id="fileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.log,.txt">
                        </div>
                    </div>
                </div>
                <div class="actions">
                    <button type="button" class="btn-cancel" onclick="window.history.back()">Cancelar</button>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span id="submitText">Enviar Ticket</span>
                        <span id="submitLoading" style="display:none;">Enviando...</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
<script>
        document.getElementById('closeSuccessModal')?.addEventListener('click', () => {
            document.getElementById('ticketSuccessModal')?.remove();
        });
        document.getElementById('ticketSuccessModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'ticketSuccessModal') {
                document.getElementById('ticketSuccessModal')?.remove();
            }
        });
    // Script simple para simular click en el dropzone
    document.querySelector('.file-dropzone').addEventListener('click', function() {
        document.getElementById('fileInput').click();
    });
    // Toggle para mostrar/ocultar el formulario de editar perfil
    const toggleBtn = document.getElementById('toggleEditProfile');
    const editBox = document.getElementById('editProfile');
    if (toggleBtn && editBox) {
        toggleBtn.addEventListener('click', function() {
            if (editBox.style.display === 'none' || editBox.style.display === '') {
                editBox.style.display = 'block';
                toggleBtn.textContent = 'Cerrar edición';
            } else {
                editBox.style.display = 'none';
                toggleBtn.textContent = 'Editar perfil';
            }
        });
    }
    // Mostrar nombre de archivos seleccionados
    document.getElementById('fileInput').addEventListener('change', function(e) {
        const files = e.target.files;
        const dropzone = document.querySelector('.file-dropzone');
        if (files.length > 0) {
            let fileNames = [];
            for (let i = 0; i < files.length; i++) {
                fileNames.push(files[i].name);
            }
            dropzone.querySelector('.dropzone-text').innerHTML = 
                `<strong>${files.length} archivo(s) seleccionado(s):</strong><br>` +
                `<span style="font-size:12px; opacity:0.7">${fileNames.join(', ')}</span>`;
        } else {
            dropzone.querySelector('.dropzone-text').innerHTML = 
                'Arrastra archivos aquí o <strong>haz clic para buscar</strong><br>' +
                '<span style="font-size:12px; opacity:0.7">JPG, PNG, PDF, LOG (Max 10MB)</span>';
        }
    });
    // Loading state para el botón de enviar
    document.getElementById('ticketForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const submitLoading = document.getElementById('submitLoading');
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitLoading.style.display = 'inline';
    });
    // Auto-ocultar notificaciones después de 8 segundos
    setTimeout(() => {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                notification.remove();
            }, 500);
        });
    }, 8000);
</script>
</body>
</html>












