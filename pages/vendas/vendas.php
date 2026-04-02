<?php
// pages/vendas/vendas.php

require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$loginUrl = $isProd ? '/login' : '/karen_site/controle-salao/login.php';
$logoutUrl = $isProd ? '/logout' : '/karen_site/controle-salao/logout.php';
$dashboardUrl = $isProd ? '/dashboard' : '/karen_site/controle-salao/pages/dashboard.php';
$painelAdminUrl = $isProd ? '/painel-admin' : '/karen_site/controle-salao/painel-admin.php';

if (isset($_SESSION['admin_logged_in']) && !isset($_SESSION['vendedor_id'])) {
    header("Location: {$painelAdminUrl}");
    exit;
}

if (isset($_SESSION['user_id'])) {
    header("Location: {$dashboardUrl}");
    exit;
}

if (!isset($_SESSION['vendedor_id'])) {
    header("Location: {$loginUrl}");
    exit;
}

$vendedorId = (int)$_SESSION['vendedor_id'];
$vendedorNome = $_SESSION['vendedor_nome'] ?? 'Vendedor';
$vendedorCodigo = $_SESSION['vendedor_codigo'] ?? '';

$feedback = '';
$feedbackType = 'success';

// Planos e valores
$planos = [
    'mensal' => ['meses' => 1, 'valor' => 69.90, 'label' => 'Mensal (R$ 69,90)'],
    'trimestral' => ['meses' => 3, 'valor' => 160.00, 'label' => '3 meses (R$ 160,00)'],
    'anual' => ['meses' => 12, 'valor' => 680.00, 'label' => '12 meses (R$ 680,00)'],
    'vitalicio' => ['meses' => 0, 'valor' => 1600.00, 'label' => 'Vitalício (R$ 1.600,00)'],
    'custom' => ['meses' => 0, 'valor' => 0, 'label' => 'Customizado'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_sale') {
    $nome = trim($_POST['cliente_nome'] ?? '');
    $email = trim($_POST['cliente_email'] ?? '');
    $telefone = trim($_POST['cliente_telefone'] ?? '');
    $cpf = trim($_POST['cliente_cpf'] ?? '');
    $estabelecimento = trim($_POST['cliente_estabelecimento'] ?? '');
    $senha = $_POST['cliente_senha'] ?? '';
    $plano = $_POST['plano'] ?? 'mensal';
    $mesesCustom = (int)($_POST['meses_custom'] ?? 0);
    $valorCustom = isset($_POST['valor_custom']) ? (float)str_replace(',', '.', $_POST['valor_custom']) : 0;
    $descontoPercent = (int)($_POST['desconto_percent'] ?? 0);
    $entradaValor = isset($_POST['entrada_valor']) ? (float)str_replace(',', '.', $_POST['entrada_valor']) : 0;
    $descontoEntrada = isset($_POST['desconto_entrada']) ? (float)str_replace(',', '.', $_POST['desconto_entrada']) : 0;
    $metodoPagamento = trim($_POST['metodo_pagamento'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome && $email && $senha) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $feedback = 'E-mail já cadastrado no sistema.';
            $feedbackType = 'danger';
        } else {
            if (!isset($planos[$plano])) {
                $plano = 'mensal';
            }

            $meses = $planos[$plano]['meses'];
            $valorPlano = $planos[$plano]['valor'];

            if ($plano === 'custom') {
                $meses = max(1, $mesesCustom);
                $valorPlano = max(0, $valorCustom);
            }

            $descontoPercent = in_array($descontoPercent, [0, 5, 10, 15, 20, 25, 30], true) ? $descontoPercent : 0;
            $descontoValor = ($valorPlano * $descontoPercent) / 100;
            $valorTotal = max(0, $valorPlano - $descontoValor);

            $descontoEntrada = max(0, $descontoEntrada);
            $entradaValor = max(0, $entradaValor);
            if ($descontoEntrada > $entradaValor) {
                $descontoEntrada = $entradaValor;
            }
            $entradaFinal = max(0, $entradaValor - $descontoEntrada);

            $isVitalicio = ($plano === 'vitalicio') ? 1 : 0;
            $dataExpiracao = null;
            $valorMensal = $isVitalicio ? 0 : 69.90;

            if (!$isVitalicio) {
                $dataExp = new DateTime();
                $dataExp->modify('+' . $meses . ' months');
                $dataExpiracao = $dataExp->format('Y-m-d');
            }

            $comissaoMensal = 0.0;
            $comissaoTotal = 0.0;
            if ($isVitalicio) {
                $comissaoTotal = 600.00;
            } else {
                $comissaoMensal = 9.90;
                $comissaoTotal = $comissaoMensal * $meses;
            }

            try {
                $pdo->beginTransaction();

                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios 
                    (nome, email, telefone, cpf, estabelecimento, senha, ativo, is_vitalicio, data_expiracao, valor_mensal, vendedor_id, criado_em)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)");
                $stmt->execute([
                    $nome,
                    $email,
                    $telefone ?: null,
                    $cpf ?: null,
                    $estabelecimento ?: null,
                    $hash,
                    1,
                    $isVitalicio,
                    $dataExpiracao,
                    $valorMensal,
                    $vendedorId
                ]);

                $usuarioId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO vendas_assinaturas 
                    (vendedor_id, usuario_id, plano_tipo, meses, valor_plano, desconto_percent, desconto_valor, valor_total, entrada_valor, desconto_entrada, entrada_valor_final, metodo_pagamento, comissao_mensal, comissao_total, observacoes, criado_em)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)");
                $stmt->execute([
                    $vendedorId,
                    $usuarioId,
                    $plano,
                    $meses,
                    $valorPlano,
                    $descontoPercent,
                    $descontoValor,
                    $valorTotal,
                    $entradaValor,
                    $descontoEntrada,
                    $entradaFinal,
                    $metodoPagamento,
                    $comissaoMensal,
                    $comissaoTotal,
                    $observacoes ?: null
                ]);

                $pdo->commit();
                $feedback = 'Cliente cadastrado e venda registrada com sucesso.';
                $feedbackType = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback = 'Erro ao registrar a venda. Tente novamente.';
                $feedbackType = 'danger';
            }
        }
    } else {
        $feedback = 'Preencha nome, e-mail e senha do cliente.';
        $feedbackType = 'danger';
    }
}

// Dados do vendedor
$stmtVend = $pdo->prepare("SELECT * FROM vendedores WHERE id = ? LIMIT 1");
$stmtVend->execute([$vendedorId]);
$vendedor = $stmtVend->fetch(PDO::FETCH_ASSOC);

// Vendas e clientes do vendedor
$stmtVendas = $pdo->prepare("SELECT * FROM vendas_assinaturas WHERE vendedor_id = ? ORDER BY id DESC");
$stmtVendas->execute([$vendedorId]);
$vendas = $stmtVendas->fetchAll(PDO::FETCH_ASSOC);

$stmtClientes = $pdo->prepare("SELECT * FROM usuarios WHERE vendedor_id = ? ORDER BY id DESC");
$stmtClientes->execute([$vendedorId]);
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
$clientesById = [];
foreach ($clientes as $cliente) {
    $clientesById[$cliente['id']] = $cliente;
}

$ultimaVendaPorCliente = [];
$comissaoTotalAcumulada = 0.0;
foreach ($vendas as $venda) {
    if (isset($clientesById[$venda['usuario_id']]) && !empty($clientesById[$venda['usuario_id']]['ativo'])) {
        $comissaoTotalAcumulada += (float)$venda['comissao_total'];
    }
    if (!isset($ultimaVendaPorCliente[$venda['usuario_id']])) {
        $ultimaVendaPorCliente[$venda['usuario_id']] = $venda;
    }
}

$totalClientes = count($clientes);
$ativos = 0;
$vencendo = 0;
$comissaoMensalPrevista = 0.0;
$hoje = new DateTime();

foreach ($clientes as $cliente) {
    if (!empty($cliente['ativo'])) {
        $ativos++;
    }

    $dataExp = !empty($cliente['data_expiracao']) ? new DateTime($cliente['data_expiracao']) : null;
    if ($dataExp && $dataExp >= $hoje) {
        $diff = $hoje->diff($dataExp)->days;
        if ($diff <= 15) {
            $vencendo++;
        }
    }

    $ultima = $ultimaVendaPorCliente[$cliente['id']] ?? null;
    if ($ultima && empty($cliente['is_vitalicio']) && !empty($cliente['ativo'])) {
        $comissaoMensalPrevista += (float)$ultima['comissao_mensal'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Vendedor | Develoi Gestão</title>

    <link rel="icon" type="image/svg+xml" href="../../favicon.svg">
    <link rel="icon" href="../../favicon.ico" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --slate: #64748b;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--dark);
            min-height: 100vh;
        }

        /* HEADER */
        .vendas-header {
            background: var(--dark);
            padding: 2.5rem 0;
            color: #fff;
            margin-bottom: -2rem;
            border-bottom: 4px solid var(--primary);
        }

        /* STAT CARDS */
        .stat-card {
            background: var(--card);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-card:hover { transform: translateY(-3px); }

        .stat-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 1rem;
        }

        .stat-success { border-left: 5px solid var(--success); }
        .stat-warning { border-left: 5px solid var(--warning); }
        .stat-danger { border-left: 5px solid var(--danger); }
        .stat-primary { border-left: 5px solid var(--primary); }

        /* GLASS PANEL/CONTAINER */
        .glass-panel {
            background: var(--card);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid var(--border);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        /* BUTTONS */
        .btn-premium {
            background: var(--primary);
            color: #fff;
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }
        .btn-premium:hover { background: #4338ca; transform: translateY(-1px); color: #fff; }

        /* TABLE CUSTOM */
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-modern th {
            padding: 1rem; color: var(--slate); font-size: 0.75rem; 
            text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        .table-modern td { padding: 1.2rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .table-modern tr:last-child td { border-bottom: none; }

        .badge-premium {
            padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700;
        }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-vitalicio { background: #fef3c7; color: #92400e; }

        /* MOBILE CARDS */
        .mobile-user-card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
            display: none;
        }

        .mobile-user-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem; }
        .mobile-user-info { margin-bottom: 1rem; font-size: 0.85rem; color: var(--slate); }
        .mobile-user-footer { border-top: 1px solid var(--border); padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; }

        @media (max-width: 991px) {
            .table-modern thead { display: none; }
            .table-modern tbody tr { display: none; }
            .mobile-user-card { display: block; }
        }

        /* MODAL STEPS */
        .step-dot { width: 40px; height: 8px; border-radius: 4px; background: var(--border); transition: 0.3s; }
        .step-dot.active { background: var(--primary); }

        .form-control, .form-select { border-radius: 10px; padding: 10px 14px; border: 1px solid var(--border); }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        
        .hidden { display: none !important; }

        .chart-bars { display: grid; gap: 12px; margin-top: 15px; }
        .chart-row { display: grid; grid-template-columns: 80px 1fr 120px; gap: 15px; align-items: center; font-size: 0.85rem; }
        .chart-bar-container { background: #f1f5f9; height: 10px; border-radius: 999px; overflow: hidden; }
        .chart-bar { height: 100%; background: var(--primary); border-radius: 999px; }
    </style>
</head>
<body>
    <header class="vendas-header">
        <div class="container text-center text-md-start">
            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
                <div>
                    <h2 class="fw-bold mb-1" style="font-family:'Outfit',sans-serif; letter-spacing:-1px;">Olá, <?= htmlspecialchars($vendedorNome) ?> 👋</h2>
                    <p class="mb-0 opacity-75">Bem-vindo à sua central de vendas. Vamos bater as metas de hoje?</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-md-end d-none d-md-block">
                        <div class="small opacity-75 text-uppercase fw-bold">Seu Código</div>
                        <div class="fw-bold fs-5 text-primary-light"><?= htmlspecialchars($vendedorCodigo) ?></div>
                    </div>
                    <a href="<?= $logoutUrl ?>" class="btn btn-outline-light border-0 px-4 py-2" style="background: rgba(255,255,255,0.1);">Sair</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container py-4" style="margin-top: 1rem;">
        <?php if ($feedback): ?>
            <div class="alert alert-<?= $feedbackType ?> alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($feedback) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-5">
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-primary">
                    <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">Clientes Totais</div>
                        <div class="fs-3 fw-extrabold"><?= $totalClientes ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-success">
                    <div class="stat-icon" style="background: #dcfce7; color: #059669;"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">Ativos</div>
                        <div class="fs-3 fw-extrabold"><?= $ativos ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-warning">
                    <div class="stat-icon" style="background: #fef3c7; color: #d97706;"><i class="bi bi-clock-fill"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">Vencendo</div>
                        <div class="fs-3 fw-extrabold"><?= $vencendo ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card stat-primary">
                    <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">Rend. Previsto</div>
                        <div class="fs-4 fw-extrabold text-primary">R$ <?= number_format($comissaoMensalPrevista, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-panel">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h4 class="fw-bold mb-0" style="font-family:'Outfit',sans-serif;">Minha Carteira</h4>
                            <p class="text-muted small mb-0">Gestão de clientes e assinaturas</p>
                        </div>
                        <button class="btn btn-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
                            <i class="bi bi-plus-lg"></i> Nova Venda
                        </button>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0" id="filtroBusca" placeholder="Pesquisar cliente ou e-mail...">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select class="form-select" id="filtroStatus">
                                <option value="todos">Status: Todos</option>
                                <option value="ativo">Ativos</option>
                                <option value="inativo">Inativos</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <select class="form-select" id="filtroVencimento">
                                <option value="todos">Vencimento: Todos</option>
                                <option value="vencendo">Vencendo (15 dias)</option>
                                <option value="vencido">Vencidos</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="clientesTabela">
                            <thead>
                                <tr>
                                    <th>Profissional</th>
                                    <th>Status</th>
                                    <th class="d-none d-lg-table-cell">Validade</th>
                                    <th class="d-none d-lg-table-cell">Últ. Pagto</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($clientes)): ?>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <?php
                                        $ultima = $ultimaVendaPorCliente[$cliente['id']] ?? null;
                                        $dataExp = !empty($cliente['data_expiracao']) ? new DateTime($cliente['data_expiracao']) : null;
                                        $diasRestantes = $dataExp ? (int)$hoje->diff($dataExp)->format('%r%a') : null;
                                        $statusBase = !empty($cliente['ativo']) ? 'ativo' : 'inativo';
                                        if (!empty($cliente['is_vitalicio'])) {
                                            $statusBase = 'vitalicio';
                                        }
                                        $vencStatus = 'em_dia';
                                        if (!empty($cliente['is_vitalicio'])) {
                                            $vencStatus = 'vitalicio';
                                        } elseif ($dataExp) {
                                            if ($diasRestantes !== null && $diasRestantes < 0) {
                                                $vencStatus = 'vencido';
                                            } elseif ($diasRestantes !== null && $diasRestantes <= 15) {
                                                $vencStatus = 'vencendo';
                                            }
                                        }
                                        $ultimaData = $ultima ? date('Y-m-d', strtotime($ultima['criado_em'])) : '';
                                        $telefoneDigits = preg_replace('/\D+/', '', $cliente['telefone'] ?? '');
                                        if ($telefoneDigits !== '' && strlen($telefoneDigits) <= 11) {
                                            $telefoneDigits = '55' . $telefoneDigits;
                                        }
                                        $dataExpTexto = $dataExp ? $dataExp->format('d/m/Y') : '-';
                                        $msgVencendo = "Ola " . ($cliente['nome'] ?? '') . ", sua assinatura vence em " . $dataExpTexto . ". Para continuar ativo no Develoi, precisamos do pagamento. Obrigado!";
                                        $msgVencido = "Ola " . ($cliente['nome'] ?? '') . ", sua assinatura venceu em " . $dataExpTexto . ". Ficamos felizes em continuar com voce. Para reativar, precisamos do pagamento. Obrigado!";
                                        $msgFinal = $vencStatus === 'vencido' ? $msgVencido : $msgVencendo;
                                        $linkWpp = $telefoneDigits !== '' ? "https://wa.me/" . $telefoneDigits . "?text=" . rawurlencode($msgFinal) : '';
                                        ?>
                                        <tr data-nome="<?= htmlspecialchars(mb_strtolower($cliente['nome'])) ?>"
                                            data-email="<?= htmlspecialchars(mb_strtolower($cliente['email'])) ?>"
                                            data-status="<?= $statusBase ?>"
                                            data-vencimento="<?= $vencStatus ?>">
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($cliente['nome']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($cliente['email']) ?></div>
                                            </td>
                                            <td>
                                                <?php if ($statusBase === 'ativo'): ?>
                                                    <span class="badge-premium badge-active">Ativo</span>
                                                <?php elseif ($statusBase === 'vitalicio'): ?>
                                                    <span class="badge-premium badge-vitalicio">Vitalício</span>
                                                <?php else: ?>
                                                    <span class="badge-premium badge-inactive">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <div class="fw-bold"><?= $dataExpTexto ?></div>
                                                <?php if ($vencStatus === 'vencendo'): ?>
                                                    <span class="text-warning small fw-bold">Vence em <?= $diasRestantes ?> dias</span>
                                                <?php elseif ($vencStatus === 'vencido'): ?>
                                                    <span class="text-danger small fw-bold">Vencido</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-lg-table-cell">
                                                <?= $ultima ? date('d/m/Y', strtotime($ultima['criado_em'])) : '-' ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($linkWpp): ?>
                                                    <a href="<?= $linkWpp ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                        <i class="bi bi-whatsapp"></i> Cobrar
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Sem contato</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">Nenhum cliente encontrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE CARDS -->
                    <div class="d-lg-none mt-3" id="mobileCardsContainer">
                        <?php foreach ($clientes as $cliente): ?>
                            <?php 
                            // Redefining vars for the specific mobile loop to avoid leak context
                            $ultima = $ultimaVendaPorCliente[$cliente['id']] ?? null;
                            $dataExp = !empty($cliente['data_expiracao']) ? new DateTime($cliente['data_expiracao']) : null;
                            $diasRestantes = $dataExp ? (int)$hoje->diff($dataExp)->format('%r%a') : null;
                            $statusBase = !empty($cliente['ativo']) ? 'ativo' : 'inativo';
                            if (!empty($cliente['is_vitalicio'])) $statusBase = 'vitalicio';
                            $vencStatus = 'em_dia';
                            if (!empty($cliente['is_vitalicio'])) { $vencStatus = 'vitalicio'; } 
                            elseif ($dataExp) {
                                if ($diasRestantes !== null && $diasRestantes < 0) $vencStatus = 'vencido';
                                elseif ($diasRestantes !== null && $diasRestantes <= 15) $vencStatus = 'vencendo';
                            }
                            $telefoneDigits = preg_replace('/\D+/', '', $cliente['telefone'] ?? '');
                            if ($telefoneDigits !== '' && strlen($telefoneDigits) <= 11) $telefoneDigits = '55' . $telefoneDigits;
                            $linkWpp = $telefoneDigits !== '' ? "https://wa.me/" . $telefoneDigits . "?text=" . rawurlencode($vencStatus === 'vencido' ? "Ola " . ($cliente['nome'] ?? '') . ", sua assinatura venceu em " . ($dataExp ? $dataExp->format('d/m/Y') : '-') . ". Para reativar, precisamos do pagamento." : "Ola " . ($cliente['nome'] ?? '') . ", sua assinatura vence em " . ($dataExp ? $dataExp->format('d/m/Y') : '-') . ". Para continuar ativo, precisamos do pagamento.") : '';
                            ?>
                            <div class="mobile-user-card" 
                                 data-nome="<?= htmlspecialchars(mb_strtolower($cliente['nome'])) ?>"
                                 data-email="<?= htmlspecialchars(mb_strtolower($cliente['email'])) ?>"
                                 data-status="<?= $statusBase ?>"
                                 data-vencimento="<?= $vencStatus ?>">
                                <div class="mobile-user-header">
                                    <div>
                                        <div class="fw-bold fs-6"><?= htmlspecialchars($cliente['nome']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($cliente['email']) ?></div>
                                    </div>
                                    <span class="badge-premium <?= $statusBase === 'ativo' ? 'badge-active' : ($statusBase === 'vitalicio' ? 'badge-vitalicio' : 'badge-inactive') ?>">
                                        <?= $statusBase === 'ativo' ? 'Ativo' : ($statusBase === 'vitalicio' ? 'Vitalício' : 'Inativo') ?>
                                    </span>
                                </div>
                                <div class="mobile-user-info">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Validade:</span>
                                        <span class="fw-bold <?= $vencStatus === 'vencido' ? 'text-danger' : ($vencStatus === 'vencendo' ? 'text-warning' : '') ?>">
                                            <?= $dataExp ? $dataExp->format('d/m/Y') : '-' ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Último Pagto:</span>
                                        <span><?= $ultima ? date('d/m/Y', strtotime($ultima['criado_em'])) : '-' ?></span>
                                    </div>
                                </div>
                                <div class="mobile-user-footer">
                                    <?php if ($linkWpp): ?>
                                        <a href="<?= $linkWpp ?>" target="_blank" class="btn btn-premium btn-sm w-100 py-2">
                                            <i class="bi bi-whatsapp me-2"></i> Cobrar Cliente
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm w-100 disabled">Sem Telefone</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-4">
                        <div class="small text-muted" id="clientesResumo">0 resultados</div>
                        <div class="d-flex gap-2" id="clientesPaginacao"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-panel">
                    <h5 class="fw-bold mb-4" style="font-family:'Outfit',sans-serif;">Performance Direta</h5>
                    
                    <div class="p-3 rounded-4 mb-3" style="background: #f8fafc; border: 1px solid var(--border);">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Comissão Acumulada</div>
                        <div class="fs-2 fw-extrabold text-primary">R$ <?= number_format($comissaoTotalAcumulada, 2, ',', '.') ?></div>
                        <div class="small text-muted mt-1">Ganhos totais confirmados</div>
                    </div>

                    <div class="p-3 rounded-4" style="background: #f8fafc; border: 1px solid var(--border);">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Potencial Mensal</div>
                        <div class="fs-3 fw-bold">R$ <?= number_format($comissaoMensalPrevista, 2, ',', '.') ?></div>
                        <div class="small text-muted mt-1">Recorrência dos clientes ativos</div>
                    </div>
                </div>

                <div class="glass-panel mt-4">
                    <h5 class="fw-bold mb-3" style="font-family:'Outfit',sans-serif;"><i class="bi bi-bullseye me-2 text-primary"></i>Simulador de Metas</h5>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Clientes ativos atuais</label>
                        <input type="number" class="form-control" id="metaClientes" value="<?= $ativos ?>" min="0">
                    </div>
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Novos /mês</label>
                            <input type="number" class="form-control" id="metaNovos" value="5" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Proj. Meses</label>
                            <input type="number" class="form-control" id="metaMeses" value="6" min="1">
                        </div>
                    </div>
                    <input type="hidden" id="metaComissao" value="9.90">

                    <div class="p-3 rounded-4 bg-primary text-white">
                        <div class="small opacity-75">Renda Mensal no final do período:</div>
                        <div class="fs-3 fw-bold" id="metaMensalFinal">R$ 0,00</div>
                    </div>

                    <div class="mt-4">
                        <div class="small text-muted fw-bold text-uppercase mb-2">Projeção Acumulada</div>
                        <div id="metaProjecao" class="chart-bars"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL NOVA VENDA -->
    <div class="modal fade" id="modalNovaVenda" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:28px; overflow:hidden;">
                <div class="modal-header bg-dark text-white p-4">
                    <h5 class="modal-title fw-bold" style="font-family:'Outfit',sans-serif;"><i class="bi bi-cart-plus me-2"></i>Registrar Nova Venda</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 p-md-5">
                    <form method="post" id="formNovaVenda">
                        <input type="hidden" name="action" value="create_sale">
                        
                        <!-- Step Indicators -->
                        <div class="d-flex justify-content-center gap-2 mb-5">
                            <div class="step-dot active" id="stepDot1"></div>
                            <div class="step-dot" id="stepDot2"></div>
                        </div>

                        <!-- Step 1: Client Data -->
                        <div id="step1">
                            <h6 class="fw-extrabold text-uppercase small text-primary mb-4">1. Dados do Profissional</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nome Completo</label>
                                    <input type="text" name="cliente_nome" class="form-control" placeholder="Ex: João Silva" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-mail de Acesso</label>
                                    <input type="email" name="cliente_email" class="form-control" placeholder="email@exemplo.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp / Telefone</label>
                                    <input type="text" name="cliente_telefone" class="form-control" id="clienteTelefone" placeholder="(00) 00000-0000">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Senha Temporária</label>
                                    <input type="password" name="cliente_senha" class="form-control" required placeholder="Defina uma senha">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Nome do Salão / Barbearia (Opcional)</label>
                                    <input type="text" name="cliente_estabelecimento" class="form-control" placeholder="Ex: Studio VIP">
                                </div>
                            </div>
                            <div class="mt-5 text-end">
                                <button type="button" class="btn btn-premium px-5" id="btnAvancar">Próximo Passo <i class="bi bi-arrow-right ms-2"></i></button>
                            </div>
                        </div>

                        <!-- Step 2: Plan & Payment -->
                        <div id="step2" class="hidden">
                            <h6 class="fw-extrabold text-uppercase small text-primary mb-4">2. Plano e Pagamento</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Escolha o Plano</label>
                                    <select name="plano" class="form-select" id="planoSelect">
                                        <?php foreach ($planos as $key => $info): ?>
                                            <option value="<?= $key ?>"><?= htmlspecialchars($info['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Forma de Recebimento</label>
                                    <select name="metodo_pagamento" class="form-select">
                                        <option value="pix">Pix</option>
                                        <option value="cartao">Cartão de Crédito/Débito</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="transferencia">Transferência</option>
                                    </select>
                                </div>
                                
                                <!-- Custom Plan Fields -->
                                <div class="col-md-6 custom-only hidden">
                                    <label class="form-label">Duração (Meses)</label>
                                    <input type="number" name="meses_custom" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-md-6 custom-only hidden">
                                    <label class="form-label">Valor Total (R$)</label>
                                    <input type="text" name="valor_custom" class="form-control" placeholder="0,00">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Observações da Venda</label>
                                    <textarea name="observacoes" class="form-control" rows="3" placeholder="Ex: Brinde concedido, parcelamento combinado..."></textarea>
                                </div>
                            </div>
                            <div class="mt-5 d-flex justify-content-between">
                                <button type="button" class="btn btn-light border px-4" id="btnVoltar"><i class="bi bi-arrow-left me-2"></i> Voltar</button>
                                <button type="submit" class="btn btn-success px-5 fw-bold">Finalizar e Ativar Cliente <i class="bi bi-check-lg ms-2"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // NAVIGATION STEPS logic
        const btnAvancar = document.getElementById('btnAvancar');
        const btnVoltar = document.getElementById('btnVoltar');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const dot1 = document.getElementById('stepDot1');
        const dot2 = document.getElementById('stepDot2');

        btnAvancar.onclick = () => {
             // Basic validation
             const nome = document.querySelector('input[name="cliente_nome"]').value;
             const email = document.querySelector('input[name="cliente_email"]').value;
             if(!nome || !email) { alert('Por favor, preencha o nome e e-mail.'); return; }
             
             step1.classList.add('hidden');
             step2.classList.remove('hidden');
             dot1.classList.remove('active');
             dot2.classList.add('active');
        };

        btnVoltar.onclick = () => {
             step2.classList.add('hidden');
             step1.classList.remove('hidden');
             dot2.classList.remove('active');
             dot1.classList.add('active');
        };

        // Custom Fields Logic
        const planoSel = document.getElementById('planoSelect');
        const customs = document.querySelectorAll('.custom-only');
        planoSel.onchange = () => {
            customs.forEach(c => {
                if(planoSel.value === 'custom') c.classList.remove('hidden');
                else c.classList.add('hidden');
            });
        };

        // SIMULATOR LOGIC
        function atualizarMeta() {
            const ativos = parseInt(document.getElementById('metaClientes').value) || 0;
            const novos = parseInt(document.getElementById('metaNovos').value) || 0;
            const meses = parseInt(document.getElementById('metaMeses').value) || 1;
            const comissao = 9.90;

            const projecaoEl = document.getElementById('metaProjecao');
            projecaoEl.innerHTML = '';

            let totalAcumulado = 0;
            let finalMensal = (ativos + (novos * meses)) * comissao;

            document.getElementById('metaMensalFinal').innerText = finalMensal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

            for (let i = 1; i <= meses; i++) {
                let cl = ativos + (novos * i);
                let rend = cl * comissao;
                totalAcumulado += rend;

                const row = document.createElement('div');
                row.className = 'chart-row';
                row.innerHTML = `
                    <span class="fw-bold">Mês ${i}</span>
                    <div class="chart-bar-container">
                        <div class="chart-bar" style="width: ${Math.min(100, (rend / (finalMensal||1)) * 100)}%"></div>
                    </div>
                    <span class="text-end fw-bold text-primary">${rend.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                `;
                projecaoEl.appendChild(row);
            }
        }

        document.getElementById('metaClientes').oninput = atualizarMeta;
        document.getElementById('metaNovos').oninput = atualizarMeta;
        document.getElementById('metaMeses').oninput = atualizarMeta;
        atualizarMeta();

        // FILTER LOGIC
        const busca = document.getElementById('filtroBusca');
        const stFiltro = document.getElementById('filtroStatus');
        const vcFiltro = document.getElementById('filtroVencimento');
        const rows = Array.from(document.querySelectorAll('#clientesTabela tbody tr'));
        const cards = Array.from(document.querySelectorAll('.mobile-user-card'));

        function filtrar() {
            const t = busca.value.toLowerCase();
            const s = stFiltro.value;
            const v = vcFiltro.value;

            const applyFilter = (el) => {
                const name = el.dataset.nome || '';
                const email = el.dataset.email || '';
                const status = el.dataset.status || '';
                const venc = el.dataset.vencimento || '';

                const matchesSearch = !t || name.includes(t) || email.includes(t);
                const matchesStatus = s === 'todos' || status === s;
                const matchesVenc = v === 'todos' || venc === v;

                if (matchesSearch && matchesStatus && matchesVenc) el.style.setProperty('display', '', 'important');
                else el.style.setProperty('display', 'none', 'important');
            };

            rows.forEach(applyFilter);
            cards.forEach(applyFilter);

            const visCount = rows.filter(r => r.style.display !== 'none').length;
            document.getElementById('clientesResumo').innerText = `${visCount} resultados encontrados`;
        }

        busca.oninput = filtrar;
        stFiltro.onchange = filtrar;
        vcFiltro.onchange = filtrar;
        filtrar();

        // Masking
        const telInput = document.getElementById('clienteTelefone');
        telInput.oninput = (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (v.length > 5) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            } else if (v.length > 0) {
                v = v.replace(/^(\d{0,2})/, '($1');
            }
            e.target.value = v;
        };

    </script>
</body>
</html>
