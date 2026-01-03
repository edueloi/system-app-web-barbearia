<?php
require_once __DIR__ . '/../../includes/config.php';
include '../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProd ? '/login' : '../../login.php'));
    exit;
}

$userId = $_SESSION['user_id'];
$financeiroUrl = $isProd ? '/financeiro' : '/karen_site/controle-salao/pages/financeiro/financeiro.php';

// Categorias predefinidas
$categoriasEntrada = [
    'Agendamentos' => '💇 Agendamentos',
    'Produtos' => '🛍️ Produtos',
    'Pacotes' => '📦 Pacotes',
    'Outros' => '💰 Outros'
];

$categoriasSaida = [
    'Salários' => '👤 Salários',
    'Produtos' => '📦 Produtos/Estoque',
    'Aluguel' => '🏠 Aluguel',
    'Energia' => '⚡ Energia',
    'Água' => '💧 Água',
    'Internet' => '🌐 Internet',
    'Marketing' => '📢 Marketing',
    'Manutenção' => '🔧 Manutenção',
    'Impostos' => '📊 Impostos',
    'Outros' => '💸 Outros'
];

// Ações de CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    // Salvar movimento
    if ($acao === 'salvar_movimento') {
        $tipo = $_POST['tipo'] ?? 'entrada';
        $categoria = trim($_POST['categoria'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = (float)str_replace(',', '.', $_POST['valor'] ?? 0);
        $dataMov = $_POST['data_movimento'] ?? date('Y-m-d');
        $origem = $_POST['origem'] ?? 'manual';
        $referencia_id = !empty($_POST['referencia_id']) ? (int)$_POST['referencia_id'] : null;

        if (!in_array($tipo, ['entrada', 'saida'], true)) {
            $tipo = 'entrada';
        }

        if ($valor > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO financeiro_movimentos
                    (user_id, tipo, categoria, descricao, valor, data_movimento, origem, referencia_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $tipo, $categoria, $descricao, $valor, $dataMov, $origem, $referencia_id]);
            header("Location: {$financeiroUrl}?status=saved");
            exit;
        }
    }
    
    // Excluir movimento
    elseif ($acao === 'excluir' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM financeiro_movimentos WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        header("Location: {$financeiroUrl}?status=deleted");
        exit;
    }
    
    // Editar movimento
    elseif ($acao === 'editar' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $tipo = $_POST['tipo'] ?? 'entrada';
        $categoria = trim($_POST['categoria'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = (float)str_replace(',', '.', $_POST['valor'] ?? 0);
        $dataMov = $_POST['data_movimento'] ?? date('Y-m-d');

        if ($valor > 0) {
            $stmt = $pdo->prepare("
                UPDATE financeiro_movimentos 
                SET tipo = ?, categoria = ?, descricao = ?, valor = ?, data_movimento = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$tipo, $categoria, $descricao, $valor, $dataMov, $id, $userId]);
            header("Location: {$financeiroUrl}?status=updated");
            exit;
        }
    }
}

// Filtros
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$busca = $_GET['busca'] ?? '';

$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$mes = str_pad((string)(int)$mes, 2, '0', STR_PAD_LEFT);
$ano = (int)$ano;

$inicioMes = "{$ano}-{$mes}-01";
$fimMes = date('Y-m-t', strtotime($inicioMes));

// === 1. SINCRONIZAR AGENDAMENTOS COM FINANCEIRO ===
// Buscar agendamentos confirmados/concluídos que ainda não estão no financeiro
$stmtSync = $pdo->prepare("
    SELECT a.id, a.cliente_nome, a.servico, a.valor, a.data_agendamento, a.status
    FROM agendamentos a
    WHERE a.user_id = ?
      AND a.status IN ('Confirmado', 'Concluido')
      AND a.data_agendamento BETWEEN ? AND ?
      AND NOT EXISTS (
          SELECT 1 FROM financeiro_movimentos fm 
          WHERE fm.origem = 'agendamento' AND fm.referencia_id = a.id
      )
      AND a.valor > 0
");
$stmtSync->execute([$userId, $inicioMes, $fimMes]);
$agendamentosSync = $stmtSync->fetchAll();

// Inserir automaticamente no financeiro
foreach ($agendamentosSync as $ag) {
    $descricaoAuto = "Agendamento: {$ag['cliente_nome']} - {$ag['servico']}";
    $stmt = $pdo->prepare("
        INSERT INTO financeiro_movimentos
            (user_id, tipo, categoria, descricao, valor, data_movimento, origem, referencia_id)
        VALUES (?, 'entrada', 'Agendamentos', ?, ?, ?, 'agendamento', ?)
    ");
    $stmt->execute([$userId, $descricaoAuto, $ag['valor'], $ag['data_agendamento'], $ag['id']]);
}

// === 2. ESTATÍSTICAS GERAIS ===
$stmtSum = $pdo->prepare("
    SELECT
        SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) AS total_entradas,
        SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) AS total_saidas
    FROM financeiro_movimentos
    WHERE user_id = ?
      AND data_movimento BETWEEN ? AND ?
");
$stmtSum->execute([$userId, $inicioMes, $fimMes]);
$somas = $stmtSum->fetch() ?: [];
$totalEntradas = (float)($somas['total_entradas'] ?? 0);
$totalSaidas   = (float)($somas['total_saidas'] ?? 0);
$saldoMes      = $totalEntradas - $totalSaidas;

// === 3. ESTATÍSTICAS POR CATEGORIA ===
$stmtCat = $pdo->prepare("
    SELECT 
        categoria,
        tipo,
        SUM(valor) as total,
        COUNT(*) as quantidade
    FROM financeiro_movimentos
    WHERE user_id = ?
      AND data_movimento BETWEEN ? AND ?
    GROUP BY categoria, tipo
    ORDER BY total DESC
");
$stmtCat->execute([$userId, $inicioMes, $fimMes]);
$estatisticasCategorias = $stmtCat->fetchAll();

// === 4. FATURAMENTO DE AGENDAMENTOS ===
$stmtAgenda = $pdo->prepare("
    SELECT SUM(valor) AS total
    FROM agendamentos
    WHERE user_id = ?
      AND status IN ('Confirmado', 'Concluido')
      AND data_agendamento BETWEEN ? AND ?
");
$stmtAgenda->execute([$userId, $inicioMes, $fimMes]);
$faturamentoAgenda = (float)($stmtAgenda->fetchColumn() ?? 0);

// === 5. BUSCAR MOVIMENTOS COM FILTROS ===
$whereClauses = ["user_id = ?", "data_movimento BETWEEN ? AND ?"];
$params = [$userId, $inicioMes, $fimMes];

if ($filtroCategoria) {
    $whereClauses[] = "categoria = ?";
    $params[] = $filtroCategoria;
}
if ($filtroTipo) {
    $whereClauses[] = "tipo = ?";
    $params[] = $filtroTipo;
}
if ($busca) {
    $whereClauses[] = "(descricao LIKE ? OR categoria LIKE ?)";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$whereSQL = implode(' AND ', $whereClauses);

$stmtMov = $pdo->prepare("
    SELECT *
    FROM financeiro_movimentos
    WHERE {$whereSQL}
    ORDER BY data_movimento DESC, id DESC
    LIMIT 100
");
$stmtMov->execute($params);
$movimentos = $stmtMov->fetchAll();

include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/ui-toast.php';
?>

<style>
    :root {
        --primary-color: #0f2f66;
        --primary-dark: #1e3a8a;
        --accent: #2563eb;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --bg-page: #f1f5f9;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow-soft: 0 4px 12px rgba(15,23,42,0.08);
        --shadow-hover: 0 12px 24px rgba(15,23,42,0.12);
    }

    body {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        font-family: -apple-system, BlinkMacSystemFont, "Outfit", "Inter", system-ui, sans-serif;
        font-size: 0.875rem;
        color: var(--text-main);
        min-height: 100vh;
        line-height: 1.6;
    }

    .finance-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px 16px 100px 16px;
    }

    /* === HEADER === */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        border-radius: 20px;
        padding: 24px 28px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-soft);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .page-title {
        margin: 0;
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-subtitle {
        margin: 8px 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
        font-weight: 400;
    }

    .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    /* === FILTROS === */
    .filters-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-color);
    }

    .filters-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filter-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-main);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .filter-input, .filter-select {
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-page);
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-main);
        transition: all 0.2s;
    }

    .filter-input:focus, .filter-select:focus {
        outline: none;
        border-color: var(--accent);
        background: white;
    }

    .btn-filter {
        padding: 11px 20px;
        border-radius: 10px;
        border: none;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        font-weight: 700;
        font-size: 0.875rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s;
        box-shadow: var(--shadow-sm);
    }

    .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    .btn-reset {
        background: #6b7280;
        padding: 11px 16px;
    }

    /* === DASHBOARD CARDS === */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .dashboard-card {
        background: var(--bg-card);
        padding: 20px 24px;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-soft);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--accent));
    }

    .dashboard-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-hover);
    }

    .dashboard-card.success::before {
        background: linear-gradient(90deg, #10b981, #059669);
    }

    .dashboard-card.danger::before {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .dashboard-card.warning::before {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    .dashboard-card.info::before {
        background: linear-gradient(90deg, #3b82f6, #2563eb);
    }

    .card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 12px;
    }

    .card-icon.success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .card-icon.danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .card-icon.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .card-icon.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

    .card-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        font-weight: 700;
        margin-bottom: 8px;
    }

    .card-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.02em;
        margin-bottom: 4px;
    }

    .card-detail {
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* === FORMULÁRIO === */
    .form-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
    }

    .form-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-main);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-input, .form-select, .form-textarea {
        padding: 12px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-page);
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-main);
        transition: all 0.2s;
    }

    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--accent);
        background: white;
    }

    .btn-save {
        background: linear-gradient(135deg, var(--success), #059669);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.875rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        box-shadow: var(--shadow-sm);
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    /* === TABELA === */
    .table-card {
        background: var(--bg-card);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-soft);
        overflow: hidden;
        margin-bottom: 24px;
    }

    .table-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .table-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .custom-table th {
        text-align: left;
        padding: 14px 20px;
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: var(--bg-page);
        border-bottom: 2px solid var(--border-color);
    }

    .custom-table td {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .custom-table tr:hover {
        background: var(--bg-page);
    }

    .badge {
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .badge-entrada {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
    }

    .badge-saida {
        background: rgba(239, 68, 68, 0.15);
        color: #dc2626;
    }

    .badge-agendamento {
        background: rgba(59, 130, 246, 0.15);
        color: #2563eb;
    }

    .btn-action {
        padding: 6px 10px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-edit {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .btn-edit:hover {
        background: rgba(59, 130, 246, 0.2);
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .btn-delete:hover {
        background: rgba(239, 68, 68, 0.2);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 16px;
    }

    /* === GRÁFICOS === */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 24px;
        margin-bottom: 24px;
    }

    .chart-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-color);
    }

    .chart-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    /* === CATEGORIAS === */
    .categorias-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .categoria-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--bg-page);
        border-radius: 10px;
        border-left: 4px solid var(--accent);
    }

    .categoria-item.entrada {
        border-left-color: var(--success);
    }

    .categoria-item.saida {
        border-left-color: var(--danger);
    }

    .categoria-nome {
        font-weight: 600;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .categoria-valor {
        font-weight: 700;
        font-size: 1rem;
    }

    .categoria-valor.entrada {
        color: var(--success);
    }

    .categoria-valor.saida {
        color: var(--danger);
    }

    /* === RESPONSIVE === */
    @media (max-width: 768px) {
        .finance-wrapper {
            padding: 16px 12px 120px 12px;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .charts-grid {
            grid-template-columns: 1fr;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .custom-table {
            font-size: 0.75rem;
        }

        .custom-table th,
        .custom-table td {
            padding: 10px 12px;
        }
    }
</style>

<div class="finance-wrapper">
    <!-- HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="bi bi-currency-dollar"></i> Controle Financeiro
            </h1>
            <p class="page-subtitle">Gestão completa de entradas e saídas do seu negócio</p>
        </div>
    </div>

    <!-- FILTROS AVANÇADOS -->
    <div class="filters-section">
        <div class="filters-title">
            <i class="bi bi-funnel"></i> Filtros e Busca
        </div>
        <form method="GET" class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">Mês</label>
                <select name="mes" class="filter-select">
                    <?php
                    $meses = ['01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho',
                              '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'];
                    foreach ($meses as $mv => $nome):
                    ?>
                        <option value="<?php echo $mv; ?>" <?php echo ($mv === $mes) ? 'selected' : ''; ?>>
                            <?php echo $nome; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Ano</label>
                <input type="number" name="ano" class="filter-input" value="<?php echo $ano; ?>" min="2000" max="2100">
            </div>
            <div class="filter-group">
                <label class="filter-label">Tipo</label>
                <select name="tipo" class="filter-select">
                    <option value="">Todos</option>
                    <option value="entrada" <?php echo $filtroTipo === 'entrada' ? 'selected' : ''; ?>>Entradas</option>
                    <option value="saida" <?php echo $filtroTipo === 'saida' ? 'selected' : ''; ?>>Saídas</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Categoria</label>
                <select name="categoria" class="filter-select">
                    <option value="">Todas</option>
                    <optgroup label="Entradas">
                        <?php foreach (array_keys($categoriasEntrada) as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filtroCategoria === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Saídas">
                        <?php foreach (array_keys($categoriasSaida) as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filtroCategoria === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Buscar</label>
                <input type="text" name="busca" class="filter-input" placeholder="Descrição ou categoria..." value="<?php echo htmlspecialchars($busca); ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-filter">
                    <i class="bi bi-search"></i> Filtrar
                </button>
            </div>
            <div class="filter-group">
                <a href="<?php echo $financeiroUrl; ?>" class="btn-filter btn-reset">
                    <i class="bi bi-x-circle"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="dashboard-grid">
        <div class="dashboard-card success">
            <div class="card-icon success">
                <i class="bi bi-arrow-up-circle-fill"></i>
            </div>
            <div class="card-label">Total de Entradas</div>
            <div class="card-value">R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?></div>
            <div class="card-detail">
                <i class="bi bi-calendar-check"></i>
                <?php echo $meses[$mes] ?? ''; ?>/<?php echo $ano; ?>
            </div>
        </div>

        <div class="dashboard-card danger">
            <div class="card-icon danger">
                <i class="bi bi-arrow-down-circle-fill"></i>
            </div>
            <div class="card-label">Total de Saídas</div>
            <div class="card-value">R$ <?php echo number_format($totalSaidas, 2, ',', '.'); ?></div>
            <div class="card-detail">
                <i class="bi bi-calendar-check"></i>
                <?php echo $meses[$mes] ?? ''; ?>/<?php echo $ano; ?>
            </div>
        </div>

        <div class="dashboard-card <?php echo $saldoMes >= 0 ? 'info' : 'warning'; ?>">
            <div class="card-icon <?php echo $saldoMes >= 0 ? 'info' : 'warning'; ?>">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="card-label">Saldo do Mês</div>
            <div class="card-value" style="color: <?php echo $saldoMes >= 0 ? '#3b82f6' : '#f59e0b'; ?>;">
                R$ <?php echo number_format($saldoMes, 2, ',', '.'); ?>
            </div>
            <div class="card-detail">
                <?php if ($saldoMes >= 0): ?>
                    <i class="bi bi-arrow-up"></i> Saldo positivo
                <?php else: ?>
                    <i class="bi bi-arrow-down"></i> Saldo negativo
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card info">
            <div class="card-icon info">
                <i class="bi bi-calendar2-check"></i>
            </div>
            <div class="card-label">Agendamentos Confirmados</div>
            <div class="card-value">R$ <?php echo number_format($faturamentoAgenda, 2, ',', '.'); ?></div>
            <div class="card-detail">
                <i class="bi bi-check2-circle"></i> Confirmados/Concluídos
            </div>
        </div>
    </div>

    <!-- GRÁFICOS E ESTATÍSTICAS POR CATEGORIA -->
    <?php if (!empty($estatisticasCategorias)): ?>
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-title">
                <i class="bi bi-pie-chart-fill"></i> Entradas por Categoria
            </div>
            <div class="categorias-list">
                <?php 
                $entradasCat = array_filter($estatisticasCategorias, fn($e) => $e['tipo'] === 'entrada');
                foreach ($entradasCat as $cat): 
                ?>
                <div class="categoria-item entrada">
                    <div class="categoria-nome">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                        <?php echo htmlspecialchars($cat['categoria'] ?: 'Sem categoria'); ?>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">(<?php echo $cat['quantidade']; ?>)</span>
                    </div>
                    <div class="categoria-valor entrada">
                        R$ <?php echo number_format($cat['total'], 2, ',', '.'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($entradasCat)): ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                        Nenhuma entrada registrada
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-title">
                <i class="bi bi-bar-chart-fill"></i> Saídas por Categoria
            </div>
            <div class="categorias-list">
                <?php 
                $saidasCat = array_filter($estatisticasCategorias, fn($e) => $e['tipo'] === 'saida');
                foreach ($saidasCat as $cat): 
                ?>
                <div class="categoria-item saida">
                    <div class="categoria-nome">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                        <?php echo htmlspecialchars($cat['categoria'] ?: 'Sem categoria'); ?>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">(<?php echo $cat['quantidade']; ?>)</span>
                    </div>
                    <div class="categoria-valor saida">
                        R$ <?php echo number_format($cat['total'], 2, ',', '.'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($saidasCat)): ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                        Nenhuma saída registrada
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- FORMULÁRIO ADICIONAR MOVIMENTO -->
    <div class="form-card">
        <div class="form-title">
            <i class="bi bi-plus-circle-fill"></i> Adicionar Novo Movimento
        </div>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_movimento">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label"><i class="bi bi-arrow-left-right"></i> Tipo *</label>
                    <select name="tipo" id="tipoMovimento" class="form-select" required onchange="atualizarCategorias()">
                        <option value="entrada">💰 Entrada</option>
                        <option value="saida">💸 Saída</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="bi bi-calendar3"></i> Data *</label>
                    <input type="date" name="data_movimento" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="bi bi-cash-coin"></i> Valor *</label>
                    <input type="number" step="0.01" name="valor" class="form-input" placeholder="0,00" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="bi bi-tag-fill"></i> Categoria *</label>
                    <select name="categoria" id="categoriaSelect" class="form-select" required>
                        <?php foreach ($categoriasEntrada as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" data-tipo="entrada">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label class="form-label"><i class="bi bi-card-text"></i> Descrição</label>
                    <textarea name="descricao" class="form-textarea" placeholder="Detalhe o movimento financeiro..."></textarea>
                </div>
            </div>
            <button type="submit" class="btn-save">
                <i class="bi bi-check-circle-fill"></i> Salvar Movimento
            </button>
        </form>
    </div>

    <!-- TABELA DE MOVIMENTOS -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">
                <i class="bi bi-table"></i> Histórico de Movimentos
                <span style="font-size: 0.85rem; font-weight: 400; color: var(--text-muted);">
                    (<?php echo count($movimentos); ?> registros)
                </span>
            </div>
        </div>
        <table class="custom-table">
            <thead>
                <tr>
                    <th><i class="bi bi-calendar"></i> Data</th>
                    <th><i class="bi bi-arrow-left-right"></i> Tipo</th>
                    <th><i class="bi bi-tag"></i> Categoria</th>
                    <th><i class="bi bi-card-text"></i> Descrição</th>
                    <th><i class="bi bi-cash"></i> Valor</th>
                    <th><i class="bi bi-gear"></i> Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($movimentos)): ?>
                    <?php foreach ($movimentos as $mov): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo date('d/m/Y', strtotime($mov['data_movimento'])); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $mov['tipo']; ?>">
                                    <?php if ($mov['tipo'] === 'entrada'): ?>
                                        <i class="bi bi-arrow-up"></i> Entrada
                                    <?php else: ?>
                                        <i class="bi bi-arrow-down"></i> Saída
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $cat = htmlspecialchars($mov['categoria'] ?? '-');
                                if ($mov['origem'] === 'agendamento') {
                                    echo '<span class="badge badge-agendamento"><i class="bi bi-calendar-check"></i> ' . $cat . '</span>';
                                } else {
                                    echo $cat;
                                }
                                ?>
                            </td>
                            <td style="max-width: 300px;">
                                <?php echo htmlspecialchars($mov['descricao'] ?? '-'); ?>
                            </td>
                            <td style="font-weight: 700; font-size: 1rem; color: <?php echo $mov['tipo'] === 'entrada' ? 'var(--success)' : 'var(--danger)'; ?>;">
                                R$ <?php echo number_format((float)$mov['valor'], 2, ',', '.'); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <?php if ($mov['origem'] !== 'agendamento'): ?>
                                        <button type="button" class="btn-action btn-edit" onclick="editarMovimento(<?php echo $mov['id']; ?>)">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este movimento?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?php echo $mov['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete">
                                                <i class="bi bi-trash3-fill"></i> Excluir
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">
                                            <i class="bi bi-lock-fill"></i> Auto
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 8px;">
                                    Nenhum movimento encontrado
                                </div>
                                <div>
                                    <?php if ($filtroCategoria || $filtroTipo || $busca): ?>
                                        Tente ajustar os filtros de busca ou <a href="<?php echo $financeiroUrl; ?>">limpar filtros</a>.
                                    <?php else: ?>
                                        Adicione seu primeiro movimento financeiro usando o formulário acima.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SCRIPTS -->
<script>
    // Atualizar categorias com base no tipo
    const categoriasEntrada = <?php echo json_encode($categoriasEntrada); ?>;
    const categoriasSaida = <?php echo json_encode($categoriasSaida); ?>;

    function atualizarCategorias() {
        const tipo = document.getElementById('tipoMovimento').value;
        const selectCat = document.getElementById('categoriaSelect');
        const categorias = tipo === 'entrada' ? categoriasEntrada : categoriasSaida;

        selectCat.innerHTML = '';
        Object.entries(categorias).forEach(([key, label]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = label;
            selectCat.appendChild(option);
        });
    }

    // Editar movimento (modal simples)
    function editarMovimento(id) {
        // Você pode implementar um modal aqui
        alert('Funcionalidade de edição em desenvolvimento. ID: ' + id);
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        atualizarCategorias();
    });
</script>

<?php if (isset($_GET['status'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const status = '<?php echo $_GET['status']; ?>';
        let message = '';
        let type = 'success';

        switch(status) {
            case 'saved':
                message = 'Movimento salvo com sucesso!';
                break;
            case 'updated':
                message = 'Movimento atualizado com sucesso!';
                break;
            case 'deleted':
                message = 'Movimento excluído com sucesso!';
                type = 'info';
                break;
        }

        if (message && window.AppToast) {
            AppToast.show(message, type);
        }
    });
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
