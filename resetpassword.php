<?php
session_start();

// Headers de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Configuración BD
$host = 'localhost';
$db   = 'iuconect';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Verificar tabla de recuperación
$pdo->exec("
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expiry INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(email),
    INDEX(code)
)
");

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/smtp_config.php';

// Configuración de envío (SMTP Gmail)
// --- Función para enviar código ---
function sendResetCode($email, $code) {
    $gmail_config = iuc_get_smtp_config();
    $subject = "Código de recuperación - IUC";
    $body = "
    <html><body style='font-family:Inter, sans-serif;'>
      <div style='max-width:600px;margin:auto;padding:30px;border:1px solid #eee;border-radius:12px;'>
        <h2 style='color:#111;'>Restablece tu contraseña</h2>
        <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>
        <p>Introduce este código en la página de verificación:</p>
        <div style='background:#f5f5f5;border-left:4px solid #111;padding:20px;font-size:24px;font-weight:bold;text-align:center;letter-spacing:6px;'>$code</div>
        <p style='font-size:13px;color:#555;margin-top:20px;'>El código expirará en 15 minutos. Si no solicitaste este cambio, ignora este mensaje.</p>
      </div>
    </body></html>";

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $gmail_config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $gmail_config['username'];
        $mail->Password = $gmail_config['password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $gmail_config['smtp_port'];
        $mail->setFrom($gmail_config['from_email'], $gmail_config['from_name']);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error PHPMailer: " . $e->getMessage());
        return false;
    }
}

// Variables iniciales
$stage = $_POST['stage'] ?? 'email';
$error = '';
$info = '';
$email = $_POST['email'] ?? ($_SESSION['reset_email'] ?? '');

// --- Paso 1: enviar código ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($stage === 'email') {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Introduce un correo válido.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) {
                $error = 'No existe ninguna cuenta con ese correo.';
            } else {
                $pdo->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);
                $code = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
                $expiry = time() + 900;
                $pdo->prepare("INSERT INTO password_resets (email, code, expiry) VALUES (?, ?, ?)")->execute([$email,$code,$expiry]);
                $_SESSION['reset_email'] = $email;
                if (sendResetCode($email,$code)) {
                    $info = '✅ Código enviado a tu correo.';
                    $stage = 'code';
                } else {
                    $error = 'No se pudo enviar el correo de verificación, revisa la configuración.';
                }
            }
        }
    }

    // --- Paso 2: validar código ---
    elseif ($stage === 'code') {
        $code = '';
        for ($i=1; $i<=6; $i++) $code .= $_POST["code$i"] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email=? AND code=? AND expiry>=?");
        $stmt->execute([$email,$code,time()]);
        $match = $stmt->fetch();
        if (!$match) {
            $error = 'Código inválido o caducado.';
        } else {
            $_SESSION['verified'] = true;
            $info = '✅ Código verificado.';
            $stage = 'password';
        }
    }

    // --- Paso 3: guardar contraseña ---
    elseif ($stage === 'password' && !empty($_SESSION['verified'])) {
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['confirm'] ?? '';
        if ($p1 !== $p2) $error = 'Las contraseñas no coinciden.';
        elseif (strlen($p1) < 8) $error = 'Debe tener al menos 8 caracteres.';
        else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password=? WHERE email=?");
            $stmt->execute([$hash, $_SESSION['reset_email']]);
            $pdo->prepare("DELETE FROM password_resets WHERE email=?")->execute([$_SESSION['reset_email']]);
            session_destroy();
            header("Location: login.php?reset=success");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Recuperar contraseña | IUC</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
:root{
  --black:#111;
  --gray-dark:#222;
  --gray:#555;
  --gray-light:#e5e5e5;
  --gray-bg:#f5f5f5;
  --white:#fff;
  --green:#16a34a;
  --red:#dc2626;
  --radius:18px;
  --transition:.25s ease;
}
body{
  font-family:'Inter',sans-serif;
  background:var(--gray-bg);
  display:flex;
  align-items:center;
  justify-content:center;
  min-height:100vh;
  margin:0;
}
.container{
  display:flex;
  max-width:950px;
  width:100%;
  background:var(--white);
  border-radius:var(--radius);
  overflow:hidden;
  box-shadow:0 8px 30px rgba(0,0,0,.08);
  border:1px solid var(--gray-light);
}
.left{
  flex:1;
  background:var(--black);
  color:var(--white);
  padding:60px 50px;
  display:flex;
  flex-direction:column;
  justify-content:center;
}
.left .logo{
  display:flex;
  align-items:center;
  gap:12px;
  margin-bottom:40px;
}
.logo-mark{
  width:44px;
  height:44px;
  background:#000;
  color:#fff;
  border-radius:14px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:900;
  letter-spacing:0.02em;
  border:1px solid rgba(255,255,255,0.12);
  box-shadow:0 14px 30px rgba(0,0,0,0.25);
}
.logo-meta{
  display:flex;
  flex-direction:column;
  line-height:1.05;
}
.logo-name{
  font-weight:800;
  font-size:20px;
  color:var(--white);
  letter-spacing:-0.01em;
}
.logo-version{
  margin-top:2px;
  font-weight:600;
  font-size:11px;
  color:rgba(255,255,255,0.65);
  text-transform:uppercase;
  letter-spacing:0.18em;
}
.left h1{font-size:30px;font-weight:800;margin-bottom:14px;}
.left p{font-size:15px;color:rgba(255,255,255,.75);}
.right{
  flex:1;
  padding:60px 50px;
  background:var(--white);
  display:flex;
  flex-direction:column;
  justify-content:center;
}
h2{font-size:26px;font-weight:800;margin-bottom:10px;}
p.subtitle{color:var(--gray);margin-bottom:30px;}
.progress{display:flex;justify-content:space-between;align-items:center;position:relative;margin-bottom:35px;}
.progress::before{
  content:'';position:absolute;left:25px;right:25px;height:2px;
  background:var(--gray-light);top:50%;transform:translateY(-50%);
}
.step{
  width:38px;height:38px;border-radius:50%;
  background:#eee;color:var(--gray);
  display:flex;align-items:center;justify-content:center;font-weight:600;
  transition:all var(--transition);
  z-index:1;
}
.step.active{background:var(--black);color:var(--white);}
.step.completed{background:var(--gray-dark);color:var(--white);}
.notification{
  padding:14px 16px;border-radius:10px;margin-bottom:16px;
  display:flex;align-items:center;gap:10px;font-size:14px;
}
.notification.success{background:#ecfdf5;color:var(--green);border:1px solid rgba(16,185,129,.15);}
.notification.error{background:#fef2f2;color:var(--red);border:1px solid rgba(220,38,38,.12);}
label{font-size:14px;font-weight:600;margin-bottom:8px;display:block;}
input[type=email],input[type=password]{width:100%;padding:14px 16px;border:1.5px solid var(--gray-light);border-radius:10px;background:var(--white);transition:border var(--transition);}
input:focus{outline:none;border-color:var(--black);}
.code-inputs{display:flex;justify-content:center;gap:10px;margin:20px 0;}
.code-input{width:48px;height:58px;text-align:center;font-size:20px;font-weight:700;border:1.5px solid var(--gray-light);border-radius:10px;}
.code-input:focus{border-color:var(--black);background:var(--gray-bg);outline:none;}
button{border:none;border-radius:10px;font-weight:700;padding:14px 20px;cursor:pointer;font-size:15px;transition:background var(--transition);}
button.primary{width:100%;background:var(--black);color:var(--white);}
button.primary:hover{background:var(--gray-dark);}
a.back{display:inline-block;margin-top:25px;color:var(--gray);font-size:14px;text-decoration:none;}
a.back:hover{color:var(--black);}
.security{margin-top:25px;font-size:12px;color:var(--gray);border:1px solid var(--gray-light);border-radius:10px;padding:10px;background:var(--gray-bg);}
@media(max-width:860px){.container{flex-direction:column;max-width:500px;}.left{display:none;}.right{padding:40px 30px;}}
</style>
</head>
<body>
<div class="container">
  <div class="left">
    <div class="logo"><div class="logo-mark">IUC</div><div class="logo-meta"><div class="logo-name">IUC</div><div class="logo-version">v3.0</div></div></div>
    <h1>¿Olvidaste tu contraseña?</h1>
    <p>Te ayudaremos a recuperar el acceso a tu cuenta de forma segura y rápida.</p>
  </div>
  <div class="right">
    <h2>Recuperar acceso</h2>
    <p class="subtitle">Sigue los pasos para restablecer tu contraseña.</p>

    <div class="progress">
      <div class="step <?= $stage==='email'?'active':($stage!=='email'?'completed':'')?>">1</div>
      <div class="step <?= $stage==='code'?'active':($stage==='password'?'completed':'')?>">2</div>
      <div class="step <?= $stage==='password'?'active':''?>">3</div>
    </div>

    <?php if($error): ?><div class="notification error">⚠️ <?= $error ?></div><?php endif; ?>
    <?php if($info): ?><div class="notification success">✅ <?= $info ?></div><?php endif; ?>

    <form method="POST">
      <input type="hidden" name="stage" value="<?= htmlspecialchars($stage) ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

      <?php if($stage==='email'): ?>
        <label>Correo electrónico</label>
        <input type="email" name="email" placeholder="nombre@correo.com" required>

      <?php elseif($stage==='code'): ?>
        <label>Código de verificación</label>
        <div class="code-inputs">
          <?php for($i=1;$i<=6;$i++): ?>
            <input type="tel" maxlength="1" name="code<?= $i ?>" class="code-input" pattern="[0-9]" required>
          <?php endfor; ?>
        </div>

      <?php elseif($stage==='password'): ?>
        <label>Nueva contraseña</label>
        <input type="password" name="password" placeholder="Mínimo 8 caracteres" required minlength="8">
        <label>Confirmar contraseña</label>
        <input type="password" name="confirm" placeholder="Repite la contraseña" required minlength="8">
      <?php endif; ?>

      <button type="submit" class="primary">
        <?php if($stage==='email'): ?>Enviar código<?php elseif($stage==='code'): ?>Verificar código<?php else: ?>Guardar contraseña<?php endif; ?>
      </button>

      <a href="login.php" class="back">← Volver al inicio de sesión</a>
      <div class="security">🔒 Tus datos están protegidos con encriptación empresarial.</div>
    </form>
  </div>
</div>
</body>
</html>
