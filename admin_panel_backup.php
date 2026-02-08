<?php
/**
 * Panel de Administración Learnnect
 * Sistema de Tickets global + Gestión de Tenants
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
// PHPMailer
require __DIR__ . '/vendor/autoload.php';
/**
 * Slug sencillo para subdominios de academias: nombre-centro -> nombre-centro.iuconnect.net
 */
function iu_slug(string $text): string
{
    $original = $text;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    // Reemplaza espacios y separadores por guiones
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    // Solo caracteres válidos para subdominio
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    if ($text === '') {
        $text = 'centro';
    }
    return $text;
}
/**
 * Subdominio público para un centro educativo
 * ejemplo: academia-valencia.iuconnect.net
 */
function iu_academy_subdomain(string $name): string
{
    return iu_slug($name) . '.iuconnect.net';
}
// Config de correo
const MAIL_CONFIG = [
    'host'       => 'smtp.gmail.com',
    'username'   => 'soporte.iuconnect@gmail.com',
    'password'   => 'jkhj vnln gnwl rgar', // APP PASSWORD de Gmail (recomendado mover a .env)
    'from_email' => 'soporte.iuconnect@gmail.com',
    'from_name'  => 'Soporte Learnnect',
];
class Database {
    private $host = 'localhost';
    private $dbname = 'iuconect'; // IMPORTANTE: nombre de la BD real
    private $username = 'root';
    private $password = '';
    private $conn;
    public function __construct() {
        try {
            // Conexión PDO a MySQL
            $this->conn = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES utf8mb4");
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    public function getConnection() {
        return $this->conn;
    }
}
class TicketSystem {
    private $db;
    public function __construct($db) {
        $this->db = $db;
    }
    /**
     * Crear ticket desde el panel.
     * SQL: INSERT en tabla tickets
     */
    public function createTicket($data) {
        $conn = $this->db->getConnection();
        try {
            $ticket_id = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $stmt = $conn->prepare("
                INSERT INTO tickets (ticket_id, user_id, subject, category, priority, description, status, created_at)
                VALUES (:ticket_id, :user_id, :subject, :category, :priority, :description, 'abierto', NOW())
            ");
            $stmt->execute([
                ':ticket_id'   => $ticket_id,
                ':user_id'     => $data['user_id'],
                ':subject'     => $data['subject'],
                ':category'    => $data['category'] ?? '',
                ':priority'    => $data['priority'] ?? 'Normal',
                ':description' => $data['description'],
            ]);
            return $conn->lastInsertId();
        } catch(PDOException $e) {
            error_log("Error creando ticket: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Obtener un ticket concreto.
     * SQL principal: SELECT con JOIN a users y academies
     */
    public function getTicket($ticket_id) {
        $conn = $this->db->getConnection();
        try {
            $sql = "
                SELECT 
                    t.*,
                    u.username AS created_by_name,
                    u.email    AS created_by_email,
                    a.name     AS academy_name,
                    u2.username AS assigned_to_name,
                    (
                        SELECT COUNT(*) 
                        FROM ticket_responses tr 
                        WHERE tr.ticket_id = t.id
                    ) AS message_count
                FROM tickets t
                LEFT JOIN users u  ON t.user_id   = u.id
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                LEFT JOIN academies a ON u.academy_id = a.id
                WHERE t.id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$ticket_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error obteniendo ticket: " . $e->getMessage());
            // Fallback sin academies ni contador
            try {
                $sql2 = "
                    SELECT 
                        t.*,
                        u.username AS created_by_name,
                        u.email    AS created_by_email,
                        NULL AS academy_name,
                        u2.username AS assigned_to_name
                    FROM tickets t
                    LEFT JOIN users u  ON t.user_id   = u.id
                    LEFT JOIN users u2 ON t.assigned_to = u2.id
                    WHERE t.id = ?
                ";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->execute([$ticket_id]);
                return $stmt2->fetch(PDO::FETCH_ASSOC);
            } catch(PDOException $e2) {
                error_log("Error fallback getTicket: " . $e2->getMessage());
                return false;
            }
        }
    }
    /**
     * Actualizar estado del ticket.
     * SQL: UPDATE tickets SET status = ?
     */
    public function updateTicketStatus($ticket_id, $status) {
        $conn = $this->db->getConnection();
        try {
            $stmt = $conn->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$status, $ticket_id]);
        } catch(PDOException $e) {
            error_log("Error actualizando estado del ticket: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Asignar ticket a un agente (usuario admin/support).
     * SQL: UPDATE tickets SET assigned_to = ?
     */
    public function assignTicket($ticket_id, $assigned_to) {
        $conn = $this->db->getConnection();
        try {
            $stmt = $conn->prepare("UPDATE tickets SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$assigned_to ?: null, $ticket_id]);
        } catch(PDOException $e) {
            error_log("Error asignando ticket: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Guardar respuesta de soporte en ticket_responses.
     * SQL: INSERT INTO ticket_responses
     */
    public function addTicketMessage($ticket_id, $user_id, $message, $is_internal = 0) {
        $conn = $this->db->getConnection();
        try {
            $stmt = $conn->prepare("
                INSERT INTO ticket_responses (ticket_id, user_id, message, is_internal, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $message, $is_internal]);
            return true;
        } catch(PDOException $e) {
            error_log("Error agregando mensaje al ticket: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Obtener mensajes desde ticket_responses.
     * SQL: SELECT desde ticket_responses + JOIN users
     */
    public function getTicketMessages($ticket_id) {
        $conn = $this->db->getConnection();
        try {
            $stmt = $conn->prepare("
                SELECT tr.*, u.username
                FROM ticket_responses tr
                LEFT JOIN users u ON tr.user_id = u.id
                WHERE tr.ticket_id = ?
                ORDER BY tr.created_at ASC
            ");
            $stmt->execute([$ticket_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error obteniendo mensajes del ticket: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Listado de tickets global.
     * SQL principal: SELECT tickets + users + academies + conteo de mensajes
     */
    public function getTickets($filter = 'all', $search = '', $academy_id = null) {
        $conn   = $this->db->getConnection();
        $tickets = [];
        $where  = "WHERE 1=1";
        $params = [];
        // Filtro de estado
        if ($filter !== 'all') {
            switch ($filter) {
                case 'open':
                    $where .= " AND (t.status = 'abierto' OR t.status = 'open')";
                    break;
                case 'in_progress':
                    $where .= " AND (t.status = 'en_progreso' OR t.status = 'in_progress')";
                    break;
                case 'closed':
                    $where .= " AND (t.status = 'cerrado' OR t.status = 'closed')";
                    break;
                default:
                    $where .= " AND t.status = ?";
                    $params[] = $filter;
            }
        }
        // Filtro por tenant (academy)
        if (!empty($academy_id)) {
            $where .= " AND u.academy_id = ?";
            $params[] = $academy_id;
        }
        // Búsqueda
        if (!empty($search)) {
            $where .= " AND (
                t.subject LIKE ?
                OR t.description LIKE ?
                OR u.username LIKE ?
            )";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        try {
            $sql = "
                SELECT 
                    t.*,
                    u.username AS created_by_name,
                    u.email    AS created_by_email,
                    a.name     AS academy_name,
                    u2.username AS assigned_to_name,
                    (
                        SELECT COUNT(*) 
                        FROM ticket_responses tr 
                        WHERE tr.ticket_id = t.id
                    ) AS message_count
                FROM tickets t
                LEFT JOIN users u  ON t.user_id = u.id
                LEFT JOIN academies a ON u.academy_id = a.id
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                $where
                ORDER BY t.created_at DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('getTickets OK. Filas: ' . count($tickets));
        } catch (PDOException $e) {
            // Fallback simple
            error_log("Error en getTickets: " . $e->getMessage() . " | Usando fallback simple.");
            try {
                $sqlFallback = "
                    SELECT 
                        t.*,
                        u.username AS created_by_name,
                        u.email    AS created_by_email,
                        NULL AS academy_name,
                        NULL AS assigned_to_name
                    FROM tickets t
                    LEFT JOIN users u ON t.user_id = u.id
                    $where
                    ORDER BY t.created_at DESC
                ";
                $stmtFallback = $conn->prepare($sqlFallback);
                $stmtFallback->execute($params);
                $tickets = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
                error_log('getTickets fallback devolvió ' . count($tickets) . ' filas.');
            } catch (PDOException $e2) {
                error_log("Error en getTickets fallback: " . $e2->getMessage());
                $tickets = [];
            }
        }
        return $tickets;
    }
    /**
     * Tickets recientes para dashboard.
     * SQL: SELECT últimos tickets + academy
     */
    public function getRecentTickets($limit = 5) {
        $conn = $this->db->getConnection();
        $tickets = [];
        try {
            $stmt = $conn->query("
                SELECT 
                    t.*,
                    u.username AS created_by_name,
                    a.name     AS academy_name
                FROM tickets t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN academies a ON u.academy_id = a.id
                ORDER BY t.created_at DESC
                LIMIT " . intval($limit)
            );
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error obteniendo tickets recientes: " . $e->getMessage());
            try {
                $stmt = $conn->query("
                    SELECT 
                        t.*,
                        u.username AS created_by_name
                    FROM tickets t
                    LEFT JOIN users u ON t.user_id = u.id
                    ORDER BY t.created_at DESC
                    LIMIT " . intval($limit)
                );
                $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch(PDOException $fallbackError) {
                error_log("Error en consulta alternativa de tickets recientes: " . $fallbackError->getMessage());
                $tickets = [];
            }
        }
        return $tickets;
    }
    /**
     * Conteo de tickets abiertos a nivel global.
     * SQL: SELECT COUNT(*) FROM tickets WHERE status...
     */
    public function getOpenTicketCount() {
        $conn = $this->db->getConnection();
        try {
            $stmt = $conn->query("
                SELECT COUNT(*) as total 
                FROM tickets 
                WHERE status IN ('abierto','open','en_progreso','in_progress')
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch(PDOException $e) {
            error_log("Error obteniendo conteo de tickets abiertos: " . $e->getMessage());
            return 0;
        }
    }
}
class AdminController {
    private $db;
    private $ticketSystem;
    public function __construct($db) {
        $this->db = $db;
        $this->ticketSystem = new TicketSystem($db);
    }
    /**
     * Sincroniza las respuestas que llegan por correo al buzón de soporte
     * y las mete en ticket_responses.
     *
     * Usa IMAP sobre el buzón global support.
     */
    private function syncEmailReplies(): void
    {
        if (!function_exists('imap_open')) {
            error_log('IMAP no disponible en este servidor. No se sincronizan respuestas por email.');
            return;
        }
        // Configuración IMAP de Gmail
        $imapHost = '{imap.gmail.com:993/imap/ssl}INBOX';
        $imapUser = MAIL_CONFIG['username'];
        $imapPass = MAIL_CONFIG['password'];
        $inbox = @imap_open($imapHost, $imapUser, $imapPass);
        if (!$inbox) {
            error_log('IMAP error: ' . imap_last_error());
            return;
        }
        $conn = $this->db->getConnection();
        // 1) Correos no leídos
        $emails = imap_search($inbox, 'UNSEEN');
        // 2) Si no hay UNSEEN, cogemos últimos 3 días (aunque estén leídos)
        if ($emails === false || empty($emails)) {
            $since = date('d-M-Y', strtotime('-3 days'));
            $emails = imap_search($inbox, 'SINCE "'.$since.'"');
        }
        if ($emails === false || empty($emails)) {
            imap_close($inbox);
            return;
        }
        rsort($emails); // Procesar primero los más recientes
        // Función auxiliar para obtener cuerpo texto/HTML simple
        $getBody = function ($msgNumber) use ($inbox) {
            $structure = imap_fetchstructure($inbox, $msgNumber);
            $body = '';
            if (!isset($structure->parts)) {
                $body = imap_body($inbox, $msgNumber);
            } else {
                foreach ($structure->parts as $index => $part) {
                    $partNo = $index + 1;
                    if ($part->type == 0 && in_array(strtolower($part->subtype), ['plain','html'])) {
                        $partBody = imap_fetchbody($inbox, $msgNumber, (string)$partNo);
                        if ($part->encoding == 3) {
                            $partBody = base64_decode($partBody);
                        } elseif ($part->encoding == 4) {
                            $partBody = quoted_printable_decode($partBody);
                        }
                        $body = $partBody;
                        break;
                    }
                }
            }
            return trim($body);
        };
        foreach ($emails as $msgNumber) {
            $overviewList = imap_fetch_overview($inbox, $msgNumber, 0);
            if (!$overviewList || !isset($overviewList[0])) {
                imap_setflag_full($inbox, $msgNumber, "\\Seen");
                continue;
            }
            $overview = $overviewList[0];
            $subject  = isset($overview->subject) ? imap_utf8($overview->subject) : '';
            // Email remitente (cliente real)
            $fromEmail = '';
            if (!empty($overview->from)) {
                $fromParts = imap_rfc822_parse_adrlist($overview->from, '');
                if (!empty($fromParts) && isset($fromParts[0]->mailbox, $fromParts[0]->host)) {
                    $fromEmail = strtolower($fromParts[0]->mailbox . '@' . $fromParts[0]->host);
                }
            }
            // Saltar correos enviados por el propio buzón de soporte
            if ($fromEmail && strtolower($fromEmail) === strtolower(MAIL_CONFIG['username'])) {
                imap_setflag_full($inbox, $msgNumber, "\\Seen");
                continue;
            }
            // Buscar [Ticket TKT-YYYYMMDD-XXXXXX] en el asunto
            if (!preg_match('/\[Ticket\s+(TKT-\d{8}-[A-Z0-9]{6})\]/i', $subject, $m)) {
                imap_setflag_full($inbox, $msgNumber, "\\Seen");
                continue;
            }
            $ticketCode = $m[1];
            // SQL: buscar ticket por ticket_id
            $stmt = $conn->prepare("SELECT id, user_id FROM tickets WHERE ticket_id = ?");
            $stmt->execute([$ticketCode]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) {
                imap_setflag_full($inbox, $msgNumber, "\\Seen");
                continue;
            }
            $body = $getBody($msgNumber);
            if ($body === '') {
                imap_setflag_full($inbox, $msgNumber, "\\Seen");
                continue;
            }
            // Por defecto, user_id del creador del ticket
            $userId = (int)$ticket['user_id'];
            // Si existe un usuario con ese email, lo usamos
            if ($fromEmail) {
                // SQL: buscar usuario por email
                $stmtUser = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
                $stmtUser->execute([$fromEmail]);
                if ($rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
                    $userId = (int)$rowUser['id'];
                }
            }
            // Evitar duplicados: mismo ticket, mismo usuario, mismo mensaje
            $stmtCheck = $conn->prepare("
                SELECT id 
                FROM ticket_responses 
                WHERE ticket_id = :ticket_id 
                  AND user_id   = :user_id 
                  AND message   = :message
                LIMIT 1
            ");
            $stmtCheck->execute([
                ':ticket_id' => $ticket['id'],
                ':user_id'   => $userId,
                ':message'   => $body,
            ]);
            if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                imap_setflag_full($inbox, $msgNumber, "\\Seen");
                continue;
            }
            // Insertar respuesta como mensaje del ticket
            $stmtInsert = $conn->prepare("
                INSERT INTO ticket_responses (ticket_id, user_id, message, is_internal, created_at)
                VALUES (:ticket_id, :user_id, :message, 0, NOW())
            ");
            $stmtInsert->execute([
                ':ticket_id' => $ticket['id'],
                ':user_id'   => $userId,
                ':message'   => $body,
            ]);
            imap_setflag_full($inbox, $msgNumber, "\\Seen");
        }
        imap_close($inbox);
    }
    public function render() {
        $page = $_GET['page'] ?? 'dashboard';
        $action = $_GET['action'] ?? 'list';
        switch($page) {
            case 'support':
                $this->renderSupport($action);
                break;
            case 'tenants':
                $this->renderTenants($action);
                break;
            case 'users':
                $this->renderUsers($action);
                break;
            case 'activity':
                $this->renderActivityLog();
                break;
            case 'settings':
                $this->renderSettings();
                break;
            default:
                $this->renderDashboard();
        }
    }
    /**
     * Manejo de POST antes del HTML
     */
    public function handlePostActions() {
        if ($_POST['action'] ?? '' === 'reply_ticket') {
            $this->handleTicketReply();
        } elseif ($_POST['action'] ?? '' === 'update_ticket_status') {
            $this->handleStatusUpdate();
        } elseif ($_POST['action'] ?? '' === 'assign_ticket') {
            $this->handleTicketAssignment();
        } elseif ($_POST['action'] ?? '' === 'create_ticket') {
            $this->handleCreateTicket();
        } elseif ($_POST['action'] ?? '' === 'create_user') {
            $this->handleCreateUser();
        }
        // A futuro: aquí se engancha creación de tenants, gestión de planes, etc.
    }
    /**
     * Ejecuta la sincronización de tickets por correo sin renderizar el panel.
     */
    public function handleAsyncSupportSync(): void
    {
        $this->syncEmailReplies();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
    }
    /**
     * Guardar respuesta + enviar correo al cliente si no es interna
     */
    private function handleTicketReply() {
        $ticket_id   = (int)($_POST['ticket_id'] ?? 0);
        $message     = trim($_POST['message'] ?? '');
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        $user_id     = $_SESSION['user_id'] ?? 1;
        if ($ticket_id <= 0 || $message === '') {
            $_SESSION['flash_error'] = "El mensaje no puede estar vacío.";
            header("Location: ?page=support&action=view&id=" . $ticket_id);
            exit;
        }
        $ok = $this->ticketSystem->addTicketMessage($ticket_id, $user_id, $message, $is_internal);
        if ($ok) {
            if (!$is_internal) {
                try {
                    $this->sendReplyEmailToCustomer($ticket_id, $message);
                } catch (Exception $e) {
                    error_log("Error enviando email de respuesta: " . $e->getMessage());
                }
            }
            $_SESSION['flash_message'] = "Respuesta agregada correctamente";
        } else {
            $_SESSION['flash_error'] = "Error al agregar la respuesta";
        }
        header("Location: ?page=support&action=view&id=" . $ticket_id);
        exit;
    }
    /**
     * Email al usuario que abrió el ticket con la respuesta del soporte.
     */
    private function sendReplyEmailToCustomer(int $ticketDbId, string $message): void
    {
        $ticket = $this->ticketSystem->getTicket($ticketDbId);
        if (!$ticket) {
            throw new Exception("Ticket no encontrado al enviar correo de respuesta");
        }
        $toEmail = $ticket['created_by_email'] ?? '';
        if (empty($toEmail)) {
            return;
        }
        $ticketCode   = $ticket['ticket_id'] ?: ('#' . $ticketDbId);
        $customerName = $ticket['created_by_name'] ?: 'usuario';
        $subject = "[Ticket {$ticketCode}] Respuesta del equipo de soporte";
        $bodyHtml = "
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family:Segoe UI,Arial,sans-serif;color:#111827;'>
                <div style='background:#000000;color:#ffffff;padding:16px;font-size:18px;font-weight:600;'>
                    Learnnect · Soporte
                </div>
                <div style='padding:20px;background:#f9fafb;'>
                    <p>Hola <strong>{$customerName}</strong>,</p>
                    <p>Hemos respondido a tu ticket:</p>
                    <p style='margin:10px 0;'>
                        <strong>Ticket:</strong> {$ticketCode}<br>
                        <strong>Asunto:</strong> ".htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8')."
                    </p>
                    <p><strong>Respuesta del soporte:</strong></p>
                    <div style='margin-top:8px;padding:12px;background:#ffffff;border-radius:6px;border:1px solid #e5e7eb;'>
                        ".nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))."
                    </div>
                    <p style='margin-top:16px;font-size:13px;color:#6b7280;'>
                        Puedes responder directamente a este correo para continuar la conversación.<br>
                        También puedes ver el ticket desde el portal de soporte.
                    </p>
                </div>
                <div style='padding:10px 20px;font-size:11px;color:#9ca3af;background:#000000;'>
                    Learnnect Support System · ".date('Y')."
                </div>
            </body>
            </html>
        ";
        $bodyText = "Hola {$customerName},
Hemos respondido a tu ticket {$ticketCode}:
{$message}
Puedes responder a este correo para continuar la conversación.
Learnnect Support System";
        $cfg = MAIL_CONFIG;
        if (empty($cfg['username']) || empty($cfg['password'])) {
            throw new Exception("MAIL_CONFIG incompleto");
        }
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $customerName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;
        $mail->send();
    }
    private function handleStatusUpdate() {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $status    = $_POST['status'] ?? '';
        if ($ticket_id <= 0 || $status === '') {
            $_SESSION['flash_error'] = "Datos de estado inválidos.";
            header("Location: ?page=support&action=view&id=" . $ticket_id);
            exit;
        }
        if ($this->ticketSystem->updateTicketStatus($ticket_id, $status)) {
            $_SESSION['flash_message'] = "Estado actualizado correctamente";
        } else {
            $_SESSION['flash_error'] = "Error al actualizar el estado";
        }
        header("Location: ?page=support&action=view&id=" . $ticket_id);
        exit;
    }
    private function handleTicketAssignment() {
        $ticket_id   = (int)($_POST['ticket_id'] ?? 0);
        $assigned_to = $_POST['assigned_to'] ?? '';
        if ($ticket_id <= 0) {
            $_SESSION['flash_error'] = "Ticket inválido";
            header("Location: ?page=support");
            exit;
        }
        if ($this->ticketSystem->assignTicket($ticket_id, $assigned_to)) {
            $_SESSION['flash_message'] = "Ticket asignado correctamente";
        } else {
            $_SESSION['flash_error'] = "Error al asignar el ticket";
        }
        header("Location: ?page=support&action=view&id=" . $ticket_id);
        exit;
    }
    private function handleCreateTicket() {
        $subject     = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority    = $_POST['priority'] ?? 'Normal';
        $category    = $_POST['category'] ?? '';
        $user_id     = $_SESSION['user_id'] ?? 1; // el admin crea el ticket
        if ($subject === '' || $description === '') {
            $_SESSION['flash_error'] = "Asunto y descripción son obligatorios";
            header("Location: ?page=support&action=create");
            exit;
        }
        $data = [
            'user_id'     => $user_id,
            'subject'     => $subject,
            'description' => $description,
            'priority'    => $priority,
            'category'    => $category,
        ];
        if ($this->ticketSystem->createTicket($data)) {
            $_SESSION['flash_message'] = "Ticket creado correctamente";
        } else {
            $_SESSION['flash_error'] = "Error al crear el ticket";
        }
        header("Location: ?page=support");
        exit;
    }
    private function handleCreateUser() {
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $role       = trim($_POST['role'] ?? 'usuario');
        $academy_id = $_POST['academy_id'] ?? '';
        if ($username === '' || $email === '' || $password === '') {
            $_SESSION['flash_error'] = 'Todos los campos obligatorios deben completarse.';
            header('Location: ?page=users&action=create');
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'El correo electrónico no tiene un formato válido.';
            header('Location: ?page=users&action=create');
            exit();
        }
        $conn = $this->db->getConnection();
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $_SESSION['flash_error'] = 'Ya existe un usuario con ese correo.';
                header('Location: ?page=users&action=create');
                exit();
            }
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmtInsert = $conn->prepare("
                INSERT INTO users (username, email, password, role, academy_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmtInsert->execute([
                $username,
                $email,
                $hashedPassword,
                $role,
                $academy_id !== '' ? $academy_id : null
            ]);
            $_SESSION['flash_message'] = 'Usuario creado correctamente.';
            header('Location: ?page=users');
            exit();
        } catch (PDOException $e) {
            error_log('Error creando usuario: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'No se pudo crear el usuario. Inténtalo de nuevo.';
            header('Location: ?page=users&action=create');
            exit();
        }
    }
    /**
     * Dashboard global Learnnect.
     * SQL: estadísticas básicas globales.
     */
    private function renderDashboard() {
        $conn = $this->db->getConnection();
        $stats = [
            'total_users'     => 0,
            'active_plans'    => 0,
            'open_tickets'    => 0,
            'total_academies' => 0
        ];
        try {
            // Usuarios totales (sin baja lógica)
            $stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
            $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            // Centros con plan activo
            $stmt = $conn->query("
                SELECT COUNT(DISTINCT academy_id) as total 
                FROM academy_plan 
                WHERE ends_at > NOW() OR ends_at IS NULL
            ");
            $stats['active_plans'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $stats['open_tickets'] = $this->ticketSystem->getOpenTicketCount();
            // Total de academies (tenants)
            $stmt = $conn->query("SELECT COUNT(*) as total FROM academies");
            $stats['total_academies'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch(PDOException $e) {
            // se mantienen los valores por defecto
        }
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Resumen general</h1>
                <p class="page-subtitle">Visión global de la plataforma Learnnect</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline">Actualizar</button>
                <button class="btn btn-outline">Configuración</button>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">U</div>
                <div class="stat-value"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Usuarios totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">C</div>
                <div class="stat-value"><?= $stats['total_academies'] ?></div>
                <div class="stat-label">Centros educativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">P</div>
                <div class="stat-value"><?= $stats['active_plans'] ?></div>
                <div class="stat-label">Planes activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">T</div>
                <div class="stat-value"><?= $stats['open_tickets'] ?></div>
                <div class="stat-label">Tickets abiertos</div>
            </div>
        </div>
        <div class="main-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Tickets recientes</h2>
                    <a href="?page=support" class="btn btn-outline">Ver todos</a>
                </div>
                <div class="card-body">
                    <?php $recentTickets = $this->ticketSystem->getRecentTickets(); ?>
                    <?php if (!empty($recentTickets)): ?>
                        <div class="list-block">
                            <?php foreach($recentTickets as $ticket): ?>
                                <?php
                                $status = strtolower($ticket['status']);
                                $status_badge = 'badge-secondary';
                                if (in_array($status, ['abierto','open'])) {
                                    $status_badge = 'badge-warning';
                                } elseif (in_array($status, ['cerrado','closed'])) {
                                    $status_badge = 'badge-success';
                                } elseif (in_array($status, ['en_progreso','in_progress'])) {
                                    $status_badge = 'badge-primary';
                                }
                                ?>
                                <div class="list-row">
                                    <div class="list-main">
                                        <div class="list-title"><?= htmlspecialchars($ticket['subject']) ?></div>
                                        <div class="list-subtitle">
                                            <?= htmlspecialchars($ticket['academy_name'] ?? 'Sistema') ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= $status_badge ?>"><?= htmlspecialchars($ticket['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            No hay tickets recientes
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Última actividad</h2>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-dot success"></div>
                            <div>
                                <div class="activity-title">Nuevo ticket creado</div>
                                <div class="activity-time">Hace 2 horas</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot primary"></div>
                            <div>
                                <div class="activity-title">Usuario registrado</div>
                                <div class="activity-time">Hace 4 horas</div>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-dot warning"></div>
                            <div>
                                <div class="activity-title">Plan actualizado</div>
                                <div class="activity-time">Hace 1 día</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    private function renderSupport($action) {
        switch($action) {
            case 'create':
                $this->renderCreateTicket();
                break;
            case 'view':
                $this->renderTicketDetail();
                break;
            default:
                $this->renderTicketsList();
        }
    }
    /**
     * Lista global de tickets.
     * SQL: TicketSystem::getTickets()
     */
    private function renderTicketsList() {
        $filter  = $_GET['filter'] ?? 'all';
        $search  = $_GET['search'] ?? '';
        $tickets = $this->ticketSystem->getTickets($filter, $search);
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Soporte global</h1>
                <p class="page-subtitle">Gestiona todos los tickets de la plataforma</p>
            </div>
            <a href="?page=support&action=create" class="btn btn-primary">Nuevo ticket</a>
        </div>
        <div class="card filters-card">
            <div class="card-header">
                <h2 class="card-title">Filtros y búsqueda</h2>
            </div>
            <div class="card-body">
                <div class="filters-row">
                    <div class="filters-left">
                        <a href="?page=support&filter=all" class="btn <?= $filter == 'all' ? 'btn-primary' : 'btn-outline' ?>">Todos</a>
                        <a href="?page=support&filter=open" class="btn <?= $filter == 'open' ? 'btn-primary' : 'btn-outline' ?>">Abiertos</a>
                        <a href="?page=support&filter=in_progress" class="btn <?= $filter == 'in_progress' ? 'btn-primary' : 'btn-outline' ?>">En progreso</a>
                        <a href="?page=support&filter=closed" class="btn <?= $filter == 'closed' ? 'btn-primary' : 'btn-outline' ?>">Cerrados</a>
                    </div>
                    <div class="filters-right">
                        <button class="btn btn-outline">Filtros avanzados</button>
                        <button class="btn btn-outline">Exportar</button>
                    </div>
                </div>
                <div class="search-row">
                    <form method="GET" class="search-form">
                        <input type="hidden" name="page" value="support">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar tickets..." class="form-control">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Listado de tickets</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Asunto</th>
                                <th>Centro</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Asignado a</th>
                                <th>Mensajes</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="10" class="empty-cell">
                                        No hay tickets que coincidan con los filtros seleccionados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($tickets as $ticket): ?>
                                    <?php
                                        $status = strtolower($ticket['status']);
                                        $status_badge = 'badge-secondary';
                                        if (in_array($status, ['abierto','open'])) {
                                            $status_badge = 'badge-warning';
                                        } elseif (in_array($status, ['cerrado','closed'])) {
                                            $status_badge = 'badge-success';
                                        } elseif (in_array($status, ['en_progreso','in_progress'])) {
                                            $status_badge = 'badge-primary';
                                        } elseif (in_array($status, ['en_espera','on_hold'])) {
                                            $status_badge = 'badge-secondary';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong>#<?= $ticket['id'] ?></strong></td>
                                        <td>
                                            <div class="cell-title"><?= htmlspecialchars($ticket['subject']) ?></div>
                                            <div class="cell-subtitle">
                                                por <?= htmlspecialchars($ticket['created_by_name'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($ticket['academy_name'] ?? 'Sistema') ?></td>
                                        <td>
                                            <?php if (!empty($ticket['category'])): ?>
                                                <span class="badge badge-secondary"><?= htmlspecialchars($ticket['category']) ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $status_badge ?>"><?= htmlspecialchars($ticket['status']) ?></span></td>
                                        <td><span class="badge badge-primary"><?= ucfirst(htmlspecialchars($ticket['priority'])) ?></span></td>
                                        <td>
                                            <?php if (!empty($ticket['assigned_to_name'])): ?>
                                                <span class="badge badge-primary"><?= htmlspecialchars($ticket['assigned_to_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-info"><?= $ticket['message_count'] ?? 0 ?></span></td>
                                        <td><?= date('d/m/Y', strtotime($ticket['created_at'])) ?></td>
                                        <td>
                                            <a href="?page=support&action=view&id=<?= $ticket['id'] ?>" class="btn btn-outline btn-small">Ver</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Detalle de ticket + hilo de conversación.
     */
    private function renderTicketDetail() {
        $ticket_id = (int)($_GET['id'] ?? 0);
        $ticket    = $this->ticketSystem->getTicket($ticket_id);
        if (!$ticket) {
            echo '<div class="alert alert-error">Ticket no encontrado</div>';
            return;
        }
        $messages = $this->ticketSystem->getTicketMessages($ticket_id);
        $conn     = $this->db->getConnection();
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Ticket #<?= $ticket_id ?> · <?= htmlspecialchars($ticket['subject']) ?></h1>
                <p class="page-subtitle">
                    <?= htmlspecialchars($ticket['academy_name'] ?? 'Sistema') ?> · 
                    Creado por <?= htmlspecialchars($ticket['created_by_name'] ?? '') ?> · 
                    <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                </p>
            </div>
            <div class="page-actions">
                <a href="?page=support" class="btn btn-outline">Volver a tickets</a>
            </div>
        </div>
        <div class="detail-grid">
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Detalles del ticket</h2>
                    </div>
                    <div class="card-body">
                        <div class="ticket-meta">
                            <div>
                                <span class="meta-label">Estado</span><br>
                                <?php
                                $status = strtolower($ticket['status']);
                                $status_badge = 'badge-secondary';
                                if (in_array($status, ['abierto','open'])) {
                                    $status_badge = 'badge-warning';
                                } elseif (in_array($status, ['cerrado','closed'])) {
                                    $status_badge = 'badge-success';
                                } elseif (in_array($status, ['en_progreso','in_progress'])) {
                                    $status_badge = 'badge-primary';
                                } elseif (in_array($status, ['en_espera','on_hold'])) {
                                    $status_badge = 'badge-secondary';
                                }
                                ?>
                                <span class="badge <?= $status_badge ?>"><?= htmlspecialchars($ticket['status']) ?></span>
                            </div>
                            <div>
                                <span class="meta-label">Asignado a</span><br>
                                <?php if(!empty($ticket['assigned_to_name'])): ?>
                                    <span class="badge badge-primary"><?= htmlspecialchars($ticket['assigned_to_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Sin asignar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if($ticket['description']): ?>
                            <div class="ticket-section">
                                <h3 class="section-title">Descripción</h3>
                                <p class="ticket-description"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if(!empty($ticket['category'])): ?>
                            <div class="ticket-section">
                                <span class="meta-label">Categoría</span><br>
                                <span class="badge badge-secondary"><?= htmlspecialchars($ticket['category']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Conversación</h2>
                    </div>
                    <div class="card-body">
                        <div class="conversation-list">
                            <?php foreach($messages as $message): ?>
                                <div class="message-row">
                                    <div class="avatar-circle">
                                        <?= strtoupper(substr($message['username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div class="message-main">
                                        <div class="message-header">
                                            <strong><?= htmlspecialchars($message['username'] ?? 'Usuario') ?></strong>
                                            <span class="activity-time">
                                                <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="message-body <?= $message['is_internal'] ? 'message-internal' : '' ?>">
                                            <?php if($message['is_internal']): ?>
                                                <em class="internal-label">[Mensaje interno]</em><br>
                                            <?php endif; ?>
                                            <?= nl2br(htmlspecialchars($message['message'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($messages)): ?>
                                <div class="empty-state small">
                                    Aún no hay mensajes en este ticket.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <form method="POST" class="reply-form">
                    <input type="hidden" name="action" value="reply_ticket">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Responder al ticket</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Mensaje</label>
                                <textarea class="form-control" name="message" rows="4" placeholder="Escribe tu respuesta..." required></textarea>
                            </div>
                            <div class="reply-footer">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_internal" value="1">
                                    <span>Mensaje interno (solo equipo de soporte)</span>
                                </label>
                                <button type="submit" class="btn btn-primary">Enviar respuesta</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div>
                <form method="POST" class="side-form">
                    <input type="hidden" name="action" value="update_ticket_status">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Estado del ticket</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="abierto"     <?= in_array(strtolower($ticket['status']), ['abierto','open']) ? 'selected' : '' ?>>Abierto</option>
                                    <option value="en_progreso" <?= in_array(strtolower($ticket['status']), ['en_progreso','en progreso','in_progress']) ? 'selected' : '' ?>>En progreso</option>
                                    <option value="en_espera"   <?= in_array(strtolower($ticket['status']), ['en_espera','en espera','on_hold']) ? 'selected' : '' ?>>En espera</option>
                                    <option value="cerrado"     <?= in_array(strtolower($ticket['status']), ['cerrado','closed']) ? 'selected' : '' ?>>Cerrado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
                <form method="POST" class="side-form">
                    <input type="hidden" name="action" value="assign_ticket">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Asignar ticket</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Asignar a</label>
                                <select class="form-control" name="assigned_to">
                                    <option value="">Sin asignar</option>
                                    <?php
                                    try {
                                        // SQL: usuarios admin/support (global NOC)
                                        $stmt = $conn->query("
                                            SELECT id, username, email 
                                            FROM users 
                                            WHERE role IN ('admin','support')
                                        ");
                                        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach($admins as $admin):
                                    ?>
                                        <option value="<?= $admin['id'] ?>" <?= ($ticket['assigned_to'] ?? 0) == $admin['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($admin['username']) ?> (<?= htmlspecialchars($admin['email']) ?>)
                                        </option>
                                    <?php
                                        endforeach;
                                    } catch(PDOException $e) {
                                        echo '<option value="">Error al cargar admins</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary full-width">Asignar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    /**
     * Wizard simple de creación de ticket manual desde NOC.
     */
    private function renderCreateTicket() {
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Crear nuevo ticket</h1>
                <p class="page-subtitle">Registra una solicitud de soporte global</p>
            </div>
            <a href="?page=support" class="btn btn-outline">Volver</a>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Datos del ticket</h2>
            </div>
            <div class="card-body">
                <form method="POST" class="form-layout">
                    <input type="hidden" name="action" value="create_ticket">
                    <div class="form-group">
                        <label class="form-label">Asunto *</label>
                        <input type="text" class="form-control" name="subject" placeholder="Asunto del ticket..." required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoría</label>
                        <select class="form-control" name="category">
                            <option value="">Seleccionar categoría...</option>
                            <option value="Soporte Técnico">Soporte Técnico</option>
                            <option value="Facturación y Licencias">Facturación y Licencias</option>
                            <option value="Seguridad e Infraestructura">Seguridad e Infraestructura</option>
                            <option value="Solicitud de Funcionalidad">Solicitud de Funcionalidad</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción detallada *</label>
                        <textarea class="form-control" name="description" rows="5" placeholder="Describe el problema en detalle..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioridad</label>
                        <select class="form-control" name="priority">
                            <option value="Baja">Baja</option>
                            <option value="Normal" selected>Normal</option>
                            <option value="Alta">Alta</option>
                            <option value="Crítica">Crítica</option>
                        </select>
                    </div>
                    <div class="form-footer">
                        <a href="?page=support" class="btn btn-outline">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Crear ticket</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    /**
     * Sección Tenants (academies).
     * A futuro: aquí conectaremos el wizard multistep de creación de tenant.
     */
    private function renderTenants($action) {
        switch($action) {
            case 'create':
                $this->renderCreateTenant();
                break;
            default:
                $this->renderTenantsList();
        }
    }
    /**
     * Listado global de centros (tenants).
     * SQL: academies + academy_plan + plans + conteo de users por tenant
     */
    private function renderTenantsList() {
        $conn    = $this->db->getConnection();
        $tenants = [];
        try {
            $stmt = $conn->query("
                SELECT a.*, p.name as plan_name,
                       (
                           SELECT COUNT(*) 
                           FROM users u 
                           WHERE u.academy_id = a.id 
                             AND u.deleted_at IS NULL
                       ) as user_count
                FROM academies a
                LEFT JOIN academy_plan ap ON a.id = ap.academy_id
                LEFT JOIN plans p        ON ap.plan_id = p.id
                ORDER BY a.created_at DESC
            ");
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo '<div style="text-align:center;padding:20px;color:var(--secondary);">Error al cargar los centros</div>';
        }
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Centros educativos</h1>
                <p class="page-subtitle">Gestión de tenants (academias, colegios, universidades)</p>
            </div>
            <a href="?page=tenants&action=create" class="btn btn-primary">Nuevo centro</a>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Listado de centros</h2>
                <div class="card-header-actions">
                    <button class="btn btn-outline">Exportar</button>
                    <button class="btn btn-outline">Filtros</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Centro</th>
                                <th>Subdominio</th>
                                <th>Email contacto</th>
                                <th>Plan</th>
                                <th>Usuarios</th>
                                <th>Fecha alta</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tenants as $tenant): ?>
                                <tr>
                                    <td>
                                        <div class="tenant-cell">
                                            <?php if($tenant['logo_url']): ?>
                                                <img src="<?= htmlspecialchars($tenant['logo_url']) ?>" alt="Logo" class="tenant-logo">
                                            <?php else: ?>
                                                <div class="avatar-square">
                                                    <?= strtoupper(substr($tenant['name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="cell-title"><?= htmlspecialchars($tenant['name']) ?></div>
                                                <div class="cell-subtitle"><?= htmlspecialchars($tenant['contact_email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars(iu_academy_subdomain($tenant['name'])) ?></td>
                                    <td><?= htmlspecialchars($tenant['contact_email'] ?? '') ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($tenant['plan_name'] ?? 'Sin plan') ?></span></td>
                                    <td><span class="badge badge-info"><?= $tenant['user_count'] ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($tenant['created_at'])) ?></td>
                                    <td><span class="badge badge-success">Activo</span></td>
                                    <td>
                                        <div class="actions-inline">
                                            <a href="?page=tenants&action=edit&id=<?= $tenant['id'] ?>" class="btn btn-outline btn-small">Gestionar</a>
                                            <a href="?page=tenants&action=delete&id=<?= $tenant['id'] ?>" class="btn btn-outline btn-small btn-danger">Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tenants)): ?>
                                <tr>
                                    <td colspan="8" class="empty-cell">No hay centros dados de alta.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Wizard visual (multi-paso en UI) para creación de un nuevo tenant.
     * A nivel de servidor, por ahora es un POST único (se puede extender a multi-step real).
     */
    private function renderCreateTenant() {
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Crear nuevo centro</h1>
                <p class="page-subtitle">Agrega un nuevo centro educativo a la plataforma Learnnect</p>
            </div>
            <a href="?page=tenants" class="btn btn-outline">Volver</a>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Datos básicos del centro</h2>
            </div>
            <div class="card-body">
                <!-- A FUTURO: este formulario se convertirá en wizard multi-step real con JS/POST -->
                <form class="form-layout">
                    <div class="form-group">
                        <label class="form-label">Nombre del centro *</label>
                        <input type="text" class="form-control" placeholder="Nombre completo del centro...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email de contacto *</label>
                        <input type="email" class="form-control" placeholder="contacto@centro.edu">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" placeholder="+34 000 000 000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dirección</label>
                        <textarea class="form-control" rows="3" placeholder="Dirección completa del centro..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Logo (opcional)</label>
                        <input type="file" class="form-control">
                    </div>
                    <div class="form-footer">
                        <a href="?page=tenants" class="btn btn-outline">Cancelar</a>
                        <button type="button" class="btn btn-primary">Continuar (wizard)</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    /**
     * Gestión global de usuarios (NOC).
     * SQL: SELECT users + academies
     */
    private function renderUsers($action) {
        if ($action === 'create') {
            $this->renderUserCreateForm();
            return;
        }
        $conn  = $this->db->getConnection();
        $users = [];
        try {
            $stmt = $conn->query("
                SELECT u.*, a.name as academy_name
                FROM users u
                LEFT JOIN academies a ON u.academy_id = a.id
                ORDER BY u.created_at DESC
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo '<div style="text-align:center;padding:20px;color:var(--secondary);">Error al cargar los usuarios</div>';
        }
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestión de usuarios</h1>
                <p class="page-subtitle">Administra todos los usuarios de la plataforma</p>
            </div>
            <a href="?page=users&action=create" class="btn btn-primary">Nuevo usuario</a>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Listado de usuarios</h2>
                <div class="card-header-actions">
                    <button class="btn btn-outline">Exportar</button>
                    <button class="btn btn-outline">Filtros</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Centro</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar-circle">
                                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="cell-title"><?= htmlspecialchars($user['username']) ?></div>
                                                <div class="cell-subtitle">ID: <?= $user['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['academy_name'] ?? 'Sistema') ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($user['role']) ?></span></td>
                                    <td>
                                        <?php
                                            $status = $user['status'] ?? 'active';
                                            $status_normalized = strtolower($status);
                                            $badge = 'badge-secondary';
                                            $label = $status;
                                            if (in_array($status_normalized, ['active', 'activo'])) {
                                                $badge = 'badge-success';
                                                $label = 'Activo';
                                            } elseif (in_array($status_normalized, ['blocked', 'bloqueado'])) {
                                                $badge = 'badge-danger';
                                                $label = 'Bloqueado';
                                            } elseif (in_array($status_normalized, ['pending', 'pendiente'])) {
                                                $badge = 'badge-warning';
                                                $label = 'Pendiente';
                                            }
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($label) ?></span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="actions-inline">
                                            <a href="?page=users&action=edit&id=<?= $user['id'] ?>" class="btn btn-outline btn-small">Editar</a>
                                            <a href="?page=users&action=delete&id=<?= $user['id'] ?>" class="btn btn-outline btn-small btn-danger">Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="7" class="empty-cell">No hay usuarios registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    private function renderUserCreateForm() {
        $conn = $this->db->getConnection();
        $academies = [];
        try {
            $stmt = $conn->query("SELECT id, name FROM academies ORDER BY name ASC");
            $academies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error cargando academias: ' . $e->getMessage());
        }
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Crear nuevo usuario</h1>
                <p class="page-subtitle">Define los datos básicos del usuario y su rol dentro de la plataforma.</p>
            </div>
            <a href="?page=users" class="btn btn-outline">Volver al listado</a>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Información del usuario</h2>
            </div>
            <div class="card-body">
                <form method="POST" class="form">
                    <input type="hidden" name="action" value="create_user">
                    <div class="form-grid">
                        <div class="form-control-group">
                            <label class="form-label">Nombre completo *</label>
                            <input type="text" name="username" class="form-control" placeholder="Ej: Laura Gómez" required>
                        </div>
                        <div class="form-control-group">
                            <label class="form-label">Correo electrónico *</label>
                            <input type="email" name="email" class="form-control" placeholder="usuario@centro.edu" required>
                        </div>
                        <div class="form-control-group">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" name="password" class="form-control" placeholder="Asigna una contraseña inicial" required>
                        </div>
                        <div class="form-control-group">
                            <label class="form-label">Rol del usuario</label>
                            <select name="role" class="form-control">
                                <option value="Administrador">Administrador</option>
                                <option value="Support">Support</option>
                                <option value="Usuario">Usuario</option>
                            </select>
                        </div>
                        <div class="form-control-group full-width">
                            <label class="form-label">Centro educativo (opcional)</label>
                            <select name="academy_id" class="form-control">
                                <option value="">Usuario global / sistema</option>
                                <?php foreach ($academies as $academy): ?>
                                    <option value="<?= $academy['id'] ?>"><?= htmlspecialchars($academy['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-footer">
                        <a href="?page=users" class="btn btn-outline">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Crear usuario</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    /**
     * Vista de actividad / auditoría global.
     * En esta primera versión, usamos users_logs como fuente principal.
     *
     * SQL:
     *  SELECT ul.*, a.name AS academy_name, u.username
     *  FROM users_logs ul
     *  LEFT JOIN users u ON ul.user_id = u.id
     *  LEFT JOIN academies a ON u.academy_id = a.id
     *  ORDER BY ul.timestamp DESC
     */
    private function renderActivityLog() {
        $conn = $this->db->getConnection();
        $logs = [];
        try {
            $stmt = $conn->query("
                SELECT ul.*, a.name as academy_name, u.username
                FROM users_logs ul
                LEFT JOIN users u ON ul.user_id = u.id
                LEFT JOIN academies a ON u.academy_id = a.id
                ORDER BY ul.timestamp DESC
                LIMIT 50
            ");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo '<div class="alert alert-error">Error al cargar la actividad del sistema</div>';
        }
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Registro de actividad</h1>
                <p class="page-subtitle">Historial de eventos del sistema</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline">Filtrar</button>
                <button class="btn btn-outline">Exportar</button>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Actividad reciente</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Centro</th>
                                <th>Acción</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($log['academy_name'] ?? 'Sistema') ?></strong></td>
                                    <td><?= htmlspecialchars($log['action']) ?></td>
                                    <td><?= htmlspecialchars($log['username'] ?? 'Sistema') ?></td>
                                    <td><span class="badge badge-success">Completado</span></td>
                                    <td><?= date('d/m/Y H:i', strtotime($log['timestamp'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="empty-cell">No hay actividad registrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Configuración global Learnnect (branding, idioma, etc.).
     * A futuro aquí conectaremos también SMTP global, límites por defecto, admin_notifications, etc.
     */
    private function renderSettings() {
        ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Configuración del sistema</h1>
                <p class="page-subtitle">Ajustes generales de Learnnect</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Configuración general</h2>
            </div>
            <div class="card-body">
                <form class="form-layout">
                    <div class="form-group">
                        <label class="form-label">Nombre de la plataforma</label>
                        <input type="text" class="form-control" value="Learnnect">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email de contacto</label>
                        <input type="email" class="form-control" value="contacto@iuconnect.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Idioma predeterminado</label>
                        <select class="form-control">
                            <option>Español</option>
                            <option>English</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Zona horaria</label>
                        <select class="form-control">
                            <option>(GMT+01:00) Madrid</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar configuración</button>
                </form>
            </div>
        </div>
        <?php
    }
}
/**
 * Instanciamos y manejamos POST ANTES de sacar HTML
 */
$db = new Database();
$controller = new AdminController($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handlePostActions();
    exit;
}
if (($_GET['page'] ?? '') === 'support' && ($_GET['action'] ?? '') === 'sync_background') {
    $controller->handleAsyncSupportSync();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de administración Learnnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #f5f6fb;
            --surface: #ffffff;
            --surface-soft: #f8f9fc;
            --surface-strong: #fbfcfe;
            --text: #0f1a25;
            --muted: #606a7b;
            --muted-2: #808a9b;
            --border: #e2e5ed;
            --border-strong: #d1d6e2;
            --shadow: 0 2px 8px rgba(0,0,0,0.08), 0 8px 24px rgba(0,0,0,0.12);
            --shadow-hover: 0 4px 12px rgba(0,0,0,0.15);
            --sidebar: #050505;
            --header: #050505;
            --accent: #101828;
            --accent-hover: #182135;
            --accent-active: #0c131f;
            --accent-soft: #1a2336;
            --success: #107c10;
            --success-bg: #dff6dd;
            --warning: #ffaa44;
            --warning-bg: #fff4ce;
            --danger: #d73b02;
            --info: #0078d4;
            --info-bg: #c7e0f4;
            --divider: #eaebed;
            --highlight: #f3f2f1;
        }
        * { box-sizing:border-box; margin:0; padding:0; }
        body {
            margin:0;
            background:var(--bg);
            color:var(--text);
            font-family:'Segoe UI', 'Manrope', system-ui, -apple-system, sans-serif;
            min-height:100vh;
            display:flex;
            overflow-x:hidden;
        }
        a { color:var(--accent); text-decoration:none; }
        img { max-width:100%; display:block; }
        ul, ol { list-style:none; }
        /* LAYOUT */
        .sidebar {
            width:260px;
            min-height:100vh;
            background:var(--sidebar);
            color:#f5f6f8;
            position:fixed;
            top:0;
            left:0;
            z-index:50;
            border-right:1px solid #0d0d0d;
            box-shadow: 1px 0 10px rgba(0,0,0,0.2);
            display:flex;
            flex-direction:column;
            transition:transform .3s ease;
        }
        .sidebar.collapsed { width:70px; }
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .nav-title,
        .sidebar.collapsed .tenant-name,
        .sidebar.collapsed .tenant-subdomain {
            display:none;
        }
        .sidebar-header {
            padding:16px 20px;
            border-bottom:1px solid #1c1c1c;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }
        .logo {
            display:flex;
            align-items:center;
            gap:12px;
            text-decoration:none;
            color:#f8fafc;
        }
        .logo-icon {
            width:32px;
            height:32px;
            border-radius:8px;
            background:#f5f5f5;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#050505;
            font-weight:800;
            font-size:18px;
        }
        .logo-text {
            font-size:18px;
            font-weight:700;
            letter-spacing:-0.5px;
        }
        .tenant-info {
            padding:16px 20px 12px;
            border-bottom:1px solid #1c1c1c;
        }
        .tenant-name {
            font-weight:600;
            font-size:14px;
            color:#f8f8f8;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .tenant-subdomain {
            font-size:12px;
            color:rgba(255,255,255,0.6);
            margin-top:2px;
        }
        .nav-section {
            padding:4px 0;
            margin-bottom:4px;
        }
        .nav-title {
            font-size:10px;
            font-weight:600;
            color:rgba(255,255,255,0.6);
            padding:8px 20px 6px;
            text-transform:uppercase;
            letter-spacing:1px;
        }
        .nav-item {
            display:flex;
            align-items:center;
            padding:10px 20px;
            color:rgba(255,255,255,0.85);
            text-decoration:none;
            font-size:14px;
            transition:all .2s ease;
            position:relative;
        }
        .nav-item:hover {
            background:rgba(255,255,255,0.1);
            color:#ffffff;
        }
        .nav-item.active {
            background:rgba(255,255,255,0.12);
            color:#ffffff;
            font-weight:500;
        }
        .nav-item.active::before {
            content:'';
            position:absolute;
            left:0;
            top:0;
            bottom:0;
            width:3px;
            background:#ffffff;
        }
        .nav-icon {
            width:28px;
            height:28px;
            border-radius:6px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            margin-right:14px;
            font-size:14px;
        }
        .nav-icon i {
            width:16px;
            height:16px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:14px;
        }
        .main-content {
            flex:1;
            margin-left:260px;
            display:flex;
            flex-direction:column;
            min-height:100vh;
            transition:margin-left .3s ease;
        }
        .sidebar.collapsed + .main-content {
            margin-left:70px;
        }
        .header {
            background:var(--header);
            height:56px;
            padding:0 24px;
            box-shadow:0 1px 4px rgba(0,0,0,0.14);
            display:flex;
            align-items:center;
            z-index:40;
            position:sticky;
            top:0;
            border-bottom:1px solid #0d0d0d;
            color:#f8fafc;
        }
        .header-left {
            display:flex;
            align-items:center;
            flex:1;
            gap:16px;
        }
        .menu-toggle {
            background:transparent;
            border:1px solid rgba(255,255,255,0.2);
            color:#f8fafc;
            font-size:16px;
            cursor:pointer;
            width:32px;
            height:32px;
            border-radius:6px;
            display:flex;
            align-items:center;
            justify-content:center;
            transition:all .2s ease;
            flex-shrink:0;
        }
        .menu-toggle:hover {
            border-color:#ffffff;
            color:#ffffff;
            background:rgba(255,255,255,0.1);
        }
        .search-box {
            background:rgba(23,23,23,0.7);
            border:1px solid rgba(255,255,255,0.15);
            border-radius:8px;
            color:#f8fafc;
            padding:6px 12px;
            width:100%;
            max-width:480px;
            font-family:'Segoe UI', sans-serif;
            font-size:14px;
            transition:border-color .2s ease;
        }
        .search-box:focus {
            outline:none;
            border-color:#555555;
            background:rgba(23,23,23,0.9);
        }
        .search-box::placeholder {
            color:rgba(248,250,252,0.65);
        }
        .header-center {
            flex:1;
            max-width:600px;
            margin:0 24px;
        }
        .user-menu {
            display:flex;
            align-items:center;
            gap:16px;
            color:#f8fafc;
            margin-left:auto;
        }
        .notification-bell {
            position:relative;
            color:#f8fafc;
            cursor:pointer;
            font-size:18px;
            transition:color .2s ease;
        }
        .notification-bell:hover {
            color:#ffffff;
        }
        .notification-badge {
            position:absolute;
            top:-5px;
            right:-5px;
            background:#ff5a5a;
            color:white;
            font-size:10px;
            width:16px;
            height:16px;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:bold;
        }
        .tenant-switcher {
            position:relative;
            display:flex;
            align-items:center;
            gap:8px;
            cursor:pointer;
            padding:4px 8px;
            border-radius:6px;
            transition:background .2s ease;
        }
        .tenant-switcher:hover {
            background:rgba(255,255,255,0.1);
        }
        .tenant-avatar {
            width:28px;
            height:28px;
            border-radius:50%;
            background:#3a3a3a;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:600;
            font-size:12px;
            color:#c5c5c5;
            flex-shrink:0;
        }
        .tenant-dropdown {
            position:absolute;
            top:100%;
            right:0;
            background:var(--surface);
            border-radius:8px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            min-width:280px;
            z-index:100;
            border:1px solid var(--border);
            overflow:hidden;
            display:none;
            margin-top:4px;
        }
        .tenant-switcher:hover .tenant-dropdown {
            display:block;
        }
        .tenant-dropdown-header {
            padding:12px 16px;
            border-bottom:1px solid var(--border);
            font-weight:600;
            color:var(--text);
            background:var(--surface-soft);
        }
        .tenant-list {
            max-height:350px;
            overflow-y:auto;
        }
        .tenant-item {
            padding:10px 16px;
            display:flex;
            align-items:center;
            gap:10px;
            transition:all .15s ease;
            cursor:pointer;
            border-bottom:1px solid var(--border);
        }
        .tenant-item:hover {
            background:var(--highlight);
        }
        .tenant-item.active {
            background:var(--accent);
            color:white;
        }
        .tenant-item.active .tenant-avatar {
            background:#555555;
        }
        .tenant-item:last-child {
            border-bottom:none;
        }
        .tenant-name-dropdown {
            font-weight:500;
            font-size:14px;
            color:var(--text);
        }
        .tenant-subdomain-dropdown {
            font-size:12px;
            color:var(--muted);
            margin-top:2px;
        }
        .user-avatar {
            width:32px;
            height:32px;
            border-radius:50%;
            background:#3a3a3a;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#c5c5c5;
            font-weight:600;
            font-size:14px;
            cursor:pointer;
            transition:all .2s ease;
        }
        .user-menu:hover .user-avatar {
            background:#4a4a4a;
        }
        .content {
            flex:1;
            padding:24px 32px;
            background:var(--bg);
            overflow-y:auto;
            min-height:calc(100vh - 56px);
        }
        /* PAGE HEADER */
        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
            margin-bottom:24px;
            padding-bottom:12px;
            border-bottom:1px solid var(--border);
        }
        .page-title {
            font-size:24px;
            font-weight:600;
            color:var(--text);
            margin-bottom:4px;
        }
        .page-subtitle {
            font-size:14px;
            color:var(--muted);
        }
        .page-actions { display:flex; gap:12px; }
        /* BUTTONS */
        .btn {
            padding:8px 16px;
            border-radius:4px;
            border:1px solid var(--border);
            font-weight:500;
            cursor:pointer;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:14px;
            background:var(--surface);
            transition:all .15s ease;
            color:var(--text);
            gap:6px;
            box-shadow:0 1px 3px rgba(0,0,0,0.08);
        }
        .btn:hover {
            transform:translateY(-1px);
            box-shadow:0 2px 4px rgba(0,0,0,0.12);
            border-color:var(--border-strong);
        }
        .btn:active {
            transform:translateY(0);
            box-shadow:0 1px 2px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background:var(--accent);
            border-color:var(--accent);
            color:#ffffff;
            box-shadow:0 1px 3px rgba(0,0,0,0.15);
        }
        .btn-primary:hover {
            background:var(--accent-hover);
            border-color:var(--accent-hover);
            box-shadow:0 2px 6px rgba(0,0,0,0.2);
        }
        .btn-primary:active {
            background:var(--accent-active);
            border-color:var(--accent-active);
            box-shadow:0 1px 3px rgba(0,0,0,0.15);
        }
        .btn-outline {
            border-color:var(--border);
            background:var(--surface);
            color:var(--text);
        }
        .btn-small {
            padding:6px 12px;
            font-size:13px;
            border-radius:4px;
        }
        .btn-danger {
            background:var(--danger);
            border-color:var(--danger);
            color:#ffffff;
        }
        .btn-danger:hover {
            background:#c73500;
            border-color:#c73500;
        }
        .btn-group { display:flex; gap:12px; flex-wrap:wrap; }
        .full-width { width:100%; }
        /* CARDS */
        .card {
            background:var(--surface);
            border-radius:4px;
            box-shadow:var(--shadow);
            margin-bottom:24px;
            border:1px solid var(--border);
            transition:box-shadow .2s ease;
        }
        .card:hover {
            box-shadow:var(--shadow-hover);
        }
        .card-header {
            padding:16px 20px;
            border-bottom:1px solid var(--border);
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .card-title {
            font-size:16px;
            font-weight:600;
            color:var(--text);
        }
        .card-body {
            padding:20px;
        }
        .card-header-actions {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        /* TABLES */
        .table-container {
            border-radius:4px;
            overflow:hidden;
            border:1px solid var(--border);
            box-shadow:0 1px 3px rgba(0,0,0,0.05);
        }
        .table {
            width:100%;
            border-collapse:collapse;
            background:transparent;
            font-size:14px;
        }
        .table th,
        .table td {
            padding:12px 16px;
            text-align:left;
            border-bottom:1px solid var(--border);
            color:var(--text);
        }
        .table th {
            background:var(--surface-soft);
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.5px;
            font-size:12px;
            color:var(--muted);
        }
        .table tr:last-child td {
            border-bottom:none;
        }
        .table tr:hover td {
            background:var(--highlight);
        }
        .empty-cell {
            text-align:center;
            padding:40px;
            color:var(--muted-2);
            font-style:italic;
        }
        .cell-title {
            font-weight:600;
            color:var(--text);
        }
        .cell-subtitle {
            font-size:13px;
            color:var(--muted);
            margin-top:2px;
        }
        /* BADGES */
        .badge {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:4px 10px;
            border-radius:4px;
            font-size:12px;
            font-weight:600;
            border:1px solid transparent;
            letter-spacing:0.2px;
            min-width:70px;
            text-align:center;
        }
        .badge-primary {
            background-color: #e8e8e8;
            color: var(--accent);
            border-color: #d0d0d0;
        }
        .badge-success {
            background-color: var(--success-bg);
            color: var(--success);
            border-color: #a8d194;
        }
        .badge-warning {
            background-color: var(--warning-bg);
            color: #8a5e01;
            border-color: #f4c84e;
        }
        .badge-danger {
            background-color: #fde7e7;
            color: var(--danger);
            border-color: #f7b8ae;
        }
        .badge-info {
            background-color: var(--info-bg);
            color: var(--info);
            border-color: #8cbce4;
        }
        .badge-secondary {
            background-color: #f3f2f1;
            color: #323130;
            border-color: #edebe9;
        }
        /* FORMS */
        .form-group {
            margin-bottom:16px;
        }
        .form-label {
            display:block;
            margin-bottom:6px;
            font-weight:500;
            font-size:14px;
            color:var(--text);
        }
        .form-control {
            width:100%;
            padding:8px 12px;
            border-radius:4px;
            border:1px solid var(--border);
            font-size:14px;
            background:var(--surface-strong);
            color:var(--text);
            transition:all .15s ease;
            height:36px;
        }
        .form-control:focus {
            outline:none;
            border-color:var(--accent);
            box-shadow:0 0 0 2px rgba(16,24,40,0.2);
        }
        .form-control.textarea {
            height:auto;
            min-height:100px;
            resize:vertical;
        }
        .form-layout {
            max-width:720px;
        }
        .form-footer {
            display:flex;
            justify-content:flex-end;
            gap:12px;
            margin-top:20px;
            padding-top:16px;
            border-top:1px solid var(--border);
        }
        .filters-card {
            margin-bottom:24px;
        }
        .filters-row {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            flex-wrap:wrap;
        }
        .filters-left {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-bottom:16px;
        }
        .filters-right {
            display:flex;
            gap:12px;
            margin-bottom:16px;
        }
        .search-row {
            margin-top:12px;
        }
        .search-form {
            display:flex;
            gap:12px;
            align-items:flex-start;
            flex-wrap:wrap;
        }
        /* ALERTS */
        .alert {
            padding:12px 16px;
            border-radius:4px;
            margin-bottom:20px;
            border:1px solid var(--border);
            font-size:14px;
            background:var(--surface);
            display:flex;
            align-items:center;
            gap:12px;
        }
        .alert-success {
            background:var(--success-bg);
            border-color:#a8d194;
            color:var(--success);
        }
        .alert-error {
            background:#ffecec;
            border-color:#f7b8ae;
            color:var(--danger);
        }
        .alert-icon {
            font-size:18px;
            flex-shrink:0;
        }
        /* STATS */
        .stats-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
            gap:20px 16px;
            margin-bottom:28px;
        }
        .stat-card {
            background:var(--surface);
            border-radius:4px;
            padding:20px;
            border:1px solid var(--border);
            box-shadow:var(--shadow);
            transition:all .2s ease;
        }
        .stat-card:hover {
            box-shadow:var(--shadow-hover);
            transform:translateY(-1px);
        }
        .stat-content {
            display:flex;
            align-items:center;
            justify-content:space-between;
        }
        .stat-info {
            flex:1;
        }
        .stat-icon-container {
            width:48px;
            height:48px;
            border-radius:8px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin-right:16px;
            flex-shrink:0;
            font-size:20px;
        }
        .stat-icon {
            font-size:20px;
        }
        .stat-users { background-color: rgba(0, 120, 212, 0.1); color: var(--info); }
        .stat-academies { background-color: rgba(255, 169, 68, 0.1); color: #ffaa44; }
        .stat-plans { background-color: rgba(116, 7, 187, 0.1); color: #740bbb; }
        .stat-tickets { background-color: rgba(175, 0, 42, 0.1); color: #af002a; }
        .stat-value {
            font-size:28px;
            font-weight:700;
            margin:4px 0;
            color:var(--text);
        }
        .stat-label {
            font-size:14px;
            color:var(--muted);
            font-weight:500;
        }
        .main-grid {
            display:grid;
            grid-template-columns:1fr;
            gap:24px;
            margin-top:16px;
        }
        @media (min-width:960px) {
            .main-grid {
                grid-template-columns:2fr 1fr;
            }
        }
        /* LISTS */
        .list-block {
            display:flex;
            flex-direction:column;
            gap:16px;
        }
        .list-row {
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:12px 0;
            border-bottom:1px solid var(--border);
        }
        .list-row:last-child {
            border-bottom:none;
        }
        .list-main {
            flex:1;
        }
        .list-title {
            font-weight:600;
            font-size:15px;
            color:var(--text);
        }
        .list-subtitle {
            font-size:13px;
            color:var(--muted);
            margin-top:3px;
        }
        .empty-state {
            text-align:center;
            padding:24px;
            color:var(--muted);
            font-style:italic;
        }
        .empty-state.small {
            padding:12px;
            font-size:13px;
        }
        .activity-list {
            display:flex;
            flex-direction:column;
            gap:16px;
        }
        .activity-item {
            display:flex;
            gap:12px;
        }
        .activity-dot {
            width:10px;
            height:10px;
            border-radius:50%;
            margin-top:6px;
            flex-shrink:0;
        }
        .activity-dot.success { background:var(--success); }
        .activity-dot.primary { background:var(--accent); }
        .activity-dot.warning { background:var(--warning); }
        .activity-dot.info { background:var(--info); }
        .activity-content {
            flex:1;
        }
        .activity-title {
            font-weight:500;
            font-size:14px;
            color:var(--text);
            margin-bottom:2px;
        }
        .activity-time {
            font-size:13px;
            color:var(--muted);
        }
        .avatar-circle,
        .avatar-square {
            width:36px;
            height:36px;
            border-radius:50%;
            background:var(--surface-soft);
            display:flex;
            align-items:center;
            justify-content:center;
            color:var(--text);
            font-size:15px;
            font-weight:600;
            border:1px solid var(--border);
            flex-shrink:0;
        }
        .avatar-square {
            border-radius:4px;
        }
        .tenant-cell,
        .user-cell {
            display:flex;
            align-items:center;
            gap:12px;
        }
        .tenant-logo {
            width:36px;
            height:36px;
            border-radius:4px;
            object-fit:cover;
            border:1px solid var(--border);
        }
        .actions-inline {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        /* DETAIL / CONVERSATION */
        .detail-grid {
            display:grid;
            grid-template-columns:1fr;
            gap:24px;
            margin-top:16px;
        }
        @media (min-width:960px) {
            .detail-grid {
                grid-template-columns:2fr 1fr;
            }
        }
        .ticket-meta {
            display:flex;
            justify-content:flex-start;
            gap:32px;
            margin-bottom:16px;
            flex-wrap:wrap;
        }
        .meta-item {
            min-width:120px;
        }
        .meta-label {
            font-size:12px;
            color:var(--muted);
            text-transform:uppercase;
            letter-spacing:0.5px;
            font-weight:500;
            margin-bottom:4px;
            display:block;
        }
        .ticket-section {
            margin-top:16px;
        }
        .section-title {
            font-size:15px;
            font-weight:600;
            margin-bottom:8px;
            color:var(--text);
            padding-bottom:4px;
            border-bottom:1px solid var(--border);
        }
        .ticket-description {
            white-space:pre-wrap;
            font-size:14px;
            color:var(--text);
            line-height:1.6;
            margin-top:8px;
        }
        .conversation-list {
            display:flex;
            flex-direction:column;
            gap:16px;
        }
        .message-row {
            display:flex;
            gap:16px;
        }
        .message-main {
            flex:1;
            min-width:0;
        }
        .message-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            margin-bottom:6px;
        }
        .message-body {
            padding:14px;
            border-radius:4px;
            font-size:14px;
            background:var(--surface-soft);
            border:1px solid var(--border);
            color:var(--text);
            line-height:1.5;
            word-wrap:break-word;
        }
        .message-internal {
            background:var(--warning-bg);
            border-style:dashed;
            border-color:#ffc947;
        }
        .internal-label {
            color:var(--muted);
            font-size:13px;
            display:block;
            margin-bottom:6px;
            font-style:italic;
        }
        .reply-form {
            margin-top:16px;
        }
        .reply-footer {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-top:16px;
            flex-wrap:wrap;
            gap:12px;
        }
        .checkbox-label {
            display:flex;
            align-items:center;
            gap:8px;
            cursor:pointer;
            font-size:14px;
            color:var(--text);
            user-select:none;
        }
        .checkbox-label input[type="checkbox"] {
            cursor:pointer;
        }
        .side-form {
            margin-bottom:24px;
        }
        /* RESPONSIVE */
        @media (max-width:992px) {
            .sidebar {
                transform:translateX(-100%);
            }
            .sidebar.open {
                transform:translateX(0);
            }
            .main-content {
                margin-left:0;
            }
        }
        @media (max-width:768px) {
            .page-header {
                flex-direction:column;
                align-items:flex-start;
                gap:16px;
            }
            .page-actions {
                width:100%;
                justify-content:space-between;
            }
            .filters-row,
            .btn-group {
                flex-direction:column;
                align-items:flex-start;
                gap:12px;
            }
            .search-form {
                width:100%;
                flex-direction:column;
            }
            .content {
                padding:16px;
            }
            .card-body {
                padding:16px;
            }
        }
        /* SCROLLBAR */
        ::-webkit-scrollbar {
            width:8px;
        }
        ::-webkit-scrollbar-track {
            background:var(--surface-soft);
            border-radius:4px;
        }
        ::-webkit-scrollbar-thumb {
            background:var(--border);
            border-radius:4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background:var(--border-strong);
        }
        /* ANIMATIONS */
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(5px); }
            to { opacity:1; transform:translateY(0); }
        }
        .animate-fade-in {
            animation:fadeIn .3s ease forwards;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="?page=dashboard" class="logo">
                <div class="logo-icon">I</div>
                <div class="logo-text">Learnnect</div>
            </a>
            <button class="menu-toggle">≡</button>
        </div>
        <div class="tenant-info">
            <div class="tenant-name">Learnnect Global</div>
            <div class="tenant-subdomain">iuconnect.global</div>
        </div>
        <?php $currentPage = $_GET['page'] ?? 'dashboard'; ?>
        <div class="nav-section">
            <div class="nav-title">Principal</div>
            <a href="?page=dashboard" class="nav-item <?= ($currentPage === 'dashboard') ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-home"></i></span><span class="nav-text">Dashboard</span>
            </a>
            <a href="?page=support" class="nav-item <?= ($currentPage === 'support') ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-ticket-alt"></i></span><span class="nav-text">Soporte</span>
            </a>
            <a href="?page=tenants" class="nav-item <?= ($currentPage === 'tenants') ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-building"></i></span><span class="nav-text">Centros</span>
            </a>
            <a href="?page=users" class="nav-item <?= ($currentPage === 'users') ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-users"></i></span><span class="nav-text">Usuarios</span>
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-title">Sistema</div>
            <a href="?page=activity" class="nav-item <?= ($currentPage === 'activity') ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-history"></i></span><span class="nav-text">Actividad</span>
            </a>
            <a href="?page=settings" class="nav-item <?= ($currentPage === 'settings') ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-cog"></i></span><span class="nav-text">Configuración</span>
            </a>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <button class="menu-toggle mobile-toggle">≡</button>
                <div class="header-center">
                    <input type="text" class="search-box" placeholder="Buscar en Learnnect...">
                </div>
            </div>
            <div class="user-menu">
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="tenant-switcher">
                    <div class="tenant-avatar">I</div>
                    <div class="tenant-name">Learnnect</div>
                    <i class="fas fa-chevron-down" style="font-size:12px;"></i>
                    <div class="tenant-dropdown">
                        <div class="tenant-dropdown-header">Centros educativos</div>
                        <div class="tenant-list">
                            <div class="tenant-item active">
                                <div class="tenant-avatar">I</div>
                                <div>
                                    <div class="tenant-name-dropdown">Learnnect Global</div>
                                    <div class="tenant-subdomain-dropdown">iuconnect.global</div>
                                </div>
                            </div>
                            <div class="tenant-item">
                                <div class="tenant-avatar">A</div>
                                <div>
                                    <div class="tenant-name-dropdown">Academia Valencia</div>
                                    <div class="tenant-subdomain-dropdown">academia-valencia.iuconnect.net</div>
                                </div>
                            </div>
                            <div class="tenant-item">
                                <div class="tenant-avatar">C</div>
                                <div>
                                    <div class="tenant-name-dropdown">Colegio Madrid</div>
                                    <div class="tenant-subdomain-dropdown">colegio-madrid.iuconnect.net</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'Admin', 0, 1)) ?></div>
            </div>
        </div>
        <div class="content">
            <?php
            if (isset($_SESSION['flash_message'])) {
                echo '<div class="alert alert-success animate-fade-in"><i class="fas fa-check-circle alert-icon"></i>' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
                unset($_SESSION['flash_message']);
            }
            if (isset($_SESSION['flash_error'])) {
                echo '<div class="alert alert-error animate-fade-in"><i class="fas fa-exclamation-triangle alert-icon"></i>' . htmlspecialchars($_SESSION['flash_error']) . '</div>';
                unset($_SESSION['flash_error']);
            }
            // Renderizar la página solicitada
            $controller->render();
            ?>
        </div>
    </div>
    <script>
        // Toggle sidebar on mobile
        const sidebar = document.querySelector('.sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        const mobileToggle = document.querySelector('.mobile-toggle');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
        
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && sidebar.classList.contains('open')) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isMenuToggle = menuToggle.contains(event.target) || mobileToggle.contains(event.target);
                
                if (!isClickInsideSidebar && !isMenuToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
        
        // Current page for sync
        const currentPage = <?= json_encode($_GET['page'] ?? 'dashboard') ?>;
        
        // Auto sync support tickets
        if (currentPage === 'support') {
            setTimeout(function() {
                fetch('admin_panel.php?page=support&action=sync_background')
                    .then(response => response.json())
                    .catch(error => console.log('Sync error:', error));
            }, 1000);
        }
        
        // Add fade-in animation to cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(10px)';
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50 * index);
                }, 100);
            });
        });
    </script>
</body>
</html>

