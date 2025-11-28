<?php
// painel-admin.php
session_start();

// =========================================================
// 1. LÓGICA DE LOGIN E SEGURANÇA
// =========================================================

// Login fixo do admin (Conforme solicitado)
$adminUser = 'Admin';
$adminPass = 'Edu@06051992';

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$painelAdminUrl = $isProd
    ? '/painel-admin' // em produção usa rota amigável
    : '/karen_site/controle-salao/painel-admin.php';

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: {$painelAdminUrl}");
    exit;
}

// Verifica Login
if (!isset($_SESSION['admin_logged_in'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_user'], $_POST['admin_pass'])) {
        $userInput = trim($_POST['admin_user']);
        $passInput = $_POST['admin_pass'];

        if ($userInput === $adminUser && $passInput === $adminPass) {
            $_SESSION['admin_logged_in'] = true;
            header("Location: {$painelAdminUrl}");
            exit;
        } else {
            $error = 'Usuário ou senha incorretos.';
        }
    }
    // RENDERIZA TELA DE LOGIN (DESIGN CLEAN)
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Administração | Login</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-color: #4f46e5;
                --primary-hover: #4338ca;
                --bg-soft: #f3f4f6;
            }
            * {
                box-sizing: border-box;
            }
            body {
                background: radial-gradient(circle at top, #e5e7eb 0, #f3f4f6 40%, #e5e7eb 100%);
                font-family: 'Inter', sans-serif;
                min-height: 100vh;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-wrapper {
                width: 100%;
                max-width: 420px;
                padding: 1rem;
                animation: fadeIn 0.4s ease-out;
            }
            .login-card {
                background: #ffffff;
                border-radius: 18px;
                border: none;
                box-shadow:
                    0 18px 40px rgba(15, 23, 42, 0.12),
                    0 2px 4px rgba(15, 23, 42, 0.04);
                padding: 2.5rem 2.25rem 2rem;
                position: relative;
                overflow: hidden;
            }
            .login-card::before {
                content: "";
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at top right, rgba(79,70,229,0.05), transparent 55%);
                pointer-events: none;
            }
            .brand-logo {
                width: 52px;
                height: 52px;
                background: radial-gradient(circle at 0 0, #a5b4fc, #4f46e5);
                color: white;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 1.3rem;
                margin-bottom: 1rem;
                box-shadow: 0 10px 20px rgba(79,70,229,0.4);
                transform: translateY(0);
                animation: floatLogo 3s ease-in-out infinite;
            }
            .title {
                font-size: 1.5rem;
                font-weight: 700;
                color: #111827;
            }
            .subtitle {
                color: #6b7280;
                font-size: 0.9rem;
                margin-bottom: 1.8rem;
            }
            .form-label {
                font-weight: 500;
                font-size: 0.85rem;
                color: #374151;
                margin-bottom: 0.25rem;
            }
            .form-control {
                padding: 0.7rem 0.85rem;
                border-radius: 9px;
                border: 1px solid #e5e7eb;
                background-color: #f9fafb;
                font-size: 0.92rem;
                transition: all 0.18s ease;
            }
            .form-control:focus {
                border-color: var(--primary-color);
                background-color: #ffffff;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
            }
            .btn-primary {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
                padding: 0.75rem;
                font-weight: 600;
                width: 100%;
                border-radius: 999px;
                font-size: 0.95rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.4rem;
                box-shadow: 0 10px 20px rgba(79,70,229,0.35);
                transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
            }
            .btn-primary:hover {
                background-color: var(--primary-hover);
                border-color: var(--primary-hover);
                transform: translateY(-1px);
                box-shadow: 0 12px 24px rgba(79,70,229,0.45);
            }
            .footer-text {
                font-size: 0.78rem;
                color: #9ca3af;
                margin-top: 1.25rem;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @keyframes floatLogo {
                0%, 100% { transform: translateY(0); }
                50%      { transform: translateY(-4px); }
            }
        </style>
    </head>
    <body>
        <div class="login-wrapper">
            <div class="login-card">
                <div class="d-flex justify-content-center">
                    <div class="brand-logo">AD</div>
                </div>
                <h1 class="title text-center">Painel Administrativo</h1>
                <p class="subtitle text-center">Acesse para gerenciar os profissionais do sistema.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger text-center py-2 mb-4" style="font-size:0.9rem;border-radius:10px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Usuário</label>
                        <input type="text" name="admin_user" class="form-control" placeholder="Digite seu usuário" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Senha</label>
                        <input type="password" name="admin_pass" class="form-control" placeholder="Digite sua senha" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span>Entrar no sistema</span>
                    </button>
                </form>
                <div class="text-center footer-text">
                    <small>Sistema seguro · Painel de controle do salão</small>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// =========================================================
// 2. CONEXÃO E OPERAÇÕES DO BANCO DE DADOS
// =========================================================

require_once __DIR__ . '/includes/db.php';

// Migrações automáticas (garante que as colunas existam)
try {
    $colunas = $pdo->query("PRAGMA table_info(usuarios)")->fetchAll(PDO::FETCH_ASSOC);
    $colsExistentes = array_column($colunas, 'name');

    if (!in_array('ultimo_login', $colsExistentes)) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ultimo_login DATETIME");
    }
    if (!in_array('ativo', $colsExistentes)) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ativo INTEGER DEFAULT 1");
    }
} catch (Exception $e) {
    // Em produção loga o erro
}

// Ações GET (Ativar, Inativar, Excluir)
if (isset($_GET['acao'], $_GET['id'])) {
    $id = (int)$_GET['id'];

    // Proteção: não excluir o ID 1 (Admin padrão do sistema se existir)
    if ($id === 1 && $_GET['acao'] === 'excluir') {
        header("Location: {$painelAdminUrl}?msg=error_admin");
        exit;
    }

    if ($_GET['acao'] === 'excluir') {
        $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmtDel->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=deleted");
        exit;
    }

    if ($_GET['acao'] === 'ativar') {
        $stmtOn = $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
        $stmtOn->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=activated");
        exit;
    }

    if ($_GET['acao'] === 'inativar') {
        $stmtOff = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
        $stmtOff->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=deactivated");
        exit;
    }
}

// =========================================================
// 3. CRIAÇÃO DE USUÁRIO (POST ADMIN)
// =========================================================

$feedback = '';
$feedbackType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['admin_action'])
    && $_POST['admin_action'] === 'create_user'
) {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel   = trim($_POST['telefone'] ?? '');
    $cid   = trim($_POST['cidade'] ?? '');
    $uf    = trim($_POST['estado'] ?? '');
    $estab = trim($_POST['estabelecimento'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome && $email && $senha) {
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetchColumn() > 0) {
                $feedback = 'Este e-mail já está cadastrado.';
                $feedbackType = 'danger';
            } else {
                $sql = "INSERT INTO usuarios (nome, email, telefone, cidade, estado, estabelecimento, senha, ativo)
                        VALUES (?,?,?,?,?,?,?,1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $email, $tel, $cid, $uf, $estab, $hash]);

                header("Location: {$painelAdminUrl}?msg=created");
                exit;
            }
        } catch (Exception $e) {
            $feedback = 'Erro técnico: ' . $e->getMessage();
            $feedbackType = 'danger';
        }
    } else {
        $feedback = 'Preencha os campos obrigatórios (*).';
        $feedbackType = 'warning';
    }
}

// =========================================================
// 4. BUSCA DE USUÁRIOS + ESTATÍSTICAS
// =========================================================

$usuarios = [];
$searchTerm = trim($_GET['search'] ?? '');

if ($searchTerm !== '') {
    $sqlUsers = "SELECT * FROM usuarios
                 WHERE nome LIKE :term
                    OR email LIKE :term
                    OR estabelecimento LIKE :term
                 ORDER BY id DESC";
    $stmtUsers = $pdo->prepare($sqlUsers);
    $stmtUsers->execute([':term' => "%{$searchTerm}%"]);
    $usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} else {
    $usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$totalUsers    = count($usuarios);
$activeUsers   = 0;
$inactiveUsers = 0;

foreach ($usuarios as $u) {
    if (!empty($u['ativo'])) {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Administração | Profissionais</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bs-body-bg: #f3f4f6;
            --bs-body-color: #1f2937;
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            padding-bottom: 2rem;
            background: radial-gradient(circle at top, #e5e7eb 0, #f3f4f6 45%, #e5e7eb 100%);
        }

        /* Navbar */
        .navbar {
            background-color: #ffffffcc;
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
            padding: 0.8rem 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .navbar-brand {
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
        }
        .logo-box {
            width: 36px;
            height: 36px;
            background: radial-gradient(circle at 0 0, #a5b4fc, #4f46e5);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            box-shadow: 0 6px 12px rgba(79,70,229,0.5);
        }
        .navbar-subtitle {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: -2px;
        }

        .main-container {
            max-width: 1200px;
            margin: 1.8rem auto;
            padding: 0 1rem;
            animation: pageFadeIn 0.35s ease-out;
        }

        /* Cards */
        .card-custom {
            background: #ffffff;
            border: none;
            border-radius: 14px;
            box-shadow:
                0 6px 16px rgba(15, 23, 42, 0.08),
                0 1px 2px rgba(15, 23, 42, 0.03);
            overflow: hidden;
            height: 100%;
            transition: transform 0.16s ease, box-shadow 0.16s ease;
        }
        .card-custom:hover {
            transform: translateY(-2px);
            box-shadow:
                0 10px 22px rgba(15, 23, 42, 0.12),
                0 2px 4px rgba(15, 23, 42, 0.04);
        }
        .card-header-custom {
            padding: 1.4rem 1.4rem 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .card-header-custom h5 {
            font-weight: 700;
            margin: 0;
            color: #111827;
            font-size: 1rem;
        }
        .card-header-custom p {
            margin: 4px 0 0 0;
            color: #6b7280;
            font-size: 0.85rem;
        }
        .card-body-custom {
            padding: 1.4rem;
        }

        /* Mini cards estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 0.9rem;
        }
        .stat-card {
            border-radius: 12px;
            padding: 0.85rem 0.9rem;
            background: linear-gradient(135deg, #eef2ff, #e5e7eb);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(79,70,229,0.1), transparent 55%);
            opacity: 0.9;
        }
        .stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 0.2rem;
            position: relative;
            z-index: 1;
        }
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #111827;
            position: relative;
            z-index: 1;
        }
        .stat-icon {
            position: absolute;
            bottom: 4px;
            right: 8px;
            font-size: 1.3rem;
            color: rgba(79,70,229,0.25);
            z-index: 0;
        }

        /* Form / Inputs */
        .form-label {
            font-weight: 500;
            font-size: 0.83rem;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .form-control {
            border: 1px solid #d1d5db;
            padding: 0.6rem 0.8rem;
            border-radius: 9px;
            font-size: 0.9rem;
            transition: all 0.15s ease;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            border: none;
            padding: 0.6rem 1.1rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.18s ease;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            box-shadow: 0 8px 18px rgba(79,70,229,0.35);
            white-space: nowrap;
        }
        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            color: white;
            box-shadow: 0 10px 22px rgba(79,70,229,0.45);
        }

        .btn-outline-secondary {
            border-radius: 999px;
        }

        /* Table */
        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }
        .table thead th {
            background-color: #f9fafb;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.9rem 1rem;
        }
        .table tbody td {
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        .table tbody tr {
            transition: background-color 0.12s ease;
        }
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            background-color: #e0e7ff;
            color: var(--primary-color);
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 10px;
        }
        .user-name {
            font-weight: 600;
            color: #111827;
            display: block;
            font-size: 0.92rem;
        }
        .user-email {
            font-size: 0.8rem;
            color: #6b7280;
            word-break: break-all;
        }

        /* Badges */
        .badge-status {
            padding: 0.35em 0.8em;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        /* Ações */
        .btn-action {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background 0.16s ease, transform 0.1s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }
        .btn-action.delete {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn-action.delete:hover {
            background: #fecaca;
        }
        .btn-action.lock {
            background: #fef3c7;
            color: #92400e;
        }
        .btn-action.lock:hover {
            background: #fde68a;
        }
        .btn-action.unlock {
            background: #dcfce7;
            color: #166534;
        }
        .btn-action.unlock:hover {
            background: #bbf7d0;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .custom-toast {
            background: white;
            padding: 1rem 1.2rem;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.15);
            border-left: 4px solid #10b981;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .badge-total {
            background-color: #eff6ff;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #1d4ed8;
            padding: 0.3rem 0.7rem;
            border: 1px solid #dbeafe;
        }

        .search-input {
            max-width: 260px;
        }

        /* Responsivo */
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0);   opacity: 1; }
        }

        @media (max-width: 991.98px) {
            .main-container {
                margin-top: 1.5rem;
            }
            .card-header-custom {
                padding-bottom: 0.75rem;
            }
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0,1fr));
            }
        }
        @media (max-width: 767.98px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0,1fr));
            }
            .search-input {
                max-width: 100%;
            }
        }
        @media (max-width: 575.98px) {
            .navbar-brand span {
                display: none;
            }
            .navbar-subtitle {
                display: none;
            }
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0,1fr));
            }
            .table thead {
                display: none;
            }
            .table tbody tr {
                display: block;
                margin-bottom: 0.9rem;
                border-radius: 12px;
                box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
                background-color: #ffffff;
            }
            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.45rem 0.9rem;
                border-bottom: 1px solid #f3f4f6;
            }
            .table tbody td:last-child {
                border-bottom: 0;
            }
            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.78rem;
                color: #6b7280;
                margin-right: 0.5rem;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar mb-4">
        <div class="container main-container my-0 d-flex justify-content-between align-items-center">
            <div>
                <a class="navbar-brand" href="#">
                    <div class="logo-box">AD</div>
                    <div>
                        <span>Painel Admin</span>
                        <div class="navbar-subtitle">Gestão de profissionais do sistema</div>
                    </div>
                </a>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <span style="display:block; font-size:0.85rem; font-weight:600;">Administrador</span>
                    <span style="display:block; font-size:0.75rem; color:#6b7280;">Logado</span>
                </div>
                <a href="?logout=1" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-right me-1"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        
        <?php if (!empty($feedback)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($feedbackType); ?> alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-radius:10px;">
                <?php echo htmlspecialchars($feedback); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- CARD RESUMO / ESTATÍSTICAS -->
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5>Resumo do painel</h5>
                        <p>Visão rápida dos profissionais cadastrados.</p>
                    </div>
                    <div class="card-body-custom">
                        <div class="stats-grid mb-3">
                            <div class="stat-card">
                                <div class="stat-label">Total</div>
                                <div class="stat-value"><?php echo $totalUsers; ?></div>
                                <i class="bi bi-people-fill stat-icon"></i>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Ativos</div>
                                <div class="stat-value"><?php echo $activeUsers; ?></div>
                                <i class="bi bi-shield-check stat-icon"></i>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Inativos</div>
                                <div class="stat-value"><?php echo $inactiveUsers; ?></div>
                                <i class="bi bi-slash-circle stat-icon"></i>
                            </div>
                        </div>
                        <p class="mb-1" style="font-size:0.82rem;color:#6b7280;">
                            Use o botão abaixo para cadastrar um novo profissional com acesso ao sistema.
                        </p>
                        <button
                            type="button"
                            class="btn-primary-custom mt-2 w-100"
                            data-bs-toggle="modal"
                            data-bs-target="#modalNovoUsuario">
                            <i class="bi bi-person-plus-fill"></i>
                            Novo profissional
                        </button>
                    </div>
                </div>
            </div>

            <!-- CARD LISTA DE PROFISSIONAIS -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div>
                                <h5>Profissionais cadastrados</h5>
                                <p>Gerencie o acesso e o status dos usuários.</p>
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
                                <form class="d-flex" method="get">
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control form-control-sm search-input"
                                        placeholder="Buscar por nome, e-mail ou salão..."
                                        value="<?php echo htmlspecialchars($searchTerm); ?>"
                                    >
                                </form>
                                <span class="badge-total">
                                    <i class="bi bi-people-fill me-1"></i>
                                    Total: <?php echo $totalUsers; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Profissional</th>
                                    <th>Contato</th>
                                    <th>Status</th>
                                    <th>Último acesso</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($totalUsers === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-people display-5 d-block mb-2 opacity-50"></i>
                                            Nenhum usuário encontrado.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <?php 
                                            $nomeExibicao = $u['nome'] ?? 'Usuário';
                                            $iniciais = mb_strtoupper(mb_substr($nomeExibicao, 0, 1, 'UTF-8'));
                                            $isAdm = ($u['id'] == 1);
                                        ?>
                                        <tr>
                                            <td data-label="Profissional">
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar"><?php echo $iniciais; ?></div>
                                                    <div>
                                                        <span class="user-name">
                                                            <?php echo htmlspecialchars($nomeExibicao); ?>
                                                        </span>
                                                        <span class="user-email">
                                                            <?php
                                                                echo !empty($u['estabelecimento'])
                                                                    ? htmlspecialchars($u['estabelecimento'])
                                                                    : 'Sem estabelecimento';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Contato">
                                                <div class="d-flex flex-column">
                                                    <span><?php echo htmlspecialchars($u['email']); ?></span>
                                                    <?php if (!empty($u['telefone'])): ?>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($u['telefone']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td data-label="Status">
                                                <?php if (!empty($u['ativo'])): ?>
                                                    <span class="badge-status status-active">
                                                        <i class="bi bi-check-circle-fill"></i> Ativo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-status status-inactive">
                                                        <i class="bi bi-slash-circle"></i> Inativo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted" style="font-size:0.85rem;" data-label="Último acesso">
                                                <?php
                                                    echo !empty($u['ultimo_login'])
                                                        ? date('d/m/y H:i', strtotime($u['ultimo_login']))
                                                        : 'Nunca acessou';
                                                ?>
                                            </td>
                                            <td class="text-end" data-label="Ações">
                                                <?php if (!$isAdm): ?>
                                                    <?php if (!empty($u['ativo'])): ?>
                                                        <a href="?acao=inativar&id=<?php echo (int)$u['id']; ?>"
                                                           class="btn-action lock"
                                                           title="Bloquear acesso">
                                                            <i class="bi bi-lock"></i>
                                                            <span class="d-none d-sm-inline">Bloquear</span>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?acao=ativar&id=<?php echo (int)$u['id']; ?>"
                                                           class="btn-action unlock"
                                                           title="Liberar acesso">
                                                            <i class="bi bi-unlock"></i>
                                                            <span class="d-none d-sm-inline">Liberar</span>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?acao=excluir&id=<?php echo (int)$u['id']; ?>" 
                                                       class="btn-action delete ms-1" 
                                                       onclick="return confirm('Tem certeza que deseja EXCLUIR este profissional?');"
                                                       title="Excluir usuário">
                                                        <i class="bi bi-trash"></i>
                                                        <span class="d-none d-sm-inline">Excluir</span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary opacity-75">
                                                        Admin do sistema
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cadastro de Novo Usuário -->
    <div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-labelledby="modalNovoUsuarioLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="admin_action" value="create_user">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalNovoUsuarioLabel">
                            <i class="bi bi-person-plus-fill me-2"></i>
                            Cadastrar novo profissional
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome completo *</label>
                            <input type="text" name="nome" class="form-control" placeholder="Ex: Maria Silva" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-mail (login) *</label>
                            <input type="email" name="email" class="form-control" placeholder="email@exemplo.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha inicial *</label>
                            <input type="password" name="senha" class="form-control" placeholder="Defina a senha de acesso" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">WhatsApp</label>
                                <input type="text" name="telefone" class="form-control" placeholder="(11) 99999-9999">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Cidade/UF</label>
                                <input type="text" name="cidade" class="form-control" placeholder="Tatuí - SP">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Nome do estabelecimento</label>
                            <input type="text" name="estabelecimento" class="form-control" placeholder="Ex: Studio Bela">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus-fill"></i>
                            Cadastrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="toast-container">
        <div id="liveToast" class="custom-toast">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>
                <strong class="d-block text-dark">Sucesso!</strong>
                <small class="text-muted" id="toastMsg">Operação realizada.</small>
            </div>
        </div>
    </div>
    <script>
        const msgs = {
            'created': 'Novo profissional cadastrado!',
            'activated': 'Acesso do profissional liberado.',
            'deactivated': 'Acesso do profissional bloqueado.',
            'deleted': 'Profissional excluído do sistema.',
            'error_admin': 'O usuário administrador padrão não pode ser excluído.'
        };
        const params = new URLSearchParams(window.location.search);
        const msgCode = params.get('msg');
        
        if (msgs[msgCode]) {
            document.getElementById('toastMsg').innerText = msgs[msgCode];
            setTimeout(() => {
                const toast = document.querySelector('.custom-toast');
                if (toast) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        const container = document.querySelector('.toast-container');
                        if (container) container.remove();
                    }, 400);
                }
            }, 4000);
        }
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
