<?php
// painel-admin.php

// --- CONTROLE DE SESSÃO E INATIVIDADE ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';

// CONFIGURAÇÃO: AUTO LOGOUT POR INATIVIDADE (SOMENTE PARA ADMIN)
$AUTO_LOGOUT         = defined('AUTO_LOGOUT') ? AUTO_LOGOUT : true; // padrão: ativo
$INATIVIDADE_MINUTOS = defined('AUTO_LOGOUT_MINUTES') ? AUTO_LOGOUT_MINUTES : 30;

// Só verifica inatividade se for sessão de ADMIN
if ($AUTO_LOGOUT && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $inatividade = $INATIVIDADE_MINUTOS * 60;
    if (isset($_SESSION['ADMIN_LAST_ACTIVITY']) && (time() - $_SESSION['ADMIN_LAST_ACTIVITY'] > $inatividade)) {
        // Sessão de admin expirada por inatividade
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['ADMIN_LAST_ACTIVITY']);
        $_SESSION['admin_logout_message'] = 'Sessão administrativa expirada por inatividade.';

        $isProdTemp       = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        $painelAdminUrl   = $isProdTemp ? '/painel-admin' : $_SERVER['PHP_SELF'];
        header("Location: {$painelAdminUrl}");
        exit;
    }
    $_SESSION['ADMIN_LAST_ACTIVITY'] = time();
}

// =========================================================
// 1. CONFIGURAÇÕES E LÓGICA DE LOGIN
// =========================================================

$adminUser = 'Admin';
$adminPass = 'Edu@06051992';

// Constantes de negócio
const VALOR_BASE_MENSAL  = 19.90;
const COMISSAO_INDICACAO = 9.90;       // por mês para quem indicou
const PERC_SOCIA_LUCIANA = 0.10;      // 10%
const NOME_SOCIA_LUCIANA = 'Luciana Aparecida';

// Verifica ambiente
$isProd        = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$painelAdminUrl = $isProd ? '/painel-admin' : $_SERVER['PHP_SELF'];
$loginUrl       = $isProd ? '/login' : '/karen_site/controle-salao/login.php';

// Logout (apenas admin, não afeta usuários comuns)
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['ADMIN_LAST_ACTIVITY']);
    header("Location: {$loginUrl}");
    exit;
}

// Verifica Login
if (!isset($_SESSION['admin_logged_in'])) {
    $error = isset($_SESSION['admin_logout_message']) ? $_SESSION['admin_logout_message'] : '';
    unset($_SESSION['admin_logout_message']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_user'], $_POST['admin_pass'])) {
        $userInput = trim($_POST['admin_user']);
        $passInput = $_POST['admin_pass'];

        if ($userInput === $adminUser && $passInput === $adminPass) {
            $_SESSION['admin_logged_in']    = true;
            $_SESSION['ADMIN_LAST_ACTIVITY'] = time();
            header("Location: {$painelAdminUrl}");
            exit;
        } else {
            $error = 'Credenciais inválidas.';
        }
    }

    // Tela de Login Admin
?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login | Develoi Gestão</title>

        <link rel="icon" type="image/svg+xml" href="favicon.svg">
        <link rel="icon" href="favicon.ico" type="image/x-icon">
        <link rel="apple-touch-icon" href="favicon.svg">
        <meta name="theme-color" content="#4f46e5">

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            :root {
                --primary: #818cf8;
                --primary-hover: #6366f1;
                --secondary: #fb7185;
                --accent: #c084fc;
                --bg-deep: #1e1e2e;
                --bg-surface: #2a2a3c;
                --bg-card: rgba(42, 42, 60, 0.6);
                --border-color: rgba(255, 255, 255, 0.1);
                --text-main: #f1f5f9;
                --text-muted: #94a3b8;
                --success: #34d399;
                --warning: #fbbf24;
                --danger: #f87171;
                --sidebar-width: 250px;
                --bottom-nav-height: 70px;
            }

            * { box-sizing: border-box; }

            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background-color: var(--bg-deep);
                background-image: 
                    radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                    radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.1) 0px, transparent 50%);
                color: var(--text-main);
                min-height: 100vh;
                margin: 0;
                overflow-x: hidden;
            }

            h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; font-weight: 700; }

            /* LAYOUT WRAPPER */
            .admin-wrapper {
                display: block;
                padding-left: 0;
                transition: padding 0.3s;
            }

            @media (min-width: 992px) {
                .admin-wrapper { padding-left: var(--sidebar-width); }
            }

            /* SIDEBAR DESKTOP */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: var(--sidebar-width);
                height: 100vh;
                background: var(--bg-surface);
                border-right: 1px solid var(--border-color);
                z-index: 1000;
                padding: 1.5rem;
                display: none;
                flex-direction: column;
            }

            @media (min-width: 992px) { .sidebar { display: flex; } }

            .sidebar-logo {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 2.5rem;
                text-decoration: none;
            }

            .sidebar-logo i {
                font-size: 1.5rem;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }

            .sidebar-logo span {
                font-size: 1.25rem;
                font-weight: 800;
                color: #fff;
                letter-spacing: -0.5px;
            }

            .nav-menu { list-style: none; padding: 0; margin: 0; }
            .nav-item { margin-bottom: 0.5rem; }
            .nav-link {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-radius: 12px;
                color: var(--text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                font-weight: 500;
                transition: all 0.2s;
            }

            .nav-link:hover, .nav-link.active {
                background: rgba(99, 102, 241, 0.1);
                color: var(--primary);
            }

            .nav-link.active { background: var(--primary); color: #fff; }

            /* BOTTOM NAV MOBILE */
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                height: var(--bottom-nav-height);
                background: rgba(30, 41, 59, 0.8);
                backdrop-filter: blur(15px);
                border-top: 1px solid var(--border-color);
                display: flex;
                justify-content: space-around;
                align-items: center;
                z-index: 1000;
                padding: 0 10px;
            }

            @media (min-width: 992px) { .bottom-nav { display: none; } }

            .bnav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                color: var(--text-muted);
                text-decoration: none;
                font-size: 0.65rem;
                font-weight: 600;
                gap: 4px;
            }

            .bnav-item i { font-size: 1.25rem; }
            .bnav-item.active { color: var(--primary); }

            /* CARDS & COMPONENTS */
            .glass-card {
                background: var(--bg-card);
                backdrop-filter: blur(10px);
                border: 1px solid var(--border-color);
                border-radius: 24px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                margin-bottom: 2rem;
                overflow: hidden;
            }

            .stat-card {
                padding: 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                position: relative;
                height: 100%;
            }

            .stat-card i {
                position: absolute;
                top: 1.5rem;
                right: 1.5rem;
                font-size: 1.5rem;
                opacity: 0.2;
            }

            .stat-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
            .stat-value { font-size: 1.75rem; font-weight: 800; color: #fff; }
            .stat-sub { font-size: 0.7rem; color: var(--text-muted); }

            .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
            .table-modern th {
                background: rgba(0, 0, 0, 0.2);
                color: var(--text-muted);
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                padding: 1rem 1.5rem;
                border-bottom: 1px solid var(--border-color);
            }
            .table-modern td { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-main); }
            .table-modern tr:last-child td { border-bottom: none; }
            .table-modern tr:hover { background: rgba(255, 255, 255, 0.02); }

            .badge-status {
                padding: 4px 12px;
                border-radius: 999px;
                font-size: 0.7rem;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .status-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
            .status-inactive { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
            .status-teste { background: rgba(99, 102, 241, 0.1); color: #818cf8; }

            /* MOBILE USER CARDS */
            .user-card {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: 20px;
                padding: 1.25rem;
                margin-bottom: 1rem;
            }

            /* MODALS */
            .modal-content {
                background: var(--bg-surface);
                color: var(--text-main);
                border-radius: 28px;
                border: 1px solid var(--border-color);
                box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            }

            .modal-header { border-bottom: 1px solid var(--border-color); padding: 1.5rem; }
            .modal-footer { border-top: 1px solid var(--border-color); padding: 1.5rem; }

            .form-control, .form-select {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid var(--border-color);
                border-radius: 10px;
                color: #fff;
                padding: 8px 12px;
                font-size: 0.9rem;
            }

            .form-control:focus, .form-select:focus {
                background: rgba(255, 255, 255, 0.08);
                border-color: var(--primary);
                color: #fff;
                box-shadow: none;
            }

            .btn-indigo {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: #fff;
                border: none;
                border-radius: 10px;
                padding: 10px 20px;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s;
            }

            .btn-indigo:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
                color: #fff;
            }

            .avatar-circle {
                width: 38px; height: 38px; border-radius: 999px;
                background: var(--primary); color: #fff;
                display: flex; align-items: center; justify-content: center;
                font-weight: 700; font-size: 0.9rem;
            }

            .btn-action {
                width: 32px; height: 32px; border-radius: 10px;
                display: inline-flex; align-items: center; justify-content: center;
                border: none; transition: all 0.2s;
            }
            .btn-renew { background: rgba(16, 185, 129, 0.1); color: #10b981; }
            .btn-edit { background: rgba(99, 102, 241, 0.1); color: #818cf8; }
            .btn-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

            .btn-action:hover { transform: scale(1.1); }
        </style>
    </head>

    <body>
        <div class="aurora-bg">
            <div class="aurora-blob blob-1"></div>
            <div class="aurora-blob blob-2"></div>
        </div>

        <div class="login-card">
            <div class="brand-logo-container">
                <div class="brand-d">D</div>
                <div class="brand-text">EVELOI ADMIN</div>
                <p class="subtitle">Painel de Gestão Premium</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">
                        <i class="bi bi-person-fill me-1"></i> Usuário
                    </label>
                    <input type="text" name="admin_user" class="form-control" required autofocus placeholder="Digite seu usuário">
                </div>
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-lock-fill me-1"></i> Senha
                    </label>
                    <input type="password" name="admin_pass" class="form-control" required placeholder="Digite sua senha">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Entrar no Painel
                </button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

// =========================================================
// 2. CONEXÃO E LÓGICA DE DADOS
// =========================================================
require_once __DIR__ . '/includes/db.php';

// Helper: formata validade para UI
function formatarValidadeParaAdminUI($u) {
    if (!empty($u['is_vitalicio'])) {
        return ['texto' => 'Vitalício', 'classe' => 'badge-active'];
    }
    if (empty($u['data_expiracao'])) {
        return ['texto' => 'Pendente', 'classe' => 'badge-danger'];
    }
    try {
        $exp  = new DateTime($u['data_expiracao']);
        $now  = new DateTime();
        if ($exp < $now) {
            return ['texto' => 'Expirou ' . $exp->format('d/m/Y'), 'classe' => 'badge-danger'];
        }
        $diff = $now->diff($exp)->days;
        return [
            'texto' => $diff . ' dias (' . $exp->format('d/m/Y') . ')',
            'classe' => ($diff < 7 ? 'badge-warning' : 'badge-active')
        ];
    } catch (Exception $e) {
        return ['texto' => 'Erro data', 'classe' => 'badge-danger'];
    }
}

// Helper: gera codigo unico para vendedor
function gerarCodigoVendedor($pdo) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendedores WHERE codigo = ?");
        $stmt->execute([$codigo]);
        $existe = $stmt->fetchColumn() > 0;
    } while ($existe);

    return $codigo;
}

// --- ACOES GET (Vendedores) ---
if (isset($_GET['acao_vendedor'], $_GET['id'])) {
    $idVend = (int)$_GET['id'];

    if ($_GET['acao_vendedor'] === 'ativar') {
        $pdo->prepare("UPDATE vendedores SET ativo = 1 WHERE id = ?")->execute([$idVend]);
        header("Location: {$painelAdminUrl}?msg=vend_activated");
        exit;
    }
    if ($_GET['acao_vendedor'] === 'inativar') {
        $pdo->prepare("UPDATE vendedores SET ativo = 0 WHERE id = ?")->execute([$idVend]);
        header("Location: {$painelAdminUrl}?msg=vend_deactivated");
        exit;
    }
    if ($_GET['acao_vendedor'] === 'excluir') {
        $pdo->prepare("DELETE FROM vendedores WHERE id = ?")->execute([$idVend]);
        header("Location: {$painelAdminUrl}?msg=vend_deleted");
        exit;
    }
}

// --- BLOQUEIO AUTOMÁTICO POR EXPIRAÇÃO ---
try {
    $hoje     = date('Y-m-d');
    $sqlBlock = "UPDATE usuarios 
                 SET ativo = 0 
                 WHERE is_vitalicio = 0 
                 AND data_expiracao IS NOT NULL 
                 AND data_expiracao < :hoje 
                 AND ativo = 1";
    $stmtBlock = $pdo->prepare($sqlBlock);
    $stmtBlock->execute([':hoje' => $hoje]);
} catch (Exception $e) {
    // silencioso em produção
}

// --- AÇÕES GET (Ativar, Inativar, Excluir) ---
if (isset($_GET['acao'], $_GET['id'])) {
    $id = (int)$_GET['id'];

    // Protege usuário Admin ID 1
    if ($id === 1 && $_GET['acao'] === 'excluir') {
        header("Location: {$painelAdminUrl}?msg=error_admin");
        exit;
    }

    if ($_GET['acao'] === 'excluir') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=deleted");
        exit;
    }
    if ($_GET['acao'] === 'ativar') {
        $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?")->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=activated");
        exit;
    }
    if ($_GET['acao'] === 'inativar') {
        $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?")->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=deactivated");
        exit;
    }
}

// --- AÇÕES POST (CRIAR, RENOVAR, EDITAR) ---

$action = $_POST['action'] ?? '';
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CRIAR NOVO USUÁRIO
    if ($action === 'create') {
        $nome        = trim($_POST['nome'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $senha       = $_POST['senha'] ?? '';
        $plano       = $_POST['plano_inicial'] ?? '30'; // dias ou 'teste_7' ou 'vitalicio'
        $indicadoPor = trim($_POST['indicado_por'] ?? '');
        $valorMensal = isset($_POST['valor_mensal']) && $_POST['valor_mensal'] !== ''
            ? (float)str_replace(',', '.', $_POST['valor_mensal'])
            : VALOR_BASE_MENSAL;
        $isTeste     = isset($_POST['is_teste']) ? 1 : 0;

        if ($nome && $email && $senha) {
            // Verifica e-mail duplicado
            $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetchColumn() > 0) {
                $feedback = 'E-mail já cadastrado!';
            } else {
                $is_vitalicio   = 0;
                $data_expiracao = null;

                // TESTE GRÁTIS 7 DIAS (sem cobrança / sem comissão)
                if ($plano === 'teste_7') {
                    $dias           = 7;
                    $data_expiracao = date('Y-m-d', strtotime("+$dias days"));
                    $indicadoPor    = null; // teste não gera comissão
                    $valorMensal    = 0;

                    // VITALÍCIO (sem expiração)
                } elseif ($plano === 'vitalicio') {
                    $is_vitalicio   = 1;
                    $data_expiracao = null;

                    // PLANOS NORMAIS (30, 60, 90, 180, 365, 730)
                } else {
                    $dias = (int)$plano;
                    if ($dias <= 0) {
                        $dias = 30;
                    }
                    $data_expiracao = date('Y-m-d', strtotime("+$dias days"));
                }

                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql  = "INSERT INTO usuarios 
                         (nome, email, senha, ativo, is_vitalicio, data_expiracao, indicado_por, valor_mensal, is_teste, criado_em)
                         VALUES (?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)";

                $ativoInput     = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;

                $pdo->prepare($sql)->execute([
                    $nome,
                    $email,
                    $hash,
                    $ativoInput, 
                    $is_vitalicio,
                    $data_expiracao,
                    $indicadoPor ?: null,
                    $valorMensal,
                    $isTeste
                ]);

                header("Location: {$painelAdminUrl}?msg=created");
                exit;
            }
        } else {
            $feedback = 'Preencha os campos obrigatórios.';
        }
    }

    // 2. RENOVAR USUÁRIO (PLANO)
    if ($action === 'renew') {
        $idRenovar      = (int)($_POST['user_id_renew'] ?? 0);
        $tipo_renovacao = $_POST['tipo_renovacao'] ?? '';

        if ($idRenovar > 0 && $tipo_renovacao) {
            $novo_vitalicio = 0;
            $nova_data      = null;
            $ativo          = 1;
            $baseDate       = date('Y-m-d');

            if ($tipo_renovacao === 'set_vitalicio') {
                $novo_vitalicio = 1;
                $nova_data      = null; // sem expiração
            } else {
                switch ($tipo_renovacao) {
                    case 'add_30':
                        $nova_data = date('Y-m-d', strtotime($baseDate . ' + 30 days'));
                        break;
                    case 'add_60':
                        $nova_data = date('Y-m-d', strtotime($baseDate . ' + 60 days'));
                        break;
                    case 'add_90':
                        $nova_data = date('Y-m-d', strtotime($baseDate . ' + 90 days'));
                        break;
                    case 'add_365':
                        $nova_data = date('Y-m-d', strtotime($baseDate . ' + 1 year'));
                        break;
                    case 'add_730':
                        $nova_data = date('Y-m-d', strtotime($baseDate . ' + 2 years'));
                        break;
                    default:
                        $nova_data = date('Y-m-d', strtotime($baseDate . ' + 30 days'));
                        break;
                }
            }

            $stmt = $pdo->prepare("UPDATE usuarios SET is_vitalicio = ?, data_expiracao = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$novo_vitalicio, $nova_data, $ativo, $idRenovar]);

            header("Location: {$painelAdminUrl}?msg=renewed");
            exit;
        } else {
            $feedback = 'Falha ao renovar: dados incompletos.';
        }
    }

    // 3. EDITAR USUÁRIO
    if ($action === 'edit') {
        $edit_id      = (int)($_POST['edit_id'] ?? 0);
        $edit_nome    = trim($_POST['edit_nome'] ?? '');
        $edit_email   = trim($_POST['edit_email'] ?? '');
        $edit_is_teste = isset($_POST['edit_is_teste']) ? 1 : 0;

        $edit_ativo    = isset($_POST['edit_ativo']) ? (int)$_POST['edit_ativo'] : 1;

        if ($edit_id > 0 && $edit_nome && $edit_email) {
            $sql = "UPDATE usuarios SET nome = ?, email = ?, is_teste = ?, ativo = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([
                $edit_nome,
                $edit_email,
                $edit_is_teste,
                $edit_ativo,
                $edit_id
            ]);
            header("Location: {$painelAdminUrl}?msg=edited");
            exit;
        } else {
            $feedback = 'Preencha todos os campos obrigatórios.';
        }
    }
    // 4. CRIAR VENDEDOR
    if ($action === 'create_vendedor') {
        $nome     = trim($_POST['vend_nome'] ?? '');
        $email    = trim($_POST['vend_email'] ?? '');
        $telefone = trim($_POST['vend_telefone'] ?? '');
        $cpf      = trim($_POST['vend_cpf'] ?? '');
        $senha    = $_POST['vend_senha'] ?? '';
        $ativo    = isset($_POST['vend_ativo']) ? 1 : 0;

        if ($nome && $email && $senha) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM vendedores WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetchColumn() > 0) {
                $feedback = 'E-mail de vendedor ja cadastrado!';
            } else {
                $codigo = gerarCodigoVendedor($pdo);
                $hash = password_hash($senha, PASSWORD_DEFAULT);

                $sql = "INSERT INTO vendedores (nome, email, telefone, cpf, senha, codigo, ativo, criado_em)
                        VALUES (?,?,?,?,?,?,?, CURRENT_TIMESTAMP)";
                $pdo->prepare($sql)->execute([
                    $nome,
                    $email,
                    $telefone ?: null,
                    $cpf ?: null,
                    $hash,
                    $codigo,
                    $ativo
                ]);

                header("Location: {$painelAdminUrl}?msg=vend_created");
                exit;
            }
        } else {
            $feedback = 'Preencha nome, e-mail e senha do vendedor.';
        }
    }

    // 5. EDITAR VENDEDOR
    if ($action === 'edit_vendedor') {
        $edit_id   = (int)($_POST['edit_vend_id'] ?? 0);
        $nome      = trim($_POST['edit_vend_nome'] ?? '');
        $email     = trim($_POST['edit_vend_email'] ?? '');
        $telefone  = trim($_POST['edit_vend_telefone'] ?? '');
        $cpf       = trim($_POST['edit_vend_cpf'] ?? '');
        $senha     = $_POST['edit_vend_senha'] ?? '';
        $ativo     = isset($_POST['edit_vend_ativo']) ? 1 : 0;

        if ($edit_id > 0 && $nome && $email) {
            $sql = "UPDATE vendedores SET nome = ?, email = ?, telefone = ?, cpf = ?, ativo = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([
                $nome,
                $email,
                $telefone ?: null,
                $cpf ?: null,
                $ativo,
                $edit_id
            ]);

            if (!empty($senha)) {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE vendedores SET senha = ? WHERE id = ?")->execute([$hash, $edit_id]);
            }

            header("Location: {$painelAdminUrl}?msg=vend_edited");
            exit;
        } else {
            $feedback = 'Preencha os campos obrigatorios do vendedor.';
        }
    }

}
// =========================================================
// 3. BUSCA, ESTATÍSTICAS E CÁLCULO FINANCEIRO
// =========================================================

// Filtros da tela
$searchTerm       = trim($_GET['search'] ?? '');
$filtroTipo       = $_GET['tipo'] ?? 'todos';       // todos | teste | pagos
$filtroVencimento = $_GET['vencimento'] ?? 'todos'; // todos | proximos | vencidos
$filtroStatus     = $_GET['status']     ?? 'todos'; // todos | ativos | inativos
$section          = $_GET['section']    ?? 'usuarios'; // default to users

$conditions = [];
$params     = [];

// Filtro por status
if ($filtroStatus === 'ativos') {
    $conditions[] = "ativo = 1";
} elseif ($filtroStatus === 'inativos') {
    $conditions[] = "ativo = 0";
}

// Busca por nome / e-mail / estabelecimento
if ($searchTerm !== '') {
    $conditions[]       = "(nome LIKE :busca OR email LIKE :busca OR IFNULL(estabelecimento,'') LIKE :busca)";
    $params[':busca']   = "%{$searchTerm}%";
}

// Filtro por tipo (teste ou pagos)
if ($filtroTipo === 'teste') {
    $conditions[] = "is_teste = 1";
} elseif ($filtroTipo === 'pagos') {
    // pagos = não teste
    $conditions[] = "(is_teste IS NULL OR is_teste = 0)";
}

// Filtro por vencimento
if ($filtroVencimento === 'proximos') {
    // Próximos 15 dias
    $conditions[] = "is_vitalicio = 0 
                     AND data_expiracao IS NOT NULL 
                     AND data_expiracao >= DATE('now') 
                     AND data_expiracao <= DATE('now','+15 day')";
} elseif ($filtroVencimento === 'vencidos') {
    $conditions[] = "is_vitalicio = 0 
                     AND data_expiracao IS NOT NULL 
                     AND data_expiracao < DATE('now')";
}

$sql = "SELECT * FROM usuarios";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " ORDER BY nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vendedores e vendas recentes
$stmtVendedores = $pdo->query("SELECT * FROM vendedores ORDER BY nome ASC");
$vendedores = $stmtVendedores->fetchAll(PDO::FETCH_ASSOC);
$mapVendedores = [];
foreach ($vendedores as $v) {
    $mapVendedores[$v['id']] = $v;
}

$stmtVendas = $pdo->query("SELECT va.*, v.nome AS vendedor_nome, v.codigo AS vendedor_codigo, u.nome AS cliente_nome, u.email AS cliente_email
    FROM vendas_assinaturas va
    JOIN vendedores v ON v.id = va.vendedor_id
    JOIN usuarios u ON u.id = va.usuario_id
    ORDER BY va.id DESC
    LIMIT 50");
$vendas = $stmtVendas->fetchAll(PDO::FETCH_ASSOC);


$total    = count($usuarios);
$ativos   = 0;
$inativos = 0;

// Agrupamento por mês (Histórico de Vendas)
$receitaMensal = [];
try {
    $sqlMensal = "SELECT strftime('%m/%Y', criado_em) as mes_ano, SUM(valor) as total 
                  FROM vendas_assinaturas 
                  GROUP BY mes_ano 
                  ORDER BY criado_em DESC 
                  LIMIT 12";
    // Nota: Se for MySQL use: DATE_FORMAT(criado_em, '%m/%Y')
    // Assumindo SQLite pelo DATE('now') visto anteriormente
    $stmtMensal = $pdo->query($sqlMensal);
    if ($stmtMensal) {
        $receitaMensal = $stmtMensal->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Falls back if table or syntax differs
}

foreach ($usuarios as $u) {
    if (!empty($u['ativo'])) $ativos++;
    else $inativos++;
}

// Cálculo de receita, comissões e saldo da Luciana
$hojeDate           = new DateTime();
$receitaTotal       = 0.0;
$comissoesIndicacao = []; // [nome_indicador => valor]
$luciana10Total     = 0.0;
$lucroSistema       = 0.0;

foreach ($usuarios as $u) {
    if (empty($u['ativo'])) continue; // Apenas usuários ativos contam para o saldo atual

    $valorMensal = (isset($u['valor_mensal']) && $u['valor_mensal'] > 0)
        ? (float)$u['valor_mensal']
        : VALOR_BASE_MENSAL;

    if (empty($u['criado_em'])) continue;

    $inicio = new DateTime($u['criado_em']);

    // Vitalício: considera até hoje como permanência para efeito de receita
    if (!empty($u['is_vitalicio'])) {
        $fim = $hojeDate;
    } else {
        if (!empty($u['data_expiracao'])) {
            $dataExp = new DateTime($u['data_expiracao']);
            $fim     = ($dataExp < $hojeDate) ? $dataExp : $hojeDate;
        } else {
            $fim = $hojeDate;
        }
    }

    if ($fim <= $inicio) continue;

    $dias  = $inicio->diff($fim)->days;
    $meses = (int)floor($dias / 30);
    if ($meses < 1) $meses = 1;

    $valorBase = $valorMensal * $meses;

    // se valorMensal = 0 (teste grátis), não soma
    if ($valorBase <= 0) {
        continue;
    }

    $receitaTotal += $valorBase;

    // 10% da Luciana
    $luciana10      = $valorBase * PERC_SOCIA_LUCIANA;
    $luciana10Total += $luciana10;

    // Comissão de indicação (não conta para vitalício e nem para teste)
    $comissaoIndic = 0.0;
    if (empty($u['is_vitalicio']) && !empty($u['indicado_por'])) {
        $comissaoIndic = COMISSAO_INDICACAO * $meses;

        $nomeInd = $u['indicado_por'];
        if (!isset($comissoesIndicacao[$nomeInd])) {
            $comissoesIndicacao[$nomeInd] = 0.0;
        }
        $comissoesIndicacao[$nomeInd] += $comissaoIndic;
    }

    // Lucro líquido do sistema
    $lucroSistema += ($valorBase - $luciana10 - $comissaoIndic);
}

$totalComissaoIndic  = array_sum($comissoesIndicacao);
$lucianaSoIndicacoes = $comissoesIndicacao[NOME_SOCIA_LUCIANA] ?? 0.0;
$lucianaSaldoFinal   = $luciana10Total + $lucianaSoIndicacoes;

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | Develoi Gestão</title>

    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="favicon.svg">
    <meta name="theme-color" content="#4f46e5">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --secondary: #f43f5e;
            --accent: #8b5cf6;
            --bg-deep: #f8fafc; /* Light background for content */
            --bg-sidebar: #1e1e2e; /* Dark sidebar */
            --bg-surface: #ffffff;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 250px;
            --bottom-nav-height: 72px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            min-height: 100vh;
            margin: 0;
            display: block;
            padding-bottom: env(safe-area-inset-bottom);
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', sans-serif; font-weight: 700; }

        /* LAYOUT WRAPPER */
        .admin-wrapper {
            display: block;
            padding-left: 0;
            transition: padding 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding-top: 20px;
        }

        @media (min-width: 992px) {
            .admin-wrapper { padding-left: var(--sidebar-width); padding-top: 0; }
        }

        /* SIDEBAR DESKTOP */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid rgba(255,255,255,0.05);
            z-index: 1000;
            padding: 2rem 1.5rem;
            display: none;
            flex-direction: column;
            box-shadow: 10px 0 30px rgba(0,0,0,0.05);
        }

        @media (min-width: 992px) { .sidebar { display: flex; } }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 3rem;
            text-decoration: none;
        }

        .sidebar-logo i {
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 4px 10px rgba(99, 102, 241, 0.3));
        }

        .sidebar-logo span {
            font-size: 1.4rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            font-family: 'Outfit', sans-serif;
        }

        .nav-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .nav-item { margin-bottom: 0.75rem; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(79, 70, 229, 0.1);
            color: #fff;
            border-left: 3px solid var(--primary);
        }

        .nav-link.active i { color: var(--primary); }

        .sidebar-footer { padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.05); }

        /* BOTTOM NAV MOBILE */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--bottom-nav-height);
            background: #ffffff;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            padding: 0 10px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
        }

        @media (min-width: 992px) { .bottom-nav { display: none; } }

        .bnav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 700;
            gap: 4px;
            transition: all 0.2s;
            flex: 1;
        }

        .bnav-item i { font-size: 1.4rem; transition: transform 0.2s; }
        .bnav-item.active { color: var(--primary); }
        .bnav-item.active i { transform: translateY(-3px); }

        /* HEADER MOBILE */
        .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.25rem;
            background: transparent;
        }
        @media (min-width: 992px) { .mobile-header { display: none; } }

        /* STAT CARDS */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
            height: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--primary);
        }
        .stat-card.stat-success::after { background: var(--success); }
        .stat-card.stat-danger::after { background: var(--danger); }
        .stat-card.stat-info::after { background: var(--secondary); }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            background: #f8fafc;
        }

        .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.15rem; letter-spacing: -0.5px; }
        .stat-label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* TABLE & CONTENT CARDS */
        .glass-panel {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .panel-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-modern th {
            padding: 1.25rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(0,0,0,0.1);
        }
        .table-modern td {
            padding: 1rem 1.25rem;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 0.85rem;
        }
        .table-modern tr:last-child td { border-bottom: none; }
        .table-modern tr:hover { background: #f8fafc; }

        /* BADGES */
        .badge-premium {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* MOBILE CARDS */
        .mobile-card {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .mobile-card-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; align-items: center; }
        .mobile-card-label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .mobile-card-value { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }

        /* BUTTONS */
        .btn-premium {
            background: var(--primary);
            border: none;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-premium:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2); color: #fff; }

        .form-control, .form-select {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            padding: 7px 12px;
            font-size: 0.8rem;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.07);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.15);
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #eee;
            background: #fff;
            color: var(--text-muted);
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .action-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* MODALS */
        .modal-content { background: #fff; border: none; border-radius: 12px; color: var(--text-main); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 1.25rem; }
        .modal-footer { border-top: 1px solid #f1f5f9; padding: 1.25rem; }
        .modal-body { padding: 1.25rem; }
        .form-control, .form-select {
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            color: var(--text-main) !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); border-color: var(--primary) !important; }

        @media (max-width: 991px) {
            .admin-wrapper { padding-bottom: 100px; }
        }
    </style>
</head>

<body>
    <aside class="sidebar" id="sidebarMain">
        <div class="d-flex justify-content-between align-items-center mb-4 d-lg-none">
             <span class="fw-bold text-white">MENU</span>
             <button class="btn-close btn-close-white" onclick="toggleSidebar()"></button>
        </div>
        <a href="?" class="sidebar-logo">
            <i class="bi bi-shield-lock-fill"></i>
            <span>DEVELOI ADMIN</span>
        </a>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="?section=usuarios" class="nav-link <?= (!$section || $section === 'usuarios') ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Usuários</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?section=vendedores" class="nav-link <?= ($section === 'vendedores') ? 'active' : '' ?>">
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Vendedores</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?section=luciana" class="nav-link <?= ($section === 'luciana') ? 'active' : '' ?>">
                    <i class="bi bi-star-fill"></i>
                    <span>Sócia Luciana</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sair do Painel</span>
            </a>
        </div>
    </aside>

    <!-- BOTTOM NAV MOBILE -->
    <nav class="bottom-nav">
        <a href="?section=usuarios" class="bnav-item <?= (!$section || $section === 'usuarios') ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Usuários</span>
        </a>
        <a href="?section=vendedores" class="bnav-item <?= ($section === 'vendedores') ? 'active' : '' ?>">
            <i class="bi bi-person-badge-fill"></i>
            <span>Vendedores</span>
        </a>
        <a href="?section=luciana" class="bnav-item <?= ($section === 'luciana') ? 'active' : '' ?>">
            <i class="bi bi-star-fill"></i>
            <span>Sócia</span>
        </a>
        <a href="?logout=1" class="bnav-item">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sair</span>
        </a>
    </nav>

    <main class="admin-wrapper">
        <div class="container-fluid px-md-5">
            
            <header class="mobile-header d-lg-none">
                <div class="d-flex align-items-center gap-3">
                    <button class="action-btn border-0 bg-transparent" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div class="sidebar-logo m-0">
                        <span style="color: var(--primary)">DEVELOI</span>
                    </div>
                </div>
                <div class="avatar-circle">A</div>
            </header>

            <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
                <h2 class="h4 mb-0 fw-800">Resumo do Sistema</h2>
                
                <form method="get" class="d-flex gap-2">
                    <input type="hidden" name="section" value="<?= $section ?>">
                    <select name="status" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
                        <option value="todos" <?= $filtroStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="ativos" <?= $filtroStatus === 'ativos' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inativos" <?= $filtroStatus === 'inativos' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                    <select name="tipo" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
                        <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Tipo: Todos</option>
                        <option value="pagos" <?= $filtroTipo === 'pagos' ? 'selected' : '' ?>>Pagos</option>
                        <option value="teste" <?= $filtroTipo === 'teste' ? 'selected' : '' ?>>Teste</option>
                    </select>
                    <select name="vencimento" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                        <option value="todos" <?= $filtroVencimento === 'todos' ? 'selected' : '' ?>>Vencimento</option>
                        <option value="proximos" <?= $filtroVencimento === 'proximos' ? 'selected' : '' ?>>Próximos 15d</option>
                        <option value="vencidos" <?= $filtroVencimento === 'vencidos' ? 'selected' : '' ?>>Já Vencidos</option>
                    </select>
                    <?php if ($searchTerm): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($feedback): ?>
                <div class="alert alert-indigo border-0 rounded-4 shadow-sm mb-4" style="background: rgba(129, 140, 248, 0.1); color: #c7d2fe;">
                    <i class="bi bi-info-circle me-2"></i> <?= htmlspecialchars($feedback) ?>
                </div>
            <?php endif; ?>

            <div class="row g-2 g-md-4 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary);">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-value"><?= $total ?></div>
                        <div class="stat-label">Total Usuários</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-success">
                        <div class="stat-icon" style="color: var(--success);">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="stat-value"><?= $ativos ?></div>
                        <div class="stat-label">Ativos Agora</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-danger">
                        <div class="stat-icon" style="color: var(--danger);">
                            <i class="bi bi-dash-circle-fill"></i>
                        </div>
                        <div class="stat-value"><?= $inativos ?></div>
                        <div class="stat-label">Inativos</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card stat-info">
                        <div class="stat-icon" style="color: var(--secondary);">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="stat-value">R$ <?= number_format($receitaTotal, 0, ',', '.') ?></div>
                        <div class="stat-label">Receita Bruta</div>
                    </div>
                </div>
            </div>

            <!-- FATURAMENTO POR MÊS -->
            <?php if (!empty($receitaMensal)): ?>
            <div class="glass-panel p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Histórico de Faturamento</h5>
                    <span class="badge bg-primary-subtle text-primary py-2 px-3 rounded-pill" style="font-size:0.75rem">Últimos 12 meses</span>
                </div>
                <div class="row g-2">
                    <?php foreach ($receitaMensal as $rm): ?>
                        <div class="col-4 col-md-2">
                            <div class="stat-card p-2 text-center" style="box-shadow:none; border: 1px solid #f1f5f9;">
                                <div class="text-muted small mb-1" style="font-size:0.65rem"><?= $rm['mes_ano'] ?></div>
                                <div class="fw-bold text-primary" style="font-size:0.9rem">R$ <?= number_format($rm['total'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- SEÇÃO: USUÁRIOS -->
            <?php if ($section === 'usuarios' || !$section): ?>
                <div class="glass-panel">
                    <div class="panel-header">
                        <div>
                            <h4 class="mb-1"><i class="bi bi-people-fill me-2" style="color:var(--primary)"></i>Gestão de Assinaturas</h4>
                            <p class="text-muted small mb-0">Controle de acessos e validades</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <form method="get" class="d-flex gap-2">
                                <input type="hidden" name="section" value="usuarios">
                                <input type="text" name="search" class="form-control form-control-sm" style="width:150px; border-radius:10px;" placeholder="Buscar..." value="<?= htmlspecialchars($searchTerm) ?>">
                                <button type="submit" class="action-btn"><i class="bi bi-search"></i></button>
                            </form>
                            <button class="btn-premium btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovo"><i class="bi bi-plus-lg"></i> Novo</button>
                        </div>
                    </div>

                    <div class="table-responsive d-none d-lg-block">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Profissional</th>
                                    <th>Status</th>
                                    <th>Validade</th>
                                    <th>Link</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php $v = formatarValidadeParaAdminUI($u); ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($u['nome']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge-premium <?= $u['ativo'] ? 'badge-active' : 'badge-danger' ?>"><?= $u['ativo'] ? 'Ativo' : 'Bloq.' ?></span>
                                        </td>
                                        <td>
                                            <div class="badge-premium <?= $v['classe'] ?>"><?= $v['texto'] ?></div>
                                            <div class="small text-muted mt-1">R$ <?= number_format($u['valor_mensal'] ?: VALOR_BASE_MENSAL, 2, ',', '.') ?>/mês</div>
                                        </td>
                                        <td>
                                            <div class="small text-muted">Ind: <?= htmlspecialchars($u['indicado_por'] ?: 'Direto') ?></div>
                                            <div class="small text-muted">Vend: <?= isset($mapVendedores[$u['vendedor_id']]) ? htmlspecialchars($mapVendedores[$u['vendedor_id']]['nome']) : '-' ?></div>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#modalRenovar" onclick="prepararRenovacao(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>')"><i class="bi bi-calendar-plus"></i></a>
                                                <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario" onclick="prepararEdicaoUsuario(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>', '<?= addslashes($u['email']) ?>', <?= !empty($u['is_teste']) ? 'true' : 'false' ?>, <?= !empty($u['ativo']) ? 'true' : 'false' ?>)"><i class="bi bi-pencil"></i></a>
                                                <a href="?acao=excluir&id=<?= $u['id'] ?>&section=usuarios" class="action-btn text-danger" onclick="return confirm('Excluir?')"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE -->
                    <div class="d-lg-none p-3">
                        <?php foreach ($usuarios as $u): ?>
                            <?php $v = formatarValidadeParaAdminUI($u); ?>
                            <div class="mobile-card">
                                <div class="mobile-card-row">
                                    <span class="fw-bold"><?= htmlspecialchars($u['nome']) ?></span>
                                    <span class="badge-premium <?= $u['ativo'] ? 'badge-active' : 'badge-danger' ?>"><?= $u['ativo'] ? 'Ativo' : 'Bloq.' ?></span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Validade</span>
                                    <span class="mobile-card-value text-<?= str_replace('badge-','',$v['classe']) ?>"><?= $v['texto'] ?></span>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button class="action-btn flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalRenovar" onclick="prepararRenovacao(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>')"><i class="bi bi-calendar-plus me-1"></i> Renovar</button>
                                    <button class="action-btn flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario" onclick="prepararEdicaoUsuario(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>', '<?= addslashes($u['email']) ?>', <?= !empty($u['is_teste']) ? 'true' : 'false' ?>, <?= !empty($u['ativo']) ? 'true' : 'false' ?>)"><i class="bi bi-pencil me-1"></i> Editar</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SEÇÃO: VENDEDORES -->
            <?php if ($section === 'vendedores'): ?>
                <div class="glass-panel">
                    <div class="panel-header">
                        <h4 class="mb-0">Equipe de Vendas</h4>
                        <button class="btn-premium btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoVendedor">Novo Vendedor</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr><th>Nome</th><th>Código</th><th>Status</th><th class="text-end">Ações</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendedores as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['nome']) ?></td>
                                        <td><code class="text-primary"><?= htmlspecialchars($v['codigo']) ?></code></td>
                                        <td><span class="badge-premium <?= $v['ativo'] ? 'badge-active' : 'badge-danger' ?>"><?= $v['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
                                        <td class="text-end">
                                            <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#modalEditarVendedor" onclick="prepararEdicaoVendedor(<?= $v['id'] ?>, '<?= addslashes($v['nome']) ?>', '<?= addslashes($v['email']) ?>', '<?= addslashes($v['telefone'] ?? '') ?>', '<?= addslashes($v['cpf'] ?? '') ?>', <?= $v['ativo'] ? 'true' : 'false' ?>)"><i class="bi bi-pencil"></i></a>
                                            <a href="?acao_vendedor=excluir&id=<?= $v['id'] ?>&section=vendedores" class="action-btn text-danger ms-2" onclick="return confirm('Excluir vendedor?')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SEÇÃO: LUCIANA -->
            <?php if ($section === 'luciana'): ?>
                <div class="glass-panel">
                    <div class="panel-header"><h4>Repasse Sócia Luciana</h4></div>
                    <div class="p-4">
                        <div class="row g-4 text-center">
                            <div class="col-6">
                                <div class="p-3 bg-dark rounded-4">
                                    <div class="text-muted small">Fixo 10%</div>
                                    <div class="h4 mb-0">R$ <?= number_format($lucianaFixo10, 2, ',', '.') ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-dark rounded-4">
                                    <div class="text-muted small">Indicações</div>
                                    <div class="h4 mb-0 text-primary">R$ <?= number_format($lucianaSoIndicacoes, 2, ',', '.') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- container-fluid -->
    </main>

    <!-- MODALS -->
    <div class="modal fade" id="modalNovo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Novo Profissional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome Completo</label>
                            <input type="text" name="nome" class="form-control" placeholder="Ex: João Silva" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-mail de Acesso</label>
                            <input type="email" name="email" class="form-control" placeholder="email@exemplo.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Senha Inicial</label>
                            <input type="password" name="senha" class="form-control" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Plano Inicial</label>
                                <select name="plano_inicial" class="form-select">
                                    <option value="30">30 Dias (Mensal)</option>
                                    <option value="90">90 Dias (Trimestral)</option>
                                    <option value="365">365 Dias (Anual)</option>
                                    <option value="teste_7">7 Dias (Teste)</option>
                                    <option value="vitalicio">Vitalício</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Status</label>
                                <select name="ativo" class="form-select">
                                    <option value="1">Ativo</option>
                                    <option value="0">Bloqueado</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Valor Mensal (R$)</label>
                            <input type="text" name="valor_mensal" class="form-control" value="19.90">
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_teste" id="is_teste_novo">
                            <label class="form-check-label small" for="is_teste_novo">Este é um usuário de teste (sem comissão)</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary w-100 py-2">Criar Profissional</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Editar Profissional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome</label>
                            <input type="text" name="edit_nome" id="edit_nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-mail</label>
                            <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status da Conta</label>
                            <select name="edit_ativo" id="edit_ativo" class="form-select">
                                <option value="1">Ativo</option>
                                <option value="0">Bloqueado</option>
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="edit_is_teste" id="edit_is_teste">
                            <label class="form-check-label small" for="edit_is_teste">Modo Teste</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary w-100 py-2">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Novo Vendedor -->
    <div class="modal fade" id="modalNovoVendedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Novo Vendedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_vendedor">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome</label>
                            <input type="text" name="vend_nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-mail</label>
                            <input type="email" name="vend_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Senha</label>
                            <input type="password" name="vend_senha" class="form-control" required>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="vend_ativo" checked>
                            <label class="form-check-label small">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary w-100">Criar Vendedor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Vendedor -->
    <div class="modal fade" id="modalEditarVendedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Editar Vendedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_vendedor">
                        <input type="hidden" name="edit_vend_id" id="edit_vend_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome</label>
                            <input type="text" name="edit_vend_nome" id="edit_vend_nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-mail</label>
                            <input type="email" name="edit_vend_email" id="edit_vend_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nova Senha (deixe em branco para não alterar)</label>
                            <input type="password" name="edit_vend_senha" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Telefone</label>
                            <input type="text" name="edit_vend_telefone" id="edit_vend_telefone" class="form-control">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="edit_vend_ativo" id="edit_vend_ativo">
                            <label class="form-check-label small">Ativo</label>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary w-100">Salvar Dados</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL RENOVAR -->
    <div class="modal fade" id="modalRenovar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
                <div class="modal-header border-0 pb-2" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(236, 72, 153, 0.05));">
                    <h5 class="modal-title fw-bold" style="font-size:1.1rem; font-family: 'Outfit', sans-serif; color: var(--dark);">
                        <i class="bi bi-calendar-plus-fill me-2" style="color: var(--primary);"></i>
                        Renovar Acesso
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem 2rem;">
                    <div class="alert alert-info d-flex align-items-center gap-2 mb-4" style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 16px; color: #075985;">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>
                            <strong>Renovando para:</strong><br>
                            <span id="renew_user_name" style="font-size: 1.05rem; font-weight: 600;"></span>
                        </div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="user_id_renew" id="user_id_renew">

                        <div class="d-grid gap-3">
                            <button type="submit" name="tipo_renovacao" value="add_30" class="btn btn-outline-primary text-start d-flex align-items-center gap-3" style="font-size:0.9rem; border-radius:16px; padding: 14px 18px; border: 2px solid #e2e8f0; transition: all 0.3s;">
                                <i class="bi bi-plus-circle-fill fs-5" style="color: var(--primary);"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">+ 30 Dias</div>
                                    <small class="text-muted">Mensal</small>
                                </div>
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_60" class="btn btn-outline-primary text-start d-flex align-items-center gap-3" style="font-size:0.9rem; border-radius:16px; padding: 14px 18px; border: 2px solid #e2e8f0; transition: all 0.3s;">
                                <i class="bi bi-plus-circle-fill fs-5" style="color: var(--primary);"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">+ 60 Dias</div>
                                    <small class="text-muted">Bimestral</small>
                                </div>
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_90" class="btn btn-outline-primary text-start d-flex align-items-center gap-3" style="font-size:0.9rem; border-radius:16px; padding: 14px 18px; border: 2px solid #e2e8f0; transition: all 0.3s;">
                                <i class="bi bi-plus-circle-fill fs-5" style="color: var(--primary);"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">+ 90 Dias</div>
                                    <small class="text-muted">Trimestral</small>
                                </div>
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_365" class="btn btn-outline-primary text-start d-flex align-items-center gap-3" style="font-size:0.9rem; border-radius:16px; padding: 14px 18px; border: 2px solid #e2e8f0; transition: all 0.3s;">
                                <i class="bi bi-calendar-check-fill fs-5" style="color: var(--success);"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">+ 1 Ano</div>
                                    <small class="text-muted">Anual</small>
                                </div>
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_730" class="btn btn-outline-primary text-start d-flex align-items-center gap-3" style="font-size:0.9rem; border-radius:16px; padding: 14px 18px; border: 2px solid #e2e8f0; transition: all 0.3s;">
                                <i class="bi bi-calendar-check-fill fs-5" style="color: var(--success);"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">+ 2 Anos</div>
                                    <small class="text-muted">Bienal</small>
                                </div>
                            </button>
                            <div class="border-top my-2"></div>
                            <button type="submit" name="tipo_renovacao" value="set_vitalicio" class="btn text-start d-flex align-items-center gap-3" style="font-size:0.9rem; border-radius:16px; padding: 14px 18px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #fde68a; color: #92400e; font-weight: 700;">
                                <i class="bi bi-star-fill fs-5"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">Tornar Vitalício</div>
                                    <small>Sem expiração</small>
                                </div>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
            <div class="toast show border-0 shadow-lg" role="alert" style="border-radius: 20px; background: white; overflow: hidden;">
                <div class="toast-body d-flex align-items-center gap-3 p-3">
                    <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-radius: 14px;">
                        <i class="bi bi-check-circle-fill fs-4" style="color: #10b981;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <strong class="d-block" style="font-size:0.95rem; color: var(--dark);">Sucesso!</strong>
                        <small class="text-muted" style="font-size: 0.8rem;">Operação realizada com sucesso.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarMain');
            sidebar.classList.toggle('d-flex');
            if (sidebar.classList.contains('d-flex')) {
                sidebar.style.display = 'flex';
                sidebar.style.width = '100%';
                sidebar.style.backgroundColor = 'rgba(15, 23, 42, 0.95)';
                sidebar.style.backdropFilter = 'blur(10px)';
            } else {
                sidebar.style.display = 'none';
            }
        }
        function prepararRenovacao(id, nome) {
            document.getElementById('user_id_renew').value = id;
            document.getElementById('renew_user_name').textContent = nome;
        }

        function prepararEdicaoUsuario(id, nome, email, isTeste, ativo) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_is_teste').checked = isTeste;
            document.getElementById('edit_ativo').value = ativo ? "1" : "0";
        }

        function prepararEdicaoVendedor(id, nome, email, telefone, cpf, ativo) {
            document.getElementById('edit_vend_id').value = id;
            document.getElementById('edit_vend_nome').value = nome;
            document.getElementById('edit_vend_email').value = email;
            document.getElementById('edit_vend_telefone').value = telefone || '';
            document.getElementById('edit_vend_cpf').value = cpf || '';
            document.getElementById('edit_vend_ativo').checked = ativo;
        }

        setTimeout(() => {
            const toastEl = document.querySelector('.toast');
            if (toastEl) {
                var bsToast = new bootstrap.Toast(toastEl);
                bsToast.hide();
            }
        }, 4000);
    </script>
</body>

</html>
