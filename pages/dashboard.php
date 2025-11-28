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
$pageTitle = 'Dashboard - Salão Top';

include '../includes/header.php';
include '../includes/menu.php';
include '../includes/db.php';

// =========================================================
// 3. LÓGICA: CONSULTAS NO BANCO
// =========================================================

// Datas
$hoje        = date('Y-m-d');
$mesAtual    = date('m');
$nomeUsuario = 'Profissional';

// --- BUSCA NOME DO USUÁRIO ---
$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if ($user && !empty($user['nome'])) {
    $nomeUsuario = explode(' ', $user['nome'])[0]; // Primeiro nome
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
    ':mes'    => $mesAtual
]);
$aniversariantes = $stmtAniv->fetchAll();

?>
<style>
    /* ============================
       DASHBOARD - ESTILO APP
       ============================ */

    :root {
        --dash-radius-lg: 18px;
        --dash-radius-md: 14px;
    }

    .app-dashboard-wrapper {
        padding: 12px 14px 18px;
        max-width: 1200px;
        margin: 0 auto;
    }

    @media (min-width: 1024px) {
        .app-dashboard-wrapper {
            padding: 16px 18px 26px;
        }
    }

    .welcome-section {
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .welcome-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        background: radial-gradient(circle at 30% 0, #a5b4fc, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 1rem;
        box-shadow: 0 8px 18px rgba(79, 70, 229, 0.4);
    }

    .welcome-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-dark);
        margin: 0;
        letter-spacing: 0.01em;
    }

    .welcome-subtitle {
        color: var(--text-gray);
        font-size: 0.84rem;
        margin-top: 2px;
        font-weight: 500;
    }

    .welcome-right {
        font-size: 0.78rem;
        color: var(--text-gray);
        padding: 6px 10px;
        border-radius: 999px;
        background: #eef2ff;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
    }

    .welcome-right i {
        font-size: 0.9rem;
        color: #4f46e5;
    }

    /* Cards de resumo */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .stat-box {
        position: relative;
        background: linear-gradient(145deg, #ffffff, #f9fafb);
        padding: 14px 12px 12px;
        border-radius: var(--dash-radius-lg);
        box-shadow: var(--shadow);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        min-width: 0;
    }

    .stat-box::after {
        content: "";
        position: absolute;
        right: -16px;
        top: -16px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(129,140,248,0.2), transparent 60%);
    }

    .stat-label {
        color: var(--text-gray);
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 1.35rem;
        font-weight: 800;
        margin: 0;
        color: var(--primary);
        letter-spacing: 0.01em;
        display: flex;
        align-items: baseline;
        gap: 4px;
    }

    .stat-chip {
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #ecfdf5;
        color: #166534;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .stat-chip i {
        font-size: 0.85rem;
    }

    /* Módulos principais - estilo carrossel no mobile */
    .modules-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 4px 0 10px;
    }

    .modules-title {
        font-size: 1.02rem;
        font-weight: 700;
        margin: 0;
    }

    .modules-subtitle {
        font-size: 0.78rem;
        color: var(--text-gray);
    }

    .modules-scroll {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 4px;
        margin-bottom: 14px;
        scroll-snap-type: x mandatory;
    }

    .modules-scroll::-webkit-scrollbar {
        height: 4px;
    }
    .modules-scroll::-webkit-scrollbar-thumb {
        background: #c7d2fe;
        border-radius: 999px;
    }

    .nav-card {
        min-width: 160px;
        max-width: 210px;
        flex: 0 0 auto;
        background: #ffffff;
        padding: 12px 10px 10px;
        border-radius: var(--dash-radius-lg);
        box-shadow: var(--shadow);
        border: 1px solid #e5e7eb;
        text-decoration: none;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
        display: flex;
        flex-direction: column;
        gap: 4px;
        scroll-snap-align: start;
    }

    @media (min-width: 768px) {
        .modules-scroll {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            overflow: visible;
        }
        .nav-card {
            flex: 1 1 auto;
        }
    }

    .nav-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -10px rgba(15, 23, 42, 0.25);
        border-color: var(--primary);
    }

    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 4px;
    }

    .bg-indigo  { background: #e0e7ff; color: #4338ca; }
    .bg-orange  { background: #ffedd5; color: #c2410c; }
    .bg-blue    { background: #dbeafe; color: #1e40af; }
    .bg-emerald { background: #dcfce7; color: #15803d; }

    .nav-title {
        font-weight: 700;
        color: var(--text-dark);
        font-size: 0.95rem;
    }
    .nav-desc {
        color: var(--text-gray);
        font-size: 0.78rem;
    }

    /* Painéis de baixo */
    .dashboard-panels {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr);
        gap: 10px;
        margin-top: 10px;
    }

    @media (min-width: 960px) {
        .dashboard-panels {
            grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.1fr);
        }
    }

    .panel-column {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .recent-section {
        background: #ffffff;
        border-radius: var(--dash-radius-md);
        padding: 10px 8px 8px;
        box-shadow: var(--shadow);
        border: 1px solid #e2e8f0;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        gap: 6px;
        flex-wrap: wrap;
    }
    .section-title {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: 0.01em;
    }

    .section-sub {
        font-size: 0.76rem;
        color: var(--text-gray);
        margin-top: 2px;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .custom-table th {
        text-align: left;
        padding: 6px 6px;
        color: var(--text-gray);
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
        background: #f9fafb;
    }
    .custom-table td {
        padding: 7px 6px;
        border-bottom: 1px solid #f1f5f9;
        color: var(--text-dark);
        vertical-align: middle;
        font-size: 0.8rem;
    }
    .custom-table tr:last-child td {
        border-bottom: none;
    }

    .custom-table.small th {
        padding: 5px 5px;
        font-size: 0.68rem;
    }
    .custom-table.small td {
        padding: 6px 5px;
        font-size: 0.76rem;
    }

    .status-badge {
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 700;
        display: inline-block;
        white-space: nowrap;
    }
    .status-Confirmado { background: #dcfce7; color: #166534; }
    .status-Pendente   { background: #fef9c3; color: #854d0e; }
    .status-Cancelado  { background: #fee2e2; color: #dc2626; }

    .btn-action {
        padding: 4px 8px;
        background: #f1f5f9;
        color: var(--text-dark);
        border-radius: 999px;
        text-decoration: none;
        font-size: 0.72rem;
        font-weight: 600;
        transition: 0.16s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .btn-action i {
        font-size: 0.85rem;
    }
    .btn-action:hover {
        background: #e2e8f0;
    }

    .top-client-name {
        font-weight: 700;
        margin-bottom: 1px;
        font-size: 0.82rem;
    }
    .top-client-sub {
        font-size: 0.68rem;
        color: var(--text-gray);
    }

    .badge-pill {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 0.68rem;
        background: #f1f5f9;
        color: var(--text-dark);
        font-weight: 700;
    }

    .birthday-day {
        font-weight: 700;
        font-size: 0.82rem;
    }
    .birthday-age {
        font-size: 0.68rem;
        color: var(--text-gray);
    }

    .empty-row {
        text-align: center;
        color: var(--text-gray);
        padding: 22px 4px;
        font-size: 0.8rem;
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
            margin: 0 2px 10px;
            border-radius: 14px;
            background: #f9fafb;
            box-shadow: 0 6px 14px -8px rgba(15, 23, 42, 0.3);
            overflow: hidden;
        }

        .custom-table td,
        .custom-table.small td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border-bottom: 0;
            padding: 6px 9px;
            font-size: 0.78rem;
        }

        .custom-table td:last-child,
        .custom-table.small td:last-child {
            padding-bottom: 8px;
        }

        .custom-table td[data-label]::before,
        .custom-table.small td[data-label]::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-gray);
        }
    }

    @media (max-width: 768px) {
        .welcome-title {
            font-size: 1.05rem;
        }
        .stats-summary {
            gap: 8px;
        }
        .stat-box {
            padding: 11px 10px 10px;
            border-radius: 14px;
        }
        .recent-section {
            padding: 8px 4px 6px;
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

    <!-- Cards de Resumo -->
    <div class="stats-summary">
        <div class="stat-box">
            <span class="stat-label">Faturamento previsto hoje</span>
            <p class="stat-value" style="color:#16a34a;">
                R$ <?php echo number_format($faturamentoHoje, 2, ',', '.'); ?>
            </p>
            <span class="stat-chip">
                <i class="bi bi-graph-up"></i> Dia em andamento
            </span>
        </div>
        <div class="stat-box">
            <span class="stat-label">Total de clientes</span>
            <p class="stat-value">
                <?php echo (int)$totalClientes; ?>
            </p>
            <span class="stat-chip" style="background:#eff6ff;color:#1d4ed8;">
                <i class="bi bi-people-fill"></i> Base ativa
            </span>
        </div>
        <div class="stat-box">
            <span class="stat-label">Produtos em estoque</span>
            <p class="stat-value">
                <?php echo (int)$totalProdutos; ?>
            </p>
            <span class="stat-chip" style="background:#fef3c7;color:#92400e;">
                <i class="bi bi-box-seam"></i> Loja pronta
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
                        <h3 class="section-title">Aniversariantes de <?php echo htmlspecialchars($nomeMesAtual); ?></h3>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($aniversariantes)): ?>
                                <?php foreach ($aniversariantes as $cli): ?>
                                    <tr>
                                        <td data-label="Dia">
                                            <?php
                                            $dia = '-';
                                            $idadeLabel = '-';
                                            if (!empty($cli['data_nascimento'])) {
                                                $nasc = new DateTime($cli['data_nascimento']);
                                                $dia = $nasc->format('d/m');
                                                $hojeDt = new DateTime('today');
                                                $idade = $hojeDt->diff($nasc)->y;
                                                $idadeLabel = $idade . ' anos';
                                            }
                                            ?>
                                            <span class="birthday-day"><?php echo $dia; ?></span>
                                        </td>
                                        <td data-label="Cliente"><?php echo htmlspecialchars($cli['nome']); ?></td>
                                        <td data-label="Telefone"><?php echo htmlspecialchars($cli['telefone'] ?? '-'); ?></td>
                                        <td data-label="Idade">
                                            <span class="birthday-age"><?php echo $idadeLabel; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-row">
                                        Nenhum aniversariante cadastrado para este mês.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

    </div><!-- .dashboard-panels -->

</main>

<?php include '../includes/footer.php'; ?>
