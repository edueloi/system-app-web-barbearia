<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Develoi Agenda • Sistema de Gestão Premium</title>
    
    <meta name="description" content="O sistema definitivo para salões e barbearias. Controle estoque em gramas, agenda via WhatsApp e financeiro em um só lugar.">
    <meta name="theme-color" content="#4f46e5">

    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- VARIÁVEIS E BASE --- */
        /* --- CSS PARA SEÇÕES PROVOCATIVAS --- */
        .transition-all { transition: all 0.3s ease; }

        /* Efeitos Hover nos Cards Escuros */
        .hover-card-danger:hover {
            background: rgba(220, 53, 69, 0.1) !important;
            border-color: #dc3545 !important;
            transform: translateY(-5px);
        }
        .hover-card-warning:hover {
            background: rgba(255, 193, 7, 0.1) !important;
            border-color: #ffc107 !important;
            transform: translateY(-5px);
        }
        .hover-card-primary:hover {
            background: rgba(79, 70, 229, 0.15) !important;
            border-color: var(--primary) !important;
            transform: translateY(-5px);
        }
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #ec4899;
            --accent: #8b5cf6;
            --dark: #0f172a;
            --light: #f8fafc;
            --success: #10b981;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.6);
            --shadow-soft: 0 20px 40px -10px rgba(0,0,0,0.08);
            --shadow-strong: 0 25px 60px -15px rgba(15, 23, 42, 0.15);
        }

        * { box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: var(--dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, .brand-text { font-family: 'Outfit', sans-serif; }
        
        a { text-decoration: none; }

        /* Scrollbar Personalizada */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }


        /* --- NOVO PRELOADER (EFEITO VÍDEO) --- */
        #intro-loader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #ffffff; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            /* A transição de saída (fade out) será controlada pelo JS/CSS */
            transition: opacity 0.8s ease-in-out, visibility 0.8s ease-in-out;
        }

        .logo-animation-container {
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif; /* Mesma fonte do logo */
            overflow: hidden; /* Importante para o efeito de deslizar */
        }

        /* O "D" Grande dentro do quadrado arredondado */
        .brand-d-anim {
            font-size: 5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 18px;
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 8px 32px rgba(79,70,229,0.10);
            margin-right: 5px;
            z-index: 2;
            /* Animação de entrada do D */
            animation: pop-in-d 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            opacity: 0; transform: scale(0.5);
        }

        /* O resto do texto "EVELOI" */
        .brand-text-anim {
            font-size: 3rem; /* Um pouco menor que o D para equilíbrio visual */
            font-weight: 700;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-left: 5px;
            /* Começa escondido */
            max-width: 0;
            opacity: 0;
            white-space: nowrap;
            overflow: hidden;
            /* Animação de deslizar */
            animation: slide-reveal 1s cubic-bezier(0.77, 0, 0.175, 1) forwards;
            animation-delay: 0.6s; /* Espera o D aparecer */
        }
        /* Removido fundo cinza das letras */

        /* Keyframes */
        @keyframes pop-in-d {
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes slide-reveal {
            to { 
                max-width: 300px; /* Largura suficiente para mostrar o texto */
                opacity: 1; 
                margin-left: 15px;
            }
        }

        /* Responsivo para celular */
        @media (max-width: 768px) {
            .brand-d-anim { font-size: 3.5rem; width: 60px; height: 60px; }
            .brand-text-anim { font-size: 2rem; }
        }

        /* --- FUNDO AURORA --- */
        .aurora-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden;
            background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
        }
        .aurora-blob {
            position: absolute; border-radius: 50%; filter: blur(90px); opacity: 0.5;
            animation: float-blob 15s infinite alternate;
        }
        .blob-1 { top: -10%; left: -10%; width: 60vw; height: 60vw; background: #c7d2fe; animation-duration: 25s; }
        .blob-2 { bottom: -10%; right: -10%; width: 50vw; height: 50vw; background: #fbcfe8; animation-duration: 20s; }
        @keyframes float-blob {
            0% { transform: translate(0, 0); }
            100% { transform: translate(40px, -40px); }
        }

        /* --- NAVBAR & MENU MOBILE (APP FEEL) --- */
        .navbar {
            padding: 1rem 0;
            transition: all 0.3s;
            background: transparent;
            z-index: 100;
        }
        .navbar.scrolled {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 0.8rem 0;
        }

        /* Botão Hamburger Moderno */
        .menu-toggle {
            width: 40px; height: 40px;
            background: white;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            z-index: 105;
        }
        
        /* Menu Overlay Fullscreen (Estilo App) */
        .mobile-menu-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            z-index: 100;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            opacity: 0; visibility: hidden;
            transition: all 0.4s ease;
        }
        .mobile-menu-overlay.active { opacity: 1; visibility: visible; }
        
        .mobile-nav-link {
            font-size: 1.5rem; font-weight: 700; color: var(--dark);
            margin: 15px 0; position: relative;
        }
        .mobile-nav-link::after {
            content: ''; position: absolute; width: 0; height: 3px;
            bottom: -5px; left: 0; background: var(--primary);
            transition: width 0.3s;
        }
        .mobile-nav-link:hover::after { width: 100%; }

        /* --- HERO SECTION --- */
        .hero-section { padding-top: 150px; padding-bottom: 100px; position: relative; }
        
        .hero-title {
            font-size: 3.5rem; font-weight: 800; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -0.02em;
        }
        @media (max-width: 768px) { .hero-title { font-size: 2.5rem; } }

        .text-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        
        .typing-wrapper { display: inline-block; }
        .typing-cursor {
            display: inline-block; width: 4px; height: 1em; background-color: var(--primary);
            margin-left: 4px; animation: blink 0.8s infinite; vertical-align: middle;
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        /* Botões Premium */
        .btn-main {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; border: none; padding: 14px 32px; border-radius: 999px;
            font-weight: 600; font-size: 1rem;
            box-shadow: 0 10px 30px -5px rgba(79, 70, 229, 0.5);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-main:hover { transform: translateY(-3px); box-shadow: 0 20px 40px -5px rgba(236, 72, 153, 0.5); color: white; }

        .btn-secondary-soft {
            background: white; color: var(--dark); border: 1px solid #e2e8f0;
            padding: 14px 32px; border-radius: 999px; font-weight: 600;
            transition: all 0.3s;
        }
        .btn-secondary-soft:hover { border-color: var(--primary); color: var(--primary); background: #eef2ff; }

        /* --- MOCKUP DO CELULAR (O FOCO) --- */
        .mockup-wrapper {
            position: relative; z-index: 10; perspective: 1000px;
            margin-top: 30px;
        }
        
        .phone-frame {
            width: 310px; height: 620px;
            background: #fff; border-radius: 48px;
            box-shadow: 
                0 0 0 10px #fff,
                0 0 0 11px #e2e8f0,
                0 40px 100px -20px rgba(0,0,0,0.2);
            margin: 0 auto; position: relative; overflow: hidden;
            transform: rotateY(-5deg) rotateX(3deg);
            transition: transform 0.5s ease;
            animation: float-phone 6s ease-in-out infinite;
        }
        .mockup-wrapper:hover .phone-frame { transform: rotateY(0) rotateX(0); }

        @keyframes float-phone {
            0%, 100% { transform: translateY(0) rotateY(-5deg); }
            50% { transform: translateY(-15px) rotateY(-5deg); }
        }

        /* Conteúdo do Celular */
        .screen-content {
            background: #f8fafc; height: 100%; width: 100%; padding: 25px 20px;
            display: flex; flex-direction: column;
        }
        
        .app-card {
            background: white; border-radius: 18px; padding: 14px;
            margin-bottom: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            display: flex; align-items: center; gap: 12px;
            opacity: 0; animation: slide-up 0.6s forwards;
        }
        
        /* O Card Preto de Faturamento (Pedido Específico) */
        .revenue-card-dark {
            background: #0f172a; color: white;
            border-radius: 22px; padding: 20px;
            margin-top: 15px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.25);
            opacity: 0; animation: slide-up 0.8s forwards 0.3s;
        }
        .revenue-progress {
            height: 6px; background: rgba(255,255,255,0.15);
            border-radius: 10px; margin-top: 12px; overflow: hidden;
        }
        .revenue-bar { width: 70%; height: 100%; background: #22c55e; border-radius: 10px; }

        /* Notificação WhatsApp Pop-up */
        .whatsapp-popup {
            position: absolute; right: -50px; top: 28%;
            background: white; border-radius: 18px; padding: 14px 18px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.12);
            width: 260px; z-index: 20;
            display: flex; align-items: center; gap: 12px;
            border-left: 4px solid #25D366;
            opacity: 0; transform: scale(0.8) translateX(30px);
            animation: pop-in 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 1.2s forwards;
        }
        .whatsapp-icon-bg {
            width: 36px; height: 36px; background: #25D366; color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }

        @keyframes pop-in { to { opacity: 1; transform: scale(1) translateX(0); } }
        @keyframes slide-up { to { opacity: 1; transform: translateY(0); } from { opacity: 0; transform: translateY(20px); } }

        @media(max-width: 991px) {
            .whatsapp-popup { right: 50%; transform: translateX(50%); top: -25px; width: 240px; }
            .phone-frame { margin-top: 40px; }
        }

        /* --- SEÇÃO LUCRO REAL (Recibo) --- */
        .section-profit { padding: 80px 0; }
        
        .receipt-card {
            background: rgba(255,255,255,0.8); backdrop-filter: blur(20px);
            border-radius: 24px; padding: 35px; border: 1px solid white;
            box-shadow: var(--shadow-strong);
            transform: rotate(-2deg); transition: transform 0.3s;
            max-width: 450px; margin: 0 auto;
        }
        .receipt-card:hover { transform: rotate(0); }
        .receipt-line { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #cbd5e1; font-size: 0.95rem; color: #64748b; }
        .receipt-line:last-child { border: none; }
        .receipt-total { background: #dcfce7; color: #15803d; padding: 15px; border-radius: 12px; margin-top: 15px; font-weight: 700; display: flex; justify-content: space-between; }

        /* --- CARDS DE RECURSOS --- */
        .feature-box {
            background: rgba(255, 255, 255, 0.5); backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 24px;
            padding: 35px 30px; height: 100%; transition: all 0.3s;
        }
        .feature-box:hover {
            background: white; transform: translateY(-5px); box-shadow: var(--shadow-soft);
        }
        .feature-icon {
            font-size: 2rem; margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* --- ACCORDION FAQ --- */
        .accordion-item { border: none; background: transparent; margin-bottom: 10px; }
        .accordion-button {
            background: white !important; border-radius: 16px !important;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03); font-weight: 600; padding: 1.2rem;
        }
        .accordion-button:not(.collapsed) {
            color: var(--primary); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.15);
        }
        .accordion-button:focus { box-shadow: none; }
        .accordion-body { background: white; border-radius: 0 0 16px 16px; padding: 1.5rem; color: #64748b; margin-top: -10px; padding-top: 2rem; }

        /* --- CTA FOOTER --- */
        .cta-section {
            background: var(--dark); border-radius: 40px; margin: 0 20px;
            padding: 80px 20px; text-align: center; color: white; position: relative; overflow: hidden;
        }
        .cta-blob {
            position: absolute; width: 400px; height: 400px; background: var(--primary);
            filter: blur(80px); opacity: 0.3; border-radius: 50%;
        }

    </style>
</head>
<body>

    <div id="intro-loader">
        <div class="logo-animation-container">
            <div class="brand-d-anim" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 18px; color: #fff;">D</div>
            <div class="brand-text-anim" id="brand-text-anim"></div>
            <span class="typing-cursor" id="brand-typing-cursor" style="display:inline-block;width:4px;height:1em;background:var(--primary);margin-left:4px;vertical-align:middle;"></span>
        </div>
    </div>

    <div class="mobile-menu-overlay" id="mobileMenu">
        <a href="#inicio" class="mobile-nav-link" onclick="toggleMenu()">Início</a>
        <a href="#lucro" class="mobile-nav-link" onclick="toggleMenu()">Custos & Lucro</a>
        <a href="#recursos" class="mobile-nav-link" onclick="toggleMenu()">Funcionalidades</a>
        <a href="#faq" class="mobile-nav-link" onclick="toggleMenu()">Dúvidas</a>
        <a href="#" class="btn btn-main mt-4 rounded-pill px-5" onclick="toggleMenu()">Entrar no Sistema</a>
        <button class="btn btn-link text-muted mt-3" onclick="toggleMenu()">Fechar</button>
    </div>

    <div class="aurora-bg">
        <div class="aurora-blob blob-1"></div>
        <div class="aurora-blob blob-2"></div>
    </div>

    <nav class="navbar fixed-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#" style="font-family: 'Outfit', sans-serif;">
                <div style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2.1rem; font-weight: 800; box-shadow: 0 8px 32px rgba(79,70,229,0.10);">D</div>
                <span style="font-size: 1.7rem; font-weight: 700; color: var(--dark); text-transform: uppercase; letter-spacing: 0.1em; margin-left: 2px;">EVELOI</span>
            </a>

            <div class="d-none d-lg-flex align-items-center gap-4">
                <a href="#lucro" class="fw-medium text-secondary hover-primary">Lucro Real</a>
                <a href="#recursos" class="fw-medium text-secondary hover-primary">Recursos</a>
                <a href="#" class="btn btn-dark rounded-pill px-4">Área do Cliente</a>
            </div>

            <div class="menu-toggle d-lg-none" onclick="toggleMenu()">
                <i class="fa-solid fa-bars text-dark fs-5"></i>
            </div>
        </div>
    </nav>

    <section id="inicio" class="hero-section">
        <div class="container">
            <div class="row align-items-center gy-5">
                
                <div class="col-lg-6 order-2 order-lg-1">
                    <div data-aos="fade-up">
                        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 bg-white border rounded-pill shadow-sm mb-4">
                            <span class="badge bg-success rounded-pill">Novo</span>
                            <small class="fw-bold text-secondary">Painel 2.0 disponível</small>
                        </div>
                        
                        <h1 class="hero-title">
                            O sistema perfeito para <br>
                            <span id="typing-text" class="text-gradient"></span><span class="typing-cursor"></span>
                        </h1>
                        
                        <p class="fs-5 text-secondary mb-5" style="line-height: 1.6;">
                            Esqueça o caderno e as planilhas complexas. O Develoi Agenda calcula o custo exato do serviço, envia lembretes no Zap e organiza sua vida.
                        </p>

                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <a href="#" class="btn btn-main d-flex align-items-center justify-content-center gap-2">
                                Testar Grátis Agora <i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <a href="#" class="btn btn-secondary-soft d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-brands fa-whatsapp fs-5 text-success"></i> Ver Demo
                            </a>
                        </div>
                        
                        <div class="mt-5 d-flex gap-4 align-items-center opacity-75">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check-circle text-primary"></i> <span>Agenda</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check-circle text-primary"></i> <span>Estoque</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check-circle text-primary"></i> <span>Financeiro</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 order-1 order-lg-2" data-aos="fade-left" data-aos-delay="200">
                    <div class="mockup-wrapper">
                        
                        <div class="whatsapp-popup">
                            <div class="whatsapp-icon-bg">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size:0.7rem;">Agora</small>
                                <strong class="text-dark" style="font-size:0.8rem; line-height:1.2;">Novo horário agendado.<br>Confirmar pelo Zap?</strong>
                            </div>
                        </div>

                        <div class="phone-frame">
                            <div class="screen-content">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div>
                                        <small class="text-muted d-block" style="font-size:0.75rem;">Painel do dia</small>
                                        <h5 class="fw-bold m-0">Studio Elite</h5>
                                    </div>
                                    <img src="https://ui-avatars.com/api/?name=User&background=random" class="rounded-circle" width="40" alt="User">
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-bold text-dark">Agenda de hoje</span>
                                    <span class="badge bg-dark rounded-pill">26 Nov</span>
                                </div>

                                <div class="app-card">
                                    <div class="fw-bold fs-5 text-dark" style="min-width: 55px;">09:00</div>
                                    <div style="width: 4px; height: 40px; background: #ec4899; border-radius: 4px;"></div>
                                    <div>
                                        <div class="fw-bold text-dark small">Corte & Barba</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Cliente: Marcos P.</div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success ms-auto">Confirmado</span>
                                </div>

                                <div class="app-card" style="animation-delay: 0.2s;">
                                    <div class="fw-bold fs-5 text-dark" style="min-width: 55px;">10:30</div>
                                    <div style="width: 4px; height: 40px; background: #4f46e5; border-radius: 4px;"></div>
                                    <div>
                                        <div class="fw-bold text-dark small">Hidratação</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Cliente: Ana Júlia</div>
                                    </div>
                                    <span class="badge bg-warning-subtle text-warning ms-auto">Pendente</span>
                                </div>

                                <div class="revenue-card-dark">
                                    <small class="text-white-50">Faturamento previsto de hoje</small>
                                    <h3 class="fw-bold m-0 mt-1">R$ 480,00</h3>
                                    <div class="d-flex justify-content-between mt-3 text-white-50" style="font-size: 0.7rem;">
                                        <span>70% da meta</span>
                                        <span class="text-success fw-bold">+18% vs semana passada</span>
                                    </div>
                                    <div class="revenue-progress">
                                        <div class="revenue-bar"></div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Seções Provocativas -->
    <section class="py-5 bg-dark text-white position-relative overflow-hidden">
        <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10" style="background: radial-gradient(circle at 50% 50%, #4f46e5, transparent 70%);"></div>
        <div class="container py-5 position-relative z-2">
            <div class="text-center mb-5" data-aos="fade-up">
                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill mb-3">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Pare de perder dinheiro
                </span>
                <h2 class="display-5 fw-bold">Onde está o lucro que você não vê?</h2>
                <p class="text-white-50 fs-5 mx-auto" style="max-width: 700px;">
                    Agenda cheia não significa conta cheia. Se você não controla esses 3 pontos, você está trabalhando de graça.
                </p>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="p-4 rounded-4 border border-secondary border-opacity-25 h-100 bg-black bg-opacity-25 hover-card-danger transition-all">
                        <div class="mb-4 d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                        <h4 class="fw-bold mb-3">A Cadeira Vazia</h4>
                        <p class="text-white-50 mb-0">
                            O cliente esqueceu e não avisou? Esse horário perdido jamais volta. 
                            <span class="text-white fw-bold">Um "bolo" por dia custa R$ 2.400,00 no fim do mês.</span> 
                            Nosso sistema cobra confirmação automática e evita isso.
                        </p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="p-4 rounded-4 border border-secondary border-opacity-25 h-100 bg-black bg-opacity-25 hover-card-warning transition-all">
                        <div class="mb-4 d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fa-solid fa-fill-drip"></i>
                        </div>
                        <h4 class="fw-bold mb-3">O "Chutômetro"</h4>
                        <p class="text-white-50 mb-0">
                            Você gasta 50g de produto mas cobra como se usasse 20g? 
                            <span class="text-white fw-bold">Você está pagando para trabalhar.</span>
                            O Develoi desconta a grama exata do estoque e te mostra se o serviço deu lucro ou prejuízo.
                        </p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="p-4 rounded-4 border border-secondary border-opacity-25 h-100 bg-black bg-opacity-25 hover-card-primary transition-all">
                        <div class="mb-4 d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Escravo do Zap</h4>
                        <p class="text-white-50 mb-0">
                            Parar o atendimento 10 vezes para responder "tem horário hoje?". 
                            Isso quebra seu ritmo e irrita quem está na cadeira.
                            <span class="text-white fw-bold">Deixe o link da agenda trabalhar por você 24h.</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container py-5">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5" data-aos="fade-right">
                    <h2 class="display-6 fw-bold mb-4">Qual realidade você quer viver?</h2>
                    <p class="text-secondary fs-5 mb-4">
                        A diferença entre um profissional cansado e um empresário de sucesso é a ferramenta que ele usa.
                    </p>
                    <a href="#" class="btn btn-main btn-lg px-5 shadow-lg">Mudar minha realidade <i class="fa-solid fa-arrow-right ms-2"></i></a>
                </div>
                <div class="col-lg-7" data-aos="fade-left">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-25 opacity-75" style="transform: scale(0.98);">
                            <div class="text-danger fs-2"><i class="fa-solid fa-xmark"></i></div>
                            <div>
                                <h5 class="fw-bold text-danger mb-1">O Jeito Antigo</h5>
                                <p class="mb-0 small text-muted">Caderno rasurado, clientes esquecendo horário, sem saber quanto lucrou no mês, estoque furado.</p>
                            </div>
                        </div>
                        <div class="text-center text-primary fs-3 my-n2 position-relative z-2">
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        <div class="d-flex align-items-center gap-3 p-4 rounded-4 bg-white border border-success border-opacity-50 shadow-lg position-relative">
                            <div class="position-absolute top-0 end-0 m-3 text-success small fw-bold"><i class="fa-solid fa-star me-1"></i> Com Develoi</div>
                            <div class="d-flex align-items-center justify-content-center bg-success text-white rounded-circle flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Gestão Profissional</h5>
                                <p class="mb-0 small text-secondary">Agenda automática, lucro calculado por serviço, confirmação no WhatsApp, clientes fidelizados e paz mental.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="lucro" class="section-profit">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 order-2 order-lg-1" data-aos="zoom-in">
                    <div class="receipt-card">
                        <div class="text-center mb-4 pb-3 border-bottom">
                            <h4 class="fw-bold m-0">Análise do Serviço</h4>
                            <small class="text-muted">Hidratação Profunda</small>
                        </div>
                        
                        <div class="receipt-line">
                            <span><i class="fa-solid fa-bottle-droplet me-2"></i>Shampoo (15ml)</span>
                            <span class="text-danger">- R$ 1,20</span>
                        </div>
                        <div class="receipt-line">
                            <span><i class="fa-solid fa-jar me-2"></i>Máscara (25g)</span>
                            <span class="text-danger">- R$ 4,50</span>
                        </div>
                        <div class="receipt-line">
                            <span><i class="fa-solid fa-bolt me-2"></i>Taxa (Luz/Água)</span>
                            <span class="text-danger">- R$ 2,00</span>
                        </div>
                        <div class="receipt-line">
                            <span><i class="fa-solid fa-user-tag me-2"></i>Comissão (30%)</span>
                            <span class="text-danger">- R$ 24,00</span>
                        </div>

                        <div class="d-flex justify-content-between mt-3 fw-bold">
                            <span>Valor Cobrado</span>
                            <span>R$ 80,00</span>
                        </div>

                        <div class="receipt-total">
                            <span>LUCRO LÍQUIDO</span>
                            <span>R$ 48,30</span>
                        </div>
                        
                        <div class="position-absolute top-0 end-0 translate-middle badge bg-primary rounded-pill px-3 py-2 shadow">
                            Cálculo Automático
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 order-1 order-lg-2 ps-lg-5" data-aos="fade-left">
                    <span class="text-primary fw-bold text-uppercase small ls-1">Engenharia de Menu</span>
                    <h2 class="display-5 fw-bold mb-4 mt-2">Você sabe quanto custa <span class="text-gradient">20g do seu creme?</span></h2>
                    <p class="fs-5 text-secondary mb-4">
                        A maioria dos salões perde dinheiro nos detalhes. O Develoi Agenda calcula centavo por centavo.
                    </p>
                    <p class="text-muted mb-4">
                        Ao realizar o serviço, o sistema desconta automaticamente a fração do estoque (gramas ou ml) e soma aos custos fixos e comissões.
                    </p>
                    
                    <ul class="list-unstyled">
                        <li class="d-flex gap-3 mb-3">
                            <div class="bg-white p-2 rounded shadow-sm text-primary h-100"><i class="fa-solid fa-flask"></i></div>
                            <div>
                                <strong>Conversão Inteligente</strong>
                                <p class="small text-muted m-0">Compre em Litro, use em ML. O sistema faz a conta.</p>
                            </div>
                        </li>
                        <li class="d-flex gap-3">
                            <div class="bg-white p-2 rounded shadow-sm text-warning h-100"><i class="fa-solid fa-lightbulb"></i></div>
                            <div>
                                <strong>Custos Invisíveis</strong>
                                <p class="small text-muted m-0">Inclua taxas de cartão e energia no cálculo.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="recursos" class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="fw-bold display-6">Controle Absoluto</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">
                    Funcionalidades pensadas para quem não tem tempo a perder.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-box">
                        <i class="fa-solid fa-boxes-stacked feature-icon"></i>
                        <h4 class="fw-bold">Estoque Fracionado</h4>
                        <p class="text-secondary small">Controle validade e quantidade exata. O sistema avisa quando o produto está acabando.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-box">
                        <i class="fa-brands fa-whatsapp feature-icon"></i>
                        <h4 class="fw-bold">Confirmação via Zap</h4>
                        <p class="text-secondary small">Chegou o dia? Clique em "Confirmar" e o sistema abre o WhatsApp do cliente com a mensagem pronta.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box">
                        <i class="fa-solid fa-file-invoice-dollar feature-icon"></i>
                        <h4 class="fw-bold">Recibos Profissionais</h4>
                        <p class="text-secondary small">Emita recibos de serviços ou vendas de produtos com sua logo. Profissionalismo total.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="faq" class="py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">Dúvidas Frequentes</h2>
                    </div>

                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item" data-aos="fade-up">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Preciso baixar algum aplicativo?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Não! O Develoi Agenda funciona direto no navegador do seu celular ou computador. É leve, rápido e não ocupa memória.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="100">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Serve para barbearia com vários profissionais?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sim. Temos planos onde você cadastra vários profissionais, define comissões diferentes e cada um tem seu acesso.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Como funciona o teste grátis?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Você cria sua conta em 1 minuto e tem 7 dias para usar tudo. Não pedimos cartão de crédito. Se gostar, você assina depois.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container pb-5">
        <div class="cta-section" data-aos="zoom-in">
            <div class="cta-blob" style="top: -50%; left: -50%;"></div>
            <div class="cta-blob" style="bottom: -50%; right: -50%; background: var(--secondary);"></div>
            
            <div class="position-relative z-2">
                <h2 class="fw-bold display-5 mb-3">Comece a organizar hoje.</h2>
                <p class="fs-5 opacity-75 mb-5 mx-auto" style="max-width: 600px;">
                    Junte-se a mais de 2.000 profissionais que pararam de perder dinheiro e assumiram o controle.
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="#" class="btn btn-light rounded-pill px-5 py-3 fw-bold text-primary shadow-lg">Criar Conta Grátis</a>
                    <a href="#" class="btn btn-outline-light rounded-pill px-5 py-3 fw-bold">Falar no WhatsApp</a>
                </div>
                <p class="small mt-4 opacity-50">Sem fidelidade. Cancele quando quiser.</p>
            </div>
        </div>
    </div>

    <footer class="py-5 bg-white border-top text-center text-md-start">
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-4">
                    <h5 class="fw-bold text-dark mb-3">Develoi Agenda</h5>
                    <p class="text-muted small">Sistema de gestão inteligente para profissionais da beleza que querem crescer.</p>
                </div>
                <div class="col-md-2 offset-md-2">
                    <h6 class="fw-bold mb-3">Produto</h6>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2">
                        <li><a href="#" class="text-reset">Recursos</a></li>
                        <li><a href="#" class="text-reset">Preços</a></li>
                        <li><a href="#" class="text-reset">Atualizações</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6 class="fw-bold mb-3">Suporte</h6>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2">
                        <li><a href="#" class="text-reset">Central de Ajuda</a></li>
                        <li><a href="#" class="text-reset">WhatsApp</a></li>
                        <li><a href="#" class="text-reset">Termos de Uso</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6 class="fw-bold mb-3">Social</h6>
                    <div class="d-flex gap-3 text-secondary">
                        <i class="fa-brands fa-instagram fs-5 cursor-pointer"></i>
                        <i class="fa-brands fa-facebook fs-5 cursor-pointer"></i>
                        <i class="fa-brands fa-youtube fs-5 cursor-pointer"></i>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5 pt-4 border-top text-muted small">
                © 2025 Develoi. Feito com <i class="fa-solid fa-heart text-danger"></i> no Brasil.
            </div>
        </div>
    </footer>

    <div class="fixed-bottom p-3 d-lg-none" style="z-index: 999;">
        <div class="bg-dark text-white rounded-pill p-2 shadow-lg d-flex justify-content-between align-items-center ps-4 pe-2 border border-secondary border-opacity-25" style="backdrop-filter: blur(10px); background: rgba(15, 23, 42, 0.95) !important;">
            <div class="d-flex flex-column" style="line-height: 1.1;">
                <span class="fw-bold text-success" style="font-size: 0.9rem;">R$ 19,90 <small class="text-white">/mês</small></span>
                <span class="small text-white-50" style="font-size: 0.65rem;">Plano Completo</span>
            </div>
            <a href="#" class="btn btn-primary rounded-pill fw-bold px-4 btn-glow">Quero <i class="fa-solid fa-arrow-right ms-2"></i></a>
        </div>
    </div>

    <?php
        // Seu número com código do país (55) e DDD (15)
        $meu_whatsapp = "5515992897425";
        // Nome do Atendente Virtual
        $nome_robo = "Assistente Develoi";
    ?>

    <style>
        .chat-trigger {
            position: fixed; bottom: 20px; right: 20px;
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            transition: transform 0.3s;
            animation: pulse-green 2s infinite;
        }
        .chat-trigger:hover { transform: scale(1.1); }
        .chat-trigger i { color: white; font-size: 30px; }
        .chat-window {
            position: fixed; bottom: 90px; right: 20px;
            width: 350px; max-width: 90%;
            height: 500px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.15);
            z-index: 9999;
            display: flex; flex-direction: column;
            overflow: hidden;
            opacity: 0; pointer-events: none; transform: translateY(20px);
            transition: all 0.3s ease;
        }
        .chat-window.active { opacity: 1; pointer-events: all; transform: translateY(0); }
        .chat-header {
            background: linear-gradient(135deg, #4f46e5, #ec4899);
            padding: 15px; color: white;
            display: flex; align-items: center; gap: 10px;
        }
        .chat-avatar { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; }
        .chat-close { margin-left: auto; cursor: pointer; opacity: 0.8; }
        .chat-body {
            flex: 1; padding: 15px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex; flex-direction: column; gap: 10px;
        }
        .msg { max-width: 80%; padding: 10px 15px; border-radius: 15px; font-size: 0.9rem; line-height: 1.4; position: relative; animation: slide-up 0.3s ease; }
        .msg-bot { background: #e2e8f0; color: #1e293b; border-bottom-left-radius: 2px; align-self: flex-start; }
        .msg-user { background: #4f46e5; color: white; border-bottom-right-radius: 2px; align-self: flex-end; }
        .chat-options { display: flex; flex-direction: column; gap: 8px; margin-top: 5px; }
        .btn-option {
            background: white; border: 1px solid #4f46e5; color: #4f46e5;
            padding: 8px 12px; border-radius: 20px; font-size: 0.85rem;
            cursor: pointer; text-align: left; transition: all 0.2s;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn-option:hover { background: #4f46e5; color: white; }
        .btn-option i { font-size: 0.8rem; }
        .typing-indicator { font-size: 0.7rem; color: #94a3b8; margin-left: 10px; display: none; }
        @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(37, 211, 102, 0); } 100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); } }
        @keyframes slide-up { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <div class="chat-trigger" onclick="toggleChat()">
        <i class="fa-brands fa-whatsapp"></i>
    </div>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="chat-avatar"><i class="fa-solid fa-robot"></i></div>
            <div>
                <div class="fw-bold" style="font-size: 0.95rem;"><?php echo $nome_robo; ?></div>
                <div style="font-size: 0.7rem; opacity: 0.9;">Online agora</div>
            </div>
            <div class="chat-close" onclick="toggleChat()"><i class="fa-solid fa-xmark"></i></div>
        </div>
        <div class="chat-body" id="chatBody"></div>
        <div class="typing-indicator" id="typingIndicator">Digitando...</div>
    </div>

    <script>
        const whatsappNumber = "<?php echo $meu_whatsapp; ?>";
        const chatBody = document.getElementById('chatBody');
        const chatWindow = document.getElementById('chatWindow');
        const typingInd = document.getElementById('typingIndicator');
        let step = 0;
        function toggleChat() {
            chatWindow.classList.toggle('active');
            if(chatWindow.classList.contains('active') && chatBody.innerHTML === '') {
                startConversation();
            }
        }
        function addMessage(text, sender) {
            const div = document.createElement('div');
            div.classList.add('msg', sender === 'bot' ? 'msg-bot' : 'msg-user');
            div.innerHTML = text;
            chatBody.appendChild(div);
            chatBody.scrollTop = chatBody.scrollHeight;
        }
        function botType(text, delay = 600) {
            typingInd.style.display = 'block';
            chatBody.scrollTop = chatBody.scrollHeight;
            setTimeout(() => {
                typingInd.style.display = 'none';
                addMessage(text, 'bot');
            }, delay);
        }
        function showOptions(optionsArray) {
            setTimeout(() => {
                const div = document.createElement('div');
                div.classList.add('chat-options');
                optionsArray.forEach(opt => {
                    const btn = document.createElement('button');
                    btn.classList.add('btn-option');
                    btn.innerHTML = `${opt.text} <i class="fa-solid fa-chevron-right"></i>`;
                    btn.onclick = () => handleUserChoice(opt.text, opt.action, opt.payload);
                    div.appendChild(btn);
                });
                chatBody.appendChild(div);
                chatBody.scrollTop = chatBody.scrollHeight;
            }, 800);
        }
        function startConversation() {
            botType("Olá! 👋 Eu sou o assistente virtual da Develoi. Seja muito bem-vindo(a)! 😊");
            setTimeout(() => {
                botType("Posso te ajudar a economizar tempo, controlar seu salão e aumentar seu lucro. O que você gostaria de saber?");
                showOptions([
                    { text: "Quero saber o preço", action: 'precos' },
                    { text: "Como funciona?", action: 'como_funciona' },
                    { text: "Controle de estoque", action: 'estoque' },
                    { text: "Perguntas frequentes", action: 'faq' },
                    { text: "Falar com humano", action: 'zap_humano' }
                ]);
            }, 900);
        }
        function handleUserChoice(userText, action, payload) {
            const oldOptions = document.querySelector('.chat-options');
            if(oldOptions) oldOptions.remove();
            addMessage(userText, 'user');
            if (action === 'precos') {
                botType("Nós simplificamos tudo para você! 🚀");
                setTimeout(() => {
                    botType("Acesso completo a todas as funções por apenas **R$ 19,90/mês**. Sem taxas de adesão, sem fidelidade e com suporte humano sempre que precisar.");
                    setTimeout(() => {
                        botType("Quer garantir esse valor promocional agora?");
                        showOptions([
                            { text: "Sim, quero por R$ 19,90/mês", action: 'zap_plano_19' },
                            { text: "Quais funções estão inclusas?", action: 'como_funciona' },
                            { text: "Tenho outra dúvida", action: 'faq' },
                            { text: "Voltar ao menu", action: 'restart' }
                        ]);
                    }, 1200);
                }, 900);
            }
            else if (action === 'como_funciona') {
                botType("O Develoi é um sistema online para salões, barbearias e clínicas de beleza. Você pode controlar agendamentos, estoque, caixa, comissões, relatórios e muito mais, tudo pelo celular ou computador.");
                                botType("O Develoi é um sistema online para salões, barbearias e clínicas de beleza. Você pode controlar agendamentos, estoque, caixa, comissões, relatórios e muito mais, tudo pelo celular ou computador, pagando apenas **R$ 19,90/mês**.");
                setTimeout(() => {
                    botType("Tudo é muito simples de usar e você recebe treinamento gratuito. Quer ver um vídeo rápido ou falar com nosso time?");
                    showOptions([
                        { text: "Quero ver vídeo", action: 'zap_video' },
                        { text: "Falar com humano", action: 'zap_humano' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 1500);
            }
            else if (action === 'estoque') {
                botType("Nosso sistema desconta automaticamente a quantidade exata de produto (gramas ou ml) a cada serviço realizado. Assim, você sabe exatamente quando comprar e nunca mais perde dinheiro com desperdício!");
                setTimeout(() => {
                    botType("Quer garantir o preço promocional de R$ 19,90 e ter esse controle no seu salão?");
                    showOptions([
                        { text: "Sim, quero assinar por R$ 19,90/mês", action: 'zap_plano_19' },
                        { text: "Quero ver vídeo", action: 'zap_video' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 1800);
            }
            else if (action === 'faq') {
                botType("Perguntas frequentes:");
                setTimeout(() => {
                    showOptions([
                        { text: "Tem suporte humano?", action: 'faq_suporte' },
                        { text: "Posso cancelar quando quiser?", action: 'faq_cancelar' },
                        { text: "Tem fidelidade?", action: 'faq_fidelidade' },
                        { text: "Quais funções estão inclusas?", action: 'como_funciona' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 800);
            }
            else if (action === 'faq_suporte') {
                botType("Sim! Você tem suporte humano via WhatsApp sempre que precisar, sem custo extra.");
                setTimeout(() => {
                    showOptions([
                        { text: "Quero falar com suporte", action: 'zap_humano' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 1000);
            }
            else if (action === 'faq_cancelar') {
                botType("Você pode cancelar quando quiser, sem multa e sem burocracia. Basta avisar pelo WhatsApp.");
                setTimeout(() => {
                    showOptions([
                        { text: "Quero falar com suporte", action: 'zap_humano' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 1000);
            }
            else if (action === 'faq_fidelidade') {
                botType("Não existe fidelidade! Você é livre para usar o sistema pelo tempo que quiser.");
                setTimeout(() => {
                    showOptions([
                        { text: "Quero falar com suporte", action: 'zap_humano' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 1000);
            }
            else if (action === 'restart') {
                startConversation();
            }
            else if (action.startsWith('zap_')) {
                botType("Ótima escolha! 👏 Vou abrir seu WhatsApp para finalizarmos seu cadastro.");
                let zapMsg = "";
                if(action === 'zap_teste') zapMsg = "Olá! Vi no site e quero testar o sistema.";
                if(action === 'zap_humano') zapMsg = "Olá! Preciso tirar uma dúvida com o suporte.";
                if(action === 'zap_plano_19') zapMsg = "Olá! Quero aproveitar o plano completo por R$ 19,90 mensais.";
                                if(action === 'zap_plano_19') zapMsg = "Olá! Quero aproveitar o plano completo por R$ 19,90/mês.";
                setTimeout(() => {
                    let url = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(zapMsg)}`;
                    window.open(url, '_blank');
                    // Após abrir WhatsApp, mostrar opções de voltar ao menu ou fechar chat
                    setTimeout(() => {
                        chatBody.innerHTML = '';
                        botType("Se precisar de mais alguma coisa, estou aqui! O que deseja fazer agora?");
                        showOptions([
                            { text: "Voltar ao menu", action: 'restart' },
                            { text: "Fechar chat", action: 'close_chat' }
                        ]);
                    }, 500);
                }, 2000);
            }
            else if (action === 'close_chat') {
                chatWindow.classList.remove('active');
                chatBody.innerHTML = '';
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>

        // 1. Preloader com Animação de Logo e efeito de digitação
        window.addEventListener('load', () => {
            const preloader = document.getElementById('intro-loader');
            const brandText = 'EVELOI';
            const brandTextAnim = document.getElementById('brand-text-anim');
            const cursor = document.getElementById('brand-typing-cursor');
            let charIndex = 0;

            // Função de digitação
            function typeBrandText() {
                if (charIndex <= brandText.length) {
                    brandTextAnim.textContent = brandText.substring(0, charIndex);
                    charIndex++;
                    setTimeout(typeBrandText, 90);
                } else {
                    cursor.style.opacity = '0';
                }
            }

            // Inicia a digitação após o D aparecer (delay igual ao delay da animação do texto)
            setTimeout(() => {
                brandTextAnim.style.maxWidth = '300px';
                brandTextAnim.style.opacity = '1';
                brandTextAnim.style.marginLeft = '15px';
                typeBrandText();
            }, 600);

            // Tempo total da animação (0.8s do D + 1s do texto + tempo de digitação + extra)
            // Aproximadamente 2.5s + tempo de digitação
            setTimeout(() => {
                preloader.style.opacity = '0';
                preloader.style.visibility = 'hidden';
                setTimeout(() => {
                    preloader.remove();
                }, 800);
            }, 2500 + brandText.length * 90); 
        });

        // 2. Inicializar AOS (Animações)
        AOS.init({
            once: true,
            offset: 60,
            duration: 1000,
            easing: 'ease-out-cubic'
        });

        // 3. Menu Mobile Toggle
        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('active');
        }

        // 4. Efeito Digitação
        const textElement = document.getElementById('typing-text');
        const phrases = ["cada serviço.", "sua barbearia.", "seu salão.", "sua estética."];
        let phraseIndex = 0; let charIndex = 0; let isDeleting = false;
        
        function typeWriter() {
            const currentPhrase = phrases[phraseIndex];
            if (isDeleting) { textElement.textContent = currentPhrase.substring(0, charIndex - 1); charIndex--; } 
            else { textElement.textContent = currentPhrase.substring(0, charIndex + 1); charIndex++; }
            
            let typeSpeed = isDeleting ? 40 : 90;
            if (!isDeleting && charIndex === currentPhrase.length) { isDeleting = true; typeSpeed = 2000; } 
            else if (isDeleting && charIndex === 0) { isDeleting = false; phraseIndex = (phraseIndex + 1) % phrases.length; typeSpeed = 500; }
            setTimeout(typeWriter, typeSpeed);
        }
        document.addEventListener('DOMContentLoaded', typeWriter);

        // 5. Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.navbar');
            nav.classList.toggle('scrolled', window.scrollY > 50);
        });

        // 6. 3D Tilt Effect no Mockup e Cards (Vanilla JS leve)
        document.querySelectorAll('.mockup-wrapper, .receipt-card').forEach(el => {
            el.addEventListener('mousemove', (e) => {
                const rect = el.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                el.style.transform = `perspective(1000px) rotateY(${x * 0.02}deg) rotateX(${y * -0.02}deg)`;
            });
            el.addEventListener('mouseleave', () => {
                el.style.transform = 'perspective(1000px) rotateY(0) rotateX(0)';
            });
        });
    </script>
</body>
</html>