<?php
require_once __DIR__ . '/../../includes/config.php';
// pages/agenda/agenda.php

// =========================================================
// 1. CONFIGURAÇÃO E SESSÃO
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    // Redireciona para login se não estiver logado
    $isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// Tenta incluir o DB com verificação de caminho
$dbPath = '../../includes/db.php';
if (!file_exists($dbPath)) {
    // Fallback se a estrutura de pastas for diferente
    $dbPath = 'includes/db.php'; 
    if (!file_exists($dbPath)) die("Erro: db.php não encontrado.");
}
require_once $dbPath;

// Parâmetros de Entrada
$dataExibida = $_GET['data'] ?? date('Y-m-d');
$viewType    = $_GET['view'] ?? 'day'; // 'day', 'week', 'month'
$hoje        = date('Y-m-d');

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$agendaUrl = $isProd
    ? '/agenda' // em produção usa rota amigável
    : '/karen_site/controle-salao/pages/agenda/agenda.php';

// Buscar estabelecimento e gerar link de agendamento
$stmtUser = $pdo->prepare("SELECT estabelecimento FROM usuarios WHERE id = ?");
$stmtUser->execute([$userId]);
$userInfo = $stmtUser->fetch();
$nomeEstabelecimento = $userInfo['estabelecimento'] ?? 'Meu Salão';

// Link de agendamento online
$linkAgendamento = $isProd 
    ? "https://salao.develoi.com/agendar?user={$userId}"
    : "http://localhost/karen_site/controle-salao/agendar.php?user={$userId}";

// Função Auxiliar de Redirecionamento
function redirect($data, $view) {
    global $agendaUrl;
    header("Location: {$agendaUrl}?data=" . urlencode($data) . "&view=" . $view);
    exit;
}

// =========================================================
// 2. AÇÕES DO BACKEND (POST/GET)
// =========================================================

try {
    // 2.1 Excluir Agendamento
    if (isset($_GET['delete'])) {
        require_once __DIR__ . '/../../includes/recorrencia_helper.php';
        
        $id = (int)$_GET['delete'];
        $tipoExclusao = $_GET['tipo_exclusao'] ?? 'unico'; // 'unico', 'proximos', 'serie'
        
        // Verificar se é agendamento recorrente
        $stmt = $pdo->prepare("SELECT serie_id, e_recorrente FROM agendamentos WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        $agendamento = $stmt->fetch();
        
        if ($agendamento && $agendamento['e_recorrente'] && !empty($agendamento['serie_id'])) {
            // É recorrente - aplicar lógica de exclusão
            if ($tipoExclusao === 'serie') {
                // Excluir toda a série
                cancelarSerieCompleta($pdo, $agendamento['serie_id'], $userId);
            } elseif ($tipoExclusao === 'proximos') {
                // Excluir este e os próximos
                cancelarOcorrenciaEProximas($pdo, $id, $userId);
            } else {
                // Excluir apenas este
                cancelarOcorrencia($pdo, $id, $userId);
            }
        } else {
            // Agendamento único - exclusão simples
            $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id=? AND user_id=?");
            $stmt->execute([$id, $userId]);
        }
        
        redirect($dataExibida, $viewType);
    }

    // 2.2 Mudar Status
    if (isset($_GET['status'], $_GET['id'])) {
        $novoStatus = $_GET['status'];
        $agendamentoId = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("UPDATE agendamentos SET status=? WHERE id=? AND user_id=?");
        $stmt->execute([$novoStatus, $agendamentoId, $userId]);
        
        // Se confirmou, notifica o bot
        if ($novoStatus === 'Confirmado') {
            require_once __DIR__ . '/../../includes/notificar_bot.php';
            notificarBotAgendamentoConfirmado($pdo, $agendamentoId);
        }
        
        redirect($dataExibida, $viewType);
    }

    // 2.3 Novo Agendamento (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
        require_once __DIR__ . '/../../includes/recorrencia_helper.php';
        
        $cliente   = trim($_POST['cliente']);
        $servicoId = !empty($_POST['servico_id']) ? (int)$_POST['servico_id'] : null;
        $servicoNome = trim($_POST['servico_nome'] ?? '');
        $valor     = str_replace(',', '.', $_POST['valor']);
        $horario   = $_POST['horario'];
        $obs       = trim($_POST['obs']);
        $dataAg    = $_POST['data_agendamento'] ?? $dataExibida;
        $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;

        // Validação de segurança: Impede data passada no backend
        if ($dataAg < $hoje) { 
            $dataAg = $hoje; 
        }

        // Busca nome do serviço se não foi fornecido
        if ($servicoId && !$servicoNome) {
            $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id=? AND user_id=?");
            $stmt->execute([$servicoId, $userId]);
            $servicoNome = $stmt->fetchColumn() ?: 'Serviço';
        }

        if ($cliente && $horario) {
            // Preparar dados para criar agendamento (pode ser único ou recorrente)
            $dadosAgendamento = [
                'cliente_id' => $clienteId,
                'cliente_nome' => $cliente,
                'servico_id' => $servicoId,
                'servico_nome' => $servicoNome,
                'valor' => $valor,
                'horario' => $horario,
                'data_inicio' => $dataAg,
                'observacoes' => $obs
            ];

            // Criar agendamento (verifica automaticamente se é recorrente)
            $resultado = criarAgendamentosRecorrentes($pdo, $userId, $dadosAgendamento);
            
            if ($resultado['sucesso']) {
                if (!empty($resultado['serie_id'])) {
                    // É recorrente - redirecionar com mensagem de sucesso
                    $msg = "Série criada com {$resultado['qtd_criados']} agendamentos!";
                    $_SESSION['mensagem_sucesso'] = $msg;
                }
            } else {
                $_SESSION['mensagem_erro'] = $resultado['erro'] ?? 'Erro ao criar agendamento';
            }
        }
        // Redireciona para o dia que foi agendado para o usuário ver
        redirect($dataAg, 'day');
    }

} catch (PDOException $e) {
    // Captura erros de banco (como o Locked) e mostra amigável
    die("Erro no banco de dados (Tente novamente em alguns segundos): " . $e->getMessage());
}

// =========================================================
// 3. CONSULTA DE DADOS (VIEW LOGIC)
// =========================================================

$agendamentos = [];
$faturamento = 0;
$diasComAgendamento = []; // Para marcar pontinhos no calendário mensal

// Define intervalo de datas baseado na View
if ($viewType === 'month') {
    $start = date('Y-m-01', strtotime($dataExibida));
    $end   = date('Y-m-t', strtotime($dataExibida));
    
    // Título do Mês
    setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
    $meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $tituloData = $meses[(int)date('m', strtotime($dataExibida))] . ' ' . date('Y', strtotime($dataExibida));

} elseif ($viewType === 'week') {
    $ts = strtotime($dataExibida);
    $diaSemana = date('w', $ts);
    $start = date('Y-m-d', strtotime("-$diaSemana days", $ts));
    $end   = date('Y-m-d', strtotime("+6 days", strtotime($start)));
    $tituloData = date('d/m', strtotime($start)) . ' a ' . date('d/m', strtotime($end));

} else {
    // Dia
    $start = $dataExibida;
    $end   = $dataExibida;
    $diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $tituloData = $diasSemana[date('w', strtotime($dataExibida))] . ', ' . date('d/m', strtotime($dataExibida));
}

// Busca principal com dados de recorrência
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        s.tipo_recorrencia,
        s.dias_semana as recorrencia_dias_semana,
        s.intervalo_dias
    FROM agendamentos a
    LEFT JOIN servicos s ON s.nome = a.servico AND s.user_id = a.user_id
    WHERE a.user_id = ? 
    AND a.data_agendamento BETWEEN ? AND ? 
    ORDER BY a.data_agendamento ASC, a.horario ASC
");
$stmt->execute([$userId, $start, $end]);
$raw = $stmt->fetchAll();

// Listas para os Modais
$servicos = $pdo->query("SELECT id, nome, preco, duracao, permite_recorrencia, tipo_recorrencia, dias_semana FROM servicos WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();
$clientes = $pdo->query("SELECT nome, telefone FROM clientes WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();

// Processamento dos dados (Correção de Preço 0 e Organização)
foreach ($raw as &$r) {
    // Se o valor for 0, tenta pegar o preço padrão do serviço
    if ((float)$r['valor'] <= 0) {
        foreach ($servicos as $s) {
            if ($s['nome'] === $r['servico']) {
                $r['valor'] = $s['preco']; 
                break;
            }
        }
    }
}
unset($r); // Limpa referência

// Array para contar agendamentos por dia (para o calendário mensal)
$agendamentosPorDia = [];

// Organiza array final baseado na View
if ($viewType === 'day') {
    // Filtrar apenas agendamentos do dia específico
    $agendamentos = array_filter($raw, function($ag) use ($dataExibida) {
        return $ag['data_agendamento'] === $dataExibida;
    });
    foreach ($agendamentos as $ag) if(($ag['status']??'')!=='Cancelado') $faturamento += $ag['valor'];

} elseif ($viewType === 'week') {
    for($i=0; $i<=6; $i++) {
        $d = date('Y-m-d', strtotime("+$i days", strtotime($start)));
        $agendamentos[$d] = [];
    }
    foreach ($raw as $ag) {
        $agendamentos[$ag['data_agendamento']][] = $ag;
        if(($ag['status']??'')!=='Cancelado') $faturamento += $ag['valor'];
    }

} elseif ($viewType === 'month') {
    // Conta agendamentos por dia para mostrar no badge
    foreach ($raw as $ag) {
        $data = $ag['data_agendamento'];
        if (!isset($agendamentosPorDia[$data])) {
            $agendamentosPorDia[$data] = 0;
        }
        $agendamentosPorDia[$data]++;
        $diasComAgendamento[$data] = true;
        if(($ag['status']??'')!=='Cancelado') $faturamento += $ag['valor'];
    }
}

// Datas para navegação (setas < >)
$mod = ($viewType==='month') ? 'month' : (($viewType==='week') ? 'week' : 'day');
$dataAnt = date('Y-m-d', strtotime($dataExibida . " -1 $mod"));
$dataPro = date('Y-m-d', strtotime($dataExibida . " +1 $mod"));

// =========================================================
// 4. ESTRUTURA HTML E CSS
// =========================================================
$pageTitle = 'Minha Agenda';
include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    /* === DESIGN CLEAN SAAS - AGENDA === */
    :root {
        --primary-color: #4f46e5;
        --primary-dark: #4338ca;
        --secondary: #8b5cf6;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --bg-page: #f8fafc;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
        --shadow-card: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
        --shadow-hover: 0 4px 12px rgba(0,0,0,0.1);
        --radius-lg: 16px;
        --radius-md: 12px;
        --radius-sm: 8px;
    }
    
    * { 
        box-sizing: border-box; 
    }

    body {
        background: var(--bg-page);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding-bottom: 90px;
        margin: 0;
        font-size: 0.875rem;
        color: var(--text-main);
        line-height: 1.5;
    }

    /* === HEADER === */
    .app-header {
        position: relative;
        background: #ffffff;
        padding: 1rem 1.125rem;
        border-bottom: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }
    
    .input-desktop {
        display: block;
    }
    .select-mobile {
        display: none;
    }
    
    .agenda-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0 0 0.875rem 0;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* === VIEW CONTROL === */
    .view-control {
        display: flex;
        background: #f1f5f9;
        padding: 0.25rem;
        border-radius: var(--radius-sm);
        margin-bottom: 0.875rem;
        gap: 0.25rem;
    }
    .view-opt {
        flex: 1;
        text-align: center;
        padding: 0.5rem 0;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .view-opt.active {
        background: var(--primary-color);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    /* === DATE NAVIGATION === */
    .date-nav-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.875rem;
        gap: 0.75rem;
    }
    .btn-circle {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 50%;
        background: #ffffff;
        border: 1px solid var(--border-color);
        color: var(--text-main);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-circle:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .date-picker-trigger {
        position: relative;
        text-align: center;
    }
    .current-date-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-main);
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        border-radius: var(--radius-sm);
        background: #f1f5f9;
    }
    .hidden-date-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    /* === FINANCE CARD === */
    .finance-card {
        margin-top: 0.75rem;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 1rem;
        border-radius: var(--radius-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
    .fin-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: rgba(255,255,255,0.9);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .fin-value {
        font-size: 1.125rem;
        font-weight: 700;
        color: white;
    }
    
    /* === LINK CARD === */
    .link-card {
        margin-top: 0.75rem;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        padding: 1rem;
        border-radius: var(--radius-md);
        box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    }
    .link-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }
    .link-card-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .link-input-group {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    .link-input {
        flex: 1;
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: var(--radius-sm);
        padding: 0.5rem 0.75rem;
        color: white;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .link-input:focus {
        outline: none;
        border-color: rgba(255,255,255,0.5);
        background: rgba(255,255,255,0.25);
    }
    .btn-copy-link,
    .btn-share-link {
        background: rgba(255,255,255,0.95);
        border: none;
        padding: 0.5rem 0.875rem;
        border-radius: var(--radius-sm);
        color: #6366f1;
        font-weight: 600;
        font-size: 0.75rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.375rem;
        transition: all 0.2s ease;
    }
    .btn-copy-link:hover,
    .btn-share-link:hover {
        background: white;
        transform: translateY(-1px);
    }
    .link-hint {
        font-size: 0.6875rem;
        color: rgba(255,255,255,0.9);
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    /* === CONTENT AREA === */
    .content-area {
        padding: 0.75rem 0.875rem 1rem;
    }

    .appt-card {
        background: #ffffff;
        border-radius: var(--radius-md);
        padding: 0.875rem 1rem;
        margin-bottom: 0.75rem;
        position: relative;
        display: flex;
        gap: 0.875rem;
        box-shadow: var(--shadow-card);
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }
    .appt-card:hover {
        box-shadow: var(--shadow-hover);
        border-color: var(--primary-color);
    }
    .time-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 3rem;
        background: #f8fafc;
        border-radius: var(--radius-sm);
        padding: 0.5rem;
        justify-content: center;
    }
    .time-val {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1;
    }
    .time-min {
        font-size: 0.6875rem;
        color: var(--text-muted);
        font-weight: 500;
        margin-top: 0.125rem;
    }
    
    .info-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.25rem;
    }
    .client-name {
        font-weight: 600;
        color: var(--text-main);
        font-size: 0.875rem;
        margin-bottom: 0.125rem;
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;

        /* permite quebrar em 2 linhas sem estourar o card */
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Cores de fundo para status */
    .appt-card[data-status="Confirmado"] .client-name {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
    }
    
    .appt-card[data-status="Pendente"] .client-name {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
    }
    
    .appt-card[data-status="Cancelado"] .client-name {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #991b1b;
    }

    /* Card clicável */
    .appt-card.appt-clickable {
        cursor: pointer;
    }
    .appt-card.appt-clickable .client-name {
        text-decoration: none;
    }
    .appt-card.appt-clickable:hover .client-name {
        text-decoration: underline;
    }
    .service-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        color: var(--text-muted);
        flex-wrap: wrap;
        font-weight: 500;
    }
    .price-tag {
        background: #eef2ff;
        color: var(--primary-color);
        font-size: 0.6875rem;
        font-weight: 600;
        padding: 0.1875rem 0.5625rem;
        border-radius: var(--radius-sm);
    }

    /* === STATUS BADGE === */
    .status-badge {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
    }
    .st-Confirmado { background: var(--success); }
    .st-Pendente { background: var(--warning); }
    .st-Cancelado { background: var(--danger); }

    .appt-card button {
        background: #f8fafc;
        border: none;
        font-size: 1rem;
        color: var(--text-muted);
        padding: 0.5rem;
        align-self: center;
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .appt-card button:hover {
        background: var(--primary-color);
        color: white;
    }

    /* === CALENDAR GRID === */
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    .week-day-name {
        text-align: center;
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 0.25rem;
        letter-spacing: 0.03em;
    }
    .day-cell {
        aspect-ratio: 1;
        background: #ffffff;
        border-radius: var(--radius-sm);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-main);
        text-decoration: none;
        border: 1px solid var(--border-color);
        position: relative;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s ease;
    }
    .day-cell:hover:not(.empty) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99,102,241,0.2);
        border-color: var(--primary-color);
    }
    .day-cell.today {
        border-color: var(--primary-color);
        color: white;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        box-shadow: 0 4px 12px rgba(99,102,241,0.4);
    }
    .day-cell.empty {
        background: transparent;
        box-shadow: none;
        pointer-events: none;
        border: none;
    }
    .day-cell.has-events {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-color: #93c5fd;
    }
    .day-cell.has-events.today {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    }
    .event-count-badge {
        position: absolute;
        top: 0.25rem;
        right: 0.25rem;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        font-size: 0.6875rem;
        font-weight: 600;
        padding: 0.125rem 0.375rem;
        border-radius: var(--radius-sm);
        min-width: 1.125rem;
        text-align: center;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(99,102,241,0.3);
    }
    .day-cell.today .event-count-badge {
        background: rgba(255,255,255,0.25);
        color: white;
        font-weight: 700;
    }

    /* === WEEK HEADER === */
    .week-header {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        margin: 1rem 0.125rem 0.375rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }
    .week-header::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-color);
    }

    /* === FAB BUTTON === */
    .fab-add {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        width: 3.5rem;
        height: 3.5rem;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        border-radius: 50%;
        border: none;
        font-size: 1.5rem;
        box-shadow: 0 8px 24px rgba(99,102,241,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .fab-add:hover {
        transform: scale(1.08) rotate(90deg);
        box-shadow: 0 12px 32px rgba(99,102,241,0.5);
    }
    .fab-add:active {
        transform: scale(0.95) rotate(90deg);
    }

    /* === MODALS === */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.6);
        z-index: 2000;
        display: none;
        align-items: flex-end;
        justify-content: center;
    }
    .modal-overlay.active {
        display: flex;
        animation: fadeIn 0.2s ease-out;
    }
    .sheet-modal {
        background: white;
        width: 100%;
        max-width: 500px;
        border-radius: var(--radius-md) var(--radius-md) 0 0;
        padding: 1.5rem 1.25rem 1.75rem;
        animation: slideUp 0.3s ease-out;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
    }
    @keyframes slideUp { 
        from { 
            transform: translateY(100%); 
            opacity: 0;
        } 
        to { 
            transform: translateY(0); 
            opacity: 1;
        } 
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .form-group { margin-bottom: 1rem; }
    .form-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-main);
        margin-bottom: 0.375rem;
        display: block;
    }
    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        background: #ffffff;
        outline: none;
        box-sizing: border-box;
        font-family: inherit;
        transition: all 0.2s ease;
    }
    .form-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
    }
    .form-input:disabled,
    .form-input[readonly] {
        background: #f8fafc;
        color: var(--text-muted);
        cursor: not-allowed;
    }
    textarea.form-input {
        resize: vertical;
        min-height: 5rem;
    }
    .btn-main {
        width: 100%;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        border: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.875rem;
        margin-top: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    }
    .btn-main:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(99,102,241,0.4);
    }
    
    .btn-cancel {
        width: 100%;
        padding: 0.75rem 1.5rem;
        background: white;
        color: var(--text-main);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.875rem;
        margin-top: 0.625rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-cancel:hover {
        background: #f8fafc;
    }
    
    .empty-state {
        text-align: center;
        padding: 2.5rem 1.25rem;
        color: var(--text-muted);
        font-size: 0.8125rem;
    }
    .empty-state button {
        color: var(--primary-color);
        background: none;
        border: none;
        font-weight: 600;
        font-size: 0.8125rem;
        margin-top: 0.25rem;
        cursor: pointer;
    }

    /* === FREE SLOTS SECTION === */
    .free-slots-section {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-radius: var(--radius-md);
        padding: 1rem;
        margin-top: 1.25rem;
        border: 1px solid #93c5fd;
    }
    .free-slots-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-main);
        margin: 0 0 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .free-slots-title i {
        color: #6366f1;
    }
    /* Horários disponíveis: grid mais padronizado */
    .free-slots-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr)); /* 4 colunas fixas no desktop */
        gap: 0.5rem;
    }

    /* 3 colunas no mobile pra não ficar apertado */
    @media (max-width: 600px) {
        .free-slots-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .slot-chip {
        background: white;
        padding: 0.625rem 0.75rem;
        border-radius: var(--radius-sm);
        text-align: center;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-main);
        border: 1px solid #bfdbfe;
        transition: all 0.2s ease;
        cursor: pointer;
        user-select: none;

        display: flex;
        align-items: center;
        justify-content: center;  /* centraliza ícone + texto */
        gap: 4px;
    }
    .slot-chip:hover {
        transform: translateY(-2px);
        border-color: #6366f1;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    }

    .action-list { list-style: none; padding: 0; margin: 0; }
    .action-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
        text-decoration: none;
        font-size: 0.8125rem;
        cursor: pointer;
    }
    .action-item:last-child {
        border-bottom: none;
    }
    .action-item i {
        font-size: 0.9375rem;
    }
    .action-item.danger { color: var(--danger); }

    /* ============================
       AJUSTE DOS BOTÕES PENDENTES
       ============================ */
    .pendente-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        width: 100%;
    }
    .pendente-actions .btn-confirmar,
    .pendente-actions .btn-whats-pendente {
        width: 100%;
        flex: 1 1 auto;
        justify-content: center;
        box-sizing: border-box;
    }

    /* === RESPONSIVE (MOBILE) === */
    @media (max-width: 768px) {
        .app-header {
            padding: 0.875rem 1rem;
        }
        
        .agenda-title {
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .input-desktop {
            display: none;
        }
        .select-mobile {
            display: block;
        }
        
        select.form-input {
            padding: 0.625rem 2.5rem 0.625rem 0.875rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234f46e5' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.875rem center;
            background-size: 12px;
            appearance: none;
            cursor: pointer;
        }
        
        .finance-card,
        .link-card {
            margin-top: 0.625rem;
            padding: 0.875rem;
        }
        
        .link-input-group {
            flex-direction: column;
        }
        .link-input,
        .btn-copy-link,
        .btn-share-link {
            width: 100%;
        }
        .btn-copy-link,
        .btn-share-link {
            justify-content: center;
        }
        
        .content-area {
            padding: 0.625rem 0.75rem 1rem;
        }
        
        .appt-card {
            padding: 0.75rem 0.875rem;
            margin-bottom: 0.625rem;
        }
        
        .time-col {
            min-width: 2.75rem;
            padding: 0.375rem;
        }
        
        .time-val {
            font-size: 0.875rem;
        }
        
        .time-min {
            font-size: 0.625rem;
        }
        
        .client-name {
            font-size: 0.8125rem;
        }
        
        .service-row {
            font-size: 0.6875rem;
        }
        
        .calendar-grid {
            gap: 0.375rem;
        }
        
        .day-cell {
            font-size: 0.75rem;
        }
        
        .event-count-badge {
            font-size: 0.625rem;
            padding: 0.125rem 0.3125rem;
            min-width: 1rem;
        }
        
        .fab-add {
            bottom: 1.25rem;
            right: 1.25rem;
            width: 3rem;
            height: 3rem;
            font-size: 1.25rem;
        }
        
        .sheet-modal {
            max-height: 92vh;
            padding: 1.25rem 1rem 1.5rem;
        }
        
        .sheet-modal h3 {
            font-size: 0.9375rem;
        }
        
        .form-group {
            margin-bottom: 0.875rem;
        }
        
        .form-label {
            font-size: 0.6875rem;
            margin-bottom: 0.3125rem;
        }
        
        .form-input {
            padding: 0.5625rem 0.6875rem;
            font-size: 0.8125rem;
        }
        
        .btn-main,
        .btn-cancel {
            padding: 0.6875rem 1.25rem;
            font-size: 0.8125rem;
        }
        
        .pendente-actions {
            flex-direction: column;
        }
    }
    
    /* Em telas maiores, botões lado a lado */
    @media (min-width: 768px) {
        .pendente-actions {
            flex-direction: row;
            flex-wrap: nowrap;
        }
        .pendente-actions .btn-confirmar,
        .pendente-actions .btn-whats-pendente {
            width: auto;
        }
    }
</style>

<div class="app-header">
    <h1 class="agenda-title">📅 Minha Agenda</h1>
    
    <div class="view-control">
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataExibida; ?>&view=day" class="view-opt <?php echo $viewType==='day'?'active':''; ?>">Dia</a>
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataExibida; ?>&view=week" class="view-opt <?php echo $viewType==='week'?'active':''; ?>">Semana</a>
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataExibida; ?>&view=month" class="view-opt <?php echo $viewType==='month'?'active':''; ?>">Mês</a>
    </div>

    <div class="date-nav-row">
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataAnt; ?>&view=<?php echo $viewType; ?>" class="btn-circle"><i class="bi bi-chevron-left"></i></a>
        <div class="date-picker-trigger">
            <div class="current-date-label">
                <?php echo $tituloData; ?> <i class="bi bi-caret-down-fill" style="font-size:0.65rem; color:var(--primary);"></i>
            </div>
            <input type="date" class="hidden-date-input" value="<?php echo $dataExibida; ?>" onchange="window.location.href='<?php echo $agendaUrl; ?>?view=<?php echo $viewType; ?>&data='+this.value">
        </div>
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataPro; ?>&view=<?php echo $viewType; ?>" class="btn-circle"><i class="bi bi-chevron-right"></i></a>
    </div>

    <div class="finance-card">
        <span class="fin-label">💰 Faturamento</span>
        <span class="fin-value">R$ <?php echo number_format($faturamento, 2, ',', '.'); ?></span>
    </div>
    
    <div class="link-card">
        <div class="link-card-header">
            <div class="link-card-title">
                <i class="bi bi-link-45deg"></i>
                Link de Agendamento Online
            </div>
        </div>
        <div class="link-input-group">
            <input type="text" class="link-input" id="linkAgendamento" value="<?php echo htmlspecialchars($linkAgendamento); ?>" readonly>
            <button class="btn-copy-link" onclick="copiarLink(event)">
                <i class="bi bi-clipboard-check"></i>
                Copiar
            </button>
            <button class="btn-share-link" onclick="compartilharLink()">
                <i class="bi bi-share-fill"></i>
                Compartilhar
            </button>
        </div>
        <div class="link-hint">
            <i class="bi bi-info-circle-fill"></i>
            Compartilhe este link para clientes agendarem online
        </div>
    </div>
</div>

<div class="content-area">

    <?php if ($viewType === 'month'): ?>
        <div class="calendar-grid">
            <div class="week-day-name">DOM</div><div class="week-day-name">SEG</div><div class="week-day-name">TER</div>
            <div class="week-day-name">QUA</div><div class="week-day-name">QUI</div><div class="week-day-name">SEX</div><div class="week-day-name">SÁB</div>
            
            <?php
            // Lógica de Renderização do Calendário Melhorado
            $firstDayMonth = date('Y-m-01', strtotime($dataExibida));
            $daysInMonth   = date('t', strtotime($dataExibida));
            $startPadding  = date('w', strtotime($firstDayMonth));

            // Espaços vazios antes do dia 1
            for($k=0; $k<$startPadding; $k++) { echo '<div class="day-cell empty"></div>'; }

            // Dias
            for($day=1; $day<=$daysInMonth; $day++) {
                $currentDate = date('Y-m-', strtotime($dataExibida)) . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isToday = ($currentDate === $hoje) ? 'today' : '';
                $hasEvents = isset($diasComAgendamento[$currentDate]) ? 'has-events' : '';
                $eventCount = $agendamentosPorDia[$currentDate] ?? 0;
                
                $badge = '';
                if ($eventCount > 0) {
                    $badge = "<div class='event-count-badge'>{$eventCount}</div>";
                }

                echo "<a href='?view=day&data={$currentDate}' class='day-cell {$isToday} {$hasEvents}'>
                        {$day}
                        {$badge}
                      </a>";
            }
            ?>
        </div>
    
    <?php elseif ($viewType === 'week'): ?>
        <?php foreach ($agendamentos as $dia => $lista): ?>
            <div class="week-header">
                <?php echo date('d/m', strtotime($dia)) . ' • ' . ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', strtotime($dia))]; ?>
            </div>
            <?php if(count($lista) > 0): ?>
                <?php foreach($lista as $ag) renderCard($ag, $clientes); ?>
            <?php else: ?>
                <div style="font-size:0.75rem; color:#cbd5e1; font-style:italic; padding-left:6px;">Livre</div>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php else: ?>
        <?php if (count($agendamentos) > 0): ?>
            <?php foreach($agendamentos as $ag) renderCard($ag, $clientes); ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar4-week" style="font-size:2.4rem; opacity:0.2;"></i>
                <p>Agenda livre neste dia.</p>
                <button onclick="openModal()">+ Adicionar agendamento</button>
            </div>
        <?php endif; ?>
        
        <?php
        // Mostra horários livres do dia
        if ($viewType === 'day'):
            $diaSemana = date('w', strtotime($dataExibida));
            $stmtHorarios = $pdo->prepare("SELECT * FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ? ORDER BY inicio ASC");
            $stmtHorarios->execute([$userId, $diaSemana]);
            $horariosConfig = $stmtHorarios->fetchAll();
            
            if (!empty($horariosConfig)):
                // Pega horários já agendados
                $horariosOcupados = [];
                foreach ($agendamentos as $ag) {
                    $horariosOcupados[] = date('H:i', strtotime($ag['horario']));
                }
                
                // Gera horários livres
                $horariosLivres = [];
                foreach ($horariosConfig as $config) {
                    $inicio = strtotime($config['inicio']);
                    $fim = strtotime($config['fim']);
                    $intervalo = ($config['intervalo_minutos'] ?? 30) * 60;
                    
                    while ($inicio < $fim) {
                        $horario = date('H:i', $inicio);
                        if (!in_array($horario, $horariosOcupados)) {
                            $horariosLivres[] = $horario;
                        }
                        $inicio += $intervalo;
                    }
                }
                
                if (!empty($horariosLivres)):
        ?>
            <div class="free-slots-section">
                <h3 class="free-slots-title">
                    <i class="bi bi-clock"></i>
                    Horários Disponíveis (<?php echo count($horariosLivres); ?>)
                </h3>
                <p style="font-size: 0.75rem; color: var(--text-light); margin: 0 0 12px 0; font-weight: 500;">
                    <i class="bi bi-hand-index"></i> Clique em um horário para agendar rapidamente
                </p>
                <div class="free-slots-grid">
                    <?php foreach($horariosLivres as $hora): ?>
                        <div class="slot-chip" onclick="abrirModalComHorario('<?php echo $dataExibida; ?>', '<?php echo $hora; ?>')" title="Clique para agendar neste horário">
                            <i class="bi bi-clock-fill" style="font-size: 0.75rem; opacity: 0.6; margin-right: 4px;"></i>
                            <?php echo $hora; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php 
                endif;
            endif;
        endif;
        ?>
    <?php endif; ?>

</div>

<button class="fab-add" onclick="openModal()"><i class="bi bi-plus"></i></button>

<div class="modal-overlay" id="modalAdd">
    <div class="sheet-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="margin:0; font-size:1rem; font-weight:700;">Novo agendamento</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:1.4rem; padding:0; line-height:1;"><i class="bi bi-x"></i></button>
        </div>
        
        <form method="POST" onsubmit="sincronizarCamposFormulario()">
            <input type="hidden" name="novo_agendamento" value="1">

            <div class="form-group">
                <label class="form-label">Data</label>
                <input type="date" name="data_agendamento" value="<?php echo ($viewType==='day'?$dataExibida:$hoje); ?>" min="<?php echo $hoje; ?>" class="form-input" required>
                <small id="infoDiaSemana" style="display:block; margin-top:6px; color:#6366f1; font-size:0.75rem; font-weight:600;">
                    <!-- Preenchido por JavaScript -->
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Nome do cliente</label>
                
                <!-- Desktop: Input com datalist -->
                <input type="text" name="cliente" id="inputNomeCliente" class="form-input input-desktop" placeholder="Digite ou selecione o nome do cliente" list="dlNomes" oninput="preencherTelefonePorNome();">
                <datalist id="dlNomes">
                    <?php foreach($clientes as $c) echo "<option value='".htmlspecialchars($c['nome'])."'>"; ?>
                </datalist>
                
                <!-- Mobile: Select nativo -->
                <select name="cliente_mobile" id="selectNomeCliente" class="form-input select-mobile" onchange="selecionarClienteMobile();">
                    <option value="">Selecione um cliente</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['nome']); ?>" data-telefone="<?php echo htmlspecialchars($c['telefone']); ?>">
                            <?php echo htmlspecialchars($c['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__novo__">+ Novo cliente</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Telefone do cliente</label>
                
                <!-- Desktop: Input com datalist -->
                <input type="tel" name="telefone" id="inputTelefone" class="form-input input-desktop" placeholder="(11) 99999-9999" list="dlTels" required oninput="mascaraTelefone(this);">
                <datalist id="dlTels">
                    <?php foreach($clientes as $c) echo "<option value='".htmlspecialchars($c['telefone'])."'>"; ?>
                </datalist>
                
                <!-- Mobile: Input simples (sem datalist para melhor UX no mobile) -->
                <input type="tel" name="telefone_mobile" id="inputTelefoneMobile" class="form-input select-mobile" placeholder="(11) 99999-9999" oninput="sincronizarTelefone(this);" required>
            </div>
            <script>
                // Máscara de telefone
                function mascaraTelefone(i) {
                    let v = i.value.replace(/\D/g, "");
                    v = v.replace(/^([0-9]{2})([0-9])/, "($1) $2");
                    v = v.replace(/([0-9]{5})([0-9]{1,4})$/, "$1-$2");
                    i.value = v.substring(0, 15);
                }
                // Lista de clientes PHP para JS
                var clientes = <?php echo json_encode($clientes); ?>;
                function preencherTelefonePorNome() {
                    var nome = document.getElementById('inputNomeCliente').value.trim().toLowerCase();
                    var telInput = document.getElementById('inputTelefone');
                    var achou = false;
                    clientes.forEach(function(c) {
                        if (c.nome && c.nome.trim().toLowerCase() === nome) {
                            telInput.value = c.telefone;
                            achou = true;
                        }
                    });
                    if (!achou) telInput.value = '';
                }
                
                // Função para quando selecionar cliente no mobile
                function selecionarClienteMobile() {
                    var select = document.getElementById('selectNomeCliente');
                    var inputNome = document.getElementById('inputNomeCliente');
                    var inputTel = document.getElementById('inputTelefone');
                    var inputTelMobile = document.getElementById('inputTelefoneMobile');
                    
                    if (select.value === '__novo__') {
                        // Novo cliente - limpar campos
                        inputNome.value = '';
                        inputTel.value = '';
                        if (inputTelMobile) inputTelMobile.value = '';
                        inputNome.focus();
                    } else if (select.value) {
                        // Cliente existente - preencher campos
                        var option = select.options[select.selectedIndex];
                        var telefone = option.getAttribute('data-telefone') || '';
                        inputNome.value = select.value;
                        inputTel.value = telefone;
                        if (inputTelMobile) inputTelMobile.value = telefone;
                    }
                }
                
                // Sincronizar telefone entre mobile e desktop
                function sincronizarTelefone(input) {
                    mascaraTelefone(input);
                    var inputTel = document.getElementById('inputTelefone');
                    if (inputTel) {
                        inputTel.value = input.value;
                    }
                }
            </script>

            <div class="form-group">
                <label class="form-label">Serviço</label>
                
                <!-- Desktop: Input com datalist -->
                <input type="text" name="servico_nome" id="inputServicoNome" class="form-input input-desktop" list="datalistServicos" placeholder="Digite ou escolha o serviço" oninput="atualizaPrecoPorNome()">
                <input type="hidden" name="servico_id" id="inputServicoId">
                <datalist id="datalistServicos">
                    <!-- Serviços filtrados por JavaScript -->
                </datalist>
                
                <!-- Mobile: Select nativo -->
                <select name="servico_nome_mobile" id="selectServico" class="form-input select-mobile" onchange="selecionarServicoMobile();">
                    <option value="">Selecione um serviço</option>
                    <!-- Opções preenchidas por JavaScript -->
                </select>
                
                <small id="avisoRecorrencia" style="display:none; color:#0369a1; font-size:0.75rem; margin-top:4px;">
                    <i class="bi bi-info-circle-fill"></i> Este serviço criará múltiplos agendamentos automaticamente
                </small>
                <small id="infoDiaSemana" style="display:block; color:#64748b; font-size:0.75rem; margin-top:4px;"></small>
            </div>
            <script>
            // Array completo de serviços
            var todosServicos = <?php echo json_encode($servicos); ?>;
            
            // Filtra serviços baseado no dia da semana da data selecionada
            function filtrarServicosPorData() {
                var dataInput = document.querySelector('input[name="data_agendamento"]');
                if (!dataInput || !dataInput.value) return;
                
                var dataSelecionada = new Date(dataInput.value + 'T00:00:00');
                var diaSemana = dataSelecionada.getDay(); // 0=Dom, 1=Seg, 2=Ter...
                var nomesDias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                
                var datalist = document.getElementById('datalistServicos');
                datalist.innerHTML = ''; // Limpar
                
                var servicosDisponiveis = [];
                var servicosFiltrados = 0;
                
                todosServicos.forEach(function(s) {
                    var disponivel = true;
                    
                    // Se o serviço tem recorrência configurada, verificar dias
                    if (s.permite_recorrencia && s.tipo_recorrencia && s.tipo_recorrencia !== 'sem_recorrencia') {
                        
                        // Para recorrências com dias específicos
                        if ((s.tipo_recorrencia === 'semanal' || s.tipo_recorrencia === 'personalizada') && s.dias_semana) {
                            try {
                                var diasPermitidos = JSON.parse(s.dias_semana);
                                // Verifica se o dia da semana está na lista
                                disponivel = diasPermitidos.includes(diaSemana.toString());
                                if (!disponivel) servicosFiltrados++;
                            } catch(e) {
                                // Se não conseguir parsear, permite o serviço
                                disponivel = true;
                            }
                        }
                        // Outros tipos de recorrência (diária, quinzenal, mensal) estão sempre disponíveis
                    }
                    
                    if (disponivel) {
                        servicosDisponiveis.push(s);
                        var option = document.createElement('option');
                        option.value = s.nome;
                        option.setAttribute('data-preco', s.preco);
                        option.setAttribute('data-id', s.id);
                        option.setAttribute('data-recorrente', s.permite_recorrencia ? '1' : '0');
                        datalist.appendChild(option);
                    }
                });
                
                // Atualizar informação do dia da semana
                var infoDia = document.getElementById('infoDiaSemana');
                if (infoDia) {
                    if (servicosFiltrados > 0) {
                        infoDia.innerHTML = `<i class="bi bi-calendar-check"></i> ${nomesDias[diaSemana]} - ${servicosDisponiveis.length} serviço(s) disponível(is)`;
                        infoDia.style.color = '#0369a1';
                    } else {
                        infoDia.innerHTML = `<i class="bi bi-calendar"></i> ${nomesDias[diaSemana]}`;
                        infoDia.style.color = '#64748b';
                    }
                }
                
                // Atualizar select mobile com serviços filtrados
                var selectServico = document.getElementById('selectServico');
                if (selectServico) {
                    selectServico.innerHTML = '<option value="">Selecione um serviço</option>';
                    servicosDisponiveis.forEach(function(s) {
                        var option = document.createElement('option');
                        option.value = s.nome;
                        option.textContent = s.nome + ' - R$ ' + parseFloat(s.preco).toFixed(2).replace('.', ',');
                        option.setAttribute('data-preco', s.preco);
                        option.setAttribute('data-id', s.id);
                        option.setAttribute('data-recorrente', s.permite_recorrencia ? '1' : '0');
                        selectServico.appendChild(option);
                    });
                }
            }
            
            // Função para quando selecionar serviço no mobile
            function selecionarServicoMobile() {
                var select = document.getElementById('selectServico');
                var inputNome = document.getElementById('inputServicoNome');
                var inputId = document.getElementById('inputServicoId');
                var inputValor = document.getElementById('inputValor');
                
                if (select.value) {
                    var option = select.options[select.selectedIndex];
                    var preco = option.getAttribute('data-preco');
                    var id = option.getAttribute('data-id');
                    var recorrente = option.getAttribute('data-recorrente');
                    
                    // Sincronizar com input desktop
                    inputNome.value = select.value;
                    inputId.value = id || '';
                    if (inputValor && preco) {
                        inputValor.value = parseFloat(preco).toFixed(2);
                    }
                    
                    // Mostrar aviso de recorrência se aplicável
                    var avisoRecorrencia = document.getElementById('avisoRecorrencia');
                    if (avisoRecorrencia) {
                        avisoRecorrencia.style.display = (recorrente === '1') ? 'block' : 'none';
                    }
                }
            }
            
            // Atualiza preço e ID ao digitar serviço
            function atualizaPrecoPorNome() {
                var servicoInput = document.getElementById('inputServicoNome');
                var valorInput = document.getElementById('inputValor');
                var servicoIdInput = document.getElementById('inputServicoId');
                var avisoRecorrencia = document.getElementById('avisoRecorrencia');
                var nome = servicoInput.value.trim().toLowerCase();
                var found = false;
                
                todosServicos.forEach(function(s) {
                    if (s.nome && s.nome.toLowerCase() === nome) {
                        valorInput.value = s.preco;
                        servicoIdInput.value = s.id;
                        
                        // Mostrar aviso se for recorrente
                        if (s.permite_recorrencia && s.tipo_recorrencia && s.tipo_recorrencia !== 'sem_recorrencia') {
                            avisoRecorrencia.style.display = 'block';
                        } else {
                            avisoRecorrencia.style.display = 'none';
                        }
                        
                        found = true;
                    }
                });
                
                if (!found) {
                    valorInput.value = '';
                    servicoIdInput.value = '';
                    avisoRecorrencia.style.display = 'none';
                }
            }
            
            // Escutar mudança na data
            document.addEventListener('DOMContentLoaded', function() {
                var dataInput = document.querySelector('input[name="data_agendamento"]');
                if (dataInput) {
                    dataInput.addEventListener('change', filtrarServicosPorData);
                    // Filtrar na abertura se já houver data
                    if (dataInput.value) {
                        filtrarServicosPorData();
                    }
                }
            });
            </script>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label class="form-label">Horário</label>
                    <input type="time" name="horario" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" name="valor" id="inputValor" step="0.01" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Observação</label>
                <textarea name="obs" rows="2" class="form-input"></textarea>
            </div>

            <button type="submit" class="btn-main">Salvar agendamento</button>
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="actionSheet" style="align-items:flex-end;">
    <div class="sheet-modal" style="border-radius:22px 22px 0 0; padding-bottom:26px;">
        <h3 id="sheetClientName" style="margin-bottom:4px; font-size:0.95rem;">Opções</h3>
        <p id="sheetClientInfo" style="margin:0 0 12px 0; font-size:0.75rem; color:#64748b;"></p>
        <div class="action-list">
            <a href="#" id="actConfirm" class="action-item"><i class="bi bi-check-circle" style="color:#10b981;"></i> Confirmar</a>
            <a href="#" id="actWhatsapp" target="_blank" class="action-item"><i class="bi bi-whatsapp" style="color:#25D366;"></i> Enviar mensagem</a>
            <a href="#" id="actNota" class="action-item"><i class="bi bi-receipt" style="color:#6366f1;"></i> Emitir nota</a>
            <a href="#" id="actCancel" class="action-item"><i class="bi bi-x-circle" style="color:#f59e0b;"></i> Cancelar</a>
            <a href="#" id="actDelete" class="action-item danger"><i class="bi bi-trash"></i> Excluir</a>
        </div>
        <button onclick="document.getElementById('actionSheet').classList.remove('active')" class="btn-main" style="background:#f1f5f9; color:#0f172a; margin-top:14px; box-shadow:none;">Fechar</button>
    </div>
</div>

<!-- Modal para Exclusão de Agendamentos Recorrentes -->
<div class="modal-overlay" id="deleteRecorrenteModal" style="align-items:center;">
    <div class="sheet-modal" style="border-radius:22px; max-width:420px; padding:28px 24px;">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:2rem; color:#dc2626;"></i>
            </div>
            <h3 style="margin:0 0 8px 0; font-size:1.1rem; color:#0f172a;">Excluir Agendamento Recorrente</h3>
            <p id="deleteRecorrenteDesc" style="margin:0; font-size:0.85rem; color:#64748b; line-height:1.5;">
                Este agendamento faz parte de uma série recorrente. Como deseja proceder?
            </p>
        </div>
        
        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
            <label style="display:flex; align-items:center; gap:12px; background:#f8fafc; padding:14px; border-radius:12px; cursor:pointer; border:2px solid transparent; transition:all 0.2s;" onclick="selecionarOpcaoDelete('unico')">
                <input type="radio" name="opcao_delete" value="unico" checked style="width:18px; height:18px;">
                <div style="flex:1;">
                    <div style="font-weight:600; color:#0f172a; font-size:0.9rem; margin-bottom:2px;">
                        <i class="bi bi-calendar-x"></i> Apenas esta ocorrência
                    </div>
                    <div style="font-size:0.75rem; color:#64748b;">Remove somente este agendamento</div>
                </div>
            </label>
            
            <label style="display:flex; align-items:center; gap:12px; background:#f8fafc; padding:14px; border-radius:12px; cursor:pointer; border:2px solid transparent; transition:all 0.2s;" onclick="selecionarOpcaoDelete('proximos')">
                <input type="radio" name="opcao_delete" value="proximos" style="width:18px; height:18px;">
                <div style="flex:1;">
                    <div style="font-weight:600; color:#0f172a; font-size:0.9rem; margin-bottom:2px;">
                        <i class="bi bi-calendar-range"></i> Esta e as próximas
                    </div>
                    <div style="font-size:0.75rem; color:#64748b;">Remove este agendamento e todos os futuros</div>
                </div>
            </label>
            
            <label style="display:flex; align-items:center; gap:12px; background:#f8fafc; padding:14px; border-radius:12px; cursor:pointer; border:2px solid transparent; transition:all 0.2s;" onclick="selecionarOpcaoDelete('serie')">
                <input type="radio" name="opcao_delete" value="serie" style="width:18px; height:18px;">
                <div style="flex:1;">
                    <div style="font-weight:600; color:#0f172a; font-size:0.9rem; margin-bottom:2px;">
                        <i class="bi bi-trash3"></i> Toda a série
                    </div>
                    <div style="font-size:0.75rem; color:#64748b;">Remove todos os agendamentos desta série</div>
                </div>
            </label>
        </div>
        
        <div style="display:flex; gap:10px;">
            <button onclick="fecharDeleteRecorrenteModal()" class="btn-main" style="flex:1; background:#f1f5f9; color:#0f172a; box-shadow:none;">
                <i class="bi bi-x-circle"></i> Cancelar
            </button>
            <button onclick="confirmarDeleteRecorrente()" class="btn-main" style="flex:1; background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                <i class="bi bi-trash3-fill"></i> Excluir
            </button>
        </div>
    </div>
</div>

<?php
// Função para formatar texto de recorrência
function formatarRecorrencia($tipoRecorrencia, $diasSemana = null, $intervaloDias = null) {
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

// Função para renderizar o Card HTML
function renderCard($ag, $clientes) {
    $stClass = 'st-' . ($ag['status'] ?? 'Pendente');
    $tel = '';
    // Encontra telefone do cliente
    foreach($clientes as $c) if($c['nome']===$ag['cliente_nome']) { $tel=$c['telefone']; break; }
    
    // Valor formatado
    $valStr = number_format($ag['valor'], 2, ',', '.');
    
    // Dados para o JavaScript
    $jsonData = htmlspecialchars(json_encode([
        'id'=>$ag['id'], 
        'cliente'=>$ag['cliente_nome'], 
        'status'=>$ag['status'],
        'tel'=>$tel, 
        'serv'=>$ag['servico'], 
        'val'=>$valStr,
        'data'=>date('d/m', strtotime($ag['data_agendamento'])), 
        'hora'=>date('H:i', strtotime($ag['horario'])),
        'e_recorrente'=>!empty($ag['e_recorrente']) && $ag['e_recorrente'] == 1,
        'serie_id'=>$ag['serie_id'] ?? null
    ]));

    // Badge de recorrência
    $badgeRecorrente = '';
    $infoRecorrencia = '';
    
    if (!empty($ag['e_recorrente']) && $ag['e_recorrente'] == 1) {
        $textoRecorrencia = formatarRecorrencia(
            $ag['tipo_recorrencia'] ?? null, 
            $ag['recorrencia_dias_semana'] ?? null,
            $ag['intervalo_dias'] ?? null
        );
        
        $badgeRecorrente = "<span style='display:inline-flex; align-items:center; gap:4px; background:#dbeafe; color:#1e40af; font-size:0.7rem; padding:2px 8px; border-radius:12px; font-weight:600; margin-left:6px;'><i class='bi bi-arrow-repeat'></i> Recorrente</span>";
        
        // Informação detalhada de recorrência abaixo do serviço
        if (!empty($textoRecorrencia)) {
            $infoRecorrencia = "<div style='font-size:0.7rem; color:#0369a1; margin-top:4px; display:flex; align-items:center; gap:4px; font-weight:600;'><i class='bi bi-clock-history'></i> {$textoRecorrencia}</div>";
        }
    }
    
    echo "
    <div class='appt-card' data-status='{$ag['status']}'>
        <div class='status-badge {$stClass}'></div>
        <div class='time-col'>
            <span class='time-val'>".date('H', strtotime($ag['horario']))."</span>
            <span class='time-min'>".date('i', strtotime($ag['horario']))."</span>
        </div>
        <div class='info-col'>
            <div class='client-name'>".htmlspecialchars($ag['cliente_nome']).$badgeRecorrente."</div>
            <div class='service-row'>
                ".htmlspecialchars($ag['servico'])."
                <span class='price-tag'>R$ {$valStr}</span>
            </div>
            {$infoRecorrencia}
        </div>
        <button onclick='openActions($jsonData)'><i class='bi bi-three-dots-vertical'></i></button>
    </div>";
}
?>

<script>
    // --- LÓGICA DE ABERTURA DE MODAIS ---
    function openModal() { 
        document.getElementById('modalAdd').classList.add('active');
        // Filtrar serviços baseado na data atual do campo
        setTimeout(() => {
            var dataInput = document.querySelector('input[name="data_agendamento"]');
            if (dataInput && dataInput.value) {
                filtrarServicosPorData();
            } else {
                // Se não tiver data, mostrar todos os serviços
                var datalist = document.getElementById('datalistServicos');
                datalist.innerHTML = '';
                todosServicos.forEach(function(s) {
                    var option = document.createElement('option');
                    option.value = s.nome;
                    option.setAttribute('data-preco', s.preco);
                    option.setAttribute('data-id', s.id);
                    datalist.appendChild(option);
                });
            }
        }, 50);
    }
    
    function closeModal() { 
        document.getElementById('modalAdd').classList.remove('active'); 
    }
    
    // Sincronizar campos mobile com desktop antes de enviar formulário
    function sincronizarCamposFormulario() {
        // Sincronizar nome do cliente
        var selectCliente = document.getElementById('selectNomeCliente');
        var inputNome = document.getElementById('inputNomeCliente');
        if (selectCliente && selectCliente.value && selectCliente.value !== '__novo__') {
            inputNome.value = selectCliente.value;
        }
        
        // Sincronizar telefone
        var inputTelMobile = document.getElementById('inputTelefoneMobile');
        var inputTel = document.getElementById('inputTelefone');
        if (inputTelMobile && inputTelMobile.value) {
            inputTel.value = inputTelMobile.value;
        }
        
        // Sincronizar serviço
        var selectServico = document.getElementById('selectServico');
        var inputServicoNome = document.getElementById('inputServicoNome');
        if (selectServico && selectServico.value) {
            inputServicoNome.value = selectServico.value;
        }
        
        return true; // Permite envio do formulário
    }
    
    // Abre modal com data e horário preenchidos (para horários livres)
    function abrirModalComHorario(data, horario) {
        // Feedback visual no chip clicado
        const chips = document.querySelectorAll('.slot-chip');
        chips.forEach(chip => {
            if (chip.textContent.trim() === horario) {
                chip.style.transform = 'scale(1.1)';
                chip.style.background = 'var(--primary)';
                chip.style.color = 'white';
                setTimeout(() => {
                    chip.style.transform = '';
                }, 200);
            }
        });
        
        // Preenche os campos com animação de destaque
        const inputData = document.querySelector('input[name="data_agendamento"]');
        const inputHorario = document.querySelector('input[name="horario"]');
        
        if (inputData) {
            inputData.value = data;
            inputData.classList.add('auto-filled');
            setTimeout(() => inputData.classList.remove('auto-filled'), 800);
            // Filtrar serviços após preencher a data
            setTimeout(() => filtrarServicosPorData(), 100);
        }
        if (inputHorario) {
            inputHorario.value = horario;
            inputHorario.classList.add('auto-filled');
            setTimeout(() => inputHorario.classList.remove('auto-filled'), 800);
        }
        
        // Abre o modal após pequeno delay para feedback visual
        setTimeout(() => {
            openModal();
            
            // Foca no campo de nome do cliente
            setTimeout(() => {
                const inputCliente = document.getElementById('inputNomeCliente');
                if (inputCliente) inputCliente.focus();
            }, 300);
        }, 150);
    }
    
    // Atualiza preço automático ao escolher serviço
    function atualizaPreco() {
        const sel = document.getElementById('selServico');
        const opt = sel.options[sel.selectedIndex];
        if(opt && opt.dataset.preco) document.getElementById('inputValor').value = opt.dataset.preco;
    }

    // Variáveis globais para modal de delete recorrente
    let agendamentoAtualId = null;
    let agendamentoAtualRecorrente = false;
    
    // --- LÓGICA DO MENU DE AÇÕES ---
    function openActions(data) {
        document.getElementById('sheetClientName').innerText = data.cliente;
        
        const info = [];
        if (data.data && data.hora) info.push(`${data.data} às ${data.hora}`);
        if (data.serv) info.push(data.serv);
        if (data.val) info.push(`R$ ${data.val}`);
        if (data.tel) info.push(`Tel: ${data.tel}`);
        
        const infoEl = document.getElementById('sheetClientInfo');
        if (infoEl) infoEl.textContent = info.join(' • ');
        
        var isProd = window.location.hostname === 'salao.develoi.com';
        var agendaUrl = isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php';
        const base = `${agendaUrl}?data=<?php echo $dataExibida; ?>&view=<?php echo $viewType; ?>&id=${data.id}`;

        // Armazenar ID e se é recorrente
        agendamentoAtualId = data.id;
        agendamentoAtualRecorrente = data.e_recorrente || false;

        // Ação: Confirmar (muda status e envia bot, sem abrir WhatsApp)
        document.getElementById('actConfirm').onclick = () => {
            window.location.href = base + '&status=Confirmado';
        };

        // Ação: Status/Excluir
        document.getElementById('actCancel').href = base + '&status=Cancelado';
        
        // Ação: Excluir (verificar se é recorrente)
        document.getElementById('actDelete').onclick = () => {
            document.getElementById('actionSheet').classList.remove('active');
            
            if (agendamentoAtualRecorrente) {
                // Abrir modal de opções para recorrente
                abrirDeleteRecorrenteModal();
            } else {
                // Confirmação simples para não recorrente
                if(confirm('Tem certeza que deseja excluir este agendamento?')) {
                    window.location.href = base + '&delete=' + agendamentoAtualId + '&tipo_exclusao=unico';
                }
            }
        };
        
        // Corrige o link para emitir nota: em produção usa /nota?id=XX (com redirecionamento), local usa nota.php
        var notaUrl = isProd ? '/nota' : 'nota.php';
        document.getElementById('actNota').href = `${notaUrl}?id=${data.id}`;
        
        // Ação: WhatsApp avulso
        if(data.tel) {
            document.getElementById('actWhatsapp').href = `https://wa.me/55${data.tel.replace(/\D/g,'')}?text=Olá ${data.cliente.split(' ')[0]}`;
            document.getElementById('actWhatsapp').style.display = 'flex';
        } else {
            document.getElementById('actWhatsapp').style.display = 'none';
        }

        document.getElementById('actionSheet').classList.add('active');
    }

    // --- FUNÇÕES DO LINK DE AGENDAMENTO ---
    function copiarLink(ev) {
        const linkInput = document.getElementById('linkAgendamento');
        if (!linkInput) return;

        linkInput.select();
        linkInput.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(linkInput.value);

        const btn = ev.currentTarget;
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Copiado!';
            btn.style.background = 'rgba(16,185,129,0.95)';
            btn.style.color = 'white';
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = 'rgba(255,255,255,0.95)';
                btn.style.color = '#667eea';
            }, 2000);
        }
    }

    function compartilharLink() {
        const link = document.getElementById('linkAgendamento').value;
        const texto = `Agende seu horário online no <?php echo htmlspecialchars($nomeEstabelecimento); ?>! Acesse: ${link}`;
        
        if (navigator.share) {
            // Usa API nativa de compartilhamento (mobile)
            navigator.share({
                title: 'Link de Agendamento',
                text: texto,
                url: link
            }).catch(err => console.log('Erro ao compartilhar:', err));
        } else {
            // Fallback: abre WhatsApp
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(texto)}`;
            window.open(whatsappUrl, '_blank');
        }
    }

    // --- FUNÇÕES PARA MODAL DE DELETE RECORRENTE ---
    function abrirDeleteRecorrenteModal() {
        document.getElementById('deleteRecorrenteModal').classList.add('active');
        // Reset para primeira opção
        document.querySelector('input[name="opcao_delete"][value="unico"]').checked = true;
    }

    function fecharDeleteRecorrenteModal() {
        document.getElementById('deleteRecorrenteModal').classList.remove('active');
    }

    function selecionarOpcaoDelete(opcao) {
        document.querySelector(`input[name="opcao_delete"][value="${opcao}"]`).checked = true;
        
        // Destacar visualmente a opção selecionada
        const labels = document.querySelectorAll('#deleteRecorrenteModal label');
        labels.forEach(label => {
            label.style.borderColor = 'transparent';
            label.style.background = '#f8fafc';
        });
        
        const labelSelecionado = document.querySelector(`input[name="opcao_delete"][value="${opcao}"]`).parentElement;
        labelSelecionado.style.borderColor = '#6366f1';
        labelSelecionado.style.background = '#eef2ff';
    }

    function confirmarDeleteRecorrente() {
        const opcaoSelecionada = document.querySelector('input[name="opcao_delete"]:checked').value;
        
        var isProd = window.location.hostname === 'salao.develoi.com';
        var agendaUrl = isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php';
        
        const url = `${agendaUrl}?data=<?php echo $dataExibida; ?>&view=<?php echo $viewType; ?>&delete=${agendamentoAtualId}&tipo_exclusao=${opcaoSelecionada}`;
        
        window.location.href = url;
    }

    // Fechar modal ao clicar fora
    document.getElementById('deleteRecorrenteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharDeleteRecorrenteModal();
        }
    });
</script>
