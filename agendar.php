<?php
// agendar.php (NA RAIZ DO PROJETO)
include 'includes/db.php';

// ID do Profissional dinâmico via GET
$profissionalId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
if ($profissionalId <= 0) {
    die('<h2>Profissional não encontrado.</h2>');
}

// Busca dados do profissional
$stmtProf = $pdo->prepare("
    SELECT id, nome, email, telefone,
           cep, endereco, numero, bairro, cidade, estado
      FROM usuarios
     WHERE id = ?
     LIMIT 1
");
$stmtProf->execute([$profissionalId]);
$profissional = $stmtProf->fetch();

if (!$profissional) {
    die('<h2>Profissional não encontrado.</h2>');
}

// Monta dados do profissional
$profNome      = !empty($profissional['nome'])     ? $profissional['nome']     : 'Profissional de Beleza';
$profEmail     = !empty($profissional['email'])    ? $profissional['email']    : '';
$profTelefone  = !empty($profissional['telefone']) ? $profissional['telefone'] : '';

$enderecoPartes = [];

if (!empty($profissional['endereco'])) {
    $linha = $profissional['endereco'];
    if (!empty($profissional['numero'])) {
        $linha .= ', ' . $profissional['numero'];
    }
    $enderecoPartes[] = $linha;
}
if (!empty($profissional['bairro'])) {
    $enderecoPartes[] = $profissional['bairro'];
}
$cidadeEstado = trim(
    (string)($profissional['cidade'] ?? '') .
    (!empty($profissional['estado']) ? ' - ' . $profissional['estado'] : '')
);
if (!empty($cidadeEstado)) {
    $enderecoPartes[] = $cidadeEstado;
}
if (!empty($profissional['cep'])) {
    $enderecoPartes[] = 'CEP: ' . $profissional['cep'];
}

$profEndereco = implode(' • ', array_filter($enderecoPartes));

// flag de sucesso (após redirect)
$sucesso = isset($_GET['ok']) && $_GET['ok'] == 1;

// =========================================================
// API INTERNA (AJAX)
// =========================================================

// 1. Buscar Horários
if (isset($_GET['action']) && $_GET['action'] === 'buscar_horarios') {
    $data           = $_GET['data'];
    $duracaoServico = (int)$_GET['duracao'];
    $diaSemana      = date('w', strtotime($data));

    // Turnos de trabalho
    $stmt = $pdo->prepare("SELECT inicio, fim FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?");
    $stmt->execute([$profissionalId, $diaSemana]);
    $turnos = $stmt->fetchAll();

    // Horários ocupados
    $stmt = $pdo->prepare("SELECT horario, servico FROM agendamentos WHERE user_id = ? AND data_agendamento = ? AND status != 'Cancelado'");
    $stmt->execute([$profissionalId, $data]);
    $ocupados = $stmt->fetchAll();

    $minutosOcupados = [];
    foreach ($ocupados as $ag) {
        $horaMin   = explode(':', $ag['horario']);
        $inicioMin = ((int)$horaMin[0] * 60) + (int)$horaMin[1];
        $fimMin    = $inicioMin + $duracaoServico; // bloqueia pelo tempo do serviço

        for ($m = $inicioMin; $m < $fimMin; $m++) {
            $minutosOcupados[$m] = true;
        }
    }

    $slotsDisponiveis = [];
    foreach ($turnos as $turno) {
        $inicioParts = explode(':', $turno['inicio']);
        $fimParts    = explode(':', $turno['fim']);
        $inicioMin   = ((int)$inicioParts[0] * 60) + (int)$inicioParts[1];
        $fimMin      = ((int)$fimParts[0] * 60) + (int)$fimParts[1];

        for ($time = $inicioMin; $time <= ($fimMin - $duracaoServico); $time += 30) {
            $livre = true;
            for ($check = $time; $check < ($time + $duracaoServico); $check++) {
                if (isset($minutosOcupados[$check])) {
                    $livre = false;
                    break;
                }
            }
            if ($livre) {
                $horaFormatada =
                    str_pad(floor($time / 60), 2, '0', STR_PAD_LEFT) . ':' .
                    str_pad($time % 60, 2, '0', STR_PAD_LEFT);
                $slotsDisponiveis[] = $horaFormatada;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($slotsDisponiveis);
    exit;
}

// 2. Buscar Cliente por CPF (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'buscar_cliente') {
    $cpf = preg_replace('/[^0-9]/', '', $_GET['cpf']);

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE cpf = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$cpf, $profissionalId]);
    $cliente = $stmt->fetch();

    header('Content-Type: application/json');
    if ($cliente) {
        echo json_encode([
            'found'    => true,
            'nome'     => $cliente['nome'],
            'telefone' => $cliente['telefone']
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}

// =========================================================
// PROCESSAR O AGENDAMENTO (POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = $_POST['cliente_nome']      ?? '';
    $telefone   = $_POST['cliente_telefone']  ?? '';
    $cpf        = preg_replace('/[^0-9]/', '', $_POST['cliente_cpf'] ?? '');
    $obsCliente = $_POST['cliente_obs']       ?? '';

    $servicoId  = $_POST['servico_id']        ?? null;
    $data       = $_POST['data_escolhida']    ?? '';
    $horario    = $_POST['horario_escolhido'] ?? '';

    // Recupera nome do serviço
    $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$servicoId, $profissionalId]);
    $servicoNome = $stmt->fetchColumn();

    if ($nome && $horario && $servicoNome && $cpf && $data) {
        // 1. Verifica/Salva o Cliente na tabela de CLIENTES
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf = ? AND user_id = ?");
        $stmt->execute([$cpf, $profissionalId]);
        $clienteExistente = $stmt->fetch();

        if ($clienteExistente) {
            $clienteId = $clienteExistente['id'];
            $pdo->prepare("UPDATE clientes SET nome = ?, telefone = ? WHERE id = ?")
                ->execute([$nome, $telefone, $clienteId]);
        } else {
            $pdo->prepare("INSERT INTO clientes (user_id, nome, telefone, cpf) VALUES (?, ?, ?, ?)")
                ->execute([$profissionalId, $nome, $telefone, $cpf]);
            $clienteId = $pdo->lastInsertId();
        }

        // 2. Cria o Agendamento vinculado
        $sql = "INSERT INTO agendamentos 
                (user_id, cliente_id, cliente_nome, cliente_cpf, servico, data_agendamento, horario, status, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)";

        $obsFinal = $obsCliente;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $profissionalId,
            $clienteId,
            $nome,
            $cpf,
            $servicoNome,
            $data,
            $horario,
            $obsFinal
        ]);

        // Redireciona para evitar re-envio de formulário e mostrar tela de sucesso
        header("Location: agendar.php?user={$profissionalId}&ok=1");
        exit;
    }
}

// Buscar Serviços para exibir
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY nome ASC");
$stmt->execute([$profissionalId]);
$servicos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Agendar com <?php echo htmlspecialchars($profNome); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-soft: #eef2ff;
            --accent: #f97316;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --border-soft: #e5e7eb;
            --text-main: #0f172a;
            --text-muted: #6b7280;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top left, #e0f2fe 0, transparent 50%),
                radial-gradient(circle at bottom right, #e0e7ff 0, transparent 55%),
                #f9fafb;
            color: var(--text-main);
            margin: 0;
            padding: 24px 16px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 520px;
            background: var(--bg-card);
            border-radius: 24px;
            box-shadow: 0 22px 60px rgba(148,163,184,0.35);
            overflow: hidden;
            border: 1px solid rgba(226,232,240,0.9);
        }

        /* HEADER NOVO: card mais clean, sem degradê forte */
        .header {
            padding: 16px 18px 14px;
            display: flex;
            gap: 12px;
            align-items: center;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .avatar {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: #4338ca;
            border: 1px solid #c7d2fe;
        }

        .header-info {
            flex: 1;
        }

        .header-info h1 {
            margin: 0;
            font-size: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: baseline;
        }

        .header-info h1 span:first-child {
            font-weight: 600;
        }

        .header-info h1 span:last-child {
            font-size: 0.78rem;
            font-weight: 500;
            color: #6366f1;
            background: #eef2ff;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .header-info p {
            margin: 3px 0 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .header-meta {
            margin-top: 6px;
            font-size: 0.75rem;
            color: #64748b;
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
        }

        .header-meta div {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .header-meta i {
            font-size: 0.82rem;
            color: #6366f1;
        }

        /* STEPS – estilo “etapas” com bolinha numerada */
        .steps-bar {
            display: flex;
            justify-content: space-between;
            padding: 10px 18px 8px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }

        .step-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .step-dot {
            width: 22px;
            height: 22px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            background: #f9fafb;
            color: #6b7280;
            transition: 0.2s;
        }

        .step-dot.active {
            border-color: #6366f1;
            background: #6366f1;
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(99,102,241,0.45);
        }

        .step-pill span.label {
            white-space: nowrap;
        }

        .step-container {
            padding: 18px 18px 20px;
            display: none;
            animation: fadeIn 0.25s;
        }
        .step-container.active { display: block; }

        .step-title {
            font-size: 0.98rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .step-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 18px;
        }

        /* CARDS DE SERVIÇO – novo formato com “radio” visual */
        .service-card {
            border-radius: 999px;
            padding: 10px 14px;
            margin-bottom: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid var(--border-soft);
            transition: 0.16s;
        }

        .service-card:hover {
            border-color: #a5b4fc;
            box-shadow: 0 8px 20px rgba(148,163,184,0.28);
            transform: translateY(-1px);
        }

        .service-main {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .service-name {
            font-weight: 600;
            font-size: 0.92rem;
        }

        .service-meta {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .service-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .service-price {
            font-weight: 600;
            font-size: 0.88rem;
        }

        .service-radio {
            width: 18px;
            height: 18px;
            border-radius: 999px;
            border: 2px solid #cbd5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .service-card.selected {
            background: #f5f3ff;
            border-color: #6366f1;
        }

        .service-card.selected .service-name {
            color: #4f46e5;
        }

        .service-card.selected .service-radio {
            border-color: #4f46e5;
        }

        .service-card.selected .service-radio::after {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #4f46e5;
        }

        .form-group { margin-bottom: 14px; }
        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 4px;
        }

        .form-control {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-soft);
            font-size: 0.9rem;
            background: #f9fafb;
            color: var(--text-main);
        }
        .form-control::placeholder {
            color: #9ca3af;
        }
        .form-control:focus {
            border-color: #6366f1;
            outline: none;
            box-shadow: 0 0 0 1px rgba(99,102,241,0.2);
            background: #ffffff;
        }

        textarea.form-control {
            resize: vertical;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 6px;
            margin-bottom: 6px;
        }
        @media (max-width: 420px) {
            .slots-grid { grid-template-columns: repeat(3, 1fr); }
        }

        .time-slot {
            background: #f9fafb;
            padding: 8px 4px;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.78rem;
            border: 1px solid #e5e7eb;
            transition: 0.16s;
            color: #111827;
        }
        .time-slot:hover {
            background: #e0f2fe;
            border-color: #93c5fd;
        }
        .time-slot.selected {
            background: #0ea5e9;
            border-color: #0284c7;
            color: #f9fafb;
            font-weight: 600;
            box-shadow: 0 8px 18px rgba(14,165,233,0.4);
        }

        .btn-action {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg,#6366f1,#22c55e);
            color: white;
            border: none;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 14px 34px rgba(34,197,94,0.35);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-action:disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-back {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
            font-size: 0.8rem;
            padding: 0;
        }

        .btn-back i {
            font-size: 0.85rem;
        }

        .feedback-msg { font-size: 0.78rem; margin-top: 4px; display: none; }
        .text-success { color: #16a34a; }
        .text-error { color: #dc2626; }

        .summary-card {
            background: #f9fafb;
            border-radius: 16px;
            padding: 14px;
            margin-top: 8px;
            border: 1px dashed #e2e8f0;
            font-size: 0.85rem;
        }

        .summary-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            gap: 8px;
        }

        .summary-label { color: #6b7280; }
        .summary-value { font-weight: 600; text-align: right; }

        .success-screen {
            text-align: center;
            padding: 32px 22px 26px;
        }
        .success-icon {
            font-size: 3.2rem;
            color: #16a34a;
            margin-bottom: 10px;
        }
        .success-screen h2 {
            margin: 4px 0 6px;
            font-size: 1.2rem;
            color: #111827;
        }
        .success-screen p {
            margin: 0;
            font-size: 0.9rem;
            color: #4b5563;
        }
        .success-meta {
            margin-top: 14px;
            font-size: 0.8rem;
            color: #6b7280;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($sucesso): ?>
        <div class="header">
            <div class="avatar">
                <?php echo strtoupper(mb_substr($profNome, 0, 1, 'UTF-8')); ?>
            </div>
            <div class="header-info">
                <h1>
                    <span><?php echo htmlspecialchars($profNome); ?></span>
                    <span>Agendamento concluído</span>
                </h1>
                <p>Seu horário foi reservado com sucesso.</p>
                <div class="header-meta">
                    <?php if (!empty($profTelefone)): ?>
                        <div><i class="bi bi-telephone"></i><?php echo htmlspecialchars($profTelefone); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($profEndereco)): ?>
                        <div><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($profEndereco); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="success-screen">
            <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
            <h2>Agendamento Confirmado</h2>
            <p>Você receberá as orientações diretamente com o profissional.</p>

            <div class="success-meta">
                Em caso de dúvidas ou necessidade de remarcar,<br>
                entre em contato com <?php echo htmlspecialchars($profNome); ?>.
            </div>

            <a href="agendar.php?user=<?php echo $profissionalId; ?>" class="btn-action" style="margin-top:18px; text-decoration:none;">
                <i class="bi bi-plus-circle"></i> Novo Agendamento
            </a>
        </div>
    <?php else: ?>

    <div class="header">
        <div class="avatar">
            <?php echo strtoupper(mb_substr($profNome, 0, 1, 'UTF-8')); ?>
        </div>
        <div class="header-info">
            <h1>
                <span><?php echo htmlspecialchars($profNome); ?></span>
                <span>Agenda Online</span>
            </h1>
            <p>Escolha o serviço, dia e horário em poucos passos.</p>
            <div class="header-meta">
                <?php if (!empty($profTelefone)): ?>
                    <div><i class="bi bi-telephone"></i><?php echo htmlspecialchars($profTelefone); ?></div>
                <?php endif; ?>
                <?php if (!empty($profEndereco)): ?>
                    <div><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($profEndereco); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="steps-bar">
        <div class="step-pill">
            <div class="step-dot active" id="dot1">1</div>
            <span class="label">Serviço</span>
        </div>
        <div class="step-pill">
            <div class="step-dot" id="dot2">2</div>
            <span class="label">Data &amp; hora</span>
        </div>
        <div class="step-pill">
            <div class="step-dot" id="dot3">3</div>
            <span class="label">Seus dados</span>
        </div>
    </div>

    <form method="POST" id="bookingForm" onsubmit="return validarForm()">
        <input type="hidden" name="servico_id" id="inputServicoId">
        <input type="hidden" name="data_escolhida" id="inputData">
        <input type="hidden" name="horario_escolhido" id="inputHorario">

        <!-- STEP 1 -->
        <div class="step-container active" id="step1">
            <div class="step-title">1. Escolha o serviço</div>
            <div class="step-desc">Selecione o procedimento desejado para ver os horários disponíveis.</div>

            <?php if (empty($servicos)): ?>
                <p style="font-size:0.85rem; color:#dc2626;">
                    Nenhum serviço cadastrado para este profissional.
                </p>
            <?php else: ?>
                <?php foreach ($servicos as $s): ?>
                    <?php
                        $precoFormatado = 'R$ ' . number_format($s['preco'], 2, ',', '.');
                    ?>
                    <div class="service-card"
                         data-id="<?php echo (int)$s['id']; ?>"
                         data-duration="<?php echo (int)$s['duracao']; ?>"
                         data-nome="<?php echo htmlspecialchars($s['nome']); ?>"
                         data-preco-text="<?php echo htmlspecialchars($precoFormatado); ?>"
                         onclick="selectService(this)">
                        <div class="service-main">
                            <div class="service-name"><?php echo htmlspecialchars($s['nome']); ?></div>
                            <div class="service-meta"><?php echo (int)$s['duracao']; ?> min</div>
                        </div>
                        <div class="service-right">
                            <div class="service-price"><?php echo $precoFormatado; ?></div>
                            <div class="service-radio"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- STEP 2 -->
        <div class="step-container" id="step2">
            <button type="button" class="btn-back" onclick="goToStep(1)">
                <i class="bi bi-arrow-left"></i> Voltar
            </button>
            <div class="step-title">2. Escolha data e horário</div>
            <div class="step-desc">Selecione a melhor data e, em seguida, um dos horários disponíveis.</div>

            <div class="form-group">
                <label>Data do atendimento</label>
                <input type="date" class="form-control" id="datePicker" name="data_picker" onchange="loadTimes()">
            </div>

            <label style="font-size:0.75rem;">Horários disponíveis</label>
            <div id="loadingTimes" style="display:none; color:#6b7280; font-size:0.8rem; margin-bottom:10px;">
                Buscando horários...
            </div>
            <div class="slots-grid" id="slotsContainer">
                <div style="grid-column: 1/-1; text-align:center; color:#9ca3af; font-size:0.8rem;">
                    Selecione a data acima para ver os horários.
                </div>
            </div>

            <button type="button" class="btn-action" id="btnNextTo3" disabled onclick="goToStep(3)">
                Continuar
            </button>
        </div>

        <!-- STEP 3 -->
        <div class="step-container" id="step3">
            <button type="button" class="btn-back" onclick="goToStep(2)">
                <i class="bi bi-arrow-left"></i> Voltar
            </button>
            <div class="step-title">3. Seus dados</div>
            <div class="step-desc">
                Informe seu CPF para identificação. Se já tiver atendimentos anteriores, seus dados serão preenchidos automaticamente.
            </div>

            <div class="form-group">
                <label>CPF (apenas números)</label>
                <input type="tel" name="cliente_cpf" id="cpfInput" class="form-control"
                       placeholder="000.000.000-00" maxlength="14"
                       oninput="mascaraCPF(this)" onblur="buscarCliente()">
                <div id="cpfFeedback" class="feedback-msg"></div>
            </div>

            <div class="form-group">
                <label>Nome completo</label>
                <input type="text" name="cliente_nome" id="nomeInput" class="form-control"
                       placeholder="Seu nome completo" required>
            </div>

            <div class="form-group">
                <label>Telefone / WhatsApp</label>
                <input type="tel" name="cliente_telefone" id="telInput" class="form-control"
                       placeholder="(00) 00000-0000" onkeyup="mascaraTelefone(this)" required>
            </div>

            <div class="form-group">
                <label>Observação (opcional)</label>
                <textarea name="cliente_obs" class="form-control" rows="2"
                          placeholder="Ex: Tenho alergia a tal produto..."></textarea>
            </div>

            <div class="summary-card">
                <div style="font-size:0.78rem; color:#6b7280; margin-bottom:6px;">Resumo do agendamento</div>
                <div class="summary-line">
                    <span class="summary-label">Serviço</span>
                    <span class="summary-value" id="resumeServico">-</span>
                </div>
                <div class="summary-line">
                    <span class="summary-label">Data &amp; horário</span>
                    <span class="summary-value" id="resumeDataHora">-</span>
                </div>
                <div class="summary-line">
                    <span class="summary-label">Profissional</span>
                    <span class="summary-value"><?php echo htmlspecialchars($profNome); ?></span>
                </div>
                <?php if (!empty($profTelefone)): ?>
                    <div class="summary-line">
                        <span class="summary-label">Contato</span>
                        <span class="summary-value"><?php echo htmlspecialchars($profTelefone); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" id="btnConfirmar" class="btn-action">
                <i class="bi bi-calendar-check"></i> Confirmar Agendamento
            </button>
        </div>

    </form>
    <?php endif; ?>
</div>

<script>
    const PROF_ID  = <?php echo $profissionalId; ?>;
    const BASE_URL = 'agendar.php?user=' + PROF_ID;

    let selectedDuration     = 0;
    let selectedServiceName  = '';
    let selectedServicePrice = '';

    function goToStep(step) {
        document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');

        document.querySelectorAll('.step-dot').forEach((el, idx) => {
            el.classList.toggle('active', idx < step);
        });
    }

    function setMinDateLocal() {
        const dp = document.getElementById('datePicker');
        if (!dp) return;
        const hoje = new Date();
        const y = hoje.getFullYear();
        const m = String(hoje.getMonth() + 1).padStart(2, '0');
        const d = String(hoje.getDate()).padStart(2, '0');
        dp.min = `${y}-${m}-${d}`;
    }

    function selectService(card) {
        document.querySelectorAll('.service-card').forEach(el => el.classList.remove('selected'));
        card.classList.add('selected');

        const id       = card.dataset.id;
        const duration = parseInt(card.dataset.duration, 10) || 0;
        const nome     = card.dataset.nome || '';
        const precoTxt = card.dataset.precoText || '';

        selectedDuration     = duration;
        selectedServiceName  = nome;
        selectedServicePrice = precoTxt;

        document.getElementById('inputServicoId').value = id;

        setMinDateLocal();
        document.getElementById('datePicker').value = '';
        document.getElementById('inputData').value  = '';
        document.getElementById('inputHorario').value = '';

        document.getElementById('btnNextTo3').disabled = true;

        document.getElementById('slotsContainer').innerHTML =
            '<div style="grid-column: 1/-1; text-align:center; color:#9ca3af; font-size:0.8rem;">Selecione a data acima para ver os horários.</div>';

        document.getElementById('resumeServico').innerText = `${selectedServiceName} · ${selectedServicePrice}`;

        goToStep(2);
    }

    function loadTimes() {
        const date      = document.getElementById('datePicker').value;
        const container = document.getElementById('slotsContainer');
        const loader    = document.getElementById('loadingTimes');
        const btnNext   = document.getElementById('btnNextTo3');

        if (!date || !selectedDuration) return;

        document.getElementById('inputData').value = date;
        container.innerHTML = '';
        loader.style.display = 'block';
        btnNext.disabled = true;

        fetch(`${BASE_URL}&action=buscar_horarios&data=${encodeURIComponent(date)}&duracao=${selectedDuration}`)
            .then(response => response.json())
            .then(times => {
                loader.style.display = 'none';
                if (!Array.isArray(times) || times.length === 0) {
                    container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#dc2626; font-size:0.8rem;">Sem horários livres para esta data.</div>';
                } else {
                    times.forEach(time => {
                        const div = document.createElement('div');
                        div.className = 'time-slot';
                        div.innerText = time;
                        div.onclick = () => selectTime(div, time);
                        container.appendChild(div);
                    });
                }
            })
            .catch(err => {
                loader.style.display = 'none';
                console.error('Erro ao buscar horários:', err);
                container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#dc2626; font-size:0.8rem;">Erro ao carregar horários. Tente novamente.</div>';
            });
    }

    function selectTime(div, time) {
        document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));
        div.classList.add('selected');

        document.getElementById('inputHorario').value = time;
        document.getElementById('btnNextTo3').disabled = false;

        const dataRaw = document.getElementById('datePicker').value;
        let dataFormatada = dataRaw;
        if (dataRaw && dataRaw.indexOf('-') > -1) {
            const [y,m,d] = dataRaw.split('-');
            dataFormatada = `${d}/${m}/${y}`;
        }

        document.getElementById('resumeServico').innerText   = `${selectedServiceName} · ${selectedServicePrice}`;
        document.getElementById('resumeDataHora').innerText  = `${dataFormatada} às ${time}`;

        goToStep(3);
    }

    function mascaraCPF(i) {
        let v = i.value.replace(/\D/g, "");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        i.value = v;
    }

    function mascaraTelefone(i) {
        let v = i.value.replace(/\D/g,"");
        v = v.replace(/^(\d{2})(\d)/g,"($1) $2");
        v = v.replace(/(\d)(\d{4})$/,"$1-$2");
        i.value = v;
    }

    function buscarCliente() {
        const cpf       = document.getElementById('cpfInput').value;
        const feedback  = document.getElementById('cpfFeedback');
        const nomeInput = document.getElementById('nomeInput');
        const telInput  = document.getElementById('telInput');

        if (cpf.replace(/\D/g, "").length < 11) return;

        feedback.style.display = 'block';
        feedback.innerText     = 'Buscando cadastro...';
        feedback.className     = 'feedback-msg';

        fetch(`${BASE_URL}&action=buscar_cliente&cpf=${encodeURIComponent(cpf)}`)
            .then(r => r.json())
            .then(data => {
                if (data.found) {
                    feedback.innerText = 'Cliente encontrado! Dados preenchidos.';
                    feedback.classList.add('text-success');
                    nomeInput.value = data.nome || '';
                    telInput.value  = data.telefone || '';
                } else {
                    feedback.innerText = 'CPF não cadastrado. Preencha seus dados.';
                    feedback.classList.add('text-error');
                    nomeInput.value = '';
                    telInput.value  = '';
                    nomeInput.focus();
                }
            })
            .catch(err => {
                console.error('Erro ao buscar cliente:', err);
                feedback.innerText = 'Erro ao buscar CPF.';
                feedback.classList.add('text-error');
            });
    }

    function validarForm() {
        const servico = document.getElementById('inputServicoId').value;
        const data    = document.getElementById('inputData').value;
        const horario = document.getElementById('inputHorario').value;

        if (!servico || !data || !horario) {
            alert('Escolha o serviço, a data e o horário antes de confirmar.');
            return false;
        }

        const btn = document.getElementById('btnConfirmar');
        if (btn) {
            btn.innerHTML = 'Enviando...';
            btn.disabled = true;
        }
        return true;
    }

    setMinDateLocal();
</script>
</body>
</html>
