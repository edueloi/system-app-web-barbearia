<?php
$pageTitle = 'Meus Serviços';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Cria pasta uploads se não existir
if (!is_dir('../../uploads')) { mkdir('../../uploads', 0777, true); }

// Simulação de User ID
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- 1. LÓGICA PHP (CRIAR, EDITAR, EXCLUIR) ---

// A. EXCLUIR
if (isset($_GET['delete'])) {
    $idDelete = $_GET['delete'];
    // Verifica se pertence ao usuário para segurança
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$idDelete, $userId]);
    echo "<script>window.location.href='servicos.php';</script>";
    exit;
}

// B. SALVAR (CRIAR OU EDITAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao']; // 'create' ou 'update'
    $idEdit = $_POST['id_servico'] ?? null;
    
    $nome = $_POST['nome'];
    $preco = str_replace(',', '.', $_POST['preco']); 
    $duracao = $_POST['duracao']; 
    $obs = $_POST['obs'];
    $tipo = $_POST['tipo']; 
    $itens = isset($_POST['itens_selecionados']) ? implode(',', $_POST['itens_selecionados']) : '';
    // Caminho FÍSICO da pasta uploads (no servidor)
    $uploadDirFs = __DIR__ . '/../../uploads/';

    // Garante que a pasta existe
    if (!is_dir($uploadDirFs)) {
        mkdir($uploadDirFs, 0777, true);
    }

    // Caminho que vai ficar salvo no banco (RELATIVO ao projeto)
    $uploadDirDb = 'uploads/';

    // Upload de Imagem
    $fotoPath = $_POST['foto_atual'] ?? ''; // Mantém a antiga se não enviar nova

    if (!empty($_FILES['foto']['name'])) {
        if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {

            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg','jpeg','png','webp'];

            if (in_array($ext, $permitidas)) {
                $novoNome = uniqid('srv_', true) . '.' . $ext;

                $destinoFs = $uploadDirFs . $novoNome;   // caminho físico
                $caminhoDb = $uploadDirDb . $novoNome;   // o que vai para o banco

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $destinoFs)) {
                    $fotoPath = $caminhoDb;
                } else {
                    error_log('Falha ao mover o upload de foto para: ' . $destinoFs);
                }
            } else {
                error_log('Extensão de imagem não permitida: ' . $ext);
            }

        } else {
            error_log('Erro no upload da foto: código ' . $_FILES['foto']['error']);
        }
    }

    if (!empty($nome) && !empty($preco)) {
        if ($acao === 'update' && $idEdit) {
            // ATUALIZAR
            $sql = "UPDATE servicos SET nome=?, preco=?, duracao=?, foto=?, observacao=?, itens_pacote=? WHERE id=? AND user_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $preco, $duracao, $fotoPath, $obs, $itens, $idEdit, $userId]);
        } else {
            // CRIAR NOVO
            $sql = "INSERT INTO servicos (user_id, nome, preco, duracao, foto, observacao, tipo, itens_pacote) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $preco, $duracao, $fotoPath, $obs, $tipo, $itens]);
        }
        header("Location: servicos.php?status=saved");
        exit;
    }
}

// --- 2. BUSCAR DADOS ---
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$todosRegistros = $stmt->fetchAll();

$listaServicos = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'unico'; });
$listaPacotes = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'pacote'; });
?>

<style>
    /* Container principal da página */
.main-content {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

/* Barra de Pesquisa */
.search-bar-container { 
    position: relative; 
    margin-bottom: 24px; 
}
.search-input {
    width: 100%; 
    padding: 14px 14px 14px 45px; 
    border-radius: 16px; 
    border: 1px solid #e2e8f0;
    font-size: 0.95rem; 
    box-sizing: border-box; 
    background: #ffffff; 
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}
.search-input::placeholder {
    color: #94a3b8;
}
.search-icon { 
    position: absolute; 
    left: 16px; 
    top: 50%; 
    transform: translateY(-50%); 
    color: #94a3b8; 
    font-size: 1.1rem;
}

/* Tabs */
.tabs { 
    display: flex; 
    gap: 20px; 
    margin-bottom: 20px; 
    border-bottom: 1px solid #e2e8f0; 
}
.tab-btn {
    padding: 10px 0; 
    background: none; 
    border: none; 
    font-size: 0.95rem; 
    color: var(--text-gray);
    cursor: pointer; 
    border-bottom: 3px solid transparent; 
    font-weight: 600;
    transition: color 0.2s, border-color 0.2s;
}
.tab-btn.active { 
    color: var(--primary); 
    border-bottom-color: var(--primary); 
}

/* Grid */
.services-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); 
    gap: 18px; 
}

/* Card de serviço */
.service-card {
    background: #ffffff; 
    border-radius: 18px; 
    overflow: hidden; 
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
    border: 1px solid #f1f5f9; 
    display: flex; 
    flex-direction: column; 
    position: relative;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.service-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
}
.service-card:active { 
    transform: scale(0.97); 
}

/* Botões de Ação no Card (Editar/Excluir) */
.card-actions {
    position: absolute; 
    top: 10px; 
    right: 10px; 
    display: flex; 
    gap: 6px;
}
.action-btn {
    width: 30px; 
    height: 30px; 
    border-radius: 999px; 
    background: rgba(255,255,255,0.95);
    border: none; 
    box-shadow: 0 4px 10px rgba(148, 163, 184, 0.4); 
    cursor: pointer;
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: var(--text-dark);
    font-size: 0.9rem; 
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.action-btn:hover { 
    transform: scale(1.07); 
    background: #ffffff;
}
.btn-edit { color: var(--primary); }
.btn-delete { color: var(--danger); }

/* Imagem do Card */
.service-img {
        height: 140px; 
        background-color: #f1f5f9; 
        background-size: cover; 
        background-position: center; 
        background-repeat: no-repeat;
        display: flex; 
        align-items: center; 
        justify-content: center; 
        color: #cbd5e1;
}

/* Conteúdo do Card */
.service-body { 
    padding: 12px; 
    flex-grow: 1; 
    display: flex; 
    flex-direction: column; 
}
.service-title { 
    font-weight: 700; 
    color: var(--text-dark); 
    margin-bottom: 4px; 
    font-size: 0.95rem; 
    line-height: 1.2; 
}
.service-meta { 
    font-size: 0.8rem; 
    color: var(--text-gray); 
    margin-bottom: 8px; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
}
.service-price { 
    color: var(--primary); 
    font-weight: 800; 
    font-size: 1.1rem; 
    margin-top: auto; 
}

/* Botão principal */
.btn-submit { 
    background: var(--primary); 
    color: white; 
    padding: 12px 24px; 
    border: none; 
    border-radius: 999px; 
    font-weight: 600; 
    font-size: 0.95rem; 
    cursor: pointer; 
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.35); 
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.btn-submit:active { 
    transform: scale(0.97); 
}

/* Modal estilo app */
.modal-overlay { 
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(15, 23, 42, 0.45); 
    z-index: 2000; 
    align-items: center; 
    justify-content: center; 
    backdrop-filter: blur(3px);
    opacity: 0; 
    transition: opacity 0.3s;
}
.modal-overlay.active { 
    display: flex; 
    opacity: 1; 
}
.modal-box { 
    background: white; 
    padding: 22px; 
    border-radius: 24px; 
    width: 95%; 
    max-width: 500px; 
    max-height: 90vh; 
    overflow-y: auto; 
    box-shadow: 0 24px 40px rgba(15, 23, 42, 0.25); 
    transform: translateY(24px); 
    transition: transform 0.3s;
}
.modal-overlay.active .modal-box { 
    transform: translateY(0); 
}

/* Formulário */
.form-group { margin-bottom: 14px; }
.form-label { 
    display: block; 
    margin-bottom: 6px; 
    font-weight: 600; 
    font-size: 0.85rem; 
    color: #334155; 
}
.form-control { 
    width: 100%; 
    padding: 12px; 
    border: 1px solid #e2e8f0; 
    border-radius: 14px; 
    box-sizing: border-box; 
    font-size: 0.95rem; 
    background: #f8fafc; 
}
.form-control:focus { 
    outline: none; 
    border-color: var(--primary); 
    background: white; 
}

/* Lista de serviços dentro do pacote */
.checkbox-list { 
    max-height: 150px; 
    overflow-y: auto; 
    border: 1px solid #e2e8f0; 
    border-radius: 14px; 
    padding: 10px; 
    background: #f8fafc; 
}
.check-item { 
    padding: 6px 0; 
    border-bottom: 1px solid #e2e8f0; 
    display:flex; 
    justify-content: space-between; 
    align-items:center;
    font-size: 0.85rem; 
}
.check-item:last-child {
    border-bottom: none;
}

/* RESPONSIVO */
@media (max-width: 768px) {
    .main-content {
        padding: 16px;
    }
    .services-grid { 
        grid-template-columns: repeat(2, minmax(0, 1fr)); 
        gap: 12px;
    }
    .service-img {
        height: 110px;
    }
}

@media (max-width: 480px) {
    .services-grid { 
        grid-template-columns: repeat(2, minmax(0, 1fr)); 
    }
    .service-title {
        font-size: 0.85rem;
    }
    .service-price {
        font-size: 1rem;
    }
    .modal-overlay { 
        align-items: flex-end; 
    }
    .modal-box {
        width: 100%; 
        max-width: 100%; 
        border-radius: 24px 24px 0 0; 
        margin: 0; 
        max-height: 85vh; 
        padding-bottom: 32px;
    }
}

</style>

<main class="main-content">
    
    <div class="search-bar-container">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Pesquisar serviço..." onkeyup="filtrarServicos()">
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('servicos')">Serviços</button>
        <button class="tab-btn" onclick="showTab('pacotes')">Pacotes</button>
    </div>

    <div id="tab-servicos" class="tab-content">
        <button class="btn-submit" onclick="abrirModal('unico')" style="margin-bottom: 20px; width: auto; padding: 12px 24px;">
            + Novo Serviço
        </button>

        <div class="services-grid">
            <?php foreach ($listaServicos as $s): ?>
                <div class="service-card" data-nome="<?php echo strtolower($s['nome']); ?>">
                    <div class="card-actions">
                        <button class="action-btn btn-edit" onclick='editar(<?php echo json_encode($s); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <a href="?delete=<?php echo $s['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Tem certeza que deseja apagar?');">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                    </div>

                    <div class="service-img" style="<?php echo $s['foto'] ? "background-image: url('../../{$s['foto']}')" : ""; ?>">
                        <?php if(!$s['foto']): ?><i class="bi bi-scissors" style="font-size: 2.5rem;"></i><?php endif; ?>
                    </div>
                    
                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($s['nome']); ?></div>
                        <div class="service-meta">
                            <i class="bi bi-clock"></i> <?php echo $s['duracao']; ?> min
                        </div>
                        <div class="service-price">R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="tab-pacotes" class="tab-content" style="display: none;">
        <button class="btn-submit" onclick="abrirModal('pacote')" style="margin-bottom: 20px; width: auto; padding: 12px 24px;">
            + Novo Pacote
        </button>

        <div class="services-grid">
            <?php foreach ($listaPacotes as $p): ?>
                <div class="service-card" data-nome="<?php echo strtolower($p['nome']); ?>" style="border-bottom: 3px solid var(--primary);">
                    <div class="card-actions">
                        <button class="action-btn btn-edit" onclick='editar(<?php echo json_encode($p); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <a href="?delete=<?php echo $p['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Tem certeza que deseja apagar?');">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                    </div>

                    <div class="service-img" style="<?php echo $p['foto'] ? "background-image: url('../../{$p['foto']}')" : ""; ?>">
                        <?php if(!$p['foto']): ?><i class="bi bi-box-seam" style="font-size: 2.5rem;"></i><?php endif; ?>
                    </div>
                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($p['nome']); ?></div>
                        <div class="service-meta">
                            <i class="bi bi-clock"></i> <?php echo $p['duracao']; ?> min
                            <span style="background:var(--primary); color:white; padding:2px 6px; border-radius:4px; font-size:0.6rem; margin-left:auto;">COMBO</span>
                        </div>
                        <div class="service-price">R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<div class="modal-overlay" id="modalServico">
    <div class="modal-box">
        <div style="width: 40px; height: 5px; background: #e2e8f0; border-radius: 10px; margin: 0 auto 20px auto;"></div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 id="modalTitle" style="margin:0; font-size: 1.25rem;">Novo Serviço</h3>
            <button onclick="fecharModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#64748b;">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" id="inputAcao" value="create">
            <input type="hidden" name="id_servico" id="inputIdServico" value="">
            <input type="hidden" name="tipo" id="inputTipo" value="unico">
            <input type="hidden" name="foto_atual" id="inputFotoAtual" value="">
            
            <div class="form-group">
                <label class="form-label">Nome do Serviço/Pacote</label>
                <input type="text" name="nome" id="inputNome" class="form-control" placeholder="Ex: Corte Degrade" required>
            </div>

            <div class="form-group" id="areaSelecaoItens" style="display:none;">
                <label class="form-label">Itens do Pacote</label>
                <div class="checkbox-list">
                    <?php if(count($listaServicos) > 0): ?>
                        <?php foreach($listaServicos as $s): ?>
                            <div class="check-item">
                                <label style="display:flex; align-items:center; gap:10px;">
                                    <input type="checkbox" name="itens_selecionados[]" 
                                           value="<?php echo $s['id']; ?>"
                                           class="chk-servico"
                                           data-price="<?php echo $s['preco']; ?>"
                                           data-time="<?php echo $s['duracao']; ?>"
                                           onchange="calcularPacote()">
                                    <?php echo htmlspecialchars($s['nome']); ?>
                                </label>
                                <span>R$ <?php echo $s['preco']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small>Sem serviços cadastrados.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex; gap:15px;">
                <div class="form-group" style="flex:1">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="preco" id="inputPreco" class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">Tempo (min)</label>
                    <input type="number" name="duracao" id="inputTempo" class="form-control" placeholder="30" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Foto de Capa</label>
                <input type="file" name="foto" class="form-control">
                <small id="avisoFoto" style="color:#64748b; font-size:0.8rem;"></small>
            </div>

            <div class="form-group">
                <label class="form-label">Descrição (Opcional)</label>
                <textarea name="obs" id="inputObs" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn-submit" id="btnSalvar">Salvar</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    const modal = document.getElementById('modalServico');
    const inputAcao = document.getElementById('inputAcao');
    const inputId = document.getElementById('inputIdServico');
    const inputTipo = document.getElementById('inputTipo');
    const inputNome = document.getElementById('inputNome');
    const inputPreco = document.getElementById('inputPreco');
    const inputTempo = document.getElementById('inputTempo');
    const inputObs = document.getElementById('inputObs');
    const inputFotoAtual = document.getElementById('inputFotoAtual');
    const modalTitle = document.getElementById('modalTitle');
    const btnSalvar = document.getElementById('btnSalvar');
    const areaItens = document.getElementById('areaSelecaoItens');

    // 1. ABRIR MODAL (NOVO)
    function abrirModal(tipo) {
        // Resetar formulário
        inputAcao.value = 'create';
        inputId.value = '';
        inputFotoAtual.value = '';
        inputNome.value = '';
        inputPreco.value = '';
        inputTempo.value = '';
        inputObs.value = '';
        btnSalvar.innerText = 'Criar Agora';
        document.querySelectorAll('.chk-servico').forEach(chk => chk.checked = false);

        // Configurar tipo
        inputTipo.value = tipo;
        if (tipo === 'pacote') {
            modalTitle.innerText = 'Novo Pacote';
            areaItens.style.display = 'block';
        } else {
            modalTitle.innerText = 'Novo Serviço';
            areaItens.style.display = 'none';
        }
        
        modal.classList.add('active');
    }

    // 2. ABRIR MODAL (EDITAR)
    function editar(dados) {
        inputAcao.value = 'update';
        inputId.value = dados.id;
        inputTipo.value = dados.tipo;
        inputNome.value = dados.nome;
        inputPreco.value = dados.preco;
        inputTempo.value = dados.duracao;
        inputObs.value = dados.observacao;
        inputFotoAtual.value = dados.foto;
        btnSalvar.innerText = 'Atualizar';

        if (dados.tipo === 'pacote') {
            modalTitle.innerText = 'Editar Pacote';
            areaItens.style.display = 'block';
            
            // Marcar checkboxes
            let itens = dados.itens_pacote ? dados.itens_pacote.split(',') : [];
            document.querySelectorAll('.chk-servico').forEach(chk => {
                chk.checked = itens.includes(chk.value);
            });
        } else {
            modalTitle.innerText = 'Editar Serviço';
            areaItens.style.display = 'none';
        }

        modal.classList.add('active');
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    // 3. PESQUISA INSTANTÂNEA
    function filtrarServicos() {
        let termo = document.getElementById('searchInput').value.toLowerCase();
        let cards = document.querySelectorAll('.service-card');

        cards.forEach(card => {
            let nome = card.getAttribute('data-nome');
            if (nome.includes(termo)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // 4. CÁLCULO DE PACOTE
    function calcularPacote() {
        if (inputTipo.value !== 'pacote') return;
        
        let totalPreco = 0;
        let totalTempo = 0;
        document.querySelectorAll('.chk-servico:checked').forEach(chk => {
            totalPreco += parseFloat(chk.getAttribute('data-price'));
            totalTempo += parseInt(chk.getAttribute('data-time'));
        });
        
        // Só atualiza se for criação (para não sobrescrever preço editado)
        if (inputAcao.value === 'create') {
            inputPreco.value = totalPreco.toFixed(2);
            inputTempo.value = totalTempo;
        }
    }

    // Abas
    function showTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabName).style.display = 'block';
        event.target.classList.add('active');
    }
</script>