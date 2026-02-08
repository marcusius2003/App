<?php
/**
 * Lee el buzón IMAP de soporte y añade las respuestas
 * a la tabla ticket_responses según el código [Ticket TKT-...]
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'iuconect';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function __construct() {
        try {
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

// Config IMAP (misma cuenta que usas para enviar)
$imapHost   = '{imap.gmail.com:993/imap/ssl}INBOX';
$imapUser   = 'soporte.iuconnect@gmail.com';
$imapPass   = 'jkhj vnln gnwl rgar'; // APP PASSWORD (mejor mover a .env)

$db   = new Database();
$conn = $db->getConnection();

$inbox = @imap_open($imapHost, $imapUser, $imapPass);

if (!$inbox) {
    error_log("IMAP error: " . imap_last_error());
    exit;
}

// Buscar solo correos no leídos
$emails = imap_search($inbox, 'UNSEEN');

if ($emails === false) {
    imap_close($inbox);
    exit;
}

function getBody($inbox, $msgNumber) {
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
}

foreach ($emails as $msgNumber) {
    $overviewList = imap_fetch_overview($inbox, $msgNumber, 0);
    if (!$overviewList || !isset($overviewList[0])) {
        imap_setflag_full($inbox, $msgNumber, "\\Seen");
        continue;
    }

    $overview = $overviewList[0];
    $subject  = isset($overview->subject) ? imap_utf8($overview->subject) : '';

    // Buscar patrón [Ticket TKT-YYYYMMDD-XXXXXX]
    if (!preg_match('/\[Ticket\s+(TKT-\d{8}-[A-Z0-9]{6})\]/i', $subject, $m)) {
        imap_setflag_full($inbox, $msgNumber, "\\Seen");
        continue;
    }

    $ticketCode = $m[1];

    // Localizar ticket por ticket_id
    $stmt = $conn->prepare("SELECT id, user_id FROM tickets WHERE ticket_id = ?");
    $stmt->execute([$ticketCode]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        imap_setflag_full($inbox, $msgNumber, "\\Seen");
        continue;
    }

    $body = getBody($inbox, $msgNumber);
    if ($body === '') {
        imap_setflag_full($inbox, $msgNumber, "\\Seen");
        continue;
    }

    // Remitente
    $fromEmail = '';
    if (!empty($overview->from)) {
        $fromParts = imap_rfc822_parse_adrlist($overview->from, '');
        if (!empty($fromParts) && isset($fromParts[0]->mailbox, $fromParts[0]->host)) {
            $fromEmail = strtolower($fromParts[0]->mailbox . '@' . $fromParts[0]->host);
        }
    }

    $userId = (int)$ticket['user_id'];

    // Intentar identificar usuario por email
    if ($fromEmail) {
        $stmtUser = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
        $stmtUser->execute([$fromEmail]);
        if ($rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
            $userId = (int)$rowUser['id'];
        }
    }

    $stmtInsert = $conn->prepare("
        INSERT INTO ticket_responses (ticket_id, user_id, message, is_internal, created_at)
        VALUES (:ticket_id, :user_id, :message, 0, NOW())
    ");

    $stmtInsert->execute([
        ':ticket_id' => $ticket['id'],
        ':user_id'   => $userId,
        ':message'   => $body
    ]);

    imap_setflag_full($inbox, $msgNumber, "\\Seen");
}

imap_close($inbox);
