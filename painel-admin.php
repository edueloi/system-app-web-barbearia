<?php
// painel-admin.php
session_start();

// =========================================================
// 1. CONFIGURAÇÕES E LÓGICA DE LOGIN
// =========================================================

$adminUser = 'Admin';
$adminPass = 'Edu@06051992';

// Constantes de negócio
const VALOR_BASE_MENSAL     = 19.90;
const COMISSAO_INDICACAO    = 9.90;      // por mês para quem indicou
const PERC_SOCIA_LUCIANA    = 0.10;      // 10%
const NOME_SOCIA_LUCIANA    = 'Luciana Aparecida';

// Verifica ambiente
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$painelAdminUrl = $isProd ? '/painel-admin' : $_SERVER['PHP_SELF'];

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
            $error = 'Credenciais inválidas.';
        }
    }
    // Tela de Login Admin (Design Clean)
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login | Salão Develoi</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary: #4f46e5;
                --primary-hover: #4338ca;
                --bg-body: #f3f4f6;
            }
            body {
                font-family: 'Inter', sans-serif;
                background: var(--bg-body);
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                font-size: 14px;
            }
            .login-card {
                background: white;
                padding: 2.2rem;
                border-radius: 22px;
                box-shadow: 0 16px 30px rgba(15,23,42,0.12);
                width: 100%;
                max-width: 380px;
            }
            .btn-primary {
                background: var(--primary);
                border: none;
                padding: 11px;
                border-radius: 999px;
                font-weight: 600;
                width: 100%;
                font-size: 0.9rem;
            }
            .btn-primary:hover { background: var(--primary-hover); }
            .form-control {
                padding: 11px;
                border-radius: 14px;
                border: 1px solid #e5e7eb;
                background: #f9fafb;
                font-size: 0.9rem;
            }
            .brand {
                color: var(--primary);
                font-weight: 800;
                font-size: 1.25rem;
                text-align: center;
                margin-bottom: 0.25rem;
                display: block;
                text-decoration: none;
            }
            .text-muted { font-size: 0.8rem; }
            label.form-label { font-size: 0.75rem; }
        </style>
    </head>
    <body>
        <div class="login-card">
            <a href="#" class="brand">Admin Panel</a>
            <p class="text-center text-muted mb-4">Acesso restrito à gestão</p>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 text-center small"><?= $error ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Usuário</label>
                    <input type="text" name="admin_user" class="form-control" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Senha</label>
                    <input type="password" name="admin_pass" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Entrar no Painel</button>
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
    $hoje = date('Y-m-d');
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
        header("Location: {$painelAdminUrl}?msg=deleted"); exit;
    }
    if ($_GET['acao'] === 'ativar') {
        $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?")->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=activated"); exit;
    }
    if ($_GET['acao'] === 'inativar') {
        $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?")->execute([$id]);
        header("Location: {$painelAdminUrl}?msg=deactivated"); exit;
    }
}

// --- AÇÕES POST (CRIAR E RENOVAR) ---
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CRIAR NOVO USUÁRIO
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $nome        = trim($_POST['nome'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $senha       = $_POST['senha'] ?? '';
        $plano       = $_POST['plano_inicial'] ?? '30'; // dias ou 'teste_7' ou 'vitalicio'
        $indicadoPor = trim($_POST['indicado_por'] ?? '');
        $valorMensal = isset($_POST['valor_mensal']) && $_POST['valor_mensal'] !== ''
            ? (float)str_replace(',', '.', $_POST['valor_mensal'])
            : VALOR_BASE_MENSAL;

        if ($nome && $email && $senha) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetchColumn() > 0) {
                $feedback = 'E-mail já cadastrado!';
            } else {
                $is_vitalicio   = 0;
                $data_expiracao = null;

                // TESTE GRÁTIS 7 DIAS (sem cobrança / sem comissão)
                if ($plano === 'teste_7') {
                    $dias = 7;
                    $data_expiracao = date('Y-m-d', strtotime("+$dias days"));
                    $indicadoPor    = null;
                    $valorMensal    = 0;

                // VITALÍCIO (sem expiração)
                } elseif ($plano === 'vitalicio') {
                    $is_vitalicio = 1;

                // PLANOS NORMAIS (30, 60, 90, 180, 365, 730)
                } else {
                    $dias = (int)$plano;
                    $data_expiracao = date('Y-m-d', strtotime("+$dias days"));
                }

                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql  = "INSERT INTO usuarios 
                         (nome, email, senha, ativo, is_vitalicio, data_expiracao, indicado_por, valor_mensal, criado_em)
                         VALUES (?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)";

                $pdo->prepare($sql)->execute([
                    $nome,
                    $email,
                    $hash,
                    1,
                    $is_vitalicio,
                    $data_expiracao,
                    $indicadoPor ?: null,
                    $valorMensal
                ]);

                header("Location: {$painelAdminUrl}?msg=created"); exit;
            }
        } else {
            $feedback = 'Preencha os campos obrigatórios.';
        }
    }

    // 2. RENOVAR / ALTERAR PLANO (NÃO cumulativo)
    if (isset($_POST['action']) && $_POST['action'] === 'renew') {
        $id_renovar     = (int)$_POST['user_id_renew'];
        $tipo_renovacao = $_POST['tipo_renovacao'] ?? '';

        if ($id_renovar > 0) {
            $uAtual = $pdo->prepare("SELECT data_expiracao, is_vitalicio FROM usuarios WHERE id = ?");
            $uAtual->execute([$id_renovar]);
            $atual = $uAtual->fetch();

            if ($atual) {
                $novo_vitalicio = (int)$atual['is_vitalicio'];
                $nova_data      = $atual['data_expiracao'];
                $ativo          = 1;

                // Tornar vitalício
                if ($tipo_renovacao === 'set_vitalicio') {
                    $novo_vitalicio = 1;
                    $nova_data      = null;

                // Renovar por período fixo (sempre a partir de HOJE)
                } else {
                    $novo_vitalicio = 0; // se renova com prazo, deixa de ser vitalício
                    $hojeData = date('Y-m-d');
                    $baseDate = $hojeData;

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
                    }
                }

                $pdo->prepare("UPDATE usuarios SET is_vitalicio = ?, data_expiracao = ?, ativo = ? WHERE id = ?")
                    ->execute([$novo_vitalicio, $nova_data, $ativo, $id_renovar]);

                header("Location: {$painelAdminUrl}?msg=renewed"); exit;
            }
        }
    }
}

// =========================================================
// 3. BUSCA, ESTATÍSTICAS E CÁLCULO FINANCEIRO
// =========================================================

$termo = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM usuarios 
        WHERE nome LIKE :t OR email LIKE :t OR IFNULL(estabelecimento,'') LIKE :t 
        ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':t' => "%$termo%"]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total    = count($usuarios);
$ativos   = 0;
$inativos = 0;

foreach ($usuarios as $u) {
    if (!empty($u['ativo'])) $ativos++; else $inativos++;
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
    $luciana10 = $valorBase * PERC_SOCIA_LUCIANA;
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

$totalComissaoIndic   = array_sum($comissoesIndicacao);
$lucianaSoIndicacoes  = $comissoesIndicacao[NOME_SOCIA_LUCIANA] ?? 0.0;
$lucianaSaldoFinal    = $luciana10Total + $lucianaSoIndicacoes;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | Gestão</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f3f4f6;
            --text-main: #111827;
            --text-muted: #6b7280;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            padding-bottom: 40px;
            font-size: 14px; /* letras menores, estilo app */
        }

        .top-nav {
            background: white;
            padding: 0.75rem 0;
            box-shadow: 0 1px 3px rgba(15,23,42,0.08);
            margin-bottom: 1.4rem;
        }
        .brand-logo {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon {
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1rem 1.2rem;
            box-shadow: 0 10px 20px rgba(15,23,42,0.06);
            transition: transform 0.18s;
            height: 100%;
            border: 1px solid rgba(148,163,184,0.1);
        }
        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(15,23,42,0.08);
        }
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
            margin-top: 4px;
        }
        .stat-card .small { font-size: 0.7rem; }

        .content-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 14px 28px rgba(15,23,42,0.08);
            overflow: hidden;
            margin-bottom: 1.3rem;
            border: 1px solid rgba(148,163,184,0.16);
        }
        .card-header-custom {
            padding: 1rem 1.3rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
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
            padding: 0.25em 0.7em;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            display:inline-flex;
            align-items:center;
            gap:4px;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f3f4f6; color: #374151; }

        .badge-vitalicio { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-time      { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
        .badge-expired   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .btn-action {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.18s;
            border: none;
            font-size: 0.9rem;
        }
        .btn-renew { background: #e0f2fe; color: #0284c7; }
        .btn-renew:hover { background: #bae6fd; }
        .btn-delete { background: #fee2e2; color: #991b1b; }

        .toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1050;
        }

        .pill {
            border-radius: 999px;
            padding: 0.2rem 0.7rem;
            font-size: 0.7rem;
        }

        /* ---------- MOBILE / ESTILO APP ---------- */
        .user-card {
            border-radius: 22px;
            border: 1px solid rgba(148,163,184,0.25);
            padding: 0.9rem 0.95rem 0.7rem;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15,23,42,0.07);
            margin-bottom: 0.85rem;
        }
        .user-card-header {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .user-card-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .user-card-email {
            font-size: 0.76rem;
            color: var(--text-muted);
        }
        .user-card-badges {
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
        }
        .user-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.55rem;
            padding-top: 0.45rem;
            border-top: 1px dashed #e5e7eb;
        }
        .user-card-plan {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .user-card-actions .btn-action {
            margin-left: 0.25rem;
        }

        .btn-primary {
            font-size: 0.8rem;
            border-radius: 999px;
        }

        @media (max-width: 767.98px) {
            body {
                padding-bottom: 70px;
            }
            .container {
                padding-left: 0.9rem;
                padding-right: 0.9rem;
            }
            .content-card {
                border-radius: 26px;
                margin-left: -0.1rem;
                margin-right: -0.1rem;
            }
            .card-header-custom {
                padding: 0.9rem 1rem;
            }
            .stat-card {
                padding: 0.9rem 1rem;
                border-radius: 22px;
            }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="#" class="brand-logo">
                <div class="brand-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <span>Painel Admin</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-block text-end lh-1">
                    <span class="d-block fw-semibold" style="font-size:0.8rem;">Administrador</span>
                    <span class="text-muted" style="font-size: 0.7rem;">Logado</span>
                </div>
                <a href="?logout=1" class="btn btn-outline-secondary btn-sm rounded-pill px-3" style="font-size:0.75rem;">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($feedback): ?>
            <div class="alert alert-warning rounded-4 shadow-sm mb-3" style="font-size:0.8rem;"><?= htmlspecialchars($feedback) ?></div>
        <?php endif; ?>

        <!-- MÉTRICAS PRINCIPAIS -->
        <div class="row g-3 g-md-4 mb-3 mb-md-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Total Usuários</div>
                    <div class="stat-value"><?= $total ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-people-fill"></i> Base total</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Ativos</div>
                    <div class="stat-value text-success"><?= $ativos ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-check-circle-fill text-success"></i> Acessando</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Bloqueados</div>
                    <div class="stat-value text-secondary"><?= $inativos ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-dash-circle-fill text-secondary"></i> Restritos</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Receita Histórica</div>
                    <div class="stat-value">R$ <?= number_format($receitaTotal, 2, ',', '.') ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-cash-coin"></i> Desde o início</div>
                </div>
            </div>
        </div>

        <!-- MÉTRICAS FINANCEIRAS DETALHES -->
        <div class="row g-3 g-md-4 mb-3 mb-md-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Comissões de Indicação</div>
                    <div class="stat-value text-primary">R$ <?= number_format($totalComissaoIndic, 2, ',', '.') ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-people"></i> Somando todos</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Sócia Luciana</div>
                    <div class="stat-value text-warning">R$ <?= number_format($lucianaSaldoFinal, 2, ',', '.') ?></div>
                    <div class="small text-muted mt-1">
                        10% (R$ <?= number_format($luciana10Total, 2, ',', '.') ?>)
                        <?php if ($lucianaSoIndicacoes > 0): ?>
                            + indicações (R$ <?= number_format($lucianaSoIndicacoes, 2, ',', '.') ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="text-uppercase text-muted small fw-bold">Lucro do Sistema</div>
                    <div class="stat-value text-success">R$ <?= number_format($lucroSistema, 2, ',', '.') ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-graph-up-arrow"></i> Receita - Luciana - Indicações</div>
                </div>
            </div>
        </div>

        <!-- TABELA / CARDS PRINCIPAIS -->
        <div class="content-card">
            <div class="card-header-custom">
                <div>
                    <h6 class="mb-1 fw-bold" style="font-size:0.95rem;">Gerenciar Assinaturas</h6>
                    <p class="text-muted mb-0" style="font-size:0.75rem;">Controle a validade, indicação e acesso dos profissionais.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary"
                            style="background:var(--primary); border:none; padding:0.45rem 1.1rem;"
                            data-bs-toggle="modal" data-bs-target="#modalNovo">
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
                                            $labelValidade  = $diasRestantes . ' dias restantes (' . $dataExp->format('d/m/Y') . ')';
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
                                            <div class="fw-semibold text-dark" style="font-size:0.85rem;"><?= htmlspecialchars($u['nome']) ?></div>
                                            <div class="small text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($u['email']) ?></div>
                                            <div class="small text-muted" style="font-size:0.7rem;">
                                                Plano:
                                                <?php if ((float)$u['valor_mensal'] <= 0): ?>
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
                                        <span class="badge-status status-active"><i class="bi bi-circle-fill" style="font-size:5px"></i> Ativo</span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive"><i class="bi bi-slash-circle"></i> Bloqueado</span>
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
                        <?php if (empty($usuarios)): ?>
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
                                    <span class="badge-status status-active"><i class="bi bi-circle-fill" style="font-size:5px"></i> Ativo</span>
                                <?php else: ?>
                                    <span class="badge-status status-inactive"><i class="bi bi-slash-circle"></i> Bloqueado</span>
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
                                    <?php if ((float)$u['valor_mensal'] <= 0): ?>
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
                    <h6 class="mb-1 fw-bold" style="font-size:0.9rem;">Resumo de Indicações</h6>
                    <p class="text-muted mb-0" style="font-size:0.75rem;">Quanto cada pessoa tem a receber pelas indicações (R$ 9,90 por mês de permanência).</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th class="text-end">Total em Comissões</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comissoesIndicacao as $indicador => $valor): ?>
                            <tr>
                                <td><?= htmlspecialchars($indicador) ?></td>
                                <td class="text-end">R$ <?= number_format($valor, 2, ',', '.') ?></td>
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
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="font-size:0.95rem;">Novo Profissional</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted">Nome</label>
                            <input type="text" name="nome" class="form-control bg-light" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted">E-mail (login)</label>
                            <input type="email" name="email" class="form-control bg-light" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted">Senha inicial</label>
                            <input type="text" name="senha" class="form-control bg-light" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted">Valor mensal cobrado (R$)</label>
                            <input type="text" name="valor_mensal" class="form-control bg-light" placeholder="19,90 (padrão)">
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold text-muted">Indicado por quem? (opcional)</label>
                            <input list="listaIndicadores" name="indicado_por" class="form-control bg-light" placeholder="Ex: Kátia Gomes, Luciana Aparecida">
                            <datalist id="listaIndicadores">
                                <option value="Katia Gomes">
                                <option value="Luciana Aparecida">
                            </datalist>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Plano Inicial</label>
                            <select name="plano_inicial" class="form-select bg-light" style="font-size:0.8rem; border-radius:14px;">
                                <option value="teste_7">Teste 7 dias (grátis / sem cobrança)</option>
                                <option value="30" selected>30 Dias (Mensal)</option>
                                <option value="60">60 Dias (Bimestral)</option>
                                <option value="90">90 Dias (Trimestral)</option>
                                <option value="180">6 Meses</option>
                                <option value="365">1 Ano</option>
                                <option value="730">2 Anos</option>
                                <option value="vitalicio">⭐ Acesso Vitalício (Sem contador / sem comissão)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Criar Conta</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL RENOVAR -->
    <div class="modal fade" id="modalRenovar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="font-size:0.95rem;">Renovar Acesso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Adicionar tempo para: <strong id="renew_user_name" class="text-dark"></strong></p>
                    <form method="post">
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="user_id_renew" id="user_id_renew">

                        <div class="d-grid gap-2">
                            <button type="submit" name="tipo_renovacao" value="add_30" class="btn btn-outline-primary text-start" style="font-size:0.8rem; border-radius:999px;">
                                <i class="bi bi-plus-circle me-2"></i> + 30 Dias (Mensal)
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_60" class="btn btn-outline-primary text-start" style="font-size:0.8rem; border-radius:999px;">
                                <i class="bi bi-plus-circle me-2"></i> + 60 Dias (Bimestral)
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_90" class="btn btn-outline-primary text-start" style="font-size:0.8rem; border-radius:999px;">
                                <i class="bi bi-plus-circle me-2"></i> + 90 Dias (Trimestral)
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_365" class="btn btn-outline-primary text-start" style="font-size:0.8rem; border-radius:999px;">
                                <i class="bi bi-calendar-check me-2"></i> + 1 Ano (Anual)
                            </button>
                            <button type="submit" name="tipo_renovacao" value="add_730" class="btn btn-outline-primary text-start" style="font-size:0.8rem; border-radius:999px;">
                                <i class="bi bi-calendar-check me-2"></i> + 2 Anos
                            </button>
                            <div class="border-top my-2"></div>
                            <button type="submit" name="tipo_renovacao" value="set_vitalicio" class="btn btn-warning text-dark fw-bold text-start" style="font-size:0.8rem; border-radius:999px;">
                                <i class="bi bi-star-fill me-2"></i> Tornar Vitalício (Sem expiração / sem contador)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="toast-container">
        <div class="toast show border-0 shadow-lg rounded-4" role="alert" style="border-left: 4px solid #10b981;">
            <div class="toast-body d-flex align-items-center gap-2" style="font-size:0.8rem;">
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <div>
                    <strong class="d-block text-dark" style="font-size:0.8rem;">Sucesso!</strong>
                    <small class="text-muted">Operação realizada.</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
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

        setTimeout(() => {
            const toast = document.querySelector('.toast');
            if (toast) {
                var bsToast = new bootstrap.Toast(toast);
                bsToast.hide();
            }
        }, 4000);
    </script>
</body>
</html>
