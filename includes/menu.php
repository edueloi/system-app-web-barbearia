<?php
// =========================================================
// 1. CONFIGURAÇÃO E DADOS DO USUÁRIO
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirecionamento de segurança (se não estiver logado)
$pagsPublicas = ['login.php', 'cadastro.php', 'recuperar_senha.php'];
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), $pagsPublicas)) {
    header('Location: /karen_site/controle-salao/login.php');
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
$userName = 'Visitante';
$userRole = 'Profissional';
$iniciais = 'V';
$firstName = 'Visitante'; // Variável para o primeiro nome

if (isset($pdo) && isset($_SESSION['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1");
    $stmtUser->execute([$_SESSION['user_id']]);
    $dadoUsuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($dadoUsuario && !empty($dadoUsuario['nome'])) {
        $userName = $dadoUsuario['nome'];
        
        // --- LÓGICA PARA O PRIMEIRO NOME ---
        $partesNome = explode(' ', trim($userName));
        $firstName = $partesNome[0]; // Pega o primeiro nome

        // --- LÓGICA PARA AS INICIAIS (Primeiro + Último) ---
        $primeiraLetra = mb_substr($partesNome[0], 0, 1);
        $ultimaLetra = '';
        
        if (count($partesNome) > 1) {
            $ultimoNome = end($partesNome);
            $ultimaLetra = mb_substr($ultimoNome, 0, 1);
        }
        
        $iniciais = strtoupper($primeiraLetra . $ultimaLetra);
    }
}

function isActive($pageName) {
    return basename($_SERVER['PHP_SELF']) === $pageName ? 'active' : '';
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #6366f1;       /* Indigo Principal */
        --primary-dark: #4f46e5;
        --sidebar-bg: #0f172a;    /* Fundo Escuro Profundo */
        --sidebar-width: 280px;
        --text-sidebar: #cbd5e1;
        --bg-body: #f3f4f6;
    }

    body {
        margin: 0;
        font-family: 'Plus Jakarta Sans', sans-serif; /* Fonte mais moderna */
        background-color: var(--bg-body);
        padding-top: 70px;
    }

    /* --- NAVBAR (Topo) --- */
    .app-navbar {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px); /* Efeito vidro */
        height: 64px;
        position: fixed; top: 0; left: 0; width: 100%;
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 24px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        z-index: 1000;
        box-sizing: border-box;
        border-bottom: 1px solid #e2e8f0;
    }

    .brand-logo {
        font-weight: 800; font-size: 1.25rem; color: #1e293b;
        display: flex; align-items: center; gap: 10px; text-decoration: none;
        letter-spacing: -0.5px;
    }
    .brand-icon {
        background: linear-gradient(135deg, var(--primary), #818cf8);
        color: white; width: 36px; height: 36px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    
    .menu-btn { background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; }

    /* --- SIDEBAR (Lateral - Otimizada para Mobile) --- */
    .app-sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        background-color: var(--sidebar-bg);
        position: fixed; top: 0; left: -100%;
        z-index: 1002;
        transition: cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
        display: flex; flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.05);
        box-shadow: 20px 0 40px rgba(0,0,0,0.1);
    }
    .app-sidebar.open { left: 0; }

    /* Botão de Fechar Sidebar (Visível no mobile) */
    #closeMenuBtn {
        position: absolute; top: 15px; right: 15px; cursor: pointer; 
        color: white; font-size: 1.8rem; z-index: 1003;
        display: none; /* Escondido por padrão em telas grandes */
    }
    @media (max-width: 768px) {
        #closeMenuBtn { display: block; } /* Mostra no mobile */
    }

    /* Cabeçalho da Sidebar (Perfil) */
    .sidebar-header {
        padding: 30px 24px;
        display: flex; align-items: center; gap: 16px;
        background: linear-gradient(to bottom, rgba(255,255,255,0.03), transparent);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    /* Avatar Bonito com Iniciais */
    .avatar-circle {
        width: 52px; height: 52px;
        background: linear-gradient(135deg, #a855f7, #6366f1); /* Degradê Roxo/Azul */
        border-radius: 14px; /* Quadrado arredondado (Squircle) */
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: 700; font-size: 1.1rem; letter-spacing: 1px;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25); /* Sombra colorida */
        border: 2px solid rgba(255,255,255,0.1);
    }

    .user-info h4 {
        margin: 0; color: #fff; font-size: 1rem; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;
    }
    .user-info span {
        color: #94a3b8; font-size: 0.8rem; display: block; margin-top: 3px;
    }

    /* Menu com Scroll Slim */
    .sidebar-menu {
        list-style: none; padding: 24px 16px; margin: 0; overflow-y: auto; flex-grow: 1;
    }
    .sidebar-menu::-webkit-scrollbar { width: 5px; }
    .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
    .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .sidebar-menu::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    .menu-label {
        font-size: 0.7rem; text-transform: uppercase; color: #64748b;
        margin: 20px 12px 10px; font-weight: 700; letter-spacing: 1px;
    }

    /* Links do Menu */
    .sidebar-link {
        display: flex; align-items: center; gap: 14px; padding: 12px 16px;
        color: var(--text-sidebar); text-decoration: none; border-radius: 12px;
        margin-bottom: 6px; transition: all 0.2s ease; font-size: 0.95rem; font-weight: 500;
        border: 1px solid transparent; /* Para evitar pulo no hover */
    }

    .sidebar-link i {
        font-size: 1.3rem; color: #94a3b8; transition: 0.2s;
    }

    /* Hover */
    .sidebar-link:hover {
        background-color: rgba(255, 255, 255, 0.08);
        color: #fff;
        transform: translateX(4px); /* Desliza levemente */
    }
    .sidebar-link:hover i { color: #fff; }

    /* Ativo */
    .sidebar-link.active {
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35); /* Glow no ativo */
        border: 1px solid rgba(255,255,255,0.1);
        font-weight: 600;
    }
    .sidebar-link.active i { color: #fff; }

    /* Footer */
    .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); }
    .btn-logout { color: #f87171; }
    .btn-logout:hover { background: rgba(239, 68, 68, 0.1); color: #fca5a5; transform: none; }
    
    /* Backdrop */
    .backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
        z-index: 1001; opacity: 0; visibility: hidden; transition: 0.3s;
    }
    .backdrop.active { opacity: 1; visibility: visible; }

    /* User Avatar Navbar */
    .navbar-avatar {
        width: 38px; height: 38px;
        background: #e0e7ff; color: var(--primary);
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s;
    }
    .navbar-avatar:hover { background: var(--primary); color: white; }

    /* Dropdown */
    .user-dropdown {
        position: absolute; top: 50px; right: 0; width: 200px;
        background: white; border-radius: 12px; padding: 6px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
        display: none; flex-direction: column; z-index: 2000;
    }
    .user-dropdown.active { display: flex; animation: slideUp 0.2s ease; }
    .dropdown-item {
        padding: 10px 12px; border-radius: 8px; color: #475569;
        text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
        transition: all 0.2s ease;
    }
    .dropdown-item:hover { background: #f1f5f9; color: var(--primary); }
    .dropdown-divider { height: 1px; background: #e2e8f0; margin: 4px 0; }

    /* Estilo Específico para o Botão "Sair" no Dropdown */
    .dropdown-item.text-danger {
        color: #ef4444;
        font-weight: 600; /* Deixar em negrito */
    }
    .dropdown-item.text-danger:hover {
        background: rgba(239, 68, 68, 0.1); /* Fundo vermelho claro no hover */
        color: #dc2626; /* Vermelho mais escuro no hover */
    }

    @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

</style>

<nav class="app-navbar">
    <div style="display:flex; align-items:center; gap:16px;">
        <button class="menu-btn" id="openMenuBtn"><i class="bi bi-list"></i></button>
        <a href="/karen_site/controle-salao/pages/dashboard.php" class="brand-logo">
            <div class="brand-icon"><i class="bi bi-scissors"></i></div>
            <span>Salão<span style="color:var(--primary)">Top</span></span>
        </a>
    </div>

    <div style="display:flex; align-items:center; gap:20px;">
        <i class="bi bi-bell" style="font-size:1.3rem; color:#94a3b8; cursor:pointer;"></i>
        
        <div style="position:relative;">
            <div class="navbar-avatar" id="userAvatarBtn">
                <?php echo $iniciais; ?>
            </div>

            <div class="user-dropdown" id="userDropdown">
                <div style="padding:10px 12px; font-weight:600; border-bottom:1px solid #f1f5f9; font-size:0.9rem; margin-bottom:4px;">
                    Olá, <?php echo htmlspecialchars($firstName); ?> </div>
                <a href="/karen_site/controle-salao/pages/perfil/perfil.php" class="dropdown-item"><i class="bi bi-person"></i> Meu Perfil</a>
                <a href="/karen_site/controle-salao/pages/configuracoes/configuracoes.php" class="dropdown-item"><i class="bi bi-gear"></i> Configurações</a>
                <div class="dropdown-divider"></div> <a href="/karen_site/controle-salao/logout.php" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Sair</a>
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
            <h4><?php echo htmlspecialchars($firstName); ?></h4> <span><?php echo htmlspecialchars($userRole); ?></span>
        </div>
    </div>

    <ul class="sidebar-menu">
        <div class="menu-label">Principal</div>
        <li><a href="/karen_site/controle-salao/pages/dashboard.php" class="sidebar-link <?php echo isActive('dashboard.php'); ?>"><i class="bi bi-grid-fill"></i> Dashboard</a></li>
        <li><a href="/karen_site/controle-salao/pages/agenda/agenda.php" class="sidebar-link <?php echo isActive('agenda.php'); ?>"><i class="bi bi-calendar2-week-fill"></i> Agenda</a></li>
        <li><a href="/karen_site/controle-salao/pages/horarios/horarios.php" class="sidebar-link <?php echo isActive('horarios.php'); ?>"><i class="bi bi-clock-fill"></i> Horários</a></li>
        <li><a href="/karen_site/controle-salao/pages/clientes/clientes.php" class="sidebar-link <?php echo isActive('clientes.php'); ?>"><i class="bi bi-people-fill"></i> Clientes</a></li>

        <div class="menu-label">Gestão</div>
        <li><a href="/karen_site/controle-salao/pages/servicos/servicos.php" class="sidebar-link <?php echo isActive('servicos.php'); ?>"><i class="bi bi-scissors"></i> Serviços</a></li>
        <li><a href="/karen_site/controle-salao/pages/produtos-estoque/produtos-estoque.php" class="sidebar-link <?php echo isActive('produtos-estoque.php'); ?>"><i class="bi bi-box-seam-fill"></i> Produtos</a></li>
    </ul>

    <div class="sidebar-footer">
        <a href="/karen_site/controle-salao/logout.php" class="sidebar-link btn-logout">
            <i class="bi bi-box-arrow-right"></i> Sair do Sistema
        </a>
    </div>
</aside>

<script>
    // Controle Sidebar
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    
    function toggleSidebar() {
        const isOpen = sidebar.classList.contains('open');
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('active');
    }

    document.getElementById('openMenuBtn').onclick = toggleSidebar;
    document.getElementById('closeMenuBtn').onclick = toggleSidebar;
    backdrop.onclick = toggleSidebar;

    // Controle Dropdown
    const userBtn = document.getElementById('userAvatarBtn');
    const userDrop = document.getElementById('userDropdown');

    userBtn.onclick = (e) => {
        e.stopPropagation();
        userDrop.classList.toggle('active');
    };

    document.onclick = (e) => {
        if (!userDrop.contains(e.target) && e.target !== userBtn) {
            userDrop.classList.remove('active');
        }
    };
</script>