<?php
// pages/comandas/comandas.php
require_once __DIR__ . '/../../includes/db.php';

// --- LÓGICA PHP (MANTIDA) ---
if (!isset($_SESSION['user_id'])) { 
    header('Location: /login.php'); 
    exit; 
}
$uid = $_SESSION['user_id'];

// Processar Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // NOVA COMANDA
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
        try {
            $pdo->beginTransaction();

            $cliente_id = $_POST['cliente_id'];
            $servico_id = $_POST['servico_id'];
            $titulo     = $_POST['titulo'];
            $tipo       = $_POST['tipo'];
            $qtd        = intval($_POST['qtd_total']);
            $valor_tot  = floatval($_POST['valor_final']);
            $dt_inicio  = $_POST['data_inicio'];
            $frequencia = $_POST['frequencia'];

            $stmt = $pdo->prepare("INSERT INTO comandas (user_id, cliente_id, servico_id, titulo, tipo, status, valor_total, qtd_total, data_inicio, frequencia) 
                                   VALUES (?, ?, ?, ?, ?, 'aberta', ?, ?, ?, ?)");
            $stmt->execute([$uid, $cliente_id, $servico_id, $titulo, $tipo, $valor_tot, $qtd, $dt_inicio, $frequencia]);
            $comanda_id = $pdo->lastInsertId();

            $valor_sessao = ($qtd > 0) ? $valor_tot / $qtd : 0;
            $data_atual = new DateTime($dt_inicio);
            
            for ($i = 1; $i <= $qtd; $i++) {
                $dt_sql = $data_atual->format('Y-m-d');
                $pdo->exec("INSERT INTO comanda_itens (comanda_id, numero, data_prevista, valor_sessao) 
                            VALUES ($comanda_id, $i, '$dt_sql', $valor_sessao)");

                $dias = ($frequencia == 'semanal') ? 7 : (($frequencia == 'quinzenal') ? 15 : 1); 
                if($frequencia != 'unico') {
                    $data_atual->modify("+$dias days");
                }
            }

            $pdo->commit();
            header("Location: comandas.php?msg=sucesso"); 
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro: " . $e->getMessage());
        }
    }

    // EDITAR COMANDA (se quiser depois implementar)
    if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
        // implementar update aqui se quiser
    }
}

// Deletar
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $pdo->prepare("DELETE FROM comandas WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    header("Location: comandas.php"); 
    exit;
}

// Dados para a tela
$filtro_status = $_GET['tab'] ?? 'aberta';
$busca = $_GET['q'] ?? '';

$sql = "SELECT c.*, cli.nome as c_nome,
        (SELECT COUNT(*) FROM comanda_itens WHERE comanda_id = c.id AND status = 'realizado') as feitos,
        (SELECT data_prevista FROM comanda_itens WHERE comanda_id = c.id AND status = 'pendente' ORDER BY data_prevista ASC LIMIT 1) as proxima
        FROM comandas c 
        JOIN clientes cli ON c.cliente_id = cli.id
        WHERE c.user_id = ? AND c.status = ?";

if($busca) {
    $sql .= " AND (cli.nome LIKE '%$busca%' OR c.titulo LIKE '%$busca%')";
}
$sql .= " ORDER BY c.data_inicio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$uid, $filtro_status]);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE user_id = $uid ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$servicos = $pdo->query("SELECT id, nome, preco, tipo, qtd_sessoes FROM servicos WHERE user_id = $uid")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    * { box-sizing:border-box; }

    :root {
        --primary: #4f46e5;
        --primary-soft: #eef2ff;
        --primary-soft-strong: #e0e7ff;
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --bg-page: #f1f5f9;
        --bg-card: #ffffff;
        --border: #e2e8f0;
        --radius-lg: 18px;
        --radius-md: 12px;
        --shadow-soft: 0 18px 45px rgba(15, 23, 42, 0.10);
    }

    body {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        background: radial-gradient(circle at top, #e5e7ff 0, #f8fafc 38%, #f1f5f9 100%);
        color: var(--text-dark);
        font-size: 0.9rem;
    }

    .main-content {
        max-width: 1100px;
        margin: 0 auto;
        padding: 18px 16px 32px;
    }

    /* HEADER / TÍTULO */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .page-title {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .page-title h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-dark);
        letter-spacing: -0.04em;
    }

    .page-title span {
        color: var(--text-gray);
        font-size: 0.85rem;
    }

    /* TABS */
    .tabs-wrapper {
        display: inline-flex;
        padding: 3px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }

    .tab-link {
        padding: 7px 16px;
        border-radius: 999px;
        text-decoration: none;
        color: var(--text-gray);
        font-weight: 500;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1px solid transparent;
        transition: 0.18s;
        min-width: 90px;
    }

    .tab-link.active {
        background: var(--primary);
        color: #ffffff;
        border-color: rgba(129, 140, 248, 0.4);
        box-shadow: 0 10px 24px rgba(79, 70, 229, 0.32);
    }

    /* BARRA DE AÇÕES */
    .actions-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 18px;
        align-items: center;
        flex-wrap: wrap;
    }

    .search-input {
        flex: 1;
        padding: 10px 14px 10px 38px;
        border-radius: 999px;
        border: 1px solid var(--border);
        outline: none;
        font-size: 0.85rem;
        background: rgba(248, 250, 252, 0.9);
        box-shadow: 0 6px 18px rgba(148, 163, 184, 0.25);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 10-1.397 1.398h-.001l3.85 3.85a1 1 0 001.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 110-10 5 5 0 010 10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-size: 16px;
        background-position: 13px center;
        color: var(--text-dark);
    }

    .search-input::placeholder {
        color: #9ca3af;
    }

    .search-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.25), 0 10px 28px rgba(79, 70, 229, 0.25);
        background: #ffffff;
    }

    .btn-chip {
        background: linear-gradient(135deg, var(--primary), #4338ca);
        color: white;
        border: none;
        padding: 9px 16px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 10px rgba(79,70,229,0.25);
        white-space: nowrap;
        transition: all 0.25s ease;
    }
    .btn-chip i { font-size: 0.9rem; }
    .btn-chip:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(79,70,229,0.35);
    }

    /* TABELA DESKTOP */
    .desktop-table-container {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead { background: #f9fafb; }

    th {
        text-align: left;
        padding: 10px 14px;
        color: var(--text-gray);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
    }

    td {
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        font-size: 0.88rem;
        color: var(--text-dark);
    }

    tr:last-child td { border-bottom:none; }

    tbody tr { transition: background 0.12s ease-out; }
    tbody tr:hover { background:#f9fafb; }

    .title-main {
        font-weight: 600;
        color: var(--text-dark);
        font-size: 0.95rem;
    }

    .tag-row {
        display:flex;
        flex-wrap:wrap;
        gap:4px;
        margin-top:3px;
    }

    .chip-type {
        font-size: 0.7rem;
        padding: 2px 7px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-weight: 600;
    }

    .chip-status {
        font-size: 0.7rem;
        padding: 2px 7px;
        border-radius: 999px;
        background: #ecfdf3;
        color: #166534;
        font-weight: 600;
    }

    .chip-status.fechada {
        background: #fef2f2;
        color: #b91c1c;
    }

    .progress-wrap { display:flex; flex-direction:column; gap:4px; }
    .progress-label { font-size:0.75rem; color:var(--text-gray); }

    .progress-bar-bg {
        width:100%;
        max-width:130px;
        height:6px;
        border-radius:999px;
        overflow:hidden;
        background:#e5e7eb;
    }
    .progress-bar-fill {
        height:100%;
        border-radius:999px;
        background:linear-gradient(90deg,#4f46e5,#22c55e);
    }

    .pill-next {
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:4px 9px;
        border-radius:999px;
        background:#eff6ff;
        color:#1d4ed8;
        font-weight:500;
        font-size:0.78rem;
    }

    .pill-done {
        display:inline-flex;
        align-items:center;
        gap:5px;
        padding:4px 9px;
        border-radius:999px;
        background:#f0fdf4;
        color:#16a34a;
        font-size:0.78rem;
        font-weight:500;
    }

    .pill-value {
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:4px 10px;
        border-radius:999px;
        background:#f9fafb;
        border:1px dashed #d1d5db;
        font-size:0.86rem;
        font-weight:600;
        color:#166534;
    }
    .pill-value span.label {
        font-size:0.7rem;
        text-transform:uppercase;
        color:var(--text-gray);
    }

    .actions-cell { text-align:right; }

    .btn-icon-danger {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:32px;
        height:32px;
        border-radius:999px;
        background:#fef2f2;
        color:#b91c1c;
        text-decoration:none;
        border:none;
        cursor:pointer;
        transition:0.15s;
    }
    .btn-icon-danger:hover { background:#fee2e2; }

    /* CARDS MOBILE */
    .mobile-cards-container {
        display:none;
        gap:10px;
        margin-top:4px;
    }

    .card-item {
        background:var(--bg-card);
        border-radius:var(--radius-lg);
        padding:12px 12px 10px;
        box-shadow:var(--shadow-soft);
        border:1px solid rgba(226,232,240,0.95);
        position:relative;
    }

    .card-header {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:6px;
        margin-bottom:8px;
    }

    .card-title {
        font-weight:600;
        font-size:0.98rem;
        color:var(--text-dark);
        margin-bottom:2px;
    }

    .card-subtitle { font-size:0.78rem; color:var(--text-gray); }

    .card-badge {
        font-size:0.7rem;
        padding:3px 7px;
        border-radius:999px;
        background:var(--primary-soft);
        color:var(--primary);
        font-weight:600;
    }

    .card-body {
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:8px;
        font-size:0.8rem;
        margin-bottom:8px;
    }

    .card-label {
        display:block;
        color:var(--text-gray);
        font-size:0.7rem;
        margin-bottom:2px;
    }

    .card-actions {
        border-top:1px solid #e5e7eb;
        padding-top:8px;
        text-align:right;
    }

    .btn-icon-sm {
        background:#fef2f2;
        color:#b91c1c;
        width:30px;
        height:30px;
        border-radius:999px;
        border:none;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        font-size:0.9rem;
    }

    /* MODAIS */
    .modal-overlay {
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15, 23, 42, 0.45);
        backdrop-filter:blur(4px);
        z-index:1000;
        align-items:center;
        justify-content:center;
        padding:16px;
        opacity:0;
        transition:opacity 0.25s ease-out;
    }
    .modal-overlay.open { display:flex; opacity:1; }

    .sheet-box {
        background:var(--bg-card);
        width:100%;
        max-width:480px;
        border-radius:24px;
        padding:18px 18px 22px 18px;
        transform:translateY(18px);
        transition:transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        max-height:88vh;
        overflow-y:auto;
        display:flex;
        flex-direction:column;
        box-shadow:0 24px 60px rgba(15,23,42,0.35);
        margin:0 auto;
    }
    .modal-overlay.open .sheet-box { transform:translateY(0); }

    .drag-handle {
        width:40px;
        height:4px;
        background:var(--border);
        margin:0 auto 16px auto;
        border-radius:999px;
        flex-shrink:0;
    }

    .form-group { margin-bottom:1rem; }
    .form-label {
        font-size:0.75rem;
        font-weight:600;
        color:var(--text-dark);
        margin-bottom:0.375rem;
        display:block;
    }
    .form-input,
    .form-input[type="number"],
    .form-input[type="date"],
    .form-input[type="text"],
    .form-input[type="textarea"],
    .form-input textarea,
    select.form-input {
        width:100%;
        padding:0.625rem 0.75rem;
        border:1px solid var(--border);
        border-radius:11px;
        font-size:0.87rem;
        background:#fff;
        outline:none;
        font-family:inherit;
        transition:all 0.2s ease;
    }
    .form-input:focus {
        border-color:var(--primary);
        box-shadow:0 0 0 3px rgba(79,70,229,0.1);
    }

    textarea.form-input {
        min-height:80px;
        resize:vertical;
    }

    .btn-main {
        width:100%;
        padding:0.75rem 1.5rem;
        background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        color:white;
        border:none;
        border-radius:11px;
        font-weight:600;
        font-size:0.95rem;
        margin-top:0.75rem;
        cursor:pointer;
        transition:all 0.2s ease;
        box-shadow:0 4px 12px rgba(99,102,241,0.3);
    }
    .btn-main:hover {
        transform:translateY(-1px);
        box-shadow:0 6px 16px rgba(99,102,241,0.4);
    }

    .btn-modal-cancel {
        width:100%;
        padding:0.75rem 1.5rem;
        background:white;
        color:var(--text-dark);
        border:1px solid var(--border);
        border-radius:11px;
        font-weight:600;
        font-size:0.95rem;
        margin-top:0.625rem;
        cursor:pointer;
        transition:all 0.2s ease;
    }

    .modal-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:10px;
    }

    .modal-header h3 {
        margin:0;
        font-size:1.05rem;
        font-weight:700;
        color:var(--text-dark);
    }

    .modal-header-sub {
        font-size:0.78rem;
        color:var(--text-gray);
        margin-top:2px;
    }

    .modal-close {
        border:none;
        background:#f1f5f9;
        width:28px;
        height:28px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        color:var(--text-gray);
        font-size:0.9rem;
        flex-shrink:0;
    }

    .form-grid { display:grid; gap:12px; margin-top:6px; }
    .two-cols { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }

    .type-switcher {
        background:#f3f4f6;
        padding:3px;
        border-radius:999px;
        display:flex;
        gap:4px;
        margin-top:4px;
    }
    .type-option {
        flex:1;
        text-align:center;
        padding:6px 4px;
        border-radius:999px;
        font-size:0.8rem;
        font-weight:600;
        color:var(--text-gray);
        cursor:pointer;
        transition:0.16s;
    }
    .type-option.active {
        background:#ffffff;
        color:var(--primary);
        box-shadow:0 6px 16px rgba(148,163,184,0.4);
    }

    #salvarBtns, #editarBtns { display:flex; gap:10px; margin-top:4px; }

    @media (max-width: 768px) {
        .main-content { padding:14px 10px 26px; }
        .page-header { flex-direction:column; align-items:flex-start; }
        .tabs-wrapper { width:100%; justify-content:space-between; }
        .tab-link { flex:1; text-align:center; }
        .actions-bar { flex-direction:column; }
        .btn-chip { width:100%; justify-content:center; }
        .desktop-table-container { display:none; }
        .mobile-cards-container { display:flex; flex-direction:column; }
        .card-body { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .sheet-box { max-width:100%; border-radius:20px; }
        .two-cols { grid-template-columns:1fr; }
    }
</style>

<main class="main-content">
    
    <div class="page-header">
        <div class="page-title">
            <h1>Comandas</h1>
            <span>Gerencie pacotes e sessões dos clientes</span>
        </div>
        <div class="tabs-wrapper">
            <a href="?tab=aberta" class="tab-link <?= $filtro_status=='aberta'?'active':'' ?>">Abertas</a>
            <a href="?tab=fechada" class="tab-link <?= $filtro_status=='fechada'?'active':'' ?>">Fechadas</a>
        </div>
    </div>

    <form class="actions-bar">
        <input type="hidden" name="tab" value="<?= $filtro_status ?>">
        <input
            type="text"
            name="q"
            placeholder="Buscar cliente ou descrição..."
            value="<?= htmlspecialchars($busca) ?>"
            class="search-input"
        >
        <button type="button" onclick="abrirModalNova()" class="btn-chip">
            <i class="bi bi-plus-lg"></i> Nova Comanda
        </button>
    </form>

    <?php if(count($lista) == 0): ?>
        <div style="text-align:center; padding: 40px 16px; color: var(--text-gray);">
            <i class="bi bi-clipboard-x" style="font-size: 2rem; opacity:0.5; margin-bottom:8px; display:block;"></i>
            Nenhuma comanda encontrada ainda.
        </div>
    <?php endif; ?>

    <!-- DESKTOP -->
    <div class="desktop-table-container">
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Cliente</th>
                    <th>Progresso</th>
                    <th>Próxima</th>
                    <th>Valor</th>
                    <th style="text-align:right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lista as $c): 
                    $percent = ($c['qtd_total'] > 0) ? ($c['feitos'] / $c['qtd_total']) * 100 : 0;
                    $percent = max(0, min(100, $percent));

                    $dataAttr = [
                        "id"=>$c["id"],
                        "c_nome"=>$c["c_nome"],
                        "titulo"=>$c["titulo"],
                        "qtd_total"=>$c["qtd_total"],
                        "data_inicio"=>$c["data_inicio"],
                        "frequencia"=>$c["frequencia"] ?? '',
                        "valor_total"=>$c["valor_total"],
                        "tipo"=>$c["tipo"],
                        "servico_id"=>$c["servico_id"] ?? null
                    ];
                    $dataJson = htmlspecialchars(json_encode($dataAttr), ENT_QUOTES, 'UTF-8');
                ?>
                <tr 
                    data-comanda='<?= $dataJson ?>' 
                >
                    <td>
                        <div class="title-main"><?= htmlspecialchars($c['titulo']) ?></div>
                        <div class="tag-row">
                            <span class="chip-type"><?= ucfirst($c['tipo']) ?></span>
                            <span class="chip-status <?= $c['status']==='fechada'?'fechada':'' ?>">
                                <?= $c['status']==='fechada' ? 'Concluída' : 'Ativa' ?>
                            </span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($c['c_nome']) ?></td>
                    <td>
                        <div class="progress-wrap">
                            <span class="progress-label"><?= $c['feitos'] ?>/<?= $c['qtd_total'] ?> sessões</span>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width:<?= $percent ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if($c['proxima']): ?>
                            <span class="pill-next">
                                <i class="bi bi-calendar-event"></i>
                                <?= date('d/m', strtotime($c['proxima'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="pill-done">
                                <i class="bi bi-check2-circle"></i> Todas realizadas
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="pill-value">
                            <span class="label">Pacote</span>
                            <span>R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></span>
                        </div>
                    </td>
                    <td class="actions-cell" onclick="event.stopPropagation()">
                        <button type="button" onclick="abrirModalEditar(JSON.parse(this.closest('tr').dataset.comanda), true)" class="btn-icon-danger" style="background:#eef2ff;color:#4f46e5;margin-right:6px;">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?del=<?= $c['id'] ?>" onclick="return confirm('Excluir esta comanda?')" class="btn-icon-danger">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE -->
    <div class="mobile-cards-container">
        <?php foreach($lista as $c): 
            $percent = ($c['qtd_total'] > 0) ? ($c['feitos'] / $c['qtd_total']) * 100 : 0;
            $percent = max(0, min(100, $percent));
        ?>
        <div class="card-item">
            <div class="card-header">
                <div>
                    <span class="card-title"><?= htmlspecialchars($c['titulo']) ?></span>
                    <span class="card-subtitle"><?= htmlspecialchars($c['c_nome']) ?></span>
                </div>
                <span class="card-badge"><?= ucfirst($c['tipo']) ?></span>
            </div>
            <div class="card-body">
                <div>
                    <span class="card-label">Progresso</span>
                    <div style="display:flex; align-items:center; gap:6px; font-size:0.78rem;">
                        <div class="progress-bar-bg" style="max-width:50px;">
                            <div class="progress-bar-fill" style="width:<?= $percent ?>%"></div>
                        </div>
                        <?= $c['feitos'] ?>/<?= $c['qtd_total'] ?>
                    </div>
                </div>
                <div>
                    <span class="card-label">Valor</span>
                    <strong style="color:#16a34a; font-size:0.9rem;">
                        R$ <?= number_format($c['valor_total'], 2, ',', '.') ?>
                    </strong>
                </div>
                <div>
                    <span class="card-label">Próxima</span>
                    <strong style="font-size:0.86rem;">
                        <?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '—' ?>
                    </strong>
                </div>
            </div>
            <div class="card-actions" onclick="event.stopPropagation()">
                <button
                    class="btn-icon-sm"
                    type="button"
                    style="background:#eef2ff;color:#4f46e5;margin-right:6px;"
                    onclick='abrirModalEditar(<?= json_encode($c, JSON_UNESCAPED_UNICODE) ?>, true)'>
                    <i class="bi bi-pencil"></i>
                </button>
                <button
                    class="btn-icon-sm"
                    type="button"
                    onclick="if(confirm('Excluir esta comanda?')) { window.location='?del=<?= $c['id'] ?>'; }"
                >
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- MODAL EDITAR -->
<div id="modalEditar" class="modal-overlay">
    <div class="sheet-box">
        <div class="drag-handle"></div>
        <div class="modal-header">
            <div>
                <h3>Comanda</h3>
                <div class="modal-header-sub">Visualize os detalhes da comanda</div>
            </div>
            <button type="button" onclick="fecharModalEditar()" class="modal-close"><i class="bi bi-x"></i></button>
        </div>
        <form id="formEditar" method="POST" autocomplete="off">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label class="form-label">Cliente</label>
                <input type="text" name="edit_cliente" id="editCliente" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Serviço base</label>
                <input type="text" name="edit_servico" id="editServico" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Título da comanda</label>
                <input type="text" name="edit_titulo" id="editTitulo" class="form-input" disabled>
            </div>
            <div class="form-grid two-cols">
                <div class="form-group">
                    <label class="form-label">Sessões</label>
                    <input type="number" name="edit_qtd" id="editQtd" class="form-input" min="1" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Início</label>
                    <input type="date" name="edit_inicio" id="editInicio" class="form-input" disabled>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Frequência</label>
                <input type="text" name="edit_frequencia" id="editFrequencia" class="form-input" readonly>
            </div>
            <div class="form-group" style="background:var(--primary-soft); padding:10px 11px; border-radius:13px; border:1px solid var(--primary-soft-strong);">
                <label class="form-label" style="color:var(--primary);">Valor final do pacote (R$)</label>
                <input type="number" step="0.01" name="edit_valor" id="editValor" class="form-input" style="border:none; background:transparent; padding:0; font-size:1.15rem; font-weight:700; color:var(--primary); box-shadow:none;" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Notificações</label>
                <textarea name="edit_notificacao" id="editNotificacao" class="form-input" placeholder="Adicionar notificação..." disabled></textarea>
            </div>
            <div id="editarBtns">
                <button type="button" onclick="habilitarEdicao()" class="btn-main">Editar</button>
                <button type="button" onclick="fecharModalEditar()" class="btn-modal-cancel">Fechar</button>
            </div>
            <div id="salvarBtns" style="display:none;">
                <button type="submit" class="btn-main">Salvar alterações</button>
                <button type="button" onclick="desabilitarEdicao()" class="btn-modal-cancel">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NOVA COMANDA -->
<div id="modalNova" class="modal-overlay">
    <div class="sheet-box">
        <div class="drag-handle"></div>
        <div class="modal-header">
            <div>
                <h3>Nova Comanda</h3>
                <div class="modal-header-sub">Crie um pacote vinculado a um cliente</div>
            </div>
            <button type="button" onclick="fecharModalNova()" class="modal-close"><i class="bi bi-x"></i></button>
        </div>
        <form method="POST" autocomplete="off" class="form-grid">
            <input type="hidden" name="acao" value="salvar">

            <div class="form-group">
                <label class="form-label">Tipo</label>
                <div class="type-switcher">
                    <div class="type-option active" onclick="mudarTipo('normal', this)">Normal</div>
                    <div class="type-option" onclick="mudarTipo('pacote', this)">Pacote</div>
                </div>
                <input type="hidden" name="tipo" id="tipoInput" value="normal">
            </div>

            <div class="form-group">
                <label class="form-label">Cliente</label>
                <select name="cliente_id" class="form-input" required>
                    <option value="">Selecione...</option>
                    <?php foreach($clientes as $cli) echo "<option value='{$cli['id']}'>{$cli['nome']}</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Serviço base</label>
                <select id="selServico" name="servico_id" class="form-input" onchange="atualizarValores()" required>
                    <option value="" data-preco="0">Selecione...</option>
                    <?php foreach($servicos as $s): ?>
                        <option value="<?= $s['id'] ?>" 
                                data-preco="<?= $s['preco'] ?>" 
                                data-tipo="<?= $s['tipo'] ?>" 
                                data-qtd="<?= $s['qtd_sessoes'] ?>">
                            <?= $s['nome'] ?> - R$ <?= number_format($s['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Título da comanda</label>
                <input type="text" name="titulo" id="titulo" class="form-input" placeholder="Ex: Pacote Terapêutico Mensal">
            </div>
            <div class="form-grid two-cols">
                <div class="form-group">
                    <label class="form-label">Sessões</label>
                    <input type="number" name="qtd_total" id="qtd" class="form-input" value="1" min="1" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Frequência</label>
                <select name="frequencia" class="form-input">
                    <option value="unico">Data única / consecutivo</option>
                    <option value="semanal" selected>Semanal (7 dias)</option>
                    <option value="quinzenal">Quinzenal (15 dias)</option>
                </select>
            </div>
            <div class="form-group" style="background:var(--primary-soft); padding:10px 11px; border-radius:13px; border:1px solid var(--primary-soft-strong);">
                <label class="form-label" style="color:var(--primary);">Valor final do pacote (R$)</label>
                <input
                    type="number"
                    step="0.01"
                    name="valor_final"
                    id="valorFinal"
                    class="form-input"
                    style="border:none; background:transparent; padding:0; font-size:1.15rem; font-weight:700; color:var(--primary); box-shadow:none;"
                >
            </div>
            <button type="submit" class="btn-main">Criar comanda</button>
            <button type="button" onclick="fecharModalNova()" class="btn-modal-cancel">Cancelar</button>
        </form>
    </div>
</div>

<script>
    const modalNova   = document.getElementById('modalNova');
    const modalEditar = document.getElementById('modalEditar');

    // --- MODAL NOVA ---
    function abrirModalNova() {
        if (!modalNova) return;
        modalNova.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function fecharModalNova() {
        if (!modalNova) return;
        modalNova.classList.remove('open');
        document.body.style.overflow = 'auto';
    }
    if (modalNova) {
        modalNova.addEventListener('click', (e) => {
            if (e.target === modalNova) fecharModalNova();
        });
    }

    // --- MODAL EDITAR ---
    function abrirModalEditar(data, editar = false) {
        if (!modalEditar) return;
        document.getElementById('editId').value         = data.id || '';
        document.getElementById('editCliente').value    = data.c_nome || '';
        document.getElementById('editServico').value    = data.servico_id || '';
        document.getElementById('editTitulo').value     = data.titulo || '';
        document.getElementById('editQtd').value        = data.qtd_total || '';
        document.getElementById('editInicio').value     = data.data_inicio || '';
        document.getElementById('editFrequencia').value = data.frequencia || '';
        document.getElementById('editValor').value      = data.valor_total || '';
        document.getElementById('editNotificacao').value = '';
        habilitarEdicao();
        modalEditar.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function fecharModalEditar() {
        if (!modalEditar) return;
        modalEditar.classList.remove('open');
        document.body.style.overflow = 'auto';
    }
    if (modalEditar) {
        modalEditar.addEventListener('click', (e) => {
            if (e.target === modalEditar) fecharModalEditar();
        });
    }

    function habilitarEdicao() {
        document.getElementById('editTitulo').disabled      = false;
        document.getElementById('editQtd').disabled         = false;
        document.getElementById('editInicio').disabled      = false;
        document.getElementById('editValor').disabled       = false;
        document.getElementById('editNotificacao').disabled = false;
        document.getElementById('salvarBtns').style.display = 'flex';
        document.getElementById('editarBtns').style.display = 'none';
    }
    function desabilitarEdicao() {
        document.getElementById('editTitulo').disabled      = true;
        document.getElementById('editQtd').disabled         = true;
        document.getElementById('editInicio').disabled      = true;
        document.getElementById('editValor').disabled       = true;
        document.getElementById('editNotificacao').disabled = true;
        document.getElementById('salvarBtns').style.display = 'none';
        document.getElementById('editarBtns').style.display = 'flex';
    }

    // --- LÓGICA DE TIPO / VALORES (NOVA COMANDA) ---
    function mudarTipo(tipo, el) {
        document.getElementById('tipoInput').value = tipo;
        document.querySelectorAll('.type-option').forEach(e => e.classList.remove('active'));
        el.classList.add('active');
        
        const qtdInput = document.getElementById('qtd');
        if (tipo === 'pacote') {
            qtdInput.setAttribute('readonly', 'readonly');
            atualizarValores();
        } else {
            qtdInput.removeAttribute('readonly');
            atualizarValores();
        }
    }

    function atualizarValores() {
        const sel = document.getElementById('selServico');
        if (!sel) return;
        const opt = sel.options[sel.selectedIndex];
        if (!opt) return;

        const preco       = parseFloat(opt.getAttribute('data-preco')) || 0;
        const nome        = opt.text.split('-')[0].trim();
        const tipoComanda = document.getElementById('tipoInput').value;
        const qtdServico  = parseInt(opt.getAttribute('data-qtd')) || 1;

        const tituloEl = document.getElementById('titulo');
        if (tituloEl && tituloEl.value === '') {
            tituloEl.value = nome;
        }

        if (tipoComanda === 'pacote') {
            document.getElementById('qtd').value       = qtdServico > 1 ? qtdServico : 4;
            document.getElementById('valorFinal').value = preco.toFixed(2);
        } else {
            calcTotal();
        }
    }

    function calcTotal() {
        const tipoComanda = document.getElementById('tipoInput').value;
        if (tipoComanda === 'pacote') return;

        const sel = document.getElementById('selServico');
        const opt = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.getAttribute('data-preco')) || 0;
        const qtd   = parseInt(document.getElementById('qtd').value) || 1;

        document.getElementById('valorFinal').value = (preco * qtd).toFixed(2);
    }
</script>
