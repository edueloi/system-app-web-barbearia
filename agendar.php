<?php 
// ========================================================= 
// 1. CONFIGURAÇÃO E BACKEND 
// ========================================================= 
 
// Ajuste o caminho do include conforme sua estrutura de pastas 
$dbPath = 'includes/db.php'; 
if (!file_exists($dbPath)) $dbPath = '../../includes/db.php'; 
require_once $dbPath; 
 
// ID do Profissional (Pega da URL) 
$profissionalId = isset($_GET['user']) ? (int)$_GET['user'] : 0; 
 
// Se não tiver ID, tenta pegar o primeiro usuário (Fallback) 
if ($profissionalId <= 0) { 
    $stmtFirst = $pdo->query("SELECT id FROM usuarios LIMIT 1"); 
    $profissionalId = $stmtFirst->fetchColumn(); 
    if (!$profissionalId) { 
        die('<div style="font-family:sans-serif;text-align:center;padding:50px;">Sistema indisponível.</div>'); 
    } 
} 
 
// Busca dados COMPLETOS do profissional/estabelecimento 
$stmtProf = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1"); 
$stmtProf->execute([$profissionalId]); 
$profissional = $stmtProf->fetch(); 
 
if (!$profissional) die('Profissional não encontrado.'); 
 
// --- LÓGICA DE EXIBIÇÃO (Negócio vs Profissional) --- 
$nomeEstabelecimento = !empty($profissional['estabelecimento']) ? $profissional['estabelecimento'] : $profissional['nome']; 
$nomeProfissional    = $profissional['nome']; 
$telefone            = !empty($profissional['telefone']) ? $profissional['telefone'] : ''; 
$biografia           = !empty($profissional['biografia']) ? $profissional['biografia'] : 'Agende seu horário com a gente!'; 
 
// Endereço Formatado 
$enderecoCompleto = $profissional['endereco'] ?? ''; 
if (!empty($profissional['numero'])) $enderecoCompleto .= ', ' . $profissional['numero']; 
if (!empty($profissional['bairro'])) $enderecoCompleto .= ' - ' . $profissional['bairro']; 
 
// Foto / Logo 
$fotoPerfil = 'assets/default-avatar.png'; // Fallback padrão se você tiver 
$iniciais   = strtoupper(mb_substr($nomeEstabelecimento, 0, 1)); 
$temFoto    = false; 
 
if (!empty($profissional['foto']) && file_exists($profissional['foto'])) { 
    $fotoPerfil = $profissional['foto']; 
    $temFoto = true; 
} elseif (!empty($profissional['foto']) && file_exists('../../' . $profissional['foto'])) { 
    $fotoPerfil = '../../' . $profissional['foto']; 
    $temFoto = true; 
} 
 
// --- CONFIGURAÇÃO DE CORES (AGORA VEM DO BANCO) --- 
$corPersonalizada = '#4f46e5'; // padrão 
 
if (!empty($profissional['cor_tema'])) { 
    $cor = trim($profissional['cor_tema']); 
 
    // garante que começa com # 
    if ($cor[0] !== '#') { 
        $cor = '#' . $cor; 
    } 
 
    // valida formato #RRGGBB 
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) { 
        $corPersonalizada = $cor; 
    } 
} 
 


$servicoNome = $servicoNome ?? '';
// Variáveis para exibir na tela de sucesso (sempre disponíveis)
$servicoConfirmado = $_GET['servico'] ?? '';
$dataConfirmada    = $_GET['data'] ?? '';
$horaConfirmada    = $_GET['hora'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Garante que não dará warning ao acessar fora do POST
    $_POST['data_escolhida'] = $_POST['data_escolhida'] ?? '';
    $_POST['horario_escolhido'] = $_POST['horario_escolhido'] ?? '';
}
$sucesso = isset($_GET['ok']) && $_GET['ok'] == 1; 
 
// ========================================================= 
// 2. API AJAX (JSON) 
// ========================================================= 
if (isset($_GET['action'])) { 
    header('Content-Type: application/json'); 
 
    // Buscar Horários 
    if ($_GET['action'] === 'buscar_horarios') { 
        $data           = $_GET['data']; 
        $duracaoServico = (int)$_GET['duracao']; 
        $diaSemana      = date('w', strtotime($data)); 
 
        $stmt = $pdo->prepare("SELECT inicio, fim FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?"); 
        $stmt->execute([$profissionalId, $diaSemana]); 
        $turnos = $stmt->fetchAll(); 
 
        $stmt = $pdo->prepare("SELECT horario FROM agendamentos WHERE user_id = ? AND data_agendamento = ? AND status != 'Cancelado'"); 
        $stmt->execute([$profissionalId, $data]); 
        $ocupados = $stmt->fetchAll(); 
 
        $minutosOcupados = []; 
        foreach ($ocupados as $ag) { 
            $hm = explode(':', $ag['horario']); 
            $inicioMin = ((int)$hm[0] * 60) + (int)$hm[1]; 
            for ($m = $inicioMin; $m < ($inicioMin + $duracaoServico); $m++) { 
                $minutosOcupados[$m] = true; 
            } 
        } 
 
        $slots = []; 
        if ($turnos) { 
            foreach ($turnos as $turno) { 
                $ini   = explode(':', $turno['inicio']); 
                $fim   = explode(':', $turno['fim']); 
                $start = ($ini[0] * 60) + $ini[1]; 
                $end   = ($fim[0] * 60) + $fim[1]; 
 
                for ($time = $start; $time <= ($end - $duracaoServico); $time += 30) { 
                    $livre = true; 
                    for ($check = $time; $check < ($time + $duracaoServico); $check++) { 
                        if (isset($minutosOcupados[$check])) { 
                            $livre = false; 
                            break; 
                        } 
                    } 
                    if ($livre) { 
                        $slots[] = str_pad(floor($time / 60), 2, '0', STR_PAD_LEFT) 
                                 . ':' . 
                                   str_pad($time % 60, 2, '0', STR_PAD_LEFT); 
                    } 
                } 
            } 
        } 
        echo json_encode($slots); 
        exit; 
    } 
 
    // Buscar Cliente 
    if ($_GET['action'] === 'buscar_cliente') { 
        $telefone = preg_replace('/[^0-9]/', '', $_GET['telefone']); 
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ? AND user_id = ? LIMIT 1"); 
        $stmt->execute([$telefone, $profissionalId]); 
        $cliente = $stmt->fetch(); 
 
        if ($cliente) { 
            echo json_encode([ 
                'found'           => true, 
                'nome'            => $cliente['nome'], 
                'telefone'        => $cliente['telefone'], 
                'data_nascimento' => $cliente['data_nascimento'] 
            ]); 
        } else { 
            echo json_encode(['found' => false]); 
        } 
        exit; 
    } 
    exit; 
} 
 
// ========================================================= 
// 3. PROCESSAR POST (AGENDAMENTO) 
// ========================================================= 
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $nome       = $_POST['cliente_nome'] ?? ''; 
    $telefone   = preg_replace('/[^0-9]/', '', $_POST['cliente_telefone'] ?? ''); 
    $nascimento = !empty($_POST['cliente_nascimento']) ? $_POST['cliente_nascimento'] : null; 
    $obs        = $_POST['cliente_obs'] ?? ''; 
    $servicoId  = $_POST['servico_id'] ?? null; 
    $data       = $_POST['data_escolhida'] ?? ''; 
    $horario    = $_POST['horario_escolhido'] ?? ''; 
 
    $stmt = $pdo->prepare("SELECT nome, preco FROM servicos WHERE id = ?"); 
    $stmt->execute([$servicoId]); 
    $servicoDados = $stmt->fetch(); 
    $servicoNome  = $servicoDados['nome']  ?? 'Serviço'; 
    $servicoValor = $servicoDados['preco'] ?? 0; 
 
    if ($nome && $telefone && $data && $horario) { 
        // Cliente 
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ? AND user_id = ?"); 
        $stmt->execute([$telefone, $profissionalId]); 
        $existing = $stmt->fetch(); 
 
        if ($existing) { 
            $clienteId = $existing['id']; 
            $pdo->prepare("UPDATE clientes SET nome=?, telefone=?, data_nascimento=? WHERE id=?") 
                ->execute([$nome, $telefone, $nascimento, $clienteId]); 
        } else { 
            $pdo->prepare("INSERT INTO clientes (user_id, nome, telefone, data_nascimento) 
                           VALUES (?, ?, ?, ?)") 
                ->execute([$profissionalId, $nome, $telefone, $nascimento]); 
            $clienteId = $pdo->lastInsertId(); 
        } 
 
        // Agendamento 
        $sql = "INSERT INTO agendamentos 
            (user_id, cliente_id, cliente_nome, servico, valor, data_agendamento, horario, status, observacoes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)"; 
        $pdo->prepare($sql)->execute([ 
            $profissionalId, 
            $clienteId, 
            $nome, 
            $servicoNome, 
            $servicoValor, 
            $data, 
            $horario, 
            $obs 
        ]); 
 
        // Consumir estoque conforme cálculo vinculado ao serviço (usando servico_id direto) 
        require_once __DIR__ . '/includes/estoque_helper.php'; 
        consumirEstoquePorServico($pdo, $profissionalId, (int)$servicoId); 
 
        // 🔹 Descobre se está em produção (salao.develoi.com) ou local
        $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        $agendarUrl = $isProd
            ? '/agendar'
            : '/karen_site/controle-salao/agendar.php';

        // Envia dados do agendamento pela URL
        $params = [
            'user'    => $profissionalId,
            'ok'      => 1,
            'servico' => $servicoNome,
            'data'    => $data,
            'hora'    => $horario,
        ];

        header('Location: ' . $agendarUrl . '?' . http_build_query($params));
        exit;

    } 
} 
 
// Serviços 
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY nome ASC"); 
$stmt->execute([$profissionalId]); 
$servicos = $stmt->fetchAll(); 
?> 
<!DOCTYPE html> 
<html lang="pt-br"> 
<head> 
        <meta charset="UTF-8"> 
        <meta name="viewport" 
            content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
        <title><?php echo htmlspecialchars($nomeEstabelecimento); ?> | Agendamento</title> 

        <?php
        // Favicon dinâmico conforme ambiente
        $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        if ($isProd) {
          $faviconUrl = 'https://salao.develoi.com/img/logo-azul.png';
        } else {
          $host = $_SERVER['HTTP_HOST'];
          $faviconUrl = "http://{$host}/karen_site/controle-salao/img/logo-azul.png";
        }
        ?>
        <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
        <link rel="shortcut icon" href="<?php echo $faviconUrl; ?>" type="image/png">

        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" 
            rel="stylesheet"> 
        <link rel="stylesheet" 
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"> 

        <style> 
        /* --- SISTEMA DE CORES DINÂMICO --- */ 
        :root { 
            /* PHP injeta a cor escolhida no painel de Configurações */ 
            --brand-color: <?php echo $corPersonalizada; ?>; 
 
            /* Variações simuladas a partir da cor principal */ 
            --brand-dark: color-mix(in srgb, var(--brand-color), black 20%); 
            --brand-light: color-mix(in srgb, var(--brand-color), white 90%); 
 
            --bg-body: #f8fafc; 
            --bg-card: #ffffff; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border: #e2e8f0; 
            --radius-lg: 24px; 
            --radius-md: 16px; 
            --shadow-card: 0 4px 6px -1px rgba(0,0,0,0.05), 
                           0 2px 4px -1px rgba(0,0,0,0.03); 
        } 
 
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Outfit', sans-serif; 
            -webkit-tap-highlight-color: transparent; 
        } 
 
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        } 
 
        .app-container { display: grid; grid-template-columns: 1fr; min-height: 100vh; } 
 
        .sidebar { 
            background: white; 
            padding: 40px 30px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center; 
            border-bottom: 1px solid var(--border); 
            position: relative; 
            overflow: hidden; 
        } 
 
        .sidebar::before { 
            content: ''; 
            position: absolute; 
            top: 0; left: 0; right: 0; 
            height: 150px; 
            background: linear-gradient(to bottom, var(--brand-light), transparent); 
            z-index: 0; 
        } 
 
        .business-logo { 
            width: 120px; 
            height: 120px; 
            border-radius: 35px; 
            background: white; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.08); 
            margin-bottom: 20px; 
            z-index: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            border: 4px solid white; 
        } 
 
        .logo-img { width: 100%; height: 100%; object-fit: cover; } 
        .logo-initial { font-size: 3rem; font-weight: 800; color: var(--brand-color); } 
 
        .business-name { 
            font-size: 1.75rem; 
            font-weight: 800; 
            margin-bottom: 5px; 
            color: var(--text-main); 
            z-index: 1; 
            line-height: 1.2; 
        } 
 
        .business-bio { 
            font-size: 0.95rem; 
            color: var(--text-muted); 
            margin-bottom: 20px; 
            max-width: 350px; 
            z-index: 1; 
        } 
 
        .info-pill { 
            background: var(--bg-body); 
            padding: 8px 16px; 
            border-radius: 50px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--text-main); 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            margin-bottom: 8px; 
            z-index: 1; 
        } 
 
        .info-pill i { color: var(--brand-color); } 
 
        .main-content { 
            padding: 20px; 
            width: 100%; 
            max-width: 600px; 
            margin: 0 auto; 
            z-index: 2; 
        } 
 
        @media (min-width: 900px) { 
            .app-container { grid-template-columns: 420px 1fr; } 
            .sidebar { 
                border-right: 1px solid var(--border); 
                border-bottom: none; 
                height: 100vh; 
                position: sticky; 
                top: 0; 
                justify-content: center; 
            } 
            .main-content { 
                padding: 60px; 
                max-width: 800px; 
                margin: 0; 
                display: flex; 
                flex-direction: column; 
                justify-content: center; 
            } 
        } 
 
        .step-progress { display:flex; justify-content:space-between; margin-bottom:40px; position:relative; padding:0 10px; } 
        .progress-line { position:absolute; top:15px; left:20px; right:20px; height:3px; background:#e2e8f0; z-index:0; } 
        .step-dot { 
            width:34px; height:34px; border-radius:50%; 
            background:white; border:2px solid #e2e8f0; 
            z-index:1; display:flex; align-items:center; justify-content:center; 
            font-weight:700; color:#94a3b8; transition:0.3s; 
        } 
        .step-label { 
            position:absolute; top:40px; font-size:0.75rem; font-weight:600; 
            color:#94a3b8; transform:translateX(-50%); left:50%; white-space:nowrap; 
        } 
        .step-wrapper { position:relative; display:flex; flex-direction:column; align-items:center; } 
        .step-wrapper.active .step-dot { 
            border-color:var(--brand-color); background:var(--brand-color); color:white; 
            transform:scale(1.1); box-shadow:0 0 0 4px var(--brand-light); 
        } 
        .step-wrapper.active .step-label { color:var(--brand-color); } 
        .step-wrapper.done .step-dot { border-color:var(--brand-color); background:var(--brand-color); color:white; } 
 
        .card-title { font-size:1.5rem; font-weight:700; margin-bottom:10px; color:var(--text-main); } 
        .card-subtitle { color:var(--text-muted); margin-bottom:30px; } 
 
        .service-card { 
            background:white; padding:20px; border-radius:var(--radius-md); 
            border:2px solid transparent; box-shadow:var(--shadow-card); 
            cursor:pointer; transition:0.2s; display:flex; justify-content:space-between; 
            align-items:center; margin-bottom:12px; 
        } 
        .service-card:hover { transform:translateY(-3px); border-color:var(--brand-light); } 
        .service-card.selected { border-color:var(--brand-color); background:var(--brand-light); } 
        .service-price { font-weight:700; color:var(--brand-color); font-size:1.1rem; } 
 
        .form-label { display:block; font-size:0.9rem; font-weight:600; margin-bottom:8px; } 
        .form-control { 
            width:100%; padding:14px; border:2px solid var(--border); 
            border-radius:var(--radius-md); font-size:1rem; outline:none; 
            transition:0.3s; background:white; 
        } 
        .form-control:focus { border-color:var(--brand-color); } 
 
        .btn-action { 
            width:100%; padding:18px; background:var(--brand-color); 
            color:white; border:none; border-radius:50px; font-size:1rem; 
            font-weight:700; cursor:pointer; transition:0.3s; 
            display:flex; align-items:center; justify-content:center; gap:10px; 
            margin-top:30px; box-shadow:0 4px 15px rgba(0,0,0,0.1); 
        } 
        .btn-action:hover { background:var(--brand-dark); transform:translateY(-2px); } 
        .btn-action:disabled { background:#cbd5e1; cursor:not-allowed; transform:none; } 
 
        .step-screen { display:none; animation:fadeIn 0.4s ease-out forwards; } 
        .step-screen.active { display:block; } 
        @keyframes fadeIn { 
            from { opacity:0; transform:translateY(10px); } 
            to   { opacity:1; transform:translateY(0); } 
        } 
 
        .time-slots-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(80px,1fr)); gap:10px; } 
        .time-slot { 
            padding:12px; text-align:center; background:white; 
            border:1px solid var(--border); border-radius:10px; 
            font-weight:600; cursor:pointer; transition:0.2s; 
        } 
        .time-slot:hover { border-color:var(--brand-color); color:var(--brand-color); } 
        .time-slot.selected { background:var(--brand-color); color:white; border-color:var(--brand-color); } 
 
        .btn-back { 
            background:none; border:none; color:var(--text-muted); font-weight:600; 
            cursor:pointer; margin-bottom:20px; display:inline-flex; align-items:center; gap:8px; 
        } 
 
        .loading-spinner { 
            display:inline-block; width:20px; height:20px; 
            border:3px solid rgba(255,255,255,0.3); border-radius:50%; 
            border-top-color:white; animation:spin 1s infinite; 
        } 
        @keyframes spin { to { transform:rotate(360deg); } } 
    </style> 
</head> 
<body> 
 
<div class="app-container"> 
    <div class="sidebar"> 
        <div class="business-logo"> 
            <?php if ($temFoto): ?> 
                <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" alt="Logo" class="logo-img"> 
            <?php else: ?> 
                <div class="logo-initial"><?php echo $iniciais; ?></div> 
            <?php endif; ?> 
        </div> 
 
        <h1 class="business-name"><?php echo htmlspecialchars($nomeEstabelecimento); ?></h1> 
 
        <?php if ($nomeEstabelecimento !== $nomeProfissional): ?> 
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:10px;"> 
                Responsável: <?php echo htmlspecialchars($nomeProfissional); ?> 
            </p> 
        <?php endif; ?> 
 
        <p class="business-bio"><?php echo htmlspecialchars($biografia); ?></p> 
 
        <?php if ($telefone): ?> 
            <div class="info-pill"> 
                <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($telefone); ?> 
            </div> 
        <?php endif; ?> 
 
        <?php if ($enderecoCompleto): ?> 
            <div class="info-pill"> 
                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($enderecoCompleto); ?> 
            </div> 
        <?php endif; ?> 
 
        <div id="bookingSummary" 
             style="margin-top:auto; width:100%; background:white; padding:20px; border-radius:20px; border:1px solid #e2e8f0; text-align:left; display:none; box-shadow:var(--shadow-card);" 
             class="step-screen"> 
            <div style="font-size:0.7rem; text-transform:uppercase; color:#94a3b8; font-weight:800; margin-bottom:10px; letter-spacing:1px;"> 
                Seu Agendamento 
            </div> 
            <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-weight:700; font-size:1rem;"> 
                <span id="sumServico">...</span> 
                <span id="sumPreco" style="color:var(--brand-color);">...</span> 
            </div> 
            <div style="font-size:0.9rem; color:#64748b;" id="sumDataHora"></div> 
        </div> 
    </div> 
 
    <div class="main-content"> 
        <?php if ($sucesso): ?> 
            <div style="text-align:center; padding:40px;"> 
                <i class="bi bi-check-circle-fill" 
                   style="font-size:5rem; color:#10b981; margin-bottom:20px; display:block;"></i> 
                <h2 class="card-title">Agendamento Confirmado!</h2> 
                <p class="card-subtitle">
                    Serviço: <strong><?php echo htmlspecialchars($servicoConfirmado); ?></strong><br>
                    Data/Hora: <strong><?php echo htmlspecialchars($dataConfirmada); ?> <?php echo htmlspecialchars($horaConfirmada); ?></strong>
                </p>
                <?php
                $whats = preg_replace('/[^0-9]/', '', $profissional['telefone'] ?? '');
                $msg   = rawurlencode(
                    "Olá! Acabei de agendar o serviço: {$servicoConfirmado} para {$dataConfirmada} às {$horaConfirmada}. Obrigado!"
                );
                ?>
                <?php if ($whats): ?>
                    <a href="https://wa.me/<?php echo $whats; ?>?text=<?php echo $msg; ?>" 
                       target="_blank" class="btn-action" style="background:#25d366; color:white; margin-bottom:10px; display:inline-block;"> 
                        <i class="bi bi-whatsapp"></i> Confirmar pelo WhatsApp 
                    </a> 
                <?php endif; ?> 
                <a href="?user=<?php echo $profissionalId; ?>" class="btn-action" style="text-decoration:none;"> 
                    Agendar Novamente 
                </a> 
            </div> 
        <?php else: ?> 
 
            <div class="step-progress"> 
                <div class="progress-line"></div> 
                <div class="step-wrapper active" id="dot1"> 
                    <div class="step-dot">1</div> 
                    <div class="step-label">Serviço</div> 
                </div> 
                <div class="step-wrapper" id="dot2"> 
                    <div class="step-dot">2</div> 
                    <div class="step-label">Data</div> 
                </div> 
                <div class="step-wrapper" id="dot3"> 
                    <div class="step-dot">3</div> 
                    <div class="step-label">Finalizar</div> 
                </div> 
            </div> 
 
            <form method="POST" id="agendaForm" onsubmit="return startSubmit()"> 
                <input type="hidden" name="servico_id" id="inServicoId"> 
                <input type="hidden" name="data_escolhida" id="inData"> 
                <input type="hidden" name="horario_escolhido" id="inHorario"> 
 
                <div class="step-screen active" id="step1"> 
                    <h2 class="card-title">Selecione o serviço</h2> 
                    <p class="card-subtitle">O que vamos fazer hoje?</p> 
 
                    <div> 
                        <?php foreach ($servicos as $s): ?> 
                            <div class="service-card" 
                                 onclick="selectService(this, '<?php echo $s['id']; ?>', '<?php echo $s['nome']; ?>', '<?php echo $s['preco']; ?>', '<?php echo $s['duracao']; ?>')"> 
                                <div> 
                                    <h3 style="font-size:1rem; font-weight:700;"><?php echo htmlspecialchars($s['nome']); ?></h3> 
                                    <div style="font-size:0.85rem; color:var(--text-muted);"> 
                                        <?php echo $s['duracao']; ?> min 
                                    </div> 
                                </div> 
                                <div class="service-price"> 
                                    R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?> 
                                </div> 
                            </div> 
                        <?php endforeach; ?> 
 
                        <?php if (empty($servicos)): ?> 
                            <div style="text-align:center; padding:30px; color:#999;"> 
                                Nenhum serviço disponível. 
                            </div> 
                        <?php endif; ?> 
                    </div> 
                </div> 
 
                <div class="step-screen" id="step2"> 
                    <button type="button" class="btn-back" onclick="goToStep(1)"> 
                        <i class="bi bi-arrow-left"></i> Escolher outro serviço 
                    </button> 
                    <h2 class="card-title">Escolha o horário</h2> 
                    <p class="card-subtitle">Toque na data e veja os horários livres.</p> 
 
                    <div style="margin-bottom:20px;"> 
                        <input type="date" id="dateInput" class="form-control" 
                               onchange="fetchTimes()" style="padding:16px;"> 
                    </div> 
 
                    <div id="loadingTimes" style="display:none; text-align:center; padding:20px; color:var(--brand-color);"> 
                        <i class="bi bi-arrow-repeat" 
                           style="animation:spin 1s infinite; display:inline-block; font-size:1.5rem;"></i> 
                    </div> 
 
                    <div id="timesContainer" class="time-slots-grid"></div> 
                    <div id="noTimesMsg" 
                         style="display:none; text-align:center; color:#ef4444; margin-top:20px;"> 
                        Sem horários livres nesta data. 
                    </div> 
                </div> 
 
                <div class="step-screen" id="step3"> 
                    <button type="button" class="btn-back" onclick="goToStep(2)"> 
                        <i class="bi bi-arrow-left"></i> Trocar horário 
                    </button> 
                    <h2 class="card-title">Seus dados</h2> 
                    <p class="card-subtitle">Para confirmarmos sua reserva.</p> 
 
                    <div class="form-group"> 
                        <label class="form-label">Celular / WhatsApp</label> 
                        <input type="tel" name="cliente_telefone" id="telInput" class="form-control" 
                               placeholder="(11) 99999-9999" maxlength="15" 
                               oninput="maskPhone(this)" onblur="checkClient()"> 
                        <div id="cpfLoading" 
                             style="display:none; font-size:0.85rem; color:var(--text-muted); margin-top:5px;"> 
                            Verificando... 
                        </div> 
                    </div> 
 
                    <div id="welcomeCard" 
                         style="display:none; background:var(--brand-light); padding:15px; border-radius:12px; color:var(--brand-color); align-items:center; gap:10px; margin-bottom:20px; border:1px solid var(--brand-color);"> 
                        <i class="bi bi-person-check-fill" style="font-size:1.5rem;"></i> 
                        <div> 
                            <strong>Olá, <span id="clientNameDisplay"></span>!</strong><br> 
                            <small>Seus dados foram carregados.</small> 
                        </div> 
                    </div> 
 
                    <div id="newClientFields" style="margin-top: 20px;">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" name="cliente_nome" id="nomeInput" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nascimento (Opcional)</label>
                            <input type="date" name="cliente_nascimento" id="nascInput" class="form-control">
                        </div>
                    </div>
 
                    <div class="form-group" style="margin-top:20px;"> 
                        <label class="form-label">Observação (Opcional)</label> 
                        <textarea name="cliente_obs" class="form-control" rows="2"></textarea> 
                    </div> 
 
                    <button type="submit" id="btnConfirmar" class="btn-action" disabled> 
                        Confirmar Agendamento 
                    </button> 
                </div> 
            </form> 
        <?php endif; ?> 
    </div> 
</div> 
 
<script>
    const PROF_ID = <?php echo $profissionalId; ?>;
    const CURRENT_PAGE = "<?php echo basename($_SERVER['PHP_SELF']); ?>";
    let currentServiceDuration = 0;

    function goToStep(step) {
        document.querySelectorAll('.step-screen').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');

        document.querySelectorAll('.step-wrapper').forEach((el, index) => {
            el.classList.remove('active', 'done');
            if (index + 1 === step) el.classList.add('active');
            if (index + 1 < step) el.classList.add('done');
        });
    }

    function selectService(el, id, nome, preco, duracao) {
        document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');

        document.getElementById('inServicoId').value = id;
        currentServiceDuration = parseInt(duracao);

        document.getElementById('bookingSummary').style.display = 'block';
        document.getElementById('sumServico').innerText = nome;
        document.getElementById('sumPreco').innerText =
            'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');

        document.getElementById('inData').value = '';
        document.getElementById('inHorario').value = '';
        document.getElementById('sumDataHora').innerText = '';

        setTimeout(() => goToStep(2), 200);
    }

    function fetchTimes() {
        const dateVal = document.getElementById('dateInput').value;
        if (!dateVal) return;

        const loader = document.getElementById('loadingTimes');
        const container = document.getElementById('timesContainer');
        const noTimes = document.getElementById('noTimesMsg');

        container.innerHTML = '';
        noTimes.style.display = 'none';
        loader.style.display = 'block';

        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_horarios&data=${dateVal}&duracao=${currentServiceDuration}`)
            .then(res => res.json())
            .then(slots => {
                loader.style.display = 'none';

                if (!slots.length) {
                    noTimes.style.display = 'block';
                } else {
                    slots.forEach(time => {
                        const div = document.createElement('div');
                        div.className = 'time-slot';
                        div.innerText = time;
                        div.onclick = () => selectTime(div, time, dateVal);
                        container.appendChild(div);
                    });
                }
            });
    }

    function selectTime(el, time, dateVal) {
        document.querySelectorAll('.time-slot').forEach(t => t.classList.remove('selected'));
        el.classList.add('selected');

        document.getElementById('inHorario').value = time;
        document.getElementById('inData').value = dateVal;

        const [y, m, d] = dateVal.split('-');
        document.getElementById('sumDataHora').innerText = `${d}/${m}/${y} às ${time}`;

        setTimeout(() => goToStep(3), 200);
    }

    function checkClient() {
        const telInput = document.getElementById('telInput');
        const tel = telInput.value.replace(/\D/g, '');
        const btn = document.getElementById('btnConfirmar');
        const newFields = document.getElementById('newClientFields');
        const welcome = document.getElementById('welcomeCard');
        const nomeIn = document.getElementById('nomeInput');
        const nascIn = document.getElementById('nascInput');
        const loading = document.getElementById('cpfLoading');

        if (tel.length < 10) {
            btn.disabled = true;
            return;
        }

        loading.style.display = 'block';
        welcome.style.display = 'none';
        newFields.style.display = 'none';

        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_cliente&telefone=${tel}`)
            .then(res => res.json())
            .then(data => {
                loading.style.display = 'none';
                newFields.style.display = 'block';

                if (data.found) {
                    welcome.style.display = 'flex';
                    document.getElementById('clientNameDisplay').innerText = data.nome.split(' ')[0];
                    nomeIn.value = data.nome;
                    telInput.value = data.telefone;
                    nascIn.value = data.data_nascimento;
                } else {
                    welcome.style.display = 'none';
                    nomeIn.value = '';
                    nascIn.value = '';
                }
                btn.disabled = false;
            })
            .catch(() => {
                loading.style.display = 'none';
                newFields.style.display = 'block';
                btn.disabled = false;
            });
    }

    function startSubmit() {
        const btn = document.getElementById('btnConfirmar');
        if (btn.disabled) return false;

        btn.innerHTML = '<span class="loading-spinner"></span> Processando...';
        btn.disabled = true;
        return true;
    }

    function maskPhone(i) {
        let v = i.value.replace(/\D/g, "");
        v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
        v = v.replace(/(\d)(\d{4})$/, "$1-$2");
        i.value = v;
    }

    // Data mínima = hoje
    document.getElementById('dateInput').min = new Date().toISOString().split("T")[0];
</script>
</body> 
</html>