<?php
require_once __DIR__ . '/../includes/config.php';
// dashboard.php (painel do profissional)

// =========================================================
// 1. SESSÃO, AMBIENTE E LOGIN
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Flag de ambiente: produção x localhost
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

// Garante login
if (!isset($_SESSION['user_id'])) {
    // Em prod -> /login.php | Em localhost -> ../login.php
    $loginUrl = $isProd ? '/login.php' : '../login.php';
    header('Location: ' . $loginUrl);
    exit;
}

$userId = $_SESSION['user_id'];

// =========================================================
// 2. DEFINIÇÕES DA PÁGINA E INCLUDES
// =========================================================
$pageTitle = 'Dashboard - Salão Develoi';

include '../includes/header.php';
include '../includes/menu.php';
include '../includes/db.php';

// =========================================================
// 3. LÓGICA: CONSULTAS NO BANCO
// =========================================================

// Datas - Filtros personalizados
$hoje        = date('Y-m-d');
$mesAtual    = date('m');
$anoAtual    = date('Y');
$nomeUsuario = 'Profissional';

// Filtros de período (GET)
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$dataFim    = $_GET['data_fim'] ?? date('Y-m-t');     // Último dia do mês atual
$mesFiltro  = $_GET['mes_aniversario'] ?? $mesAtual;

// --- BUSCA NOME DO USUÁRIO E ESTABELECIMENTO ---
$stmt = $pdo->prepare("SELECT nome, estabelecimento, is_vitalicio, data_expiracao FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if ($user && !empty($user['nome'])) {
    $nomeUsuario = explode(' ', $user['nome'])[0]; // Primeiro nome
}
$nomeEstabelecimento = $user['estabelecimento'] ?? 'Nosso Salão';

// --- CÁLCULO DA LICENÇA ---
$isVitalicio = $user['is_vitalicio'] ?? 0;
$dataExpiracao = $user['data_expiracao'] ?? null;
$diasRestantes = null;
$statusLicenca = 'ativo'; // ativo, alerta, critico, expirado
$corLicenca = '#10b981'; // verde padrão
$mostrarNotificacao = false;
$mensagemNotificacao = '';
$tipoLicenca = 'Período de Teste';

if ($isVitalicio) {
    $tipoLicenca = 'Vitalício';
    $statusLicenca = 'vitalicio';
    $corLicenca = '#8b5cf6'; // roxo para vitalício
} elseif (!empty($dataExpiracao)) {
    $dataExp = new DateTime($dataExpiracao);
    $dataHoje = new DateTime();
    $diff = $dataHoje->diff($dataExp);
    
    if ($dataHoje > $dataExp) {
        // Expirado
        $statusLicenca = 'expirado';
        $corLicenca = '#ef4444';
        $diasRestantes = 0;
        $mostrarNotificacao = true;
        $mensagemNotificacao = 'Sua licença expirou! Entre em contato para renovar.';
    } else {
        $diasRestantes = $diff->days;
        
        if ($diasRestantes <= 1) {
            $statusLicenca = 'critico';
            $corLicenca = '#ef4444'; // vermelho
            $mostrarNotificacao = true;
            $mensagemNotificacao = $diasRestantes == 0 
                ? 'Sua licença expira HOJE! Renove agora para não perder o acesso.' 
                : 'Sua licença expira AMANHÃ! Renove o quanto antes.';
        } elseif ($diasRestantes <= 2) {
            $statusLicenca = 'critico';
            $corLicenca = '#ef4444';
            $mostrarNotificacao = true;
            $mensagemNotificacao = "Faltam apenas {$diasRestantes} dias para sua licença expirar!";
        } elseif ($diasRestantes <= 5) {
            $statusLicenca = 'critico';
            $corLicenca = '#ef4444';
        } elseif ($diasRestantes <= 15) {
            $statusLicenca = 'alerta';
            $corLicenca = '#f59e0b'; // laranja
        }
    }
}

// --- CRIAR NOTIFICAÇÃO DE LICENÇA NO BANCO ---
if (!$isVitalicio && $diasRestantes !== null && $diasRestantes <= 15) {
    // Verifica se já existe notificação para hoje sobre a licença
    $checkNotif = $pdo->prepare("
        SELECT id FROM notificacoes 
        WHERE usuario_id = ? 
        AND tipo = 'licenca_expiracao'
        AND DATE(criado_em) = ?
        LIMIT 1
    ");
    $checkNotif->execute([$userId, $hoje]);
    
    // Se não existir, cria uma nova
    if (!$checkNotif->fetch()) {
        $mensagemNotif = '';
        $iconeNotif = 'bi-exclamation-triangle-fill';
        
        if ($diasRestantes == 0) {
            $mensagemNotif = 'Sua licença expira HOJE! Renove agora para não perder o acesso.';
        } elseif ($diasRestantes == 1) {
            $mensagemNotif = 'Sua licença expira AMANHÃ! Renove o quanto antes.';
        } elseif ($diasRestantes <= 2) {
            $mensagemNotif = "Faltam apenas {$diasRestantes} dias para sua licença expirar!";
        } elseif ($diasRestantes <= 5) {
            $mensagemNotif = "Sua licença expira em {$diasRestantes} dias. Renove para garantir acesso contínuo.";
        } elseif ($diasRestantes <= 15) {
            $mensagemNotif = "Sua licença expira em {$diasRestantes} dias. Planeje sua renovação.";
        }
        
        try {
            $insertNotif = $pdo->prepare("
                INSERT INTO notificacoes (usuario_id, tipo, mensagem, icone, link, criado_em)
                VALUES (?, 'licenca_expiracao', ?, ?, 'https://wa.me/5511999999999', NOW())
            ");
            $insertNotif->execute([$userId, $mensagemNotif, $iconeNotif]);
        } catch (Exception $e) {
            // Tabela de notificações pode não existir ainda, silencioso
        }
    }
}

// --- 1. Agendamentos de Hoje (Próximos Clientes) ---
$stmt = $pdo->prepare("
    SELECT cliente_nome, servico, horario, status 
      FROM agendamentos 
     WHERE user_id = ? 
       AND data_agendamento = ? 
     ORDER BY horario ASC 
     LIMIT 5
");
$stmt->execute([$userId, $hoje]);
$agendamentosHoje = $stmt->fetchAll();

// --- 2. Faturamento de Hoje ---
$stmtFat = $pdo->prepare("
    SELECT SUM(valor) 
      FROM agendamentos
     WHERE user_id = :userId
       AND data_agendamento = :hoje
       AND status != 'Cancelado'
");
$stmtFat->execute([
    ':userId' => $userId,
    ':hoje'   => $hoje
]);
$faturamentoHoje = $stmtFat->fetchColumn() ?: 0;

// --- 2B. Faturamento do Período Filtrado ---
$stmtFatPeriodo = $pdo->prepare("
    SELECT SUM(valor), COUNT(id)
      FROM agendamentos
     WHERE user_id = :userId
       AND data_agendamento BETWEEN :dataInicio AND :dataFim
       AND status != 'Cancelado'
");
$stmtFatPeriodo->execute([
    ':userId' => $userId,
    ':dataInicio' => $dataInicio,
    ':dataFim' => $dataFim
]);
$resultPeriodo = $stmtFatPeriodo->fetch();
$faturamentoPeriodo = $resultPeriodo['SUM(valor)'] ?: 0;
$totalAgendamentosPeriodo = $resultPeriodo['COUNT(id)'] ?: 0;

// --- 3. Total de Clientes ---
$totalClientes = $pdo->query("
    SELECT COUNT(id) 
      FROM clientes 
     WHERE user_id = {$userId}
")->fetchColumn() ?: 0;

// --- 4. Total de Produtos ---
$totalProdutos = $pdo->query("
    SELECT COUNT(id) 
      FROM produtos 
     WHERE user_id = {$userId}
")->fetchColumn() ?: 0;

// --- 5. Clientes que mais agendam (Ranking) ---
$stmtTopClientes = $pdo->prepare("
    SELECT 
        COALESCE(c.nome, a.cliente_nome) AS nome_cliente,
        COUNT(a.id) AS total_agendamentos,
        SUM(CASE WHEN a.status != 'Cancelado' THEN 1 ELSE 0 END) AS total_realizados,
        MAX(a.data_agendamento || ' ' || a.horario) AS ultimo_atendimento
    FROM agendamentos a
    LEFT JOIN clientes c 
           ON c.id = a.cliente_id
          AND c.user_id = a.user_id
    WHERE a.user_id = :userId
    GROUP BY nome_cliente
    HAVING nome_cliente IS NOT NULL
    ORDER BY total_agendamentos DESC
    LIMIT 5
");
$stmtTopClientes->execute([':userId' => $userId]);
$topClientes = $stmtTopClientes->fetchAll();

// --- 6. Aniversariantes do mês ---
$mesNomes = [
    '01' => 'Janeiro',
    '02' => 'Fevereiro',
    '03' => 'Março',
    '04' => 'Abril',
    '05' => 'Maio',
    '06' => 'Junho',
    '07' => 'Julho',
    '08' => 'Agosto',
    '09' => 'Setembro',
    '10' => 'Outubro',
    '11' => 'Novembro',
    '12' => 'Dezembro',
];
$nomeMesAtual = $mesNomes[$mesAtual] ?? $mesAtual;

$stmtAniv = $pdo->prepare("
    SELECT nome, data_nascimento, telefone
      FROM clientes
     WHERE user_id = :userId
       AND data_nascimento IS NOT NULL
       AND strftime('%m', data_nascimento) = :mes
  ORDER BY strftime('%d', data_nascimento)
");
$stmtAniv->execute([
    ':userId' => $userId,
    ':mes'    => str_pad($mesFiltro, 2, '0', STR_PAD_LEFT)
]);
$aniversariantes = $stmtAniv->fetchAll();

// --- 7. Agendamentos Próximos (30 minutos antes) ---
$horaAtual = date('H:i:s');
$horaLimite = date('H:i:s', strtotime('+30 minutes'));

$stmtProximos = $pdo->prepare("
    SELECT id, cliente_nome, servico, horario, valor, status
      FROM agendamentos
     WHERE user_id = :userId
       AND data_agendamento = :hoje
       AND horario BETWEEN :horaAtual AND :horaLimite
       AND status != 'Cancelado'
  ORDER BY horario ASC
");
$stmtProximos->execute([
    ':userId' => $userId,
    ':hoje' => $hoje,
    ':horaAtual' => $horaAtual,
    ':horaLimite' => $horaLimite
]);
$agendamentosProximos = $stmtProximos->fetchAll();

// --- 8. Confirmações Pendentes ---
$stmtPendentes = $pdo->prepare("
    SELECT a.id, a.cliente_nome, a.servico, a.data_agendamento, a.horario, a.valor, c.telefone
      FROM agendamentos a
      LEFT JOIN clientes c ON a.cliente_nome = c.nome AND a.user_id = c.user_id
     WHERE a.user_id = :userId
       AND a.status = 'Pendente'
       AND a.data_agendamento >= :hoje
  ORDER BY a.data_agendamento ASC, a.horario ASC
  LIMIT 10
");
$stmtPendentes->execute([
    ':userId' => $userId,
    ':hoje' => $hoje
]);
$agendamentosPendentes = $stmtPendentes->fetchAll();

?>
<style>
    /* ============================
       DASHBOARD - VISUAL MODERNO APP
       ============================ */

    :root {
        --dash-radius-lg: 20px;
        --dash-radius-md: 16px;
        --dash-radius-sm: 12px;
        --dash-primary: #6366f1;
        --dash-primary-dark: #4f46e5;
        --dash-secondary: #8b5cf6;
        --dash-success: #10b981;
        --dash-warning: #f59e0b;
        --dash-danger: #ef4444;
        --dash-bg: #f8fafc;
        --dash-text: #0f172a;
        --dash-text-light: #64748b;
        --dash-border: #e2e8f0;
    }

    * {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    body {
        background: var(--dash-bg);
    }

    /* Scrollbar customizada */
    *::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    *::-webkit-scrollbar-track {
        background: transparent;
    }

    *::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    *::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .app-dashboard-wrapper {
        padding: 16px;
        max-width: 1400px;
        margin: 0 auto;
        background: var(--dash-bg);
        animation: fadeInUp 0.5s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (min-width: 768px) {
        .app-dashboard-wrapper {
            padding: 24px 32px;
        }
    }

    .welcome-section {
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .welcome-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .avatar-circle {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 1.2rem;
        box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.5);
        border: 3px solid #fff;
        letter-spacing: 0.03em;
    }

    .welcome-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dash-text);
        margin: 0;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }

    .welcome-subtitle {
        color: var(--dash-text-light);
        font-size: 0.875rem;
        margin-top: 4px;
        font-weight: 500;
        letter-spacing: -0.01em;
    }

    .welcome-right {
        font-size: 0.8125rem;
        color: var(--dash-text-light);
        padding: 8px 14px;
        border-radius: 999px;
        background: #fff;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--dash-border);
        position: relative;
    }

    .welcome-right::before {
        content: "";
        position: absolute;
        left: 8px;
        width: 6px;
        height: 6px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.5;
            transform: scale(1.2);
        }
    }

    .welcome-right i {
        font-size: 1rem;
        color: var(--dash-primary);
        margin-left: 6px;
    }

    @media (max-width: 640px) {
        .avatar-circle {
            width: 44px;
            height: 44px;
            font-size: 1rem;
        }
        .welcome-title {
            font-size: 1.25rem;
        }
        .welcome-subtitle {
            font-size: 0.8125rem;
        }
    }

    /* Cards de resumo - Visual modernizado */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    @media (max-width: 768px) {
        .stats-summary {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
    }

    @media (max-width: 480px) {
        .stats-summary {
            grid-template-columns: 1fr;
        }
    }

    .stat-box {
        position: relative;
        background: #fff;
        padding: 20px;
        border-radius: var(--dash-radius-lg);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--dash-border);
        overflow: hidden;
        min-width: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stat-box:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 32px -12px rgba(0, 0, 0, 0.2);
    }

    .stat-box::before {
        content: "";
        position: absolute;
        right: -20px;
        top: -20px;
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.08), transparent 70%);
        z-index: 0;
        transition: all 0.3s ease;
    }

    .stat-box:hover::before {
        transform: scale(1.2);
        opacity: 0.6;
    }

    .stat-label {
        color: var(--dash-text-light);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        margin-bottom: 8px;
        text-transform: uppercase;
        position: relative;
        z-index: 1;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        color: var(--dash-text);
        letter-spacing: -0.02em;
        display: flex;
        align-items: baseline;
        gap: 6px;
        position: relative;
        z-index: 1;
    }

    .stat-chip {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #f1f5f9;
        color: var(--dash-text-light);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        position: relative;
        z-index: 1;
        transition: all 0.2s ease;
    }

    .stat-chip i {
        font-size: 0.875rem;
    }

    .stat-box:hover .stat-chip {
        transform: scale(1.05);
    }

    /* Módulos principais - Cards modernos */
    .modules-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin: 0 0 16px 0;
    }

    .modules-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0;
        color: var(--dash-text);
        letter-spacing: -0.01em;
    }

    .modules-subtitle {
        font-size: 0.8125rem;
        color: var(--dash-text-light);
        margin-top: 4px;
        font-weight: 500;
    }

    .modules-scroll {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 8px;
        margin-bottom: 24px;
        scroll-snap-type: x mandatory;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .modules-scroll::-webkit-scrollbar {
        height: 6px;
    }
    .modules-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    .modules-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }
    .modules-scroll::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .nav-card {
        min-width: 180px;
        max-width: 240px;
        flex: 0 0 auto;
        background: #fff;
        padding: 20px;
        border-radius: var(--dash-radius-lg);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--dash-border);
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        gap: 8px;
        scroll-snap-align: start;
        position: relative;
        overflow: hidden;
    }

    @media (min-width: 768px) {
        .modules-scroll {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            overflow: visible;
        }
        .nav-card {
            flex: 1 1 auto;
        }
    }

    .nav-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent 0%, rgba(99, 102, 241, 0.03) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .nav-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.15);
        border-color: var(--dash-primary);
    }

    .nav-card:hover::before {
        opacity: 1;
    }

    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 8px;
        transition: transform 0.3s ease;
    }

    .nav-card:hover .icon-circle {
        transform: scale(1.1);
    }

    .bg-indigo  { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #4338ca; }
    .bg-orange  { background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%); color: #c2410c; }
    .bg-blue    { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; }
    .bg-emerald { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #15803d; }

    .nav-title {
        font-weight: 700;
        color: var(--dash-text);
        font-size: 0.9375rem;
        letter-spacing: -0.01em;
        line-height: 1.3;
    }
    .nav-desc {
        color: var(--dash-text-light);
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1.4;
    }

    /* Badge "novo" opcional para módulos */
    .nav-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        font-size: 0.625rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    /* Painéis inferiores - Layout responsivo */
    .dashboard-panels {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        margin-top: 0;
    }

    @media (min-width: 960px) {
        .dashboard-panels {
            grid-template-columns: 1.6fr 1.2fr;
        }
    }

    .panel-column {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .recent-section {
        background: #fff;
        border-radius: var(--dash-radius-lg);
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--dash-border);
        transition: all 0.3s ease;
    }

    .recent-section:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
        gap: 12px;
        flex-wrap: wrap;
    }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.01em;
        color: var(--dash-text);
    }

    .section-sub {
        font-size: 0.8125rem;
        color: var(--dash-text-light);
        margin-top: 4px;
        font-weight: 500;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        border-radius: var(--dash-radius-md);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .custom-table th {
        text-align: left;
        padding: 10px 12px;
        color: var(--dash-text-light);
        font-weight: 700;
        border-bottom: 2px solid var(--dash-border);
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        background: #f8fafc;
    }
    .custom-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        color: var(--dash-text);
        vertical-align: middle;
        font-size: 0.8125rem;
        font-weight: 500;
    }
    .custom-table tr:last-child td {
        border-bottom: none;
    }
    .custom-table tbody tr:hover {
        background: #f8fafc;
    }

    .custom-table.small th {
        padding: 8px 10px;
        font-size: 0.6875rem;
    }
    .custom-table.small td {
        padding: 10px;
        font-size: 0.8125rem;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
        letter-spacing: 0.01em;
    }
    .status-Confirmado { background: #d1fae5; color: #065f46; }
    .status-Pendente   { background: #fef3c7; color: #92400e; }
    .status-Cancelado  { background: #fee2e2; color: #991b1b; }

    .btn-action {
        padding: 6px 12px;
        background: #f1f5f9;
        color: var(--dash-text);
        border-radius: 999px;
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        border: 1px solid transparent;
    }
    .btn-action i {
        font-size: 0.875rem;
    }
    .btn-action:hover {
        background: var(--dash-primary);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
    }

    .top-client-name {
        font-weight: 700;
        margin-bottom: 2px;
        font-size: 0.875rem;
        color: var(--dash-text);
    }
    .top-client-sub {
        font-size: 0.75rem;
        color: var(--dash-text-light);
        font-weight: 500;
    }

    .badge-pill {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        background: #f1f5f9;
        color: var(--dash-text);
        font-weight: 600;
    }

    .birthday-day {
        font-weight: 700;
        font-size: 0.875rem;
        color: var(--dash-text);
    }
    .birthday-age {
        font-size: 0.75rem;
        color: var(--dash-text-light);
        font-weight: 500;
    }

    .empty-row {
        text-align: center;
        color: var(--dash-text-light);
        padding: 32px 16px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* MOBILE: tabelas viram cards (estilo app) */
    @media (max-width: 640px) {
        .custom-table thead {
            display: none;
        }

        .custom-table,
        .custom-table.small {
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table tr,
        .custom-table.small tr {
            display: block;
            margin: 0 0 12px;
            border-radius: var(--dash-radius-md);
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid var(--dash-border);
        }

        .custom-table td,
        .custom-table.small td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
            padding: 10px 14px;
            font-size: 0.8125rem;
            background: transparent;
        }

        .custom-table td:first-child,
        .custom-table.small td:first-child {
            padding-top: 14px;
        }

        .custom-table td:last-child,
        .custom-table.small td:last-child {
            padding-bottom: 14px;
            border-bottom: none;
        }

        .custom-table td[data-label]::before,
        .custom-table.small td[data-label]::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--dash-text-light);
        }
    }

    /* ============================
       FILTROS - Visual Moderno
       ============================ */
    .filters-card {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-radius: var(--dash-radius-lg);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px -10px rgba(99, 102, 241, 0.5);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .filters-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        opacity: 0.4;
    }

    .filters-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        position: relative;
        z-index: 1;
    }

    .filters-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .filters-icon {
        font-size: 1.4rem;
    }

    .filters-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        align-items: end;
        position: relative;
        z-index: 1;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.95;
    }

    .filter-input {
        padding: 12px 14px;
        border-radius: var(--dash-radius-sm);
        border: 2px solid rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .filter-input:focus {
        outline: none;
        border-color: rgba(255,255,255,0.6);
        background: rgba(255,255,255,0.15);
        box-shadow: 0 0 0 4px rgba(255,255,255,0.1);
    }

    .filter-input::placeholder {
        color: rgba(255,255,255,0.6);
    }

    .filter-input option {
        background: var(--dash-primary-dark);
        color: white;
    }

    .btn-filter {
        padding: 12px 24px;
        border-radius: var(--dash-radius-sm);
        border: none;
        background: white;
        color: var(--dash-primary);
        font-weight: 700;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        letter-spacing: -0.01em;
    }

    .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.25);
    }

    .btn-filter:active {
        transform: scale(0.98);
    }

    .btn-filter i {
        font-size: 1.125rem;
    }

    @media (max-width: 640px) {
        .filters-card {
            padding: 20px;
        }
        .filters-form {
            grid-template-columns: 1fr;
        }
        .btn-filter {
            width: 100%;
        }
    }

    /* Modal de Mensagem de Aniversário - Modernizado */
    .message-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.75);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(12px);
        animation: fadeIn 0.25s ease-out;
    }

    .message-modal.active {
        display: flex;
    }

    .message-box {
        background: #ffffff;
        padding: 32px;
        border-radius: var(--dash-radius-lg);
        width: 92%;
        max-width: 540px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px -15px rgba(15,23,42,0.5);
        animation: modalSlideUp 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(24px) scale(0.96);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
    }

    .message-title {
        font-size: 1.375rem;
        font-weight: 700;
        margin: 0;
        color: var(--dash-text);
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.02em;
    }

    .message-title i {
        font-size: 1.625rem;
        color: #f59e0b;
    }

    .message-close {
        background: #f1f5f9;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        color: var(--dash-text-light);
        font-size: 1.375rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .message-close:hover {
        background: #e2e8f0;
        transform: rotate(90deg);
        color: var(--dash-text);
    }

    .message-preview {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border-radius: var(--dash-radius-md);
        padding: 20px;
        margin-bottom: 20px;
        border: 2px solid #86efac;
    }

    .message-preview-label {
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #166534;
        margin-bottom: 12px;
    }

    .message-content {
        font-size: 0.9375rem;
        line-height: 1.7;
        color: var(--dash-text);
        white-space: pre-wrap;
        font-weight: 500;
    }

    .message-info {
        background: #eff6ff;
        border-radius: var(--dash-radius-sm);
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: start;
        gap: 12px;
        border: 1px solid #bfdbfe;
    }

    .message-info i {
        font-size: 1.25rem;
        color: #2563eb;
        margin-top: 2px;
    }

    .message-info-text {
        font-size: 0.8125rem;
        color: #1e40af;
        line-height: 1.6;
        font-weight: 500;
    }

    .message-actions {
        display: flex;
        gap: 12px;
    }

    .btn-copy-message {
        flex: 1;
        padding: 14px 24px;
        border-radius: var(--dash-radius-sm);
        border: none;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        letter-spacing: -0.01em;
    }

    .btn-copy-message:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16,185,129,0.4);
    }

    .btn-copy-message:active {
        transform: scale(0.97);
    }

    .btn-copy-message i {
        font-size: 1.125rem;
    }

    .btn-whatsapp {
        flex: 1;
        padding: 14px 24px;
        border-radius: var(--dash-radius-sm);
        border: none;
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(37,211,102,0.3);
        text-decoration: none;
        letter-spacing: -0.01em;
    }

    .btn-whatsapp:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(37,211,102,0.4);
        color: white;
    }

    .btn-whatsapp:active {
        transform: scale(0.97);
    }

    .btn-whatsapp i {
        font-size: 1.25rem;
    }

    .btn-send-birthday {
        padding: 6px 14px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border-radius: 999px;
        border: none;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(245,158,11,0.3);
        letter-spacing: -0.01em;
    }

    /* ============================
       MODAL DE AGENDAMENTOS PRÓXIMOS
       ============================ */
    .proximos-modal {
        display: none;
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .proximos-modal.active {
        display: block;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .proximos-box {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 20px 24px;
        border-radius: var(--dash-radius-lg);
        box-shadow: 0 20px 40px rgba(239,68,68,0.4);
        min-width: 340px;
        max-width: 400px;
        position: relative;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { box-shadow: 0 20px 40px rgba(239,68,68,0.4); }
        50% { box-shadow: 0 25px 50px rgba(239,68,68,0.6); }
    }

    .proximos-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid rgba(255,255,255,0.2);
    }

    .proximos-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .proximos-title i {
        font-size: 1.5rem;
        animation: ring 1.5s ease-in-out infinite;
    }

    @keyframes ring {
        0%, 100% { transform: rotate(0deg); }
        10%, 30% { transform: rotate(-15deg); }
        20% { transform: rotate(15deg); }
    }

    .proximos-close {
        background: rgba(255,255,255,0.2);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        color: white;
        font-size: 1.125rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .proximos-close:hover {
        background: rgba(255,255,255,0.3);
        transform: scale(1.1);
    }

    .proximos-item {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        padding: 12px;
        border-radius: var(--dash-radius-sm);
        margin-bottom: 10px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .proximos-item:last-child {
        margin-bottom: 0;
    }

    .proximos-cliente {
        font-weight: 700;
        font-size: 0.9375rem;
        margin-bottom: 4px;
    }

    .proximos-info {
        font-size: 0.8125rem;
        opacity: 0.95;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .proximos-info i {
        font-size: 0.875rem;
    }

    @media (max-width: 640px) {
        .proximos-modal {
            bottom: 12px;
            right: 12px;
            left: 12px;
        }
        .proximos-box {
            min-width: auto;
            max-width: none;
        }
    }

    /* ============================
       CARD DE CONFIRMAÇÕES PENDENTES
       ============================ */
    .pendentes-section {
        background: #fff;
        border-radius: var(--dash-radius-lg);
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid var(--dash-border);
        transition: all 0.3s ease;
        position: relative;
    }

    .pendentes-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        border-radius: var(--dash-radius-lg) var(--dash-radius-lg) 0 0;
    }

    .pendentes-section:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .pendentes-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border-radius: 50%;
        font-size: 0.75rem;
        font-weight: 700;
        margin-left: 8px;
        box-shadow: 0 4px 12px rgba(245,158,11,0.3);
    }

    .pendente-item {
        background: #fef3c7;
        padding: 16px;
        border-radius: var(--dash-radius-md);
        margin-bottom: 12px;
        border: 2px solid #fde68a;
        transition: all 0.2s ease;
    }

    .pendente-item:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(245,158,11,0.2);
    }

    .pendente-item:last-child {
        margin-bottom: 0;
    }

    .pendente-cliente {
        font-weight: 700;
        font-size: 0.9375rem;
        color: #92400e;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pendente-cliente i {
        font-size: 1.125rem;
    }

    .pendente-detalhes {
        font-size: 0.8125rem;
        color: #78350f;
        margin-bottom: 12px;
        line-height: 1.6;
    }

    .pendente-detalhes strong {
        font-weight: 600;
    }

    .pendente-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-confirmar {
        flex: 1;
        min-width: 120px;
        padding: 10px 16px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 999px;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        text-decoration: none;
    }

    .btn-confirmar:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(16,185,129,0.4);
    }

    .btn-confirmar i {
        font-size: 1rem;
    }

    .btn-whats-pendente {
        padding: 10px 16px;
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        border: none;
        border-radius: 999px;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(37,211,102,0.3);
        text-decoration: none;
    }

    .btn-whats-pendente:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(37,211,102,0.4);
    }

    .btn-whats-pendente i {
        font-size: 1.125rem;
    }

    .btn-send-birthday:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(245,158,11,0.4);
    }

    .btn-send-birthday:active {
        transform: scale(0.96);
    }

    .btn-send-birthday i {
        font-size: 0.875rem;
    }

    /* Cards de estatística com cores personalizadas */
    .stat-box.stat-success {
        border-left: 4px solid var(--dash-success);
    }
    .stat-box.stat-success .stat-value {
        color: var(--dash-success);
    }

    .stat-box.stat-primary {
        border-left: 4px solid var(--dash-primary);
    }
    .stat-box.stat-primary .stat-value {
        color: var(--dash-primary);
    }

    .stat-box.stat-secondary {
        border-left: 4px solid var(--dash-secondary);
    }
    .stat-box.stat-secondary .stat-value {
        color: var(--dash-secondary);
    }

    .stat-box.stat-warning {
        border-left: 4px solid var(--dash-warning);
    }
    .stat-box.stat-warning .stat-value {
        color: var(--dash-warning);
    }

    @media (max-width: 768px) {
        .dashboard-panels {
            gap: 12px;
        }
        .recent-section {
            padding: 16px;
        }
        .section-title {
            font-size: 0.9375rem;
        }
    }

    @media (max-width: 640px) {
        .message-box {
            width: 100%;
            max-width: 100%;
            border-radius: 24px 24px 0 0;
            margin: 0;
            padding: 20px;
        }
        .message-actions {
            flex-direction: column;
        }
    }

    /* Animação Pulse para botão crítico */
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 4px 16px #ef444460;
        }
        50% {
            transform: scale(1.05);
            box-shadow: 0 6px 24px #ef444480;
        }
    }

    /* Modal de Notificação de Licença */
    .license-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 20px;
        animation: fadeIn 0.3s ease-out;
    }

    .license-modal-content {
        background: #fff;
        border-radius: 24px;
        max-width: 480px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.4s ease-out;
        overflow: hidden;
    }

    .license-modal-header {
        padding: 32px 32px 24px;
        text-align: center;
        border-bottom: 1px solid #f1f5f9;
    }

    .license-modal-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
        animation: iconPulse 2s infinite;
    }

    @keyframes iconPulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    .license-modal-icon i {
        font-size: 40px;
        color: #fff;
    }

    .license-modal-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 8px;
        letter-spacing: -0.02em;
    }

    .license-modal-subtitle {
        font-size: 1rem;
        color: #64748b;
        margin: 0;
        font-weight: 500;
    }

    .license-modal-body {
        padding: 24px 32px 32px;
    }

    .license-modal-message {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 24px;
    }

    .license-modal-message p {
        margin: 0;
        color: #991b1b;
        font-size: 0.9375rem;
        font-weight: 600;
        line-height: 1.6;
    }

    .license-modal-actions {
        display: flex;
        gap: 12px;
    }

    .license-modal-btn {
        flex: 1;
        padding: 14px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9375rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .license-modal-btn-primary {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    .license-modal-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(239, 68, 68, 0.5);
    }

    .license-modal-btn-secondary {
        background: #f1f5f9;
        color: #475569;
    }

    .license-modal-btn-secondary:hover {
        background: #e2e8f0;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<main class="app-dashboard-wrapper">

    <div class="welcome-section">
        <div class="welcome-left">
            <div class="avatar-circle">
                <?php echo strtoupper(mb_substr($nomeUsuario, 0, 1)); ?>
            </div>
            <div>
                <h1 class="welcome-title">Olá, <?php echo htmlspecialchars($nomeUsuario); ?> 👋</h1>
                <p class="welcome-subtitle">Aqui está o resumo do teu salão hoje.</p>
            </div>
        </div>
        <div class="welcome-right">
            <i class="bi bi-calendar-event"></i>
            <span><?php echo date('d/m/Y'); ?></span>
        </div>
    </div>

    <!-- Card de Licença -->
    <?php if (!$isVitalicio): ?>
    <div class="license-card" style="background: linear-gradient(135deg, <?php echo $corLicenca; ?>15 0%, <?php echo $corLicenca; ?>08 100%); border: 2px solid <?php echo $corLicenca; ?>40; border-radius: 16px; padding: 20px; margin-bottom: 24px; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: <?php echo $corLicenca; ?>10; border-radius: 50%;"></div>
        <div style="position: relative; z-index: 1;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1; min-width: 200px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div style="width: 48px; height: 48px; background: <?php echo $corLicenca; ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px <?php echo $corLicenca; ?>40;">
                            <i class="bi bi-shield-check" style="font-size: 24px; color: #fff;"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--dash-text);">Status da Licença</h3>
                            <p style="margin: 0; font-size: 0.875rem; color: var(--dash-text-light); font-weight: 500;"><?php echo $tipoLicenca; ?></p>
                        </div>
                    </div>
                    
                    <?php if ($statusLicenca == 'expirado'): ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #fee2e2; border-radius: 10px; border-left: 4px solid #ef4444;">
                            <i class="bi bi-exclamation-triangle-fill" style="color: #dc2626; font-size: 20px;"></i>
                            <span style="color: #991b1b; font-weight: 600; font-size: 0.9375rem;">Licença Expirada</span>
                        </div>
                    <?php elseif ($diasRestantes !== null): ?>
                        <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 8px;">
                            <span style="font-size: 2.5rem; font-weight: 800; color: <?php echo $corLicenca; ?>; line-height: 1;">
                                <?php echo $diasRestantes; ?>
                            </span>
                            <span style="font-size: 1rem; font-weight: 600; color: var(--dash-text-light);">
                                <?php echo $diasRestantes == 1 ? 'dia restante' : 'dias restantes'; ?>
                            </span>
                        </div>
                        <p style="margin: 0; font-size: 0.8125rem; color: var(--dash-text-light);">
                            Expira em: <strong style="color: var(--dash-text);"><?php echo date('d/m/Y', strtotime($dataExpiracao)); ?></strong>
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if ($statusLicenca != 'expirado'): ?>
                <div style="text-align: right;">
                    <a href="https://wa.me/5511999999999?text=Olá! Gostaria de renovar minha licença" 
                       target="_blank"
                       style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: <?php echo $corLicenca; ?>; color: #fff; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9375rem; box-shadow: 0 4px 12px <?php echo $corLicenca; ?>40; transition: all 0.3s ease;">
                        <i class="bi bi-whatsapp" style="font-size: 18px;"></i>
                        <span>Renovar Licença</span>
                    </a>
                </div>
                <?php else: ?>
                <div style="text-align: right;">
                    <a href="https://wa.me/5511999999999?text=Olá! Minha licença expirou, preciso reativar" 
                       target="_blank"
                       style="display: inline-flex; align-items: center; gap: 8px; padding: 14px 28px; background: #ef4444; color: #fff; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 1rem; box-shadow: 0 4px 16px #ef444460; animation: pulse 2s infinite;">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 20px;"></i>
                        <span>Reativar Agora</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros de Período -->
    <div class="filters-card">
        <div class="filters-header">
            <i class="bi bi-funnel-fill filters-icon"></i>
            <h2 class="filters-title">Filtrar Faturamento por Período</h2>
        </div>
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label class="filter-label">Data Início</label>
                <input type="date" name="data_inicio" class="filter-input" value="<?php echo htmlspecialchars($dataInicio); ?>" required>
            </div>
            <div class="filter-group">
                <label class="filter-label">Data Fim</label>
                <input type="date" name="data_fim" class="filter-input" value="<?php echo htmlspecialchars($dataFim); ?>" required>
            </div>
            <div class="filter-group">
                <label class="filter-label">Aniversários</label>
                <select name="mes_aniversario" class="filter-input">
                    <?php foreach ($mesNomes as $num => $nome): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($num == $mesFiltro) ? 'selected' : ''; ?>>
                            <?php echo $nome; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-filter">
                    <i class="bi bi-search"></i>
                    Aplicar Filtros
                </button>
            </div>
        </form>
    </div>

    <!-- Cards de Resumo -->
    <div class="stats-summary">
        <div class="stat-box stat-success">
            <span class="stat-label">💰 Faturamento Hoje</span>
            <p class="stat-value">
                R$ <?php echo number_format($faturamentoHoje, 2, ',', '.'); ?>
            </p>
            <span class="stat-chip" style="background:#d1fae5;color:#065f46;">
                <i class="bi bi-graph-up"></i> Dia em andamento
            </span>
        </div>
        <div class="stat-box stat-secondary">
            <span class="stat-label">
                📊 Faturamento Período
                <small style="display:block; margin-top:4px; font-size:0.6875rem; opacity:0.85; font-weight:500; text-transform:none;">
                    <?php echo date('d/m', strtotime($dataInicio)); ?> até <?php echo date('d/m/Y', strtotime($dataFim)); ?>
                </small>
            </span>
            <p class="stat-value">
                R$ <?php echo number_format($faturamentoPeriodo, 2, ',', '.'); ?>
            </p>
            <span class="stat-chip" style="background:#f3e8ff;color:#6b21a8;">
                <i class="bi bi-calendar-range"></i> <?php echo (int)$totalAgendamentosPeriodo; ?> agendamentos
            </span>
        </div>
        <div class="stat-box stat-primary">
            <span class="stat-label">👥 Total de Clientes</span>
            <p class="stat-value">
                <?php echo (int)$totalClientes; ?>
            </p>
            <span class="stat-chip" style="background:#dbeafe;color:#1e40af;">
                <i class="bi bi-people-fill"></i> Base ativa
            </span>
        </div>
        <div class="stat-box stat-warning">
            <span class="stat-label">📦 Produtos Estoque</span>
            <p class="stat-value">
                <?php echo (int)$totalProdutos; ?>
            </p>
            <span class="stat-chip" style="background:#fef3c7;color:#92400e;">
                <i class="bi bi-box-seam"></i> Disponíveis
            </span>
        </div>
    </div>

    <!-- Módulos principais -->
    <div class="modules-header">
        <div>
            <h3 class="modules-title">Módulos principais</h3>
            <p class="modules-subtitle">Acesse rápido o que você mais usa no dia a dia.</p>
        </div>
    </div>
    <div class="modules-scroll">

        <a href="<?php echo $isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php'; ?>" class="nav-card">
            <div class="icon-circle bg-indigo">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="nav-title">Agenda</div>
            <div class="nav-desc">Ver marcações, horários e encaixes.</div>
        </a>

        <a href="<?php echo $isProd ? '/servicos' : '/karen_site/controle-salao/pages/servicos/servicos.php'; ?>" class="nav-card">
            <div class="icon-circle bg-orange">
                <i class="bi bi-scissors"></i>
            </div>
            <div class="nav-title">Serviços</div>
            <div class="nav-desc">Cortes, pacotes, coloração e preços.</div>
        </a>

        <a href="<?php echo $isProd ? '/produtos-estoque' : '/karen_site/controle-salao/pages/produtos-estoque/produtos-estoque.php'; ?>" class="nav-card">
            <div class="icon-circle bg-blue">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="nav-title">Estoque</div>
            <div class="nav-desc">Controle de produtos e vendas.</div>
        </a>

        <a href="<?php echo $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php'; ?>" class="nav-card">
            <div class="icon-circle bg-emerald">
                <i class="bi bi-people"></i>
            </div>
            <div class="nav-title">Clientes</div>
            <div class="nav-desc">Base de clientes e histórico.</div>
        </a>

    </div>

    <!-- Painéis inferiores -->
    <div class="dashboard-panels">

        <div class="panel-column">

            <!-- Próximos agendamentos (Hoje) -->
            <div class="recent-section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">Próximos agendamentos (hoje)</h3>
                        <p class="section-sub">Veja quem está chegando ao salão.</p>
                    </div>
                    <a href="<?php echo $isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php'; ?>" class="btn-action">
                        <i class="bi bi-arrow-right-circle"></i> Ver agenda
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Horário</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agendamentosHoje)): ?>
                                <?php foreach ($agendamentosHoje as $ag): ?>
                                    <tr>
                                        <td data-label="Horário">
                                            <strong><?php echo date('H:i', strtotime($ag['horario'])); ?></strong>
                                        </td>
                                        <td data-label="Cliente"><?php echo htmlspecialchars($ag['cliente_nome']); ?></td>
                                        <td data-label="Serviço"><?php echo htmlspecialchars($ag['servico']); ?></td>
                                        <td data-label="Status">
                                            <span class="status-badge status-<?php echo htmlspecialchars($ag['status']); ?>">
                                                <?php echo htmlspecialchars($ag['status']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Ação">
                                            <a href="<?php echo $isProd ? '/agenda' : '/karen_site/controle-salao/pages/agenda/agenda.php'; ?>" class="btn-action">
                                                <i class="bi bi-pencil"></i> Abrir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-row">
                                        Nenhuma marcação agendada para hoje.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="panel-column">

            <!-- Clientes que mais vêm no salão -->
            <div class="recent-section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">Clientes que mais vêm</h3>
                        <p class="section-sub">Quem é fiel ao seu salão.</p>
                    </div>
                    <a href="<?php echo $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php'; ?>" class="btn-action">
                        <i class="bi bi-people"></i> Ver clientes
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table small">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Agendamentos</th>
                                <th>Realizados</th>
                                <th>Última visita</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($topClientes)): ?>
                                <?php foreach ($topClientes as $cli): ?>
                                    <tr>
                                        <td data-label="Cliente">
                                            <div class="top-client-name">
                                                <?php echo htmlspecialchars($cli['nome_cliente']); ?>
                                            </div>
                                            <div class="top-client-sub">
                                                Cliente fiel do salão
                                            </div>
                                        </td>
                                        <td data-label="Agendamentos">
                                            <span class="badge-pill">
                                                <?php echo (int)$cli['total_agendamentos']; ?>
                                            </span>
                                        </td>
                                        <td data-label="Realizados">
                                            <span class="badge-pill">
                                                <?php echo (int)$cli['total_realizados']; ?>
                                            </span>
                                        </td>
                                        <td data-label="Última visita">
                                            <?php
                                            if (!empty($cli['ultimo_atendimento'])) {
                                                $dt = new DateTime($cli['ultimo_atendimento']);
                                                echo $dt->format('d/m/Y H:i');
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-row">
                                        Ainda não há histórico suficiente para montar o ranking.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Aniversariantes do mês -->
            <div class="recent-section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">
                            Aniversariantes de <?php echo htmlspecialchars($mesNomes[str_pad($mesFiltro, 2, '0', STR_PAD_LEFT)]); ?>
                        </h3>
                        <p class="section-sub">Mande um parabéns especial e fidelize ainda mais.</p>
                    </div>
                    <a href="<?php echo $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php'; ?>" class="btn-action">
                        <i class="bi bi-gift"></i> Ver todos
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table small">
                        <thead>
                            <tr>
                                <th>Dia</th>
                                <th>Cliente</th>
                                <th>Telefone</th>
                                <th>Idade</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($aniversariantes)): ?>
                                <?php foreach ($aniversariantes as $cli): ?>
                                    <?php
                                    $dia = '-';
                                    $idadeLabel = '-';
                                    $idade = 0;
                                    if (!empty($cli['data_nascimento'])) {
                                        $nasc = new DateTime($cli['data_nascimento']);
                                        $dia = $nasc->format('d/m');
                                        $hojeDt = new DateTime('today');
                                        $idade = $hojeDt->diff($nasc)->y;
                                        $idadeLabel = $idade . ' anos';
                                    }
                                    ?>
                                    <tr>
                                        <td data-label="Dia">
                                            <span class="birthday-day"><?php echo $dia; ?></span>
                                        </td>
                                        <td data-label="Cliente"><?php echo htmlspecialchars($cli['nome']); ?></td>
                                        <td data-label="Telefone"><?php echo htmlspecialchars($cli['telefone'] ?? '-'); ?></td>
                                        <td data-label="Idade">
                                            <span class="birthday-age"><?php echo $idadeLabel; ?></span>
                                        </td>
                                        <td data-label="Ação">
                                            <button class="btn-send-birthday" onclick="abrirMensagemAniversario('<?php echo htmlspecialchars(addslashes($cli['nome'])); ?>', '<?php echo htmlspecialchars($cli['telefone'] ?? ''); ?>', <?php echo $idade; ?>)">
                                                <i class="bi bi-send-fill"></i>
                                                Enviar Parabéns
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-row">
                                        Nenhum aniversariante cadastrado para este mês.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- Confirmações Pendentes -->
            <?php if (!empty($agendamentosPendentes)): ?>
            <div class="pendentes-section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;"></i>
                            Confirmações Pendentes
                            <span class="pendentes-count"><?php echo count($agendamentosPendentes); ?></span>
                        </h3>
                        <p class="section-sub">Agendamentos aguardando sua confirmação</p>
                    </div>
                </div>

                <?php foreach ($agendamentosPendentes as $pend): ?>
                    <div class="pendente-item">
                        <div class="pendente-cliente">
                            <i class="bi bi-person-fill"></i>
                            <?php echo htmlspecialchars($pend['cliente_nome']); ?>
                        </div>
                        <div class="pendente-detalhes">
                            <strong>Serviço:</strong> <?php echo htmlspecialchars($pend['servico']); ?><br>
                            <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($pend['data_agendamento'])); ?> às <?php echo date('H:i', strtotime($pend['horario'])); ?><br>
                            <strong>Valor:</strong> R$ <?php echo number_format($pend['valor'], 2, ',', '.'); ?>
                        </div>
                        <div class="pendente-actions">
                            <button class="btn-confirmar" onclick="confirmarAgendamento(<?php echo $pend['id']; ?>, true, event)">
                                <i class="bi bi-check-circle-fill"></i>
                                Confirmar Apenas
                            </button>
                            <?php if (!empty($pend['telefone'])): ?>
                                <a href="https://wa.me/55<?php echo preg_replace('/[^0-9]/', '', $pend['telefone']); ?>?text=<?php 
                                    $msg = "Olá, {$pend['cliente_nome']}! 👋\n\n";
                                    $msg .= "Seu agendamento no {$nomeEstabelecimento} foi confirmado! ✅\n\n";
                                    $msg .= "📅 Data: " . date('d/m/Y', strtotime($pend['data_agendamento'])) . "\n";
                                    $msg .= "🕐 Horário: " . date('H:i', strtotime($pend['horario'])) . "\n";
                                    $msg .= "✂️ Serviço: {$pend['servico']}\n";
                                    $msg .= "💰 Valor: R$ " . number_format($pend['valor'], 2, ',', '.') . "\n\n";
                                    $msg .= "Te esperamos! 💜";
                                    echo urlencode($msg);
                                ?>" 
                                   class="btn-whats-pendente" 
                                   target="_blank"
                                   onclick="confirmarAgendamento(<?php echo $pend['id']; ?>, false, event)">
                                    <i class="bi bi-whatsapp"></i>
                                    Confirmar + WhatsApp
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

    </div><!-- .dashboard-panels -->

</main>

<!-- Modal de Agendamentos Próximos (30 minutos antes) -->
<?php if (!empty($agendamentosProximos)): ?>
<div class="proximos-modal active" id="proximosModal">
    <div class="proximos-box">
        <div class="proximos-header">
            <h3 class="proximos-title">
                <i class="bi bi-bell-fill"></i>
                Agendamento Próximo!
            </h3>
            <button class="proximos-close" onclick="fecharProximos()">&times;</button>
        </div>
        <?php foreach ($agendamentosProximos as $prox): ?>
            <div class="proximos-item">
                <div class="proximos-cliente">
                    <?php echo htmlspecialchars($prox['cliente_nome']); ?>
                </div>
                <div class="proximos-info">
                    <span><i class="bi bi-clock-fill"></i> <?php echo date('H:i', strtotime($prox['horario'])); ?></span>
                    <span><i class="bi bi-scissors"></i> <?php echo htmlspecialchars($prox['servico']); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>



<!-- Modal de Mensagem de Aniversário -->
<div class="message-modal" id="messageModal">
    <div class="message-box">
        <div class="message-header">
            <h3 class="message-title">
                <i class="bi bi-gift-fill"></i>
                Mensagem de Aniversário
            </h3>
            <button class="message-close" onclick="fecharMensagem()">&times;</button>
        </div>

        <div class="message-preview">
            <div class="message-preview-label">MENSAGEM GERADA AUTOMATICAMENTE</div>
            <div class="message-content" id="messageContent"></div>
        </div>

        <div class="message-info">
            <i class="bi bi-info-circle-fill"></i>
            <div class="message-info-text">
                Copie a mensagem ou envie diretamente pelo WhatsApp. Personalize como desejar para criar uma conexão ainda mais especial com seu cliente!
            </div>
        </div>

        <div class="message-actions">
            <button class="btn-copy-message" onclick="copiarMensagem()">
                <i class="bi bi-clipboard-check-fill"></i>
                Copiar Mensagem
            </button>
            <a href="#" id="whatsappLink" class="btn-whatsapp" target="_blank">
                <i class="bi bi-whatsapp"></i>
                Abrir WhatsApp
            </a>
        </div>
    </div>
</div>

<script>
    const nomeEstabelecimento = <?php echo json_encode($nomeEstabelecimento); ?>;
    let mensagemAtual = '';

    function abrirMensagemAniversario(nomeCliente, telefone, idade) {
        // Gerar mensagem personalizada
        const primeiroNome = nomeCliente.split(' ')[0];
        
        const mensagens = [
            `🎉🎂 Parabéns, ${primeiroNome}! 🎂🎉\n\n` +
            `Hoje é um dia especial e a equipe do ${nomeEstabelecimento} não poderia deixar passar em branco! ` +
            `Desejamos que seus ${idade} anos sejam repletos de alegrias, saúde e muitas conquistas.\n\n` +
            `Como presente, queremos te ver ainda mais linda(o)! Entre em contato e agende um horário especial. ` +
            `Temos uma surpresa esperando por você! 💇‍♀️✨\n\n` +
            `Com carinho,\n${nomeEstabelecimento} 💜`,
            
            `🎊 Feliz Aniversário, ${primeiroNome}! 🎊\n\n` +
            `A equipe do ${nomeEstabelecimento} deseja que este novo ciclo de ${idade} anos seja incrível! ` +
            `Que você continue sendo essa pessoa maravilhosa que alegra nosso salão.\n\n` +
            `Preparamos algo especial para você! Agende seu horário e venha celebrar conosco. ` +
            `Você merece todo o carinho e cuidado! 💅✨\n\n` +
            `Beijos da equipe ${nomeEstabelecimento}! 💕`,
            
            `🎈 ${primeiroNome}, hoje é seu dia! 🎈\n\n` +
            `Parabéns pelos seus ${idade} anos! A família ${nomeEstabelecimento} está em festa para comemorar ` +
            `com você essa data tão especial.\n\n` +
            `Que tal se presentear com aquele visual que você sempre quis? Agende agora e aproveite ` +
            `nossa surpresa de aniversário exclusiva para você! 🎁💇‍♀️\n\n` +
            `Estamos te esperando!\n${nomeEstabelecimento} 🌟`
        ];
        
        // Escolher mensagem aleatória
        mensagemAtual = mensagens[Math.floor(Math.random() * mensagens.length)];
        
        // Exibir no modal
        document.getElementById('messageContent').innerText = mensagemAtual;
        
        // Configurar link do WhatsApp
        const telefoneFormatado = telefone.replace(/\D/g, ''); // Remove caracteres não numéricos
        const whatsappUrl = `https://wa.me/55${telefoneFormatado}?text=${encodeURIComponent(mensagemAtual)}`;
        document.getElementById('whatsappLink').href = whatsappUrl;
        
        // Abrir modal
        document.getElementById('messageModal').classList.add('active');
    }

    function fecharMensagem() {
        document.getElementById('messageModal').classList.remove('active');
    }

    function copiarMensagem() {
        navigator.clipboard.writeText(mensagemAtual).then(() => {
            // Feedback visual
            const btn = event.target.closest('.btn-copy-message');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Copiado!';
            btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            }, 2000);
        }).catch(err => {
            alert('Erro ao copiar. Tente selecionar e copiar manualmente.');
        });
    }

    // Fechar modal ao clicar fora
    document.getElementById('messageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharMensagem();
        }
    });

    // Atalho ESC para fechar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharMensagem();
            fecharProximos();
        }
    });

    // ============================
    // MODAL DE AGENDAMENTOS PRÓXIMOS
    // ============================
    function fecharProximos() {
        const modal = document.getElementById('proximosModal');
        if (modal) {
            modal.classList.remove('active');
            // Salvar no localStorage para não mostrar novamente nesta sessão
            localStorage.setItem('proximosModalFechado', Date.now());
        }
    }

    // Verifica se deve mostrar o modal (não mostrar se foi fechado há menos de 1 hora)
    window.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('proximosModal');
        if (modal) {
            const fechadoEm = localStorage.getItem('proximosModalFechado');
            if (fechadoEm) {
                const umaHora = 60 * 60 * 1000; // 1 hora em milissegundos
                if (Date.now() - fechadoEm < umaHora) {
                    modal.classList.remove('active');
                }
            }
        }
    });

    // ============================
    // CONFIRMAÇÃO DE AGENDAMENTOS
    // ============================
    function confirmarAgendamento(agendamentoId, recarregar = true, event = null) {
        // Envia requisição AJAX para confirmar
        fetch('<?php echo $isProd ? '/api/confirmar_agendamento.php' : '/karen_site/controle-salao/api/confirmar_agendamento.php'; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + agendamentoId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Feedback visual
                const item = event ? event.target.closest('.pendente-item') : null;
                if (item) {
                    item.style.background = 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)';
                    item.style.borderColor = '#6ee7b7';
                    
                    // Remove após animação
                    setTimeout(() => {
                        item.style.transition = 'all 0.3s ease';
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(-100%)';
                        
                        setTimeout(() => {
                            if (recarregar) {
                                location.reload();
                            }
                        }, 300);
                    }, 1000);
                }
            } else {
                alert('Erro ao confirmar agendamento. Tente novamente.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao confirmar agendamento. Verifique sua conexão.');
        });
    }
</script>

<!-- Modal de Notificação de Licença -->
<?php if ($mostrarNotificacao): ?>
<div class="license-modal" id="licenseModal">
    <div class="license-modal-content">
        <div class="license-modal-header">
            <div class="license-modal-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h2 class="license-modal-title">⚠️ Atenção: Licença Expirando</h2>
            <p class="license-modal-subtitle">Ação necessária para manter seu acesso</p>
        </div>
        <div class="license-modal-body">
            <div class="license-modal-message">
                <p><?php echo htmlspecialchars($mensagemNotificacao); ?></p>
            </div>
            <div class="license-modal-actions">
                <button class="license-modal-btn license-modal-btn-secondary" onclick="fecharModalLicenca()">
                    Entendi
                </button>
                <a href="https://wa.me/5511999999999?text=<?php echo urlencode('Olá! Preciso renovar minha licença. ' . $mensagemNotificacao); ?>" 
                   target="_blank"
                   class="license-modal-btn license-modal-btn-primary"
                   style="text-decoration: none;">
                    <i class="bi bi-whatsapp"></i>
                    <span>Renovar Agora</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Fecha o modal de licença
    function fecharModalLicenca() {
        const modal = document.getElementById('licenseModal');
        if (modal) {
            modal.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        // Salva no localStorage para não mostrar novamente hoje
        localStorage.setItem('licenseModalShown_<?php echo $userId; ?>_<?php echo date('Y-m-d'); ?>', 'true');
    }

    // Verifica se já mostrou hoje
    document.addEventListener('DOMContentLoaded', function() {
        const modalShownToday = localStorage.getItem('licenseModalShown_<?php echo $userId; ?>_<?php echo date('Y-m-d'); ?>');
        if (modalShownToday === 'true') {
            const modal = document.getElementById('licenseModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
    });

    // Fecha ao clicar fora do modal
    document.getElementById('licenseModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalLicenca();
        }
    });
</script>

<style>
    @keyframes fadeOut {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
        }
    }
</style>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
