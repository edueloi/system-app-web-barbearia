<?php
// Definições da página
$pageTitle = 'Dashboard - Salão Top';
include '../includes/header.php'; // 1. Carrega o topo e CSS
include '../includes/menu.php';   // 2. Carrega o menu lateral
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

    .stat-card {
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

    .stat-card:hover {
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
    .bg-blue   { background: #dbeafe; color: #1e40af; }
    .bg-emerald{ background: #dcfce7; color: #15803d; }

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
    .status-confirmed { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef9c3; color: #854d0e; }

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
        <h1 class="welcome-title">Olá, <?php echo isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : 'Profissional'; ?>! 👋</h1>
        <p class="welcome-subtitle">Aqui está o resumo do teu salão hoje.</p>
    </div>

    <div class="stats-grid">
        <a href="/karen_site/controle-salao/pages/agenda/agenda.php" class="stat-card">
            <div class="icon-circle bg-indigo"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-title">Agenda</div>
            <div class="stat-desc">Ver marcações</div>
        </a>

        <a href="/karen_site/controle-salao/pages/servicos/servicos.php" class="stat-card">
            <div class="icon-circle bg-orange"><i class="bi bi-scissors"></i></div>
            <div class="stat-title">Serviços</div>
            <div class="stat-desc">Cortes e preços</div>
        </a>

        <!-- <a href="produtos-estoque/produtos-estoque.php" class="stat-card"> -->
        <a href="#" class="stat-card disabled" style="pointer-events:none;opacity:0.5;">
            <div class="icon-circle bg-blue"><i class="bi bi-box-seam"></i></div>
            <div class="stat-title">Stock</div>
            <div class="stat-desc">Gerir produtos</div>
        </a>

        <a href="/karen_site/controle-salao/pages/clientes/clientes.php" class="stat-card">
            <div class="icon-circle bg-emerald"><i class="bi bi-people"></i></div>
            <div class="stat-title">Clientes</div>
            <div class="stat-desc">Base de dados</div>
        </a>
    </div>

    <div class="recent-section">
        <div class="section-header">
            <h3 class="section-title">Próximos Clientes</h3>
            <a href="/karen_site/controle-salao/pages/agenda/agenda.php" class="btn-action">Ver todos</a>
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
                    <tr>
                        <td><strong>14:00</strong></td>
                        <td>Ana Silva</td>
                        <td>Corte + Hidratação</td>
                        <td><span class="status-badge status-confirmed">Confirmado</span></td>
                        <td><a href="#" class="btn-action"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                    <tr>
                        <td><strong>15:30</strong></td>
                        <td>Carlos Souza</td>
                        <td>Barba e Cabelo</td>
                        <td><span class="status-badge status-pending">Pendente</span></td>
                        <td><a href="#" class="btn-action"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                    <tr>
                        <td><strong>16:45</strong></td>
                        <td>Mariana Costa</td>
                        <td>Madeixas</td>
                        <td><span class="status-badge status-confirmed">Confirmado</span></td>
                        <td><a href="#" class="btn-action"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php include '../includes/footer.php'; // 3. Fecha o HTML ?>