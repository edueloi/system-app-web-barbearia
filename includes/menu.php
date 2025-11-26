<?php
// 1. Iniciar Sessão e Proteção de Login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'cadastro.php' && basename($_SERVER['PHP_SELF']) !== 'recuperar_senha.php') {
    header('Location: /karen_site/controle-salao/login.php');
    exit;
}

$baseUrl = ""; // Ajusta se estiveres numa subpasta

// Verifica o utilizador
$userName = isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'Visitante';
$userRole = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : 'Profissional';
$userEmail = isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : 'usuario@salao.com'; // Exemplo

// 2. Função Active
function isActive($pageName) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $pageName ? 'active' : '';
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    /* --- VARIÁVEIS DE COR --- */
    :root {
        --app-primary: #6366f1;
        --app-dark: #0f172a;
        --app-bg: #f3f4f6;
        --app-text: #334155;
        --app-hover: rgba(255, 255, 255, 0.1);
        --sidebar-width: 280px;
    }

    /* Reset básico */
    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background-color: var(--app-bg);
        padding-top: 70px;
    }

    /* --- NAVBAR --- */
    .app-navbar {
        background-color: white;
        height: 60px;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        z-index: 1000;
        box-sizing: border-box;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .menu-btn {
        background: none;
        border: none;
        font-size: 1.6rem;
        color: var(--app-text);
        cursor: pointer;
        padding: 5px;
        border-radius: 8px;
        transition: 0.2s;
    }
    .menu-btn:active { background-color: #e2e8f0; }

    .brand-logo {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--app-dark);
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .brand-icon {
        background-color: var(--app-primary);
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* Avatar e Dropdown Wrapper */
    .user-menu-wrap {
        position: relative; /* Importante para o dropdown ficar alinhado aqui */
    }

    .user-avatar-sm {
        width: 38px;
        height: 38px;
        background-color: var(--app-dark);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        transition: transform 0.2s;
        border: 2px solid transparent;
    }
    
    .user-avatar-sm:hover {
        transform: scale(1.05);
        border-color: var(--app-primary);
    }

    /* ESTILO DO DROPDOWN (Novo) */
    .user-dropdown {
        position: absolute;
        top: 50px;
        right: 0;
        width: 240px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        display: none; /* Escondido por padrão */
        flex-direction: column;
        z-index: 2000;
        overflow: hidden;
        animation: fadeIn 0.2s ease;
    }

    .user-dropdown.active {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-header {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        background: #f8fafc;
    }
    .dropdown-header h5 { margin: 0; font-size: 0.95rem; color: #0f172a; }
    .dropdown-header span { font-size: 0.8rem; color: #64748b; display: block; margin-top: 2px; }

    .dropdown-item {
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #334155;
        text-decoration: none;
        font-size: 0.9rem;
        transition: background 0.2s;
    }
    .dropdown-item:hover { background-color: #f1f5f9; color: var(--app-primary); }
    .dropdown-item i { font-size: 1.1rem; }
    
    .dropdown-divider { height: 1px; background: #e2e8f0; margin: 4px 0; }
    
    .text-danger { color: #ef4444 !important; }
    .text-danger:hover { background-color: #fef2f2 !important; }


    /* --- SIDEBAR --- */
    .app-sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        background-color: var(--app-dark);
        position: fixed;
        top: 0;
        left: -100%;
        z-index: 1002;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        box-shadow: 10px 0 25px rgba(0,0,0,0.3);
    }
    .app-sidebar.open { left: 0; }

    .sidebar-header {
        padding: 30px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .profile-pic {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--app-primary), #4f46e5);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .profile-info h4 { margin: 0; color: white; font-size: 1rem; font-weight: 600; }
    .profile-info span { color: #94a3b8; font-size: 0.8rem; }

    .sidebar-menu { list-style: none; padding: 20px 15px; margin: 0; overflow-y: auto; flex-grow: 1; }
    .menu-label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin: 15px 10px 5px; font-weight: 700; letter-spacing: 0.5px; }
    
    .sidebar-link {
        display: flex; align-items: center; gap: 12px; padding: 12px 15px;
        color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 5px;
        transition: all 0.2s; font-size: 0.95rem;
    }
    .sidebar-link i { font-size: 1.2rem; width: 24px; text-align: center; }
    .sidebar-link:hover { background-color: var(--app-hover); color: white; }
    .sidebar-link.active { background-color: var(--app-primary); color: white; font-weight: 600; }

    .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
    .btn-logout { color: #ef4444; }
    .btn-logout:hover { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
        z-index: 1001; opacity: 0; visibility: hidden; transition: 0.3s;
    }
    .backdrop.active { opacity: 1; visibility: visible; }
    .close-sidebar-btn { position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
</style>

<nav class="app-navbar">
    <div class="navbar-left">
        <button class="menu-btn" id="openMenuBtn">
            <i class="bi bi-list"></i>
        </button>
        <a href="/karen_site/controle-salao/pages/dashboard.php" class="brand-logo">
            <div class="brand-icon"><i class="bi bi-scissors"></i></div>
            <span>Salão<span style="color:var(--app-primary)">Top</span></span>
        </a>
    </div>

    <div class="navbar-right">
        <?php
        // Notificações não lidas
        $notiCount = 0;
        if (isset($pdo) && isset($userId)) {
            $notiCount = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . intval($userId) . " AND is_read = 0")->fetchColumn();
        }
        ?>
        <div style="position:relative;">
            <i class="bi bi-bell" id="notiBell" style="font-size:1.2rem; color:#64748b; cursor:pointer;"></i>
            <?php if($notiCount > 0): ?>
                <span id="notiBadge" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:white;font-size:0.7rem;padding:2px 6px;border-radius:10px;font-weight:700;min-width:18px;text-align:center;z-index:10;">
                    <?php echo $notiCount; ?>
                </span>
            <?php endif; ?>
            <div id="notiDropdown" style="display:none;position:absolute;right:0;top:30px;width:320px;max-width:90vw;background:white;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12);border:1px solid #e2e8f0;z-index:9999;overflow:hidden;">
                <div style="padding:12px 16px;font-weight:600;border-bottom:1px solid #f1f5f9;background:#f8fafc;">Notificações</div>
                <div id="notiList">
                <?php
                if (isset($pdo) && isset($userId)) {
                    $notis = $pdo->query("SELECT * FROM notifications WHERE user_id = " . intval($userId) . " AND is_read = 0 ORDER BY created_at DESC LIMIT 10")->fetchAll();
                    if (count($notis) === 0) {
                        echo '<div style=\'padding:18px;text-align:center;color:#94a3b8;\'>Sem novos alertas.</div>';
                    } else {
                        foreach($notis as $n) {
                            $link = $n['link'] ? $n['link'] : '#';
                            echo "<div class='noti-item' data-id='{$n['id']}' style='padding:14px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;'>";
                            echo "<i class='bi bi-info-circle' style='color:#6366f1;font-size:1.2rem;'></i>";
                            echo "<div style='flex:1;'><div style='font-size:0.97rem;'>{$n['message']}</div><div style='font-size:0.75rem;color:#94a3b8;margin-top:2px;'>".date('d/m H:i', strtotime($n['created_at']))."</div></div>";
                            echo "<button class='noti-mark-read' data-id='{$n['id']}' style='background:#e2e8f0;border:none;border-radius:6px;padding:4px 8px;font-size:0.8rem;cursor:pointer;'>Ok</button>";
                            echo "</div>";
                        }
                    }
                }
                ?>
                </div>
            </div>
        </div>
        <div class="user-menu-wrap">
            <div class="user-avatar-sm" id="userAvatarBtn">
                <i class="bi bi-person"></i>
            </div>
            
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <h5><?php echo htmlspecialchars($userName); ?></h5>
                    <span><?php echo htmlspecialchars($userRole); ?></span>
                </div>
                
                <a href="/karen_site/controle-salao/pages/perfil/perfil.php" class="dropdown-item">
                    <i class="bi bi-person-circle"></i> Meu Perfil
                </a>
                <a href="/karen_site/controle-salao/pages/configuracoes/configuracoes.php" class="dropdown-item">
                    <i class="bi bi-gear"></i> Configurações
                </a>
                
                <div class="dropdown-divider"></div>
                
                <a href="/karen_site/controle-salao/logout.php" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="backdrop" id="backdrop"></div>

<aside class="app-sidebar" id="sidebar">
    <button class="close-sidebar-btn" id="closeMenuBtn">&times;</button>
    <div class="sidebar-header">
        <div class="profile-pic"><i class="bi bi-person-fill"></i></div>
        <div class="profile-info">
            <h4><?php echo htmlspecialchars($userName); ?></h4>
            <span><?php echo htmlspecialchars($userRole); ?></span>
        </div>
    </div>
    <ul class="sidebar-menu">
        <div class="menu-label">Principal</div>
        <li><a href="/karen_site/controle-salao/pages/dashboard.php" class="sidebar-link <?php echo isActive('index.php'); ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li><a href="/karen_site/controle-salao/pages/agenda/agenda.php" class="sidebar-link <?php echo isActive('agenda.php'); ?>"><i class="bi bi-calendar-check-fill"></i> Agenda</a></li>
        <li><a href="/karen_site/controle-salao/pages/horarios/horarios.php" class="sidebar-link <?php echo isActive('horarios.php'); ?>"><i class="bi bi-clock-fill"></i> Horários</a></li>
        <li><a href="/karen_site/controle-salao/pages/clientes/clientes.php" class="sidebar-link <?php echo isActive('clientes.php'); ?>"><i class="bi bi-people-fill"></i> Clientes</a></li>

        <div class="menu-label">Gestão</div>
        <li><a href="/karen_site/controle-salao/pages/servicos/servicos.php" class="sidebar-link <?php echo isActive('servicos.php'); ?>"><i class="bi bi-scissors"></i> Serviços</a></li>
        <li><a href="/karen_site/controle-salao/pages/produtos-estoque/produtos-estoque.php" class="sidebar-link <?php echo isActive('produtos-estoque.php'); ?>"><i class="bi bi-box-seam-fill"></i> Produtos & Estoque</a></li>
        <!-- Produtos & Estoque e Relatórios ainda não possuem arquivos -->
        <!-- <li><a href="/karen_site/controle-salao/pages/produtos-estoque/produtos-estoque.php" class="sidebar-link <?php echo isActive('produtos-estoque.php'); ?>"><i class="bi bi-box-seam-fill"></i> Produtos & Estoque</a></li> -->
        <!-- <li><a href="/karen_site/controle-salao/pages/relatorio/relatorio.php" class="sidebar-link <?php echo isActive('relatorio.php'); ?>"><i class="bi bi-graph-up-arrow"></i> Relatórios</a></li> -->
    </ul>
    <div class="sidebar-footer">
        <a href="/karen_site/controle-salao/logout.php" class="sidebar-link btn-logout"><i class="bi bi-box-arrow-right"></i> Sair do Sistema</a>
    </div>
</aside>

<script>
// Dropdown de notificações
const notiBell = document.getElementById('notiBell');
const notiDropdown = document.getElementById('notiDropdown');
if(notiBell && notiDropdown) {
    notiBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notiDropdown.style.display = notiDropdown.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', function(e) {
        if(notiDropdown.style.display === 'block' && !notiDropdown.contains(e.target) && e.target !== notiBell) {
            notiDropdown.style.display = 'none';
        }
    });
}
// Marcar notificação como lida (AJAX)
document.addEventListener('click', function(e) {
    if(e.target.classList.contains('noti-mark-read')) {
        const id = e.target.getAttribute('data-id');
        fetch('/karen_site/controle-salao/pages/notificacao_ler.php?id='+id)
            .then(() => { e.target.closest('.noti-item').remove(); });
        // Atualiza badge
        const badge = document.getElementById('notiBadge');
        if(badge) {
            let n = parseInt(badge.textContent)-1;
            if(n <= 0) badge.remove();
            else badge.textContent = n;
        }
    }
});
    /* Lógica da Sidebar */
    const openBtn = document.getElementById('openMenuBtn');
    const closeBtn = document.getElementById('closeMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');

    function toggleSidebar() {
        const isOpen = sidebar.classList.contains('open');
        if (isOpen) {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
        } else {
            sidebar.classList.add('open');
            backdrop.classList.add('active');
        }
    }

    if(openBtn) openBtn.addEventListener('click', toggleSidebar);
    if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if(backdrop) backdrop.addEventListener('click', toggleSidebar);

    // Fechar ao clicar em links no mobile
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', () => {
             if (window.innerWidth < 1024) toggleSidebar();
        });
    });

    /* Lógica do Dropdown de Usuário (NOVO) */
    const userBtn = document.getElementById('userAvatarBtn');
    const userDropdown = document.getElementById('userDropdown');

    // Toggle ao clicar no avatar
    if(userBtn) {
        userBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Impede que o clique feche imediatamente
            userDropdown.classList.toggle('active');
        });
    }

    // Fechar ao clicar fora
    document.addEventListener('click', (e) => {
        if (userDropdown && userDropdown.classList.contains('active')) {
            if (!userDropdown.contains(e.target) && !userBtn.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        }
    });
</script>