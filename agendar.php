<?php 
// ========================================================= 
// 1. CONFIGURAÇÃO E BACKEND 
// ========================================================= 
 
// Ajuste o caminho do include conforme sua estrutura de pastas 
$dbPath = 'includes/db.php'; 
if (!file_exists($dbPath)) $dbPath = '../../includes/db.php'; 
require_once $dbPath; 

// 🔹 Detecta ambiente (prod vs local) - DECLARADO NO INÍCIO
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
 
// ID do Profissional (Pega da URL) 
$profissionalId = isset($_GET['user']) ? (int)$_GET['user'] : 0; 
 
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
 
// Endereço Formatado 
$enderecoCompleto = $profissional['endereco'] ?? ''; 
if (!empty($profissional['numero'])) $enderecoCompleto .= ', ' . $profissional['numero']; 
if (!empty($profissional['bairro'])) $enderecoCompleto .= ' - ' . $profissional['bairro']; 
 
// Foto / Logo 
$fotoPerfil = ''; 
$iniciais   = strtoupper(mb_substr($nomeEstabelecimento, 0, 1)); 
$temFoto    = false; 
 
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

// 🔹 NORMALIZAÇÃO DO CAMINHO DA FOTO PARA COMPARTILHAMENTO
if ($temFoto) {
    // Se já é uma URL absoluta (http/https), deixa como está
    if (preg_match('#^https?://#', $fotoPerfil)) {
        // URL absoluta, não mexe
    } else {
        // Garante que é um caminho relativo começando com /
        // Remove possíveis duplicações de /karen_site/controle-salao
        $fotoPerfil = '/' . ltrim($fotoPerfil, '/');
        
        // Se estiver em localhost e tiver /karen_site no path, remove duplicações
        if (!$isProd && strpos($fotoPerfil, '/karen_site/controle-salao/') !== false) {
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
        <meta property="og:url" content="<?php echo $isProd ? 'https://salao.develoi.com/agendar?user=' : 'http://'.$_SERVER['HTTP_HOST'].'/karen_site/controle-salao/agendar.php?user='; ?><?php echo $profissionalId; ?>">
        
        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:title" content="<?php echo htmlspecialchars($nomeEstabelecimento); ?> - Agende Online">
        <meta property="twitter:description" content="<?php echo htmlspecialchars($biografia); ?>">
        <meta property="twitter:image" content="<?php echo $imagemCompartilhamento; ?>">
        
        <!-- Canonical URL -->
        <link rel="canonical" href="<?php echo $isProd ? 'https://salao.develoi.com/agendar?user=' : 'http://'.$_SERVER['HTTP_HOST'].'/karen_site/controle-salao/agendar.php?user='; ?><?php echo $profissionalId; ?>">
        
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
          "url": "<?php echo $isProd ? 'https://salao.develoi.com/agendar?user=' : 'http://'.$_SERVER['HTTP_HOST'].'/karen_site/controle-salao/agendar.php?user='; ?><?php echo $profissionalId; ?>"
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
        /* --- SISTEMA DE CORES DINÂMICO --- */ 
        :root { 
            /* PHP injeta a cor escolhida no painel de Configurações */ 
            --brand-color: <?php echo $corPersonalizada; ?>; 
 
            /* Variações simuladas a partir da cor principal */ 
            --brand-dark: color-mix(in srgb, var(--brand-color), black 20%); 
            --brand-light: color-mix(in srgb, var(--brand-color), white 90%); 
 
            --bg-body: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
            --bg-card: #ffffff; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border: #e2e8f0; 
            --radius-lg: 24px; 
            --radius-md: 18px; 
            --shadow-card: 0 10px 30px -5px rgba(0,0,0,0.08);
            --shadow-strong: 0 25px 60px -15px rgba(15, 23, 42, 0.15);
        } 
 
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            -webkit-tap-highlight-color: transparent; 
        } 
 
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }

        body { 
            background: var(--bg-body);
            color: var(--text-main); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            position: relative;
            padding-bottom: 70px; /* Espaço para o footer fixo */
        }

        /* Aurora Background */
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
            opacity: 0.2;
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
            0% { transform: translate(0, 0); }
            100% { transform: translate(40px, -40px); }
        } 
 
        .app-container { display: grid; grid-template-columns: 1fr; min-height: 100vh; } 
 
        .sidebar { 
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            padding: 40px 30px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center; 
            border-bottom: 1px solid rgba(148,163,184,0.1);
            position: relative; 
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(15,23,42,0.05);
        } 
 
        .sidebar::before { 
            content: ''; 
            position: absolute; 
            top: 0; left: 0; right: 0; 
            height: 200px; 
            background: linear-gradient(to bottom, var(--brand-light), transparent); 
            z-index: 0; 
        } 
 
        .business-logo { 
            width: 130px; 
            height: 130px; 
            border-radius: 50%; 
            background: linear-gradient(135deg, white, #f8fafc);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12), 0 5px 15px rgba(0,0,0,0.08); 
            margin-bottom: 24px; 
            z-index: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            border: 5px solid white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .business-logo::after {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.4s;
        }
        
        .business-logo:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15), 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .business-logo:hover::after {
            opacity: 1;
        }
 
        .logo-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover;
            position: relative;
            z-index: 1;
        } 
        
        .logo-initial { 
            font-size: 3rem; 
            font-weight: 800; 
            color: var(--brand-color);
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
        } 
 
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
            margin-bottom: 24px; 
            max-width: 380px; 
            z-index: 1;
            line-height: 1.6;
            font-weight: 400;
        } 
 
        .info-pill { 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 10px 18px; 
            border-radius: 999px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--text-main); 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            margin-bottom: 10px; 
            z-index: 1;
            border: 1px solid rgba(148,163,184,0.15);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        } 
 
        .info-pill i {
            color: var(--brand-color);
            font-size: 1rem;
        } 
 
        .main-content { 
            padding: 20px; 
            width: 100%; 
            max-width: 600px; 
            margin: 0 auto; 
            z-index: 2; 
        } 
 
        @media (max-width: 767px) {
            .card-title {
                font-size: 1.5rem;
            }
            .business-name {
                font-size: 1.5rem;
            }
            .business-logo {
                width: 100px;
                height: 100px;
            }
            .business-bio {
                font-size: 0.9rem;
                max-width: 320px;
            }
            .step-label {
                font-size: 0.7rem;
            }
            .time-slots-grid {
                grid-template-columns: repeat(auto-fill, minmax(75px, 1fr));
                gap: 10px;
            }
            .main-content {
                padding: 14px 12px;
            }
            .service-card {
                grid-template-columns: 1fr auto;
                padding: 12px 14px;
                border-radius: 16px;
                box-shadow: 0 6px 16px rgba(15,23,42,0.08);
                gap: 10px;
            }
            .service-card.no-image {
                padding: 12px 14px;
            }
            .service-img {
                width: 56px;
                height: 56px;
                border-radius: 999px;
                box-shadow: 0 3px 8px rgba(15,23,42,0.15);
            }
            .service-content {
                padding: 0;
            }
            .service-title {
                font-size: 0.95rem;
                margin-bottom: 4px;
            }
            .service-description {
                font-size: 0.78rem;
                -webkit-line-clamp: 1;
                margin-bottom: 6px;
            }
            .service-duration {
                font-size: 0.75rem;
                padding: 3px 8px;
            }
            .service-price-wrapper {
                padding: 0;
                align-items: center;
                justify-content: flex-end;
                gap: 4px;
            }
            .service-price {
                font-size: 1.05rem;
            }
        }

        @media (min-width: 900px) { 
            .app-container { grid-template-columns: 450px 1fr; } 
            .sidebar { 
                border-right: 1px solid rgba(148,163,184,0.1); 
                border-bottom: none; 
                height: 100vh; 
                position: sticky; 
                top: 0; 
                justify-content: center; 
            } 
            .main-content { 
                padding: 60px; 
                max-width: 900px; 
                margin: 0 auto; 
                display: flex; 
                flex-direction: column; 
                justify-content: center; 
            } 
        } 
 
        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            position: relative;
            padding: 0 10px;
        } 
        .progress-line {
            position: absolute;
            top: 18px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: #e2e8f0;
            z-index: 0;
            border-radius: 10px;
        } 
        .step-dot { 
            width: 40px;
            height: 40px;
            border-radius: 50%; 
            background: white;
            border: 3px solid #e2e8f0; 
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center; 
            font-weight: 800;
            color: #94a3b8;
            transition: all 0.3s ease;
            font-family: 'Outfit', sans-serif;
        } 
        .step-label { 
            position: absolute;
            top: 50px;
            font-size: 0.8rem;
            font-weight: 700; 
            color: #94a3b8;
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
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            color: white; 
            transform: scale(1.15);
            box-shadow: 0 0 0 5px var(--brand-light), 0 8px 20px rgba(var(--brand-color-rgb), 0.3); 
        } 
        .step-wrapper.active .step-label {
            color: var(--brand-color);
        } 
        .step-wrapper.done .step-dot {
            border-color: var(--brand-color);
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            color: white;
        } 
 
        .card-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 12px;
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
        } 
        .card-subtitle {
            color: var(--text-muted);
            margin-bottom: 35px;
            font-size: 1rem;
            line-height: 1.5;
        } 
 
        .service-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 0;
            border-radius: var(--radius-md); 
            border: 2px solid rgba(148,163,184,0.1);
            box-shadow: var(--shadow-card); 
            cursor: pointer;
            transition: all 0.3s ease;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--brand-color), var(--brand-dark));
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            border-color: var(--brand-light);
            box-shadow: var(--shadow-strong);
        }
        
        .service-card:hover::before {
            opacity: 1;
        }
        
        .service-card.selected {
            border-color: var(--brand-color);
            background: linear-gradient(135deg, rgba(255,255,255,0.98), var(--brand-light));
            box-shadow: 0 0 0 4px rgba(var(--brand-color-rgb), 0.15), var(--shadow-strong);
            transform: translateY(-2px);
        }
        
        .service-card.selected .service-img {
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            border-color: var(--brand-color);
            transform: scale(1.05);
        }
        
        .service-card.selected .service-img::after {
            content: '✓';
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1rem;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            animation: checkPop 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes checkPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .service-card.selected .service-img-placeholder {
            color: white;
            opacity: 1;
        }
        
        .service-card.selected::before {
            opacity: 1;
        }
        
        .service-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-light), #f1f5f9);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        /* Card quando não tem imagem do serviço */
        .service-card.no-image {
            grid-template-columns: 1fr auto;
            padding: 16px 18px;
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
            font-size: 2rem;
            color: var(--brand-color);
            opacity: 0.6;
        }
        
        .service-content {
            flex: 1;
            padding: 18px 0;
            min-width: 0;
        }
        
        .service-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
        }
        
        .service-description {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .service-duration {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(148,163,184,0.1);
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .service-price-wrapper {
            padding: 18px 20px 18px 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        
        .service-price {
            font-weight: 800;
            color: var(--brand-color);
            font-size: 1.3rem;
            font-family: 'Outfit', sans-serif;
            white-space: nowrap;
        } 
 
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-main);
            letter-spacing: 0.02em;
        } 
        .form-control { 
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border); 
            border-radius: var(--radius-md);
            font-size: 1rem;
            outline: none; 
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        } 
        .form-control:focus {
            border-color: var(--brand-color);
            box-shadow: 0 0 0 4px rgba(var(--brand-color-rgb), 0.1);
            background: white;
        } 
 
        .btn-action { 
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            color: white;
            border: none;
            border-radius: 999px;
            font-size: 1rem; 
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px; 
            margin-top: 30px;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.15);
            text-decoration: none;
        } 
        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -5px rgba(0,0,0,0.25);
            color: white;
        } 
        .btn-action:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        } 
 
        .step-screen { display:none; animation:fadeIn 0.4s ease-out forwards; } 
        .step-screen.active { display:block; } 
        @keyframes fadeIn { 
            from { opacity:0; transform:translateY(10px); } 
            to   { opacity:1; transform:translateY(0); } 
        } 
 
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 12px;
        } 
        .time-slot { 
            padding: 14px;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 2px solid var(--border);
            border-radius: 12px; 
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Outfit', sans-serif;
        } 
        .time-slot:hover {
            border-color: var(--brand-color);
            color: var(--brand-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        } 
        .time-slot.selected {
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            color: white;
            border-color: var(--brand-color);
            box-shadow: 0 8px 20px rgba(var(--brand-color-rgb), 0.3);
        } 
 
        .btn-back { 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(148,163,184,0.1);
            color: var(--text-muted);
            font-weight: 600; 
            cursor: pointer;
            margin-bottom: 25px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: white;
            border-color: var(--brand-color);
            color: var(--brand-color);
            transform: translateX(-3px);
        } 
 
        .loading-spinner { 
            display:inline-block; width:20px; height:20px; 
            border:3px solid rgba(255,255,255,0.3); border-radius:50%; 
            border-top-color:white; animation:spin 1s infinite; 
        } 
        @keyframes spin { to { transform:rotate(360deg); } }

        /* Footer Develoi - Fixo no rodapé */
        .footer-develoi {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(148,163,184,0.15);
            padding: 16px 20px;
            text-align: center;
            z-index: 999;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
        }
        
        .footer-content {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: all 0.25s ease;
            padding: 6px 12px;
            border-radius: 10px;
        }
        
        .footer-logo:hover {
            color: var(--brand-color);
            background: rgba(79, 70, 229, 0.08);
            transform: translateY(-1px);
        }
        
        .footer-logo img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }
        
        .footer-text {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        
        @media (max-width: 640px) {
            .footer-develoi {
                padding: 12px 16px;
            }
            
            .footer-content {
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .footer-text {
                font-size: 0.65rem;
            }
            
            .footer-logo {
                font-size: 0.75rem;
                padding: 4px 10px;
            }
            
            .footer-logo img {
                width: 18px;
                height: 18px;
            }
        }
        
        @media (min-width: 768px) {
            .footer-content {
                flex-direction: row;
                justify-content: center;
                gap: 16px;
            }
        }

        /* Smooth Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ========================================== */
        /* SPLASH SCREEN & WELCOME */
        /* ========================================== */
        .splash-screen {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: splashFadeOut 0.6s ease-out 2s forwards;
        }
        
        .splash-logo {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 800;
            color: var(--brand-color);
            margin-bottom: 24px;
            animation: logoFloat 2s ease-in-out infinite;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .splash-text {
            color: white;
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            animation: textFadeIn 0.8s ease-out 0.3s backwards;
        }
        
        .splash-spinner {
            margin-top: 32px;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes splashFadeOut {
            to { opacity: 0; pointer-events: none; }
        }
        
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes textFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Welcome Screen */
        .welcome-screen {
            position: fixed;
            inset: 0;
            background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            padding: 32px;
            text-align: center;
            animation: fadeIn 0.5s ease-out 2.5s backwards;
        }
        
        .welcome-screen.hidden {
            display: none;
        }
        
        .welcome-logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, white, #f8fafc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--brand-color);
            margin-bottom: 24px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            border: 5px solid white;
        }
        
        .welcome-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 12px;
            font-family: 'Outfit', sans-serif;
        }
        
        .welcome-subtitle {
            font-size: 1.05rem;
            color: var(--text-muted);
            margin-bottom: 40px;
            max-width: 400px;
            line-height: 1.6;
        }
        
        .welcome-options {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
            max-width: 400px;
        }
        
        .btn-welcome {
            padding: 16px 26px;
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            color: white;
            border: none;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.2);
        }
        
        .btn-welcome-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .btn-welcome-icon i {
            font-size: 0.95rem;
        }
        
        .btn-welcome:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px -5px rgba(0,0,0,0.3);
        }
        
        .btn-welcome.secondary {
            background: white;
            color: var(--brand-color);
            border: 2px solid var(--brand-color);
        }
        
        .btn-welcome.secondary:hover {
            background: var(--brand-light);
        }
        
        .btn-welcome.instagram {
            background: linear-gradient(135deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%);
            color: white;
            border: none;
        }
        
        .btn-welcome.instagram:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 40px -5px rgba(188,24,136,0.4);
        }

        /* Consultar Agendamento Modal */
        .consulta-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeIn 0.3s ease-out;
        }
        
        .consulta-modal.active {
            display: flex;
        }
        
        .consulta-content {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 450px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            animation: slideUp 0.4s ease-out;
            position: relative;
        }
        
        .consulta-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .consulta-content::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }
        
        .consulta-content::-webkit-scrollbar-thumb {
            background: var(--brand-color);
            border-radius: 10px;
        }
        
        @media (max-width: 767px) {
            .consulta-content {
                padding: 20px;
                border-radius: 20px;
                max-height: 90vh;
            }
            
            .consulta-content::-webkit-scrollbar {
                width: 4px;
            }
        }
        
        .consulta-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(148,163,184,0.1);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        
        .consulta-close:hover {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
        }
        
        .agendamento-card {
            background: linear-gradient(135deg, var(--brand-light), #ffffff);
            border: 2px solid var(--brand-color);
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 12px;
            animation: slideUp 0.3s ease-out;
        }
        
        .agendamento-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .agendamento-icon {
            width: 44px;
            height: 44px;
            background: var(--brand-color);
            color: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .agendamento-info {
            flex: 1;
            min-width: 0;
        }
        
        .agendamento-servico {
            font-weight: 700;
            color: var(--brand-dark);
            font-size: 1rem;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        
        .agendamento-data {
            color: var(--text-muted);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .agendamento-preco {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--brand-color);
            flex-shrink: 0;
        }
        
        @media (max-width: 767px) {
            .agendamento-card {
                padding: 14px;
                border-radius: 16px;
                margin-bottom: 10px;
            }
            
            .agendamento-header {
                gap: 8px;
            }
            
            .agendamento-icon {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
                border-radius: 12px;
            }
            
            .agendamento-servico {
                font-size: 0.9rem;
            }
            
            .agendamento-data {
                font-size: 0.8rem;
            }
            
            .agendamento-preco {
                font-size: 1rem;
            }
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
                padding: 12px 18px;
                font-size: 0.9rem;
                border-radius: 999px;
                gap: 8px;
                box-shadow: 0 6px 18px -4px rgba(0,0,0,0.2);
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

        /* Error Modal */
        .error-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeIn 0.3s ease-out;
        }
        
        .error-modal.active {
            display: flex;
        }
        
        .error-content {
            background: white;
            border-radius: 24px;
            padding: 32px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            animation: slideUp 0.4s ease-out;
            text-align: center;
        }
        
        .error-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #dc2626;
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }
        
        .error-message {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 24px;
        }
        
        .error-btn {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .error-btn:hover {
            transform: scale(1.05);
        }

        /* Selected Services Badge */
        .selected-services-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--brand-color), var(--brand-dark));
            color: white;
            padding: 12px 20px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 100;
            animation: bounceIn 0.5s ease-out;
            cursor: pointer;
        }
        
        .selected-services-badge.show {
            display: flex;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
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
                     alt="<?php echo htmlspecialchars($nomeEstabelecimento); ?>">
            <?php else: ?>
                <img src="img/logo-D.png" 
                     style="width:70%;height:70%;object-fit:contain;"
                     alt="Develoi">
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
                Nosso Instagram
            </a>
            <?php endif; ?>
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
                <div style="width:120px; height:120px; margin:0 auto 24px; background:white; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 20px 60px rgba(16, 185, 129, 0.3); border:5px solid #10b981; overflow:hidden;">
                    <?php if ($temFoto): ?>
                        <img src="<?php echo htmlspecialchars($fotoPerfil); ?>" 
                             style="width:100%;height:100%;object-fit:cover;"
                             alt="<?php echo htmlspecialchars($nomeEstabelecimento); ?>">
                    <?php else: ?>
                        <img src="img/logo-D.png" 
                             style="width:70%;height:70%;object-fit:contain;"
                             alt="Develoi">
                    <?php endif; ?>
                </div>
                <h2 class="card-title" style="color:#10b981; margin-bottom:16px;">Agendamento Confirmado!</h2> 
                <div style="background:rgba(16, 185, 129, 0.08); padding:20px; border-radius:20px; margin-bottom:30px; border:1px solid rgba(16, 185, 129, 0.2);">
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
                
                $msg = rawurlencode(
                    "Olá! 👋\n\n" .
                    "Gostaria de *confirmar meu agendamento*:\n\n" .
                    "📌 *Serviço:* {$servicoConfirmado}\n" .
                    "📅 *Data:* {$diaSemana}, {$dataFormatada}\n" .
                    "🕐 *Horário:* {$horaConfirmada}\n\n" .
                    "Aguardo a confirmação. Obrigado! 😊"
                );
                ?>
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
                                        <h3 class="service-title"><?php echo htmlspecialchars($s['nome']); ?></h3>
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
                                        <h3 class="service-title"><?php echo htmlspecialchars($s['nome']); ?></h3>
                                        <?php if (!empty($s['observacao'])): ?>
                                            <div class="service-description"><?php echo htmlspecialchars($s['observacao']); ?></div>
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

                    <button type="button" class="btn-action" onclick="continuarParaHorario()" id="btnContinuar" disabled style="opacity: 0.5">
                        <span id="btnContinuarText">Selecione um serviço</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
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

                    <button type="button" class="btn-action" onclick="confirmarHorario()" id="btnConfirmarHorario" disabled style="opacity: 0.5; margin-top: 20px;">
                        <span>Confirmar Horário</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
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
 
                    <button type="submit" id="btnConfirmar" class="btn-action" disabled style="opacity: 0.5"> 
                        Confirmar Agendamento 
                    </button> 
                </div> 
            </form> 
        <?php endif; ?> 
    </div> 
</div> 
 
<script>
    const PROF_ID = <?php echo $profissionalId; ?>;
    const CURRENT_PAGE = <?php echo json_encode($isProd ? '/agendar' : '/karen_site/controle-salao/agendar.php'); ?>;
    let selectedServices = [];
    let currentServiceDuration = 0;

    // ===== SPLASH & WELCOME =====
    window.addEventListener('DOMContentLoaded', () => {
        <?php if ($sucesso): ?>
            document.getElementById('splashScreen').style.display = 'none';
            document.getElementById('mainApp').style.display = 'grid';
        <?php else: ?>
            setTimeout(() => {
                document.getElementById('splashScreen').style.display = 'none';
            }, 2600);
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

    function fecharConsulta() {
        document.getElementById('consultaModal').classList.remove('active');
        document.getElementById('consultaTelefone').value = '';
        document.getElementById('consultaResult').innerHTML = '';
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
        const btnText = document.getElementById('btnContinuarText');
        const btnContinuar = document.getElementById('btnContinuar');
        
        if (selectedServices.length === 0) {
            summary.style.display = 'none';
            if (btnText) btnText.innerText = 'Selecione um serviço';
            if (btnContinuar) {
                btnContinuar.disabled = true;
                btnContinuar.style.opacity = '0.5';
            }
            // Esconde badge
            const badge = document.getElementById('selectedBadge');
            if (badge) badge.style.display = 'none';
            return;
        }

        summary.style.display = 'block';
        if (btnContinuar) {
            btnContinuar.disabled = false;
            btnContinuar.style.opacity = '1';
        }
        
        const totalPreco = selectedServices.reduce((sum, s) => sum + s.preco, 0);
        currentServiceDuration = selectedServices.reduce((sum, s) => sum + s.duracao, 0);
        
        const servicosIds = selectedServices.map(s => s.id).join(',');
        document.getElementById('inServicoId').value = servicosIds;
        
        const nomesServicos = selectedServices.length === 1 
            ? selectedServices[0].nome 
            : `${selectedServices.length} serviços`;
        
        if (btnText) {
            btnText.innerText = selectedServices.length === 1 
                ? 'Continuar com 1 serviço'
                : `Continuar com ${selectedServices.length} serviços`;
        }
        
        document.getElementById('sumServico').innerText = nomesServicos;
        document.getElementById('sumPreco').innerText = 'R$ ' + totalPreco.toFixed(2).replace('.', ',');
        
        // Atualiza badge flutuante
        const badge = document.getElementById('selectedBadge');
        const badgeCount = document.getElementById('selectedCount');
        if (badge && badgeCount) {
            badgeCount.innerText = selectedServices.length;
            badge.style.display = 'flex';
            badge.style.animation = 'bounceIn 0.5s ease-out';
        }
        
        document.getElementById('inData').value = '';
        document.getElementById('inHorario').value = '';
        document.getElementById('sumDataHora').innerText = '';
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

        const loader = document.getElementById('loadingTimes');
        const container = document.getElementById('timesContainer');
        const noTimes = document.getElementById('noTimesMsg');
        const btnConfirmarHorario = document.getElementById('btnConfirmarHorario');

        container.innerHTML = '';
        noTimes.style.display = 'none';
        loader.style.display = 'block';
        if (btnConfirmarHorario) {
            btnConfirmarHorario.disabled = true;
            btnConfirmarHorario.style.opacity = '0.5';
        }

        fetch(`${CURRENT_PAGE}?user=${PROF_ID}&action=buscar_horarios&data=${dateVal}&duracao=${currentServiceDuration}`)
            .then(res => res.json())
            .then(slots => {
                loader.style.display = 'none';

                // Filtrar horários passados se a data for hoje
                const selectedDate = new Date(dateVal + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                let availableSlots = slots;
                if (selectedDate.getTime() === today.getTime()) {
                    const now = new Date();
                    const currentHour = now.getHours();
                    const currentMinute = now.getMinutes();
                    
                    availableSlots = slots.filter(time => {
                        const [hour, minute] = time.split(':').map(Number);
                        const slotMinutes = hour * 60 + minute;
                        const currentMinutes = currentHour * 60 + currentMinute;
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

        // Habilitar botão confirmar horário
        const btnConfirmarHorario = document.getElementById('btnConfirmarHorario');
        if (btnConfirmarHorario) {
            btnConfirmarHorario.disabled = false;
            btnConfirmarHorario.style.opacity = '1';
        }
    }

    function confirmarHorario() {
        const dateVal = document.getElementById('inData').value;
        const timeVal = document.getElementById('inHorario').value;
        
        if (!dateVal || !timeVal) {
            mostrarErro('Selecione uma data e horário', 'Por favor, escolha um horário disponível antes de continuar.');
            return;
        }
        
        // Validar se a data não é passada
        const [year, month, day] = dateVal.split('-').map(Number);
        const [hour, minute] = timeVal.split(':').map(Number);
        
        const selectedDateTime = new Date(year, month - 1, day, hour, minute);
        const now = new Date();
        
        if (selectedDateTime < now) {
            mostrarErro(
                'Horário Inválido', 
                `Não é possível agendar para ${day.toString().padStart(2,'0')}/${month.toString().padStart(2,'0')}/${year} às ${timeVal} pois este horário já passou. Por favor, escolha um horário futuro.`
            );
            // Limpar seleção
            document.querySelectorAll('.time-slot').forEach(t => t.classList.remove('selected'));
            document.getElementById('inHorario').value = '';
            document.getElementById('sumDataHora').innerText = '';
            const btnConfirmarHorario = document.getElementById('btnConfirmarHorario');
            if (btnConfirmarHorario) {
                btnConfirmarHorario.disabled = true;
                btnConfirmarHorario.style.opacity = '0.5';
            }
            return;
        }
        
        // Horário válido, avançar para próxima etapa
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

    function validateForm() {
        const telInput = document.getElementById('telInput');
        const nomeInput = document.getElementById('nomeInput');
        const btn = document.getElementById('btnConfirmar');
        
        const tel = telInput.value.replace(/\D/g, '');
        const nome = nomeInput.value.trim();
        
        // Habilita botão se telefone (10+ dígitos) e nome estiverem preenchidos
        if (tel.length >= 10 && nome.length >= 3) {
            btn.disabled = false;
            btn.style.opacity = '1';
        } else {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        }
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
    const dateInput = document.getElementById('dateInput');
    if (dateInput) {
        dateInput.min = new Date().toISOString().split("T")[0];
    }
</script>

<footer class="footer-develoi">
    <div class="footer-content">
        <div class="footer-text">
            Tecnologia desenvolvida por
        </div>
        <a href="https://develoi.com/" target="_blank" rel="noopener noreferrer" class="footer-logo">
            <img src="img/logo-D.png" alt="Develoi">
            <span>Develoi</span>
        </a>
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