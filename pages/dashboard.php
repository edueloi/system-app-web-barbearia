<?php
// Definições da página
$pageTitle = 'Dashboard - Salão Top';
include '../includes/header.php'; 
include '../includes/menu.php'; 
include '../includes/db.php'; // Inclui a conexão ao banco

// --- SIMULAÇÃO DE USUÁRIO ---
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- 1. LÓGICA: BUSCAR DADOS HOJE ---
$hoje = date('Y-m-d');
$nomeUsuario = 'Profissional'; // Nome padrão, será atualizado abaixo

// A. Agendamentos de Hoje (Próximos Clientes)
$stmt = $pdo->prepare("SELECT cliente_nome, servico, horario, status FROM agendamentos 
                        WHERE user_id = ? AND data_agendamento = ? 
                        ORDER BY horario ASC LIMIT 5");
$stmt->execute([$userId, $hoje]);
$agendamentosHoje = $stmt->fetchAll();

// B. Faturamento de Hoje
$faturamentoHoje = $pdo->query("SELECT SUM(valor) FROM agendamentos 
                                WHERE user_id = {$userId} AND data_agendamento = '{$hoje}' 
                                AND status != 'Cancelado'")->fetchColumn() ?: 0;

// C. Contagem Total de Clientes
$totalClientes = $pdo->query("SELECT COUNT(id) FROM clientes WHERE user_id = {$userId}")->fetchColumn() ?: 0;

// D. Contagem Total de Produtos em Estoque
$totalProdutos = $pdo->query("SELECT COUNT(id) FROM produtos WHERE user_id = {$userId}")->fetchColumn() ?: 0;

// E. Buscar Nome do Usuário Logado
$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if ($user && !empty($user['nome'])) {
    $nomeUsuario = explode(' ', $user['nome'])[0]; // Pega só o primeiro nome
}

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

    /* Grid dos Cards (Botões grandes) */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }
    /* NOVO: Cards de Status (Faturamento, Clientes, Produtos) */
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
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 5px 0 0 0;
        color: var(--primary);
    }
    .stat-label {
        color: var(--text-gray);
        font-size: 0.9rem;
    }

    /* Cards de Navegação (Módulos) */
    .nav-card {
        background: white;
        padding: 25px;
        border-radius: 16px;
        box-shadow: var(--shadow);
        border: 1px solid #f1f5f9;
        text-decoration: none;
        transition: transform 0.2s, box-shadow 0.2s;
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

    /* Cores dos ícones */
    .bg-indigo { background: #e0e7ff; color: #4338ca; }
    .bg-orange { background: #ffedd5; color: #c2410c; }
    .bg-blue   { background: #dbeafe; color: #1e40af; }
    .bg-emerald{ background: #dcfce7; color: #15803d; }
    .bg-red    { background: #fee2e2; color: #dc2626; } /* Adicionei cor para Faturamento */


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

    /* Tabela de Agendamentos Recentes */
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
    }

    .custom-table td {
        padding: 16px 15px;
        border-bottom: 1px solid #f1f5f9;
        color: var(--text-dark);
    }

    .custom-table tr:last-child td { border-bottom: none; }

    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-Confirmado { background: #dcfce7; color: #166534; }
    .status-Pendente { background: #fef9c3; color: #854d0e; }
    .status-Cancelado { background: #fee2e2; color: #dc2626; }

    .btn-action {
        padding: 6px 12px;
        background: #f1f5f9;
        color: var(--text-dark);
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: 0.2s;
    }
    .btn-action:hover { background: #e2e8f0; }

</style>

<main class="main-content">

    <div class="welcome-section">
        <h1 class="welcome-title">Olá, <?php echo htmlspecialchars($nomeUsuario); ?>! 👋</h1>
        <p class="welcome-subtitle">Aqui está o resumo do teu salão hoje.</p>
    </div>

    <div class="stats-summary">
        <div class="stat-box">
            <span class="stat-label">Faturamento Previsto Hoje</span>
            <p class="stat-value" style="color:#16a34a;">R$ <?php echo number_format($faturamentoHoje, 2, ',', '.'); ?></p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Total de Clientes</span>
            <p class="stat-value"><?php echo $totalClientes; ?></p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Produtos em Estoque</span>
            <p class="stat-value"><?php echo $totalProdutos; ?></p>
        </div>
    </div>


    <h3 style="font-size:1.1rem; margin-bottom:15px; margin-top:0;">Módulos Principais</h3>
    <div class="stats-grid">
        <a href="agenda/agenda.php" class="nav-card">
            <div class="icon-circle bg-indigo"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-title">Agenda</div>
            <div class="stat-desc">Ver marcações de hoje</div>
        </a>

        <a href="servicos/servicos.php" class="nav-card">
            <div class="icon-circle bg-orange"><i class="bi bi-scissors"></i></div>
            <div class="stat-title">Serviços</div>
            <div class="stat-desc">Cortes e preços</div>
        </a>

        <a href="produtos-estoque/produtos-estoque.php" class="nav-card">
            <div class="icon-circle bg-blue"><i class="bi bi-box-seam"></i></div>
            <div class="stat-title">Estoque</div>
            <div class="stat-desc">Gerir produtos</div>
        </a>

        <a href="clientes/clientes.php" class="nav-card">
            <div class="icon-circle bg-emerald"><i class="bi bi-people"></i></div>
            <div class="stat-title">Clientes</div>
            <div class="stat-desc">Base de dados</div>
        </a>
    </div>

    <div class="recent-section">
        <div class="section-header">
            <h3 class="section-title">Próximos Agendamentos (Hoje)</h3>
            <a href="agenda/agenda.php" class="btn-action">Ver todos</a>
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
                    <?php if (count($agendamentosHoje) > 0): ?>
                        <?php foreach($agendamentosHoje as $ag): ?>
                            <tr>
                                <td><strong><?php echo date('H:i', strtotime($ag['horario'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($ag['cliente_nome']); ?></td>
                                <td><?php echo htmlspecialchars($ag['servico']); ?></td>
                                <td><span class="status-badge status-<?php echo htmlspecialchars($ag['status']); ?>"><?php echo htmlspecialchars($ag['status']); ?></span></td>
                                <td><a href="agenda/agenda.php" class="btn-action"><i class="bi bi-pencil"></i></a></td>
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

</main>

<?php include '../includes/footer.php'; ?>