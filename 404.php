<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada | Learnnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #000000;
            --accent: #6366f1;
            --accent-light: #818cf8;
            --accent-glow: rgba(99, 102, 241, 0.25);
            --text-primary: #0f0f0f;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --bg-light: #ffffff;
            --bg-subtle: #f9fafb;
            --border: rgba(0, 0, 0, 0.08);
            --border-dark: rgba(0, 0, 0, 0.12);
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Subtle animated background */
        .bg-animation {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            width: 120vmax;
            height: 120vmax;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            animation: float 25s ease-in-out infinite;
        }

        .bg-gradient-1 {
            top: -40%;
            left: -20%;
            background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
        }

        .bg-gradient-2 {
            bottom: -50%;
            right: -30%;
            background: radial-gradient(circle, #a855f7 0%, transparent 70%);
            animation-delay: -10s;
        }

        .bg-gradient-3 {
            top: 30%;
            right: -10%;
            width: 60vmax;
            height: 60vmax;
            background: radial-gradient(circle, #06b6d4 0%, transparent 70%);
            animation-delay: -18s;
            opacity: 0.1;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(3%, 3%) scale(1.03); }
            50% { transform: translate(-2%, 5%) scale(0.97); }
            75% { transform: translate(-3%, -2%) scale(1.01); }
        }

        /* Grid pattern overlay */
        .grid-overlay {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(0,0,0,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 1;
        }

        /* Main content */
        .container {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }

        /* Logo */
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 3rem;
            text-decoration: none;
            opacity: 0;
            animation: fadeUp 0.8s ease forwards;
            animation-delay: 0.2s;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 8px 24px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255,255,255,0.1);
        }

        .brand-icon-text {
            color: #fff;
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: 0.05em;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }

        /* Error code */
        .error-code {
            font-size: clamp(8rem, 25vw, 14rem);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.05em;
            color: var(--text-primary);
            position: relative;
            margin-bottom: 1rem;
            opacity: 0;
            animation: fadeUp 0.8s ease forwards;
            animation-delay: 0.4s;
        }

        .error-code::before {
            content: '404';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, var(--accent) 0%, var(--accent-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: blur(50px);
            opacity: 0.3;
            z-index: -1;
        }

        /* Glitch effect */
        .error-code::after {
            content: '404';
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            color: var(--accent);
            opacity: 0;
            animation: glitch 4s ease-in-out infinite;
        }

        @keyframes glitch {
            0%, 90%, 100% { opacity: 0; transform: translate(0, 0); }
            92% { opacity: 0.6; transform: translate(-4px, 2px); }
            94% { opacity: 0; transform: translate(4px, -2px); }
            96% { opacity: 0.4; transform: translate(2px, 2px); }
            98% { opacity: 0; }
        }

        /* Text content */
        .error-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            opacity: 0;
            animation: fadeUp 0.8s ease forwards;
            animation-delay: 0.6s;
        }

        .error-description {
            font-size: 1.1rem;
            color: var(--text-secondary);
            line-height: 1.7;
            max-width: 440px;
            margin: 0 auto 2.5rem;
            opacity: 0;
            animation: fadeUp 0.8s ease forwards;
            animation-delay: 0.7s;
        }

        /* Buttons */
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            opacity: 0;
            animation: fadeUp 0.8s ease forwards;
            animation-delay: 0.9s;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        }

        .btn-primary span {
            position: relative;
            z-index: 1;
        }

        .btn-secondary {
            background: var(--bg-subtle);
            color: var(--text-primary);
            border: 1px solid var(--border-dark);
        }

        .btn-secondary:hover {
            background: #f3f4f6;
            border-color: rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* Icon in button */
        .btn-icon {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }

        /* Additional links */
        .links {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            opacity: 0;
            animation: fadeUp 0.8s ease forwards;
            animation-delay: 1.1s;
        }

        .links-title {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: 1rem;
        }

        .links-list {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
        }

        .link {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: color 0.3s ease;
        }

        .link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -4px;
            width: 0;
            height: 2px;
            background: var(--primary);
            border-radius: 1px;
            transition: width 0.3s ease;
        }

        .link:hover {
            color: var(--text-primary);
        }

        .link:hover::after {
            width: 100%;
        }

        /* Floating particles */
        .particles {
            position: fixed;
            inset: 0;
            z-index: 3;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--accent);
            border-radius: 50%;
            opacity: 0.2;
            animation: rise 18s linear infinite;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 14s; }
        .particle:nth-child(2) { left: 25%; animation-delay: 2s; animation-duration: 20s; }
        .particle:nth-child(3) { left: 40%; animation-delay: 4s; animation-duration: 16s; }
        .particle:nth-child(4) { left: 55%; animation-delay: 1s; animation-duration: 18s; }
        .particle:nth-child(5) { left: 70%; animation-delay: 3s; animation-duration: 15s; }
        .particle:nth-child(6) { left: 85%; animation-delay: 5s; animation-duration: 19s; }

        @keyframes rise {
            0% { bottom: -10px; opacity: 0; }
            10% { opacity: 0.2; }
            90% { opacity: 0.2; }
            100% { bottom: 110vh; opacity: 0; }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 640px) {
            .container {
                padding: 1.5rem;
            }

            .brand {
                margin-bottom: 2rem;
            }

            .error-title {
                font-size: 1.4rem;
            }

            .error-description {
                font-size: 1rem;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                justify-content: center;
            }

            .links-list {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Subtle animated background -->
    <div class="bg-animation">
        <div class="bg-gradient bg-gradient-1"></div>
        <div class="bg-gradient bg-gradient-2"></div>
        <div class="bg-gradient bg-gradient-3"></div>
    </div>
    <div class="grid-overlay"></div>

    <!-- Floating particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Main content -->
    <div class="container">
        <a href="/iuconnect/index.php" class="brand">
            <span class="brand-icon">
                <span class="brand-icon-text">IU</span>
            </span>
            <span class="brand-name">Learnnect</span>
        </a>

        <h1 class="error-code">404</h1>
        
        <h2 class="error-title">Página no encontrada</h2>
        <p class="error-description">
            Lo sentimos, la página que buscas no existe o ha sido movida. 
            Verifica la URL o regresa al inicio para continuar navegando.
        </p>

        <div class="actions">
            <a href="/iuconnect/index.php" class="btn btn-primary">
                <span>Ir al inicio</span>
                <svg class="btn-icon" viewBox="0 0 24 24">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="/iuconnect/dashboard.php" class="btn btn-secondary">
                <svg class="btn-icon" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Panel de control
            </a>
        </div>

        <div class="links">
            <div class="links-title">Enlaces útiles</div>
            <div class="links-list">
                <a href="/iuconnect/dashboard.php" class="link">Panel de Learnnect</a>
                <a href="/iuconnect/soporte.php" class="link">Centro de soporte</a>
                <a href="/iuconnect/index.php#contacto" class="link">Contacto</a>
            </div>
        </div>
    </div>
</body>
</html>

