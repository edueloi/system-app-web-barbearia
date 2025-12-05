<?php
require_once __DIR__ . '/config.php';
// =========================================================
// 1. CONFIGURAÇÃO E DADOS DO USUÁRIO
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Flag pra saber se está em produção (salao.develoi.com)
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

// Redirecionamento de segurança (se não estiver logado)
$pagsPublicas = ['login.php', 'cadastro.php', 'recuperar_senha.php'];
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), $pagsPublicas)) {
    // Se estiver em prod → /login
    // Se estiver local → /karen_site/controle-salao/login.php
    $loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';
    header('Location: ' . $loginUrl);
    exit;
}

// Conexão BD
$dbFile1 = __DIR__ . '/includes/db.php';
$dbFile2 = __DIR__ . '/../../includes/db.php';
$dbFile3 = dirname(__DIR__) . '/includes/db.php'; // raiz do projeto
if (file_exists($dbFile1)) {
    include_once $dbFile1;
} elseif (file_exists($dbFile2)) {
    include_once $dbFile2;
} elseif (file_exists($dbFile3)) {
    include_once $dbFile3;
}

// Valores Padrão
$notificacoesNaoLidas = 0;
$notificacoesLista = [];
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmtNotif = $pdo->prepare("SELECT id, type, message, link, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmtNotif->execute([$_SESSION['user_id']]);
    $notificacoesLista = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    $notificacoesNaoLidas = count($notificacoesLista);
}
$userName  = 'Visitante';
$userRole  = 'Profissional';
$iniciais  = 'V';
$firstName = 'Visitante';

if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1");
    $stmtUser->execute([$_SESSION['user_id']]);
    $dadoUsuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($dadoUsuario && !empty($dadoUsuario['nome'])) {
        $userName = $dadoUsuario['nome'];

        $partesNome = explode(' ', trim($userName));
        $firstName  = $partesNome[0];

        $primeiraLetra = mb_substr($partesNome[0], 0, 1);
        $ultimaLetra   = '';

        if (count($partesNome) > 1) {
            $ultimoNome  = end($partesNome);
            $ultimaLetra = mb_substr($ultimoNome, 0, 1);
        }

        $iniciais = strtoupper($primeiraLetra . $ultimaLetra);
    }
}

function isActive($pageName)
{
    return basename($_SERVER['PHP_SELF']) === $pageName ? 'active' : '';
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --sidebar-bg: #0f172a;
        --sidebar-width: 280px;
        --text-sidebar: #cbd5e1;
        --bg-body: #f3f4f6;
    }

    body {
        margin: 0;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: var(--bg-body);
        padding-top: 70px;
    }

    /* --- NAVBAR (Topo) --- */
    .app-navbar {
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(10px);
        height: 64px;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 18px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        z-index: 1000;
        box-sizing: border-box;
        border-bottom: 1px solid #e2e8f0;
    }

    .brand-logo {
        font-weight: 700;
        font-size: 1.12rem;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        letter-spacing: -0.4px;
    }

    .brand-icon {
        background: linear-gradient(135deg, var(--primary), #818cf8);
        color: white;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        box-shadow: 0 3px 10px rgba(99, 102, 241, 0.28);
    }

    .menu-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
    }

    /* Botões de ícone (sino, fullscreen) */
    .icon-btn {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        transition: 0.2s;
    }

    .icon-btn i {
        font-size: 1.25rem;
        color: #94a3b8;
    }

    .icon-btn:hover {
        background: #e5e7eb;
    }

    .icon-btn:hover i {
        color: #475569;
    }

    /* --- SIDEBAR --- */
    .app-sidebar {
        width: min(var(--sidebar-width), 100vw);
        height: 100vh;
        background-color: var(--sidebar-bg);
        position: fixed;
        top: 0;
        left: -100%;
        z-index: 1002;
        transition: cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        box-shadow: 20px 0 40px rgba(0, 0, 0, 0.14);
    }

    .app-sidebar.open {
        left: 0;
    }

    #closeMenuBtn {
        position: absolute;
        top: 14px;
        right: 14px;
        cursor: pointer;
        color: white;
        font-size: 1.7rem;
        z-index: 1003;
        display: none;
    }

    @media (max-width: 768px) {
        #closeMenuBtn {
            display: block;
        }
    }

    .sidebar-header {
        padding: 30px 24px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        background: linear-gradient(to bottom, rgba(255, 255, 255, 0.03), transparent);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .avatar-circle {
        width: 52px;
        height: 52px;
        background: linear-gradient(135deg, #a855f7, #6366f1);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.05rem;
        letter-spacing: 1px;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25);
        border: 2px solid rgba(255, 255, 255, 0.12);
    }

    .user-info h4 {
        margin: 0;
        color: #e2e8f0;
        font-size: 0.96rem;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
    }

    .user-info span {
        color: #64748b;
        font-size: 0.78rem;
        display: block;
        margin-top: 3px;
        font-weight: 500;
    }

    .sidebar-menu {
        list-style: none;
        padding: 18px 14px 18px;
        margin: 0;
        overflow-y: auto;
        flex-grow: 1;
    }

    .sidebar-menu::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-menu::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 10px;
    }

    .sidebar-menu::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.22);
    }

    .menu-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #64748b;
        margin: 14px 12px 8px;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 14px;
        color: var(--text-sidebar);
        text-decoration: none;
        border-radius: 12px;
        margin-bottom: 4px;
        transition: all 0.18s ease;
        font-size: 0.9rem;
        font-weight: 500;
        border: 1px solid transparent;
    }

    .sidebar-link i {
        font-size: 1.2rem;
        color: #94a3b8;
        transition: 0.18s;
    }

    .sidebar-link:hover {
        background-color: rgba(148, 163, 184, 0.16);
        color: #e5e7eb;
        transform: translateX(2px);
    }

    .sidebar-link:hover i {
        color: #e5e7eb;
    }

    .sidebar-link.active {
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        color: #ffffff;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.12);
        font-weight: 600;
    }

    .sidebar-link.active i {
        color: #ffffff;
    }

    .sidebar-footer {
        padding: 16px 18px 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .btn-logout {
        color: #fca5a5;
    }

    .btn-logout:hover {
        background: rgba(239, 68, 68, 0.08);
        color: #fecaca;
        transform: none;
    }

    .backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1001;
        opacity: 0;
        visibility: hidden;
        transition: 0.3s;
    }

    .backdrop.active {
        opacity: 1;
        visibility: visible;
    }

    .navbar-avatar {
        width: 36px;
        height: 36px;
        background: #e0e7ff;
        color: var(--primary);
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: 0.2s;
    }

    .navbar-avatar:hover {
        background: var(--primary);
        color: white;
    }

    .user-dropdown {
        position: absolute;
        top: 50px;
        right: 0;
        width: 210px;
        background: white;
        border-radius: 12px;
        padding: 6px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
        border: 1px solid #e2e8f0;
        display: none;
        flex-direction: column;
        z-index: 2000;
    }

    .user-dropdown.active {
        display: flex;
        animation: slideUp 0.2s ease;
    }

    /* ==========================
       NOTIFICAÇÕES MODERNAS
       ========================== */
    .notif-dropdown {
        position: absolute;
        top: 50px;
        right: 0;
        width: 420px;
        max-width: 95vw;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
        border: 1px solid #e2e8f0;
        display: none;
        flex-direction: column;
        z-index: 2000;
        overflow: hidden;
    }

    .notif-dropdown.active {
        display: flex;
        animation: slideUpNotif 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .notif-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-bottom: 1px solid #e2e8f0;
    }

    .notif-count {
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 999px;
        min-width: 24px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
    }

    .notif-list {
        max-height: 480px;
        overflow-y: auto;
        padding: 8px;
    }

    .notif-list::-webkit-scrollbar {
        width: 6px;
    }

    .notif-list::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    .notif-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .notif-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .notif-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 24px;
        text-align: center;
        color: #64748b;
    }

    .notif-empty p {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .notif-empty span {
        font-size: 0.9rem;
        color: #94a3b8;
    }

    .notif-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 8px;
        display: flex;
        gap: 12px;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .notif-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #6366f1, #818cf8);
        opacity: 0;
        transition: opacity 0.25s;
    }

    .notif-card:hover {
        border-color: #c7d2fe;
        background: linear-gradient(135deg, #fafbff, #f8fafc);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.12);
    }

    .notif-card:hover::before {
        opacity: 1;
    }

    .notif-card:active {
        transform: scale(0.98);
    }

    .notif-icon {
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, #dbeafe, #ede9fe);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #6366f1;
    }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-message {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.5;
        margin-bottom: 6px;
        word-wrap: break-word;
    }

    .notif-time {
        font-size: 0.8rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .notif-time i {
        font-size: 0.75rem;
    }

    .notif-link {
        color: #6366f1;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 8px;
        transition: color 0.2s;
    }

    .notif-link:hover {
        color: #4f46e5;
        text-decoration: underline;
    }

    .notif-dismiss {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        background: transparent;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        transition: all 0.2s;
        font-size: 0.85rem;
    }

    .notif-dismiss:hover {
        background: #fee2e2;
        color: #ef4444;
        transform: rotate(90deg);
    }

    @keyframes slideUpNotif {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes notifFadeOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    .notif-card.removing {
        animation: notifFadeOut 0.3s forwards;
    }

    /* Ajuste da notificação no mobile / telas menores */
    @media (max-width: 768px) {
        .notif-dropdown {
            position: fixed !important;     /* força ficar preso na tela */
            top: 64px !important;           /* logo abaixo da navbar */
            left: 50% !important;           /* centraliza horizontalmente */
            right: auto !important;         /* remove o right */
            transform: translateX(-50%);    /* centraliza perfeitamente */
            width: calc(100vw - 16px);      /* largura total menos margens */
            max-width: 420px;               /* não fica muito largo em tablets */
            border-radius: 16px;
            z-index: 2000;
        }

        .notif-dropdown.active {
            animation: slideDownMobile 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .notif-card {
            padding: 12px;
        }

        .notif-icon {
            width: 38px;
            height: 38px;
            font-size: 1.1rem;
        }

        .notif-message {
            font-size: 0.9rem;
        }

        .notif-time {
            font-size: 0.75rem;
        }
    }

    @keyframes slideDownMobile {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-15px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }

    .dropdown-item {
        padding: 9px 11px;
        border-radius: 8px;
        color: #475569;
        text-decoration: none;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.18s ease;
    }

    .dropdown-item:hover {
        background: #f1f5f9;
        color: var(--primary);
    }

    .dropdown-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 4px 0;
    }

    .dropdown-item.text-danger {
        color: #ef4444;
        font-weight: 600;
    }

    .dropdown-item.text-danger:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 480px) {
        .brand-logo {
            font-size: 1rem;
        }
    }
</style>

<nav class="app-navbar">
    <div style="display:flex; align-items:center; gap:12px;">
        <button class="menu-btn" id="openMenuBtn" type="button">
            <i class="bi bi-list"></i>
        </button>
        <!-- LOGO: prod = /dashboard | local = /karen_site/controle-salao/pages/dashboard.php -->
        <?php
        // Caminho absoluto para logo, igual favicon
        if ($isProd) {
            $logoUrl = 'https://salao.develoi.com/img/logo-azul.png';
            $dashboardUrl = '/dashboard';
        } else {
            $host = $_SERVER['HTTP_HOST'];
            $logoUrl = "http://{$host}/karen_site/controle-salao/img/logo-azul.png";
            $dashboardUrl = '/karen_site/controle-salao/pages/dashboard.php';
        }
        ?>
        <a href="<?php echo $dashboardUrl; ?>" class="brand-logo">
            <img src="<?php echo $logoUrl; ?>" 
                 alt="Logo Salão Develoi"
                 style="height:38px; width:auto; display:inline-block; vertical-align:middle;">
        </a>
    </div>

    <div style="display:flex; align-items:center; gap:10px;">
        <!-- Botão Tela Cheia -->
        <button class="icon-btn" id="fullscreenBtn" type="button" aria-label="Tela cheia">
            <i class="bi bi-arrows-fullscreen" id="fullscreenIcon"></i>
        </button>

        <!-- Sino de Notificações -->
        <div style="position:relative;">
            <button class="icon-btn" id="notificBtn" type="button" aria-label="Notificações" style="position:relative;">
                <i class="bi bi-bell"></i>
                <?php if ($notificacoesNaoLidas > 0): ?>
                    <span id="notif-badge" style="position:absolute;top:2px;right:2px;background:#ef4444;color:#fff;font-size:0.7rem;padding:2px 6px;border-radius:999px;font-weight:700;min-width:18px;text-align:center;line-height:1;box-shadow:0 2px 6px #fca5a5;z-index:2;">
                        <?php echo $notificacoesNaoLidas; ?>
                    </span>
                <?php endif; ?>
            </button>
            
            <!-- Dropdown Moderno de Notificações -->
            <div id="notifDropdown" class="notif-dropdown">
                <div class="notif-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i class="bi bi-bell-fill" style="color:#6366f1;font-size:1.1rem;"></i>
                        <span style="font-weight:700;font-size:1.05rem;color:#1e293b;">Notificações</span>
                    </div>
                    <span id="notif-count" class="notif-count">
                        <?php echo $notificacoesNaoLidas; ?>
                    </span>
                </div>
                
                <div class="notif-list" id="notifList">
                    <?php if ($notificacoesNaoLidas === 0): ?>
                        <div class="notif-empty">
                            <i class="bi bi-check-circle" style="font-size:2.5rem;color:#10b981;margin-bottom:8px;"></i>
                            <p>Tudo em dia!</p>
                            <span>Nenhuma notificação nova</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notificacoesLista as $notif): ?>
                            <div class="notif-card" data-id="<?php echo $notif['id']; ?>">
                                <div class="notif-icon">
                                    <i class="bi bi-info-circle-fill"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-message">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </div>
                                    <div class="notif-time">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                        <?php if (!empty($notif['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" 
                                               class="notif-link"
                                               onclick="event.stopPropagation();">
                                                <i class="bi bi-box-arrow-up-right"></i> Ver detalhes
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="notif-dismiss" onclick="dismissNotification(<?php echo $notif['id']; ?>, event)">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Avatar + Dropdown -->
        <div style="position:relative;">
            <div class="navbar-avatar" id="userAvatarBtn">
                <?php echo $iniciais; ?>
            </div>

            <div class="user-dropdown" id="userDropdown">
                <div style="padding:10px 12px; font-weight:600; border-bottom:1px solid #f1f5f9; font-size:0.9rem; margin-bottom:4px;">
                    Olá, <?php echo htmlspecialchars($firstName); ?>
                </div>
                <a href="<?php echo $isProd ? '/perfil' : '/karen_site/controle-salao/pages/perfil/perfil.php'; ?>" class="dropdown-item">
                    <i class="bi bi-person"></i> Meu Perfil
                </a>
                <a href="<?php echo $isProd ? '/configuracoes' : '/karen_site/controle-salao/pages/configuracoes/configuracoes.php'; ?>" class="dropdown-item">
                    <i class="bi bi-gear"></i> Configurações
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo $isProd ? '/logout.php' : '/karen_site/controle-salao/logout.php'; ?>" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="backdrop" id="backdrop"></div>

<aside class="app-sidebar" id="sidebar">
    <div id="closeMenuBtn">
        <i class="bi bi-x"></i>
    </div>

    <div class="sidebar-header">
        <div class="avatar-circle">
            <?php echo $iniciais; ?>
        </div>
        <div class="user-info">
            <h4><?php echo htmlspecialchars($firstName); ?></h4>
            <span><?php echo htmlspecialchars($userRole); ?></span>
        </div>
    </div>

    <ul class="sidebar-menu">
        <div class="menu-label">Principal</div>
        <li>
            <a href="<?php echo $isProd ? '/dashboard' : '/karen_site/controle-salao/pages/dashboard.php'; ?>"
               class="sidebar-link <?php echo isActive('dashboard.php'); ?>">
                <i class="bi bi-grid-fill"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="<?php echo $isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php'; ?>"
               class="sidebar-link <?php echo isActive('agenda.php'); ?>">
                <i class="bi bi-calendar2-week-fill"></i> Agendamentos
            </a>
        </li>
        <li>
            <a href="<?php echo $isProd ? '/horarios' : '/karen_site/controle-salao/pages/horarios/horarios.php'; ?>"
               class="sidebar-link <?php echo isActive('horarios.php'); ?>">
                <i class="bi bi-clock-fill"></i> Expediente
            </a>
        </li>

        <li>
            <a href="<?php echo $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php'; ?>"
               class="sidebar-link <?php echo isActive('clientes.php'); ?>">
                <i class="bi bi-people-fill"></i> Clientes
            </a>
        </li>
        <li>
            <a href="<?php echo $isProd ? '/comandas' : '/karen_site/controle-salao/pages/comandas/comandas.php'; ?>"
               class="sidebar-link <?php echo isActive('comandas.php'); ?>">
                <i class="bi bi-clipboard"></i> Comandas
            </a>
        </li>

        <div class="menu-label">Gestão</div>
        <li>
            <a href="<?php echo $isProd ? '/servicos' : '/karen_site/controle-salao/pages/servicos/servicos.php'; ?>"
               class="sidebar-link <?php echo isActive('servicos.php'); ?>">
                <i class="bi bi-scissors"></i> Serviços
            </a>
        </li>
        <li>
            <a href="<?php echo $isProd ? '/produtos-estoque' : '/karen_site/controle-salao/pages/produtos-estoque/produtos-estoque.php'; ?>"
               class="sidebar-link <?php echo isActive('produtos-estoque.php'); ?>">
                <i class="bi bi-box-seam-fill"></i> Produtos
            </a>
        </li>
        <li>
            <a href="<?php echo $isProd ? '/calcular-servico' : '/karen_site/controle-salao/pages/calcular-servico/calcular-servico.php'; ?>"
               class="sidebar-link <?php echo isActive('calcular-servico.php'); ?>">
                <i class="bi bi-calculator"></i> Calcular Serviço
            </a>
        </li>
    </ul>

    <!-- Rodapé do menu removido: botão Sair do Sistema -->
</aside>

<script>
        // ==========================
        // NOTIFICAÇÕES MODERNAS
        // ==========================
        const notifBtn = document.getElementById('notificBtn');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifList = document.getElementById('notifList');
        const notifBadge = document.getElementById('notif-badge');
        const notifCount = document.getElementById('notif-count');

        // Toggle dropdown do sino
        if (notifBtn && notifDropdown) {
            notifBtn.onclick = (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('active');
            };
            
            document.addEventListener('click', (e) => {
                if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
                    notifDropdown.classList.remove('active');
                }
            });
        }

        // Função para dispensar notificação (botão X)
        function dismissNotification(notifId, event) {
            event.stopPropagation();
            const card = document.querySelector(`.notif-card[data-id="${notifId}"]`);
            if (!card) return;

            // Adiciona animação de saída
            card.classList.add('removing');

            // Chama o backend para marcar como lida
            fetch('<?php echo $isProd ? "/pages/notificacao_ler.php" : "/karen_site/controle-salao/pages/notificacao_ler.php"; ?>?id=' + notifId)
                .then(() => {
                    setTimeout(() => {
                        card.remove();
                        updateNotificationCount();
                    }, 300); // Tempo da animação
                })
                .catch(() => {
                    card.classList.remove('removing');
                    alert('Erro ao marcar como lida. Tente novamente.');
                });
        }

        // Clique no card inteiro também marca como lido
        document.querySelectorAll('.notif-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Se clicou no botão dismiss ou no link, não faz nada
                if (e.target.closest('.notif-dismiss') || e.target.closest('.notif-link')) {
                    return;
                }

                const notifId = this.getAttribute('data-id');
                this.classList.add('removing');

                fetch('<?php echo $isProd ? "/pages/notificacao_ler.php" : "/karen_site/controle-salao/pages/notificacao_ler.php"; ?>?id=' + notifId)
                    .then(() => {
                        setTimeout(() => {
                            this.remove();
                            updateNotificationCount();
                        }, 300);
                    })
                    .catch(() => {
                        this.classList.remove('removing');
                        alert('Erro ao marcar como lida. Tente novamente.');
                    });
            });
        });

        // Atualiza contador e badge
        function updateNotificationCount() {
            const remaining = document.querySelectorAll('.notif-card').length;

            // Atualiza contador no header do dropdown
            if (notifCount) {
                notifCount.textContent = remaining;
            }

            // Atualiza ou remove badge do sino
            if (notifBadge) {
                if (remaining === 0) {
                    notifBadge.remove();
                } else {
                    notifBadge.textContent = remaining;
                }
            }

            // Se não tiver mais notificações, mostra estado vazio
            if (remaining === 0 && notifList) {
                notifList.innerHTML = `
                    <div class="notif-empty">
                        <i class="bi bi-check-circle" style="font-size:2.5rem;color:#10b981;margin-bottom:8px;"></i>
                        <p>Tudo em dia!</p>
                        <span>Nenhuma notificação nova</span>
                    </div>
                `;
            }
        }
    // ==========================
    // SIDEBAR
    // ==========================
    const sidebar   = document.getElementById('sidebar');
    const backdrop  = document.getElementById('backdrop');
    const openBtn   = document.getElementById('openMenuBtn');
    const closeBtn  = document.getElementById('closeMenuBtn');

    function toggleSidebar() {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('active');
    }

    if (openBtn)  openBtn.onclick  = toggleSidebar;
    if (closeBtn) closeBtn.onclick = toggleSidebar;
    if (backdrop) backdrop.onclick = toggleSidebar;

    // ==========================
    // DROPDOWN USUÁRIO
    // ==========================
    const userBtn  = document.getElementById('userAvatarBtn');
    const userDrop = document.getElementById('userDropdown');

    if (userBtn && userDrop) {
        userBtn.onclick = (e) => {
            e.stopPropagation();
            userDrop.classList.toggle('active');
        };

        document.addEventListener('click', (e) => {
            if (!userDrop.contains(e.target) && e.target !== userBtn) {
                userDrop.classList.remove('active');
            }
        });
    }

    // ==========================
    // FULLSCREEN (Tela Cheia) + persistência
    // ==========================
    const fullscreenBtn  = document.getElementById('fullscreenBtn');
    const fullscreenIcon = document.getElementById('fullscreenIcon');
    const FS_STORAGE_KEY = 'salao_fullscreen_pref';

    function isFullscreen() {
        return document.fullscreenElement ||
               document.webkitFullscreenElement ||
               document.mozFullScreenElement ||
               document.msFullscreenElement;
    }

    function enterFullscreen() {
        const el = document.documentElement;
        if (el.requestFullscreen) {
            el.requestFullscreen();
        } else if (el.webkitRequestFullscreen) {
            el.webkitRequestFullscreen();
        } else if (el.mozRequestFullScreen) {
            el.mozRequestFullScreen();
        } else if (el.msRequestFullscreen) {
            el.msRequestFullscreen();
        }
    }

    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (el.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }

    function setFsPref(on) {
        try {
            localStorage.setItem(FS_STORAGE_KEY, on ? '1' : '0');
        } catch (e) {}
    }

    function getFsPref() {
        try {
            return localStorage.getItem(FS_STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function updateFullscreenIcon() {
        if (!fullscreenIcon) return;
        if (isFullscreen()) {
            fullscreenIcon.classList.remove('bi-arrows-fullscreen');
            fullscreenIcon.classList.add('bi-fullscreen-exit');
        } else {
            fullscreenIcon.classList.remove('bi-fullscreen-exit');
            fullscreenIcon.classList.add('bi-arrows-fullscreen');
        }
    }

    function toggleFullscreen() {
        if (isFullscreen()) {
            exitFullscreen();
            setFsPref(false);
        } else {
            enterFullscreen();
            setFsPref(true);
        }
    }

    function handleFsChange() {
        updateFullscreenIcon();
        if (!isFullscreen()) {
            // saiu do fullscreen (ESC, gesto do sistema etc.)
            setFsPref(false);
        }
    }

    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }

    document.addEventListener('fullscreenchange', handleFsChange);
    document.addEventListener('webkitfullscreenchange', handleFsChange);
    document.addEventListener('mozfullscreenchange', handleFsChange);
    document.addEventListener('MSFullscreenChange', handleFsChange);

    // Reentra em fullscreen na nova página
    // assim que o usuário fizer o primeiro clique
    document.addEventListener('click', function rearmFullscreenOnce() {
        if (getFsPref() && !isFullscreen()) {
            enterFullscreen();
        }
        document.removeEventListener('click', rearmFullscreenOnce);
    }, { once: true });
</script>
