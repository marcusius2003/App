<?php
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>IUC | Intranet Empresarial en 7 Días - 97€</title>
    <meta name="description" content="Implementación completa de intranet en solo 7 días por 97€. IA integrada, soporte 24/7 y garantía de lanzamiento." />
    
    <!-- CDNs Mejorados -->
    <script src="/assets/vendor/tailwindcss.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-glow': 'pulse-glow 2s ease-in-out infinite',
                        'shimmer': 'shimmer 2s infinite',
                        'slide-in': 'slide-in 0.5s ease-out',
                        'bounce-slow': 'bounce-slow 3s infinite',
                        'gradient': 'gradient 8s ease infinite',
                        'text-shimmer': 'text-shimmer 2s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        'pulse-glow': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.7 },
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% center' },
                            '100%': { backgroundPosition: '200% center' },
                        },
                        'slide-in': {
                            '0%': { transform: 'translateY(30px)', opacity: 0 },
                            '100%': { transform: 'translateY(0)', opacity: 1 },
                        },
                        'bounce-slow': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                        gradient: {
                            '0%, 100%': { backgroundPosition: '0% 50%' },
                            '50%': { backgroundPosition: '100% 50%' },
                        },
                        'text-shimmer': {
                            '0%': { backgroundPosition: '200% center' },
                            '100%': { backgroundPosition: '-200% center' },
                        },
                    },
                }
            }
        }
    </script>
    <script src="/assets/vendor/react.production.min.js"></script>
    <script src="/assets/vendor/react-dom.production.min.js"></script>
    <script src="/assets/vendor/babel.min.js"></script>
    
    <!-- Fonts Premium -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-black: #000000;
            --secondary-black: #0a0a0a;
            --accent-black: #1a1a1a;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --dark-gray: #666666;
            --pure-white: #ffffff;
            --neon-glow: #00ff88;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--pure-white);
            color: var(--primary-black);
            overflow-x: hidden;
            cursor: default;
        }
        
        /* Hero Section Ultra */
        .hero-section {
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 255, 136, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 0, 0, 0.05) 0%, transparent 50%),
                linear-gradient(135deg, var(--pure-white) 0%, var(--light-gray) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-grid {
            background-image: 
                linear-gradient(to right, rgba(0,0,0,0.02) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0,0,0,0.02) 1px, transparent 1px);
            background-size: 50px 50px;
        }
        
        /* Cards Neo-Brutalist */
        .neo-card {
            background: var(--pure-white);
            border: 2px solid var(--primary-black);
            box-shadow: 8px 8px 0 var(--primary-black);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .neo-card:hover {
            transform: translate(-4px, -4px);
            box-shadow: 12px 12px 0 var(--primary-black);
        }
        
        /* Glass Effect Premium */
        .glass-premium {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }
        
        /* Shimmer Effect */
        .shimmer-button {
            background: linear-gradient(
                90deg,
                var(--primary-black) 0%,
                var(--accent-black) 25%,
                var(--primary-black) 50%,
                var(--accent-black) 75%,
                var(--primary-black) 100%
            );
            background-size: 200% auto;
            animation: shimmer 2s infinite linear;
        }
        
        /* Floating Elements */
        .floating-element {
            animation: float 6s ease-in-out infinite;
            will-change: transform;
        }
        
        .floating-element:nth-child(2n) {
            animation-delay: 1s;
        }
        
        .floating-element:nth-child(3n) {
            animation-delay: 2s;
        }
        
        /* Process Timeline */
        .process-line {
            height: 2px;
            background: linear-gradient(90deg, 
                var(--primary-black) 0%, 
                transparent 50%, 
                var(--primary-black) 100%);
            position: relative;
        }
        
        .process-dot {
            width: 12px;
            height: 12px;
            background: var(--pure-white);
            border: 2px solid var(--primary-black);
            border-radius: 50%;
            position: absolute;
            top: -5px;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light-gray);
            border-radius: 6px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary-black), var(--accent-black));
            border-radius: 6px;
            border: 3px solid var(--light-gray);
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--accent-black), var(--primary-black));
        }
        
        /* Text Effects */
        .text-gradient-premium {
            background: linear-gradient(135deg, 
                var(--primary-black) 0%, 
                var(--dark-gray) 25%, 
                var(--primary-black) 50%, 
                var(--dark-gray) 75%, 
                var(--primary-black) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: text-shimmer 4s linear infinite;
        }
        
        .text-stroke {
            color: transparent;
            -webkit-text-stroke: 1px var(--primary-black);
        }
        
        /* Grid Pattern */
        .grid-pattern {
            background-image: 
                linear-gradient(to right, rgba(0,0,0,0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0,0,0,0.05) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        
        /* Selection Styling */
        ::selection {
            background: var(--primary-black);
            color: var(--pure-white);
        }
        
        /* Custom Animations */
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Hover Effects */
        .hover-3d {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .hover-3d:hover {
            transform: perspective(1000px) rotateX(5deg) rotateY(5deg) translateZ(20px);
        }
        
        /* Background Particles */
        .particles-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }
        
        .particle {
            position: absolute;
            background: var(--primary-black);
            border-radius: 50%;
            opacity: 0.1;
            animation: float 15s infinite linear;
        }
        
        /* Responsive Typography */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 3.5rem !important;
                line-height: 1 !important;
            }
            
            .section-title {
                font-size: 2.5rem !important;
            }
        }
        
        /* Loading Animation */
        .loading-dots {
            display: inline-block;
        }
        
        .loading-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-black);
            margin: 0 2px;
            animation: bounce-slow 1.5s infinite ease-in-out;
        }
        
        .loading-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .loading-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        /* Offer Table */
        .offer-table th,
        .offer-table td {
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        
        .offer-table td {
            line-height: 1.5;
        }
        
        .offer-table td:first-child {
            white-space: normal;
        }
        
        .offer-table td:nth-child(4) span {
            max-width: 180px;
            white-space: normal;
            text-align: center;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body class="selection:bg-black selection:text-white">
    <div id="root">
        <div id="static-fallback" style="font-family: Inter, system-ui, -apple-system, Segoe UI, sans-serif; color: #111; background: #fff;">
            <div style="max-width: 960px; margin: 0 auto; padding: 56px 16px;">
                <h1 style="margin: 0 0 10px; font-size: 42px; line-height: 1.1; font-weight: 900;">IUConnect</h1>
                <p style="margin: 0 0 18px; color: #444; font-size: 16px;">
                    Cargando la landing...
                </p>
                <div id="boot-debug" style="margin: 0 0 18px; padding: 10px 12px; background: #f5f5f5; border: 1px solid rgba(0,0,0,.10); border-radius: 12px; color: #333; font-size: 13px; white-space: pre-wrap;">
                    Estado: iniciando...
                </div>
                <p style="margin: 0 0 18px; color: #555; font-size: 14px;">
                    Si esto no desaparece, hay un problema cargando JavaScript local. Puedes entrar por:
                    <a href="login.php" style="color: #111; font-weight: 800; text-decoration: underline;">Iniciar sesi&oacute;n</a>
                </p>
            </div>
        </div>
        <div id="app"></div>
    </div>

    <script type="text/babel">
        const { useState, useEffect, useRef } = React;

        // --- DATA MEJORADA ---
        const FEATURES = [
            { 
                id: '1', 
                title: 'Gestión de Usuarios', 
                description: 'Control de acceso multi-nivel con roles personalizados y permisos granulares.', 
                icon: 'users',
                stats: '+95% eficiencia'
            },
            { 
                id: '2', 
                title: 'Comunicación Inteligente', 
                description: 'Chat en tiempo real, canales por equipo y videollamadas integradas.', 
                icon: 'chat',
                stats: '0 retrasos'
            },
            { 
                id: '3', 
                title: 'Documentación Central', 
                description: 'Drive empresarial con versionado automático y control de cambios.', 
                icon: 'docs',
                stats: '100% organizado'
            },
            { 
                id: '4', 
                title: 'Formación Digital', 
                description: 'Plataforma de cursos, evaluaciones automáticas y certificaciones.', 
                icon: 'training',
                stats: '+80% engagement'
            },
            { 
                id: '5', 
                title: 'Soporte 24/7', 
                description: 'Sistema de tickets, base de conocimiento y soporte prioritario.', 
                icon: 'support',
                stats: '30min respuesta'
            },
            { 
                id: '6', 
                title: 'IA Empresarial', 
                description: 'Asistente virtual, análisis predictivo y automatización de procesos.', 
                icon: 'ai',
                stats: '40% más rápido'
            },
        ];

        const EDUCATION_PLANS = [
            { 
                name: 'Essentials', 
                price: '149€', 
                yearlyPrice: '1.490€', 
                description: 'Perfecto para startups y pequeños equipos.', 
                features: ['Hasta 200 usuarios', 'Dashboard premium', 'Comunicación básica', 'Soporte 12/5', 'Setup 72h'],
                cta: 'Comenzar prueba',
                popular: false,
                highlight: 'Ideal para comenzar'
            },
            { 
                name: 'Professional', 
                price: '249€', 
                yearlyPrice: '2.490€', 
                description: 'Para empresas en crecimiento con necesidades avanzadas.', 
                features: ['Hasta 500 usuarios', 'IA básica incluida', 'Analíticas avanzadas', 'Soporte 24/7', 'Garantía 7 días'],
                cta: 'Elegir plan',
                popular: true,
                highlight: 'MÁS POPULAR'
            },
            { 
                name: 'Enterprise', 
                price: '399€', 
                yearlyPrice: '4.790€', 
                description: 'Solución completa para grandes organizaciones.', 
                features: ['Usuarios ilimitados', 'IA Pro avanzada', 'API personalizada', 'Soporte VIP', 'Migración gratis'],
                cta: 'Contactar ventas',
                popular: false,
                highlight: 'Máxima escala'
            },
        ];

        const INDUSTRY_KITS = [
            { 
                id: 'education', 
                title: 'Educación', 
                description: 'Plataforma académica completa con gestión de cursos, evaluaciones y comunicación estudiantil.', 
                modules: ['Aulas virtuales', 'Evaluación automática', 'Biblioteca digital', 'Foros académicos'],
                icon: 'education',
                color: 'from-black to-gray-900',
                image: 'assets/img/industry-education.png'
            },
            { 
                id: 'restaurant', 
                title: 'Hostelería', 
                description: 'Gestión de turnos, inventario y comunicación interna para restaurantes y hoteles.', 
                modules: ['Gestión de turnos', 'Control de inventario', 'Comunicación equipo', 'Checklists digitales'],
                icon: 'hospitality',
                color: 'from-black to-gray-800',
                image: 'assets/img/industry-hosteleria.png'
            },
            { 
                id: 'services', 
                title: 'Servicios', 
                description: 'Plataforma para empresas de servicios con gestión de proyectos y clientes.', 
                modules: ['Gestión de proyectos', 'Facturación integrada', 'CRM interno', 'Documentación cliente'],
                icon: 'services',
                color: 'from-black to-gray-700',
                image: 'assets/img/industry-services.png'
            },
        ];

        const PROCESS = [
            { 
                day: 'Día 1', 
                title: 'Descubrimiento', 
                description: 'Análisis detallado de necesidades y configuración inicial.',
                icon: 'discovery'
            },
            { 
                day: 'Día 2-3', 
                title: 'Implementación', 
                description: 'Despliegue de infraestructura y configuración personalizada.',
                icon: 'implementation'
            },
            { 
                day: 'Día 4', 
                title: 'Branding', 
                description: 'Personalización visual e identidad corporativa.',
                icon: 'branding'
            },
            { 
                day: 'Día 5', 
                title: 'Capacitación', 
                description: 'Formación de administradores y usuarios clave.',
                icon: 'enablement'
            },
            { 
                day: 'Día 6', 
                title: 'Pruebas', 
                description: 'Testing exhaustivo y ajustes finales.',
                icon: 'testing'
            },
            { 
                day: 'Día 7', 
                title: 'Lanzamiento', 
                description: 'Puesta en producción con soporte 24/7 activado.',
                icon: 'launch'
            },
        ];

        const STATS = [
            { value: '7', label: 'Días de implementación', suffix: '' },
            { value: '97', label: 'Precio de setup', suffix: '€' },
            { value: '100', label: 'Tasa de éxito', suffix: '%' },
            { value: '24/7', label: 'Soporte activo', suffix: '' },
            { value: '7', label: 'Días de garantía', suffix: '' },
        ];

        // --- COMPONENTS MEJORADOS ---

        const Icon = ({ name, className = 'w-6 h-6' }) => {
            const emojiProps = {
                className,
                role: 'img',
            };

            switch (name) {
                case 'users':
                    return <span {...emojiProps} aria-label="Usuarios">&#128101;</span>;
                case 'chat':
                    return <span {...emojiProps} aria-label="Chat">&#128172;</span>;
                case 'docs':
                    return <span {...emojiProps} aria-label="Documentación">&#128196;</span>;
                case 'training':
                    return <span {...emojiProps} aria-label="Formación">&#127891;</span>;
                case 'support':
                    return <span {...emojiProps} aria-label="Soporte">&#128295;</span>;
                case 'ai':
                    return <span {...emojiProps} aria-label="IA">&#129302;</span>;
                case 'education':
                    return <span {...emojiProps} aria-label="Educación">&#127979;</span>;
                case 'hospitality':
                    return <span {...emojiProps} aria-label="Hostelería">&#127976;</span>;
                case 'services':
                    return <span {...emojiProps} aria-label="Servicios">&#129513;</span>;
                case 'discovery':
                    return <span {...emojiProps} aria-label="Descubrimiento">&#128269;</span>;
                case 'implementation':
                    return <span {...emojiProps} aria-label="Implementación">&#129521;</span>;
                case 'branding':
                    return <span {...emojiProps} aria-label="Branding">&#127912;</span>;
                case 'enablement':
                    return <span {...emojiProps} aria-label="Capacitación">&#9989;</span>;
                case 'testing':
                    return <span {...emojiProps} aria-label="Pruebas">&#129514;</span>;
                case 'launch':
                    return <span {...emojiProps} aria-label="Lanzamiento">&#128640;</span>;
                case 'x':
                    return <span {...emojiProps} aria-label="X">X</span>;
                case 'instagram':
                    return <span {...emojiProps} aria-label="Instagram">&#128248;</span>;
                case 'linkedin':
                    return <span {...emojiProps} aria-label="LinkedIn">&#128188;</span>;
                default:
                    return null;
            }
        };

        const ParticlesBackground = () => {
            useEffect(() => {
                const container = document.getElementById('particles');
                if (!container) return;

                const particles = [];
                const particleCount = 50;

                for (let i = 0; i < particleCount; i++) {
                    const particle = document.createElement('div');
                    particle.className = 'particle';
                    
                    const size = Math.random() * 4 + 1;
                    const posX = Math.random() * 100;
                    const posY = Math.random() * 100;
                    const duration = Math.random() * 20 + 10;
                    const delay = Math.random() * 5;
                    
                    particle.style.width = `${size}px`;
                    particle.style.height = `${size}px`;
                    particle.style.left = `${posX}%`;
                    particle.style.top = `${posY}%`;
                    particle.style.animationDuration = `${duration}s`;
                    particle.style.animationDelay = `${delay}s`;
                    particle.style.opacity = Math.random() * 0.1 + 0.05;
                    
                    container.appendChild(particle);
                    particles.push(particle);
                }

                return () => {
                    particles.forEach(p => p.remove());
                };
            }, []);

            return <div id="particles" className="particles-container" />;
        };

        const Navbar = () => {
            const [isScrolled, setIsScrolled] = useState(false);
            const [isMenuOpen, setIsMenuOpen] = useState(false);

            useEffect(() => {
                const handleScroll = () => setIsScrolled(window.scrollY > 50);
                window.addEventListener('scroll', handleScroll);
                return () => window.removeEventListener('scroll', handleScroll);
            }, []);

            return (
                <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${isScrolled ? 'py-4 glass-premium' : 'py-6 bg-transparent'}`}>
                    <div className="container mx-auto px-4 md:px-8 flex items-center justify-between">
                        <a href="#" className="flex items-center gap-3 group">
                            <div className="relative">
                                <div className="w-12 h-12 bg-black rounded-2xl flex items-center justify-center group-hover:rotate-12 transition-transform duration-500">
                                    <span className="text-white font-black text-base">IUC</span>
                                </div>
                                <div className="absolute -inset-1 bg-black/10 blur-xl rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-xl font-black tracking-tight text-black">IUC</span>
                                <span className="text-[10px] font-medium uppercase tracking-widest text-gray-500">v3.0</span>
                            </div>
                        </a>

                        <div className="hidden lg:flex items-center gap-8">
                            <div className="flex items-center gap-8">
                                <a href="#features" className="text-sm font-semibold text-gray-600 hover:text-black transition-all duration-300 hover:scale-105">Características</a>
                                <a href="#industry" className="text-sm font-semibold text-gray-600 hover:text-black transition-all duration-300 hover:scale-105">Sectores</a>
                                <a href="#process" className="text-sm font-semibold text-gray-600 hover:text-black transition-all duration-300 hover:scale-105">Proceso</a>
                                <a href="#pricing" className="text-sm font-semibold text-gray-600 hover:text-black transition-all duration-300 hover:scale-105">Planes</a>
                                <a href="#testimonials" className="text-sm font-semibold text-gray-600 hover:text-black transition-all duration-300 hover:scale-105">Casos</a>
                            </div>
                            
                            <div className="flex items-center gap-4">
	                                <a href="login.php" className="flex items-center gap-2 px-4 py-2 rounded-lg border border-black/10 hover:border-black/30 transition-all duration-300">
	                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
	                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
	                                    </svg>
	                                    <span className="text-sm font-semibold">Acceso</span>
	                                </a>
                                <a href="#contact" className="shimmer-button px-6 py-3 rounded-xl text-xs font-black tracking-widest uppercase text-white relative overflow-hidden group">
                                    <span className="relative z-10">Comenzar 97€</span>
                                    <div className="absolute inset-0 bg-gradient-to-r from-black via-gray-900 to-black opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                </a>
                            </div>
                        </div>

                        <button 
                            className="lg:hidden p-2 relative z-50"
                            onClick={() => setIsMenuOpen(!isMenuOpen)}
                        >
                            <div className={`w-6 h-6 flex flex-col justify-between transition-all duration-300 ${isMenuOpen ? 'rotate-90' : ''}`}>
                                <span className={`w-full h-0.5 bg-black transition-all duration-300 ${isMenuOpen ? 'rotate-45 translate-y-2.5' : ''}`}></span>
                                <span className={`w-full h-0.5 bg-black transition-all duration-300 ${isMenuOpen ? 'opacity-0' : ''}`}></span>
                                <span className={`w-full h-0.5 bg-black transition-all duration-300 ${isMenuOpen ? '-rotate-45 -translate-y-2.5' : ''}`}></span>
                            </div>
                        </button>
                    </div>

                    {/* Mobile Menu */}
                    <div className={`lg:hidden absolute top-full left-0 right-0 bg-white transition-all duration-500 ${isMenuOpen ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4 pointer-events-none'}`}>
                        <div className="container mx-auto px-8 py-6 space-y-4 border-t border-gray-100">
                            <a href="#features" className="block py-3 text-lg font-semibold text-gray-700 hover:text-black transition-colors" onClick={() => setIsMenuOpen(false)}>Características</a>
                            <a href="#industry" className="block py-3 text-lg font-semibold text-gray-700 hover:text-black transition-colors" onClick={() => setIsMenuOpen(false)}>Sectores</a>
                            <a href="#process" className="block py-3 text-lg font-semibold text-gray-700 hover:text-black transition-colors" onClick={() => setIsMenuOpen(false)}>Proceso</a>
                            <a href="#pricing" className="block py-3 text-lg font-semibold text-gray-700 hover:text-black transition-colors" onClick={() => setIsMenuOpen(false)}>Planes</a>
                            <a href="#contact" className="block py-4 mt-6 text-center bg-black text-white rounded-xl font-bold text-sm tracking-widest uppercase" onClick={() => setIsMenuOpen(false)}>Comenzar Setup</a>
                        </div>
                    </div>
                </nav>
            );
        };

        const Hero = () => {
            const [typedText, setTypedText] = useState('');
            const texts = ['en 7 días.', 'por 97€.', 'con IA.', 'para tu equipo.'];
            const [textIndex, setTextIndex] = useState(0);
            const [charIndex, setCharIndex] = useState(0);
            const [isDeleting, setIsDeleting] = useState(false);

            useEffect(() => {
                const timeout = setTimeout(() => {
                    const currentText = texts[textIndex];
                    
                    if (!isDeleting && charIndex < currentText.length) {
                        setTypedText(currentText.substring(0, charIndex + 1));
                        setCharIndex(charIndex + 1);
                    } else if (isDeleting && charIndex > 0) {
                        setTypedText(currentText.substring(0, charIndex - 1));
                        setCharIndex(charIndex - 1);
                    } else if (!isDeleting && charIndex === currentText.length) {
                        setTimeout(() => setIsDeleting(true), 2000);
                    } else if (isDeleting && charIndex === 0) {
                        setIsDeleting(false);
                        setTextIndex((textIndex + 1) % texts.length);
                    }
                }, isDeleting ? 50 : 100);

                return () => clearTimeout(timeout);
            }, [charIndex, isDeleting, textIndex, texts]);

            return (
                <section className="hero-section relative pt-32 pb-20 md:pt-40 md:pb-32 overflow-hidden">
                    <ParticlesBackground />
                    <div className="hero-grid absolute inset-0"></div>
                    
                    <div className="container mx-auto px-4 md:px-8 relative z-10">
                        <div className="max-w-6xl mx-auto">
                            {/* Badge de garantía */}
                            <div className="inline-flex items-center gap-2 px-4 py-2 bg-black/5 rounded-full mb-8 animate-float">
                                <div className="w-2 h-2 bg-black rounded-full animate-pulse"></div>
                                <span className="text-xs font-black uppercase tracking-widest">GARANTÍA DE 7 DÍAS</span>
                            </div>
                            
                            {/* Título principal */}
                            <h1 className="hero-title text-6xl md:text-8xl lg:text-9xl font-black text-black leading-[0.9] mb-6 tracking-tighter">
                                Tu Intranet
                                <br />
                                <span className="text-gradient-premium relative">
                                    {typedText}
                                    <span className="inline-block w-1 h-16 bg-black ml-2 animate-pulse"></span>
                                </span>
                            </h1>
                            
                            {/* Subtítulo */}
                            <p className="text-xl md:text-2xl font-medium text-gray-600 mb-10 max-w-2xl leading-relaxed">
                                Implementación completa con IA integrada, migración de datos y soporte 24/7. 
                                <span className="font-black text-black"> Sin sorpresas.</span>
                            </p>
                            
                            {/* CTA Buttons */}
                            <div className="flex flex-col sm:flex-row gap-4 mb-16">
                                <a href="#contact" className="group relative">
                                    <div className="absolute -inset-1 bg-gradient-to-r from-black to-gray-900 rounded-2xl blur opacity-30 group-hover:opacity-50 transition duration-500"></div>
                                    <button className="relative bg-black text-white px-10 py-5 rounded-2xl font-black text-sm tracking-widest uppercase hover:scale-105 transition-transform duration-300">
                                        <span className="flex items-center gap-3">
                                            Comenzar ahora
                                            <svg className="w-5 h-5 group-hover:translate-x-2 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                            </svg>
                                        </span>
                                    </button>
                                </a>
                                
                                <a href="#process" className="group">
                                    <button className="bg-white text-black border-2 border-black px-10 py-5 rounded-2xl font-black text-sm tracking-widest uppercase hover:bg-black hover:text-white transition-all duration-300">
                                        Ver proceso
                                    </button>
                                </a>
                            </div>
                            
                            {/* Stats Grid */}
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-12">
                                {STATS.map((stat, index) => (
                                    <div key={index} className="text-center p-4 bg-white/50 backdrop-blur-sm rounded-xl border border-gray-100 hover:border-gray-200 transition-all duration-300">
                                        <div className="text-3xl md:text-4xl font-black text-black mb-1">{stat.value}<span className="text-gray-400">{stat.suffix}</span></div>
                                        <div className="text-xs font-semibold uppercase tracking-widest text-gray-500">{stat.label}</div>
                                    </div>
                                ))}
                            </div>
                            
                            {/* Trust Badges */}
                            <div className="flex flex-wrap items-center justify-center gap-6 text-gray-500">
                                <div className="flex items-center gap-2">
                                    <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                    <span className="text-sm font-medium">Soporte 24/7</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                                    <span className="text-sm font-medium">Garantía 7 días</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-2 h-2 bg-purple-500 rounded-full animate-pulse"></div>
                                    <span className="text-sm font-medium">Migración incluida</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* Floating Elements */}
                    <div className="floating-element absolute top-1/4 left-10 w-32 h-32 bg-black/5 rounded-full blur-3xl"></div>
                    <div className="floating-element absolute bottom-1/4 right-10 w-40 h-40 bg-black/5 rounded-full blur-3xl" style={{animationDelay: '2s'}}></div>
                </section>
            );
        };

        const Features = () => {
            return (
                <section id="features" className="py-24 bg-white relative overflow-hidden">
                    <div className="grid-pattern absolute inset-0"></div>
                    
                    <div className="container mx-auto px-4 md:px-8 relative z-10">
                        <div className="text-center mb-16">
                            <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-black/5 rounded-full mb-6">
                                <span className="text-xs font-black uppercase tracking-widest text-gray-600">POTENCIA MODULAR</span>
                            </div>
                            <h2 className="section-title text-5xl md:text-7xl font-black text-black mb-6">
                                Todo lo que necesitas
                                <br />
                                <span className="text-gradient-premium">en un solo lugar</span>
                            </h2>
                            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
                                Una suite completa de herramientas diseñadas para potenciar tu equipo
                            </p>
                        </div>
                        
                        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {FEATURES.map((feature, index) => (
                                <div 
                                    key={feature.id}
                                    className="neo-card p-8 hover-3d animate-slide-in"
                                    style={{animationDelay: `${index * 0.1}s`}}
                                >
                                    <div className="flex items-start justify-between mb-6">
                                        <div className="text-4xl text-black">
                                            <Icon name={feature.icon} className="w-10 h-10" />
                                        </div>
                                        <div className="text-xs font-black uppercase tracking-widest bg-black text-white px-3 py-1 rounded-full">
                                            {feature.stats}
                                        </div>
                                    </div>
                                    <h3 className="text-2xl font-black text-black mb-4">{feature.title}</h3>
                                    <p className="text-gray-600 mb-6">{feature.description}</p>
                                    <a href="#" className="inline-flex items-center gap-2 text-sm font-semibold text-black hover:gap-3 transition-all">
                                        Descubrir más
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            );
        };

        const IndustryKits = () => {
            const [activeKit, setActiveKit] = useState(INDUSTRY_KITS[0]);
            const [isPreviewOpen, setPreviewOpen] = useState(false);

            useEffect(() => {
                if (!isPreviewOpen) return;
                const handleKeyDown = (event) => {
                    if (event.key === 'Escape') {
                        setPreviewOpen(false);
                    }
                };
                window.addEventListener('keydown', handleKeyDown);
                return () => window.removeEventListener('keydown', handleKeyDown);
            }, [isPreviewOpen]);

            return (
                <section id="industry" className="py-24 bg-gray-50 relative">
                    <div className="container mx-auto px-4 md:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-5xl md:text-7xl font-black text-black mb-6">
                                Soluciones para
                                <br />
                                <span className="text-gradient-premium">cada sector</span>
                            </h2>
                            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
                                Kits preconfigurados optimizados para las necesidades específicas de tu industria
                            </p>
                        </div>
                        
                        {/* Kit Selector */}
                        <div className="flex flex-wrap justify-center gap-3 mb-12">
                            {INDUSTRY_KITS.map((kit) => (
                                <button
                                    key={kit.id}
                                    onClick={() => setActiveKit(kit)}
                                    className={`px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest transition-all duration-300 ${
                                        activeKit.id === kit.id 
                                        ? 'bg-black text-white shadow-lg' 
                                        : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300'
                                    }`}
                                >
                                    <span className="mr-2">{kit.icon}</span>
                                    {kit.title}
                                </button>
                            ))}
                        </div>
                        
                        {/* Active Kit Display */}
                        <div className="grid lg:grid-cols-2 gap-12">
                            <div className="space-y-8">
                                <div>
                                    <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-black/5 rounded-full mb-4">
                                        <span className="text-xs font-black uppercase tracking-widest">KIT ESPECIALIZADO</span>
                                    </div>
                                    <h3 className="text-4xl md:text-5xl font-black text-black mb-6 leading-tight">
                                        {activeKit.title}
                                        <br />
                                        <span className="text-gray-400">Edition</span>
                                    </h3>
                                    <p className="text-lg text-gray-600 mb-8">{activeKit.description}</p>
                                </div>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    {activeKit.modules.map((module, index) => (
                                        <div 
                                            key={index}
                                            className="bg-white border border-gray-200 rounded-xl p-4 hover:border-black transition-all duration-300"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 bg-black rounded-lg flex items-center justify-center">
                                                    <span className="text-white text-sm font-bold">{index + 1}</span>
                                                </div>
                                                <span className="font-bold text-black">{module}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                
                                <a href="#contact" className="inline-flex items-center gap-3 bg-black text-white px-8 py-4 rounded-xl font-bold text-sm tracking-widest uppercase hover:gap-4 transition-all">
                                    Solicitar demo personalizada
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </a>
                            </div>
                            
                            <div className="relative">
                                <div
                                    className="group rounded-3xl overflow-hidden aspect-video bg-gradient-to-br from-gray-900 via-gray-800 to-black shadow-2xl border border-gray-200 relative cursor-zoom-in"
                                    role="button"
                                    tabIndex={0}
                                    onClick={() => setPreviewOpen(true)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault();
                                            setPreviewOpen(true);
                                        }
                                    }}
                                    aria-label={`Ampliar vista previa ${activeKit.title}`}
                                >
                                    <img
                                        key={activeKit.id}
                                        src={activeKit.image}
                                        alt={`Panel de control ${activeKit.title}`}
                                        className="absolute inset-0 w-full h-full object-cover z-10"
                                        loading="lazy"
                                        onLoad={(event) => event.currentTarget.classList.remove('hidden')}
                                        onError={(event) => event.currentTarget.classList.add('hidden')}
                                    />
                                    <div className="absolute inset-0 z-0 flex flex-col items-center justify-center text-white/80">
                                        <Icon name={activeKit.icon} className="w-16 h-16 mb-4" />
                                        <span className="text-sm font-bold uppercase tracking-[0.3em]">Vista previa</span>
                                    </div>
                                    <div className="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/70 via-black/10 to-transparent px-6 py-4 text-sm font-bold uppercase tracking-[0.3em] text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                        Ampliar
                                    </div>
                                </div>
                                
                                {/* Floating elements */}
                                <div className="absolute -top-6 -right-6 w-32 h-32 bg-black/10 rounded-full blur-2xl"></div>
                                <div className="absolute -bottom-6 -left-6 w-40 h-40 bg-black/10 rounded-full blur-2xl"></div>
                            </div>
                        </div>

                        {isPreviewOpen && (
                            <div
                                className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 py-8"
                                onClick={() => setPreviewOpen(false)}
                                role="dialog"
                                aria-modal="true"
                            >
                                <div
                                    className="relative w-full max-w-6xl"
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <button
                                        type="button"
                                        className="absolute -top-12 right-0 rounded-full border border-white/30 bg-black/70 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] text-white hover:bg-black"
                                        onClick={() => setPreviewOpen(false)}
                                    >
                                        Cerrar
                                    </button>
                                    <div className="rounded-3xl overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-black shadow-2xl border border-white/10 relative">
                                        <img
                                            key={`${activeKit.id}-zoom`}
                                            src={activeKit.image}
                                            alt={`Panel de control ${activeKit.title}`}
                                            className="absolute inset-0 w-full h-full object-contain z-10"
                                            loading="lazy"
                                            onLoad={(event) => event.currentTarget.classList.remove('hidden')}
                                            onError={(event) => event.currentTarget.classList.add('hidden')}
                                        />
                                        <div className="absolute inset-0 z-0 flex flex-col items-center justify-center text-white/80">
                                            <Icon name={activeKit.icon} className="w-20 h-20 mb-4" />
                                            <span className="text-sm font-bold uppercase tracking-[0.3em]">Vista previa</span>
                                        </div>
                                        <div className="relative z-20 aspect-video"></div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            );
        };

        const Process = () => {
            return (
                <section id="process" className="py-24 bg-white relative overflow-hidden">
                    <div className="container mx-auto px-4 md:px-8">
                        <div className="text-center mb-20">
                            <div className="inline-flex items-center gap-2 px-4 py-1.5 bg-black/5 rounded-full mb-6">
                                <span className="text-xs font-black uppercase tracking-widest text-gray-600">PROCESO GARANTIZADO</span>
                            </div>
                            <h2 className="text-5xl md:text-7xl font-black text-black mb-6">
                                Lanzamiento en
                                <br />
                                <span className="text-gradient-premium">7 días exactos</span>
                            </h2>
                            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
                                Un proceso milimétrico diseñado para resultados inmediatos
                            </p>
                        </div>
                        
                        {/* Timeline */}
                        <div className="relative max-w-6xl mx-auto">
                            {/* Line */}
                            <div className="process-line absolute left-0 right-0 top-12 hidden lg:block"></div>
                            
                            {/* Process Steps */}
                            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                                {PROCESS.map((step, index) => (
                                    <div key={index} className="relative group">
                                        {/* Dot for timeline */}
                                        <div className="process-dot hidden lg:block" style={{ left: `${(index / (PROCESS.length - 1)) * 100}%` }}></div>
                                        
                                        <div className="bg-white border-2 border-gray-200 rounded-2xl p-8 hover:border-black transition-all duration-300 h-full">
                                            <div className="flex items-start gap-4 mb-6">
                                                <div className="text-3xl text-black">
                                                    <Icon name={step.icon} className="w-9 h-9" />
                                                </div>
                                                <div className="text-xs font-black uppercase tracking-widest bg-black text-white px-3 py-1 rounded-full">
                                                    {step.day}
                                                </div>
                                            </div>
                                            <h3 className="text-2xl font-black text-black mb-4">{step.title}</h3>
                                            <p className="text-gray-600">{step.description}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            
                            {/* Guarantee Badge */}
                            <div className="text-center mt-16">
                                <div className="inline-flex items-center gap-4 bg-black text-white px-8 py-4 rounded-full">
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span className="font-black text-sm tracking-widest uppercase">LANZAMIENTO GARANTIZADO EN DÍA 7</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            );
        };

        const PricingLegacy = () => {
            const [billingCycle, setBillingCycle] = useState('monthly');

            return (
                <section id="pricing" className="py-24 bg-gray-50 relative">
                    <div className="container mx-auto px-4 md:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-5xl md:text-7xl font-black text-black mb-6">
                                Estructura de ofertas
                                <br />
                                <span className="text-gray-600">IU 2.0</span>
                            </h2>
                            <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                                Attraction Offer ? Upsell ? Downsell ? Continuity ? Add-ons.
                            </p>
                        </div>

                        <div className="max-w-6xl mx-auto">
                            {/* Desktop Table */}
                            <div className="hidden md:block overflow-x-auto">
                                <div className="min-w-[900px] bg-white border border-[#e5e5e5] rounded-3xl overflow-hidden">
                                    <div className="bg-[#fafafa] border-b border-[#e5e5e5] px-8 py-5">
                                        <div className="text-xs font-black uppercase tracking-[0.2em] text-gray-600">
                                            Estructura de ofertas IU 2.0
                                        </div>
                                    </div>
                                    <table className="w-full table-fixed text-sm offer-table">
                                        <colgroup>
                                            <col className="w-[24%]" />
                                            <col className="w-[18%]" />
                                            <col className="w-[28%]" />
                                            <col className="w-[15%]" />
                                            <col className="w-[15%]" />
                                        </colgroup>
                                        <thead className="bg-white">
                                            <tr className="border-b border-[#e5e5e5]">
                                                <th className="text-left px-8 py-5 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Etapa</th>
                                                <th className="text-left px-6 py-5 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Tipo de oferta</th>
                                                <th className="text-left px-6 py-5 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Descripción</th>
                                                <th className="text-left px-6 py-5 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Precio / Condiciones</th>
                                                <th className="text-left px-8 py-5 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Objetivo principal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#e5e5e5]">
                                            <tr className="hover:bg-[#fafafa] transition-colors">
                                                <td className="px-8 py-6 font-black text-black whitespace-nowrap">1) Attraction Offer – “IU Launch”</td>
                                                <td className="px-6 py-6 text-gray-800 font-medium">Oferta de entrada (low ticket)</td>
                                                <td className="px-6 py-6 text-gray-700 leading-6">
                                                    Configuramos tu intranet en 7 días para que puedas experimentar procesos claros, herramientas listas y tomar decisiones con confianza.
                                                </td>
                                                <td className="px-6 py-6">
                                                    <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-black text-white text-xs font-black tracking-widest">
                                                        97 € (pago único)
                                                    </span>
                                                </td>
                                                <td className="px-8 py-6 text-gray-700 leading-6">
                                                    Que veas la propuesta de valor desde el primer día y tengas seguridad antes de avanzar.
                                                </td>
                                            </tr>
                                            <tr className="hover:bg-[#fafafa] transition-colors">
                                                <td className="px-8 py-6 font-black text-black whitespace-nowrap">2) Upsell inmediato – Plan Profesional (con descuento anual)</td>
                                                <td className="px-6 py-6 text-gray-800 font-medium">Upsell tras compra del setup</td>
                                                <td className="px-6 py-6 text-gray-700 leading-6">
                                                    Te acompañamos para pasar al plan mensual/anual con 30?% de ahorro y continuidad sin fricciones.
                                                </td>
                                                <td className="px-6 py-6">
                                                    <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-white text-black border border-[#e5e5e5] text-xs font-black tracking-widest">
                                                        ~149 €/mes o -30 % anual
                                                    </span>
                                                </td>
                                                <td className="px-8 py-6 text-gray-700 leading-6">
                                                    Darte tiempo de adaptarte al sistema completo manteniendo el momentum tras el setup.
                                                </td>
                                            </tr>
                                            <tr className="hover:bg-[#fafafa] transition-colors">
                                                <td className="px-8 py-6 font-black text-black whitespace-nowrap">3) Downsell – “IU Lite”</td>
                                                <td className="px-6 py-6 text-gray-800 font-medium">Alternativa más económica</td>
                                                <td className="px-6 py-6 text-gray-700 leading-6">
                                                    Una opción más económica para seguir aprendiendo, ejecutar tareas clave y seguir conectado con la comunidad.
                                                </td>
                                                <td className="px-6 py-6">
                                                    <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-white text-black border border-[#e5e5e5] text-xs font-black tracking-widest">
                                                        9 €/mes durante 3 meses
                                                    </span>
                                                </td>
                                                <td className="px-8 py-6 text-gray-700 leading-6">
                                                    Mantenerte dentro del ecosistema y facilitar tu evolución hacia la membresía completa.
                                                </td>
                                            </tr>
                                                                                        <tr className="hover:bg-[#fafafa] transition-colors">
                                                <td className="px-8 py-6 font-black text-black whitespace-nowrap">4) Plan de Mantenimiento - IUC Mantenimiento</td>
                                                <td className="px-6 py-6 text-gray-800 font-medium">Plan mensual de mantenimiento</td>
                                                <td className="px-6 py-6 text-gray-700 leading-6">
                                                    Mantén la intranet con revisiones periódicas, monitoreo, respaldos y soporte ágil sin sorpresas.
                                                </td>
                                                <td className="px-6 py-6">
                                                    <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-white text-black border border-[#e5e5e5] text-xs font-black tracking-widest">
                                                        49 €/mes
                                                    </span>
                                                </td>
                                                <td className="px-8 py-6 text-gray-700 leading-6">
                                                    Brindar continuidad operativa con ajustes menores y soporte claro a 49 €/mes.
                                                </td>
                                            </tr>
                                            <tr className="hover:bg-[#fafafa] transition-colors">
                                                <td className="px-8 py-6 font-black text-black whitespace-nowrap">5) Add-ons – Extras opcionales</td>
                                                <td className="px-6 py-6 text-gray-800 font-medium">Upsells complementarios</td>
                                                <td className="px-6 py-6 text-gray-700 leading-6">
                                                    Complementos personalizados: automatizaciones, soporte premium, branding y consultoría para tu estrategia educativa.
                                                </td>
                                                <td className="px-6 py-6">
                                                    <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-white text-black border border-[#e5e5e5] text-xs font-black tracking-widest">
                                                        Precio variable (por módulo o paquete)
                                                    </span>
                                                </td>
                                                <td className="px-8 py-6 text-gray-700 leading-6">
                                                    Adaptar IU a tus prioridades para obtener resultados más ágiles y medibles.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Mobile Cards */}
                            <div className="md:hidden space-y-4">
                                {[
                                    {
                                        etapa: '1) Attraction Offer – “IU Launch”',
                                        tipo: 'Oferta de entrada (low ticket)',
                                        descripcion: 'Mantén la intranet actualizada con revisiones periódicas, respaldos y soporte ágil sin sorpresas.',
                                        precio: '49 €/mes',
                                        objetivo: 'Captar clientes nuevos y reducir fricción de compra inicial.'
                                    },
                                    {
                                        etapa: '2) Upsell inmediato – Plan Profesional (con descuento anual)',
                                        tipo: 'Upsell tras compra del setup',
                                        descripcion: 'Mantén la intranet actualizada con revisiones periódicas, respaldos y soporte ágil sin sorpresas.',
                                        precio: '49 €/mes',
                                        objetivo: 'Maximizar el valor del cliente en la primera transacción.'
                                    },
                                    {
                                        etapa: '3) Downsell – “IU Lite”',
                                        tipo: 'Alternativa más económica',
                                        descripcion: 'Mantén la intranet actualizada con revisiones periódicas, respaldos y soporte ágil sin sorpresas.',
                                        precio: '49 €/mes',
                                        objetivo: 'No perder leads, mantenerlos dentro del ecosistema.'
                                    },
                                    {
                                        etapa: '4) Plan de Mantenimiento - IUC Mantenimiento',
                                        tipo: 'Plan mensual de mantenimiento',
                                        descripcion: 'Mantén la intranet con revisiones periódicas, monitoreo, respaldos y soporte ágil sin sorpresas.',
                                        descripcion: 'Mantén la intranet actualizada con revisiones periódicas, respaldos y soporte ágil sin sorpresas.',
                                        precio: '49 €/mes',
                                        objetivo: 'Garantizar continuidad operativa con ajustes menores y soporte claro a 49 €/mes.'
                                    },
                                    {
                                        etapa: '5) Add-ons - Extras opcionales',
                                        tipo: 'Upsells complementarios',
                                        descripcion: 'Mantén la intranet actualizada con revisiones periódicas, respaldos y soporte ágil sin sorpresas.',
                                        precio: '49 €/mes',
                                        objetivo: 'Incrementar el ticket medio y ofrecer personalización.'
                                    }
                                ].map((row) => (
                                    <div key={row.etapa} className="bg-white border border-[#e5e5e5] rounded-2xl p-6">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <div className="font-black text-black">{row.etapa}</div>
                                                <div className="mt-2 text-sm font-semibold text-gray-600">{row.tipo}</div>
                                            </div>
                                            <div className="shrink-0">
                                                <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-black text-white text-xs font-black tracking-widest">
                                                    {row.precio}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="mt-4 text-gray-700">{row.descripcion}</div>
                                        <div className="mt-4 pt-4 border-t border-[#e5e5e5] text-sm text-gray-700">
                                            <span className="font-black text-black">Objetivo:</span> {row.objetivo}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                        
                        {/* Legacy pricing layout (hidden) */}
                        <div className="hidden">
                        {/* Billing Toggle */}
                        <div className="flex justify-center mb-12">
                            <div className="bg-white border border-gray-200 rounded-xl p-1 inline-flex">
                                <button
                                    onClick={() => setBillingCycle('monthly')}
                                    className={`px-6 py-3 rounded-lg font-bold text-sm transition-all ${
                                        billingCycle === 'monthly' 
                                        ? 'bg-black text-white' 
                                        : 'text-gray-600 hover:text-black'
                                    }`}
                                >
                                    Mensual
                                </button>
                                <button
                                    onClick={() => setBillingCycle('yearly')}
                                    className={`px-6 py-3 rounded-lg font-bold text-sm transition-all ${
                                        billingCycle === 'yearly' 
                                        ? 'bg-black text-white' 
                                        : 'text-gray-600 hover:text-black'
                                    }`}
                                >
                                    Anual (2 meses gratis)
                                </button>
                            </div>
                        </div>
                        
                        {/* Pricing Cards */}
                        <div className="grid lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                            {EDUCATION_PLANS.map((plan, index) => (
                                <div 
                                    key={plan.name}
                                    className={`relative rounded-3xl p-8 transition-all duration-500 hover-3d ${
                                        plan.popular 
                                        ? 'bg-black text-white border-2 border-black shadow-2xl' 
                                        : 'bg-white text-black border border-gray-200'
                                    }`}
                                    style={{animationDelay: `${index * 0.2}s`}}
                                >
                                    {plan.popular && (
                                        <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-white text-black px-6 py-2 rounded-full text-xs font-black uppercase tracking-widest shadow-lg">
                                            {plan.highlight}
                                        </div>
                                    )}
                                    
                                    <div className="mb-8">
                                        <div className="flex items-center justify-between mb-4">
                                            <h3 className="text-2xl font-black">{plan.name}</h3>
                                            {plan.popular && (
                                                <div className="text-xs font-bold uppercase tracking-widest bg-white/20 px-3 py-1 rounded-full">
                                                    RECOMENDADO
                                                </div>
                                            )}
                                        </div>
                                        <p className={`mb-6 ${plan.popular ? 'text-gray-300' : 'text-gray-600'}`}>{plan.description}</p>
                                        
                                        <div className="mb-4">
                                            <div className="flex items-baseline gap-2">
                                                <span className="text-5xl font-black">{plan.price}</span>
                                                <span className="text-sm font-semibold uppercase tracking-widest text-gray-500">/mes</span>
                                            </div>
                                            <div className="text-sm font-medium text-gray-500 mt-2">
                                                {plan.yearlyPrice} anual
                                            </div>
                                        </div>
                                        
                                        <div className="text-xs font-bold uppercase tracking-widest text-gray-500 mt-2">
                                            {plan.highlight}
                                        </div>
                                    </div>
                                    
                                    <ul className="space-y-4 mb-10">
                                        {plan.features.map((feature, i) => (
                                            <li key={i} className="flex items-center gap-3">
                                                <svg className={`w-5 h-5 flex-shrink-0 ${plan.popular ? 'text-white' : 'text-black'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span className="font-medium">{feature}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    
                                    <a 
                                        href="#contact" 
                                        className={`w-full text-center py-4 rounded-xl font-bold text-sm tracking-widest uppercase transition-all ${
                                            plan.popular 
                                            ? 'bg-white text-black hover:bg-gray-100' 
                                            : 'bg-black text-white hover:bg-gray-900'
                                        }`}
                                    >
                                        {plan.cta}
                                    </a>
                                </div>
                            ))}
                        </div>
                        
                        {/* Setup Offer */}
                        <div className="mt-20 text-center">
                            <div className="max-w-2xl mx-auto bg-gradient-to-r from-black to-gray-900 text-white rounded-3xl p-12 relative overflow-hidden">
                                <div className="absolute -top-20 -right-20 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                                <div className="absolute -bottom-20 -left-20 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                                
                                <div className="relative z-10">
                                    <div className="text-xs font-black uppercase tracking-widest text-gray-300 mb-4">OFERTA ESPECIAL DE LANZAMIENTO</div>
                                    <h3 className="text-4xl font-black mb-6">Setup Completo por <span className="text-green-400">97€</span></h3>
                                    <p className="text-gray-300 mb-8 max-w-md mx-auto">
                                        Incluye configuración inicial, migración de datos y capacitación básica. Oferta limitada.
                                    </p>
                                    <a href="#contact" className="inline-flex items-center gap-3 bg-white text-black px-10 py-4 rounded-xl font-bold text-sm tracking-widest uppercase hover:gap-4 transition-all">
                                        Reservar oferta
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </section>
            );
        };

        const Pricing = () => {
            const Check = ({ className = '' }) => (
                <svg className={`w-5 h-5 ${className}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
                </svg>
            );

            const PrimaryButton = ({ children, href = '#contact' }) => (
                <a
                    href={href}
                    className="inline-flex items-center justify-center w-full sm:w-auto px-8 py-4 rounded-xl bg-[#111111] text-white font-bold text-sm tracking-widest uppercase hover:bg-gray-900 transition-colors"
                >
                    {children}
                </a>
            );

            const SecondaryButton = ({ children, href = '#contact' }) => (
                <a
                    href={href}
                    className="inline-flex items-center justify-center w-full sm:w-auto px-8 py-4 rounded-xl border border-[#E5E5E5] bg-white text-[#111111] font-bold text-sm tracking-widest uppercase hover:bg-gray-50 transition-colors"
                >
                    {children}
                </a>
            );

            return (
                <section id="pricing" className="py-24 bg-white">
                    <div className="container mx-auto px-4 md:px-8">
                        <div className="text-center mb-16">
                            <div className="text-xs font-black uppercase tracking-[0.25em] text-gray-500 mb-4">
                                Planes y precios
                            </div>
                            <h2 className="text-4xl md:text-6xl font-black text-[#111111] mb-5">
                                Planes y precios IUC
                            </h2>
                            <p className="text-lg md:text-xl text-[#4B5563] max-w-3xl mx-auto">
                                Empieza con IU Launch (97 € pago único) y, después, elige el plan mensual que mejor encaje (desde 9 €/mes).
                            </p>
                        </div>

                        <div className="max-w-6xl mx-auto grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                            {/* Bloque 1 — IU Launch */}
                            <div className="bg-white border border-[#E5E5E5] rounded-3xl shadow-sm p-10">
                                <h3 className="text-2xl md:text-3xl font-black text-[#111111] mb-4">
                                    &#128640; IU Launch — Tu sistema IUC listo en 7 días
                                </h3>
                                <p className="text-[#4B5563] leading-relaxed mb-6">
                                    Por solo <span className="font-black text-[#111111]">97 € (pago único)</span>, configuramos, personalizamos y activamos tu sistema IUC completo con todas las funciones profesionales.
                                    Empieza a operar desde el primer día, sin técnicos ni complicaciones.
                                </p>

                                <div className="space-y-3 mb-8">
                                    {[
                                        'Instalación y configuración personalizada',
                                        'Activación del Plan Profesional durante 7 días',
                                        'Soporte guiado y asistencia técnica durante la primera semana',
                                        'Hosting incluido durante los primeros 15 días',
                                        'Tu sistema queda reservado y operativo a tu nombre'
                                    ].map((item, idx) => (
                                        <div key={idx} className="flex gap-3 items-start">
                                            <Check className="text-[#111111] mt-0.5" />
                                            <div className="text-[#111111] font-medium">{item}</div>
                                        </div>
                                    ))}
                                </div>

                                <div className="bg-gray-50 border border-[#E5E5E5] rounded-2xl p-5 mb-8">
                                    <div className="text-sm text-[#4B5563] leading-relaxed">
                                        &#128712; Si luego no contratas mantenimiento o un plan, tu sistema se mantiene en modo lectura (pausado), listo para reactivarse cuando quieras.
                                    </div>
                                </div>

                                <PrimaryButton href="#contact">Comenzar Setup por 97 €</PrimaryButton>
                            </div>

                            {/* Bloque 2 — IUC Lite */}
                            <div className="bg-white border border-[#E5E5E5] rounded-3xl shadow-sm p-10">
                                <h3 className="text-2xl md:text-3xl font-black text-[#111111] mb-4">
                                    &#127793; IUC Lite — La versión esencial para empezar
                                </h3>
                                <p className="text-[#4B5563] leading-relaxed mb-6">
                                    Ideal para pequeñas academias o negocios locales que quieren centralizar comunicación y tareas sin complejidad.
                                </p>

                                <div className="flex items-baseline gap-3 mb-6">
                                    <div className="text-4xl font-black text-[#111111]">9 €/mes</div>
                                </div>

                                <div className="space-y-3 mb-8">
                                    {[
                                        'Comunidad + tareas + calendario básico',
                                        'Hasta 10 usuarios',
                                        'Sin IA ni automatizaciones',
                                        'Sin soporte técnico'
                                    ].map((item, idx) => (
                                        <div key={idx} className="flex gap-3 items-start">
                                            <Check className="text-[#111111] mt-0.5" />
                                            <div className="text-[#111111] font-medium">{item}</div>
                                        </div>
                                    ))}
                                </div>

                                <SecondaryButton href="#contact">Probar IUC Lite — 9 €/mes</SecondaryButton>
                            </div>

                            {/* Bloque 3 — IUC Mantenimiento */}
                            <div className="bg-white border border-[#E5E5E5] rounded-3xl shadow-sm p-10">
                                <h3 className="text-2xl md:text-3xl font-black text-[#111111] mb-4">
                                    &#128295; IUC Mantenimiento — Mantén tu sistema esencial activo
                                </h3>
                                <p className="text-[#4B5563] leading-relaxed mb-6">
                                    Mantén tu intranet activa, segura y operativa con las funciones esenciales de tu sector.
                                    Ideal si no necesitas automatizaciones ni IA, pero quieres estabilidad, soporte y actualizaciones.
                                </p>

                                <div className="flex items-baseline gap-3 mb-6">
                                    <div className="text-4xl font-black text-[#111111]">49 €/mes</div>
                                </div>

                                <div className="space-y-3 mb-6">
                                    {[
                                        'Hosting y seguridad gestionada',
                                        'Acceso total a tu panel y datos',
                                        'Módulos esenciales: Educación (comunidad, recursos, tareas y calendario)',
                                        'Módulos esenciales: Hostelería (comunicación interna, tareas y calendario operativo)',
                                        'Soporte técnico básico (tickets o email)',
                                        'Actualizaciones automáticas',
                                        'Copia de seguridad mensual'
                                    ].map((item, idx) => (
                                        <div key={idx} className="flex gap-3 items-start">
                                            <Check className="text-[#111111] mt-0.5" />
                                            <div className="text-[#111111] font-medium">{item}</div>
                                        </div>
                                    ))}
                                </div>

                                <div className="text-sm text-[#4B5563] border-t border-[#E5E5E5] pt-6 mb-8 leading-relaxed">
                                    &#9888; No incluye automatizaciones, IA, estadísticas ni soporte premium (solo en Plan Profesional o superior).
                                </div>

                                <PrimaryButton href="#contact">Activar Mantenimiento 49 €/mes</PrimaryButton>
                            </div>
                        </div>

                        {/* Bloque 4 — Planes Profesionales */}
                        <div className="max-w-6xl mx-auto mt-10 bg-white border border-[#E5E5E5] rounded-3xl shadow-sm p-10">
                            <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-8">
                                <div>
                                    <h3 className="text-2xl md:text-3xl font-black text-[#111111] mb-2">
                                        &#128200; Planes Profesionales IUC — Escala tu sistema con todo el poder
                                    </h3>
                                    <p className="text-[#4B5563] leading-relaxed max-w-3xl">
                                        Elige el plan que mejor se adapte a tu institución o negocio.
                                        Todos incluyen hosting, soporte, actualizaciones y acceso completo al ecosistema IUC.
                                    </p>
                                </div>
                            </div>

                            {/* Desktop table */}
                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full min-w-[1000px] text-sm table-fixed border border-[#E5E5E5] rounded-2xl overflow-hidden">
                                    <colgroup>
                                        <col className="w-[16%]" />
                                        <col className="w-[14%]" />
                                        <col className="w-[24%]" />
                                        <col className="w-[32%]" />
                                        <col className="w-[14%]" />
                                    </colgroup>
                                    <thead className="bg-gray-50">
                                        <tr className="border-b border-[#E5E5E5]">
                                            <th className="text-left px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Plan</th>
                                            <th className="text-left px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Precio</th>
                                            <th className="text-left px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Ideal para</th>
                                            <th className="text-left px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Funcionalidades</th>
                                            <th className="text-right px-6 py-4 text-xs font-black uppercase tracking-[0.2em] text-gray-600">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#E5E5E5] bg-white">
                                        <tr className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-5 font-black text-[#111111]">Profesional</td>
                                            <td className="px-6 py-5 font-black text-[#111111]">&#128182; 149 €/mes</td>
                                            <td className="px-6 py-5 text-[#4B5563]">Academias y negocios medianos</td>
                                            <td className="px-6 py-5 text-[#4B5563]">Todo lo del Mantenimiento + Automatizaciones, IA básica, soporte avanzado</td>
                                            <td className="px-6 py-5">
                                                <div className="flex justify-end">
                                                    <SecondaryButton href="#contact">Solicitar Demo</SecondaryButton>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr className="hover:bg-gray-50 transition-colors">
                                            <td className="px-6 py-5 font-black text-[#111111]">Avanzado</td>
                                            <td className="px-6 py-5 font-black text-[#111111]">&#128182; 249 €/mes</td>
                                            <td className="px-6 py-5 text-[#4B5563]">Centros grandes y cadenas locales</td>
                                            <td className="px-6 py-5 text-[#4B5563]">IA extendida, analíticas, flujos automáticos, SLA 24h</td>
                                            <td className="px-6 py-5">
                                                <div className="flex justify-end">
                                                    <PrimaryButton href="#contact">Contactar</PrimaryButton>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile cards */}
                            <div className="md:hidden grid gap-6">
                                {[
                                    {
                                        plan: 'Profesional',
                                        price: '💶 149 €/mes',
                                        ideal: 'Academias y negocios medianos',
                                        features: 'Todo lo del Mantenimiento + Automatizaciones, IA básica, soporte avanzado',
                                        cta: 'Solicitar Demo'
                                    },
                                    {
                                        plan: 'Avanzado',
                                        price: '💶 249 €/mes',
                                        ideal: 'Centros grandes y cadenas locales',
                                        features: 'IA extendida, analíticas, flujos automáticos, SLA 24h',
                                        cta: 'Contactar'
                                    }].map((row, idx) => (
                                    <div key={idx} className="bg-white border border-[#E5E5E5] rounded-2xl p-6 shadow-sm">
                                        <div className="flex items-start justify-between gap-4 mb-4">
                                            <div className="font-black text-[#111111] text-lg">{row.plan}</div>
                                            <div className="font-black text-[#111111]">{row.price}</div>
                                        </div>
                                        <div className="text-sm text-[#4B5563] mb-3">
                                            <span className="font-black text-gray-600 uppercase tracking-widest text-xs">Ideal para</span>
                                            <div className="mt-1">{row.ideal}</div>
                                        </div>
                                        <div className="text-sm text-[#4B5563] mb-6">
                                            <span className="font-black text-gray-600 uppercase tracking-widest text-xs">Funcionalidades</span>
                                            <div className="mt-1">{row.features}</div>
                                        </div>
                                        {row.cta === 'Solicitar Demo' ? (
                                            <SecondaryButton href="#contact">{row.cta}</SecondaryButton>
                                        ) : (
                                            <PrimaryButton href="#contact">{row.cta}</PrimaryButton>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Bloque 5 — CTA Final */}
                        <div className="max-w-6xl mx-auto mt-10">
                            <div className="bg-white border border-[#E5E5E5] rounded-3xl shadow-sm p-10 text-center">
                                <p className="text-xl md:text-2xl font-black text-[#111111] mb-6">
                                    Empieza hoy con IU Launch por solo 97 €<br className="hidden sm:block" />
                                    y elige luego cómo quieres mantener o escalar tu sistema.
                                </p>
                                <PrimaryButton href="#contact">Comenzar mi Setup ahora</PrimaryButton>
                            </div>
                        </div>
                    </div>
                </section>
            );
        };

        const Contact = () => {
            const [formData, setFormData] = useState({
                name: '',
                email: '',
                company: '',
                industry: '',
                message: ''
            });
            const [status, setStatus] = useState('idle');
            const [errors, setErrors] = useState({});
            const [submitError, setSubmitError] = useState('');

            const handleChange = (e) => {
                const { name, value } = e.target;
                setFormData(prev => ({ ...prev, [name]: value }));
                if (errors[name]) {
                    setErrors(prev => ({ ...prev, [name]: '' }));
                }
            };

            const validateForm = () => {
                const newErrors = {};
                if (!formData.name.trim()) newErrors.name = 'Nombre requerido';
                if (!formData.email.trim()) {
                    newErrors.email = 'Email requerido';
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
                    newErrors.email = 'Email inválido';
                }
                if (!formData.company.trim()) newErrors.company = 'Empresa requerida';
                if (!formData.industry) newErrors.industry = 'Sector requerido';
                return newErrors;
            };

            const handleSubmit = async (e) => {
                e.preventDefault();
                setSubmitError('');
                const validationErrors = validateForm();
                if (Object.keys(validationErrors).length > 0) {
                    setErrors(validationErrors);
                    return;
                }

                setStatus('loading');

                try {
                    const res = await fetch('api/contact.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(formData),
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        setStatus('idle');
                        setSubmitError(data.error || 'No se pudo enviar el mensaje. Inténtalo de nuevo.');
                        return;
                    }

                    setStatus('success');
                    setFormData({
                        name: '',
                        email: '',
                        company: '',
                        industry: '',
                        message: ''
                    });
                } catch (err) {
                    console.error(err);
                    setStatus('idle');
                    setSubmitError('Error de red. Revisa tu conexión y vuelve a intentarlo.');
                }
            };

            if (status === 'success') {
                return (
                    <div className="bg-white rounded-3xl p-12 text-center shadow-2xl animate-slide-in">
                        <div className="w-24 h-24 bg-black text-white rounded-full flex items-center justify-center mx-auto mb-8">
                            <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 className="text-3xl font-black text-black mb-4">¡Solicitud Confirmada!</h3>
                        <p className="text-gray-600 font-medium mb-8 max-w-md mx-auto">
                            Un especialista se pondrá en contacto contigo en menos de 24 horas para coordinar el inicio de tu proyecto.
                        </p>
                        <button 
                            onClick={() => setStatus('idle')}
                            className="bg-black text-white px-8 py-4 rounded-xl font-bold text-sm tracking-widest uppercase hover:bg-gray-900 transition-colors"
                        >
                            Enviar otra solicitud
                        </button>
                    </div>
                );
            }

            return (
                <div className="bg-white rounded-3xl p-8 md:p-12 shadow-2xl border border-gray-100">
                    <div className="mb-10 text-center">
                        <div className="text-xs font-black uppercase tracking-widest text-gray-500 mb-4">TRANSFORMACIÓN DIGITAL</div>
                        <p className="text-2xl font-black text-black mb-2">"Contacta con nosotros"</p>
                        <p className="text-gray-600">Únete a la revolución digital</p>
                    </div>
                    
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {submitError && (
                            <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 font-medium">
                                {submitError}
                            </div>
                        )}
                        <div className="grid md:grid-cols-2 gap-6">
                            <div>
                                <input
                                    type="text"
                                    name="name"
                                    placeholder="Nombre completo *"
                                    value={formData.name}
                                    onChange={handleChange}
                                    className={`w-full px-6 py-4 rounded-xl border ${errors.name ? 'border-red-500' : 'border-gray-200'} focus:border-black outline-none font-medium transition-colors`}
                                />
                                {errors.name && <p className="text-red-500 text-sm mt-2">{errors.name}</p>}
                            </div>
                            <div>
                                <input
                                    type="email"
                                    name="email"
                                    placeholder="Email corporativo *"
                                    value={formData.email}
                                    onChange={handleChange}
                                    className={`w-full px-6 py-4 rounded-xl border ${errors.email ? 'border-red-500' : 'border-gray-200'} focus:border-black outline-none font-medium transition-colors`}
                                />
                                {errors.email && <p className="text-red-500 text-sm mt-2">{errors.email}</p>}
                            </div>
                        </div>
                        
                        <div>
                            <input
                                type="text"
                                name="company"
                                placeholder="Organización *"
                                value={formData.company}
                                onChange={handleChange}
                                className={`w-full px-6 py-4 rounded-xl border ${errors.company ? 'border-red-500' : 'border-gray-200'} focus:border-black outline-none font-medium transition-colors`}
                            />
                            {errors.company && <p className="text-red-500 text-sm mt-2">{errors.company}</p>}
                        </div>
                        
                        <div>
                            <select
                                name="industry"
                                value={formData.industry}
                                onChange={handleChange}
                                className={`w-full px-6 py-4 rounded-xl border ${errors.industry ? 'border-red-500' : 'border-gray-200'} focus:border-black outline-none font-medium transition-colors`}
                            >
                                <option value="">Selecciona tu sector *</option>
                                <option value="education">Educación</option>
                                <option value="restaurant">Hostelería</option>
                                <option value="services">Servicios</option>
                                <option value="healthcare">Salud</option>
                                <option value="retail">Retail</option>
                                <option value="other">Otro</option>
                            </select>
                            {errors.industry && <p className="text-red-500 text-sm mt-2">{errors.industry}</p>}
                        </div>
                        
                        <div>
                            <textarea
                                rows={4}
                                name="message"
                                placeholder="Cuéntanos sobre tu proyecto, necesidades y objetivos..."
                                value={formData.message}
                                onChange={handleChange}
                                className="w-full px-6 py-4 rounded-xl border border-gray-200 focus:border-black outline-none resize-none font-medium transition-colors"
                            ></textarea>
                        </div>
                        
                        <button
                            type="submit"
                            disabled={status === 'loading'}
                            className="w-full bg-black text-white py-5 rounded-xl font-bold text-sm tracking-widest uppercase hover:bg-gray-900 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {status === 'loading' ? (
                                <span className="flex items-center justify-center gap-2">
                                    <span className="loading-dots">
                                        <span></span><span></span><span></span>
                                    </span>
                                    Procesando...
                                </span>
                            ) : 'Reservar Setup — 97€'}
                        </button>
                        
                        <p className="text-center text-xs text-gray-500 font-medium">
                            <svg className="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Tus datos están seguros. Garantía de satisfacción de 7 días.
                        </p>
                    </form>
                </div>
            );
        };

        const Footer = () => {
            const [email, setEmail] = useState('');
            const [subscribeStatus, setSubscribeStatus] = useState('idle');

            const handleSubscribe = (e) => {
                e.preventDefault();
                if (!email.trim()) return;
                
                setSubscribeStatus('loading');
                setTimeout(() => {
                    setSubscribeStatus('success');
                    setEmail('');
                    setTimeout(() => setSubscribeStatus('idle'), 3000);
                }, 1500);
            };

            return (
                <footer className="bg-black text-white pt-20 pb-12">
                    <div className="container mx-auto px-4 md:px-8">
                        <div className="grid lg:grid-cols-4 gap-12 mb-16">
                            <div>
                                <div className="flex items-center gap-3 mb-6">
                                    <div className="w-12 h-12 bg-white rounded-2xl flex items-center justify-center">
                                        <span className="text-black font-black text-base">IUC</span>
                                    </div>
                                    <div>
                                        <span className="text-xl font-black">IUC</span>
                                        <p className="text-xs text-gray-400">by IUConnect</p>
                                    </div>
                                </div>
                                <p className="text-gray-400 mb-6">
                                    Transformamos organizaciones con tecnología inteligente desde 2020.
                                </p>
                                <div className="flex gap-4">
                                    <a href="#" className="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors">
                                        <Icon name="x" className="w-5 h-5" />
                                    </a>
                                    <a href="#" className="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors">
                                        <Icon name="instagram" className="w-5 h-5" />
                                    </a>
                                    <a href="#" className="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors">
                                        <Icon name="linkedin" className="w-5 h-5" />
                                    </a>
                                </div>
                            </div>
                            
                            <div>
                                <h4 className="text-lg font-black mb-6">Producto</h4>
                                <ul className="space-y-4">
                                    <li><a href="#features" className="text-gray-400 hover:text-white transition-colors">Características</a></li>
                                    <li><a href="#industry" className="text-gray-400 hover:text-white transition-colors">Sectores</a></li>
                                    <li><a href="#pricing" className="text-gray-400 hover:text-white transition-colors">Precios</a></li>
                                    <li><a href="#process" className="text-gray-400 hover:text-white transition-colors">Proceso</a></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="text-lg font-black mb-6">Empresa</h4>
                                <ul className="space-y-4">
                                    <li><a href="#" className="text-gray-400 hover:text-white transition-colors">Sobre nosotros</a></li>
                                    <li><a href="#" className="text-gray-400 hover:text-white transition-colors">Blog</a></li>
                                    <li><a href="#" className="text-gray-400 hover:text-white transition-colors">Carreras</a></li>
                                    <li><a href="#" className="text-gray-400 hover:text-white transition-colors">Contacto</a></li>
                                    <li><a href="admin/legaltext.html" className="text-gray-400 hover:text-white transition-colors">Política de privacidad</a></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="text-lg font-black mb-6">Newsletter</h4>
                                <p className="text-gray-400 mb-4">Recibe las últimas novedades sobre transformación digital.</p>
                                <form onSubmit={handleSubscribe} className="flex gap-2">
                                    <input
                                        type="email"
                                        placeholder="Tu email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        className="flex-1 px-4 py-3 bg-white/10 rounded-lg border border-white/20 focus:border-white outline-none"
                                    />
                                    <button 
                                        type="submit"
                                        disabled={subscribeStatus === 'loading'}
                                        className="px-6 py-3 bg-white text-black rounded-lg font-bold text-sm hover:bg-gray-100 transition-colors disabled:opacity-50"
                                    >
                                        {subscribeStatus === 'loading' ? '...' : '?'}
                                    </button>
                                </form>
                                {subscribeStatus === 'success' && (
                                    <p className="text-green-400 text-sm mt-2">¡Suscrito correctamente!</p>
                                )}
                            </div>
                        </div>
                        
                        <div className="border-t border-white/10 pt-8">
                            <div className="flex flex-col md:flex-row justify-between items-center gap-4">
                                <div className="text-center md:text-left">
                                    <p className="text-sm text-gray-400">
                                        © 2024 IUC. Todos los derechos reservados.
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        info.iuconnect@gmail.com | +34 123 456 789
                                    </p>
                                </div>
                                
                                <div className="flex gap-8 text-sm text-gray-400">
                                    <a href="#" className="hover:text-white transition-colors">Privacidad</a>
                                    <a href="#" className="hover:text-white transition-colors">Términos</a>
                                    <a href="#" className="hover:text-white transition-colors">Cookies</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            );
        };

        const App = () => {
            const [scrollProgress, setScrollProgress] = useState(0);

            useEffect(() => {
                const handleScroll = () => {
                    const totalScroll = document.documentElement.scrollHeight - window.innerHeight;
                    setScrollProgress((window.scrollY / totalScroll) * 100);
                };
                window.addEventListener('scroll', handleScroll);
                return () => window.removeEventListener('scroll', handleScroll);
            }, []);

            return (
                <div className="min-h-screen bg-white">
                    {/* Progress Bar */}
                    <div 
                        className="fixed top-0 left-0 h-1 bg-gradient-to-r from-black via-gray-800 to-black z-[100] transition-all duration-300"
                        style={{ width: `${scrollProgress}%` }}
                    />
                    
                    <Navbar />
                    <Hero />
                    <Features />
                    <IndustryKits />
                    <Process />
                    <Pricing />
                    
                    {/* Contact Section */}
                    <section id="contact" className="py-24 bg-white">
                        <div className="container mx-auto px-4 md:px-8">
                            <div className="grid lg:grid-cols-2 gap-16 max-w-6xl mx-auto">
                                <div>
                                    <h2 className="text-5xl md:text-7xl font-black text-black mb-8 leading-tight">
                                        ¿Listo para
                                        <br />
                                        <span className="text-gradient-premium">transformar</span>
                                        <br />
                                        tu organización?
                                    </h2>
                                    <p className="text-xl text-gray-600 mb-12">
                                        Agenda una demostración personalizada. En 7 días estarás operando con tu nueva intranet.
                                    </p>
                                    
                                    <div className="space-y-6">
                                        {[
                                            { icon: 'discovery', title: 'Análisis personalizado', desc: 'Evaluación detallada de tus necesidades' },
                                            { icon: 'implementation', title: 'Propuesta estratégica', desc: 'Plan de implementación 100% personalizado' },
                                            { icon: 'launch', title: 'Lanzamiento garantizado', desc: 'Puesta en producción en tiempo récord' }
                                        ].map((item, index) => (
                                            <div key={index} className="flex items-center gap-4 p-6 bg-gray-50 rounded-2xl hover:bg-gray-100 transition-colors">
                                                <div className="text-black">
                                                    <Icon name={item.icon} className="w-7 h-7" />
                                                </div>
                                                <div>
                                                    <h4 className="font-black text-black">{item.title}</h4>
                                                    <p className="text-gray-600 text-sm">{item.desc}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    
                                    <div className="mt-12 p-6 bg-black text-white rounded-2xl">
                                        <div className="flex items-center gap-4">
                                            <div className="text-white">
                                                <Icon name="support" className="w-9 h-9" />
                                            </div>
                                            <div>
                                                <h4 className="font-black text-lg">Respuesta en 24h</h4>
                                                <p className="text-gray-300 text-sm">Nuestro equipo te contactará en menos de 24 horas</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <Contact />
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <Footer />
                </div>
            );
        };

        const container = document.getElementById('app');
        const fallback = document.getElementById('static-fallback');
        const bootDebug = document.getElementById('boot-debug');
        const setBootDebug = (msg) => {
            if (bootDebug) bootDebug.textContent = msg;
        };
        const showFallback = () => {
            if (fallback) fallback.style.display = '';
        };
        const hideFallback = () => {
            if (fallback) fallback.style.display = 'none';
        };

        const onFatalError = (e) => {
            showFallback();
            setBootDebug('Error JS: ' + String(e?.error?.message || e?.reason?.message || e?.message || e?.error || e?.reason || e));
            console.error('Error JS:', e?.error || e);
        };
        window.addEventListener('error', onFatalError);
        window.addEventListener('unhandledrejection', onFatalError);

        setBootDebug(
            'React: ' + (window.React ? 'OK' : 'NO') + '\n' +
            'ReactDOM: ' + (window.ReactDOM ? 'OK' : 'NO') + '\n' +
            'Babel: ' + (window.Babel ? 'OK' : 'NO')
        );

        if (container) {
            try {
                if (window.ReactDOM && typeof window.ReactDOM.createRoot === 'function') {
                    window.ReactDOM.createRoot(container).render(<App />);
                    hideFallback();
                } else if (window.ReactDOM && typeof window.ReactDOM.render === 'function') {
                    window.ReactDOM.render(<App />, container);
                    hideFallback();
                } else {
                    showFallback();
                    console.error('ReactDOM no disponible: revisa que carguen `assets/vendor/react*.js`.');
                }
            } catch (e) {
                showFallback();
                console.error('Error renderizando React:', e);
            }
        } else {
            showFallback();
        }
    </script>
</body>
</html>










