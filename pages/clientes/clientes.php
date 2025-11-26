<?php
// pages/clientes/clientes.php

// =========================================================
// 1. INICIALIZAÇÃO
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
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
    header("Location: clientes.php?status=deleted");
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
            $sql = "UPDATE clientes SET nome = ?, telefone = ?, email = ?, data_nascimento = ?, observacoes = ? WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $telefone, $email, $nascimento, $obs, $idEdit, $userId]);
            
            // Opcional: Atualizar nome nos agendamentos futuros
            $pdo->prepare("UPDATE agendamentos SET cliente_nome = ? WHERE cliente_id = ?")->execute([$nome, $idEdit]);
            
            $status = 'updated';
        } else {
            // Criar
            $sql = "INSERT INTO clientes (user_id, nome, telefone, email, data_nascimento, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $telefone, $email, $nascimento, $obs]);
            $status = 'created';
        }
        header("Location: clientes.php?status={$status}");
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
// O truque aqui: Buscamos o valor gravado no agendamento (a.valor) 
// E TAMBÉM o preço atual do serviço (s.preco) caso precise.
$sqlApps = "
    SELECT 
        a.id, a.cliente_id, a.servico, a.data_agendamento, a.horario, a.valor, a.status,
        s.preco as preco_tabela
    FROM agendamentos a
    LEFT JOIN servicos s ON a.servico = s.nome AND a.user_id = s.user_id
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
        --bg-body: #f1f5f9;
        --text-dark: #1e293b;
        --text-gray: #64748b;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', sans-serif;
    }

    .main-content {
        max-width: 600px;
        margin: 0 auto;
        padding: 0 16px 120px 16px;
    }

    /* --- Busca --- */
    .search-wrapper {
        position: sticky; top: 60px; z-index: 80;
        background: rgba(241, 245, 249, 0.9); backdrop-filter: blur(8px);
        padding: 12px 0; margin-bottom: 8px;
    }
    .search-input {
        width: 100%; padding: 14px 14px 14px 45px;
        border: 1px solid #e2e8f0; border-radius: 16px; background: white;
        font-size: 1rem; outline: none; transition: 0.2s; box-sizing: border-box;
    }
    .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
    .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; }

    /* --- Lista Clientes --- */
    .client-list { display: flex; flex-direction: column; gap: 12px; }

    .client-card {
        background: white; border-radius: 20px; padding: 16px;
        display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 14px;
        border: 1px solid #f8fafc; transition: transform 0.1s; cursor: pointer;
        position: relative; overflow: hidden;
    }
    .client-card:active { background-color: #f8fafc; }

    .avatar {
        width: 48px; height: 48px; border-radius: 14px; background: #eef2ff; color: var(--primary);
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem;
    }

    .info { display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
    .name { font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .meta { font-size: 0.85rem; color: var(--text-gray); display: flex; gap: 8px; align-items: center; }

    /* Ações */
    .actions { display: flex; gap: 8px; z-index: 2; }
    .btn-icon {
        width: 40px; height: 40px; border-radius: 12px; border: none;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        cursor: pointer; transition: background 0.2s; text-decoration: none;
    }
    .btn-whats { background: #dcfce7; color: #15803d; }
    .btn-edit { background: #f1f5f9; color: var(--text-gray); }
    .btn-del { background: #fef2f2; color: #ef4444; }

    /* FAB */
    .fab-add {
        position: fixed; bottom: 24px; right: 24px; width: 60px; height: 60px;
        background: var(--primary); color: white; border-radius: 20px; border: none;
        font-size: 2rem; box-shadow: 0 10px 25px rgba(99,102,241,0.4); cursor: pointer;
        display: flex; align-items: center; justify-content: center; z-index: 90;
    }

    /* --- Modais --- */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
        backdrop-filter: blur(2px); z-index: 2000; justify-content: center;
        align-items: flex-end; opacity: 0; transition: opacity 0.3s;
    }
    .modal-overlay.active { display: flex; opacity: 1; }

    .sheet-box {
        background: white; width: 100%; max-width: 500px; border-radius: 24px 24px 0 0;
        padding: 24px; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        max-height: 85vh; overflow-y: auto; display: flex; flex-direction: column;
    }
    .modal-overlay.active .sheet-box { transform: translateY(0); }

    /* Perfil View Styles */
    .view-header { text-align: center; margin-bottom: 20px; }
    .view-avatar { 
        width: 70px; height: 70px; background: #eef2ff; color: var(--primary); 
        border-radius: 50%; display: flex; align-items: center; justify-content: center; 
        font-size: 1.8rem; font-weight: 700; margin: 0 auto 10px auto; 
    }
    .view-name { font-size: 1.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
    .view-phone { color: var(--text-gray); font-size: 0.95rem; }

    .stats-row { display: flex; gap: 10px; margin-bottom: 24px; }
    .stat-card { 
        flex: 1; background: #f8fafc; padding: 12px; border-radius: 16px; text-align: center; border: 1px solid #e2e8f0; 
    }
    .stat-val { display: block; font-weight: 800; font-size: 1.1rem; color: var(--text-dark); }
    .stat-label { font-size: 0.75rem; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.5px; }

    .history-section { margin-top: 10px; }
    .history-title { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .history-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
    
    .history-item { 
        display: flex; align-items: center; justify-content: space-between; 
        padding: 12px; border-radius: 12px; background: white; border: 1px solid #f1f5f9; 
    }
    .h-date { font-weight: 700; font-size: 0.9rem; color: var(--text-dark); display: block; }
    .h-serv { font-size: 0.85rem; color: var(--text-gray); }
    .h-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 6px; font-weight: 600; }
    
    .status-Confirmado { background: #dcfce7; color: #15803d; }
    .status-Pendente { background: #fef3c7; color: #b45309; }
    .status-Cancelado { background: #fee2e2; color: #b91c1c; }

    /* Forms */
    .drag-handle { width: 40px; height: 4px; background: #e2e8f0; margin: 0 auto 20px auto; border-radius: 10px; flex-shrink: 0; }
    .form-group { margin-bottom: 16px; text-align: left; }
    .label { display: block; font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 6px; }
    .input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; box-sizing: border-box; background: #f8fafc; }
    .btn-block { width: 100%; padding: 14px; border-radius: 14px; font-weight: 700; border: none; cursor: pointer; font-size: 1rem; margin-top: 10px; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-danger { background: var(--danger); color: white; }
    .btn-secondary { background: #f1f5f9; color: var(--text-dark); }

    @media (min-width: 768px) {
        .modal-overlay { align-items: center; }
        .sheet-box { border-radius: 24px; transform: scale(0.95); opacity: 0; width: 450px; }
        .modal-overlay.active .sheet-box { transform: scale(1); opacity: 1; }
    }
</style>

<main class="main-content">
    
    <div class="search-wrapper">
        <div class="search-box">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar cliente..." onkeyup="filtrarClientes()">
        </div>
    </div>

    <div class="client-list" id="clientList">
        <?php if(count($clientes) > 0): ?>
            <?php foreach ($clientes as $c): 
                // Prepara JSON
                $meuHistorico = $historicoPorCliente[$c['id']] ?? [];
                $jsonHistorico = htmlspecialchars(json_encode($meuHistorico), ENT_QUOTES, 'UTF-8');
                $jsonCliente = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
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
                                <span style="opacity:0.5;">S/ Telefone</span>
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
            <div style="text-align:center; padding: 40px; color: #94a3b8;">
                <i class="bi bi-people" style="font-size:3rem; opacity:0.3;"></i>
                <p>Nenhum cliente encontrado.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<button class="fab-add" onclick="abrirModalCreate()">
    <i class="bi bi-plus"></i>
</button>

<div class="modal-overlay" id="modalView">
    <div class="sheet-box" style="background: #f8fafc;">
        <div class="drag-handle"></div>
        
        <div class="view-header">
            <div class="view-avatar" id="viewAvatar">KG</div>
            <div class="view-name" id="viewName">Nome</div>
            <div class="view-phone" id="viewPhone">Telefone</div>
            <div style="margin-top:5px; font-size:0.85rem; color:#64748b;" id="viewObs"></div>
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

        <div style="flex:1; overflow-y:auto; padding-right:5px;">
            <div class="history-section" id="secProximos" style="display:none;">
                <div class="history-title" style="color:var(--primary);">
                    <i class="bi bi-calendar-check"></i> Próximos Agendamentos
                </div>
                <ul class="history-list" id="listProximos"></ul>
            </div>

            <div class="history-section" style="margin-top:20px;">
                <div class="history-title">
                    <i class="bi bi-clock-history"></i> Histórico Recente
                </div>
                <ul class="history-list" id="listHistorico"></ul>
                <div id="emptyHistory" style="text-align:center; padding:20px; font-size:0.85rem; color:#94a3b8; display:none;">
                    Nenhum histórico encontrado.
                </div>
            </div>
        </div>
        
        <button class="btn-block btn-secondary" onclick="fecharModais()">Fechar</button>
    </div>
</div>

<div class="modal-overlay" id="modalForm">
    <div class="sheet-box">
        <div class="drag-handle"></div>
        <h3 id="formTitle" style="margin:0 0 20px 0; color:var(--text-dark);">Novo Cliente</h3>
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
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
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
                <textarea name="obs" id="inputObs" class="input" rows="3" placeholder="Anotações..."></textarea>
            </div>
            <button type="submit" class="btn-block btn-primary" id="btnSalvar">Salvar Cliente</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalDelete">
    <div class="alert-box" style="background:white; width:90%; max-width:350px; border-radius:20px; padding:24px; text-align:center; margin:auto;">
        <div style="font-size:3rem; margin-bottom:10px;">🗑️</div>
        <h3 style="margin:0 0 10px 0; color:var(--text-dark);">Excluir Cliente?</h3>
        <p id="deleteMsg" style="color:var(--text-gray); margin-bottom:20px;">Tem certeza?</p>
        <div style="display:flex; gap:10px;">
            <button class="btn-block btn-secondary" onclick="fecharModais()">Cancelar</button>
            <button class="btn-block btn-danger" id="btnConfirmDelete">Excluir</button>
        </div>
    </div>
</div>

<script>
    const modalForm = document.getElementById('modalForm');
    const modalDelete = document.getElementById('modalDelete');
    const modalView = document.getElementById('modalView');
    let idParaExcluir = null;

    function fecharModais() {
        modalForm.classList.remove('active');
        modalDelete.classList.remove('active');
        modalView.classList.remove('active');
    }

    window.onclick = function(e) {
        if (e.target == modalForm || e.target == modalDelete || e.target == modalView) fecharModais();
    }

    // --- VISUALIZAÇÃO COM CORREÇÃO DE VALOR ---
    function abrirModalView(cliente, historico) {
        document.getElementById('viewName').innerText = cliente.nome;
        document.getElementById('viewPhone').innerText = cliente.telefone || 'Sem telefone';
        document.getElementById('viewObs').innerText = cliente.observacoes || '';
        let initials = cliente.nome.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
        document.getElementById('viewAvatar').innerText = initials;

        let totalGasto = 0;
        let totalVisitas = 0;
        let hoje = new Date().toISOString().split('T')[0];
        let proximosHtml = '';
        let historicoHtml = '';

        if (historico && historico.length > 0) {
            historico.forEach(ag => {
                // LÓGICA DO VALOR: Se valor gravado for 0, pega da tabela de serviços (preco_tabela)
                let valorReal = parseFloat(ag.valor || 0);
                if (valorReal === 0 && ag.preco_tabela) {
                    valorReal = parseFloat(ag.preco_tabela);
                }

                if (ag.status !== 'Cancelado') {
                    totalGasto += valorReal;
                    totalVisitas++;
                }

                let dataFormatada = ag.data_agendamento.split('-').reverse().join('/');
                let horaCurta = ag.horario.substring(0, 5);
                
                let itemHtml = `
                    <li class="history-item" style="border-left: 4px solid ${getColorStatus(ag.status)}">
                        <div>
                            <span class="h-date">${dataFormatada} às ${horaCurta}</span>
                            <span class="h-serv">${ag.servico}</span>
                        </div>
                        <div style="text-align:right;">
                            <span class="h-badge status-${ag.status}">${ag.status}</span>
                            <div style="font-size:0.8rem; font-weight:700; color:#64748b; margin-top:2px;">R$ ${valorReal.toFixed(2)}</div>
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
        document.getElementById('viewTotalGasto').innerText = 'R$ ' + totalGasto.toLocaleString('pt-BR', {minimumFractionDigits: 2});

        const ulProx = document.getElementById('listProximos');
        const divProx = document.getElementById('secProximos');
        if (proximosHtml) {
            ulProx.innerHTML = proximosHtml;
            divProx.style.display = 'block';
        } else {
            divProx.style.display = 'none';
        }

        const ulHist = document.getElementById('listHistorico');
        const divEmpty = document.getElementById('emptyHistory');
        if (historicoHtml) {
            ulHist.innerHTML = historicoHtml;
            divEmpty.style.display = 'none';
        } else {
            ulHist.innerHTML = '';
            divEmpty.style.display = 'block';
            if(proximosHtml === '') divEmpty.innerText = "Este cliente ainda não tem agendamentos.";
        }

        modalView.classList.add('active');
    }

    function getColorStatus(status) {
        if(status === 'Confirmado') return '#10b981';
        if(status === 'Pendente') return '#f59e0b';
        if(status === 'Cancelado') return '#ef4444';
        return '#cbd5e1';
    }

    // --- CRUD ---
    function abrirModalCreate() {
        document.getElementById('inputAcao').value = 'create';
        document.getElementById('inputId').value = '';
        document.getElementById('inputNome').value = '';
        document.getElementById('inputTelefone').value = '';
        document.getElementById('inputEmail').value = '';
        document.getElementById('inputNascimento').value = '';
        document.getElementById('inputObs').value = '';
        document.getElementById('formTitle').innerText = 'Novo Cliente';
        document.getElementById('btnSalvar').innerText = 'Cadastrar';
        modalForm.classList.add('active');
    }

    function abrirModalEdit(c) {
        document.getElementById('inputAcao').value = 'update';
        document.getElementById('inputId').value = c.id;
        document.getElementById('inputNome').value = c.nome;
        document.getElementById('inputTelefone').value = c.telefone;
        document.getElementById('inputEmail').value = c.email;
        document.getElementById('inputNascimento').value = c.data_nascimento;
        document.getElementById('inputObs').value = c.observacoes;
        document.getElementById('formTitle').innerText = 'Editar Cliente';
        document.getElementById('btnSalvar').innerText = 'Salvar Alterações';
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
        let msg = '';
        let status = "<?php echo $_GET['status']; ?>";
        if(status === 'created') msg = 'Cliente cadastrado!';
        if(status === 'updated') msg = 'Dados atualizados!';
        if(status === 'deleted') msg = 'Cliente removido.';
        if(msg) {
            let toast = document.createElement('div');
            toast.style.cssText = "position:fixed; top:20px; right:20px; background:#10b981; color:white; padding:12px 24px; border-radius:50px; box-shadow:0 5px 15px rgba(0,0,0,0.2); z-index:3000; animation: slideIn 0.3s;";
            toast.innerText = msg;
            document.body.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 3000);
        }
    }
    <?php endif; ?>
</script>

<style>
@keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>