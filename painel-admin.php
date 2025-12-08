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
                --primary: #4f46e5;
                --primary-dark: #4338ca;
                --secondary: #ec4899;
                --dark: #0f172a;
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                height: 100%;
                margin: 0;
            }

            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
            }

            .aurora-bg {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
                overflow: hidden;
            }

            .aurora-blob {
                position: absolute;
                border-radius: 50%;
                filter: blur(90px);
                opacity: 0.4;
                animation: float-blob 15s infinite alternate;
            }

            .blob-1 {
                top: -10%;
                left: -10%;
                width: 60vw;
                height: 60vw;
                background: #c7d2fe;
                animation-duration: 25s;
            }

            .blob-2 {
                bottom: -10%;
                right: -10%;
                width: 50vw;
                height: 50vw;
                background: #fbcfe8;
                animation-duration: 20s;
            }

            @keyframes float-blob {
                0% {
                    transform: translate(0, 0);
                }

                100% {
                    transform: translate(40px, -40px);
                }
            }

            .login-card {
                background: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(20px);
                padding: 2.5rem 2rem;
                border-radius: 32px;
                box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.15);
                border: 1px solid rgba(255, 255, 255, 0.8);
                width: 100%;
                max-width: 440px;
                margin: 2rem 1rem;
                transition: all 0.3s;
                position: relative;
                z-index: 10;
            }

            .login-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 40px 80px -20px rgba(15, 23, 42, 0.2);
            }

            .brand-logo-container {
                text-align: center;
                margin-bottom: 1.5rem;
            }

            .brand-d {
                font-family: 'Outfit', sans-serif;
                font-size: 3rem;
                font-weight: 800;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                border-radius: 18px;
                width: 70px;
                height: 70px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                box-shadow: 0 8px 32px rgba(79, 70, 229, 0.15);
                margin-bottom: 0.5rem;
            }

            .brand-text {
                font-family: 'Outfit', sans-serif;
                font-size: 1.8rem;
                font-weight: 700;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                letter-spacing: -0.02em;
            }

            .subtitle {
                color: #64748b;
                font-size: 0.95rem;
                margin-top: 0.5rem;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                border: none;
                padding: 14px;
                border-radius: 999px;
                font-weight: 600;
                width: 100%;
                font-size: 1rem;
                transition: all 0.3s;
                box-shadow: 0 10px 30px -5px rgba(79, 70, 229, 0.5);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 20px 40px -5px rgba(236, 72, 153, 0.5);
            }

            .form-control {
                padding: 14px 18px;
                border-radius: 16px;
                border: 1px solid #e2e8f0;
                background: rgba(255, 255, 255, 0.8);
                font-size: 1rem;
                transition: all 0.3s;
            }

            .form-control:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
                background: white;
            }

            label.form-label {
                font-weight: 600;
                font-size: 0.85rem;
                color: var(--dark);
                margin-bottom: 0.5rem;
            }

            .alert {
                border-radius: 16px;
                border: none;
                font-size: 0.9rem;
            }

            @media (max-width: 600px) {
                .login-card {
                    padding: 2rem 1.5rem;
                    max-width: 95vw;
                }

                .brand-d {
                    width: 60px;
                    height: 60px;
                    font-size: 2.5rem;
                }

                .brand-text {
                    font-size: 1.5rem;
                }
            }
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
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

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

                $pdo->prepare($sql)->execute([
                    $nome,
                    $email,
                    $hash,
                    1, // ativo
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

        if ($edit_id > 0 && $edit_nome && $edit_email) {
            $sql = "UPDATE usuarios SET nome = ?, email = ?, is_teste = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([
                $edit_nome,
                $edit_email,
                $edit_is_teste,
                $edit_id
            ]);
            header("Location: {$painelAdminUrl}?msg=edited");
            exit;
        } else {
            $feedback = 'Preencha todos os campos obrigatórios.';
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

$conditions = [];
$params     = [];

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
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);


$total    = count($usuarios);
$ativos   = 0;
$inativos = 0;

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
    $valorMensal = isset($u['valor_mensal']) && $u['valor_mensal'] > 0
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
            --primary-dark: #4338ca;
            --secondary: #ec4899;
            --accent: #8b5cf6;
            --dark: #0f172a;
            --light: #f8fafc;
            --success: #10b981;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --text-main: #111827;
            --text-muted: #6b7280;
            --shadow-soft: 0 20px 40px -10px rgba(0, 0, 0, 0.08);
            --shadow-strong: 0 25px 60px -15px rgba(15, 23, 42, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
            color: var(--text-main);
            padding-bottom: 40px;
            font-size: 14px;
            position: relative;
            min-height: 100vh;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Outfit', sans-serif;
        }

        .aurora-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .aurora-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.3;
            animation: float-blob 20s infinite alternate;
        }

        .blob-1 {
            top: -10%;
            left: -10%;
            width: 60vw;
            height: 60vw;
            background: #c7d2fe;
            animation-duration: 25s;
        }

        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 50vw;
            height: 50vw;
            background: #fbcfe8;
            animation-duration: 20s;
        }

        @keyframes float-blob {
            0% {
                transform: translate(0, 0);
            }

            100% {
                transform: translate(40px, -40px);
            }
        }

        .top-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand-logo {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.3rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 1.5rem 1.3rem;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-strong);
            background: white;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1;
            margin-top: 8px;
            font-family: 'Outfit', sans-serif;
        }

        .stat-label {
            text-transform: uppercase;
            font-weight: 700;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .content-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }

        .content-card:hover {
            box-shadow: var(--shadow-strong);
            background: white;
        }

        .card-header-custom {
            padding: 1.5rem 1.8rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: rgba(249, 250, 251, 0.5);
        }

        .table-custom th {
            background: #f9fafb;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
            padding: 0.7rem 1.1rem;
        }

        .table-custom td {
            padding: 0.7rem 1.1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.8rem;
        }

        .avatar-circle {
            width: 32px;
            height: 32px;
            background: #e0e7ff;
            color: var(--primary);
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 8px;
            font-size: 0.85rem;
        }

        .badge-status {
            padding: 0.35em 0.9em;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .status-active {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }

        .status-inactive {
            background: #f3f4f6;
            color: #374151;
        }

        .badge-vitalicio {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #fde68a;
            box-shadow: 0 2px 8px rgba(251, 191, 36, 0.2);
        }

        .badge-time {
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            color: #075985;
            border: 1px solid #bae6fd;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.2);
        }

        .badge-expired {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #fecaca;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2);
        }

        .status-teste {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            box-shadow: 0 2px 8px rgba(55, 48, 163, 0.16);
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            border: none;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .btn-renew {
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            color: #0284c7;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
        }

        .btn-renew:hover {
            background: linear-gradient(135deg, #bae6fd, #7dd3fc);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            box-shadow: 0 4px 12px rgba(153, 27, 27, 0.2);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #fecaca, #fca5a5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(153, 27, 27, 0.3);
        }

        .pill {
            border-radius: 999px;
            padding: 0.2rem 0.7rem;
            font-size: 0.7rem;
        }

        .user-card {
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            padding: 1.2rem;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-soft);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-strong);
            background: white;
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .user-card-name {
            font-weight: 700;
            font-size: 0.95rem;
            font-family: 'Outfit', sans-serif;
            color: var(--dark);
        }

        .user-card-email {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .user-card-badges {
            margin-top: 0.8rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .user-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(148, 163, 184, 0.15);
        }

        .user-card-plan {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .user-card-actions .btn-action {
            margin-left: 0.3rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            font-size: 0.85rem;
            border-radius: 999px;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            box-shadow: 0 10px 30px -5px rgba(79, 70, 229, 0.5);
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -5px rgba(236, 72, 153, 0.5);
        }

        .btn-outline-secondary {
            border-radius: 999px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 767.98px) {
            body {
                padding-bottom: 70px;
            }

            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .content-card {
                border-radius: 24px;
            }

            .card-header-custom {
                padding: 1.2rem 1rem;
            }

            .stat-card {
                padding: 1.2rem 1rem;
                border-radius: 20px;
            }

            .stat-value {
                font-size: 1.6rem;
            }

            .top-nav {
                padding: 0.8rem 0;
            }

            .brand-logo {
                font-size: 1.1rem;
            }

            .brand-icon {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body>
    <div class="aurora-bg">
        <div class="aurora-blob blob-1"></div>
        <div class="aurora-blob blob-2"></div>
    </div>

    <nav class="top-nav">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="#" class="brand-logo">
                <div class="brand-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <span>DEVELOI ADMIN</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-block text-end lh-1">
                    <span class="d-block fw-bold" style="font-size:0.85rem; color: var(--dark);">Administrador</span>
                    <span class="text-muted" style="font-size: 0.72rem;">Sessão ativa</span>
                </div>
                <a href="?logout=1" class="btn btn-outline-secondary btn-sm rounded-pill px-3" style="font-size:0.78rem;">
                    <i class="bi bi-box-arrow-right me-1"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($feedback): ?>
            <div class="alert alert-warning rounded-4 shadow-sm mb-3" style="font-size:0.8rem;">
                <?= htmlspecialchars($feedback) ?>
            </div>
        <?php endif; ?>

        <!-- MÉTRICAS PRINCIPAIS -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-people-fill" style="color: var(--primary);"></i>
                        Total Usuários
                    </div>
                    <div class="stat-value"><?= $total ?></div>
                    <div class="small text-muted mt-2">Base completa</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill" style="color: var(--success);"></i>
                        Ativos
                    </div>
                    <div class="stat-value" style="color: var(--success);"><?= $ativos ?></div>
                    <div class="small text-muted mt-2">Acessando agora</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-dash-circle-fill" style="color: #94a3b8;"></i>
                        Bloqueados
                    </div>
                    <div class="stat-value" style="color: #64748b;"><?= $inativos ?></div>
                    <div class="small text-muted mt-2">Sem acesso</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-cash-coin" style="color: var(--secondary);"></i>
                        Receita Total
                    </div>
                    <div class="stat-value" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                        R$ <?= number_format($receitaTotal, 2, ',', '.') ?>
                    </div>
                    <div class="small text-muted mt-2">Histórico completo</div>
                </div>
            </div>
        </div>

        <!-- MÉTRICAS FINANCEIRAS DETALHES -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-gift-fill" style="color: var(--accent);"></i>
                        Comissões Indicação
                    </div>
                    <div class="stat-value" style="color: var(--accent);">
                        R$ <?= number_format($totalComissaoIndic, 2, ',', '.') ?>
                    </div>
                    <div class="small text-muted mt-2">Total de comissões</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-star-fill" style="color: #f59e0b;"></i>
                        Sócia Luciana
                    </div>
                    <div class="stat-value" style="color: #f59e0b;">
                        R$ <?= number_format($lucianaSaldoFinal, 2, ',', '.') ?>
                    </div>
                    <div class="small text-muted mt-2">
                        10% + Indicações
                        <?php if ($lucianaSoIndicacoes > 0): ?>
                            (R$ <?= number_format($lucianaSoIndicacoes, 2, ',', '.') ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label d-flex align-items-center gap-2">
                        <i class="bi bi-graph-up-arrow" style="color: var(--success);"></i>
                        Lucro Líquido
                    </div>
                    <div class="stat-value" style="color: var(--success);">
                        R$ <?= number_format($lucroSistema, 2, ',', '.') ?>
                    </div>
                    <div class="small text-muted mt-2">Sistema descontado</div>
                </div>
            </div>
        </div>

        <!-- TABELA / CARDS PRINCIPAIS -->
        <div class="content-card">
            <div class="card-header-custom">
                <div>
                    <h5 class="mb-1 fw-bold" style="font-size:1.1rem; font-family: 'Outfit', sans-serif;">
                        <i class="bi bi-people-fill me-2" style="color: var(--primary);"></i>
                        Gerenciar Assinaturas
                    </h5>
                    <p class="text-muted mb-0" style="font-size:0.8rem;">Controle completo de validade, indicações e acessos</p>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
                        <!-- Busca por nome / e-mail -->
                        <div class="input-group input-group-sm" style="min-width: 220px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input
                                type="text"
                                name="search"
                                class="form-control border-start-0"
                                placeholder="Buscar por nome ou e-mail"
                                value="<?= htmlspecialchars($searchTerm ?? '', ENT_QUOTES) ?>">
                        </div>

                        <!-- Filtro por tipo -->
                        <select name="tipo" class="form-select form-select-sm" style="border-radius:999px; font-size:0.8rem; max-width: 160px;">
                            <option value="todos" <?= ($filtroTipo === 'todos')   ? 'selected' : '' ?>>Todos</option>
                            <option value="teste" <?= ($filtroTipo === 'teste')   ? 'selected' : '' ?>>Apenas teste</option>
                            <option value="pagos" <?= ($filtroTipo === 'pagos')   ? 'selected' : '' ?>>Pagos</option>
                        </select>

                        <!-- Filtro por vencimento -->
                        <select name="vencimento" class="form-select form-select-sm" style="border-radius:999px; font-size:0.8rem; max-width: 190px;">
                            <option value="todos" <?= ($filtroVencimento === 'todos')    ? 'selected' : '' ?>>Todos vencimentos</option>
                            <option value="proximos" <?= ($filtroVencimento === 'proximos') ? 'selected' : '' ?>>Próx. 15 dias</option>
                            <option value="vencidos" <?= ($filtroVencimento === 'vencidos') ? 'selected' : '' ?>>Vencidos</option>
                        </select>

                        <button type="submit" class="btn btn-outline-secondary btn-sm px-3" style="border-radius:999px;">
                            <i class="bi bi-funnel me-1"></i> Filtrar
                        </button>

                        <?php if (!empty($searchTerm) || $filtroTipo !== 'todos' || $filtroVencimento !== 'todos'): ?>
                            <a href="<?= $painelAdminUrl ?>" class="btn btn-link btn-sm text-decoration-none" style="font-size:0.75rem;">
                                Limpar
                            </a>
                        <?php endif; ?>
                    </form>

                    <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modalNovo">
                        <i class="bi bi-plus-lg me-1"></i> Novo Usuário
                    </button>
                </div>
            </div>


            <!-- DESKTOP: TABELA -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Profissional</th>
                            <th>Status Conta</th>
                            <th>Validade / Plano</th>
                            <th>Indicação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $u): ?>
                                <?php
                                $iniciais = strtoupper(mb_substr($u['nome'] ?? '', 0, 1));
                                $isAdm    = ($u['id'] == 1);

                                // Label de validade
                                $labelValidade  = '';
                                $classeValidade = '';

                                if (!empty($u['is_vitalicio'])) {
                                    $labelValidade  = 'Vitalício (sem expiração)';
                                    $classeValidade = 'badge-vitalicio';
                                } else {
                                    if (empty($u['data_expiracao'])) {
                                        $labelValidade  = 'Não configurado';
                                        $classeValidade = 'badge-expired';
                                    } else {
                                        $dataExp  = new DateTime($u['data_expiracao']);
                                        $hojeData = new DateTime();

                                        if ($dataExp < $hojeData) {
                                            $labelValidade  = 'Expirou em ' . $dataExp->format('d/m/Y');
                                            $classeValidade = 'badge-expired';
                                        } else {
                                            $diff          = $hojeData->diff($dataExp);
                                            $diasRestantes = $diff->days;
                                            $labelValidade = $diasRestantes . ' dias restantes (' . $dataExp->format('d/m/Y') . ')';
                                            $classeValidade = 'badge-time';
                                        }
                                    }
                                }

                                $valorMensalList = isset($u['valor_mensal']) && $u['valor_mensal'] > 0
                                    ? (float)$u['valor_mensal']
                                    : VALOR_BASE_MENSAL;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle"><?= $iniciais ?></div>
                                            <div>
                                                <div class="fw-semibold text-dark" style="font-size:0.85rem;">
                                                    <?= htmlspecialchars($u['nome']) ?>
                                                    <?php if (!empty($u['is_teste'])): ?>
                                                        <span class="badge-status status-teste ms-1" style="font-size:0.65rem; padding:0.15rem 0.5rem;">
                                                            Teste
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="small text-muted" style="font-size:0.72rem;">
                                                    <?= htmlspecialchars($u['email']) ?>
                                                </div>
                                                <div class="small text-muted" style="font-size:0.7rem;">
                                                    Plano:
                                                    <?php if (!empty($u['is_teste'])): ?>
                                                        <span class="badge-status" style="background:#e0e7ff; color:#3730a3; margin-left:2px;">
                                                            Teste
                                                        </span>
                                                    <?php elseif ((float)$u['valor_mensal'] <= 0): ?>
                                                        Teste / Grátis
                                                    <?php else: ?>
                                                        R$ <?= number_format($valorMensalList, 2, ',', '.') ?>/mês
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['ativo'])): ?>
                                            <span class="badge-status status-active">
                                                <i class="bi bi-circle-fill" style="font-size:5px"></i> Ativo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status status-inactive">
                                                <i class="bi bi-slash-circle"></i> Bloqueado
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($u['is_teste'])): ?>
                                            <span class="badge-status status-teste ms-1">
                                                <i class="bi bi-flask"></i> Teste
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="badge-status <?= $classeValidade ?>">
                                            <?= $labelValidade ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['indicado_por'])): ?>
                                            <span class="pill bg-light border text-muted">
                                                <i class="bi bi-person-plus"></i>
                                                <?= htmlspecialchars($u['indicado_por']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="pill bg-light text-muted">Sem indicação</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$isAdm): ?>
                                            <button type="button"
                                                class="btn-action btn-renew me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalRenovar"
                                                onclick="prepararRenovacao(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>')"
                                                title="Renovar / Alterar Plano">
                                                <i class="bi bi-calendar-plus-fill"></i>
                                            </button>
                                            <button type="button"
                                                class="btn-action me-1"
                                                style="background:#eef2ff; color:#3730a3;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditar"
                                                onclick="prepararEdicaoUsuario(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', <?= !empty($u['is_teste']) ? 'true' : 'false' ?>)"
                                                title="Editar Usuário">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <?php if (!empty($u['ativo'])): ?>
                                                <a href="?acao=inativar&id=<?= (int)$u['id'] ?>" class="btn-action" style="background:#fff3cd; color:#b45309" title="Bloquear Manualmente">
                                                    <i class="bi bi-lock-fill"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?acao=ativar&id=<?= (int)$u['id'] ?>" class="btn-action" style="background:#dcfce7; color:#15803d" title="Liberar Manualmente">
                                                    <i class="bi bi-unlock-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?acao=excluir&id=<?= (int)$u['id'] ?>" class="btn-action btn-delete ms-1" onclick="return confirm('Excluir usuário?')" title="Excluir">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill" style="font-size:0.7rem;">Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted" style="font-size:0.8rem;">
                                    Nenhum usuário cadastrado ainda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE: CARDS -->
            <div class="d-block d-md-none px-3 px-md-0 pb-3">
                <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                        $iniciais = strtoupper(mb_substr($u['nome'] ?? '', 0, 1));
                        $isAdm    = ($u['id'] == 1);

                        if (!empty($u['is_vitalicio'])) {
                            $labelValidade  = 'Vitalício (sem expiração)';
                            $classeValidade = 'badge-vitalicio';
                        } else {
                            if (empty($u['data_expiracao'])) {
                                $labelValidade  = 'Não configurado';
                                $classeValidade = 'badge-expired';
                            } else {
                                $dataExp  = new DateTime($u['data_expiracao']);
                                $hojeData = new DateTime();

                                if ($dataExp < $hojeData) {
                                    $labelValidade  = 'Expirou em ' . $dataExp->format('d/m/Y');
                                    $classeValidade = 'badge-expired';
                                } else {
                                    $diff          = $hojeData->diff($dataExp);
                                    $diasRestantes = $diff->days;
                                    $labelValidade  = $diasRestantes . ' dias restantes (' . $dataExp->format('d/m/Y') . ')';
                                    $classeValidade = 'badge-time';
                                }
                            }
                        }

                        $valorMensalList = isset($u['valor_mensal']) && $u['valor_mensal'] > 0
                            ? (float)$u['valor_mensal']
                            : VALOR_BASE_MENSAL;
                        ?>
                        <div class="user-card">
                            <div class="user-card-header">
                                <div class="avatar-circle"><?= $iniciais ?></div>
                                <div>
                                    <div class="user-card-name"><?= htmlspecialchars($u['nome']) ?></div>
                                    <div class="user-card-email"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>

                            <div class="user-card-badges">
                                <?php if (!empty($u['ativo'])): ?>
                                    <span class="badge-status status-active">
                                        <i class="bi bi-circle-fill" style="font-size:5px"></i> Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status status-inactive">
                                        <i class="bi bi-slash-circle"></i> Bloqueado
                                    </span>
                                <?php endif; ?>

                                <span class="badge-status <?= $classeValidade ?>">
                                    <?= $labelValidade ?>
                                </span>

                                <?php if (!empty($u['indicado_por'])): ?>
                                    <span class="pill bg-light border text-muted">
                                        <i class="bi bi-person-plus"></i>
                                        <?= htmlspecialchars($u['indicado_por']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="pill bg-light text-muted">Sem indicação</span>
                                <?php endif; ?>

                                <?php if ($isAdm): ?>
                                    <span class="pill bg-secondary text-white">Admin</span>
                                <?php endif; ?>
                            </div>

                            <div class="user-card-footer">
                                <div class="user-card-plan">
                                    Plano:
                                    <?php if (!empty($u['is_teste'])): ?>
                                        <span class="badge-status" style="background:#e0e7ff; color:#3730a3; margin-left:2px;">Teste</span>
                                    <?php elseif ((float)$u['valor_mensal'] <= 0): ?>
                                        Teste / Grátis
                                    <?php else: ?>
                                        R$ <?= number_format($valorMensalList, 2, ',', '.') ?>/mês
                                    <?php endif; ?>
                                </div>
                                <div class="user-card-actions">
                                    <?php if (!$isAdm): ?>
                                        <button type="button"
                                            class="btn-action btn-renew"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalRenovar"
                                            onclick="prepararRenovacao(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>')"
                                            title="Renovar / Alterar Plano">
                                            <i class="bi bi-calendar-plus-fill"></i>
                                        </button>

                                        <button type="button"
                                            class="btn-action"
                                            style="background:#eef2ff; color:#3730a3;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditar"
                                            onclick="prepararEdicaoUsuario(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>', <?= !empty($u['is_teste']) ? 'true' : 'false' ?>)"
                                            title="Editar Usuário">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>

                                        <?php if (!empty($u['ativo'])): ?>
                                            <a href="?acao=inativar&id=<?= (int)$u['id'] ?>" class="btn-action" style="background:#fff3cd; color:#b45309" title="Bloquear Manualmente">
                                                <i class="bi bi-lock-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?acao=ativar&id=<?= (int)$u['id'] ?>" class="btn-action" style="background:#dcfce7; color:#15803d" title="Liberar Manualmente">
                                                <i class="bi bi-unlock-fill"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="?acao=excluir&id=<?= (int)$u['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Excluir usuário?')" title="Excluir">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-3 text-center text-muted small">
                        Nenhum usuário cadastrado ainda.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RESUMO DE INDICAÇÕES -->
        <?php if (!empty($comissoesIndicacao)): ?>
            <div class="content-card">
                <div class="card-header-custom">
                    <div>
                        <h5 class="mb-1 fw-bold" style="font-size:1.1rem; font-family: 'Outfit', sans-serif;">
                            <i class="bi bi-gift-fill me-2" style="color: var(--accent);"></i>
                            Resumo de Indicações
                        </h5>
                        <p class="text-muted mb-0" style="font-size:0.8rem;">
                            Comissões acumuladas por indicador (R$ 9,90 por mês)
                        </p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th><i class="bi bi-person-fill me-2"></i>Indicador</th>
                                <th class="text-end"><i class="bi bi-cash-stack me-2"></i>Total em Comissões</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comissoesIndicacao as $indicador => $valor): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($indicador) ?></td>
                                    <td class="text-end fw-bold" style="color: var(--success);">
                                        R$ <?= number_format($valor, 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL NOVO USUÁRIO -->
    <div class="modal fade" id="modalNovo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
                <div class="modal-header border-0 pb-2" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(236, 72, 153, 0.05));">
                    <h5 class="modal-title fw-bold" style="font-size:1.1rem; font-family: 'Outfit', sans-serif; color: var(--dark);">
                        <i class="bi bi-person-plus-fill me-2" style="color: var(--primary);"></i>
                        Novo Profissional
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem 2rem;">
                    <form method="post">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-person me-1"></i> Nome Completo
                            </label>
                            <input type="text" name="nome" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" required placeholder="Digite o nome">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-envelope me-1"></i> E-mail (login)
                            </label>
                            <input type="email" name="email" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" required placeholder="email@exemplo.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-key me-1"></i> Senha Inicial
                            </label>
                            <input type="text" name="senha" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" required placeholder="Defina uma senha">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-cash me-1"></i> Valor Mensal (R$)
                            </label>
                            <input type="text" name="valor_mensal" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" placeholder="19,90 (padrão)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-person-plus me-1"></i> Indicado por (opcional)
                            </label>
                            <input list="listaIndicadores" name="indicado_por" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" placeholder="Ex: Kátia Gomes, Luciana Aparecida">
                            <datalist id="listaIndicadores">
                                <option value="Katia Gomes">
                                <option value="Luciana Aparecida">
                            </datalist>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-calendar-check me-1"></i> Plano Inicial
                            </label>
                            <select name="plano_inicial" class="form-select" style="font-size:0.85rem; border-radius:14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;">
                                <option value="teste_7">🎁 Teste 7 dias (grátis / sem cobrança)</option>
                                <option value="30" selected>📅 30 Dias (Mensal)</option>
                                <option value="60">📅 60 Dias (Bimestral)</option>
                                <option value="90">📅 90 Dias (Trimestral)</option>
                                <option value="180">📅 6 Meses</option>
                                <option value="365">📅 1 Ano</option>
                                <option value="730">📅 2 Anos</option>
                                <option value="vitalicio">⭐ Acesso Vitalício (Sem expiração)</option>
                            </select>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="is_teste" id="is_teste" value="1">
                                <label class="form-check-label" for="is_teste" style="font-size:0.85rem; color:var(--dark);">
                                    <i class="bi bi-flask me-1"></i> Marcar como teste
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="bi bi-check-circle me-2"></i>Criar Conta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR USUÁRIO -->
    <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
                <div class="modal-header border-0 pb-2" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(236, 72, 153, 0.05));">
                    <h5 class="modal-title fw-bold" style="font-size:1.1rem; font-family: 'Outfit', sans-serif; color: var(--dark);">
                        <i class="bi bi-pencil-fill me-2" style="color: var(--primary);"></i>
                        Editar Profissional
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem 2rem;">
                    <form method="post" id="formEditarUsuario">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-person me-1"></i> Nome Completo
                            </label>
                            <input type="text" name="edit_nome" id="edit_nome" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-envelope me-1"></i> E-mail (login)
                            </label>
                            <input type="email" name="edit_email" id="edit_email" class="form-control" style="border-radius: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0;" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="edit_is_teste" id="edit_is_teste" value="1">
                            <label class="form-check-label fw-semibold" for="edit_is_teste" style="font-size: 0.85rem; color: var(--dark);">
                                <i class="bi bi-flask me-1"></i> Marcar como teste
                            </label>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary w-100">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
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
        function prepararRenovacao(id, nome) {
            document.getElementById('user_id_renew').value = id;
            document.getElementById('renew_user_name').textContent = nome;
        }

        function prepararEdicaoUsuario(id, nome, email, isTeste) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_is_teste').checked = !!isTeste;
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