<?php
/**
 * biblioteca.php
 * Panel de gestión de archivos y carpetas para estudiantes/usuarios.
 * Implementa navegación tipo explorador, subida de archivos y creación de carpetas.
 */

// 1. Configuración y Sesión
require_once 'includes/db.php';
require_once 'includes/auth_guard.php';
require_once 'includes/tenant_access.php';
require_once 'includes/tenant_context.php';

// Verificar sesión y obtener usuario activo
$currentUser = requireActiveUser($pdo);
require_feature($pdo, 'edu.library');
$userId      = (int) $currentUser['id'];
$username    = $currentUser['username'];

$tenantContext = new TenantContext($pdo);
try {
    $context = $tenantContext->resolveTenantContext();
    $academy_id = $context['academy_id'];
} catch (Exception $e) {
    $academy_id = $currentUser['academy_id'] ?? null;
}
$template = $tenantContext->getTenantTemplate($academy_id);
$templateCode = strtoupper($template['code'] ?? 'CORE_ONLY');
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);

if ($academy_id > 0 && $templateCode !== 'EDUCATION' && !$isHospitality) {
    $eduSignals = ['edu.courses', 'edu.assignments', 'edu.exams', 'edu.library'];
    foreach ($eduSignals as $signal) {
        if (tenant_has_feature($pdo, (int) $academy_id, $signal)) {
            $templateCode = 'EDUCATION';
            $template['code'] = 'EDUCATION';
            break;
        }
    }
}
$isHospitality = in_array($templateCode, ['RESTAURANT', 'BAR'], true);

$academyName = (string) ($currentUser['academy_name'] ?? ($_SESSION['academy_name'] ?? ''));
$academyNameLower = strtolower($academyName);
$isSalon = $academyNameLower !== '' && (
    strpos($academyNameLower, 'peluquer') !== false
    || strpos($academyNameLower, 'barber') !== false
    || strpos($academyNameLower, 'salon') !== false
    || strpos($academyNameLower, 'estetica') !== false
);

$isTenantAdmin = is_tenant_admin($pdo, $userId, $academy_id);

// 2. Helpers y Lógica de Rutas

// Obtener ruta actual desde GET, por defecto '/'
$currentPath = $_GET['path'] ?? '/';

// Normalizar ruta: debe empezar por / y terminar en / si no es raíz
if ($currentPath !== '/') {
    // Asegurar slash al inicio
    if (!str_starts_with($currentPath, '/')) {
        $currentPath = '/' . $currentPath;
    }
    // Asegurar slash al final
    if (!str_ends_with($currentPath, '/')) {
        $currentPath .= '/';
    }
}

// Sanitizar: eliminar '..' para evitar traversal
$currentPath = str_replace('..', '', $currentPath);

// Función para generar breadcrumbs
function getBreadcrumbs(string $path): array {
    $crumbs = [['name' => 'Inicio', 'path' => '/']];
    if ($path === '/') {
        return $crumbs;
    }
    
    // Quitar slash inicial y final para explotar
    $trimPath = trim($path, '/');
    $parts = explode('/', $trimPath);
    
    $buildPath = '/';
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $buildPath .= $part . '/';
        $crumbs[] = [
            'name' => $part,
            'path' => $buildPath
        ];
    }
    return $crumbs;
}

$breadcrumbs = getBreadcrumbs($currentPath);

// 3. Procesamiento de Formularios (POST)

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- CREAR CARPETA ---
    if ($action === 'create_folder') {
        $folderName = trim($_POST['folder_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($folderName)) {
            $errorMsg = "El nombre de la carpeta no puede estar vacío.";
        } elseif (preg_match('/[\\\\\\/:*?"<>|]/', $folderName)) {
            $errorMsg = "El nombre contiene caracteres no válidos.";
        } else {
            // Verificar duplicados en la misma ruta
            $stmt = $pdo->prepare("SELECT id FROM folders WHERE user_id = :uid AND path = :path AND name = :name AND is_deleted = 0");
            $stmt->execute([':uid' => $userId, ':path' => $currentPath, ':name' => $folderName]);
            if ($stmt->fetch()) {
                $errorMsg = "Ya existe una carpeta con ese nombre en esta ubicación.";
            } else {
                // Insertar
                $sql = "INSERT INTO folders (name, path, description, created_by, user_id, is_deleted, created_at) 
                        VALUES (:name, :path, :desc, :uid, :uid, 0, NOW())";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([
                    ':name' => $folderName, 
                    ':path' => $currentPath, 
                    ':desc' => $description, 
                    ':uid' => $userId
                ])) {
                    // Redirigir para evitar reenvío
                    header("Location: biblioteca.php?path=" . urlencode($currentPath));
                    exit;
                } else {
                    $errorMsg = "Error al crear la carpeta en base de datos.";
                }
            }
        }
    }

    // --- ELIMINAR CARPETA ---
    elseif ($action === 'delete_folder') {
        $folderId = (int) ($_POST['folder_id'] ?? 0);

        if ($folderId <= 0) {
            $errorMsg = "Carpeta no valida para eliminar.";
        } else {
            $stmt = $pdo->prepare("SELECT id, name, path FROM folders WHERE id = :id AND user_id = :uid AND is_deleted = 0");
            $stmt->execute([':id' => $folderId, ':uid' => $userId]);
            $folderRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$folderRow) {
                $errorMsg = "No se encontro la carpeta seleccionada.";
            } else {
                $targetPath = $folderRow['path'] . $folderRow['name'] . '/';
                $targetLike = $targetPath . '%';

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE folders SET is_deleted = 1 WHERE user_id = :uid AND id = :id");
                    $stmt->execute([':uid' => $userId, ':id' => $folderId]);

                    $stmt = $pdo->prepare("UPDATE folders SET is_deleted = 1 WHERE user_id = :uid AND path LIKE :path");
                    $stmt->execute([':uid' => $userId, ':path' => $targetLike]);

                    $stmt = $pdo->prepare("UPDATE files SET is_deleted = 1 WHERE user_id = :uid AND path LIKE :path");
                    $stmt->execute([':uid' => $userId, ':path' => $targetLike]);

                    $pdo->commit();

                    $successMsg = 'Carpeta eliminada correctamente.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errorMsg = "Error al eliminar la carpeta.";
                }
            }
        }
    }

    // --- SUBIR ARCHIVO ---
    elseif ($action === 'upload_file') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['file']['tmp_name'];
            $fileName = $_FILES['file']['name'];
            $fileSize = $_FILES['file']['size'];
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar', 'txt'];
            $maxSize = 20 * 1024 * 1024; // 20 MB

            if (!in_array($fileExt, $allowed)) {
                $errorMsg = "Tipo de archivo no permitido ($fileExt).";
            } elseif ($fileSize > $maxSize) {
                $errorMsg = "El archivo excede el tamaño máximo de 20MB.";
            } else {
                // Preparar directorio físico
                $uploadDir = 'uploads/' . $userId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Generar nombre físico seguro
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($fileName, PATHINFO_FILENAME));
                $physicalName = time() . '_' . $safeName . '.' . $fileExt;
                $targetFile = $uploadDir . $physicalName;

                if (move_uploaded_file($fileTmp, $targetFile)) {
                    // Guardar en BD
                    $sql = "INSERT INTO files (name, size, type, file_path, path, user_id, is_deleted, uploaded_at) 
                            VALUES (:name, :size, :type, :fpath, :path, :uid, 0, NOW())";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([
                        ':name' => $fileName,
                        ':size' => $fileSize,
                        ':type' => $fileExt,
                        ':fpath' => $targetFile, // Ruta relativa
                        ':path' => $currentPath,
                        ':uid' => $userId
                    ])) {
                        header("Location: biblioteca.php?path=" . urlencode($currentPath));
                        exit;
                    } else {
                        $errorMsg = "Error al guardar el registro del archivo.";
                    }
                } else {
                    $errorMsg = "Error al mover el archivo al servidor.";
                }
            }
        } else {
            $errorMsg = "Error en la subida del archivo (Cód: " . ($_FILES['file']['error'] ?? 'Desconocido') . ")";
        }
    }
}

// 4. Consultas de Datos (Listar contenido)

// Carpetas
$stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = :uid AND path = :path AND is_deleted = 0 ORDER BY name ASC");
$stmt->execute([':uid' => $userId, ':path' => $currentPath]);
$folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Archivos
$stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = :uid AND path = :path AND is_deleted = 0 ORDER BY uploaded_at DESC");
$stmt->execute([':uid' => $userId, ':path' => $currentPath]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper para tamaño
function formatBytes($bytes, $precision = 1) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Icono según extensión
function getFileIcon($ext) {
    return match($ext) {
        'pdf' => 'fa-file-pdf text-danger',
        'doc','docx' => 'fa-file-word text-primary',
        'xls','xlsx' => 'fa-file-excel text-success',
        'ppt','pptx' => 'fa-file-powerpoint text-warning',
        'zip','rar' => 'fa-file-archive text-muted',
        'jpg','jpeg','png' => 'fa-file-image text-info',
        default => 'fa-file text-secondary'
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Biblioteca - Learnnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/layout-core.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body, #f5f7fb);
            color: var(--text-main, #1f2937);
            overflow-x: hidden;
        }
        .salon-library {
            background-color: #f5f6f8;
        }
        .salon-library .layout,
        .salon-library .main {
            background: #f5f6f8;
        }
        .salon-library .sidebar,
        .salon-library .sidebar-nav a {
            color: #e5e7eb;
        }
        .salon-library .sidebar-nav a {
            border-left: 2px solid transparent;
        }
        .salon-library .sidebar-nav a.active,
        .salon-library .sidebar-nav a:hover {
            background-color: #1f1f1f;
            color: #ffffff;
            border-left-color: #ffffff;
            transform: none;
        }
        .salon-library .sidebar-nav i {
            color: inherit;
        }
        .salon-library .menu-toggle {
            background: #ffffff;
            border-color: #e5e5e5;
            color: #111111;
        }
        .salon-library .iuc-salon-sidebar {
            background: linear-gradient(180deg, #070707 0%, #0b0f1f 55%, #070707 100%);
            border-right: 1px solid rgba(255, 255, 255, 0.06);
        }
        .salon-library .iuc-salon-logo {
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.3em;
            color: #ffffff;
            padding: 0 0.5rem;
            margin-bottom: 1.5rem;
        }
        .salon-library .iuc-salon-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }
        .salon-library .iuc-salon-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: #d1d5db;
            text-decoration: none;
            font-weight: 600;
        }
        .salon-library .iuc-salon-link i {
            width: 16px;
            text-align: center;
            font-size: 14px;
        }
        .salon-library .iuc-salon-link.active {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .salon-library .iuc-salon-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }
        .salon-library .iuc-salon-logout {
            margin-top: auto;
        }
        .salon-library .page-title p,
        .salon-library .breadcrumb-item.active,
        .salon-library .empty-state,
        .salon-library .file-list th {
            color: #6b7280;
        }
        .salon-library .page-title h1,
        .salon-library .breadcrumb-item a,
        .salon-library .file-name a,
        .salon-library .card-header-custom,
        .salon-library .folder-name,
        .salon-library .file-list td {
            color: #111827;
        }
        .salon-library .content-card,
        .salon-library .breadcrumb-card {
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }
        .main {
            padding: 2.5rem 3rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }
        .page-title p {
            color: var(--text-muted, #6b7280);
            margin: 0;
        }
        .breadcrumb-card {
            background: #fff;
            border-radius: 10px;
            padding: 0.75rem 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        .breadcrumb {
            margin: 0;
        }
        .breadcrumb-item a {
            text-decoration: none;
            color: var(--primary, #111827);
            font-weight: 500;
        }
        .breadcrumb-item.active {
            color: var(--text-muted, #6b7280);
        }
        .content-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .card-header-custom {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f9fafb;
        }
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
        }
        .folder-item {
            position: relative;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s;
            color: var(--text-main, #1f2937);
            display: block;
        }
        .folder-link {
            color: inherit;
            text-decoration: none;
            display: block;
        }
        .folder-delete {
            position: absolute;
            top: 8px;
            right: 8px;
        }
        .folder-delete .btn {
            padding: 2px 6px;
            font-size: 0.7rem;
            line-height: 1.2;
        }
        .folder-item:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            background: #f9fafb;
            transform: translateY(-2px);
        }
        .folder-icon {
            font-size: 2.5rem;
            color: #fbbf24;
            margin-bottom: 0.5rem;
        }
        .folder-name {
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-list {
            width: 100%;
            border-collapse: collapse;
        }
        .file-list th {
            text-align: left;
            padding: 0.75rem 1.5rem;
            background: #f9fafb;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted, #6b7280);
            border-bottom: 1px solid #e5e7eb;
        }
        .file-list td {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .file-list tr:last-child td {
            border-bottom: none;
        }
        .file-list tr:hover td {
            background-color: #f9fafb;
        }
        .file-name a {
            text-decoration: none;
            color: var(--text-main, #1f2937);
            font-weight: 500;
        }
        .file-name a:hover {
            color: #2563eb;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted, #6b7280);
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
    </style>
</head>
<body class="<?php echo trim(($isHospitality ? 'iuc-theme iuc-theme-light ' : '') . (($templateCode === 'EDUCATION') ? 'edu-sidebar-theme ' : '') . ($isSalon ? 'salon-library' : '')); ?>">
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
    <i class="fas fa-bars"></i>
</button>
<div class="layout">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <main class="main">
        
        <!-- Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Biblioteca</h1>
                <p>Gestiona y organiza tus documentos y apuntes.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                    <i class="fas fa-folder-plus"></i> Nueva carpeta
                </button>
                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                    <i class="fas fa-cloud-upload-alt"></i> Subir archivo
                </button>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($successMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <div class="breadcrumb-card">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index === count($breadcrumbs) - 1): ?>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php echo htmlspecialchars($crumb['name']); ?>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item">
                                <a href="biblioteca.php?path=<?php echo urlencode($crumb['path']); ?>">
                                    <?php echo htmlspecialchars($crumb['name']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>

        <!-- Explorador -->
        
        <!-- Sección Carpetas -->
        <div class="content-card">
            <div class="card-header-custom">
                <i class="fas fa-folder text-warning"></i> Carpetas
            </div>
            <?php if (empty($folders)): ?>
                <div class="p-4 text-center text-muted small">
                    No hay subcarpetas en esta ubicación.
                </div>
            <?php else: ?>
                <div class="folder-grid">
                    <?php foreach ($folders as $folder): 
                        $newPath = $currentPath . $folder['name'] . '/';
                    ?>
                        <div class="folder-item">
                            <a href="biblioteca.php?path=<?php echo urlencode($newPath); ?>" class="folder-link">
                                <div class="folder-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div class="folder-name" title="<?php echo htmlspecialchars($folder['name']); ?>">
                                    <?php echo htmlspecialchars($folder['name']); ?>
                                </div>
                            </a>
                            <form method="POST" class="folder-delete" onsubmit="return confirm('Eliminar esta carpeta y su contenido?');">
                                <input type="hidden" name="action" value="delete_folder">
                                <input type="hidden" name="folder_id" value="<?php echo (int) $folder['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar carpeta">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sección Archivos -->
        <div class="content-card">
            <div class="card-header-custom">
                <i class="fas fa-file-alt text-primary"></i> Archivos
            </div>
            <?php if (empty($files)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-upload"></i>
                    <p>No hay archivos en esta carpeta.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="file-list">
                        <thead>
                            <tr>
                                <th style="width: 50px;"></th>
                                <th>Nombre</th>
                                <th style="width: 120px;">Tamaño</th>
                                <th style="width: 100px;">Tipo</th>
                                <th style="width: 150px;">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td class="text-center">
                                        <i class="fas <?php echo getFileIcon($file['type']); ?> fa-lg"></i>
                                    </td>
                                    <td class="file-name">
                                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><?php echo strtoupper($file['type']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($file['uploaded_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
    </main>
</div>

<!-- Modal: Nueva Carpeta -->
<div class="modal fade" id="newFolderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST">
            <div class="modal-header">
                <h5 class="modal-title">Nueva carpeta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create_folder">
                <div class="mb-3">
                    <label class="form-label">Nombre de la carpeta</label>
                    <input type="text" name="folder_name" class="form-control" required placeholder="Ej. Matemáticas">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción (Opcional)</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear carpeta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Subir Archivo -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title">Subir archivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="upload_file">
                <div class="alert alert-info py-2 small">
                    <i class="fas fa-info-circle"></i> Máximo 20MB. Formatos permitidos: PDF, DOCX, JPG, ZIP...
                </div>
                <div class="mb-3">
                    <label class="form-label">Selecciona el archivo</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Subir</button>
            </div>
        </form>
    </div>
</div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (
                window.innerWidth <= 768 &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target) &&
                sidebar.classList.contains('active')
            ) {
                sidebar.classList.remove('active');
            }
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    }
</script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

