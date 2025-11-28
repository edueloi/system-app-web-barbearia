<?php
// dashboard.php (painel do profissional)

// Definições da página
$pageTitle = 'Dashboard';

include '../includes/header.php';
include '../includes/db.php';

// Garante sessão e login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

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

        <a href="agenda/agenda.php" class="nav-card">
            <div class="icon-circle bg-indigo">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="nav-title">Agenda</div>
            <div class="nav-desc">Ver marcações, horários e encaixes.</div>
        </a>

        <a href="servicos/servicos.php" class="nav-card">
            <div class="icon-circle bg-orange">
                <i class="bi bi-scissors"></i>
            </div>
            <div class="nav-title">Serviços</div>
            <div class="nav-desc">Cortes, pacotes, coloração e preços.</div>
        </a>

        <a href="produtos-estoque/produtos-estoque.php" class="nav-card">
            <div class="icon-circle bg-blue">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="nav-title">Estoque</div>
            <div class="nav-desc">Controle de produtos e vendas.</div>
        </a>

        <a href="clientes/clientes.php" class="nav-card">
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
                    <a href="agenda/agenda.php" class="btn-action">
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
                                            <a href="agenda/agenda.php" class="btn-action">
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
                    <a href="clientes/clientes.php" class="btn-action">
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
