<?php
// dashboard.php ou index.php do painel

// Definições da página
$pageTitle = 'Dashboard - Salão Top';
include '../includes/header.php';
include '../includes/menu.php';
include '../includes/db.php'; // Conexão com o banco SQLite

// --- SIMULAÇÃO DE USUÁRIO LOGADO ---
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// Datas
$hoje      = date('Y-m-d');
$mesAtual  = date('m');
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
    /* Estilos exclusivos do Dashboard */

    .welcome-section {
        margin-bottom: 35px;
    }
    .welcome-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-dark);
        margin: 0;
    }
    .welcome-subtitle {
        color: var(--text-gray);
        font-size: 0.95rem;
        margin-top: 5px;
    }

    /* Grid dos Cards de Resumo (Faturamento, Clientes, Produtos) */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 16px;
        box-shadow: var(--shadow);
        border: 1px solid #e2e8f0;
    }
    .stat-label {
        color: var(--text-gray);
        font-size: 0.9rem;
    }
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 5px 0 0 0;
        color: var(--primary);
    }

    /* Grid dos Módulos Principais */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    .nav-card {
        background: white;
        padding: 25px;
        border-radius: 16px;
        box-shadow: var(--shadow);
        border: 1px solid #f1f5f9;
        text-decoration: none;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .nav-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }
    .icon-circle {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }

    .bg-indigo  { background: #e0e7ff; color: #4338ca; }
    .bg-orange  { background: #ffedd5; color: #c2410c; }
    .bg-blue    { background: #dbeafe; color: #1e40af; }
    .bg-emerald { background: #dcfce7; color: #15803d; }
    .bg-red     { background: #fee2e2; color: #dc2626; }

    .stat-title {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 1.1rem;
        margin-bottom: 5px;
    }
    .stat-desc {
        color: var(--text-gray);
        font-size: 0.85rem;
    }

    /* Seções de baixo (Agendamentos, Top Clientes, Aniversariantes) */
    .dashboard-panels {
        display: flex;
        flex-direction: column;
        gap: 24px;
        margin-top: 30px;
        margin-bottom: 40px;
    }

    .recent-section {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: var(--shadow);
        border: 1px solid #e2e8f0;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 10px;
        flex-wrap: wrap;
    }
    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .custom-table th {
        text-align: left;
        padding: 12px 15px;
        color: var(--text-gray);
        font-weight: 600;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }
    .custom-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #f1f5f9;
        color: var(--text-dark);
        vertical-align: middle;
    }
    .custom-table tr:last-child td {
        border-bottom: none;
    }

    .custom-table.small th {
        padding: 10px;
        font-size: 0.75rem;
    }
    .custom-table.small td {
        padding: 10px;
        font-size: 0.85rem;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-Confirmado { background: #dcfce7; color: #166534; }
    .status-Pendente   { background: #fef9c3; color: #854d0e; }
    .status-Cancelado  { background: #fee2e2; color: #dc2626; }

    .btn-action {
        padding: 6px 12px;
        background: #f1f5f9;
        color: var(--text-dark);
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-action:hover {
        background: #e2e8f0;
    }

    .top-client-name {
        font-weight: 600;
        margin-bottom: 2px;
    }
    .top-client-sub {
        font-size: 0.75rem;
        color: var(--text-gray);
    }

    .badge-pill {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.75rem;
        background: #f1f5f9;
        color: var(--text-dark);
    }

    .birthday-day {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .birthday-age {
        font-size: 0.8rem;
        color: var(--text-gray);
    }

    @media (max-width: 768px) {
        .welcome-title {
            font-size: 1.4rem;
        }
    }
</style>

<main class="main-content">

    <div class="welcome-section">
        <h1 class="welcome-title">Olá, <?php echo htmlspecialchars($nomeUsuario); ?>! 👋</h1>
        <p class="welcome-subtitle">Aqui está o resumo do teu salão hoje.</p>
    </div>

    <!-- Cards de Resumo -->
    <div class="stats-summary">
        <div class="stat-box">
            <span class="stat-label">Faturamento previsto hoje</span>
            <p class="stat-value" style="color:#16a34a;">
                R$ <?php echo number_format($faturamentoHoje, 2, ',', '.'); ?>
            </p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Total de clientes</span>
            <p class="stat-value">
                <?php echo (int)$totalClientes; ?>
            </p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Produtos em estoque</span>
            <p class="stat-value">
                <?php echo (int)$totalProdutos; ?>
            </p>
        </div>
    </div>

    <!-- Módulos principais -->
    <h3 style="font-size:1.1rem; margin-bottom:15px; margin-top:0;">Módulos principais</h3>
    <div class="stats-grid">

        <a href="agenda/agenda.php" class="nav-card">
            <div class="icon-circle bg-indigo">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-title">Agenda</div>
            <div class="stat-desc">Ver marcações e horários</div>
        </a>

        <a href="servicos/servicos.php" class="nav-card">
            <div class="icon-circle bg-orange">
                <i class="bi bi-scissors"></i>
            </div>
            <div class="stat-title">Serviços</div>
            <div class="stat-desc">Cortes, pacotes e preços</div>
        </a>

        <a href="produtos-estoque/produtos-estoque.php" class="nav-card">
            <div class="icon-circle bg-blue">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-title">Estoque</div>
            <div class="stat-desc">Controle de produtos</div>
        </a>

        <a href="clientes/clientes.php" class="nav-card">
            <div class="icon-circle bg-emerald">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-title">Clientes</div>
            <div class="stat-desc">Base de clientes e histórico</div>
        </a>

    </div>

    <!-- Painéis de baixo: Agendamentos, Top Clientes, Aniversariantes -->
    <div class="dashboard-panels">

        <!-- Próximos agendamentos (Hoje) -->
        <div class="recent-section">
            <div class="section-header">
                <h3 class="section-title">Próximos agendamentos (hoje)</h3>
                <a href="agenda/agenda.php" class="btn-action">
                    <i class="bi bi-arrow-right-circle"></i> Ver todos
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
                                    <td>
                                        <strong><?php echo date('H:i', strtotime($ag['horario'])); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($ag['cliente_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($ag['servico']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($ag['status']); ?>">
                                            <?php echo htmlspecialchars($ag['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="agenda/agenda.php" class="btn-action">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; color:var(--text-gray); padding:30px;">
                                    Nenhuma marcação agendada para hoje.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Clientes que mais vêm no salão -->
        <div class="recent-section">
            <div class="section-header">
                <h3 class="section-title">Clientes que mais vêm no salão</h3>
                <a href="clientes/clientes.php" class="btn-action">
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
                                    <td>
                                        <div class="top-client-name">
                                            <?php echo htmlspecialchars($cli['nome_cliente']); ?>
                                        </div>
                                        <div class="top-client-sub">
                                            Cliente fiel do salão
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-pill">
                                            <?php echo (int)$cli['total_agendamentos']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-pill">
                                            <?php echo (int)$cli['total_realizados']; ?>
                                        </span>
                                    </td>
                                    <td>
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
                                <td colspan="4" style="text-align:center; color:var(--text-gray); padding:30px;">
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
                <h3 class="section-title">Aniversariantes de <?php echo htmlspecialchars($nomeMesAtual); ?></h3>
                <a href="clientes/clientes.php" class="btn-action">
                    <i class="bi bi-gift"></i> Ver clientes
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
                                    <td>
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
                                    <td><?php echo htmlspecialchars($cli['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($cli['telefone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="birthday-age"><?php echo $idadeLabel; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:var(--text-gray); padding:30px;">
                                    Nenhum aniversariante cadastrado para este mês.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div><!-- .dashboard-panels -->

</main>

<?php include '../includes/footer.php'; ?>
