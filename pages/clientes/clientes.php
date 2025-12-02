<?php
require_once __DIR__ . '/../../includes/config.php';
// pages/clientes/clientes.php

// =========================================================
// 1. INICIALIZAÇÃO
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// Verifica DB
if (file_exists('../../includes/db.php')) {
    include '../../includes/db.php';
} else {
    die('Erro: Arquivo de banco de dados não encontrado.');
}

// =========================================================
// 2. LÓGICA PHP (SALVAR / EXCLUIR)
// =========================================================

// A. EXCLUIR
if (isset($_GET['delete'])) {
    $idDelete = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ? AND user_id = ?");
    $stmt->execute([$idDelete, $userId]);
    // 🔹 Descobre se está em produção (salao.develoi.com) ou local
    $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
    $clientesUrl = $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php';
    header("Location: {$clientesUrl}?status=deleted");
    exit;
}

// B. SALVAR (NOVO OU EDITAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao   = $_POST['acao'] ?? 'create';
    $idEdit = $_POST['id_cliente'] ?? null;
    
    $nome       = trim($_POST['nome'] ?? '');
    $telefone   = trim($_POST['telefone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nascimento = !empty($_POST['nascimento']) ? $_POST['nascimento'] : null;
    $obs        = trim($_POST['obs'] ?? '');

    if (!empty($nome)) {
        if ($acao === 'update' && !empty($idEdit)) {
            // Atualizar
            $sql = "UPDATE clientes 
                       SET nome = ?, telefone = ?, email = ?, data_nascimento = ?, observacoes = ? 
                     WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $telefone, $email, $nascimento, $obs, $idEdit, $userId]);
            
            // Opcional: Atualizar nome nos agendamentos futuros
            $pdo->prepare("UPDATE agendamentos SET cliente_nome = ? WHERE cliente_id = ?")
                ->execute([$nome, $idEdit]);
            
            $status = 'updated';
        } else {
            // Criar
            $sql = "INSERT INTO clientes (user_id, nome, telefone, email, data_nascimento, observacoes) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $telefone, $email, $nascimento, $obs]);
            $status = 'created';
        }
        // 🔹 Descobre se está em produção (salao.develoi.com) ou local
        $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        $clientesUrl = $isProd ? '/clientes' : '/karen_site/controle-salao/pages/clientes/clientes.php';
        header("Location: {$clientesUrl}?status={$status}");
        exit;
    }
}

// =========================================================
// 3. CONSULTA DE DADOS (CLIENTES E HISTÓRICO)
// =========================================================

// 3.1 Buscar Clientes
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE user_id = ? ORDER BY nome ASC");
$stmt->execute([$userId]);
$clientes = $stmt->fetchAll();

// 3.2 Buscar Agendamentos + Preço do Serviço (JOIN)
$sqlApps = "
    SELECT 
        a.id, a.cliente_id, a.servico, a.data_agendamento, a.horario, a.valor, a.status,
        s.preco as preco_tabela
    FROM agendamentos a
    LEFT JOIN servicos s 
           ON a.servico = s.nome 
          AND a.user_id = s.user_id
    WHERE a.user_id = ? 
    ORDER BY a.data_agendamento DESC, a.horario DESC
";
$stmtApps = $pdo->prepare($sqlApps);
$stmtApps->execute([$userId]);
$todosAgendamentos = $stmtApps->fetchAll();

// 3.3 Agrupar
$historicoPorCliente = [];
foreach ($todosAgendamentos as $ag) {
    if (!empty($ag['cliente_id'])) {
        $historicoPorCliente[$ag['cliente_id']][] = $ag;
    }
}

function getInitials($name) {
    $words = explode(" ", $name);
    $acronym = "";
    foreach ($words as $w) {
        if ($w === '') continue;
        $acronym .= mb_substr($w, 0, 1);
    }
    return mb_substr(strtoupper($acronym), 0, 2);
}

// =========================================================
// 4. HEADER E MENU
// =========================================================
$pageTitle = 'Meus Clientes';
include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #6366f1;
        --primary-soft: #eef2ff;
        --primary-hover: #4f46e5;
        --bg-body: #f1f5f9;
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --border-soft: #e2e8f0;
        --radius-lg: 22px;
        --radius-md: 16px;
        --radius-pill: 999px;
        --shadow-soft: 0 14px 30px rgba(15,23,42,0.13);
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
    }

    .main-content {
        max-width: 960px;
        margin: 0 auto;
        padding: 18px 14px 110px 14px;
        box-sizing: border-box;
    }

    @media (min-width: 768px) {
        .main-content {
            padding-inline: 20px;
        }
    }

    /* HEADER "APP" */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }
    .page-title-wrap {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .page-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text-dark);
        letter-spacing: -0.01em;
    }
    .page-subtitle {
        margin: 0;
        font-size: 0.78rem;
        color: var(--text-gray);
    }
    .page-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .btn-chip {
        border-radius: var(--radius-pill);
        border: none;
        padding: 7px 14px;
        font-size: 0.78rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        background: var(--primary);
        color: #fff;
        box-shadow: 0 10px 22px rgba(99,102,241,0.35);
        transition: 0.15s;
        white-space: nowrap;
    }
    .btn-chip i { font-size: 0.9rem; }
    .btn-chip:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }
    .btn-chip:active {
        transform: translateY(1px) scale(0.97);
    }

    /* --- Busca sticky estilo app --- */
    .search-wrapper {
        position: sticky;
        top: 56px;
        z-index: 80;
        padding: 10px 0 12px 0;
        backdrop-filter: blur(10px);
        margin-bottom: 6px;
    }

    .search-box {
        position: relative;
    }

    .search-input {
        width: 100%;
        padding: 10px 12px 10px 38px;
        border-radius: var(--radius-pill);
        border: 1px solid var(--border-soft);
        background: #ffffff;
        font-size: 0.82rem;
        outline: none;
        transition: 0.2s;
        box-sizing: border-box;
        color: var(--text-dark);
        box-shadow: 0 8px 18px rgba(15,23,42,0.05);
    }
    .search-input::placeholder {
        color: #9ca3af;
    }
    .search-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(99,102,241,0.18);
    }
    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.95rem;
    }

    /* --- Lista Clientes --- */
    .client-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .client-card {
        background: #ffffff;
        border-radius: var(--radius-lg);
        padding: 10px 12px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 10px;
        border: 1px solid #f8fafc;
        transition: transform 0.12s, box-shadow 0.12s, background 0.12s;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(15,23,42,0.06);
    }
    .client-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-soft);
    }
    .client-card:active {
        background-color: #f8fafc;
        transform: scale(0.98);
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 16px;
        background: var(--primary-soft);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .info {
        display: flex;
        flex-direction: column;
        gap: 1px;
        overflow: hidden;
    }
    .name {
        font-weight: 700;
        color: var(--text-dark);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }
    .meta {
        font-size: 0.78rem;
        color: var(--text-gray);
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .actions {
        display: flex;
        gap: 6px;
        z-index: 2;
    }
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 999px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.18s, transform 0.12s;
        text-decoration: none;
    }
    .btn-icon:active {
        transform: scale(0.93);
    }
    .btn-whats {
        background: #dcfce7;
        color: #15803d;
    }
    .btn-edit {
        background: #eff4ff;
        color: #4b5563;
    }
    .btn-del {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* FAB estilo app */
    .fab-add {
        position: fixed;
        bottom: 22px;
        right: 20px;
        width: 56px;
        height: 56px;
        background: var(--primary);
        color: white;
        border-radius: 20px;
        border: none;
        font-size: 1.8rem;
        box-shadow: 0 14px 28px rgba(99,102,241,0.55);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 90;
    }
    .fab-add:active {
        transform: scale(0.96);
    }

    @media (min-width: 768px) {
        .fab-add {
            bottom: 28px;
            right: 28px;
        }
    }

    /* --- Modais / Bottom Sheet --- */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.45);
        backdrop-filter: blur(4px);
        z-index: 2000;
        justify-content: center;
        align-items: flex-end;
        opacity: 0;
        transition: opacity 0.25s;
    }
    .modal-overlay.active {
        display: flex;
        opacity: 1;
    }

    .sheet-box {
        background: #ffffff;
        width: 100%;
        max-width: 500px;
        border-radius: 24px 24px 0 0;
        padding: 18px 18px 22px 18px;
        transform: translateY(100%);
        transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
        max-height: 86vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        box-shadow: 0 -14px 30px rgba(15,23,42,0.4);
    }
    .modal-overlay.active .sheet-box {
        transform: translateY(0);
    }

    .drag-handle {
        width: 38px;
        height: 4px;
        background: #e2e8f0;
        margin: 0 auto 16px auto;
        border-radius: 999px;
        flex-shrink: 0;
    }

    @media (min-width: 768px) {
        .modal-overlay {
            align-items: center;
        }
        .sheet-box {
            border-radius: 24px;
            transform: scale(0.94);
            max-height: 88vh;
        }
        .modal-overlay.active .sheet-box {
            transform: scale(1);
        }
    }

    /* Perfil View Styles */
    .view-header {
        text-align: center;
        margin-bottom: 18px;
    }
    .view-avatar {
        width: 62px;
        height: 62px;
        background: var(--primary-soft);
        color: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0 auto 8px auto;
    }
    .view-name {
        font-size: 1.02rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 4px;
    }
    .view-phone {
        color: var(--text-gray);
        font-size: 0.82rem;
    }

    .stats-row {
        display: flex;
        gap: 8px;
        margin-bottom: 18px;
    }
    .stat-card {
        flex: 1;
        background: #f8fafc;
        padding: 10px;
        border-radius: 16px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
    .stat-val {
        display: block;
        font-weight: 800;
        font-size: 1rem;
        color: var(--text-dark);
    }
    .stat-label {
        font-size: 0.7rem;
        color: var(--text-gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .history-section {
        margin-top: 8px;
    }
    .history-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .history-title i {
        font-size: 0.9rem;
    }
    .history-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .history-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 10px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #f1f5f9;
    }
    .h-date {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-dark);
        display: block;
    }
    .h-serv {
        font-size: 0.8rem;
        color: var(--text-gray);
    }
    .h-badge {
        font-size: 0.72rem;
        padding: 2px 8px;
        border-radius: 999px;
        font-weight: 600;
        display: inline-block;
    }
    .status-Confirmado { background: #dcfce7; color: #15803d; }
    .status-Pendente   { background: #fef3c7; color: #b45309; }
    .status-Cancelado  { background: #fee2e2; color: #b91c1c; }

    /* Forms */
    .form-group { 
        margin-bottom: 12px; 
        text-align: left; 
    }
    .label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 4px;
    }
    .input {
        width: 100%;
        padding: 9px 11px;
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        font-size: 0.85rem;
        box-sizing: border-box;
        background: #f8fafc;
        transition: 0.15s;
    }
    .input:focus {
        outline: none;
        border-color: var(--primary);
        background: #ffffff;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.12);
    }
    textarea.input {
        resize: vertical;
        min-height: 60px;
    }

    .btn-block {
        width: 100%;
        padding: 11px 12px;
        border-radius: 14px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        font-size: 0.85rem;
        margin-top: 6px;
    }
    .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: 0 10px 24px rgba(99,102,241,0.35);
    }
    .btn-danger {
        background: var(--danger);
        color: white;
    }
    .btn-secondary {
        background: #f3f4f6;
        color: var(--text-dark);
    }

    /* Modal delete */
    .alert-box {
        background: #ffffff;
        width: 70%;
        max-width: 340px;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        margin: auto;
        box-shadow: 0 18px 32px rgba(15,23,42,0.35);
    }

    @media (min-width: 768px) {
        .alert-box {
            border-radius: 22px;
        }
    }

    @keyframes slideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }
</style>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-wrap">
            <h1 class="page-title">Meus Clientes</h1>
            <p class="page-subtitle">Veja seus clientes, histórico e contatos rápido.</p>
        </div>
        <div class="page-actions">
            <button class="btn-chip" onclick="abrirModalCreate()">
                <i class="bi bi-person-plus"></i>
                Novo
            </button>
        </div>
    </div>
    
    <div class="search-wrapper">
        <div class="search-box">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar cliente por nome ou telefone..." onkeyup="filtrarClientes()">
        </div>
    </div>

    <div class="client-list" id="clientList">
        <?php if(count($clientes) > 0): ?>
            <?php foreach ($clientes as $c): 
                $meuHistorico  = $historicoPorCliente[$c['id']] ?? [];
                $jsonHistorico = htmlspecialchars(json_encode($meuHistorico), ENT_QUOTES, 'UTF-8');
                $jsonCliente   = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="client-card" 
                     data-nome="<?php echo strtolower($c['nome']); ?>" 
                     data-tel="<?php echo str_replace(['(',')','-',' '], '', $c['telefone'] ?? ''); ?>"
                     onclick="abrirModalView(<?php echo $jsonCliente; ?>, <?php echo $jsonHistorico; ?>)">
                    
                    <div class="avatar">
                        <?php echo getInitials($c['nome']); ?>
                    </div>
                    
                    <div class="info">
                        <div class="name"><?php echo htmlspecialchars($c['nome']); ?></div>
                        <div class="meta">
                            <?php if(!empty($c['telefone'])): ?>
                                <span><?php echo htmlspecialchars($c['telefone']); ?></span>
                            <?php else: ?>
                                <span style="opacity:0.5;">Sem telefone</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="actions">
                        <?php if(!empty($c['telefone'])): 
                            $num = preg_replace('/[^0-9]/', '', $c['telefone']);
                        ?>
                            <a href="https://wa.me/55<?php echo $num; ?>" target="_blank" class="btn-icon btn-whats" onclick="event.stopPropagation()">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                        <?php endif; ?>

                        <a href="recorrencias.php?cliente_id=<?php echo $c['id']; ?>" class="btn-icon" style="background:#dbeafe; color:#1e40af;" onclick="event.stopPropagation()" title="Agendamentos Recorrentes">
                            <i class="bi bi-arrow-repeat"></i>
                        </a>
                        
                        <button class="btn-icon btn-edit" onclick="event.stopPropagation(); abrirModalEdit(<?php echo $jsonCliente; ?>)">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        
                        <button class="btn-icon btn-del" onclick="event.stopPropagation(); abrirModalDelete(<?php echo $c['id']; ?>, '<?php echo $c['nome']; ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding: 40px 12px; color: #94a3b8;">
                <i class="bi bi-people" style="font-size:3rem; opacity:0.3; display:block; margin-bottom:8px;"></i>
                <p style="font-size:0.9rem;">Nenhum cliente cadastrado ainda.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<button class="fab-add" onclick="abrirModalCreate()">
    <i class="bi bi-plus"></i>
</button>

<!-- MODAL VISUALIZAÇÃO -->
<div class="modal-overlay" id="modalView">
    <div class="sheet-box" style="background: #f8fafc;">
        <div class="drag-handle"></div>
        
        <div class="view-header">
            <div class="view-avatar" id="viewAvatar">KG</div>
            <div class="view-name" id="viewName">Nome</div>
            <div class="view-phone" id="viewPhone">Telefone</div>
            <div style="margin-top:5px; font-size:0.78rem; color:#64748b;" id="viewObs"></div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <span class="stat-val" id="viewTotalVisitas">0</span>
                <span class="stat-label">Visitas</span>
            </div>
            <div class="stat-card">
                <span class="stat-val" style="color:var(--success);" id="viewTotalGasto">R$ 0</span>
                <span class="stat-label">Total Gasto</span>
            </div>
        </div>

        <div style="flex:1; overflow-y:auto; padding-right:4px;">
            <div class="history-section" id="secProximos" style="display:none;">
                <div class="history-title" style="color:var(--primary);">
                    <i class="bi bi-calendar-check"></i> Próximos agendamentos
                </div>
                <ul class="history-list" id="listProximos"></ul>
            </div>

            <div class="history-section" style="margin-top:14px;">
                <div class="history-title">
                    <i class="bi bi-clock-history"></i> Histórico recente
                </div>
                <ul class="history-list" id="listHistorico"></ul>
                <div id="emptyHistory" style="text-align:center; padding:16px 6px; font-size:0.78rem; color:#94a3b8; display:none;">
                    Nenhum histórico encontrado.
                </div>
            </div>
        </div>
        
        <button class="btn-block btn-secondary" onclick="fecharModais()">Fechar</button>
    </div>
</div>

<!-- MODAL FORM -->
<div class="modal-overlay" id="modalForm">
    <div class="sheet-box">
        <div class="drag-handle"></div>
        <h3 id="formTitle" style="margin:0 0 14px 0; color:var(--text-dark); font-size:0.98rem;">Novo Cliente</h3>
        <form method="POST">
            <input type="hidden" name="acao" id="inputAcao" value="create">
            <input type="hidden" name="id_cliente" id="inputId" value="">
            <div class="form-group">
                <label class="label">Nome</label>
                <input type="text" name="nome" id="inputNome" class="input" placeholder="Ex: Maria Silva" required>
            </div>
            <div class="form-group">
                <label class="label">WhatsApp</label>
                <input type="tel" name="telefone" id="inputTelefone" class="input" placeholder="(11) 99999-9999" onkeyup="mascaraTelefone(this)">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label class="label">Nascimento</label>
                    <input type="date" name="nascimento" id="inputNascimento" class="input">
                </div>
                <div class="form-group">
                    <label class="label">Email</label>
                    <input type="email" name="email" id="inputEmail" class="input" placeholder="Opcional">
                </div>
            </div>
            <div class="form-group">
                <label class="label">Observações</label>
                <textarea name="obs" id="inputObs" class="input" rows="3" placeholder="Anotações importantes sobre este cliente..."></textarea>
            </div>
            <button type="submit" class="btn-block btn-primary" id="btnSalvar">Salvar Cliente</button>
        </form>
    </div>
</div>

<!-- MODAL DELETE -->
<div class="modal-overlay" id="modalDelete">
    <div class="alert-box">
        <div style="font-size:2.4rem; margin-bottom:8px;">🗑️</div>
        <h3 style="margin:0 0 8px 0; color:var(--text-dark); font-size:0.98rem;">Excluir Cliente?</h3>
        <p id="deleteMsg" style="color:var(--text-gray); margin-bottom:16px; font-size:0.8rem;">Tem certeza?</p>
        <div style="display:flex; gap:8px;">
            <button class="btn-block btn-secondary" onclick="fecharModais()">Cancelar</button>
            <button class="btn-block btn-danger" id="btnConfirmDelete">Excluir</button>
        </div>
    </div>
</div>

<script>
    const modalForm   = document.getElementById('modalForm');
    const modalDelete = document.getElementById('modalDelete');
    const modalView   = document.getElementById('modalView');
    let idParaExcluir = null;

    function fecharModais() {
        modalForm.classList.remove('active');
        modalDelete.classList.remove('active');
        modalView.classList.remove('active');
    }

    window.onclick = function(e) {
        if (e.target == modalForm || e.target == modalDelete || e.target == modalView) fecharModais();
    }

    // VISUALIZAÇÃO COM CORREÇÃO DE VALOR
    function abrirModalView(cliente, historico) {
        document.getElementById('viewName').innerText = cliente.nome;
        document.getElementById('viewPhone').innerText = cliente.telefone || 'Sem telefone';
        document.getElementById('viewObs').innerText   = cliente.observacoes || '';
        let initials = cliente.nome.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase();
        document.getElementById('viewAvatar').innerText = initials;

        let totalGasto   = 0;
        let totalVisitas = 0;
        let hoje         = new Date().toISOString().split('T')[0];
        let proximosHtml = '';
        let historicoHtml= '';

        if (historico && historico.length > 0) {
            historico.forEach(ag => {
                let valorReal = parseFloat(ag.valor || 0);
                if (valorReal === 0 && ag.preco_tabela) {
                    valorReal = parseFloat(ag.preco_tabela);
                }

                if (ag.status !== 'Cancelado') {
                    totalGasto   += valorReal;
                    totalVisitas++;
                }

                let dataFormatada = ag.data_agendamento.split('-').reverse().join('/');
                let horaCurta     = ag.horario.substring(0, 5);
                
                let itemHtml = `
                    <li class="history-item" style="border-left: 4px solid ${getColorStatus(ag.status)}">
                        <div>
                            <span class="h-date">${dataFormatada} às ${horaCurta}</span>
                            <span class="h-serv">${ag.servico}</span>
                        </div>
                        <div style="text-align:right;">
                            <span class="h-badge status-${ag.status}">${ag.status}</span>
                            <div style="font-size:0.75rem; font-weight:700; color:#64748b; margin-top:2px;">R$ ${valorReal.toFixed(2)}</div>
                        </div>
                    </li>
                `;

                if (ag.data_agendamento >= hoje && ag.status !== 'Cancelado' && ag.status !== 'Concluido') {
                    proximosHtml += itemHtml;
                } else {
                    historicoHtml += itemHtml;
                }
            });
        }

        document.getElementById('viewTotalVisitas').innerText = totalVisitas;
        document.getElementById('viewTotalGasto').innerText   = 'R$ ' + totalGasto.toLocaleString('pt-BR', {minimumFractionDigits: 2});

        const ulProx  = document.getElementById('listProximos');
        const divProx = document.getElementById('secProximos');
        if (proximosHtml) {
            ulProx.innerHTML    = proximosHtml;
            divProx.style.display = 'block';
        } else {
            ulProx.innerHTML    = '';
            divProx.style.display = 'none';
        }

        const ulHist  = document.getElementById('listHistorico');
        const divEmpty= document.getElementById('emptyHistory');
        if (historicoHtml) {
            ulHist.innerHTML    = historicoHtml;
            divEmpty.style.display = 'none';
        } else {
            ulHist.innerHTML    = '';
            divEmpty.style.display = 'block';
            if (proximosHtml === '') divEmpty.innerText = "Este cliente ainda não tem agendamentos.";
        }

        modalView.classList.add('active');
    }

    function getColorStatus(status) {
        if (status === 'Confirmado') return '#10b981';
        if (status === 'Pendente')   return '#f59e0b';
        if (status === 'Cancelado')  return '#ef4444';
        return '#cbd5e1';
    }

    // CRUD
    function abrirModalCreate() {
        document.getElementById('inputAcao').value        = 'create';
        document.getElementById('inputId').value          = '';
        document.getElementById('inputNome').value        = '';
        document.getElementById('inputTelefone').value    = '';
        document.getElementById('inputEmail').value       = '';
        document.getElementById('inputNascimento').value  = '';
        document.getElementById('inputObs').value         = '';
        document.getElementById('formTitle').innerText    = 'Novo Cliente';
        document.getElementById('btnSalvar').innerText    = 'Salvar Cliente';
        modalForm.classList.add('active');
    }

    function abrirModalEdit(c) {
        document.getElementById('inputAcao').value        = 'update';
        document.getElementById('inputId').value          = c.id;
        document.getElementById('inputNome').value        = c.nome;
        document.getElementById('inputTelefone').value    = c.telefone;
        document.getElementById('inputEmail').value       = c.email;
        document.getElementById('inputNascimento').value  = c.data_nascimento;
        document.getElementById('inputObs').value         = c.observacoes;
        document.getElementById('formTitle').innerText    = 'Editar Cliente';
        document.getElementById('btnSalvar').innerText    = 'Salvar Alterações';
        modalForm.classList.add('active');
    }

    function abrirModalDelete(id, nome) {
        idParaExcluir = id;
        document.getElementById('deleteMsg').innerText = `Deseja remover ${nome}?`;
        modalDelete.classList.add('active');
    }

    document.getElementById('btnConfirmDelete').onclick = function() {
        if (idParaExcluir) window.location.href = `clientes.php?delete=${idParaExcluir}`;
    };

    function filtrarClientes() {
        let termo = document.getElementById('searchInput').value.toLowerCase();
        let cards = document.querySelectorAll('.client-card');
        cards.forEach(card => {
            let nome = card.getAttribute('data-nome') || '';
            let tel  = card.getAttribute('data-tel') || '';
            card.style.display = (nome.includes(termo) || tel.includes(termo)) ? 'grid' : 'none';
        });
    }

    function mascaraTelefone(input) {
        let v = input.value.replace(/\D/g, "");
        v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
        v = v.replace(/(\d)(\d{4})$/, "$1-$2");
        input.value = v;
    }

    <?php if(isset($_GET['status'])): ?>
    window.onload = function() {
        let msg    = '';
        let status = "<?php echo $_GET['status']; ?>";
        if (status === 'created') msg = 'Cliente cadastrado!';
        if (status === 'updated') msg = 'Dados atualizados!';
        if (status === 'deleted') msg = 'Cliente removido.';

        if (msg) {
            let toast = document.createElement('div');
            toast.style.cssText = "position:fixed; top:18px; right:18px; background:#10b981; color:white; padding:8px 18px; border-radius:999px; font-size:0.8rem; font-weight:600; box-shadow:0 6px 18px rgba(0,0,0,0.2); z-index:3000; animation: slideIn 0.25s;";
            toast.innerText = msg;
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 2600);
        }
    }
    <?php endif; ?>
</script>
