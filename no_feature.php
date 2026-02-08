<?php
http_response_code(403);
$feature = $_GET['feature'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>No disponible</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f7f7f7; padding:40px; }
    .card { background:#fff; padding:24px; border-radius:12px; max-width:520px; margin:0 auto; box-shadow:0 10px 20px rgba(0,0,0,.06); }
    .code { color:#666; font-size:12px; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Modulo no disponible</h2>
    <p>Este modulo no esta habilitado para tu tenant o tu plan.</p>
    <?php if ($feature): ?>
      <div class="code">Feature: <?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <p><a href="dashboard.php">Volver al dashboard</a></p>
  </div>
</body>
</html>
