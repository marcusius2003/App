<?php
session_start();

// Requiere autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Config DB
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
    $_SESSION['ticket_error'] = 'Error de conexión a la base de datos.';
    header('Location: soporte.php');
    exit();
}

require_once __DIR__ . '/includes/auth_guard.php';
$currentUser = requireActiveUser($pdo);
$user_id = (int) $currentUser['id'];
$userRow = [
    'id'     => $currentUser['id'],
    'nombre' => $currentUser['username'],
    'email'  => $currentUser['email'],
];

// Validar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['ticket_error'] = 'Solicitud inválida.';
    header('Location: soporte.php');
    exit();
}

$subject = trim($_POST['subject'] ?? '');
$category = trim($_POST['category'] ?? 'Soporte Técnico');
$priority = trim($_POST['priority'] ?? 'Normal');
$description = trim($_POST['description'] ?? '');

if ($subject === '' || $description === '') {
    $_SESSION['ticket_error'] = 'Por favor complete asunto y descripción.';
    header('Location: soporte.php');
    exit();
}

// Generar ticket_id único
$ticket_id = strtoupper('TCK-' . bin2hex(random_bytes(6)));

// Guardar ticket
try {
    $insert = "INSERT INTO tickets (ticket_id, user_id, subject, category, priority, description, nombre, email, created_at) VALUES (:ticket_id, :user_id, :subject, :category, :priority, :description, :nombre, :email, NOW())";
    $stmt = $pdo->prepare($insert);
    $stmt->execute([
        ':ticket_id' => $ticket_id,
        ':user_id' => $user_id,
        ':subject' => $subject,
        ':category' => $category,
        ':priority' => $priority,
        ':description' => $description,
        ':nombre' => $userRow['nombre'],
        ':email' => $userRow['email']
    ]);
    $ticketInsertId = (int) $pdo->lastInsertId();
} catch (Exception $e) {
    $_SESSION['ticket_error'] = 'No se pudo crear el ticket. Intente de nuevo.';
    header('Location: soporte.php');
    exit();
}

// Manejar archivos (attachments) — guardarlos en uploads/tickets/{ticket_id}/
$uploadDirBase = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tickets' . DIRECTORY_SEPARATOR . $ticket_id;
if (!is_dir($uploadDirBase)) {
    mkdir($uploadDirBase, 0755, true);
}

$savedFiles = [];
if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'text/plain', 'text/log', 'application/log'
    ];
    $maxSize = 10 * 1024 * 1024; // 10MB
    for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp = $_FILES['attachments']['tmp_name'][$i];
        $name = basename($_FILES['attachments']['name'][$i]);
        $size = $_FILES['attachments']['size'][$i];
        $type = mime_content_type($tmp) ?: $_FILES['attachments']['type'][$i];
        if ($size > $maxSize) continue;
        // Opcional: comprobar $type en array allowedTypes (se hace suavemente)
        $dest = $uploadDirBase . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $name);
        if (move_uploaded_file($tmp, $dest)) {
            $savedFiles[] = $dest;
            // Podríamos guardar archivo en DB si fuera necesario
        }
    }
}

// Enviar email al usuario y a soporte. Config básico usando PHPMailer (mail() por defecto)
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailError = '';
try {
    $mail = new PHPMailer(true);
    // Usar método mail() por defecto; si quieres usar SMTP, debes configurar credenciales
    $mail->isMail();
    $mail->setFrom('soporte@iuconnect.local', 'Learnnect Soporte');

    // Destinatarios: usuario + soporte
    $mail->addAddress($userRow['email'], $userRow['nombre']);
    $mail->addAddress('soporte.iuconnect@gmail.com', 'Soporte Learnnect');

    // Reply-To a usuario
    $mail->addReplyTo($userRow['email'], $userRow['nombre']);

    $mail->isHTML(true);
    $mail->Subject = "Nuevo ticket creado: {$ticket_id} — {$subject}";

    // Construir cuerpo HTML con datos del ticket
    $body = "<h2>Nuevo ticket: {$ticket_id}</h2>";
    $body .= "<p><strong>Asunto:</strong> " . htmlspecialchars($subject) . "</p>";
    $body .= "<p><strong>Categoria:</strong> " . htmlspecialchars($category) . "</p>";
    $body .= "<p><strong>Prioridad:</strong> " . htmlspecialchars($priority) . "</p>";
    $body .= "<p><strong>Usuario:</strong> " . htmlspecialchars($userRow['nombre']) . " (" . htmlspecialchars($userRow['email']) . ")</p>";
    $body .= "<hr><h3>Descripción</h3><p>" . nl2br(htmlspecialchars($description)) . "</p>";
    if (!empty($savedFiles)) {
        $body .= "<hr><h3>Archivos adjuntos</h3><ul>";
        foreach ($savedFiles as $f) {
            $body .= '<li>' . htmlspecialchars(basename($f)) . '</li>';
            // Adjuntar al email también
            $mail->addAttachment($f, basename($f));
        }
        $body .= "</ul>";
    }

    $body .= "<p>Recibirás novedades sobre este ticket en tu correo.</p>";

    $mail->Body = $body;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], "\n", $body));

    $mail->send();
} catch (Exception $e) {
    // No romper la creación del ticket si falla el envío — para depuración guardamos mensaje
    $mailError = $e->getMessage();
}

// Marcar éxito y redirigir
$_SESSION['ticket_success'] = "Ticket creado: $ticket_id" . ($mailError ? " (email error: $mailError)" : ' — Se envió notificación por correo.');
header('Location: soporte.php');
exit();

?>

