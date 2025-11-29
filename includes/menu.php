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

        <!-- Sino -->
        <button class="icon-btn" type="button" aria-label="Notificações">
            <i class="bi bi-bell"></i>
        </button>

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
