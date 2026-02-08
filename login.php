<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/tenant_access.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$identifierValue = '';

function resolve_login_academy_id(PDO $pdo): ?int {
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return null;
    }

    $host = preg_replace('/:\\d+$/', '', $host);
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return null;
    }

    if (!preg_match('/^([a-z0-9-]+)\\.iuconnect\\.net$/', $host, $m)) {
        return null;
    }

    $subdomain = $m[1];
    if ($subdomain === '' || in_array($subdomain, ['www', 'app', 'admin'], true)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM academies WHERE subdomain = ? LIMIT 1");
        $stmt->execute([$subdomain]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
            return null;
        }
        throw $e;
    }
}

if (!empty($_GET['error'])) {
    $error = getAuthErrorMessage((string) $_GET['error']);
}

if (!empty($_GET['success'])) {
    $success = (string) $_GET['success'];
}

if (!empty($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Contraseña actualizada. Ya puedes iniciar sesión.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $identifierValue = $identifier;

    if ($identifier === '' || $password === '') {
        $error = 'Introduce tu correo/usuario y contraseña.';
    } else {
        $user = null;
        $candidates = [];
        $academyHintId = resolve_login_academy_id($pdo);

        try {
            $academyOrder = '';
            $params = [':identifier' => $identifier];
            if (!empty($academyHintId)) {
                $academyOrder = "(academy_id = :academy_id) DESC,";
                $params[':academy_id'] = $academyHintId;
            }
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE email = :identifier OR username = :identifier
                ORDER BY
                    {$academyOrder}
                    (email = :identifier) DESC,
                    (deleted_at IS NULL) DESC,
                    (status = 'active') DESC,
                    id DESC
                LIMIT 25
            ");
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S22') {
                throw $e;
            }

            // Fallback for schemas without deleted_at column.
            $academyOrder = '';
            $params = [':identifier' => $identifier];
            if (!empty($academyHintId)) {
                $academyOrder = "(academy_id = :academy_id) DESC,";
                $params[':academy_id'] = $academyHintId;
            }
            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE email = :identifier OR username = :identifier
                ORDER BY
                    {$academyOrder}
                    (email = :identifier) DESC,
                    (status = 'active') DESC,
                    id DESC
                LIMIT 25
            ");
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $passwordValid = false;
        $shouldUpgradeHash = false;

        foreach ($candidates as $candidate) {
            $passwordHash = trim((string) ($candidate['password'] ?? ''));
            if ($passwordHash === '') {
                continue;
            }

            if (password_verify($password, $passwordHash)) {
                $user = $candidate;
                $passwordValid = true;
                $shouldUpgradeHash = password_needs_rehash($passwordHash, PASSWORD_DEFAULT);
                break;
            }
            if (hash_equals($passwordHash, $password)) {
                // Legacy plain-text password.
                $user = $candidate;
                $passwordValid = true;
                $shouldUpgradeHash = true;
                break;
            }
            if (preg_match('/^[a-f0-9]{32}$/i', $passwordHash) && hash_equals(strtolower($passwordHash), md5($password))) {
                // Legacy MD5.
                $user = $candidate;
                $passwordValid = true;
                $shouldUpgradeHash = true;
                break;
            }
            if (preg_match('/^[a-f0-9]{40}$/i', $passwordHash) && hash_equals(strtolower($passwordHash), sha1($password))) {
                // Legacy SHA1.
                $user = $candidate;
                $passwordValid = true;
                $shouldUpgradeHash = true;
                break;
            }
        }

        if (!$user || !$passwordValid) {
            $error = 'Credenciales incorrectas.';
        } elseif (!empty($user['status']) && $user['status'] !== 'active') {
            $error = 'Usuario bloqueado. Contacta con el administrador para resolverlo.';
        } else {
            if ($shouldUpgradeHash) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                    $stmt->execute([
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':id' => (int) $user['id'],
                    ]);
                } catch (PDOException $e) {
                    // Ignore schema issues to avoid blocking login.
                }
            }
            $isPlatformAdmin = is_platform_admin($pdo, (int) $user['id']);
            $academyId = (int) ($user['academy_id'] ?? 0);
            if ($academyId <= 0 && !$isPlatformAdmin) {
                    try {
                        $stmtAcademies = $pdo->query("
                            SELECT id
                            FROM academies
                            WHERE status = 'active'
                            ORDER BY id ASC
                            LIMIT 2
                        ");
                        $academyIds = $stmtAcademies->fetchAll(PDO::FETCH_COLUMN);
                    } catch (PDOException $e) {
                        $academyIds = [];
                    }

                    if (count($academyIds) === 1) {
                        $academyId = (int) $academyIds[0];
                        try {
                            $stmtAssign = $pdo->prepare("UPDATE users SET academy_id = :academy_id WHERE id = :id");
                            $stmtAssign->execute([
                                ':academy_id' => $academyId,
                                ':id' => (int) $user['id'],
                            ]);
                        } catch (PDOException $e) {
                            if ($e->getCode() !== '42S22') {
                                throw $e;
                            }
                        }
                    } else {
                        $error = 'Tu usuario no tiene academia asignada. Contacta con soporte para asignarte un tenant.';
                        $academyId = 0;
                    }
            }

            if ($error === '') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                if ($academyId > 0) {
                    $_SESSION['academy_id'] = $academyId;
                }
                if ($isPlatformAdmin) {
                    header('Location: admin_panel.php');
                    exit;
                }
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión | IUC</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --black: #111111;
      --gray-dark: #1a1a1a;
      --gray: #333333;
      --gray-light: #e5e5e5;
      --gray-bg: #f5f5f5;
      --white: #ffffff;
    }
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: var(--gray-bg);
      color: var(--gray-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .login-container {
      display: flex;
      max-width: 1100px;
      width: 100%;
      background: var(--white);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,0.06);
      border: 1px solid var(--gray-light);
    }
    .left-panel {
      flex: 1;
      background: var(--black);
      color: var(--white);
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 40px;
    }
    .logo-mark {
      width: 44px;
      height: 44px;
      background: #000;
      color: #fff;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      letter-spacing: 0.02em;
      border: 1px solid rgba(255, 255, 255, 0.12);
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.25);
    }
    .logo-meta {
      display: flex;
      flex-direction: column;
      line-height: 1.05;
    }
    .logo-name {
      font-weight: 800;
      font-size: 20px;
      color: var(--white);
      letter-spacing: -0.01em;
    }
    .logo-version {
      margin-top: 2px;
      font-weight: 600;
      font-size: 11px;
      color: rgba(255, 255, 255, 0.65);
      text-transform: uppercase;
      letter-spacing: 0.18em;
    }
    .left-panel h1 {
      font-size: 34px;
      font-weight: 800;
      margin-bottom: 20px;
      line-height: 1.2;
    }
    .left-panel p {
      color: rgba(255,255,255,0.7);
      font-size: 15px;
      line-height: 1.6;
      margin-bottom: 40px;
    }
    .features {
      display: grid;
      gap: 16px;
    }
    .feature {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    .feature-icon {
      font-size: 18px;
      opacity: 0.7;
    }
    .feature h3 {
      font-weight: 600;
      font-size: 15px;
      margin-bottom: 4px;
    }
    .feature p {
      font-size: 13px;
      color: rgba(255,255,255,0.6);
      margin: 0;
    }
    .right-panel {
      flex: 1;
      padding: 60px 50px;
      background: var(--white);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .form-title {
      font-size: 26px;
      font-weight: 800;
      margin-bottom: 8px;
    }
    .form-subtitle {
      color: var(--gray);
      margin-bottom: 40px;
      font-size: 15px;
    }
    .form-input {
      width: 100%;
      padding: 15px 16px;
      border: 1.5px solid var(--gray-light);
      border-radius: 10px;
      font-size: 15px;
      margin-bottom: 20px;
      background: var(--white);
      color: var(--gray-dark);
      transition: border 0.2s ease;
    }
    .form-input:focus {
      outline: none;
      border-color: var(--black);
    }
    .alert {
      width: 100%;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 14px;
      margin-bottom: 18px;
      border: 1px solid var(--gray-light);
      background: var(--gray-bg);
      color: var(--gray-dark);
    }
    .alert.error {
      border-color: rgba(220, 38, 38, 0.3);
      background: rgba(220, 38, 38, 0.06);
      color: #991b1b;
    }
    .alert.success {
      border-color: rgba(22, 163, 74, 0.3);
      background: rgba(22, 163, 74, 0.06);
      color: #166534;
    }
    .remember-forgot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      font-size: 14px;
      color: var(--gray);
    }
    .remember-me input {
      accent-color: var(--black);
      margin-right: 8px;
    }
    .forgot-password {
      color: var(--gray);
      text-decoration: none;
      border-bottom: 1px solid transparent;
      transition: 0.2s;
    }
    .forgot-password:hover {
      border-bottom-color: var(--gray-dark);
      color: var(--gray-dark);
    }
    .login-button {
      width: 100%;
      padding: 16px;
      background: var(--black);
      color: var(--white);
      border: none;
      border-radius: 10px;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      transition: 0.25s;
    }
    .login-button:hover {
      background: var(--gray-dark);
      transform: translateY(-1px);
    }
    .register-link {
      text-align: center;
      margin-top: 30px;
      color: var(--gray);
      font-size: 14px;
    }
    .register-link a {
      color: var(--black);
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid transparent;
      transition: 0.2s;
    }
    .register-link a:hover {
      border-bottom-color: var(--black);
    }
    .security-note {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 25px;
      padding: 12px;
      background: var(--gray-bg);
      border: 1px solid var(--gray-light);
      border-radius: 10px;
      color: var(--gray);
      font-size: 13px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="left-panel">
	      <div class="logo">
	        <div class="logo-mark">IUC</div>
	        <div class="logo-meta">
	          <div class="logo-name">IUC</div>
	          <div class="logo-version">v3.0</div>
	        </div>
	      </div>
      <h1>Accede a tu plataforma</h1>
      <p>Gestiona proyectos, colabora con tu equipo y accede a todas las herramientas desde un solo lugar.</p>
      <div class="features">
        <div class="feature">
          <div class="feature-icon">🤝</div>
          <div><h3>Colaboración eficiente</h3><p>Trabaja en equipo sin importar tu ubicación o sector.</p></div>
        </div>
        <div class="feature">
          <div class="feature-icon">🔐</div>
          <div><h3>Seguridad empresarial</h3><p>Tus datos protegidos con los más altos estándares.</p></div>
        </div>
        <div class="feature">
          <div class="feature-icon">⚙️</div>
          <div><h3>Productividad</h3><p>Optimiza el trabajo con herramientas integradas.</p></div>
        </div>
      </div>
    </div>

    <div class="right-panel">
      <h2 class="form-title">Iniciar Sesión</h2>
      <p class="form-subtitle">Introduce tus credenciales para acceder a tu cuenta</p>
      
      <form method="POST" action="">
        <?php if ($error !== ''): ?>
          <div class="alert error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
          <div class="alert success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <input type="text" name="email" class="form-input" placeholder="Correo electrónico o usuario" value="<?php echo htmlspecialchars($identifierValue, ENT_QUOTES, 'UTF-8'); ?>" required>
        <input type="password" name="password" class="form-input" placeholder="Contraseña" required>

        <div class="remember-forgot">
          <label class="remember-me"><input type="checkbox" name="remember"> Recordar sesión</label>
          <a href="resetpassword.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="login-button">Acceder</button>

        <div class="register-link">
          ¿No tienes cuenta? <a href="#">Solicitar acceso</a>
        </div>

        <div class="security-note">
          🔒 Tus datos están protegidos con encriptación empresarial
        </div>
      </form>
    </div>
  </div>
</body>
</html>
