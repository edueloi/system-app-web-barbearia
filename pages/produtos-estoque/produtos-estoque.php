<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Produtos & Estoque';
include '../../includes/db.php';


// Simulação de Login
if (session_status() === PHP_SESSION_NONE) session_start();
$isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$produtosEstoqueUrl = $isProd
    ? '/produtos-estoque' // em produção usa rota amigável
    : '/karen_site/controle-salao/pages/produtos-estoque/produtos-estoque.php';

// --- 1. PROCESSAMENTO DE AÇÕES ---

// A. Excluir
if (isset($_GET['delete'])) {
    $idDel = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE id=? AND user_id=?");
    $stmt->execute([$idDel, $userId]);
    header("Location: {$produtosEstoqueUrl}?status=deleted");
    exit;
}

// B. Salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_produto') {
    $id          = (int)$_POST['produto_id'];
    $nome        = trim($_POST['nome']);
    $marca       = trim($_POST['marca']);
    $quantidade  = (int)$_POST['quantidade'];
    $tamanho     = str_replace(',', '.', $_POST['tamanho_embalagem']);
    $unidade     = $_POST['unidade'];
    $custo       = str_replace(',', '.', $_POST['custo_unitario']);
    $venda       = isset($_POST['preco_venda']) ? str_replace(',', '.', $_POST['preco_venda']) : 0;
    $dataCompra  = !empty($_POST['data_compra'])   ? $_POST['data_compra']   : null;
    $dataValidade= !empty($_POST['data_validade']) ? $_POST['data_validade'] : null;
    $obs         = trim($_POST['observacoes']);

    $custo   = empty($custo)   ? 0.00 : (float)$custo;
    $venda   = empty($venda)   ? 0.00 : (float)$venda;
    $tamanho = empty($tamanho) ? 0.00 : (float)$tamanho;

    if ($id > 0) {
        $sql = "UPDATE produtos 
                   SET nome=?, marca=?, quantidade=?, tamanho_embalagem=?, unidade=?, 
                       custo_unitario=?, preco_venda=?, data_compra=?, data_validade=?, observacoes=? 
                 WHERE id=? AND user_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome, $marca, $quantidade, $tamanho, $unidade,
            $custo, $venda, $dataCompra, $dataValidade, $obs,
            $id, $userId
        ]);
        $status = 'updated';
    } else {
        $sql = "INSERT INTO produtos 
                    (user_id, nome, marca, quantidade, tamanho_embalagem, unidade, custo_unitario, preco_venda, data_compra, data_validade, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId, $nome, $marca, $quantidade, $tamanho, $unidade,
            $custo, $venda, $dataCompra, $dataValidade, $obs
        ]);
        $status = 'saved';
    }

    header("Location: {$produtosEstoqueUrl}?status={$status}");
    exit;
}

// --- 2. BUSCAR DADOS ---
$produtos = $pdo->query("SELECT * FROM produtos WHERE user_id = {$userId} ORDER BY nome ASC")->fetchAll();

$custoTotalEstoque = 0;
$totalItens = 0;
foreach($produtos as $p) {
    $custoTotalEstoque += ($p['quantidade'] * $p['custo_unitario']);
    $totalItens        += $p['quantidade'];
}

$produtoEdicao = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND user_id = ?");
    $stmt->execute([$idEdit, $userId]);
    $produtoEdicao = $stmt->fetch();
}

// Buscar estoque comprometido
$comprometidos = [];
$stmtComp = $pdo->prepare("
    SELECT p.id as produto_id, SUM(pc.quantidade) as total_comprometido
    FROM produtos_comprometidos pc
    JOIN produtos p ON pc.produto_id = p.id
    WHERE pc.user_id = ?
    GROUP BY p.id
");
$stmtComp->execute([$userId]);
$comprometidosRaw = $stmtComp->fetchAll();
foreach ($comprometidosRaw as $c) {
    $comprometidos[$c['produto_id']] = $c['total_comprometido'];
}


include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/ui-toast.php';
include '../../includes/ui-confirm.php';
?>

<style>
    /* ESTILO PADRÃO DO PAINEL
       - Fonte base pequena e delicada (0.8rem–0.9rem)
       - Estilo clean, moderno, bordas bem arredondadas
       - Nada de background degradê no body, só fundo neutro
       - Cards brancos com leve sombra e borda suave
       - Tudo responsivo: ajustar padding e fonte no mobile
    */

    :root {
        --primary-color: #0f2f66;
        --primary-dark: #1e3a8a;
        --accent: #2563eb;
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-soft: 0 6px 18px rgba(15,23,42,0.06);
        --shadow-hover: 0 14px 30px rgba(15,23,42,0.10);
    }

    * {
        box-sizing: border-box;
    }

    body {
        background: transparent;
        font-family: -apple-system, BlinkMacSystemFont, "Outfit", "Inter", system-ui, sans-serif;
        font-size: 0.85rem;
        color: var(--text-main);
        min-height: 100vh;
        line-height: 1.5;
    }

    .estoque-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px 12px 80px 12px;
    }

    /* Header da página */
    .estoque-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 18px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .estoque-title-area {
        flex: 1;
        min-width: 180px;
    }

    .estoque-title-area h1 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary-color), var(--accent));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: -0.03em;
        line-height: 1.2;
    }

    .estoque-title-area p {
        margin: 6px 0 0;
        color: var(--text-muted);
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .btn-new {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 9px 14px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.25s ease;
        box-shadow: 0 4px 10px rgba(15,47,102,0.25);
        white-space: nowrap;
        outline: none;
    }

    .btn-new i {
        font-size: 0.9rem;
    }

    .btn-new:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(15,47,102,0.35);
    }

    .btn-new:active {
        transform: translateY(0);
    }

    /* Cards de estatísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .stat-card {
        background: var(--bg-card);
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid rgba(148,163,184,0.18);
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: var(--shadow-soft);
        transition: all 0.25s ease;
    }

    .stat-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-1px);
        border-color: rgba(15,47,102,0.2);
    }

    /* === FILTROS E BUSCA === */
    .filters-section {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
    }

    .filters-row {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 10px 12px 10px 38px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.85rem;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(15,47,102,0.1);
        background: white;
    }

    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 1rem;
    }

    .filter-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .filter-btn {
        padding: 8px 16px;
        border: 1px solid var(--border-color);
        background: white;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-main);
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .filter-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .filter-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    /* === GRID DE PRODUTOS (CARDS) === */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .product-card {
        background: var(--bg-card);
        border: 1px solid rgba(148,163,184,0.18);
        border-radius: 16px;
        padding: 18px;
        box-shadow: var(--shadow-soft);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-hover);
        border-color: rgba(15,47,102,0.3);
    }

    .product-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        gap: 10px;
    }

    .product-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.3;
        flex: 1;
    }

    .product-brand {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .product-alert {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 4px;
    }

    .alert-low { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }
    .alert-ok { background: #22c55e; }
    .alert-warning { background: #f59e0b; }

    .product-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin: 12px 0;
        padding: 12px;
        background: #f8fafc;
        border-radius: 12px;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-main);
        display: block;
    }

    .stat-label {
        font-size: 0.7rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 2px;
        display: block;
    }

    .product-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }

    .product-price {
        font-family: ui-monospace, monospace;
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    .product-actions {
        display: flex;
        gap: 6px;
    }

    .action-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        color: var(--text-muted);
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }

    .action-icon:hover {
        background: var(--primary-color);
        color: white;
    }

    .action-icon.delete:hover {
        background: #ef4444;
    }

    .product-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .badge-hot {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .badge-low {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .badge-expired {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .icon-money {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #16a34a;
    }

    .icon-box {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #2563eb;
    }

    .stat-info h3 {
        margin: 0 0 2px;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .stat-info p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    /* Container da tabela */
    .table-container {
        background: var(--bg-card);
        border-radius: 18px;
        border: 1px solid rgba(148,163,184,0.20);
        box-shadow: var(--shadow-soft);
        overflow: hidden;
    }

    .table-scroller {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .table-scroller::-webkit-scrollbar {
        height: 6px;
    }

    .table-scroller::-webkit-scrollbar-track {
        background: transparent;
    }

    .table-scroller::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 700px;
        font-size: 0.8rem;
    }

    .custom-table thead {
        background: #f8fafc;
    }

    .custom-table th {
        text-align: left;
        padding: 10px 14px;
        color: var(--text-muted);
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border-color);
        white-space: nowrap;
    }

    .custom-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(226,232,240,0.5);
        vertical-align: middle;
    }

    .custom-table tbody tr:last-child td {
        border-bottom: none;
    }

    .custom-table tbody tr {
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .custom-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Badges */
    .badge {
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1.1;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .bg-green {
        background: #dcfce7;
        color: #16a34a;
    }

    .bg-red {
        background: #fee2e2;
        color: #dc2626;
    }

    .bg-yellow {
        background: #fef9c3;
        color: #854d0e;
    }

    .bg-gray {
        background: #f3f4f6;
        color: #6b7280;
    }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 2.5rem;
        opacity: 0.4;
        margin-bottom: 10px;
        display: block;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.8rem;
    }

    .prod-name {
        font-weight: 700;
        color: var(--text-main);
        font-size: 0.85rem;
    }

    .prod-brand {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .prod-volume {
        font-weight: 600;
        color: var(--text-main);
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .prod-volume span {
        text-transform: lowercase;
        font-size: 0.72rem;
        color: var(--text-muted);
    }

    .price-mono {
        font-family: ui-monospace, "SF Mono", "Cascadia Code", monospace;
        font-size: 0.8rem;
        color: var(--text-main);
        font-weight: 600;
    }

    .status-date {
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-top: 3px;
    }

    /* Modal de cadastro/edição */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.45);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
        padding: 16px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: #f8fafc;
        border-radius: 20px;
        width: 100%;
        max-width: 540px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 18px 20px 20px;
        box-shadow: 0 20px 50px rgba(15,23,42,0.3);
        position: relative;
        border: 1px solid rgba(148,163,184,0.20);
    }

    .modal-content::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 64px;
        border-radius: 20px 20px 0 0;
        background: linear-gradient(135deg, rgba(15,47,102,0.12), rgba(37,99,235,0.08));
        pointer-events: none;
    }

    .modal-content > * {
        position: relative;
        z-index: 1;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }

    .modal-title {
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--text-main);
    }

    .modal-subtitle {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 3px;
    }

    .close-modal {
        background: #f1f5f9;
        border: 1px solid var(--border-color);
        width: 30px;
        height: 30px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        cursor: pointer;
        color: var(--text-muted);
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .close-modal:hover {
        background: var(--border-color);
        color: var(--text-main);
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .modal-content form {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .modal-title strong {
        font-weight: 800;
    }

    .form-group {
        margin-bottom: 12px;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 10px 12px;
    }

    .form-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 5px;
    }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.8rem;
        background: #f8fafc;
        color: var(--text-main);
        font-family: inherit;
        box-sizing: border-box;
        outline: none;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(15,47,102,0.12);
        background: #ffffff;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }

    .section-divider {
        margin: 12px 0;
        padding: 10px 12px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px dashed rgba(148,163,184,0.4);
    }

    .section-title {
        margin: 0 0 4px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-main);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .section-helper {
        margin: 0;
        font-size: 0.7rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .btn-full {
        width: 100%;
        justify-content: center;
        margin-top: 16px;
        padding: 11px;
        border-radius: 999px;
        font-size: 0.82rem;
    }

    /* Bottom sheet de produto */
    .product-sheet-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.4);
        backdrop-filter: blur(4px);
        display: none;
        align-items: flex-end;
        justify-content: center;
        z-index: 1050;
    }

    .product-sheet-overlay.open {
        display: flex;
    }

    .product-sheet {
        width: 100%;
        max-width: 460px;
        background: #f8fafc;
        border-radius: 24px 24px 0 0;
        box-shadow: 0 -12px 30px rgba(15,23,42,0.25);
        padding: 16px 18px 18px;
        animation: sheet-slide-up 0.25s ease-out;
    }

    @keyframes sheet-slide-up {
        from {
            transform: translateY(30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .product-sheet-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .product-sheet-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .product-sheet-sub {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .product-sheet-close-btn {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        border: 1px solid var(--border-color);
        background: #f1f5f9;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .product-sheet-close-btn:hover {
        background: var(--border-color);
    }

    .product-sheet-body {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid var(--border-color);
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 16px;
        font-size: 0.8rem;
    }

    .product-sheet-field-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 3px;
        font-weight: 600;
    }

    .product-sheet-field-value {
        font-weight: 600;
        color: var(--text-main);
        font-size: 0.8rem;
    }

    .product-sheet-full {
        grid-column: 1 / -1;
    }

    .product-sheet-actions {
        margin-top: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .product-sheet-btn {
        width: 100%;
        border-radius: 999px;
        border: none;
        padding: 10px 0;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .product-sheet-btn:hover {
        transform: translateY(-1px);
    }

    .product-sheet-btn-edit {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .product-sheet-btn-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Responsivo Mobile */
    @media (max-width: 768px) {
        .estoque-wrapper {
            padding: 18px 10px 100px 10px;
        }

        .estoque-header {
            padding: 14px 16px;
            border-radius: 14px;
            flex-direction: column;
            align-items: stretch;
        }

        .estoque-title-area h1 {
            font-size: 1.15rem;
        }

        .estoque-title-area p {
            font-size: 0.75rem;
        }

        .btn-new {
            width: 100%;
            justify-content: center;
            padding: 10px 14px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-card {
            padding: 12px 14px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 0.95rem;
        }

        .stat-info h3 {
            font-size: 0.9rem;
        }

        .stat-info p {
            font-size: 0.72rem;
        }

        /* Cards responsivos */
        .filters-row {
            flex-direction: column;
            gap: 10px;
        }

        .search-box {
            min-width: 100%;
        }

        .filter-group {
            flex-wrap: wrap;
        }

        .filter-btn {
            font-size: 0.75rem;
            padding: 7px 12px;
        }

        .products-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .product-card {
            padding: 14px;
        }

        .table-container {
            border-radius: 14px;
        }

        .custom-table {
            font-size: 0.75rem;
        }

        .custom-table th {
            font-size: 0.68rem;
            padding: 9px 10px;
        }

        .custom-table td {
            padding: 10px 10px;
        }

        .prod-name {
            font-size: 0.8rem;
        }

        .prod-brand {
            font-size: 0.7rem;
        }

        .badge {
            font-size: 0.68rem;
            padding: 2px 7px;
        }

        .modal-content {
            max-width: 100%;
            border-radius: 16px;
            padding: 16px 18px 18px;
        }

        .modal-title {
            font-size: 0.85rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-control {
            font-size: 0.8rem;
            padding: 9px 10px;
        }

        .product-sheet {
            max-width: 100%;
        }
    }
</style>

<div class="estoque-wrapper">
    <div class="estoque-header">
        <div class="estoque-title-area">
            <h1>Estoque & Produtos</h1>
            <p>Controle seus materiais com visual de app leve e organizado.</p>
        </div>
        <button onclick="openModal(0, 'Novo Produto')" class="btn-new">
            <i class="bi bi-plus-lg"></i>
            <span>Novo produto</span>
        </button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-money"><i class="bi bi-currency-dollar"></i></div>
            <div class="stat-info">
                <h3>R$ <?php echo number_format($custoTotalEstoque, 2, ',', '.'); ?></h3>
                <p>Valor total investido em estoque</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-box"><i class="bi bi-box-seam"></i></div>
            <div class="stat-info">
                <h3><?php echo $totalItens; ?> un</h3>
                <p>Quantidade total de embalagens</p>
            </div>
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="filters-section">
        <div class="filters-row">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar produtos por nome ou marca..." onkeyup="filterProducts()">
            </div>
            <div class="filter-group">
                <button class="filter-btn active" onclick="setFilter('todos')" data-filter="todos">
                    Todos (<?= count($produtos) ?>)
                </button>
                <button class="filter-btn" onclick="setFilter('baixo')" data-filter="baixo">
                    <i class="bi bi-exclamation-circle"></i> Acabando
                </button>
                <button class="filter-btn" onclick="setFilter('vencendo')" data-filter="vencendo">
                    <i class="bi bi-clock-history"></i> Vencendo
                </button>
            </div>
        </div>
    </div>

    <!-- Grid de Produtos -->
    <?php if (count($produtos) > 0): ?>
        <div class="products-grid" id="productsGrid">
            <?php foreach ($produtos as $p):
                $qtd = (float)$p['quantidade'];
                $comprometido = (float)($comprometidos[$p['id']] ?? 0);
                $disponivel = $qtd - $comprometido;

                // Status do produto
                $statusClass = 'ok';
                $alertBadge = '';
                if ($disponivel <= 0) {
                    $statusClass = 'zerado';
                    $alertClass = 'alert-low';
                    $alertBadge = '<span class="product-badge badge-low">Esgotado</span>';
                } elseif ($disponivel <= 5) {
                    $statusClass = 'baixo';
                    $alertClass = 'alert-warning';
                    $alertBadge = '<span class="product-badge badge-low">Acabando</span>';
                } else {
                    $alertClass = 'alert-ok';
                }

                // Verifica validade
                $validade = $p['data_validade'];
                $validadeText = 'Sem validade';
                $isVencendo = false;
                if ($validade) {
                    $dataVal = new DateTime($validade);
                    $hoje = new DateTime();
                    $diff = $hoje->diff($dataVal)->days;
                    $isVencido = $hoje > $dataVal;

                    if ($isVencido) {
                        $validadeText = 'Vencido';
                        $alertBadge = '<span class="product-badge badge-expired">Vencido</span>';
                        $isVencendo = true;
                    } elseif ($diff <= 30) {
                        $validadeText = 'Vence em ' . $diff . ' dias';
                        $isVencendo = true;
                        if (!$alertBadge) {
                            $alertBadge = '<span class="product-badge badge-low">Vencendo</span>';
                        }
                    } else {
                        $validadeText = date('d/m/Y', strtotime($validade));
                    }
                }
            ?>
                <div class="product-card" 
                     data-name="<?= strtolower(htmlspecialchars($p['nome'])) ?>"
                     data-brand="<?= strtolower(htmlspecialchars($p['marca'] ?? '')) ?>"
                     data-status="<?= $statusClass ?>"
                     data-vencendo="<?= $isVencendo ? '1' : '0' ?>"
                     onclick="window.location.href='<?= $produtosEstoqueUrl ?>?edit=<?= $p['id'] ?>'">
                    
                    <?= $alertBadge ?>
                    
                    <div class="product-card-header">
                        <div style="flex: 1;">
                            <div class="product-name"><?= htmlspecialchars($p['nome']) ?></div>
                            <div class="product-brand"><?= htmlspecialchars($p['marca'] ?? 'Sem marca') ?></div>
                        </div>
                        <div class="product-alert <?= $alertClass ?>"></div>
                    </div>

                    <div class="product-stats">
                        <div class="stat-item">
                            <span class="stat-value" style="color: #2563eb;"><?= $qtd ?></span>
                            <span class="stat-label">Estoque</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value" style="color: <?= $disponivel > 0 ? '#22c55e' : '#ef4444' ?>;"><?= $disponivel ?></span>
                            <span class="stat-label">Disponível</span>
                        </div>
                    </div>

                    <div style="font-size: 0.75rem; color: var(--text-muted); margin: 8px 0;">
                        <i class="bi bi-clock"></i> <?= $validadeText ?>
                    </div>

                    <div class="product-footer">
                        <div class="product-price">R$ <?= number_format($p['custo_unitario'], 2, ',', '.') ?></div>
                        <div class="product-actions">
                            <button class="action-icon" onclick="event.stopPropagation(); window.location.href='<?= $produtosEstoqueUrl ?>?edit=<?= $p['id'] ?>'">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="action-icon delete" onclick="event.stopPropagation(); confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nome'])) ?>');">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="background: white; border-radius: 16px; padding: 60px 20px; border: 1px dashed var(--border-color);">
            <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.2;"></i>
            <p style="font-size: 1rem; margin-top: 16px;">Nenhum produto cadastrado ainda</p>
            <p style="font-size: 0.85rem; margin-top: 8px;">Comece adicionando seu primeiro item de estoque</p>
        </div>
    <?php endif; ?>
</div>

<!-- Bottom sheet de detalhes do produto -->
<div class="product-sheet-overlay" id="productSheetOverlay" onclick="sheetBackdropClick(event)">
    <div class="product-sheet" id="productSheet">
        <div class="product-sheet-header">
            <div>
                <div class="product-sheet-title" data-field="nome">Nome do produto</div>
                <div class="product-sheet-sub">
                    <span data-field="qtd">0 un</span> • 
                    <span data-field="tamanho">0 ml</span>
                </div>
            </div>
            <button type="button" class="product-sheet-close-btn" onclick="closeProductSheet()">&times;</button>
        </div>

        <div class="product-sheet-body">
            <div>
                <div class="product-sheet-field-label">Marca</div>
                <div class="product-sheet-field-value" data-field="marca">-</div>
            </div>
            <div>
                <div class="product-sheet-field-label">Custo pago</div>
                <div class="product-sheet-field-value" data-field="custo">R$ 0,00</div>
            </div>
            <div>
                <div class="product-sheet-field-label">Validade</div>
                <div class="product-sheet-field-value" data-field="validade">-</div>
            </div>
            <div>
                <div class="product-sheet-field-label">Status</div>
                <div class="product-sheet-field-value" data-field="statusText">OK</div>
            </div>
            <div class="product-sheet-full">
                <div class="product-sheet-field-label">Observações</div>
                <div class="product-sheet-field-value" data-field="obs">-</div>
            </div>
        </div>

        <div class="product-sheet-actions">
            <button type="button" class="product-sheet-btn product-sheet-btn-edit" onclick="editFromSheet()">
                <i class="bi bi-pencil"></i> Editar produto
            </button>
            <button type="button" class="product-sheet-btn product-sheet-btn-delete" onclick="deleteFromSheet()">
                <i class="bi bi-trash3"></i> Remover do estoque
            </button>
        </div>
    </div>
</div>

<!-- Modal de cadastro/edição -->
<div class="modal-overlay <?php echo $produtoEdicao ? 'active' : ''; ?>" id="productModal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="modalTitle">
                    <?php echo $produtoEdicao ? 'Editar Produto' : 'Novo Produto'; ?>
                </div>
                <div class="modal-subtitle">
                    Preencha os dados básicos para controlar seu estoque.
                </div>
            </div>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" autocomplete="off">
            <input type="hidden" name="acao" value="salvar_produto">
            <input type="hidden" name="produto_id" id="produtoId" value="<?php echo $produtoEdicao['id'] ?? 0; ?>">

            <div class="form-group">
                <label>Nome do Produto</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Progressiva Orgânica" required value="<?php echo htmlspecialchars($produtoEdicao['nome'] ?? ''); ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" class="form-control" placeholder="Ex: L'Oréal" value="<?php echo htmlspecialchars($produtoEdicao['marca'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Custo Pago (total da embalagem)</label>
                    <input type="number" step="0.01" name="custo_unitario" class="form-control" placeholder="0,00" required value="<?php echo $produtoEdicao['custo_unitario'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="section-divider">
                <p class="section-title">Detalhes da Embalagem</p>
                <p class="section-helper">Informe peso/volume real da embalagem para ter o custo correto por unidade.</p>
                <div class="form-grid" style="margin-top: 8px;">
                    <div class="form-group">
                        <label>Conteúdo (Peso/Volume)</label>
                        <input type="number" step="0.01" name="tamanho_embalagem" class="form-control" placeholder="Ex: 1 ou 1000" required value="<?php echo $produtoEdicao['tamanho_embalagem'] ?? ''; ?>">
                        <small id="ajuda-unidade" style="color:#6b7280; font-size:0.7rem; display:block; margin-top:4px;">
                            Digite a quantidade exata da embalagem.
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Unidade de Medida</label>
                        <select name="unidade" id="select-unidade" class="form-control" onchange="atualizarDica()" required>
                            <option value="ml" <?php echo ($produtoEdicao['unidade'] ?? '') == 'ml' ? 'selected' : ''; ?>>Mililitros (ml)</option>
                            <option value="l"  <?php echo ($produtoEdicao['unidade'] ?? '') == 'l'  ? 'selected' : ''; ?>>Litros (l)</option>
                            <option value="g"  <?php echo ($produtoEdicao['unidade'] ?? '') == 'g'  ? 'selected' : ''; ?>>Gramas (g)</option>
                            <option value="kg" <?php echo ($produtoEdicao['unidade'] ?? '') == 'kg' ? 'selected' : ''; ?>>Quilogramas (kg)</option>
                            <option value="un" <?php echo ($produtoEdicao['unidade'] ?? '') == 'un' ? 'selected' : ''; ?>>Unidade (un)</option>
                            <option value="kit"<?php echo ($produtoEdicao['unidade'] ?? '') == 'kit'? 'selected' : ''; ?>>Kit</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Qtd de Potes/Frascos (Estoque)</label>
                    <input type="number" name="quantidade" class="form-control" placeholder="Qtd contada" required value="<?php echo $produtoEdicao['quantidade'] ?? 1; ?>">
                </div>
                <div class="form-group">
                    <label>Validade</label>
                    <input type="date" name="data_validade" class="form-control" value="<?php echo $produtoEdicao['data_validade'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" class="form-control" rows="2" placeholder="Lote, tipo de uso, fornecedor..."><?php echo htmlspecialchars($produtoEdicao['observacoes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-new btn-full">
                Salvar produto
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    // ---- Filtros e Busca ----
    let currentFilter = 'todos';

    function filterProducts() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.product-card');
        
        cards.forEach(card => {
            const name = card.dataset.name || '';
            const brand = card.dataset.brand || '';
            const status = card.dataset.status || '';
            const vencendo = card.dataset.vencendo || '0';
            
            // Filtro de busca por texto
            const matchesSearch = name.includes(searchTerm) || brand.includes(searchTerm);
            
            // Filtro por categoria
            let matchesFilter = true;
            if (currentFilter === 'baixo') {
                matchesFilter = status === 'baixo' || status === 'zerado';
            } else if (currentFilter === 'vencendo') {
                matchesFilter = vencendo === '1';
            }
            
            // Mostra ou esconde o card
            if (matchesSearch && matchesFilter) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function setFilter(filter) {
        currentFilter = filter;
        
        // Atualiza botões ativos
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.dataset.filter === filter) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        filterProducts();
    }

    function confirmDelete(id, nome, event) {
        if (event) event.stopPropagation();
        
        if (window.AppConfirm) {
            AppConfirm.open({
                title: 'Remover produto',
                message: 'Tem certeza que deseja remover <strong>' + nome + '</strong> do estoque?',
                confirmText: 'Remover',
                cancelText: 'Cancelar',
                type: 'danger',
                onConfirm: function() {
                    window.location.href = '<?= $produtosEstoqueUrl ?>?delete=' + id;
                }
            });
        } else {
            if (confirm('Excluir ' + nome + ' do estoque?')) {
                window.location.href = '<?= $produtosEstoqueUrl ?>?delete=' + id;
            }
        }
    }

    // ---- Modal de cadastro/edição ----
    function openModal(id, title) {
        const form = document.querySelector('#productModal form');
        const modal = document.getElementById('productModal');
        
        // CORREÇÃO: Limpa o formulário quando abre para novo produto
        if (id === 0) {
            if (form) form.reset();
            document.getElementById('produtoId').value = 0;
            document.getElementById('modalTitle').innerText = title;
            
            // Remove o parâmetro edit da URL se existir
            if (window.location.search.includes('edit=')) {
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                window.history.pushState({}, '', url);
            }
        } else {
            // Para edição, recarrega a página com o produto
            window.location.href = '<?= $produtosEstoqueUrl ?>?edit=' + id;
            return;
        }
        
        modal.classList.add('active');
        atualizarDica();
    }

    function closeModal() {
        document.getElementById('productModal').classList.remove('active');
        
        // Remove o parâmetro edit da URL e limpa o form
        if (window.location.search.includes('edit=')) {
            const url = new URL(window.location);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url);
        }
        
        // Limpa o formulário ao fechar
        const form = document.querySelector('#productModal form');
        if (form) form.reset();
        document.getElementById('produtoId').value = 0;
    }

    function atualizarDica() {
        const unidade = document.getElementById('select-unidade')?.value;
        const textoAjuda = document.getElementById('ajuda-unidade');
        if (!textoAjuda || !unidade) return;
        
        if (unidade === 'l') {
            textoAjuda.innerText = 'Ex: Se for 1 Litro, digite 1.';
        } else if (unidade === 'ml') {
            textoAjuda.innerText = 'Ex: Se for 1 Litro, digite 1000.';
        } else if (unidade === 'kg') {
            textoAjuda.innerText = 'Ex: Se for 1 Quilo, digite 1.';
        } else if (unidade === 'g') {
            textoAjuda.innerText = 'Ex: Se for 1 Quilo, digite 1000.';
        } else {
            textoAjuda.innerText = 'Digite a quantidade que vem na embalagem.';
        }
    }

    // ---- Bottom sheet de produto ----
    let currentProductId = null;

    function openProductSheet(rowEl) {
        const overlay = document.getElementById('productSheetOverlay');
        if (!overlay || !rowEl) return;

        currentProductId = rowEl.dataset.id;

        const nome     = rowEl.dataset.nome || '';
        const marca    = rowEl.dataset.marca || '-';
        const qtd      = rowEl.dataset.qtd || '0';
        const tamanho  = rowEl.dataset.tamanho || '0';
        const unidade  = rowEl.dataset.unidade || '';
        const custo    = rowEl.dataset.custo || '0,00';
        const validade = rowEl.dataset.validade || '-';
        const obs      = rowEl.dataset.obs || '-';
        const status   = rowEl.dataset.status || 'OK';

        overlay.querySelector('[data-field="nome"]').innerText     = nome;
        overlay.querySelector('[data-field="marca"]').innerText    = marca || '-';
        overlay.querySelector('[data-field="qtd"]').innerText      = qtd + ' un';
        overlay.querySelector('[data-field="tamanho"]').innerText  = tamanho + ' ' + unidade;
        overlay.querySelector('[data-field="custo"]').innerText    = 'R$ ' + custo;
        overlay.querySelector('[data-field="validade"]').innerText = validade || '-';
        overlay.querySelector('[data-field="obs"]').innerText      = obs || '-';
        overlay.querySelector('[data-field="statusText"]').innerText = status;

        overlay.classList.add('open');
    }

    function closeProductSheet() {
        const overlay = document.getElementById('productSheetOverlay');
        if (!overlay) return;
        overlay.classList.remove('open');
        currentProductId = null;
    }

    function sheetBackdropClick(e) {
        const overlay = document.getElementById('productSheetOverlay');
        const sheet   = document.getElementById('productSheet');
        if (!overlay || !sheet) return;
        if (e.target === overlay) {
            closeProductSheet();
        }
    }

    function editFromSheet() {
        if (!currentProductId) return;
        window.location.href = 'produtos-estoque.php?edit=' + currentProductId;
    }

    function deleteFromSheet() {
        if (!currentProductId) return;

        const overlay = document.getElementById('productSheetOverlay');
        const nomeEl  = overlay.querySelector('[data-field="nome"]');
        const nome    = nomeEl ? nomeEl.innerText : 'o produto';

        if (window.AppConfirm) {
            AppConfirm.open({
                title: 'Remover produto',
                message: 'Tem certeza que deseja remover <strong>' + nome + '</strong> do estoque?',
                confirmText: 'Remover',
                cancelText: 'Voltar',
                type: 'danger',
                onConfirm: function () {
                    window.location.href = 'produtos-estoque.php?delete=' + currentProductId;
                }
            });
        } else {
            if (confirm('Excluir ' + nome + ' do estoque?')) {
                window.location.href = 'produtos-estoque.php?delete=' + currentProductId;
            }
        }
    }

    // ---- Toasts pós-ação ----
    <?php if (isset($_GET['status'])): ?>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($_GET['status'] === 'saved'): ?>
                if (window.AppToast) AppToast.show('Produto cadastrado com sucesso!', 'success');
            <?php elseif ($_GET['status'] === 'updated'): ?>
                if (window.AppToast) AppToast.show('Produto atualizado com sucesso!', 'info');
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                if (window.AppToast) AppToast.show('Produto removido do estoque.', 'danger');
            <?php endif; ?>
        });
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', atualizarDica);
</script>
