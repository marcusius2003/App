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
require_feature($pdo, 'edu.courses');
$userId = (int) $currentUser['id'];
$username = $currentUser['username'] ?? 'Usuario';
$userRole = $currentUser['role'] ?? 'usuario';
$academyName = $currentUser['academy_name'] ?? ($currentUser['academy'] ?? '');
$normalizedRole = strtolower((string) $userRole);
$isAdmin = in_array($normalizedRole, ['admin', 'administrator', 'administrador'], true);

if (!$isAdmin) {
    http_response_code(403);
    echo 'No tienes permiso para acceder a esta pizarra.';
    exit();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pizarra privada | Learnnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f5f7fb;
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #0f172a;
            --muted: #6b7280;
            --primary: #111827;
            --accent: #2563eb;
            --accent-soft: #dbeafe;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        .layout {
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 28px 22px;
            position: fixed;
            inset: 0 auto 0 0;
            display: flex;
            flex-direction: column;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 32px;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            color: inherit;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .sidebar-nav a.active,
        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        .sidebar-nav i {
            width: 20px;
        }
        .sidebar-user {
            margin-top: auto;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 14px;
            background: rgba(15,23,42,0.65);
        }
        .main {
            margin-left: 260px;
            padding: 36px 48px 60px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        .breadcrumb {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .top-bar h1 {
            margin: 0 0 8px;
            font-size: 2rem;
        }
        .subtitle {
            margin: 0;
            color: var(--muted);
            max-width: 620px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 600;
        }
        .whiteboard-card {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-height: 65vh;
            box-shadow: 0 20px 40px rgba(15,23,42,0.08);
            overflow: hidden;
        }
        .whiteboard-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            padding: 20px 24px;
            background: #f3f4f6;
            border-bottom: 1px solid var(--border);
        }
        .toolbar-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 110px;
        }
        .toolbar-title {
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .tool-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .tool-button {
            width: 72px;
            height: 64px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .tool-button:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(15,23,42,0.08);
        }
        .tool-button.active {
            border-color: var(--primary);
            background: var(--accent-soft);
            box-shadow: inset 0 0 0 1px var(--primary);
        }
        .tool-button.secondary {
            width: auto;
            min-width: 120px;
            padding: 0 16px;
        }
        .reference-group {
            flex: 1;
            min-width: 260px;
        }
        .reference-stack {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .reference-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .reference-hint {
            font-size: 0.8rem;
            color: var(--muted);
        }
        .color-picker {
            width: 70px;
            height: 44px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            cursor: pointer;
        }
        .thickness-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .thickness-control input {
            width: 170px;
        }
        .thickness-value {
            font-size: 0.85rem;
            color: var(--muted);
            min-width: 50px;
            text-align: right;
        }
        .whiteboard-canvas {
            flex: 1;
            background: #e5e7eb;
            padding: 22px;
        }
        .canvas-board {
            width: 100%;
            height: 100%;
            border-radius: 22px;
            border: 1px solid var(--border);
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        .canvas-board.grid-active::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(transparent 95%, rgba(37,99,235,0.15) 100%),
                linear-gradient(90deg, transparent 95%, rgba(37,99,235,0.15) 100%);
            background-size: 40px 40px;
        }
        #whiteboardCanvas {
            width: 100%;
            height: 100%;
            display: block;
            background: #fff;
            position: relative;
            z-index: 1;
        }
        .reference-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .reference-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 12px;
            background: #e0e7ff;
            color: #312e81;
            font-size: 0.85rem;
        }
        .reference-chip small {
            font-size: 0.75rem;
            color: #4b5563;
        }
        .canvas-board.dragover {
            outline: 2px dashed var(--accent);
            outline-offset: -10px;
        }
        @media (max-width: 900px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            .main {
                margin-left: 0;
                padding: 24px;
            }
        }
        @media (max-width: 640px) {
            .whiteboard-toolbar {
                flex-direction: column;
            }
            .tool-button,
            .color-picker,
            .thickness-control input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>LEARNNECT</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="calendar.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendario</span>
            </a>
            <a href="biblioteca.php">
                <i class="fas fa-book"></i>
                <span>Biblioteca</span>
            </a>
            <a href="tickets.php">
                <i class="fas fa-ticket-alt"></i>
                <span>Tickets</span>
            </a>
            <a href="soporte.php">
                <i class="fas fa-headset"></i>
                <span>Soporte</span>
            </a>
            <a href="messages.php">
                <i class="fas fa-comments"></i>
                <span>Mensajes</span>
            </a>
            <?php if ($isAdmin): ?>
            <a href="pizarra.php" class="active">
                <i class="fas fa-chalkboard"></i>
                <span>Pizarra</span>
            </a>
            <?php endif; ?>
            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Configuracion</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar sesion</span>
            </a>
        </nav>
        <div class="sidebar-user">
            <div><?php echo e($username); ?></div>
            <small><?php echo e(ucfirst($userRole)); ?></small>
        </div>
    </aside>
    <main class="main">
        <div class="top-bar">
            <div class="chip">
                <i class="fas fa-user-shield"></i>
                Admin: <?php echo e($username); ?>
            </div>
        </div>
        <div class="whiteboard-card">
            <div class="whiteboard-toolbar">
                <div class="toolbar-group">
                    <p class="toolbar-title">Herramientas</p>
                    <div class="tool-buttons">
                        <button type="button" class="tool-button active" id="toolPencil">
                            <i class="fas fa-pen"></i>
                            <span>Lapiz</span>
                        </button>
                        <button type="button" class="tool-button" id="toolEraser">
                            <i class="fas fa-eraser"></i>
                            <span>Goma</span>
                        </button>
                    </div>
                </div>
                <div class="toolbar-group">
                    <p class="toolbar-title">Color</p>
                    <input type="color" id="boardColor" class="color-picker" value="#111827">
                </div>
                <div class="toolbar-group">
                    <p class="toolbar-title">Grosor</p>
                    <div class="thickness-control">
                        <input type="range" id="boardThickness" min="1" max="30" value="4">
                        <span class="thickness-value" id="boardThicknessValue">4 px</span>
                    </div>
                </div>
                <div class="toolbar-group">
                    <p class="toolbar-title">Acciones</p>
                    <div class="tool-buttons">
                        <button type="button" class="tool-button" id="boardClear">
                            <i class="fas fa-broom"></i>
                            <span>Limpiar</span>
                        </button>
                        <button type="button" class="tool-button" id="boardDownload">
                            <i class="fas fa-download"></i>
                            <span>Guardar</span>
                        </button>
                    </div>
                </div>
                <div class="toolbar-group reference-group">
                    <p class="toolbar-title">Referencias</p>
                    <div class="tool-buttons">
                        <button type="button" class="tool-button" id="boardImageBtn">
                            <i class="fas fa-file-image"></i>
                            <span>Adjuntar</span>
                        </button>
                        <button type="button" class="tool-button secondary" id="boardCopyBtn">
                            <i class="fas fa-copy"></i>
                            <span>Copiar lienzo</span>
                        </button>
                        <button type="button" class="tool-button secondary" id="boardGridBtn">
                            <i class="fas fa-border-all"></i>
                            <span>Rejilla</span>
                        </button>
                    </div>
                    <input type="file" id="boardImageInput" accept="image/*" hidden>
                    <div class="reference-stack">
                        <strong>Referencias recientes</strong>
                        <p class="reference-hint">Puedes pegar contenido desde el portapapeles o soltar archivos sobre la pizarra.</p>
                        <div id="referenceList" class="reference-list">
                            <span class="reference-hint">Sin referencias todavía.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="whiteboard-canvas" id="canvasWrapper">
                <div class="canvas-board">
                    <canvas id="whiteboardCanvas"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
(function () {
    const canvas = document.getElementById('whiteboardCanvas');
    const wrapper = document.getElementById('canvasWrapper');
    const ctx = canvas.getContext('2d');
    const colorInput = document.getElementById('boardColor');
    const thicknessInput = document.getElementById('boardThickness');
    const thicknessValue = document.getElementById('boardThicknessValue');
    const pencilBtn = document.getElementById('toolPencil');
    const eraserBtn = document.getElementById('toolEraser');
    const clearBtn = document.getElementById('boardClear');
    const downloadBtn = document.getElementById('boardDownload');
    const imageBtn = document.getElementById('boardImageBtn');
    const imageInput = document.getElementById('boardImageInput');
    const referenceList = document.getElementById('referenceList');
    const canvasBoard = document.querySelector('.canvas-board');
    const copyBtn = document.getElementById('boardCopyBtn');
    const gridBtn = document.getElementById('boardGridBtn');
    let drawing = false;
    let lastX = 0;
    let lastY = 0;
    let mode = 'pencil';
    let references = [];
    const MAX_REFERENCES = 5;
    const MAX_IMAGE_SIZE = 15 * 1024 * 1024; // 15 MB

    function updateThicknessLabel() {
        if (thicknessValue) {
            thicknessValue.textContent = thicknessInput.value + ' px';
        }
    }

    function resizeCanvas(preserve = true) {
        if (!wrapper) return;
        const rect = wrapper.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        let snapshot = null;
        if (preserve) {
            try {
                snapshot = canvas.toDataURL('image/png');
            } catch (error) {
                snapshot = null;
            }
        }
        canvas.width = rect.width - 2;
        canvas.height = rect.height - 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (snapshot) {
            const image = new Image();
            image.onload = () => ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
            image.src = snapshot;
        }
    }

    function setActiveTool() {
        if (pencilBtn) {
            pencilBtn.classList.toggle('active', mode === 'pencil');
        }
        if (eraserBtn) {
            eraserBtn.classList.toggle('active', mode === 'eraser');
        }
    }

    function getPointerPosition(event) {
        const rect = canvas.getBoundingClientRect();
        let clientX;
        let clientY;
        if (event.touches && event.touches.length) {
            clientX = event.touches[0].clientX;
            clientY = event.touches[0].clientY;
        } else {
            clientX = event.clientX;
            clientY = event.clientY;
        }
        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }

    function startDrawing(event) {
        event.preventDefault();
        drawing = true;
        const pos = getPointerPosition(event);
        lastX = pos.x;
        lastY = pos.y;
    }

    function draw(event) {
        if (!drawing) return;
        event.preventDefault();
        const pos = getPointerPosition(event);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = mode === 'eraser' ? '#ffffff' : colorInput.value;
        ctx.lineWidth = parseInt(thicknessInput.value, 10) || 4;
        ctx.stroke();
        lastX = pos.x;
        lastY = pos.y;
    }

    function stopDrawing(event) {
        if (event) {
            event.preventDefault();
        }
        drawing = false;
        ctx.beginPath();
    }

    function clearBoard() {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    function downloadBoard() {
        const link = document.createElement('a');
        link.download = 'pizarra-learnnect.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    function copyBoardToClipboard() {
        if (!navigator.clipboard || !navigator.clipboard.write) {
            alert('Tu navegador no permite copiar directamente el lienzo al portapapeles.');
            return;
        }
        canvas.toBlob((blob) => {
            if (!blob) {
                alert('No se pudo copiar el lienzo en este momento.');
                return;
            }
            const item = new ClipboardItem({ [blob.type]: blob });
            navigator.clipboard.write([item]).then(
                () => alert('Lienzo copiado al portapapeles.'),
                () => alert('No se pudo copiar el lienzo. Comprueba los permisos del navegador.')
            );
        }, 'image/png');
    }

    function formatBytes(bytes) {
        if (!bytes) return '';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let value = bytes;
        while (value >= 1024 && i < units.length - 1) {
            value /= 1024;
            i += 1;
        }
        return `${value.toFixed(value < 10 ? 1 : 0)} ${units[i]}`;
    }

    function renderReferences() {
        if (!referenceList) {
            return;
        }
        if (!references.length) {
            referenceList.innerHTML = '<span class="reference-hint">Sin referencias todavía.</span>';
            return;
        }
        const fragment = document.createDocumentFragment();
        references.forEach((ref) => {
            const chip = document.createElement('span');
            chip.className = 'reference-chip';
            const icon = document.createElement('i');
            icon.className = 'fas fa-image';
            const name = document.createElement('span');
            name.textContent = ref.name;
            const size = document.createElement('small');
            size.textContent = formatBytes(ref.size);
            chip.appendChild(icon);
            chip.appendChild(name);
            if (ref.size) {
                chip.appendChild(size);
            }
            fragment.appendChild(chip);
        });
        referenceList.innerHTML = '';
        referenceList.appendChild(fragment);
    }

    function addReference(file) {
        references.unshift({
            name: file && file.name ? file.name : 'Referencia sin nombre',
            size: file && file.size ? file.size : 0,
        });
        if (references.length > MAX_REFERENCES) {
            references = references.slice(0, MAX_REFERENCES);
        }
        renderReferences();
    }

    function insertImageFromFile(file) {
        if (!file) {
            return;
        }
        if (!file.type.startsWith('image/')) {
            alert('Solo puedes adjuntar archivos de imagen (png, jpg, gif).');
            return;
        }
        if (file.size > MAX_IMAGE_SIZE) {
            alert('La imagen es demasiado grande. El límite es de 15MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const image = new Image();
            image.onload = () => {
                const scale = Math.min(canvas.width / image.width, canvas.height / image.height, 1);
                const targetWidth = image.width * scale;
                const targetHeight = image.height * scale;
                const x = (canvas.width - targetWidth) / 2;
                const y = (canvas.height - targetHeight) / 2;
                ctx.drawImage(image, x, y, targetWidth, targetHeight);
                addReference(file);
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    }

    function handleFileList(fileList) {
        if (!fileList || !fileList.length) {
            return;
        }
        Array.from(fileList).forEach((file) => insertImageFromFile(file));
    }

    function handlePaste(event) {
        const clipboard = event.clipboardData;
        if (!clipboard || !clipboard.items) {
            return;
        }
        for (let i = 0; i < clipboard.items.length; i += 1) {
            const item = clipboard.items[i];
            if (item.type && item.type.startsWith('image/')) {
                const file = item.getAsFile();
                if (file) {
                    insertImageFromFile(file);
                    break;
                }
            }
        }
    }

    pencilBtn.addEventListener('click', () => {
        mode = 'pencil';
        setActiveTool();
    });
    eraserBtn.addEventListener('click', () => {
        mode = 'eraser';
        setActiveTool();
    });
    clearBtn.addEventListener('click', clearBoard);
    downloadBtn.addEventListener('click', downloadBoard);
    thicknessInput.addEventListener('input', updateThicknessLabel);
    if (imageBtn) {
        imageBtn.addEventListener('click', () => imageInput && imageInput.click());
    }
    if (imageInput) {
        imageInput.addEventListener('change', () => {
            handleFileList(imageInput.files);
            imageInput.value = '';
        });
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);
    canvas.addEventListener('touchstart', startDrawing, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('touchcancel', stopDrawing);

    window.addEventListener('resize', () => resizeCanvas(true));
    document.addEventListener('paste', handlePaste);
    if (copyBtn) {
        copyBtn.addEventListener('click', copyBoardToClipboard);
    }
    if (gridBtn) {
        gridBtn.addEventListener('click', () => {
            canvasBoard.classList.toggle('grid-active');
            gridBtn.classList.toggle('active', canvasBoard.classList.contains('grid-active'));
        });
    }
    if (canvasBoard) {
        ['dragenter', 'dragover'].forEach((eventName) => {
            canvasBoard.addEventListener(eventName, (event) => {
                event.preventDefault();
                canvasBoard.classList.add('dragover');
            });
        });
        ['dragleave', 'dragend'].forEach((eventName) => {
            canvasBoard.addEventListener(eventName, (event) => {
                event.preventDefault();
                canvasBoard.classList.remove('dragover');
            });
        });
        canvasBoard.addEventListener('drop', (event) => {
            event.preventDefault();
            canvasBoard.classList.remove('dragover');
            handleFileList(event.dataTransfer.files);
        });
    }

    resizeCanvas(false);
    updateThicknessLabel();
    setActiveTool();
    renderReferences();
})();
</script>
</body>
</html>
