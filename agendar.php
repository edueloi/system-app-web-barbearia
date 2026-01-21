<?php 
// ========================================================= 
// 1. CONFIGURAÇÃO E BACKEND 
// ========================================================= 
 
require_once __DIR__ . '/includes/config.php';

// Ajuste o caminho do include conforme sua estrutura de pastas 
$dbPath = 'includes/db.php'; 
if (!file_exists($dbPath)) $dbPath = '../../includes/db.php'; 
require_once $dbPath; 

// 🔹 Detecta ambiente (prod vs local) - DECLARADO NO INÍCIO
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (empty($currentPath)) {
    $currentPath = $isProd ? '/agendar' : '/karen_site/controle-salao/agendar.php';
}
 
// ID do Profissional (Pega da URL ou da rota amigável)
$profissionalId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
if ($profissionalId <= 0) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $slugCandidate = basename($requestPath ?: '');
    if ($slugCandidate && preg_match('/-([0-9]+)$/', $slugCandidate, $matches)) {
        $profissionalId = (int)$matches[1];
    }
}
 
// Se não tiver ID na URL, retorna erro
if ($profissionalId <= 0) { 
    die('<div style="font-family:sans-serif;text-align:center;padding:50px;color:#ef4444;">❌ Link inválido. Use o link completo de agendamento.</div>'); 
} 
 
// Busca dados COMPLETOS do profissional/estabelecimento 
$stmtProf = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1"); 
$stmtProf->execute([$profissionalId]); 
$profissional = $stmtProf->fetch(); 
 
if (!$profissional) {
    die('<div style="font-family:sans-serif;text-align:center;padding:50px;color:#ef4444;">❌ Profissional não encontrado.</div>');
} 
 
// --- LÓGICA DE EXIBIÇÃO (Negócio vs Profissional) --- 
$nomeEstabelecimento = !empty($profissional['estabelecimento']) ? $profissional['estabelecimento'] : $profissional['nome']; 
$nomeProfissional    = $profissional['nome']; 
$telefone            = !empty($profissional['telefone']) ? $profissional['telefone'] : ''; 
$instagram           = !empty($profissional['instagram']) ? $profissional['instagram'] : ''; 
$biografia            = !empty($profissional['biografia']) ? $profissional['biografia'] : 'Agende seu horário com a gente!'; 
$tipoEstabelecimento = !empty($profissional['tipo_estabelecimento']) ? $profissional['tipo_estabelecimento'] : 'Salão de Beleza';

// --- MAPEAMENTO DE ÍCONES POR TIPO DE ESTABELECIMENTO ---
$iconesEstabelecimento = [
    'Salão de Beleza' => 'bi-scissors',
    'Barbearia'       => 'bi-brush',
    'Nail Art'        => 'bi-gem',
    'Estética'        => 'bi-stars',
    'Spa'             => 'bi-droplet-half',
    'Studio'          => 'bi-palette'
];
$iconeServico = $iconesEstabelecimento[$tipoEstabelecimento] ?? 'bi-scissors'; 

// URL pÃºblica amigÃ¡vel
$nomeBaseSlug = !empty($profissional['estabelecimento']) ? $profissional['estabelecimento'] : ($profissional['nome'] ?? 'Estabelecimento');
$publicSlug = buildAgendarSlug($nomeBaseSlug, $profissionalId);
$publicUrl = rtrim(BASE_URL, '/') . '/' . $publicSlug;
 
// Endereço Formatado 
$enderecoCompleto = $profissional['endereco'] ?? ''; 
if (!empty($profissional['numero'])) $enderecoCompleto .= ', ' . $profissional['numero']; 
if (!empty($profissional['bairro'])) $enderecoCompleto .= ' - ' . $profissional['bairro']; 
 
// Foto / Logo 
$fotoPerfil = ''; 
$temFoto    = false;

// Gerar iniciais (primeira letra do primeiro nome + primeira letra do último nome)
$partesNome = explode(' ', trim($nomeEstabelecimento));
if (count($partesNome) > 1) {
    // Tem nome e sobrenome
    $primeiraLetra = mb_substr($partesNome[0], 0, 1);
    $ultimaLetra = mb_substr(end($partesNome), 0, 1);
    $iniciais = strtoupper($primeiraLetra . $ultimaLetra);
} else {
    // Só tem um nome
    $iniciais = strtoupper(mb_substr($nomeEstabelecimento, 0, 1));
} 
 
if (!empty($profissional['foto'])) {
    $caminhosFoto = [
        $profissional['foto'],                              // Caminho direto do banco
        __DIR__ . '/' . $profissional['foto'],              // Relativo ao agendar.php
        'uploads/' . basename($profissional['foto']),       // Na pasta uploads
        __DIR__ . '/uploads/' . basename($profissional['foto']), // uploads relativo
        '../uploads/' . basename($profissional['foto'])     // uploads um nível acima
    ];
    
    foreach ($caminhosFoto as $caminho) {
        if (file_exists($caminho)) {
            // Determina o caminho web correto
            if (strpos($caminho, __DIR__) === 0) {
                // Remove __DIR__ e ajusta para caminho web
                $fotoPerfil = str_replace('\\', '/', str_replace(__DIR__, '', $caminho));
            } else {
                $fotoPerfil = str_replace('\\', '/', $profissional['foto']);
            }
            $temFoto = true;
            break;
        }
    }
    
    // Debug apenas em desenvolvimento (comentar em produção)
    // if ($temFoto) error_log("Foto encontrada: $fotoPerfil");
    // else error_log("Foto não encontrada. Valor no banco: " . $profissional['foto']);
}

// ???? NORMALIZA????O DO CAMINHO DA FOTO PARA COMPARTILHAMENTO
if ($temFoto) {
    // Se j?? ?? uma URL absoluta (http/https), deixa como est??
    if (preg_match('#^https?://#', $fotoPerfil)) {
        // URL absoluta, n??o mexe
    } else {
        // Garante que ?? um caminho relativo come??ando com /
        $fotoPerfil = '/' . ltrim($fotoPerfil, '/');

        // Se estiver em localhost, prefixa com a base do projeto
        if (!$isProd) {
            $baseLocal = '/karen_site/controle-salao';
            if (strpos($fotoPerfil, $baseLocal . '/') !== 0) {
                $fotoPerfil = $baseLocal . $fotoPerfil;
            }
            $fotoPerfil = preg_replace('#^(/karen_site/controle-salao)+#', '/karen_site/controle-salao', $fotoPerfil);
        }
    }
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
$clienteConfirmado = $_GET['cliente'] ?? '';
$valorConfirmado   = $_GET['valor'] ?? '';
if ($valorConfirmado !== '') {
    $valorConfirmado = (float)str_replace(',', '.', $valorConfirmado);
} elseif (isset($servicoValor)) {
    $valorConfirmado = (float)$servicoValor;
} else {
    $valorConfirmado = 0.0;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Garante que não dará warning ao acessar fora do POST
    $_POST['data_escolhida'] = $_POST['data_escolhida'] ?? '';
    $_POST['horario_escolhido'] = $_POST['horario_escolhido'] ?? '';
}
$sucesso = isset($_GET['ok']) && $_GET['ok'] == 1; 
$erroAntecedencia = isset($_GET['erro']) && $_GET['erro'] === 'antecedencia';
$erroAntecedenciaMsg = '';
if ($erroAntecedencia) {
    $minGet = isset($_GET['min']) ? (int)$_GET['min'] : 4;
    $unGet = $_GET['un'] ?? 'horas';
    $erroAntecedenciaMsg = 'O agendamento deve ser feito com pelo menos ' . $minGet . ' ' . htmlspecialchars($unGet) . ' de antecedência.';
}
 
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
 
        $stmt = $pdo->prepare("SELECT inicio, fim, intervalo_minutos FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?"); 
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
                
                // Usa o intervalo configurado ou padrão 30min
                $intervalo = !empty($turno['intervalo_minutos']) ? (int)$turno['intervalo_minutos'] : 30;
 
                for ($time = $start; $time <= ($end - $duracaoServico); $time += $intervalo) { 
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
    
    // Verificar disponibilidade do mês (novo)
    if ($_GET['action'] === 'verificar_mes') {
        $ano = (int)$_GET['ano'];
        $mes = (int)$_GET['mes'];
        $duracaoServico = (int)($_GET['duracao'] ?? 30);
        
        $disponibilidade = [];
        $diasNoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        
        // Buscar dias especiais de fechamento (feriados, aniversários, etc)
        $stmtDiasEspeciais = $pdo->prepare("
            SELECT data, recorrente 
            FROM dias_especiais_fechamento 
            WHERE user_id = ? 
              AND (
                  (recorrente = 0 AND strftime('%Y-%m', data) = ?)
                  OR (recorrente = 1)
              )
        ");
        $stmtDiasEspeciais->execute([$profissionalId, sprintf('%04d-%02d', $ano, $mes)]);
        $diasEspeciaisFechados = [];
        
        foreach ($stmtDiasEspeciais->fetchAll() as $diaEspecial) {
            if ($diaEspecial['recorrente']) {
                // Para datas recorrentes, extrai só o dia/mês
                $dataEspecial = new DateTime($diaEspecial['data']);
                $mesEspecial = (int)$dataEspecial->format('m');
                $diaEspecial_num = (int)$dataEspecial->format('d');
                
                // Se o mês/dia bate com o mês atual, marca como fechado
                if ($mesEspecial === $mes) {
                    $diasEspeciaisFechados[$diaEspecial_num] = true;
                }
            } else {
                // Para datas únicas, compara a data completa
                $dataEspecial = new DateTime($diaEspecial['data']);
                if ((int)$dataEspecial->format('Y') === $ano && (int)$dataEspecial->format('m') === $mes) {
                    $diasEspeciaisFechados[(int)$dataEspecial->format('d')] = true;
                }
            }
        }
        
        for ($dia = 1; $dia <= $diasNoMes; $dia++) {
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            $diaSemana = date('w', strtotime($data));
            
            // Verifica se é um dia especial de fechamento
            if (isset($diasEspeciaisFechados[$dia])) {
                $disponibilidade[$dia] = 'fechado';
                continue;
            }
            
            // Verifica se tem horário de atendimento neste dia
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?");
            $stmt->execute([$profissionalId, $diaSemana]);
            $temAtendimento = $stmt->fetch()['total'] > 0;
            
            if (!$temAtendimento) {
                $disponibilidade[$dia] = 'fechado';
                continue;
            }
            
            // Busca turnos e horários ocupados
            $stmt = $pdo->prepare("SELECT inicio, fim, intervalo_minutos FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ?");
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
            
            $horariosDisponiveis = 0;
            foreach ($turnos as $turno) {
                $ini = explode(':', $turno['inicio']);
                $fim = explode(':', $turno['fim']);
                $start = ($ini[0] * 60) + $ini[1];
                $end = ($fim[0] * 60) + $fim[1];
                $intervalo = !empty($turno['intervalo_minutos']) ? (int)$turno['intervalo_minutos'] : 30;
                
                for ($time = $start; $time <= ($end - $duracaoServico); $time += $intervalo) {
                    $livre = true;
                    for ($check = $time; $check < ($time + $duracaoServico); $check++) {
                        if (isset($minutosOcupados[$check])) {
                            $livre = false;
                            break;
                        }
                    }
                    if ($livre) $horariosDisponiveis++;
                }
            }
            
            if ($horariosDisponiveis === 0) {
                $disponibilidade[$dia] = 'lotado';
            } else if ($horariosDisponiveis <= 3) {
                $disponibilidade[$dia] = 'poucos';
            } else {
                $disponibilidade[$dia] = 'disponivel';
            }
        }
        
        echo json_encode($disponibilidade);
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
    
    // Buscar Agendamentos do Cliente
    if ($_GET['action'] === 'buscar_agendamentos') {
        $telefone = preg_replace('/[^0-9]/', '', $_GET['telefone']);
        
        // Primeiro busca o cliente
        $stmt = $pdo->prepare("
            SELECT id, nome, telefone, data_nascimento 
            FROM clientes 
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ? 
              AND user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$telefone, $profissionalId]);
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            echo json_encode(['found' => false, 'agendamentos' => []]);
            exit;
        }
        
        // Busca agendamentos futuros (usando data_agendamento)
        $stmt = $pdo->prepare("
            SELECT a.*
            FROM agendamentos a
            WHERE a.cliente_id = ? 
              AND a.user_id = ?
              AND (
                    a.data_agendamento > DATE('now')
                 OR (a.data_agendamento = DATE('now') 
                     AND a.horario >= TIME('now', 'localtime'))
              )
            ORDER BY a.data_agendamento ASC, a.horario ASC
        ");
        $stmt->execute([$cliente['id'], $profissionalId]);
        $agendamentos = $stmt->fetchAll();
        
        echo json_encode([
            'found' => true,
            'cliente' => [
                'nome' => $cliente['nome'],
                'telefone' => $cliente['telefone'],
                'data_nascimento' => $cliente['data_nascimento']
            ],
            'agendamentos' => $agendamentos
        ]);
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
        // Validação de antecedência mínima
        $minAntecedencia = isset($profissional['agendamento_min_antecedencia']) ? (int)$profissional['agendamento_min_antecedencia'] : 4;
        $minUnidade = $profissional['agendamento_min_unidade'] ?? 'horas';
        $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $dataHoraAgendamento = DateTime::createFromFormat('Y-m-d H:i', $data . ' ' . $horario, new DateTimeZone('America/Sao_Paulo'));
        $diffMinutos = ($dataHoraAgendamento && $agora) ? round(($dataHoraAgendamento->getTimestamp() - $agora->getTimestamp()) / 60) : 0;
        $minutosNecessarios = 0;
        if ($minUnidade === 'minutos') {
            $minutosNecessarios = $minAntecedencia;
        } elseif ($minUnidade === 'horas') {
            $minutosNecessarios = $minAntecedencia * 60;
        } elseif ($minUnidade === 'dias') {
            $minutosNecessarios = $minAntecedencia * 1440;
        }
        if ($diffMinutos < $minutosNecessarios) {
            $query = $_GET;
            $query['erro'] = 'antecedencia';
            $query['min'] = $minAntecedencia;
            $query['un'] = $minUnidade;
            $redirectUrl = $currentPath;
            if (!empty($query)) {
                $redirectUrl .= '?' . http_build_query($query);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
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
 
        // ===============================
        // CRIAR NOTIFICAÇÃO PARA O DONO
        // ===============================
        // ID do agendamento recém-criado
        $idAgendamento = $pdo->lastInsertId();

        $mensagemNotif = sprintf(
            'Novo agendamento de %s para %s em %s às %s.',
            $nome,
            $servicoNome,
            date('d/m/Y', strtotime($data)),
            date('H:i', strtotime($horario))
        );

        // Base da agenda (prod vs local)
        $agendaBase = $isProd
            ? '/agenda'
            : '/karen_site/controle-salao/pages/agenda/agenda.php';

        // Link que vai no botão "Ver" -> abre a agenda no DIA certo e com o AGENDAMENTO destacado
        $linkNotif = $agendaBase
            . '?view=day'
            . '&data=' . urlencode($data)
            . '&focus_id=' . urlencode($idAgendamento);

        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, link, created_at, is_read)
            VALUES (?, 'agendamento', ?, ?, datetime('now'), 0)
        ");
        $stmtNotif->execute([$profissionalId, $mensagemNotif, $linkNotif]);

        // ===============================
        // NOTIFICAR PUSH WEB (SE HABILITADO)
        // ===============================
        require_once __DIR__ . '/includes/push_helper.php';
        if (function_exists('sendPushNovoAgendamento')) {
            sendPushNovoAgendamento((int)$profissionalId, $mensagemNotif, $linkNotif, (int)$idAgendamento);
        }

        // ===============================
        // NOTIFICAR BOT WHATSAPP
        // ===============================
        require_once __DIR__ . '/includes/notificar_bot.php';
        notificarBotNovoAgendamento($pdo, $idAgendamento);

        // ===============================
        // ENVIAR EMAIL DE NOTIFICAÇÃO
        // ===============================
        
        // Debug: verifica se tem email e ambiente
        $isProdEmail = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        error_log('DEBUG AGENDAMENTO: Ambiente = ' . ($_SERVER['HTTP_HOST'] ?? 'desconhecido'));
        error_log('DEBUG AGENDAMENTO: Email do profissional = ' . ($profissional['email'] ?? 'VAZIO'));
        error_log('DEBUG AGENDAMENTO: É produção? = ' . ($isProdEmail ? 'SIM' : 'NÃO (localhost)'));
        
        if (!empty($profissional['email'])) {
            require_once __DIR__ . '/includes/mailer.php';
            
            error_log('DEBUG AGENDAMENTO: Iniciando envio de email para: ' . $profissional['email']);
            
            // Template HTML bonito do email
            $emailHTML = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Agendamento</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;background-color:#f8fafc;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);">
                    
                    <!-- Header com Gradiente -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);padding:40px 30px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:800;letter-spacing:-0.5px;">
                                🎉 Novo Agendamento!
                            </h1>
                            <p style="margin:10px 0 0;color:rgba(255,255,255,0.9);font-size:14px;font-weight:500;">
                                Um cliente acabou de agendar online
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Conteúdo -->
                    <tr>
                        <td style="padding:40px 30px;">
                            <p style="margin:0 0 24px;color:#475569;font-size:15px;line-height:1.6;">
                                Olá <strong>' . htmlspecialchars($nomeProfissional) . '</strong>,
                            </p>
                            <p style="margin:0 0 30px;color:#475569;font-size:15px;line-height:1.6;">
                                Você recebeu um novo agendamento através do sistema <strong>Salão Develoi</strong>:
                            </p>
                            
                            <!-- Card de Informações -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);border-radius:12px;border:1px solid #e2e8f0;margin-bottom:30px;">
                                <tr>
                                    <td style="padding:24px;">
                                        
                                        <!-- Cliente -->
                                        <div style="margin-bottom:18px;">
                                            <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                                                👤 Cliente
                                            </div>
                                            <div style="color:#0f172a;font-size:18px;font-weight:700;">
                                                ' . htmlspecialchars($nome) . '
                                            </div>
                                            <div style="color:#64748b;font-size:14px;margin-top:4px;">
                                                📱 ' . htmlspecialchars($telefone) . '
                                            </div>
                                        </div>
                                        
                                        <!-- Serviço -->
                                        <div style="margin-bottom:18px;">
                                            <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                                                ✂️ Serviço
                                            </div>
                                            <div style="color:#0f172a;font-size:16px;font-weight:700;">
                                                ' . htmlspecialchars($servicoNome) . '
                                            </div>
                                        </div>
                                        
                                        <!-- Data e Hora -->
                                        <div style="display:flex;gap:16px;margin-bottom:18px;">
                                            <div style="flex:1;">
                                                <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                                                    📅 Data
                                                </div>
                                                <div style="color:#0f172a;font-size:16px;font-weight:700;">
                                                    ' . date('d/m/Y', strtotime($data)) . '
                                                </div>
                                            </div>
                                            <div style="flex:1;">
                                                <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                                                    ⏰ Horário
                                                </div>
                                                <div style="color:#0f172a;font-size:16px;font-weight:700;">
                                                    ' . date('H:i', strtotime($horario)) . '
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Valor -->
                                        <div style="margin-bottom:' . (!empty($obs) ? '18px' : '0') . ';">
                                            <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                                                💰 Valor
                                            </div>
                                            <div style="color:#10b981;font-size:20px;font-weight:800;">
                                                R$ ' . number_format($servicoValor, 2, ',', '.') . '
                                            </div>
                                        </div>
                                        
                                        ' . (!empty($obs) ? '
                                        <!-- Observações -->
                                        <div>
                                            <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                                                📝 Observações
                                            </div>
                                            <div style="color:#475569;font-size:14px;line-height:1.5;font-style:italic;">
                                                ' . htmlspecialchars($obs) . '
                                            </div>
                                        </div>
                                        ' : '') . '
                                        
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Botão de Ação -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:10px 0 30px;">
                                        <a href="https://salao.develoi.com' . htmlspecialchars($linkNotif) . '" 
                                           style="display:inline-block;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:999px;font-weight:700;font-size:15px;box-shadow:0 8px 20px rgba(99,102,241,0.35);">
                                            📋 Ver na Agenda
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin:0;color:#94a3b8;font-size:13px;line-height:1.6;text-align:center;">
                                Acesse o painel para confirmar ou gerenciar este agendamento
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8fafc;padding:30px;text-align:center;border-top:1px solid #e2e8f0;">
                            <p style="margin:0 0 8px;color:#64748b;font-size:13px;">
                                <strong>Salão Develoi</strong> - Sistema de Gestão
                            </p>
                            <p style="margin:0;color:#94a3b8;font-size:12px;">
                                📧 <strong>Email automático - Não responder</strong>
                            </p>
                            <p style="margin:8px 0 0;color:#94a3b8;font-size:11px;">
                                Enviado de <a href="https://salao.develoi.com" style="color:#6366f1;text-decoration:none;">salao.develoi.com</a>
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

            // Envia o email
            try {
                $enviou = sendMailDeveloi(
                    $profissional['email'],
                    $nomeProfissional,
                    '🎉 Novo Agendamento - ' . $servicoNome,
                    $emailHTML
                );
                
                if ($enviou) {
                    error_log('DEBUG: Email enviado com SUCESSO para: ' . $profissional['email']);
                } else {
                    error_log('DEBUG: Falha no envio do email para: ' . $profissional['email']);
                }
                
            } catch (Exception $e) {
                // Log do erro mas não interrompe o fluxo
                error_log('ERRO ao enviar email de notificação: ' . $e->getMessage());
            }
        } else {
            error_log('DEBUG: Email NÃO enviado - profissional sem email cadastrado');
        }

        // Consumir estoque conforme cálculo vinculado ao serviço (usando servico_id direto)
        require_once __DIR__ . '/includes/estoque_helper.php';
        consumirEstoquePorServico($pdo, $profissionalId, (int)$servicoId);

        // 🔹 Usa a variável $isProd já declarada no topo
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
            'cliente' => $nome,
            'valor'   => $servicoValor,
        ];

        header('Location: ' . $agendarUrl . '?' . http_build_query($params));
        exit;

    } 
} 
 
// Serviços, Pacotes e Itens
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY nome ASC"); 
$stmt->execute([$profissionalId]); 
$todosServicos = $stmt->fetchAll();

// Mapeia todos os serviços por ID para consulta rápida
$servicosPorId = [];
foreach ($todosServicos as $s) {
    $servicosPorId[$s['id']] = $s;
}

// Função para formatar texto de recorrência
function formatarRecorrenciaAgendamento($tipoRecorrencia, $diasSemana = null, $intervaloDias = null) {
    if (empty($tipoRecorrencia) || $tipoRecorrencia === 'sem_recorrencia') {
        return '';
    }
    
    $textos = [
        'diaria' => 'Todos os dias',
        'semanal' => 'Toda semana',
        'quinzenal' => 'A cada 15 dias',
        'mensal' => 'Todo mês',
        'personalizada' => 'Personalizado'
    ];
    
    $texto = $textos[$tipoRecorrencia] ?? 'Recorrente';
    
    // Se for semanal ou personalizada e tiver dias específicos
    if (($tipoRecorrencia === 'semanal' || $tipoRecorrencia === 'personalizada') && !empty($diasSemana)) {
        try {
            $dias = json_decode($diasSemana, true);
            if (is_array($dias) && count($dias) > 0) {
                $nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                $diasTexto = array_map(function($d) use ($nomesDias) {
                    return $nomesDias[(int)$d] ?? '';
                }, $dias);
                $texto .= ' (' . implode(', ', $diasTexto) . ')';
            }
        } catch (Exception $e) {
            // Ignora erro de parse
        }
    }
    
    // Se tiver intervalo personalizado
    if ($tipoRecorrencia === 'personalizada' && !empty($intervaloDias) && $intervaloDias > 1) {
        $texto = "A cada {$intervaloDias} dias";
    }
    
    return $texto;
}

// Separa em duas listas: Pacotes e Serviços Únicos
$pacotes = [];
$servicosUnicos = [];

foreach ($todosServicos as $s) {
    if ($s['tipo'] === 'pacote') {
        $itens = [];
        if (!empty($s['itens_pacote'])) {
            $ids = explode(',', $s['itens_pacote']);
            foreach ($ids as $id) {
                if (isset($servicosPorId[trim($id)])) {
                    $itens[] = $servicosPorId[trim($id)];
                }
            }
        }
        $s['itens_detalhados'] = $itens; // Adiciona os detalhes dos itens ao pacote
        $pacotes[] = $s;
    } else {
        $servicosUnicos[] = $s;
    }
}
 
?> 
<!DOCTYPE html> 
<html lang="pt-br"> 
<head> 
        <meta charset="UTF-8"> 
        <meta name="viewport" 
            content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
        <title><?php echo htmlspecialchars($nomeEstabelecimento); ?> - Agende Online | <?php echo htmlspecialchars($tipoEstabelecimento); ?></title>
        
        <!-- SEO Meta Tags -->
        <meta name="description" content="Agende seu horário online no <?php echo htmlspecialchars($nomeEstabelecimento); ?>. <?php echo htmlspecialchars($biografia); ?> Agendamento fácil e rápido com confirmação instantânea.">
        <meta name="keywords" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?>, agendar online, <?php echo htmlspecialchars($tipoEstabelecimento); ?>, agendamento, beleza, <?php echo !empty($profissional['bairro']) ? htmlspecialchars($profissional['bairro']) : ''; ?>, <?php echo !empty($profissional['cidade']) ? htmlspecialchars($profissional['cidade']) : ''; ?>">
        <meta name="author" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?>">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="<?php echo $corPersonalizada; ?>">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?>">
        <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
        <link rel="icon" href="favicon.ico" type="image/x-icon">
        
        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?> - Agende Online">
        <meta property="og:description" content="<?php echo htmlspecialchars($biografia); ?> Agende seu horário de forma rápida e fácil.">
        <?php 
        // Base da aplicação
        $baseUrl = $isProd 
            ? 'https://salao.develoi.com' 
            : 'http://' . $_SERVER['HTTP_HOST'] . '/karen_site/controle-salao';

        // Monta a URL da imagem para compartilhamento
        if ($temFoto) {
            // Se já é URL absoluta (https://...), usa direto
            if (preg_match('#^https?://#', $fotoPerfil)) {
                $imagemCompartilhamento = $fotoPerfil;
            } else {
                // Caminho relativo → remove barras duplicadas e junta com a base
                $fotoPerfil = '/' . ltrim($fotoPerfil, '/');
                $imagemCompartilhamento = rtrim($baseUrl, '/') . $fotoPerfil;
            }
        } else {
            // Sem foto → usa logo do sistema
            $imagemCompartilhamento = $baseUrl . '/img/logo-azul.png';
        }
        ?>
        <meta property="og:image" content="<?php echo $imagemCompartilhamento; ?>">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?>">
        <meta property="og:url" content="<?php echo htmlspecialchars($publicUrl); ?>">
        
        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:title" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?> - Agende Online">
        <meta property="twitter:description" content="<?php echo htmlspecialchars($biografia); ?>">
        <meta property="twitter:image" content="<?php echo $imagemCompartilhamento; ?>">
        
        <!-- Canonical URL -->
        <link rel="canonical" href="<?php echo htmlspecialchars($publicUrl); ?>">
        
        <!-- Schema.org para Google -->
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "LocalBusiness",
          "name": "<?php echo htmlspecialchars($nomeEstabelecimento); ?>",
          "description": "<?php echo htmlspecialchars($biografia); ?>",
          "image": "<?php echo $imagemCompartilhamento; ?>",
          <?php if ($telefone): ?>"telephone": "<?php echo htmlspecialchars($telefone); ?>",<?php endif; ?>
          <?php if ($enderecoCompleto): ?>"address": "<?php echo htmlspecialchars($enderecoCompleto); ?>",<?php endif; ?>
          "priceRange": "$$",
          "url": "<?php echo htmlspecialchars($publicUrl); ?>"
        }
        </script> 

        <?php
        // Favicon dinâmico conforme ambiente (usa variável já declarada)
        if ($isProd) {
          $faviconUrl = 'https://salao.develoi.com/img/logo-azul.png';
        } else {
          $host = $_SERVER['HTTP_HOST'];
          $faviconUrl = "http://{$host}/karen_site/controle-salao/img/logo-azul.png";
        }
        ?>
        <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
        <link rel="shortcut icon" href="<?php echo $faviconUrl; ?>" type="image/png">

        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" 
            rel="stylesheet"> 
        <link rel="stylesheet" 
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"> 

        <style> 
        /* === ESTILO PADRÃO DO PAINEL === */
        /* Fonte pequena e delicada, clean, moderno, bordas arredondadas */
        /* Fundo neutro, cards brancos, tudo responsivo */
        
        :root { 
            --brand-color: <?php echo $corPersonalizada; ?>; 
            --brand-dark: color-mix(in srgb, var(--brand-color), black 15%); 
            --brand-light: color-mix(in srgb, var(--brand-color), white 92%); 
 
            --bg-page: #f8fafc;
            --bg-card: #ffffff; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border: #e2e8f0; 
            
            --radius-sm: 8px;
            --radius-md: 12px; 
            --radius-lg: 16px; 
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-card: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-hover: 0 4px 12px rgba(0,0,0,0.12), 0 2px 6px rgba(0,0,0,0.08);
        } 
 
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
            -webkit-tap-highlight-color: transparent; 
        } 
 
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.3;
        }

        body { 
            background: var(--bg-page);
            color: var(--text-main); 
            font-size: 0.875rem;
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            position: relative;
            padding-bottom: 70px;
        }

        .app-container { 
            margin-bottom: 20px; 
            display: grid; 
            grid-template-columns: 1fr; 
            min-height: 100vh; 
        } 
 
        .sidebar { 
            background: var(--bg-card);
            padding: 2rem 1.5rem; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center; 
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        } 
 
        .business-logo { 
            width: 5.5rem;
            height: 5.5rem;
            border-radius: 50%; 
            background: var(--bg-card);
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            border: 3px solid var(--border);
            transition: all 0.25s ease;
        }
        
        .business-logo:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            border-color: var(--brand-color);
        }
 
        .logo-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover;
        } 
        
        .logo-initial { 
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--brand-color);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .success-logo {
            width: 5rem;
            height: 5rem;
            margin: 0 auto 1.25rem;
            background: var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-card);
            border: 3px solid #10b981;
            overflow: hidden;
        }

        .success-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .success-logo .logo-initial {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brand-color);
        } 
 
        .business-name { 
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.375rem;
            color: var(--text-main); 
            line-height: 1.3; 
        } 
 
        .business-bio { 
            font-size: 0.8125rem;
            color: var(--text-muted); 
            margin-bottom: 1.25rem;
            max-width: 22rem;
            line-height: 1.5;
        } 
 
        .info-pill { 
            background: var(--bg-card);
            padding: 0.5rem 0.875rem;
            border-radius: 999px; 
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-main); 
            display: inline-flex; 
            align-items: center; 
            gap: 0.375rem;
            margin-bottom: 0.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        } 
 
        .info-pill i {
            color: var(--brand-color);
            font-size: 0.875rem;
        } 
 
        .main-content { 
            padding: 1.25rem;
            width: 100%; 
            max-width: 38rem;
            margin: 0 auto; 
        } 
 
        @media (max-width: 768px) {
            body {
                font-size: 0.8125rem;
            }
            .card-title {
                font-size: 1rem;
            }
            .business-name {
                font-size: 1rem;
            }
            .business-logo {
                width: 4.5rem;
                height: 4.5rem;
            }
            .business-bio {
                font-size: 0.75rem;
                max-width: 18rem;
            }
            .step-label {
                font-size: 0.625rem;
            }
            .time-slots-grid {
                grid-template-columns: repeat(auto-fill, minmax(4.5rem, 1fr));
                gap: 0.5rem;
            }
            .main-content {
                padding: 0.875rem;
            }
            .service-card {
                grid-template-columns: 1fr auto;
                padding: 0.75rem;
                gap: 0.625rem;
            }
            .service-card.no-image {
                padding: 0.75rem;
            }
            .service-img {
                width: 3.5rem;
                height: 3.5rem;
            }
            .service-content {
                padding: 0;
            }
            .service-title {
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }
            .service-description {
                font-size: 0.75rem;
                -webkit-line-clamp: 1;
                margin-bottom: 0.375rem;
            }
            .service-duration {
                font-size: 0.6875rem;
                padding: 0.1875rem 0.5rem;
            }
            .service-price-wrapper {
                padding: 0;
                align-items: center;
                justify-content: flex-end;
                gap: 0.25rem;
            }
            .service-price {
                font-size: 0.9375rem;
            }
        }

        @media (min-width: 900px) { 
            .app-container { 
                grid-template-columns: 22rem 1fr; 
            } 
            .sidebar { 
                border-right: 1px solid var(--border);
                border-bottom: none; 
                height: 100vh; 
                position: sticky; 
                top: 0; 
                justify-content: center; 
            } 
            .main-content { 
                padding: 3rem;
                max-width: 56rem;
                margin: 0 auto; 
                display: flex; 
                flex-direction: column; 
                justify-content: center; 
            } 
        } 
 
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
            padding: 0 0.5rem;
        } 
        .progress-line {
            position: absolute;
            top: 1rem;
            left: 1.25rem;
            right: 1.25rem;
            height: 2px;
            background: var(--border);
            z-index: 0;
        } 
        .step-dot { 
            width: 2rem;
            height: 2rem;
            border-radius: 50%; 
            background: var(--bg-card);
            border: 2px solid var(--border);
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center; 
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-muted);
            transition: all 0.2s ease;
        } 
        .step-label { 
            position: absolute;
            top: 2.5rem;
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--text-muted);
            transform: translateX(-50%);
            left: 50%;
            white-space: nowrap; 
        } 
        .step-wrapper {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        } 
        .step-wrapper.active .step-dot { 
            border-color: var(--brand-color);
            background: var(--brand-color);
            color: white; 
            transform: scale(1.1);
        } 
        .step-wrapper.active .step-label {
            color: var(--brand-color);
            font-weight: 600;
        } 
        .step-wrapper.done .step-dot {
            border-color: var(--brand-color);
            background: var(--brand-color);
            color: white;
        } 
 
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        } 
        .card-subtitle {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            font-size: 0.8125rem;
            line-height: 1.5;
        } 
 
        .service-card { 
            background: var(--bg-card);
            padding: 0;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-card);
            cursor: pointer;
            transition: all 0.2s ease;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 0.75rem;
        }
        
        .service-card:hover {
            transform: translateY(-1px);
            border-color: var(--brand-color);
            box-shadow: var(--shadow-hover);
        }
        
        .service-card.selected {
            border-color: var(--brand-color);
            background: var(--bg-card);
            box-shadow: 0 0 0 2px var(--brand-color), var(--shadow-hover);
        }
        
        .service-card.selected .service-img {
            border-color: var(--brand-color);
        }
        
        .service-card.selected .service-img::after {
            content: '✓';
            position: absolute;
            bottom: -0.25rem;
            right: -0.25rem;
            width: 1.25rem;
            height: 1.25rem;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.625rem;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
        }
        
        .service-img {
            width: 4.5rem;
            height: 4.5rem;
            border-radius: 50%;
            background: var(--bg-page);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
            border: 2px solid var(--border);
            transition: all 0.2s ease;
        }
        
        .service-card.no-image {
            grid-template-columns: 1fr auto;
            padding: 0.875rem 1rem;
        }
        
        .service-card.no-image .service-content {
            padding: 0;
        }
        
        .service-card.no-image .service-price-wrapper {
            padding: 0;
        }
        
        .service-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .service-img-placeholder {
            font-size: 1.5rem;
            color: var(--brand-color);
            opacity: 0.5;
        }
        
        .service-content {
            flex: 1;
            padding: 0.875rem 0;
            min-width: 0;
        }
        
        .service-title {
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-main);
        }
        
        .service-description {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 0.375rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .service-duration {
            font-size: 0.6875rem;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: var(--bg-page);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
        }
        
        .service-price-wrapper {
            padding: 0.875rem 1rem 0.875rem 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.375rem;
        }
        
        .service-price {
            font-weight: 700;
            color: var(--brand-color);
            font-size: 1rem;
            white-space: nowrap;
        } 
 
        .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        } 
        .form-control { 
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            outline: none; 
            transition: all 0.2s ease;
            background: var(--bg-card);
        } 
        .form-control:focus {
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px var(--brand-light);
        } 
 
        .btn-action { 
            width: 100%;
            padding: 0.875rem 1.25rem;
            background: var(--brand-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            box-shadow: var(--shadow-card);
            text-decoration: none;
        } 
        .btn-action:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-hover);
            color: white;
        } 
        .btn-action:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        } 

        @media (min-width: 768px) {
            #installCta {
                display: none !important;
            }
        }
 
        .step-screen { 
            display: none;
        } 
        .step-screen.active { 
            display: block;
            animation: fadeIn 0.3s ease-out;
        } 
        @keyframes fadeIn { 
            from { opacity: 0; } 
            to { opacity: 1; } 
        } 
 
        .calendar-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
            position: relative;
        }
        
        .calendar-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg-card);
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            display: none;
            align-items: center;
            gap: 0.5rem;
            z-index: 10;
        }
        
        .calendar-loading.active {
            display: flex;
        }
        
        .calendar-loading i {
            color: var(--brand-color);
            font-size: 0.875rem;
            animation: spin 1s infinite linear;
        }
        
        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        
        .calendar-month-year {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-main);
        }
        
        .calendar-nav-btn {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }
        
        .calendar-nav-btn:hover {
            background: var(--brand-color);
            color: white;
            border-color: var(--brand-color);
        }
        
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }
        
        .calendar-weekday {
            text-align: center;
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--text-muted);
            padding: 0.375rem 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.8125rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-card);
            border: 1px solid transparent;
            color: var(--text-main);
            position: relative;
        }
        
        .calendar-day:hover:not(.disabled):not(.empty) {
            background: var(--bg-page);
            border-color: var(--brand-color);
            color: var(--brand-color);
        }
        
        .calendar-day.today {
            border-color: var(--brand-color);
            background: var(--brand-light);
            font-weight: 600;
        }
        
        .calendar-day.disabled {
            color: #cbd5e1;
            cursor: not-allowed;
            background: var(--bg-page);
        }
        
        .calendar-day.fechado {
            background: #fee2e2;
            color: #ef4444;
            cursor: not-allowed;
            text-decoration: line-through;
            opacity: 0.6;
        }
        
        .calendar-day.fechado::before {
            content: '✕';
            position: absolute;
            top: 0.125rem;
            right: 0.125rem;
            font-size: 0.5rem;
            color: #ef4444;
        }
        
        .calendar-day.lotado {
            background: #fef3c7;
            color: #92400e;
            cursor: not-allowed;
            border-color: #fbbf24;
        }
        
        .calendar-day.poucos {
            background: #fff7ed;
            border-color: #fb923c;
            color: #9a3412;
            font-weight: 600;
        }
        
        .calendar-day.disponivel {
            background: var(--bg-card);
            border-color: transparent;
        }
        
        .calendar-day.selected {
            background: var(--brand-color) !important;
            color: white !important;
            border-color: var(--brand-color) !important;
            font-weight: 600;
        }
        
        .calendar-day.empty {
            cursor: default;
            background: transparent;
        }
        
        @media (max-width: 768px) {
            .calendar-container {
                padding: 0.875rem;
            }
            
            .calendar-month-year {
                font-size: 0.875rem;
            }
            
            .calendar-nav-btn {
                width: 1.75rem;
                height: 1.75rem;
            }
            
            .calendar-day {
                font-size: 0.75rem;
            }
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr));
            gap: 0.5rem;
        } 
        .time-slot { 
            padding: 0.75rem;
            text-align: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.8125rem;
            cursor: pointer;
            transition: all 0.2s ease;
        } 
        .time-slot:hover {
            border-color: var(--brand-color);
            background: var(--bg-page);
        } 
        .time-slot.selected {
            background: var(--brand-color);
            color: white;
            border-color: var(--brand-color);
        } 
 
        .btn-back { 
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.8125rem;
            cursor: pointer;
            margin-bottom: 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.875rem;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }
        
        .btn-back:hover {
            border-color: var(--brand-color);
            color: var(--brand-color);
        } 
 
        .loading-spinner { 
            display: inline-block;
            width: 1.25rem;
            height: 1.25rem; 
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%; 
            border-top-color: white;
            animation: spin 1s infinite; 
        } 
        @keyframes spin { 
            to { transform: rotate(360deg); } 
        }

        .footer-develoi {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: 0.875rem 1rem;
            text-align: center;
            z-index: 999;
            box-shadow: var(--shadow-sm);
        }
        
        .footer-content {
            max-width: 50rem;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .footer-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-action {
            flex-shrink: 0;
        }
        
        .footer-action-btn {
            padding: 0.625rem 1rem;
            border-radius: var(--radius-md);
            border: none;
            background: var(--brand-color);
            color: #ffffff;
            font-size: 0.8125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .footer-action-btn i {
            font-size: 0.875rem;
        }
        
        .footer-action-btn:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-hover);
        }
        
        .footer-action-btn:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-muted);
            transition: all 0.2s ease;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
        }
        
        .footer-logo:hover {
            color: var(--brand-color);
            background: var(--bg-page);
        }
        
        .footer-logo img {
            width: 1.125rem;
            height: 1.125rem;
            object-fit: contain;
        }
        
        .footer-text {
            font-size: 0.625rem;
            color: var(--text-muted);
        }
        
        .footer-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .footer-develoi {
                padding: 0.75rem;
            }
            
            .footer-content {
                gap: 0.5rem;
            }
            
            .footer-text {
                display: none;
            }
            
            .footer-action-btn {
                padding: 0.5rem 0.875rem;
                font-size: 0.75rem;
            }
            
            .footer-logo {
                font-size: 0.6875rem;
                padding: 0.25rem 0.625rem;
            }
            
            .footer-logo img {
                width: 1rem;
                height: 1rem;
            }
        }
        
        @media (min-width: 768px) {
            .footer-content {
                flex-direction: row;
                justify-content: center;
                gap: 1rem;
            }
        }

        ::-webkit-scrollbar { 
            width: 6px; 
        }
        ::-webkit-scrollbar-track { 
            background: var(--bg-page);
        }
        ::-webkit-scrollbar-thumb { 
            background: var(--border);
            border-radius: var(--radius-sm);
        }
        ::-webkit-scrollbar-thumb:hover { 
            background: var(--text-muted);
        }

        .splash-screen {
            position: fixed;
            inset: 0;
            background: var(--brand-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: splashFadeOut 0.5s ease-out 1.5s forwards;
        }
        
        .splash-logo {
            width: 5rem;
            height: 5rem;
            background: var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--brand-color);
            margin-bottom: 1rem;
            box-shadow: var(--shadow-card);
        }
        
        .splash-text {
            color: white;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .splash-spinner {
            margin-top: 1.5rem;
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes splashFadeOut {
            to { opacity: 0; pointer-events: none; }
        }

        .welcome-screen {
            position: fixed;
            inset: 0;
            background: var(--bg-page);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            padding: 2rem;
            text-align: center;
        }
        
        .welcome-screen.hidden {
            display: none;
        }
        
        .welcome-logo {
            width: 5rem;
            height: 5rem;
            background: var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--brand-color);
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            border: 3px solid var(--border);
        }
        
        .welcome-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            font-size: 0.8125rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            max-width: 22rem;
            line-height: 1.5;
        }
        
        .welcome-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
            max-width: 22rem;
        }
        
        .btn-welcome {
            padding: 0.875rem 1.25rem;
            background: var(--brand-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            box-shadow: var(--shadow-card);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .btn-welcome-icon {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .btn-welcome-icon i {
            font-size: 0.75rem;
        }
        
        .btn-welcome:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-hover);
        }
        
        .btn-welcome.secondary {
            background: var(--bg-card);
            color: var(--brand-color);
            border: 1px solid var(--brand-color);
        }
        
        .btn-welcome.secondary:hover {
            background: var(--bg-page);
        }
        
        .btn-welcome.instagram {
            background: #E1306C;
            color: white;
            border: none;
        }
        
        .btn-welcome.instagram:hover {
            background: #C13584;
        }

        .consulta-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }
        
        .consulta-modal.active {
            display: flex;
        }
        
        .consulta-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            max-width: 28rem;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
            position: relative;
        }
        
        .consulta-content::-webkit-scrollbar {
            width: 4px;
        }
        
        .consulta-content::-webkit-scrollbar-track {
            background: var(--bg-page);
        }
        
        .consulta-content::-webkit-scrollbar-thumb {
            background: var(--brand-color);
            border-radius: var(--radius-sm);
        }
        
        @media (max-width: 768px) {
            .consulta-content {
                padding: 1.25rem;
                max-height: 90vh;
            }
        }
        
        .consulta-close {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: var(--bg-page);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .consulta-close:hover {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .agendamento-card {
            background: var(--bg-card);
            border: 1px solid var(--brand-color);
            border-radius: var(--radius-md);
            padding: 0.875rem;
            margin-bottom: 0.75rem;
        }
        
        .agendamento-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-bottom: 0.625rem;
            padding-bottom: 0.625rem;
            border-bottom: 1px solid var(--border);
        }
        
        .agendamento-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: var(--brand-color);
            color: white;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .agendamento-info {
            flex: 1;
            min-width: 0;
        }
        
        .agendamento-servico {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }
        
        .agendamento-data {
            color: var(--text-muted);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .agendamento-preco {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--brand-color);
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .agendamento-card {
                padding: 0.75rem;
            }
            
            .agendamento-header {
                gap: 0.5rem;
            }
            
            .agendamento-icon {
                width: 2.25rem;
                height: 2.25rem;
                font-size: 0.875rem;
            }
            
            .agendamento-servico {
                font-size: 0.8125rem;
            }
            
            .agendamento-data {
                font-size: 0.6875rem;
            }
            
            .agendamento-preco {
                font-size: 0.875rem;
            }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(1rem); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Ajustes extras para telas bem pequenas (celular) */
        @media (max-width: 480px) {
            body {
                font-size: 0.9rem;
            }

            .sidebar {
                padding: 24px 18px;
            }

            .business-logo {
                width: 88px;
                height: 88px;
                border-radius: 999px;
            }

            .business-name {
                font-size: 1.3rem;
            }

            .business-bio {
                font-size: 0.8rem;
            }

            .card-title {
                font-size: 1.3rem;
            }

            .card-subtitle {
                font-size: 0.85rem;
                margin-bottom: 20px;
            }

            .form-control {
                padding: 12px 14px;
                font-size: 0.9rem;
                border-radius: 999px;
            }

            .btn-action {
                padding: 14px 18px;
                font-size: 0.9rem;
            }
            
            .service-card {
                padding: 10px 12px;
                border-radius: 14px;
                box-shadow: 0 4px 12px rgba(15,23,42,0.06);
            }
            
            .service-img {
                width: 50px;
                height: 50px;
            }
            
            .service-title {
                font-size: 0.9rem;
            }
            
            .service-description {
                font-size: 0.74rem;
            }
            
            .service-price {
                font-size: 1rem;
                border-radius: 999px;
                margin-top: 18px;
            }

            .btn-back {
                padding: 8px 14px;
                font-size: 0.8rem;
                border-radius: 999px;
            }

            .service-card {
                border-radius: 18px;
                gap: 10px;
            }

            .service-img {
                width: 68px;
                height: 68px;
                border-radius: 999px;
            }

            .service-title {
                font-size: 0.9rem;
            }

            .service-description {
                font-size: 0.75rem;
            }

            .time-slot {
                padding: 10px 8px;
                font-size: 0.9rem;
                border-radius: 999px;
            }

            .consulta-content,
            .error-content {
                padding: 22px 18px;
                border-radius: 20px;
            }

            .welcome-title {
                font-size: 1.6rem;
            }

            .welcome-subtitle {
                font-size: 0.9rem;
            }
        }
        
        .no-agendamento {
            text-align: center;
            padding: 24px 16px;
            background: rgba(148,163,184,0.1);
            border-radius: 20px;
            animation: fadeIn 0.3s ease-out;
        }
        
        .no-agendamento-icon {
            font-size: 2.5rem;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        
        .no-agendamento-text {
            color: var(--text-muted);
            margin-bottom: 16px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        @media (max-width: 767px) {
            .no-agendamento {
                padding: 20px 14px;
                border-radius: 16px;
            }
            
            .no-agendamento-icon {
                font-size: 2rem;
            }
            
            .no-agendamento-text {
                font-size: 0.9rem;
            }
        }
        
        /* Botões menores e mais delicados no celular */
        @media (max-width: 480px) {
            .welcome-screen {
                padding: 24px 16px;
            }

            .welcome-logo {
                width: 80px;
                height: 80px;
                margin-bottom: 18px;
            }

            .welcome-title {
                font-size: 1.4rem;
            }

            .welcome-subtitle {
                font-size: 0.9rem;
                margin-bottom: 24px;
            }

            .welcome-options {
                gap: 10px;
                max-width: 320px;
                margin: 0 auto;
            }

            .btn-welcome {
                padding: 12px 16px;
                font-size: 0.85rem;
                border-radius: 999px;
                gap: 6px;
                box-shadow: 0 6px 18px -4px rgba(0,0,0,0.2);
                white-space: nowrap;
                min-width: 0;
                flex: 1 1 auto;
            }
            
            .btn-welcome-icon {
                width: 26px;
                height: 26px;
            }

            .btn-welcome-icon i {
                font-size: 0.9rem;
            }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }
        
        .error-modal.active {
            display: flex;
        }
        
        .error-content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            max-width: 26rem;
            width: 100%;
            box-shadow: var(--shadow-hover);
            text-align: center;
        }
        
        .error-icon {
            width: 3.5rem;
            height: 3.5rem;
            margin: 0 auto 1rem;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #dc2626;
        }
        
        .error-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        
        .error-message {
            color: var(--text-muted);
            font-size: 0.8125rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }
        
        .error-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .error-btn:hover {
            background: #b91c1c;
        }

        .selected-services-badge {
            position: fixed;
            bottom: 5rem;
            right: 1rem;
            background: var(--brand-color);
            color: white;
            padding: 0.625rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.8125rem;
            box-shadow: var(--shadow-hover);
            display: none;
            align-items: center;
            gap: 0.5rem;
            z-index: 1001;
            cursor: pointer;
        }
        
        .selected-services-badge.show {
            display: flex;
        }
    </style> 
</head> 
<body>
    <!-- Splash Screen -->
    <div class="splash-screen" id="splashScreen">
        <div class="splash-logo">
            <?php if ($temFoto): ?>
                <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" 
                     style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
                     alt="<?php echo htmlspecialchars($nomeEstabelecimento); ?>"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-initial" style="display:none;"><?php echo $iniciais; ?></div>
            <?php else: ?>
                <div class="logo-initial"><?php echo $iniciais; ?></div>
            <?php endif; ?>
        </div>
        <div class="splash-text"><?php echo htmlspecialchars($nomeEstabelecimento); ?></div>
        <div class="splash-spinner"></div>
    </div>

    <!-- Welcome Screen -->
    <?php if (!$sucesso): ?>
    <div class="welcome-screen" id="welcomeScreen">
        <div class="welcome-logo">
            <?php if ($temFoto): ?>
                <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" 
                     style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
                     alt="<?php echo htmlspecialchars($nomeEstabelecimento); ?>">
            <?php else: ?>
                <?php echo $iniciais; ?>
            <?php endif; ?>
        </div>
        <h1 class="welcome-title"><?php echo htmlspecialchars($nomeEstabelecimento); ?></h1>
        <?php if ($nomeEstabelecimento !== $nomeProfissional): ?>
        <p class="welcome-subtitle" style="margin-bottom:8px;">
            <i class="bi bi-person-badge" style="opacity:0.7;"></i> <?php echo htmlspecialchars($nomeProfissional); ?>
        </p>
        <?php endif; ?>
        <p class="welcome-subtitle">Escolha como deseja continuar</p>
        <div class="welcome-options">
            <button class="btn-welcome" onclick="iniciarAgendamento()">
                <span class="btn-welcome-icon">
                    <i class="bi bi-calendar-plus"></i>
                </span>
                Novo Agendamento
            </button>
            <button class="btn-welcome secondary" onclick="abrirConsulta()">
                <span class="btn-welcome-icon">
                    <i class="bi bi-search"></i>
                </span>
                Consultar Meu Agendamento
            </button>
            <?php if ($instagram): ?>
            <a href="https://instagram.com/<?php echo htmlspecialchars(ltrim($instagram, '@')); ?>" 
               target="_blank" 
               class="btn-welcome instagram" 
               style="text-decoration:none;">
                <span class="btn-welcome-icon">
                    <i class="bi bi-instagram"></i>
                </span>
                <span>Instagram</span>
            </a>
            <?php endif; ?>
            <button type="button" class="btn-welcome secondary" id="installCta" onclick="abrirInstallModal()">
                <span class="btn-welcome-icon">
                    <i class="bi bi-phone"></i>
                </span>
                <span>Instalar App</span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Consultar Agendamento -->
    <div class="consulta-modal" id="consultaModal">
        <div class="consulta-content">
            <button class="consulta-close" onclick="fecharConsulta()">
                <i class="bi bi-x-lg"></i>
            </button>
            <h2 class="card-title" style="margin-bottom:8px;">Consultar Agendamento</h2>
            <p class="card-subtitle" style="margin-bottom:24px;">Digite seu telefone para buscar</p>
            
            <div class="form-group">
                <label class="form-label">Telefone / WhatsApp</label>
                <input type="tel" id="consultaTelefone" class="form-control" 
                       placeholder="(11) 99999-9999" maxlength="15"
                       oninput="maskPhone(this)">
            </div>
            
            <div id="consultaLoading" style="display:none; text-align:center; padding:20px; color:var(--brand-color);">
                <i class="bi bi-arrow-repeat" style="animation:spin 1s infinite; display:inline-block; font-size:1.5rem;"></i>
                <p style="margin-top:10px; color:var(--text-muted);">Buscando...</p>
            </div>
            
            <div id="consultaResult" style="margin-top:20px;"></div>
            
            <button class="btn-action" onclick="buscarAgendamento()" id="btnBuscarAgendamento" style="margin-top:20px;">
                <i class="bi bi-search"></i>
                Buscar
            </button>
        </div>
    </div>

    <div class="aurora-bg">
        <div class="aurora-blob blob-1"></div>
        <div class="aurora-blob blob-2"></div>
    </div> 
 
<!-- Modal Instalar App (custom, sem Bootstrap) -->
<div class="consulta-modal" id="installModalCustom">
    <div class="consulta-content" style="max-width:480px;">
        <button class="consulta-close" onclick="fecharInstallModal()">
            <i class="bi bi-x-lg"></i>
        </button>

        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:64px;height:64px;margin:0 auto 16px;background:linear-gradient(135deg,var(--brand-color),var(--brand-dark));border-radius:20px;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(79,70,229,0.25);">
                <i class="bi bi-phone" style="font-size:32px;color:white;"></i>
            </div>
            <h2 class="card-title" style="margin-bottom:8px;">Instalar no Celular</h2>
            <p class="card-subtitle" style="margin-bottom:0;">
                Acesse rápido direto da tela inicial!
            </p>
        </div>

        <!-- iOS Instructions -->
        <div id="installInstructionsIOS" style="display:none;">
            <div style="background:linear-gradient(135deg,#007AFF,#0051D5);padding:16px;border-radius:16px;margin-bottom:16px;box-shadow:0 4px 16px rgba(0,122,255,0.2);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <i class="bi bi-apple" style="font-size:28px;color:white;"></i>
                    <div>
                        <div style="font-weight:700;color:white;font-size:1.05rem;">iPhone / iPad</div>
                        <div style="color:rgba(255,255,255,0.9);font-size:0.8rem;">Safari</div>
                    </div>
                </div>
                <ol style="margin:0;padding-left:20px;color:white;line-height:1.8;">
                    <li>Toque no ícone <strong>Compartilhar</strong> <i class="bi bi-box-arrow-up" style="font-size:14px;"></i></li>
                    <li>Role para baixo e toque em <strong>"Adicionar à Tela de Início"</strong></li>
                    <li>Toque em <strong>"Adicionar"</strong> para confirmar</li>
                </ol>
            </div>
        </div>

        <!-- Android Instructions -->
        <div id="installInstructionsAndroid" style="display:none;">
            <div style="background:linear-gradient(135deg,#34A853,#0F9D58);padding:16px;border-radius:16px;margin-bottom:16px;box-shadow:0 4px 16px rgba(52,168,83,0.2);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <i class="bi bi-android2" style="font-size:28px;color:white;"></i>
                    <div>
                        <div style="font-weight:700;color:white;font-size:1.05rem;">Android</div>
                        <div style="color:rgba(255,255,255,0.9);font-size:0.8rem;">Chrome / Edge</div>
                    </div>
                </div>
                <ol style="margin:0;padding-left:20px;color:white;line-height:1.8;">
                    <li>Toque no menu <strong>⋮</strong> (três pontos) no canto superior</li>
                    <li>Selecione <strong>"Adicionar à tela inicial"</strong> ou <strong>"Instalar app"</strong></li>
                    <li>Confirme tocando em <strong>"Adicionar"</strong></li>
                </ol>
            </div>
        </div>

        <!-- Both platforms (fallback) -->
        <div id="installInstructionsBoth" style="display:none;">
            <div style="display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:16px;">
                <!-- iOS -->
                <div style="background:linear-gradient(135deg,#007AFF,#0051D5);padding:14px;border-radius:14px;box-shadow:0 2px 12px rgba(0,122,255,0.15);">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <i class="bi bi-apple" style="font-size:24px;color:white;"></i>
                        <strong style="color:white;font-size:0.95rem;">iPhone/iPad (Safari)</strong>
                    </div>
                    <ol style="margin:0;padding-left:18px;color:white;line-height:1.6;font-size:0.85rem;">
                        <li>Toque em <i class="bi bi-box-arrow-up"></i> <strong>Compartilhar</strong></li>
                        <li><strong>"Adicionar à Tela de Início"</strong></li>
                    </ol>
                </div>
                <!-- Android -->
                <div style="background:linear-gradient(135deg,#34A853,#0F9D58);padding:14px;border-radius:14px;box-shadow:0 2px 12px rgba(52,168,83,0.15);">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <i class="bi bi-android2" style="font-size:24px;color:white;"></i>
                        <strong style="color:white;font-size:0.95rem;">Android (Chrome)</strong>
                    </div>
                    <ol style="margin:0;padding-left:18px;color:white;line-height:1.6;font-size:0.85rem;">
                        <li>Menu <strong>⋮</strong> (três pontos)</li>
                        <li><strong>"Adicionar à tela inicial"</strong></li>
                    </ol>
                </div>
            </div>
        </div>

        <button type="button" class="btn-action" id="installPromptBtn" style="display:none; margin-bottom:10px;background:linear-gradient(135deg,var(--brand-color),var(--brand-dark));">
            <i class="bi bi-download"></i> Instalar Automaticamente
        </button>

        <button type="button" class="btn-action" style="background:var(--bg-card); color:var(--text-main); border:1px solid var(--border);"
                onclick="fecharInstallModal()">
            <i class="bi bi-check-lg"></i> Entendi
        </button>
    </div>
</div>

<div class="app-container" id="mainApp" style="display:none;">
    <div class="sidebar"> 
        <div class="business-logo"> 
            <?php if ($temFoto): ?> 
                <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" 
                     alt="<?php echo htmlspecialchars($nomeEstabelecimento); ?>" 
                     class="logo-img"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"> 
                <div class="logo-initial" style="display:none;"><?php echo $iniciais; ?></div>
            <?php else: ?> 
                <div class="logo-initial"><?php echo $iniciais; ?></div> 
            <?php endif; ?> 
        </div> 
 
        <h1 class="business-name"><?php echo htmlspecialchars($nomeEstabelecimento); ?></h1> 
 
        <?php if ($nomeEstabelecimento !== $nomeProfissional): ?> 
            <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:10px; font-weight:500;"> 
                <i class="bi bi-person-badge" style="opacity:0.7;"></i>
                Responsável: <?php echo htmlspecialchars($nomeProfissional); ?> 
            </p> 
        <?php endif; ?> 
 
        <?php if (!empty($biografia) && $biografia !== 'Agende seu horário com a gente!'): ?>
            <p class="business-bio">
                <i class="bi bi-quote" style="opacity:0.5; font-size:1.1rem; margin-right:4px;"></i>
                <?php echo htmlspecialchars($biografia); ?>
            </p>
        <?php else: ?>
            <p class="business-bio" style="opacity:0.8;">
                <?php echo htmlspecialchars($biografia); ?>
            </p>
        <?php endif; ?> 
 
        <?php if ($telefone): ?> 
            <div class="info-pill"> 
                <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($telefone); ?> 
            </div> 
        <?php endif; ?> 
 
        <?php if ($instagram): ?> 
            <a href="https://instagram.com/<?php echo htmlspecialchars(ltrim($instagram, '@')); ?>" 
               target="_blank" 
               class="info-pill" 
               style="text-decoration:none; cursor:pointer; transition:all 0.3s ease;" 
               onmouseover="this.style.background='linear-gradient(135deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)'; this.style.color='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(188,24,136,0.3)';" 
               onmouseout="this.style.background='rgba(255, 255, 255, 0.8)'; this.style.color=''; this.style.transform=''; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';"> 
                <i class="bi bi-instagram"></i> @<?php echo htmlspecialchars(ltrim($instagram, '@')); ?> 
            </a> 
        <?php endif; ?> 
 
        <?php if ($enderecoCompleto): ?> 
            <div class="info-pill"> 
                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($enderecoCompleto); ?> 
            </div> 
        <?php endif; ?> 
 
        <div id="bookingSummary" 
             style="margin-top:auto; width:100%; background:rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding:24px; border-radius:24px; border:1px solid rgba(148,163,184,0.1); text-align:left; display:none; box-shadow:var(--shadow-strong);" 
             class="step-screen"> 
            <div style="font-size:0.7rem; text-transform:uppercase; color:#94a3b8; font-weight:800; margin-bottom:12px; letter-spacing:1.5px;"> 
                <i class="bi bi-calendar-check" style="margin-right:6px;"></i>
                Seu Agendamento 
            </div> 
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-weight:700; font-size:1.05rem;"> 
                <span id="sumServico" style="font-family: 'Outfit', sans-serif;">...</span> 
                <span id="sumPreco" style="color:var(--brand-color); font-family: 'Outfit', sans-serif; font-size:1.2rem;">...</span> 
            </div> 
            <div style="font-size:0.9rem; color:#64748b; display:flex; align-items:center; gap:8px;" id="sumDataHora">
                <i class="bi bi-clock"></i>
            </div> 
        </div> 
    </div> 
 
    <div class="main-content"> 
        <?php if ($sucesso): ?> 
            <div style="text-align:center; padding:50px 20px;">
                <!-- Logo/Foto do Estabelecimento -->
                <div class="success-logo">
                    <?php if ($temFoto): ?>
                        <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" 
                             alt="<?php echo htmlspecialchars($nomeEstabelecimento); ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="logo-initial" style="display:none;"><?php echo $iniciais; ?></div>
                    <?php else: ?>
                        <div class="logo-initial"><?php echo $iniciais; ?></div>
                    <?php endif; ?>
                </div>
                <h2 class="card-title" style="color:#f59e0b; margin-bottom:10px;">Aguardando confirmacao</h2> 
                <p class="card-subtitle" style="margin-bottom:18px;">Seu agendamento foi enviado ao profissional. Aguarde a confirmacao.</p>
                <div style="background:rgba(245, 158, 11, 0.08); padding:20px; border-radius:20px; margin-bottom:30px; border:1px solid rgba(245, 158, 11, 0.2);">
                    <div style="font-size:0.85rem; color:#64748b; margin-bottom:8px;">Serviço</div>
                    <div style="font-weight:700; font-size:1.2rem; color:var(--text-main); margin-bottom:16px; font-family:'Outfit', sans-serif;">
                        <?php echo htmlspecialchars($servicoConfirmado); ?>
                    </div>
                    <div style="font-size:0.85rem; color:#64748b; margin-bottom:8px;">Data e Horário</div>
                    <div style="font-weight:700; font-size:1.1rem; color:var(--text-main); font-family:'Outfit', sans-serif;">
                        <i class="bi bi-calendar-event me-2"></i><?php echo date('d/m/Y', strtotime($dataConfirmada)); ?>
                        <span style="margin:0 8px;">•</span>
                        <i class="bi bi-clock me-2"></i><?php echo htmlspecialchars($horaConfirmada); ?>
                    </div>
                </div>
                <?php
                $whats = preg_replace('/[^0-9]/', '', $profissional['telefone'] ?? '');
                
                // Formata data em português
                $dataFormatada = date('d/m/Y', strtotime($dataConfirmada));
                $diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                $diaSemana = $diasSemana[date('w', strtotime($dataConfirmada))];
                
                // Inicializa variáveis para evitar avisos
                $clienteNomeMsg = !empty($clienteConfirmado) ? $clienteConfirmado : ($nome ?? '');
                $msg = rawurlencode(
                    "Ola, fiz um agendamento pelo sistema:\n\n" .
                    "Cliente: {$clienteNomeMsg}\n" .
                    "Servico: {$servicoConfirmado}\n" .
                    "Valor: R$ " . number_format((float)$valorConfirmado, 2, ',', '.') . "\n" .
                    "Data: {$diaSemana}, {$dataFormatada}\n" .
                    "Horario: {$horaConfirmada}\n\n" .
                    "Aguardo a confirmacao. Obrigado!"
                );
                ?>
                <?php
                $whatsNumero = $whats;
                if (!empty($whatsNumero) && !str_starts_with($whatsNumero, '55')) {
                    $whatsNumero = '55' . $whatsNumero;
                }
                ?>
                <?php if (!empty($whatsNumero)): ?>
                <a href="https://wa.me/<?php echo $whatsNumero; ?>?text=<?php echo $msg; ?>" class="btn-action" style="background:linear-gradient(135deg, var(--brand-color), var(--brand-dark)); margin-bottom:14px;"> 
                    <i class="bi bi-whatsapp"></i> Enviar confirmacao ao profissional
                </a> 
                <?php else: ?>
                <span class="btn-action" style="background:#e5e7eb;color:#6b7280;cursor:not-allowed;margin-bottom:14px;">
                    <i class="bi bi-whatsapp"></i> Profissional sem WhatsApp cadastrado
                </span>
                <?php endif; ?>
                <a href="?user=<?php echo $profissionalId; ?>" class="btn-action" style="background:linear-gradient(135deg, var(--brand-color), var(--brand-dark)); margin-bottom:14px;"> 
                    <i class="bi bi-arrow-clockwise"></i> Agendar Novamente 
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
                        <?php if (!empty($pacotes)): ?>
                            <h3 style="font-family: 'Outfit', sans-serif; margin-bottom: 15px; margin-top: 25px; font-size: 1.3rem; color: var(--text-main);">Nossos Pacotes</h3>
                            <?php foreach ($pacotes as $s): ?>
                                <?php
                                    $caminhoFotoServico = !empty($s['foto']) ? __DIR__ . '/' . ltrim($s['foto'], '/') : '';
                                    $temFotoServico = !empty($s['foto']) && file_exists($caminhoFotoServico);
                                    
                                    // Calcula o preço original somando os itens do pacote
                                    $precoOriginalPacote = 0;
                                    if (!empty($s['itens_detalhados'])) {
                                        foreach ($s['itens_detalhados'] as $item) {
                                            $precoOriginalPacote += floatval($item['preco']);
                                        }
                                    }
                                    
                                    // Se não houver preco_original no banco, usa o calculado
                                    if (empty($s['preco_original']) || $s['preco_original'] == 0) {
                                        $precoOriginalExibir = $precoOriginalPacote;
                                    } else {
                                        $precoOriginalExibir = floatval($s['preco_original']);
                                    }
                                    
                                    $precoFinal = floatval($s['preco']);
                                    $temDesconto = $precoOriginalExibir > $precoFinal;
                                    $economia = $precoOriginalExibir - $precoFinal;
                                    $percentualDesconto = $precoOriginalExibir > 0 ? (($economia / $precoOriginalExibir) * 100) : 0;
                                ?>
                                <div class="service-card <?php echo $temFotoServico ? '' : 'no-image'; ?>"
                                     onclick="selectService(this, '<?php echo $s['id']; ?>', '<?php echo $s['nome']; ?>', '<?php echo $s['preco']; ?>', '<?php echo $s['duracao']; ?>')"> 

                                    <?php if ($temFotoServico): ?>
                                        <div class="service-img">
                                            <img src="<?php echo htmlspecialchars($s['foto']); ?>"
                                                 alt="<?php echo htmlspecialchars($s['nome']); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="service-content">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <h3 class="service-title" style="margin: 0;"><?php echo htmlspecialchars($s['nome']); ?></h3>
                                            <?php if (!empty($s['permite_recorrencia']) && $s['permite_recorrencia'] == 1): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 4px; background: #dbeafe; color: #1e40af; font-size: 0.7rem; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
                                                    <i class="bi bi-arrow-repeat"></i> Recorrente
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($s['itens_detalhados'])): ?>
                                            <div class="service-description" style="font-size: 0.8rem; color: var(--text-muted);">
                                                Inclui: 
                                                <?php 
                                                    $nomesItens = array_map(function($item) {
                                                        return htmlspecialchars($item['nome']);
                                                    }, $s['itens_detalhados']);
                                                    echo implode(', ', $nomesItens);
                                                ?>
                                            </div>
                                        <?php elseif (!empty($s['observacao'])): ?>
                                            <div class="service-description"><?php echo htmlspecialchars($s['observacao']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['permite_recorrencia']) && $s['permite_recorrencia'] == 1): ?>
                                            <?php 
                                                $textoRecorrencia = formatarRecorrenciaAgendamento(
                                                    $s['tipo_recorrencia'] ?? null,
                                                    $s['dias_semana'] ?? null,
                                                    $s['intervalo_dias'] ?? null
                                                );
                                            ?>
                                            <?php if (!empty($textoRecorrencia)): ?>
                                                <div style="font-size: 0.75rem; color: #0369a1; margin-top: 6px; display: flex; align-items: center; gap: 4px; font-weight: 600;">
                                                    <i class="bi bi-clock-history"></i> <?php echo $textoRecorrencia; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="service-duration">
                                            <i class="bi bi-clock"></i>
                                            <?php echo $s['duracao']; ?> min
                                        </span>
                                    </div>
                                    
                                    <div class="service-price-wrapper">
                                        <?php if ($temDesconto): ?>
                                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                                <div style="font-size: 0.85rem; color: #94a3b8; text-decoration: line-through;">
                                                    R$ <?php echo number_format($precoOriginalExibir, 2, ',', '.'); ?>
                                                </div>
                                                <div class="service-price"> 
                                                    R$ <?php echo number_format($precoFinal, 2, ',', '.'); ?> 
                                                </div>
                                                <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 8px;">
                                                    <?php echo round($percentualDesconto); ?>% OFF
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="service-price"> 
                                                R$ <?php echo number_format($precoFinal, 2, ',', '.'); ?> 
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div> 
                            <?php endforeach; ?> 
                        <?php endif; ?>

                        <?php if (!empty($servicosUnicos)): ?>
                            <h3 style="font-family: 'Outfit', sans-serif; margin-bottom: 15px; margin-top: 25px; font-size: 1.3rem; color: var(--text-main);">Serviços Individuais</h3>
                            <?php foreach ($servicosUnicos as $s): ?> 
                                <?php
                                    $caminhoFotoServico = !empty($s['foto']) ? __DIR__ . '/' . ltrim($s['foto'], '/') : '';
                                    $temFotoServico = !empty($s['foto']) && file_exists($caminhoFotoServico);
                                ?>
                                <div class="service-card <?php echo $temFotoServico ? '' : 'no-image'; ?>"
                                     onclick="selectService(this, '<?php echo $s['id']; ?>', '<?php echo $s['nome']; ?>', '<?php echo $s['preco']; ?>', '<?php echo $s['duracao']; ?>')"> 

                                    <?php if ($temFotoServico): ?>
                                        <div class="service-img">
                                            <img src="<?php echo htmlspecialchars($s['foto']); ?>"
                                                 alt="<?php echo htmlspecialchars($s['nome']); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="service-content">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <h3 class="service-title" style="margin: 0;"><?php echo htmlspecialchars($s['nome']); ?></h3>
                                            <?php if (!empty($s['permite_recorrencia']) && $s['permite_recorrencia'] == 1): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 4px; background: #dbeafe; color: #1e40af; font-size: 0.7rem; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
                                                    <i class="bi bi-arrow-repeat"></i> Recorrente
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($s['observacao'])): ?>
                                            <div class="service-description"><?php echo htmlspecialchars($s['observacao']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['permite_recorrencia']) && $s['permite_recorrencia'] == 1): ?>
                                            <?php 
                                                $textoRecorrencia = formatarRecorrenciaAgendamento(
                                                    $s['tipo_recorrencia'] ?? null,
                                                    $s['dias_semana'] ?? null,
                                                    $s['intervalo_dias'] ?? null
                                                );
                                            ?>
                                            <?php if (!empty($textoRecorrencia)): ?>
                                                <div style="font-size: 0.75rem; color: #0369a1; margin-top: 6px; display: flex; align-items: center; gap: 4px; font-weight: 600;">
                                                    <i class="bi bi-clock-history"></i> <?php echo $textoRecorrencia; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <span class="service-duration">
                                            <i class="bi bi-clock"></i>
                                            <?php echo $s['duracao']; ?> min
                                        </span>
                                    </div>
                                    
                                    <div class="service-price-wrapper">
                                        <div class="service-price"> 
                                            R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?> 
                                        </div>
                                    </div>
                                    
                                </div> 
                            <?php endforeach; ?> 
                        <?php endif; ?>
 
                        <?php if (empty($pacotes) && empty($servicosUnicos)): ?> 
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
                    <h2 class="card-title">Escolha a data</h2> 
                    <p class="card-subtitle">Selecione o dia desejado no calendário abaixo.</p> 
 
                    <!-- Calendário Visual Interativo -->
                    <div class="calendar-container">
                        <div class="calendar-loading" id="calendarLoading">
                            <i class="bi bi-arrow-repeat"></i>
                            <span style="font-weight: 600; color: var(--text-main);">Verificando disponibilidade...</span>
                        </div>
                        
                        <div class="calendar-header">
                            <button type="button" class="calendar-nav-btn" onclick="changeMonth(-1)" id="prevMonthBtn">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                <div class="calendar-month-year" id="calendarMonthYear"></div>
                                <button type="button" onclick="goToToday()" 
                                        style="background: none; border: none; color: var(--brand-color); font-size: 0.75rem; font-weight: 600; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: all 0.2s;">
                                    <i class="bi bi-calendar-day"></i> Hoje
                                </button>
                            </div>
                            <button type="button" class="calendar-nav-btn" onclick="changeMonth(1)" id="nextMonthBtn">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="calendar-weekdays">
                            <div class="calendar-weekday">Dom</div>
                            <div class="calendar-weekday">Seg</div>
                            <div class="calendar-weekday">Ter</div>
                            <div class="calendar-weekday">Qua</div>
                            <div class="calendar-weekday">Qui</div>
                            <div class="calendar-weekday">Sex</div>
                            <div class="calendar-weekday">Sáb</div>
                        </div>
                        
                        <div class="calendar-days" id="calendarDays"></div>
                        
                        <!-- Legenda -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; margin-top: 16px; padding-top: 16px; border-top: 2px solid rgba(148,163,184,0.1);">
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem;">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background: white; border: 2px solid var(--border);"></div>
                                <span style="color: var(--text-muted); font-weight: 600;">Livre</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem;">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%); border: 2px solid #fb923c;"></div>
                                <span style="color: var(--text-muted); font-weight: 600;">Poucos ⚡</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem;">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #fbbf24;"></div>
                                <span style="color: var(--text-muted); font-weight: 600;">Lotado 🔒</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem;">
                                <div style="width: 16px; height: 16px; border-radius: 4px; background: repeating-linear-gradient(45deg, #fee2e2, #fee2e2 2px, #fef2f2 2px, #fef2f2 4px); text-decoration: line-through;"></div>
                                <span style="color: var(--text-muted); font-weight: 600;">Fechado ✕</span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="dateInput">
 
                    <div id="horariosSection" style="display: none; margin-top: 24px;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; font-family: 'Outfit', sans-serif;">
                            <i class="bi bi-clock-history"></i> Horários disponíveis
                        </h3>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">
                            Toque no horário desejado para continuar.
                        </p>
                        
                        <div id="loadingTimes" style="display:none; text-align:center; padding:20px; color:var(--brand-color);"> 
                            <i class="bi bi-arrow-repeat" 
                               style="animation:spin 1s infinite; display:inline-block; font-size:1.5rem;"></i> 
                        </div> 
     
                        <div id="timesContainer" class="time-slots-grid"></div>
                        <div id="noTimesMsg" 
                             style="display:none; text-align:center; color:#ef4444; margin-top:20px; padding: 16px; background: rgba(239, 68, 68, 0.1); border-radius: 12px; border: 2px solid rgba(239, 68, 68, 0.2);"> 
                            <i class="bi bi-calendar-x" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                            <strong>Sem horários disponíveis</strong><br>
                            <small>Tente outra data ou entre em contato.</small>
                        </div>
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
                               placeholder="(11) 99999-9999" maxlength="15" required
                               oninput="maskPhone(this); validateForm()" onblur="checkClient()"> 
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
                            <input type="text" name="cliente_nome" id="nomeInput" class="form-control" required oninput="validateForm()">
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
                </div> 
            </form> 
        <?php endif; ?> 
    </div> 
</div> 
 
<script>
    const PROF_ID = <?php echo $profissionalId; ?>;
    const CURRENT_PAGE = <?php echo json_encode($currentPath); ?>;
    let selectedServices = [];
    let currentServiceDuration = 0;
    
    // ===== CALENDÁRIO INTERATIVO =====
    let currentCalendarDate = new Date();
    let monthAvailability = {}; // Armazena disponibilidade do mês
    let selectedDateStr = null; // Armazena data selecionada (YYYY-MM-DD)
    const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    function initCalendar() {
        currentCalendarDate = new Date();
        renderCalendar();
    }
    
    function changeMonth(direction) {
        const newDate = new Date(currentCalendarDate);
        newDate.setMonth(newDate.getMonth() + direction);
        
        // Limita navegação (não permite voltar antes de hoje e não mais que 3 meses no futuro)
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const maxDate = new Date(today);
        maxDate.setMonth(maxDate.getMonth() + 3);
        
        if (newDate >= today && newDate <= maxDate) {
            currentCalendarDate = newDate;
            renderCalendar();
        }
    }
    
    function goToToday() {
        currentCalendarDate = new Date();
        renderCalendar();
    }
    
    function renderCalendar() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();
        
        // Atualiza cabeçalho
        document.getElementById('calendarMonthYear').textContent = 
            `${monthNames[month]} ${year}`;
        
        // Busca disponibilidade do mês
        fetchMonthAvailability(year, month + 1);
        
        // Controla botões de navegação
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const maxDate = new Date(today);
        maxDate.setMonth(maxDate.getMonth() + 3);
        
        const prevBtn = document.getElementById('prevMonthBtn');
        const nextBtn = document.getElementById('nextMonthBtn');
        
        // Desabilita prev se estiver no mês atual
        const currentMonth = new Date(currentCalendarDate);
        currentMonth.setDate(1);
        currentMonth.setHours(0, 0, 0, 0);
        const todayMonth = new Date(today);
        todayMonth.setDate(1);
        todayMonth.setHours(0, 0, 0, 0);
        
        if (currentMonth.getTime() <= todayMonth.getTime()) {
            prevBtn.disabled = true;
            prevBtn.style.opacity = '0.3';
            prevBtn.style.cursor = 'not-allowed';
        } else {
            prevBtn.disabled = false;
            prevBtn.style.opacity = '1';
            prevBtn.style.cursor = 'pointer';
        }
        
        // Desabilita next se estiver 3 meses no futuro
        const nextMonth = new Date(currentCalendarDate);
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        if (nextMonth > maxDate) {
            nextBtn.disabled = true;
            nextBtn.style.opacity = '0.3';
            nextBtn.style.cursor = 'not-allowed';
        } else {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }
        
        // Primeiro dia do mês
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startDayOfWeek = firstDay.getDay();
        
        // Limpa grid
        const daysContainer = document.getElementById('calendarDays');
        daysContainer.innerHTML = '';
        
        // Células vazias antes do primeiro dia
        for (let i = 0; i < startDayOfWeek; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day empty';
            daysContainer.appendChild(emptyDay);
        }
        
        // Dias do mês
        for (let day = 1; day <= daysInMonth; day++) {
            const dayDate = new Date(year, month, day);
            dayDate.setHours(0, 0, 0, 0);
            
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.textContent = day;
            
            // Verifica se é o dia selecionado
            const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isSelectedDay = selectedDateStr === currentDateStr;
            
            // Marca hoje
            if (dayDate.getTime() === today.getTime()) {
                dayElement.classList.add('today');
            }
            
            // Desabilita datas passadas
            if (dayDate < today) {
                dayElement.classList.add('disabled');
            } else {
                // Verifica disponibilidade
                const availability = monthAvailability[day];
                
                if (availability === 'fechado') {
                    dayElement.classList.add('fechado');
                    dayElement.title = '❌ Não abrimos neste dia';
                    dayElement.onclick = () => {
                        mostrarErro('Dia Fechado', 'Não temos atendimento neste dia da semana. Por favor, escolha outro dia.');
                    };
                } else if (availability === 'lotado') {
                    dayElement.classList.add('lotado');
                    dayElement.title = '🔒 Agenda totalmente ocupada';
                    dayElement.onclick = () => {
                        mostrarErro('Agenda Lotada', 'Infelizmente todos os horários deste dia já estão ocupados. Por favor, escolha outro dia.');
                    };
                } else if (availability === 'poucos') {
                    dayElement.classList.add('poucos');
                    dayElement.title = '⚡ Últimos horários! Reserve agora';
                    dayElement.onclick = () => selectDate(year, month, day);
                } else if (availability === 'disponivel') {
                    dayElement.classList.add('disponivel');
                    dayElement.title = '✅ Vários horários disponíveis';
                    dayElement.onclick = () => selectDate(year, month, day);
                } else {
                    // Loading ou não carregado ainda
                    dayElement.onclick = () => selectDate(year, month, day);
                }
            }
            
            // Marca como selecionado se for o dia escolhido
            if (isSelectedDay) {
                dayElement.classList.add('selected');
            }
            
            daysContainer.appendChild(dayElement);
        }
    }
    
    function fetchMonthAvailability(year, month) {
        const loading = document.getElementById('calendarLoading');
        if (loading) loading.classList.add('active');
        
        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=verificar_mes&ano=${year}&mes=${month}&duracao=${currentServiceDuration}`)
            .then(res => res.json())
            .then(data => {
                monthAvailability = data;
                if (loading) loading.classList.remove('active');
                // Re-renderiza apenas os dias com as classes corretas
                updateCalendarDays();
            })
            .catch(err => {
                console.error('Erro ao buscar disponibilidade:', err);
                if (loading) loading.classList.remove('active');
            });
    }
    
    function updateCalendarDays() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const dayElements = document.querySelectorAll('.calendar-day:not(.empty)');
        dayElements.forEach(el => {
            const day = parseInt(el.textContent);
            if (isNaN(day)) return;
            
            const dayDate = new Date(year, month, day);
            dayDate.setHours(0, 0, 0, 0);
            
            // Verifica se é o dia selecionado
            const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isSelected = selectedDateStr === currentDateStr;
            
            // Não atualiza datas passadas
            if (dayDate < today) return;
            
            // Remove classes antigas (mas mantém selected se for o caso)
            el.classList.remove('fechado', 'lotado', 'poucos', 'disponivel');
            
            const availability = monthAvailability[day];
            
            if (availability === 'fechado') {
                el.classList.add('fechado');
                el.title = '❌ Não abrimos neste dia';
                el.onclick = () => {
                    mostrarErro('Dia Fechado', 'Não temos atendimento neste dia da semana. Por favor, escolha outro dia.');
                };
            } else if (availability === 'lotado') {
                el.classList.add('lotado');
                el.title = '🔒 Agenda totalmente ocupada';
                el.onclick = () => {
                    mostrarErro('Agenda Lotada', 'Infelizmente todos os horários deste dia já estão ocupados. Por favor, escolha outro dia.');
                };
            } else if (availability === 'poucos') {
                el.classList.add('poucos');
                el.title = '⚡ Últimos horários! Reserve agora';
                el.onclick = () => selectDate(year, month, day);
            } else if (availability === 'disponivel') {
                el.classList.add('disponivel');
                el.title = '✅ Vários horários disponíveis';
                el.onclick = () => selectDate(year, month, day);
            }
            
            // Restaura seleção visual se for o dia selecionado
            if (isSelected) {
                el.classList.add('selected');
            }
        });
    }
    
    function selectDate(year, month, day) {
        // Formata data para YYYY-MM-DD
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        // Salva data selecionada globalmente
        selectedDateStr = dateStr;
        
        // Atualiza input hidden
        document.getElementById('dateInput').value = dateStr;
        
        // Atualiza também o input normal para o resumo
        document.getElementById('inData').value = dateStr;
        
        // Remove seleção anterior
        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
        
        // Marca dia selecionado
        event.target.classList.add('selected');
        
        // Mostra horários
        fetchTimes();
        
        // Scroll suave para horários (após 300ms para dar tempo de renderizar)
        setTimeout(() => {
            const horariosSection = document.getElementById('horariosSection');
            if (horariosSection) {
                horariosSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 300);
    }

    // ===== SPLASH & WELCOME =====
    window.addEventListener('DOMContentLoaded', () => {
        <?php if ($sucesso): ?>
            document.getElementById('splashScreen').style.display = 'none';
            document.getElementById('mainApp').style.display = 'grid';
        <?php else: ?>
            setTimeout(() => {
                document.getElementById('splashScreen').style.display = 'none';
            }, 2600);
            // Assim que carregar a página (modo agendamento), garante que footer está correto
            configureFooterForStep(1);
        <?php endif; ?>
    });

    function iniciarAgendamento() {
        document.getElementById('welcomeScreen').classList.add('hidden');
        document.getElementById('mainApp').style.display = 'grid';
        setTimeout(() => {
            document.getElementById('mainApp').style.animation = 'fadeIn 0.5s ease-out';
        }, 50);
    }

    function abrirConsulta() {
        document.getElementById('consultaModal').classList.add('active');
    }

    function configureFooterForStep(step) {
        const footerAction     = document.getElementById('footerAction');
        const footerActionBtn  = document.getElementById('footerActionButton');
        const footerActionText = document.getElementById('footerActionText');

        if (!footerAction || !footerActionBtn || !footerActionText) return;

        // Sempre começa escondido
        footerAction.style.display = 'none';
        footerActionBtn.disabled = true;

        if (step === 1) {
            // Só mostra se tiver pelo menos 1 serviço
            if (selectedServices.length === 0) return;

            footerAction.style.display = 'flex';
            footerActionBtn.disabled   = false;
            footerActionBtn.onclick    = continuarParaHorario;
            footerActionText.innerText = selectedServices.length === 1
                ? 'Continuar com 1 serviço'
                : `Continuar com ${selectedServices.length} serviços`;

        } else if (step === 2) {
            const horarioSelecionado = document.getElementById('inHorario').value;
            if (!horarioSelecionado) return;

            footerAction.style.display = 'flex';
            footerActionBtn.disabled   = false;
            footerActionBtn.onclick    = confirmarHorario;
            footerActionText.innerText = 'Confirmar Horário';

        } else if (step === 3) {
            footerAction.style.display = 'flex';
            // Habilita/desabilita de acordo com validateForm()
            footerActionBtn.onclick = function () {
                const form = document.getElementById('agendaForm');
                if (form && startSubmit()) {
                    form.submit();
                }
            };
            footerActionText.innerText = 'Confirmar Agendamento';
            // Deixa o estado correto conforme os campos
            footerActionBtn.disabled = !validateForm(true);
        }
    }

    function fecharConsulta() {
        document.getElementById('consultaModal').classList.remove('active');
        document.getElementById('consultaTelefone').value = '';
        document.getElementById('consultaResult').innerHTML = '';
    }

    function abrirInstallModal() {
        const modal = document.getElementById('installModalCustom');
        if (!modal) return;
        setInstallSteps(); // reaproveita a função existente
        modal.classList.add('active');
    }

    function fecharInstallModal() {
        const modal = document.getElementById('installModalCustom');
        if (modal) modal.classList.remove('active');
    }

    let dadosClienteConsulta = null;

    async function buscarAgendamento() {
        const tel = document.getElementById('consultaTelefone').value.replace(/\D/g, '');
        if (tel.length < 10) {
            mostrarErro('Telefone Inválido', 'Digite um telefone válido com DDD.');
            return;
        }

        const result = document.getElementById('consultaResult');
        const loading = document.getElementById('consultaLoading');
        const btnBuscar = document.getElementById('btnBuscarAgendamento');
        
        loading.style.display = 'block';
        result.innerHTML = '';
        btnBuscar.disabled = true;

        try {
            const res = await fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_agendamentos&telefone=${tel}`);
            const data = await res.json();
            
            loading.style.display = 'none';
            btnBuscar.disabled = false;

            if (!data.found) {
                // Cliente não existe
                result.innerHTML = `
                    <div class="no-agendamento">
                        <div class="no-agendamento-icon"><i class="bi bi-calendar-x"></i></div>
                        <p class="no-agendamento-text">Nenhum cadastro encontrado com este telefone.</p>
                        <button class="btn-action" onclick="fecharConsulta();iniciarAgendamento();" style="margin-top:0;">
                            <i class="bi bi-calendar-plus"></i> Fazer Primeiro Agendamento
                        </button>
                    </div>
                `;
                dadosClienteConsulta = null;
                return;
            }

            // Cliente existe - salvar dados
            dadosClienteConsulta = {
                nome: data.cliente.nome,
                telefone: tel,
                data_nascimento: data.cliente.data_nascimento
            };

            if (data.agendamentos.length === 0) {
                // Tem cadastro mas sem agendamentos futuros
                result.innerHTML = `
                    <div style="background:var(--brand-light);padding:20px 16px;border-radius:20px;border:2px solid var(--brand-color);text-align:center;">
                        <i class="bi bi-person-check-fill" style="font-size:2.5rem;color:var(--brand-color);margin-bottom:10px;display:block;"></i>
                        <strong style="color:var(--brand-color);font-size:1.1rem;display:block;margin-bottom:8px;">Olá, ${data.cliente.nome.split(' ')[0]}!</strong>
                        <p style="color:var(--text-muted);margin-bottom:18px;font-size:0.9rem;line-height:1.5;">Você não tem agendamentos futuros.</p>
                        <button class="btn-action" onclick="agendarComDados();" style="margin-top:0;font-size:0.95rem;">
                            <i class="bi bi-calendar-plus"></i> Agendar Agora
                        </button>
                    </div>
                `;
            } else {
                // Tem agendamentos
                let html = `
                    <div style="background:var(--brand-light);padding:14px;border-radius:16px;margin-bottom:16px;border:1px solid var(--brand-color);">
                        <strong style="color:var(--brand-color);display:block;margin-bottom:4px;font-size:0.95rem;">
                            <i class="bi bi-person-check-fill"></i> ${data.cliente.nome}
                        </strong>
                        <small style="color:var(--text-muted);font-size:0.85rem;">Você tem ${data.agendamentos.length} agendamento(s) futuro(s)</small>
                    </div>
                `;

                data.agendamentos.forEach(ag => {
                    const [ano, mes, dia] = ag.data_agendamento.split('-');
                    const dataFormatada = `${dia}/${mes}/${ano}`;
                    const preco = parseFloat(ag.valor || 0).toFixed(2).replace('.', ',');
                    
                    html += `
                        <div class="agendamento-card">
                            <div class="agendamento-header">
                                <div class="agendamento-icon">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div class="agendamento-info">
                                    <div class="agendamento-servico">${ag.servico || 'Serviço'}</div>
                                    <div class="agendamento-data">
                                        <i class="bi bi-clock"></i> ${dataFormatada} às ${ag.horario}
                                    </div>
                                </div>
                                <div class="agendamento-preco">R$ ${preco}</div>
                            </div>
                            ${ag.observacoes ? `<p style="color:var(--text-muted);font-size:0.85rem;margin:0;line-height:1.4;padding-top:4px;"><i class="bi bi-chat-left-text" style="font-size:0.9rem;"></i> ${ag.observacoes}</p>` : ''}
                        </div>
                    `;
                });

                html += `
                    <button class="btn-action" onclick="agendarComDados();" style="margin-top:12px;width:100%;font-size:0.95rem;">
                        <i class="bi bi-calendar-plus"></i> Fazer Novo Agendamento
                    </button>
                `;

                result.innerHTML = html;
            }
        } catch (error) {
            loading.style.display = 'none';
            btnBuscar.disabled = false;
            console.error('Erro ao buscar agendamentos:', error);
            mostrarErro('Erro ao Buscar', 'Não foi possível buscar os agendamentos. Verifique sua conexão e tente novamente.');
        }
    }

    function agendarComDados() {
        fecharConsulta();
        iniciarAgendamento();
        
        // Preencher dados após um pequeno delay para garantir que o DOM está pronto
        setTimeout(() => {
            if (dadosClienteConsulta) {
                const telInput = document.getElementById('telInput');
                const nomeInput = document.getElementById('nomeInput');
                const nascInput = document.getElementById('nascInput');
                
                if (telInput) {
                    telInput.value = dadosClienteConsulta.telefone;
                    maskPhone(telInput);
                }
                if (nomeInput) nomeInput.value = dadosClienteConsulta.nome;
                if (nascInput && dadosClienteConsulta.data_nascimento) {
                    nascInput.value = dadosClienteConsulta.data_nascimento;
                }
                
                // Mostrar card de boas-vindas
                const welcomeCard = document.getElementById('welcomeCard');
                const clientNameDisplay = document.getElementById('clientNameDisplay');
                if (welcomeCard && clientNameDisplay) {
                    clientNameDisplay.innerText = dadosClienteConsulta.nome.split(' ')[0];
                    welcomeCard.style.display = 'flex';
                }
                
                validateForm();
            }
        }, 300);
    }

    // ===== SELEÇÃO MÚLTIPLA DE SERVIÇOS =====
    function goToStep(step) {
        document.querySelectorAll('.step-screen').forEach(el => el.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');

        document.querySelectorAll('.step-wrapper').forEach((el, index) => {
            el.classList.remove('active', 'done');
            if (index + 1 === step) el.classList.add('active');
            if (index + 1 < step) el.classList.add('done');
        });

        // Inicializa calendário quando entrar no step 2
        if (step === 2) {
            initCalendar();
        }

        configureFooterForStep(step);
    }

    function selectService(el, id, nome, preco, duracao) {
        const isSelected = el.classList.contains('selected');
        
        if (isSelected) {
            // Desselecionar
            el.classList.remove('selected');
            selectedServices = selectedServices.filter(s => s.id !== id);
        } else {
            // Selecionar
            el.classList.add('selected');
            selectedServices.push({ id, nome, preco: parseFloat(preco), duracao: parseInt(duracao) });
        }

        updateSummary();
    }

    function updateSummary() {
        const summary = document.getElementById('bookingSummary');
        
        if (selectedServices.length === 0) {
            summary.style.display = 'none';

            // Esconde badge
            const badge = document.getElementById('selectedBadge');
            if (badge) badge.style.display = 'none';

            // Atualiza footer se estiver no passo 1
            if (document.getElementById('step1').classList.contains('active')) {
                configureFooterForStep(1);
            }
            return;
        }

        summary.style.display = 'block';
        
        const totalPreco = selectedServices.reduce((sum, s) => sum + s.preco, 0);
        currentServiceDuration = selectedServices.reduce((sum, s) => sum + s.duracao, 0);
        
        const servicosIds = selectedServices.map(s => s.id).join(',');
        document.getElementById('inServicoId').value = servicosIds;
        
        const nomesServicos = selectedServices.length === 1 
            ? selectedServices[0].nome 
            : `${selectedServices.length} serviços`;
        
        document.getElementById('sumServico').innerText = nomesServicos;
        document.getElementById('sumPreco').innerText = 'R$ ' + totalPreco.toFixed(2).replace('.', ',');

        // Limpa data/hora ao trocar serviços
        document.getElementById('inData').value = '';
        document.getElementById('inHorario').value = '';
        document.getElementById('sumDataHora').innerText = '';

        // Badge flutuante
        const badge = document.getElementById('selectedBadge');
        const badgeCount = document.getElementById('selectedCount');
        if (badge && badgeCount) {
            badgeCount.innerText = selectedServices.length;
            badge.style.display = 'flex';
            badge.style.animation = 'bounceIn 0.5s ease-out';
        }

        // Atualiza footer (se estiver no passo 1)
        if (document.getElementById('step1').classList.contains('active')) {
            configureFooterForStep(1);
        }
    }

    function continuarParaHorario() {
        if (selectedServices.length === 0) {
            alert('Selecione pelo menos um serviço');
            return;
        }
        goToStep(2);
    }

    function fetchTimes() {
        const dateVal = document.getElementById('dateInput').value;
        if (!dateVal) return;

        const horariosSection = document.getElementById('horariosSection');
        const loader = document.getElementById('loadingTimes');
        const container = document.getElementById('timesContainer');
        const noTimes = document.getElementById('noTimesMsg');

        // Mostra seção de horários
        horariosSection.style.display = 'block';
        
        container.innerHTML = '';
        noTimes.style.display = 'none';
        loader.style.display = 'block';

        // Limpa horário selecionado
        document.getElementById('inHorario').value = '';
        document.getElementById('sumDataHora').innerText = '';
        configureFooterForStep(2);

        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_horarios&data=${dateVal}&duracao=${currentServiceDuration}`)
            .then(res => res.json())
            .then(slots => {
                loader.style.display = 'none';

                const selectedDate = new Date(dateVal + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                let availableSlots = slots;
                if (selectedDate.getTime() === today.getTime()) {
                    const now = new Date();
                    const currentHour = now.getHours();
                    const currentMinute = now.getMinutes();
                    const currentMinutes = currentHour * 60 + currentMinute;
                    
                    availableSlots = slots.filter(time => {
                        const [hour, minute] = time.split(':').map(Number);
                        const slotMinutes = hour * 60 + minute;
                        return slotMinutes > currentMinutes;
                    });
                }

                if (!availableSlots.length) {
                    noTimes.style.display = 'block';
                } else {
                    availableSlots.forEach(time => {
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

        // Atualiza footer para passo 2 (agora com horário válido)
        configureFooterForStep(2);
    }

    function confirmarHorario() {
        const dateVal = document.getElementById('inData').value;
        const timeVal = document.getElementById('inHorario').value;
        
        if (!dateVal || !timeVal) {
            mostrarErro('Selecione uma data e horário', 'Por favor, escolha um horário disponível antes de continuar.');
            return;
        }
        
        const [year, month, day] = dateVal.split('-').map(Number);
        const [hour, minute] = timeVal.split(':').map(Number);
        const selectedDateTime = new Date(year, month - 1, day, hour, minute);
        const now = new Date();
        const selectedDate = new Date(year, month - 1, day);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate.getTime() === today.getTime() && selectedDateTime < now) {
            mostrarErro(
                'Horário Inválido', 
                `Não é possível agendar para ${day.toString().padStart(2,'0')}/${month.toString().padStart(2,'0')}/${year} às ${timeVal} pois este horário já passou. Por favor, escolha um horário futuro.`
            );
            document.querySelectorAll('.time-slot').forEach(t => t.classList.remove('selected'));
            document.getElementById('inHorario').value = '';
            document.getElementById('sumDataHora').innerText = '';
            configureFooterForStep(2);
            return;
        }
        
        goToStep(3);
    }
    
    function mostrarErro(titulo, mensagem) {
        document.getElementById('errorTitle').innerText = titulo;
        document.getElementById('errorMessage').innerText = mensagem;
        document.getElementById('errorModal').classList.add('active');
    }
    
    function fecharErro() {
        document.getElementById('errorModal').classList.remove('active');
    }

    <?php if (!empty($erroAntecedenciaMsg)): ?>
    document.addEventListener('DOMContentLoaded', function () {
        mostrarErro('Agendamento inválido', '<?php echo addslashes($erroAntecedenciaMsg); ?>');
    });
    <?php endif; ?>

    function validateForm(onlyReturn = false) {
        const telInput = document.getElementById('telInput');
        const nomeInput = document.getElementById('nomeInput');
        const tel = telInput.value.replace(/\D/g, '');
        const nome = nomeInput.value.trim();

        const valido = tel.length >= 10 && nome.length >= 3;

        // Só mexe no botão do footer se estiver no passo 3
        if (!onlyReturn && document.getElementById('step3').classList.contains('active')) {
            const footerBtn = document.getElementById('footerActionButton');
            if (footerBtn) {
                footerBtn.disabled = !valido;
            }
        }

        return valido;
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
                validateForm();
            })
            .catch(() => {
                loading.style.display = 'none';
                newFields.style.display = 'block';
                validateForm();
            });
    }

    function startSubmit() {
        // usado tanto no onsubmit do form quanto no botão do footer
        if (!validateForm(true)) return false;

        const footerBtn = document.getElementById('footerActionButton');
        if (footerBtn) {
            footerBtn.innerHTML = '<span class="loading-spinner"></span> Processando...';
            footerBtn.disabled = true;
        }

        return true; // deixa o submit seguir em frente
    }

    function maskPhone(i) {
        let v = i.value.replace(/\D/g, "");
        v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
        v = v.replace(/(\d)(\d{4})$/, "$1-$2");
        i.value = v;
    }

    // Data mínima = hoje
    const dateInput = document.getElementById('dateInput');
    if (dateInput) {
        dateInput.min = new Date().toISOString().split("T")[0];
    }

    // Instalar na tela inicial (PWA)
    const installCta = document.getElementById('installCta');
    const installSteps = document.getElementById('installSteps');
    const installPromptBtn = document.getElementById('installPromptBtn');
    let deferredPrompt = null;

    function isMobile() {
        return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    }

    function detectPlatform() {
        const ua = navigator.userAgent || '';
        const isiOS = /iPhone|iPad|iPod/i.test(ua);
        const isAndroid = /Android/i.test(ua);
        return { isiOS, isAndroid };
    }

    function setInstallSteps() {
        const { isiOS, isAndroid } = detectPlatform();
        
        const iosInstructions = document.getElementById('installInstructionsIOS');
        const androidInstructions = document.getElementById('installInstructionsAndroid');
        const bothInstructions = document.getElementById('installInstructionsBoth');
        
        // Esconde todos primeiro
        if (iosInstructions) iosInstructions.style.display = 'none';
        if (androidInstructions) androidInstructions.style.display = 'none';
        if (bothInstructions) bothInstructions.style.display = 'none';
        
        // Mostra o apropriado
        if (isiOS && iosInstructions) {
            iosInstructions.style.display = 'block';
        } else if (isAndroid && androidInstructions) {
            androidInstructions.style.display = 'block';
        } else if (bothInstructions) {
            // Se não conseguir detectar ou for desktop, mostra ambos
            bothInstructions.style.display = 'block';
        }
    }

    setInstallSteps();

    window.addEventListener('beforeinstallprompt', (e) => {
        if (!isMobile() || isStandalone()) return;
        e.preventDefault();
        deferredPrompt = e;
        // Mostra o botão de instalação quando o prompt estiver disponível
        if (installPromptBtn) {
            installPromptBtn.style.display = 'block';
        }
    });

    if (installPromptBtn) {
        installPromptBtn.addEventListener('click', async () => {
            if (!deferredPrompt) {
                alert('A instalação automática não está disponível neste navegador. Siga as instruções acima para adicionar manualmente.');
                return;
            }
            deferredPrompt.prompt();
            const choiceResult = await deferredPrompt.userChoice;
            if (choiceResult.outcome === 'accepted') {
                console.log('PWA instalado com sucesso');
            }
            deferredPrompt = null;
            installPromptBtn.style.display = 'none';
        });
    }

    // Oculta o botão se já estiver instalado
    if (isStandalone() && installPromptBtn) {
        installPromptBtn.style.display = 'none';
    }
</script>

<footer class="footer-develoi">
    <div class="footer-content">
        <div class="footer-info">
            <div class="footer-text">
                Tecnologia desenvolvida por
            </div>
            <a href="https://develoi.com/" target="_blank" rel="noopener noreferrer" class="footer-logo">
                <img src="img/logo-D.png" alt="Develoi">
                <span>Develoi</span>
            </a>
        </div>

        <div class="footer-action" id="footerAction" style="display:none;">
            <button type="button"
                    class="footer-action-btn"
                    id="footerActionButton"
                    onclick="continuarParaHorario()">
                <span id="footerActionText">Continuar</span>
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>
</footer>

<!-- Floating Badge de Serviços Selecionados -->
<div class="selected-services-badge" id="selectedBadge" style="display:none;">
    <i class="bi bi-check2-circle"></i>
    <span id="selectedCount">0</span>
</div>

<!-- Modal de Erro -->
<div class="error-modal" id="errorModal">
    <div class="error-content">
        <div class="error-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div class="error-title" id="errorTitle">Atenção!</div>
        <div class="error-message" id="errorMessage"></div>
        <button class="error-btn" onclick="fecharErro()">Entendi</button>
    </div>
</div>

</body> 
</html>
