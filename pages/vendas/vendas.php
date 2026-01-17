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

if (isset($_SESSION['admin_logged_in'])) {
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
            --primary-dark: #4338ca;
            --accent: #ec4899;
            --accent-2: #6366f1;
            --muted: #6b7280;
            --bg: #f5f7fb;
            --card: rgba(255,255,255,0.92);
            --glass: rgba(255,255,255,0.7);
            --ink: #0f172a;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--ink);
        }

        .hero-bar {
            background: #111827;
            border-bottom: 1px solid rgba(148,163,184,0.2);
            padding: 28px 0;
            margin-bottom: 24px;
        }

        .hero-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 20px 24px;
            color: #fff;
            box-shadow: 0 20px 60px -30px rgba(15, 23, 42, 0.5);
        }

        .stat-card {
            background: var(--card);
            border-radius: 18px;
            padding: 18px;
            border: 1px solid rgba(226,232,240,0.8);
            box-shadow: 0 22px 45px -28px rgba(15,23,42,0.35);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: "";
            position: absolute;
            inset: auto -40% -60% auto;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(79,70,229,0.08);
        }

        .section-card {
            background: var(--card);
            border-radius: 22px;
            padding: 22px;
            border: 1px solid rgba(148,163,184,0.2);
            box-shadow: 0 18px 45px -28px rgba(15,23,42,0.35);
            margin-bottom: 24px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .status-active {
            background: #e8edff;
            color: #166534;
            border: 1px solid #86efac;
        }

        .status-inactive {
            background: #fff1f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .status-vitalicio {
            background: #fef3c7;
            color: #9a3412;
            border: 1px solid #fdba74;
        }

        .card-vencido {
            border: 1px solid rgba(239,68,68,0.5) !important;
            box-shadow: 0 12px 24px -20px rgba(220,38,38,0.6);
        }

        .card-vencendo {
            border: 1px solid rgba(245,158,11,0.5) !important;
            box-shadow: 0 12px 24px -20px rgba(245,158,11,0.5);
        }

        .table thead th {
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            color: var(--muted);
            background: rgba(248,250,252,0.9);
        }

        .table tbody td {
            vertical-align: middle;
            font-size: 0.88rem;
        }

        .calc-box {
            background: rgba(79,70,229,0.06);
            border-radius: 18px;
            padding: 16px;
            border: 1px dashed rgba(148,163,184,0.5);
        }

        .link-box {
            background: #111827;
            color: #fff;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.9rem;
        }


        .btn-primary {
            background: var(--primary);
            border: none;
            box-shadow: 0 12px 24px -18px rgba(79, 70, 229, 0.4);
        }

        .btn-primary:hover {
            filter: brightness(1.05);
        }

        .btn-outline-dark {
            border-color: #d1d5db;
            color: #111827;
        }

        .btn-outline-dark:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-dark {
            background: #111827;
            border: none;
        }

        .form-label {
            font-weight: 600;
            color: #111827;
        }

        .form-steps {
            display: grid;
            gap: 18px;
        }

        .step-indicator {
            display: flex;
            gap: 8px;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: rgba(148,163,184,0.5);
        }

        .step-dot.active {
            background: var(--accent);
        }

        .step-panel {
            display: block;
        }

        .step-panel.hidden {
            display: none;
        }

        .chart-bars {
            display: grid;
            gap: 8px;
            margin-top: 8px;
        }

        .chart-row {
            display: grid;
            grid-template-columns: 70px 1fr 90px;
            gap: 10px;
            align-items: center;
            font-size: 0.8rem;
        }

        .chart-bar {
            height: 10px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .hero-card {
                padding: 16px;
            }

            .section-card {
                padding: 18px;
            }

            .stat-card {
                padding: 16px;
            }

            .table thead {
                display: none;
            }

            .table tbody tr {
                display: block;
                border: 1px solid rgba(148,163,184,0.2);
                border-radius: 14px;
                padding: 12px;
                margin-bottom: 12px;
                background: #fff;
            }

            .table tbody td {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 6px 0;
                border: none;
                font-size: 0.86rem;
            }

            .table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #111827;
                font-size: 0.72rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin-bottom: 4px;
            }

            .hero-bar .btn {
                width: 100%;
            }

            .section-card {
                padding: 18px;
            }

            .stat-card {
                padding: 16px;
            }

            .table tbody tr {
                box-shadow: 0 10px 18px -20px rgba(15,23,42,0.5);
            }

            .form-steps {
                gap: 14px;
            }

            .step-panel {
                padding: 12px;
                border-radius: 16px;
                border: 1px solid rgba(148,163,184,0.25);
                background: #fff;
            }
        }
    </style>
</head>
<body>
    <div class="hero-bar">
        <div class="container">
            <div class="hero-card">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <div class="text-uppercase small" style="letter-spacing:0.2em; opacity:0.7;">Area do vendedor</div>
                        <h2 class="mb-1" style="font-family:'Outfit',sans-serif; font-weight:800;">Olá, <?= htmlspecialchars($vendedorNome) ?></h2>
                        <div style="opacity:0.85;">Codigo do vendedor: <span class="pill"><?= htmlspecialchars($vendedorCodigo) ?></span></div>
                    </div>
                    <div class="d-flex flex-column align-items-md-end gap-2">
                        <a href="<?= $logoutUrl ?>" class="btn btn-outline-light">Sair</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($feedback): ?>
            <div class="alert alert-<?= $feedbackType ?>"><?= htmlspecialchars($feedback) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Clientes totais</div>
                    <div class="fs-4 fw-bold"><?= $totalClientes ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Clientes ativos</div>
                    <div class="fs-4 fw-bold"><?= $ativos ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Vencendo (15 dias)</div>
                    <div class="fs-4 fw-bold"><?= $vencendo ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small">Comissao mensal prevista</div>
                    <div class="fs-4 fw-bold">R$ <?= number_format($comissaoMensalPrevista, 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="section-card">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                        <h4 class="mb-0" style="font-family:'Outfit',sans-serif;">Nova venda</h4>
                        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
                            Nova venda
                        </button>
                    </div>
                </div>
                <div class="modal fade" id="modalNovaVenda" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content" style="border-radius:22px;">
                            <div class="modal-header" style="border:none;">
                                <h5 class="modal-title" style="font-family:'Outfit',sans-serif; font-weight:700;">Nova venda</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" style="padding: 1.5rem 2rem;">
                                <form method="post">
                                    <input type="hidden" name="action" value="create_sale">
                                    <div class="form-steps">
                                        <div class="step-indicator">
                                            <span class="step-dot active" id="stepDot1"></span>
                                            <span class="step-dot" id="stepDot2"></span>
                                        </div>

                                        <div class="step-panel" id="step1">
                                            <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nome do cliente</label>
                                            <input type="text" name="cliente_nome" class="form-control" required>
                                            <div class="invalid-feedback">Informe o nome.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">E-mail</label>
                                            <input type="email" name="cliente_email" class="form-control" required>
                                            <div class="invalid-feedback">Informe o e-mail.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Telefone</label>
                                            <input type="text" name="cliente_telefone" class="form-control" id="clienteTelefone">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CPF ou CNPJ</label>
                                            <input type="text" name="cliente_cpf" class="form-control" id="clienteCpf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nome do Estabelecimento</label>
                                            <input type="text" name="cliente_estabelecimento" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Senha de acesso</label>
                                            <input type="password" name="cliente_senha" class="form-control" required>
                                            <div class="invalid-feedback">Informe a senha.</div>
                                        </div>
                                        <div class="col-12">
                                            <div class="alert alert-warning d-none" id="step1Aviso"></div>
                                        </div>
                                        <div class="col-12 d-md-none">
                                            <button type="button" class="btn btn-dark w-100" id="btnAvancar">Avancar</button>
                                        </div>
                                            </div>
                                        </div>

                                        <div class="step-panel" id="step2">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Plano escolhido</label>
                                                    <select name="plano" class="form-select" id="planoSelect">
                                                        <?php foreach ($planos as $key => $info): ?>
                                                            <option value="<?= $key ?>"><?= htmlspecialchars($info['label']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 custom-only" style="display:none;">
                                                    <label class="form-label">Meses (custom)</label>
                                                    <input type="number" name="meses_custom" class="form-control" min="1" placeholder="Ex: 6">
                                                </div>
                                                <div class="col-md-4 custom-only" style="display:none;">
                                                    <label class="form-label">Valor custom (R$)</label>
                                                    <input type="text" name="valor_custom" class="form-control" placeholder="Ex: 300,00">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Desconto (%)</label>
                                                    <select name="desconto_percent" class="form-select">
                                                        <option value="0">Sem desconto</option>
                                                        <option value="5">5%</option>
                                                        <option value="10">10%</option>
                                                        <option value="15">15%</option>
                                                        <option value="20">20%</option>
                                                        <option value="25">25%</option>
                                                        <option value="30">30%</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Entrada (R$)</label>
                                                    <input type="text" name="entrada_valor" class="form-control" placeholder="0,00">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Desconto na entrada (R$)</label>
                                                    <input type="text" name="desconto_entrada" class="form-control" placeholder="0,00">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Forma de pagamento</label>
                                                    <select name="metodo_pagamento" class="form-select">
                                                        <option value="pix">Pix</option>
                                                        <option value="debito">Debito</option>
                                                        <option value="credito">Credito</option>
                                                        <option value="boleto">Boleto</option>
                                                        <option value="dinheiro">Dinheiro</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Observacoes</label>
                                                    <textarea name="observacoes" class="form-control" rows="2"></textarea>
                                                </div>
                                                <div class="col-12 d-md-none">
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-outline-dark w-50" id="btnVoltar">Voltar</button>
                                                        <button type="submit" class="btn btn-primary w-50">Registrar</button>
                                                    </div>
                                                </div>
                                                <div class="col-12 d-none d-md-block">
                                                    <button type="submit" class="btn btn-primary w-100">Registrar venda</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h4 class="mb-3" style="font-family:'Outfit',sans-serif;">Carteira de clientes</h4>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="filtroBusca" placeholder="Buscar por nome ou email">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filtroStatus">
                                <option value="todos">Status: todos</option>
                                <option value="ativo">Ativos</option>
                                <option value="inativo">Inativos</option>
                                <option value="vitalicio">Vitalicio</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filtroVencimento">
                                <option value="todos">Vencimento: todos</option>
                                <option value="vencendo">Vencendo (15 dias)</option>
                                <option value="vencido">Vencidos</option>
                                <option value="em_dia">Em dia</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filtroPorPagina">
                                <option value="5">5 por pagina</option>
                                <option value="10" selected>10 por pagina</option>
                                <option value="20">20 por pagina</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="clientesTabela">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Status</th>
                                    <th>Vencimento</th>
                                    <th>Último pagamento</th>
                                    <th>Ação</th>
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
                                        <tr class="<?= $vencStatus === 'vencido' ? 'card-vencido' : ($vencStatus === 'vencendo' ? 'card-vencendo' : '') ?>"
                                            data-nome="<?= htmlspecialchars(mb_strtolower($cliente['nome'])) ?>"
                                            data-email="<?= htmlspecialchars(mb_strtolower($cliente['email'])) ?>"
                                            data-status="<?= $statusBase ?>"
                                            data-vencimento="<?= $vencStatus ?>"
                                            data-exp="<?= $dataExp ? $dataExp->format('Y-m-d') : '' ?>"
                                            data-ultima="<?= $ultimaData ?>">
                                            <td data-label="Cliente">
                                                <div class="fw-semibold"><?= htmlspecialchars($cliente['nome']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($cliente['email']) ?></div>
                                            </td>
                                            <td data-label="Status">
                                                <?php if (!empty($cliente['ativo'])): ?>
                                                    <?php if (!empty($cliente['is_vitalicio'])): ?>
                                                        <span class="status-pill status-vitalicio">Vitalicio</span>
                                                    <?php else: ?>
                                                        <span class="status-pill status-active">Ativo</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-pill status-inactive">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Vencimento">
                                                <?php if (!empty($cliente['is_vitalicio'])): ?>
                                                    Vitalicio
                                                <?php elseif ($dataExp): ?>
                                                    <?= $dataExp->format('d/m/Y') ?>
                                                    <?php if ($diasRestantes !== null): ?>
                                                        <div class="small text-muted"><?= $diasRestantes ?> dias</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Ultimo pagamento">
                                                <?php if ($ultima): ?>
                                                    <?= date('d/m/Y', strtotime($ultima['criado_em'])) ?>
                                                    <div class="small text-muted">
                                                        <?= htmlspecialchars($ultima['plano_tipo']) ?>
                                                        <?php if (!empty($ultima['metodo_pagamento'])): ?>
                                                            • <?= htmlspecialchars($ultima['metodo_pagamento']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Acao">
                                                <?php if ($vencStatus === 'vencendo' || $vencStatus === 'vencido'): ?>
                                                    <?php if ($linkWpp !== ''): ?>
                                                        <a class="btn btn-outline-dark btn-sm" href="<?= htmlspecialchars($linkWpp) ?>" target="_blank" rel="noopener">
                                                            Enviar mensagem
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="small text-muted">Sem telefone</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="small text-muted">Em dia</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Nenhum cliente cadastrado ainda.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-3">
                        <div class="small text-muted" id="clientesResumo">0 resultados</div>
                        <div class="d-flex gap-2" id="clientesPaginacao"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="section-card">
                    <h4 class="mb-3" style="font-family:'Outfit',sans-serif;">Resumo de comissoes</h4>
                    <div class="calc-box mb-3">
                        <div class="text-muted small">Comissão total acumulada</div>
                        <div class="fs-3 fw-bold">R$ <?= number_format($comissaoTotalAcumulada, 2, ',', '.') ?></div>
                    </div>
                    <div class="calc-box">
                        <div class="text-muted small">Comissao mensal prevista (ativos)</div>
                        <div class="fs-4 fw-bold">R$ <?= number_format($comissaoMensalPrevista, 2, ',', '.') ?></div>
                    </div>
                </div>

                <div class="section-card">
                    <h4 class="mb-3" style="font-family:'Outfit',sans-serif;">Calculadora de meta</h4>
                    <div class="calc-box mb-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Clientes ativos atuais</label>
                                <input type="number" class="form-control" id="metaClientes" value="50" min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Comissao por cliente (R$)</label>
                                <input type="number" class="form-control" id="metaComissao" value="9.9" min="0" step="0.1">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Novos clientes por mes</label>
                                <input type="number" class="form-control" id="metaNovos" value="0" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Meses de projecao</label>
                                <input type="number" class="form-control" id="metaMeses" value="12" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="text-muted small">Estimativa mensal (com novos)</div>
                                <div class="fw-bold" id="metaMensal">R$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="text-muted small">Estimativa diaria (com novos)</div>
                                <div class="fw-bold" id="metaDiaria">R$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="text-muted small">Total acumulado</div>
                                <div class="fw-bold" id="metaTotal">R$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="text-muted small">Mensal no mes final</div>
                                <div class="fw-bold" id="metaMensalFinal">R$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="text-muted small">Novos clientes total</div>
                                <div class="fw-bold" id="metaNovosTotal">0</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="text-muted small">Clientes no fim</div>
                                <div class="fw-bold" id="metaClientesFinal">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Valores sao estimativas. O total acumulado soma mes a mes.</div>
                    <div class="calc-box mt-3">
                        <div class="text-muted small mb-2">Projecao mes a mes (mensal + acumulado)</div>
                        <div id="metaProjecao" class="chart-bars"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function parseNumero(valor) {
            if (typeof valor !== 'string') return Number(valor) || 0;
            return parseFloat(valor.replace(',', '.')) || 0;
        }

        function atualizarMeta() {
            const clientesBase = Math.max(0, parseNumero(document.getElementById('metaClientes').value));
            const comissao = Math.max(0, parseNumero(document.getElementById('metaComissao').value));
            const novosMes = Math.max(0, parseNumero(document.getElementById('metaNovos').value));
            const meses = Math.max(0, parseNumero(document.getElementById('metaMeses').value));
            const clientesMes1 = clientesBase + novosMes;
            const mensalBase = clientesMes1 * comissao;
            const diaria = mensalBase / 30;
            document.getElementById('metaMensal').innerText = mensalBase.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('metaDiaria').innerText = diaria.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

            const hoje = new Date();
            const mesesNomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            const projecaoEl = document.getElementById('metaProjecao');
            projecaoEl.innerHTML = '';
            let ano = hoje.getFullYear();
            let mesIndex = hoje.getMonth();
            let acumulado = 0;
            let maxValor = 1;
            for (let i = 0; i < meses; i++) {
                const clientesMes = clientesBase + (novosMes * i);
                const mensalMes = clientesMes * comissao;
                if (mensalMes > maxValor) maxValor = mensalMes;
            }
            for (let i = 0; i < meses; i++) {
                const label = `${mesesNomes[mesIndex]} ${ano}`;
                const clientesMes = clientesBase + (novosMes * i);
                const mensalMes = clientesMes * comissao;
                acumulado += mensalMes;
                const linha = document.createElement('div');
                linha.className = 'chart-row';
                const largura = Math.max(6, (mensalMes / maxValor) * 100);
                linha.innerHTML = `
                    <span>${label}</span>
                    <div class="chart-bar" style="width:${largura}%;"></div>
                    <strong>${mensalMes.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })} <span class="text-muted" style="font-weight:500;">(${acumulado.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })})</span></strong>
                `;
                projecaoEl.appendChild(linha);
                mesIndex += 1;
                if (mesIndex > 11) {
                    mesIndex = 0;
                    ano += 1;
                }
            }
            document.getElementById('metaTotal').innerText = acumulado.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const clientesFinal = clientesBase + (novosMes * Math.max(0, meses));
            document.getElementById('metaClientesFinal').innerText = Math.round(clientesFinal);
            const novosTotal = novosMes * Math.max(0, meses);
            document.getElementById('metaNovosTotal').innerText = Math.round(novosTotal);
            const clientesFinalMensal = clientesBase + (novosMes * Math.max(0, meses));
            const mensalFinal = clientesFinalMensal * comissao;
            document.getElementById('metaMensalFinal').innerText = mensalFinal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        ['metaClientes', 'metaComissao', 'metaNovos', 'metaMeses'].forEach(id => {
            document.getElementById(id).addEventListener('input', atualizarMeta);
        });
        atualizarMeta();

        const planoSelect = document.getElementById('planoSelect');
        const customFields = document.querySelectorAll('.custom-only');
        function atualizarCustom() {
            const isCustom = planoSelect.value === 'custom';
            customFields.forEach(field => {
                field.style.display = isCustom ? '' : 'none';
            });
        }
        planoSelect.addEventListener('change', atualizarCustom);
        atualizarCustom();


        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const dot1 = document.getElementById('stepDot1');
        const dot2 = document.getElementById('stepDot2');
        const btnAvancar = document.getElementById('btnAvancar');
        const btnVoltar = document.getElementById('btnVoltar');

        function atualizarSteps() {
            if (window.innerWidth <= 768) {
                step2.classList.add('hidden');
                dot1.classList.add('active');
                dot2.classList.remove('active');
            } else {
                step1.classList.remove('hidden');
                step2.classList.remove('hidden');
                dot1.classList.add('active');
                dot2.classList.add('active');
            }
        }

        btnAvancar.addEventListener('click', () => {
            const nome = document.querySelector('input[name="cliente_nome"]');
            const email = document.querySelector('input[name="cliente_email"]');
            const senha = document.querySelector('input[name="cliente_senha"]');
            const aviso = document.getElementById('step1Aviso');
            const faltando = [];

            [nome, email, senha].forEach(campo => campo.classList.remove('is-invalid'));

            if (!nome.value.trim()) { faltando.push('Nome'); nome.classList.add('is-invalid'); }
            const emailVal = email.value.trim();
            const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal);
            if (!emailVal) {
                faltando.push('E-mail');
                email.classList.add('is-invalid');
            } else if (!emailOk) {
                faltando.push('E-mail valido');
                email.classList.add('is-invalid');
            }
            if (!senha.value.trim()) { faltando.push('Senha'); senha.classList.add('is-invalid'); }

            if (faltando.length > 0) {
                aviso.textContent = 'Preencha: ' + faltando.join(', ');
                aviso.classList.remove('d-none');
                return;
            }

            aviso.classList.add('d-none');
            step1.classList.add('hidden');
            step2.classList.remove('hidden');
            dot1.classList.remove('active');
            dot2.classList.add('active');
        });

        btnVoltar.addEventListener('click', () => {
            step2.classList.add('hidden');
            step1.classList.remove('hidden');
            dot2.classList.remove('active');
            dot1.classList.add('active');
        });

        window.addEventListener('resize', atualizarSteps);
        atualizarSteps();

        const cpfInput = document.getElementById('clienteCpf');
        const telefoneInput = document.getElementById('clienteTelefone');

        function formatCpfCnpj(value) {
            const digits = value.replace(/\D/g, '').slice(0, 14);
            if (digits.length <= 11) {
                return digits
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            return digits
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        }

        function formatTelefone(value) {
            const digits = value.replace(/\D/g, '').slice(0, 11);
            if (digits.length <= 10) {
                return digits
                    .replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
            }
            return digits
                .replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
        }

        if (cpfInput) {
            cpfInput.addEventListener('input', () => {
                cpfInput.value = formatCpfCnpj(cpfInput.value);
            });
        }

        if (telefoneInput) {
            telefoneInput.addEventListener('input', () => {
                telefoneInput.value = formatTelefone(telefoneInput.value);
            });
        }

        function validaCpf(cpf) {
            if (!cpf || cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
            let soma = 0;
            for (let i = 0; i < 9; i++) soma += parseInt(cpf[i], 10) * (10 - i);
            let resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf[9], 10)) return false;
            soma = 0;
            for (let i = 0; i < 10; i++) soma += parseInt(cpf[i], 10) * (11 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            return resto === parseInt(cpf[10], 10);
        }

        function validaCnpj(cnpj) {
            if (!cnpj || cnpj.length !== 14 || /^(\d)\1+$/.test(cnpj)) return false;
            let tamanho = cnpj.length - 2;
            let numeros = cnpj.substring(0, tamanho);
            const digitos = cnpj.substring(tamanho);
            let soma = 0;
            let pos = tamanho - 7;
            for (let i = tamanho; i >= 1; i--) {
                soma += parseInt(numeros[tamanho - i], 10) * pos--;
                if (pos < 2) pos = 9;
            }
            let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
            if (resultado !== parseInt(digitos[0], 10)) return false;
            tamanho = tamanho + 1;
            numeros = cnpj.substring(0, tamanho);
            soma = 0;
            pos = tamanho - 7;
            for (let i = tamanho; i >= 1; i--) {
                soma += parseInt(numeros[tamanho - i], 10) * pos--;
                if (pos < 2) pos = 9;
            }
            resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
            return resultado === parseInt(digitos[1], 10);
        }

        const formNovaVenda = document.querySelector('#modalNovaVenda form');
        if (formNovaVenda) {
            formNovaVenda.addEventListener('submit', (event) => {
                const cpfVal = (cpfInput ? cpfInput.value : '').replace(/\D/g, '');
                if (cpfVal.length === 11 && !validaCpf(cpfVal)) {
                    cpfInput.classList.add('is-invalid');
                    cpfInput.nextElementSibling?.remove();
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = 'CPF invalido.';
                    cpfInput.parentNode.appendChild(feedback);
                    event.preventDefault();
                }
                if (cpfVal.length === 14 && !validaCnpj(cpfVal)) {
                    cpfInput.classList.add('is-invalid');
                    cpfInput.nextElementSibling?.remove();
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = 'CNPJ invalido.';
                    cpfInput.parentNode.appendChild(feedback);
                    event.preventDefault();
                }
            });
        }

        const tabela = document.getElementById('clientesTabela');
        const linhas = Array.from(tabela.querySelectorAll('tbody tr'));
        const resumo = document.getElementById('clientesResumo');
        const paginacao = document.getElementById('clientesPaginacao');
        const busca = document.getElementById('filtroBusca');
        const filtroStatus = document.getElementById('filtroStatus');
        const filtroVencimento = document.getElementById('filtroVencimento');
        const filtroPorPagina = document.getElementById('filtroPorPagina');

        let paginaAtual = 1;

        function filtrarClientes() {
            const termo = (busca.value || '').toLowerCase();
            const status = filtroStatus.value;
            const vencimento = filtroVencimento.value;
            const porPagina = parseInt(filtroPorPagina.value || '10', 10);

            const filtrados = linhas.filter(linha => {
                const nome = linha.dataset.nome || '';
                const email = linha.dataset.email || '';
                const statusLinha = linha.dataset.status || '';
                const vencLinha = linha.dataset.vencimento || '';
                const matchBusca = !termo || nome.includes(termo) || email.includes(termo);
                const matchStatus = status === 'todos' || statusLinha === status;
                const matchVenc = vencimento === 'todos' || vencLinha === vencimento;
                return matchBusca && matchStatus && matchVenc;
            });

            const totalPaginas = Math.max(1, Math.ceil(filtrados.length / porPagina));
            if (paginaAtual > totalPaginas) paginaAtual = totalPaginas;
            const inicio = (paginaAtual - 1) * porPagina;
            const fim = inicio + porPagina;

            linhas.forEach(linha => linha.style.display = 'none');
            filtrados.slice(inicio, fim).forEach(linha => linha.style.display = '');

            resumo.innerText = `${filtrados.length} resultados`;
            renderizarPaginacao(totalPaginas);
        }

        function renderizarPaginacao(totalPaginas) {
            paginacao.innerHTML = '';
            const btnPrev = document.createElement('button');
            btnPrev.className = 'btn btn-outline-dark btn-sm';
            btnPrev.innerText = 'Anterior';
            btnPrev.disabled = paginaAtual === 1;
            btnPrev.onclick = () => { paginaAtual -= 1; filtrarClientes(); };
            paginacao.appendChild(btnPrev);

            for (let i = 1; i <= totalPaginas; i++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm ' + (i === paginaAtual ? 'btn-dark' : 'btn-outline-dark');
                btn.innerText = i;
                btn.onclick = () => { paginaAtual = i; filtrarClientes(); };
                paginacao.appendChild(btn);
            }

            const btnNext = document.createElement('button');
            btnNext.className = 'btn btn-outline-dark btn-sm';
            btnNext.innerText = 'Proxima';
            btnNext.disabled = paginaAtual === totalPaginas;
            btnNext.onclick = () => { paginaAtual += 1; filtrarClientes(); };
            paginacao.appendChild(btnNext);
        }

        [busca, filtroStatus, filtroVencimento, filtroPorPagina].forEach(el => {
            el.addEventListener('input', () => { paginaAtual = 1; filtrarClientes(); });
            el.addEventListener('change', () => { paginaAtual = 1; filtrarClientes(); });
        });

        filtrarClientes();

    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
