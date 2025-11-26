<?php
$pageTitle = 'Meus Clientes';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Simulação de User ID
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --------------- LÓGICA PHP ---------------

// 1. EXCLUIR
if (isset($_GET['delete'])) {
    $idDelete = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ? AND user_id = ?");
    $stmt->execute([$idDelete, $userId]);

    echo "<script>window.location.href='clientes.php?status=deleted';</script>";
    exit;
}

// 2. SALVAR (CRIAR OU EDITAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao   = $_POST['acao'] ?? 'create';
    $idEdit = $_POST['id_cliente'] ?? null;
    
    $nome       = trim($_POST['nome'] ?? '');
    $telefone   = trim($_POST['telefone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nascimento = $_POST['nascimento'] ?? null;
    $obs        = trim($_POST['obs'] ?? '');

    if (!empty($nome)) {
        if ($acao === 'update' && !empty($idEdit)) {
            $sql  = "UPDATE clientes 
                        SET nome = ?, telefone = ?, email = ?, data_nascimento = ?, observacoes = ? 
                      WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $telefone, $email, $nascimento, $obs, $idEdit, $userId]);
            $status = 'updated';
        } else {
            $sql  = "INSERT INTO clientes (user_id, nome, telefone, email, data_nascimento, observacoes) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $telefone, $email, $nascimento, $obs]);
            $status = 'created';
        }

        header("Location: clientes.php?status={$status}");
        exit;
    }
}

// 3. BUSCAR CLIENTES
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE user_id = ? ORDER BY nome ASC");
$stmt->execute([$userId]);
$clientes = $stmt->fetchAll();

// status pra toast
$toastStatus = $_GET['status'] ?? null;

function getInitials($name) {
    $words = explode(" ", $name);
    $acronym = "";
    foreach ($words as $w) {
        if ($w === '') continue;
        $acronym .= mb_substr($w, 0, 1);
    }
    return mb_substr(strtoupper($acronym), 0, 2);
}
?>

<style>
    .main-content {
        max-width: 480px;
        margin: 0 auto;
        padding: 16px 12px 90px 12px;
    }

    /* Barra de Pesquisa */
    .search-container { 
        position: sticky; 
        top: 70px; 
        z-index: 90; 
        background: var(--bg-body); 
        padding-bottom: 10px; 
    }
    .search-input {
        width: 100%; 
        padding: 12px 12px 12px 40px; 
        border-radius: 16px; 
        border: 1px solid #e2e8f0;
        font-size: 0.9rem; 
        box-sizing: border-box; 
        background: white; 
        box-shadow: 0 6px 18px rgba(15,23,42,0.05);
    }
    .search-input::placeholder { color: #94a3b8; }
    .search-icon { 
        position: absolute; 
        left: 14px; 
        top: 50%; 
        transform: translateY(-50%); 
        color: #94a3b8; 
        font-size: 1rem;
    }

    .client-list { display: flex; flex-direction: column; gap: 10px; }

    .client-card {
        background: white; 
        border-radius: 18px; 
        padding: 10px 12px; 
        display: flex; 
        align-items: center; 
        gap: 10px;
        box-shadow: 0 8px 20px rgba(15,23,42,0.06); 
        border: 1px solid #f1f5f9;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
        position: relative;
    }
    .client-card:active { transform: scale(0.98); }

    .client-avatar {
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        background: #e0e7ff; 
        color: var(--primary);
        display: flex; 
        align-items: center; 
        justify-content: center;
        font-weight: 700; 
        font-size: 0.95rem; 
        flex-shrink: 0;
    }

    .client-info { flex-grow: 1; min-width: 0; }
    .client-name { 
        font-weight: 700; 
        font-size: 0.92rem; 
        color: var(--text-dark); 
        margin-bottom: 2px; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis; 
    }
    .client-details { 
        font-size: 0.78rem; 
        color: var(--text-gray); 
        display: flex; 
        gap: 8px; 
        align-items: center; 
        flex-wrap: wrap;
    }
    
    .btn-whatsapp {
        color: #25D366; 
        text-decoration: none; 
        font-size: 1.3rem; 
        padding: 4px;
        display: flex; 
        align-items: center; 
        justify-content: center;
    }

    .client-actions { display: flex; gap: 6px; margin-left: 4px; }
    .action-btn {
        width: 30px; 
        height: 30px; 
        border-radius: 10px; 
        border: none; 
        background: #f8fafc;
        color: var(--text-gray); 
        display: flex; 
        align-items: center; 
        justify-content: center;
        cursor: pointer; 
        transition: 0.2s; 
        font-size: 0.9rem;
    }
    .action-btn.edit:hover { background: #e0e7ff; color: var(--primary); }
    .action-btn.delete:hover { background: #fee2e2; color: var(--danger); }

    .fab-add {
        position: fixed; 
        bottom: 24px; 
        right: 18px; 
        width: 56px; 
        height: 56px;
        background: var(--primary); 
        color: white; 
        border-radius: 50%; 
        border: none;
        font-size: 1.9rem; 
        box-shadow: 0 8px 24px rgba(99,102,241,0.45);
        cursor: pointer; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        transition: transform 0.15s ease; 
        z-index: 900;
    }
    .fab-add:active { transform: scale(0.96); }

    /* Modal de cadastro/edição (bottom-sheet mobile) */
    .modal-overlay { 
        display: none; 
        position: fixed; 
        inset: 0; 
        background: rgba(15,23,42,0.4); 
        z-index: 2000; 
        align-items: flex-end; 
        justify-content: center; 
        backdrop-filter: blur(2px); 
        opacity: 0; 
        transition: opacity 0.25s;
    }
    .modal-overlay.active { display: flex; opacity: 1; }

    .modal-box { 
        background: white; 
        padding: 18px 16px 20px 16px; 
        border-radius: 24px 24px 0 0; 
        width: 100%; 
        max-width: 480px;
        max-height: 85vh; 
        overflow-y: auto; 
        box-shadow: 0 -8px 30px rgba(15,23,42,0.4); 
        transform: translateY(18px); 
        transition: transform 0.25s;
        margin: 0 auto;
    }
    .modal-overlay.active .modal-box { transform: translateY(0); }

    .form-group { margin-bottom: 12px; }
    .form-label { 
        display: block; 
        margin-bottom: 4px; 
        font-weight: 600; 
        font-size: 0.85rem; 
        color: #334155;
    }
    .form-control { 
        width: 100%; 
        padding: 10px 11px; 
        border: 1px solid #e2e8f0; 
        border-radius: 14px; 
        font-size: 0.9rem; 
        box-sizing: border-box; 
        background: #f8fafc; 
    }
    .form-control:focus { 
        outline: none; 
        border-color: var(--primary); 
        background: white; 
    }
    .btn-submit { 
        width: 100%; 
        background: var(--primary); 
        color: white; 
        padding: 12px; 
        border: none; 
        border-radius: 14px; 
        font-weight: 600; 
        font-size: 0.95rem; 
        cursor: pointer; 
        margin-top: 6px; 
    }
    .btn-submit:active { transform: scale(0.98); }

    @media (min-width: 769px) {
        .main-content {
            padding-top: 24px;
        }
        .modal-overlay {
            align-items: center;
        }
        .modal-box {
            border-radius: 24px;
        }
    }
</style>

<main class="main-content">
    
    <div class="search-container">
        <div style="position:relative;">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nome ou telefone..." onkeyup="filtrarClientes()">
        </div>
    </div>

    <div class="client-list" id="clientList">
        <?php if(count($clientes) > 0): ?>
            <?php foreach ($clientes as $c): ?>
                <div class="client-card" 
                     data-nome="<?php echo strtolower($c['nome']); ?>" 
                     data-tel="<?php echo str_replace(['(',')','-',' '], '', $c['telefone'] ?? ''); ?>">
                    
                    <div class="client-avatar">
                        <?php echo getInitials($c['nome']); ?>
                    </div>
                    
                    <div class="client-info">
                        <div class="client-name"><?php echo htmlspecialchars($c['nome']); ?></div>
                        <div class="client-details">
                            <?php if(!empty($c['telefone'])): ?>
                                <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($c['telefone']); ?></span>
                            <?php else: ?>
                                <span>Sem telefone</span>
                            <?php endif; ?>

                            <?php
                                if (!empty($c['data_nascimento'])) {
                                    try {
                                        $nasc = new DateTime($c['data_nascimento']);
                                        $diaMes = $nasc->format('d/m');
                                        $hoje   = new DateTime('today');
                                        $idade  = $hoje->diff($nasc)->y;
                                        echo '<span>🎂 ' . $diaMes . ' • ' . $idade . ' anos</span>';
                                    } catch (Exception $e) {
                                        // Se der erro na data, não mostra nada
                                    }
                                }
                            ?>
                        </div>
                    </div>

                    <?php if(!empty($c['telefone'])): 
                        $numClean = preg_replace('/[^0-9]/', '', $c['telefone']);
                    ?>
                        <a href="https://wa.me/55<?php echo $numClean; ?>" target="_blank" class="btn-whatsapp">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                    <?php endif; ?>

                    <div class="client-actions">
                        <button type="button" class="action-btn edit" onclick='editar(<?php echo json_encode($c); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button type="button" class="action-btn delete"
                            onclick='abrirConfirmDelete(<?php echo $c['id']; ?>, <?php echo json_encode($c["nome"]); ?>)'>
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; color:#94a3b8; margin-top:50px;">
                <i class="bi bi-person-plus" style="font-size:3rem; opacity:0.5;"></i>
                <p>Nenhum cliente cadastrado.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<button class="fab-add" onclick="abrirModal()">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- Modal Cadastro/Edição -->
<div class="modal-overlay" id="modalCliente">
    <div class="modal-box">
        <div style="width: 40px; height: 5px; background: #e2e8f0; border-radius: 10px; margin: 0 auto 12px auto;"></div>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h3 id="modalTitle" style="margin:0; font-size:1.05rem;">Novo Cliente</h3>
            <button onclick="fecharModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" id="inputAcao" value="create">
            <input type="hidden" name="id_cliente" id="inputId" value="">

            <div class="form-group">
                <label class="form-label">Nome Completo</label>
                <input type="text" name="nome" id="inputNome" class="form-control" placeholder="Ex: Ana Souza" required>
            </div>

            <div class="form-group">
                <label class="form-label">Telefone / WhatsApp</label>
                <input type="tel" name="telefone" id="inputTelefone" class="form-control" 
                       placeholder="(00) 00000-0000" maxlength="15" onkeyup="mascaraTelefone(this)">
            </div>

            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1">
                    <label class="form-label">Data Nascimento</label>
                    <input type="date" name="nascimento" id="inputNascimento" class="form-control">
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">Email (Opcional)</label>
                    <input type="email" name="email" id="inputEmail" class="form-control" placeholder="email@exemplo.com">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea name="obs" id="inputObs" class="form-control" rows="3" placeholder="Preferências, histórico, alergias..."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="btnSalvar">Salvar Cliente</button>
        </form>
    </div>
</div>

<?php
// componentes reutilizáveis
include '../../includes/ui-confirm.php';
include '../../includes/ui-toast.php';
include '../../includes/footer.php';
?>

<script>
    const modal = document.getElementById('modalCliente');
    
    function abrirModal() {
        document.getElementById('inputAcao').value = 'create';
        document.getElementById('inputId').value = '';
        document.getElementById('inputNome').value = '';
        document.getElementById('inputTelefone').value = '';
        document.getElementById('inputEmail').value = '';
        document.getElementById('inputNascimento').value = '';
        document.getElementById('inputObs').value = '';
        document.getElementById('modalTitle').innerText = 'Novo Cliente';
        document.getElementById('btnSalvar').innerText = 'Cadastrar';
        
        modal.classList.add('active');
    }

    function editar(cliente) {
        document.getElementById('inputAcao').value = 'update';
        document.getElementById('inputId').value = cliente.id;
        document.getElementById('inputNome').value = cliente.nome || '';
        document.getElementById('inputTelefone').value = cliente.telefone || '';
        document.getElementById('inputEmail').value = cliente.email || '';
        document.getElementById('inputNascimento').value = cliente.data_nascimento || '';
        document.getElementById('inputObs').value = cliente.observacoes || '';
        document.getElementById('modalTitle').innerText = 'Editar Cliente';
        document.getElementById('btnSalvar').innerText = 'Atualizar Dados';
        
        modal.classList.add('active');
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    function filtrarClientes() {
        let termo = document.getElementById('searchInput').value.toLowerCase();
        let cards = document.querySelectorAll('.client-card');

        cards.forEach(card => {
            let nome = card.getAttribute('data-nome') || '';
            let tel  = card.getAttribute('data-tel') || '';
            if (nome.includes(termo) || tel.includes(termo)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function mascaraTelefone(input) {
        let v = input.value;
        v = v.replace(/\D/g, "");
        v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
        v = v.replace(/(\d)(\d{4})$/, "$1-$2");
        input.value = v;
    }

    // usa o componente genérico de confirmação
    function abrirConfirmDelete(id, nome) {
        AppConfirm.open({
            title: 'Excluir Cliente',
            message: 'Deseja excluir o cliente <strong>' + nome + '</strong>?',
            confirmText: 'Sim, excluir',
            cancelText: 'Cancelar',
            type: 'danger',
            onConfirm: function () {
                window.location.href = 'clientes.php?delete=' + id;
            }
        });
    }

    // dispara toasts conforme status da URL
    <?php
    if ($toastStatus) {
        $msg  = '';
        $type = 'success';
        switch ($toastStatus) {
            case 'created':
                $msg  = 'Cliente cadastrado com sucesso.';
                $type = 'success';
                break;
            case 'updated':
                $msg  = 'Dados do cliente atualizados.';
                $type = 'success';
                break;
            case 'deleted':
                $msg  = 'Cliente excluído com sucesso.';
                $type = 'danger';
                break;
        }
        if ($msg):
    ?>
    window.addEventListener('DOMContentLoaded', function () {
        AppToast.show(<?php echo json_encode($msg); ?>, <?php echo json_encode($type); ?>);
    });
    <?php
        endif;
    }
    ?>
</script>
