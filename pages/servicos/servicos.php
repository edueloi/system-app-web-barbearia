<?php
// --- 1. LÓGICA PHP (CRIAR, EDITAR, EXCLUIR) ---
include '../../includes/db.php';

// Simulação de User ID
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// Cria pasta uploads se não existir
if (!is_dir('../../uploads')) { mkdir('../../uploads', 0777, true); }

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

    $acao   = $_POST['acao']; // 'create' ou 'update'
    $idEdit = $_POST['id_servico'] ?? null;

    $nome    = $_POST['nome'];
    $preco   = str_replace(',', '.', $_POST['preco']);
    $duracao = $_POST['duracao'];
    $obs     = $_POST['obs'];
    $tipo    = $_POST['tipo'];
    $calculoServicoId = !empty($_POST['calculo_servico_id']) ? (int)$_POST['calculo_servico_id'] : null;

    $itens = isset($_POST['itens_selecionados']) ? implode(',', $_POST['itens_selecionados']) : '';

    // Caminho FÍSICO da pasta uploads (no servidor)
    $uploadDirFs = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDirFs)) { mkdir($uploadDirFs, 0777, true); }

    // Caminho salvo no banco (relativo ao projeto)
    $uploadDirDb = 'uploads/';

    $stmtCalcs = $pdo->prepare("
        SELECT id, nome_servico, valor_cobrado
        FROM calculo_servico
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmtCalcs->execute([$userId]);
    $calculos = $stmtCalcs->fetchAll();


    // Upload de Imagem
    $fotoPath = $_POST['foto_atual'] ?? '';
    if (!empty($_FILES['foto']['name'])) {
        if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext        = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $permitidas)) {
                $novoNome  = uniqid('srv_', true) . '.' . $ext;
                $destinoFs = $uploadDirFs . $novoNome;
                $caminhoDb = $uploadDirDb . $novoNome;

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
            $sql = "UPDATE servicos 
                       SET nome=?, preco=?, duracao=?, foto=?, observacao=?, itens_pacote=?, calculo_servico_id=? 
                     WHERE id=? AND user_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $preco, $duracao, $fotoPath, $obs, $itens, $calculoServicoId, $idEdit, $userId]);
        } else {
            // CRIAR NOVO
            $sql = "INSERT INTO servicos (user_id, nome, preco, duracao, foto, observacao, tipo, itens_pacote, calculo_servico_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nome, $preco, $duracao, $fotoPath, $obs, $tipo, $itens, $calculoServicoId]);
        }

        header("Location: servicos.php?status=saved");
        exit;
    }
}

$pageTitle = 'Meus Serviços';
include '../../includes/header.php';

// --- 2. BUSCAR DADOS ---
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$todosRegistros = $stmt->fetchAll();

$listaServicos = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'unico'; });
$listaPacotes  = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'pacote'; });
?>

<main class="main-content">

    <div class="page-header">
        <div class="page-title-wrap">
            <h1 class="page-title">Meus Serviços</h1>
            <p class="page-subtitle">Organize serviços e pacotes que seus clientes podem agendar.</p>
        </div>
        <div class="page-header-actions">
            <button class="btn-chip" onclick="abrirModal('unico')">
                <i class="bi bi-plus-lg"></i>
                Novo serviço
            </button>
        </div>
    </div>

    <div class="search-bar-container">
        <i class="bi bi-search search-icon"></i>
        <input
            type="text"
            id="searchInput"
            class="search-input"
            placeholder="Buscar por nome do serviço ou pacote..."
            onkeyup="filtrarServicos()"
        >
    </div>

    <div class="tabs-wrapper">
        <div class="tabs-pill">
            <button class="tab-btn active" onclick="showTab('servicos', this)">
                <i class="bi bi-scissors"></i> Serviços
            </button>
            <button class="tab-btn" onclick="showTab('pacotes', this)">
                <i class="bi bi-box-seam"></i> Pacotes
            </button>
        </div>
    </div>

    <!-- LISTA DE SERVIÇOS -->
    <div id="tab-servicos" class="tab-content">
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
                        <?php if (!$s['foto']): ?>
                            <i class="bi bi-scissors" style="font-size: 2.2rem;"></i>
                        <?php endif; ?>
                    </div>

                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($s['nome']); ?></div>
                        <div class="service-meta">
                            <i class="bi bi-clock"></i>
                            <?php echo $s['duracao']; ?> min
                        </div>
                        <div class="service-price">
                            R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LISTA DE PACOTES -->
    <div id="tab-pacotes" class="tab-content" style="display: none;">
        <div class="services-grid">
            <?php foreach ($listaPacotes as $p): ?>
                <div class="service-card" data-nome="<?php echo strtolower($p['nome']); ?>">
                    <span class="service-chip">
                        <i class="bi bi-stars"></i> Pacote
                    </span>

                    <div class="card-actions">
                        <button class="action-btn btn-edit" onclick='editar(<?php echo json_encode($p); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <a href="?delete=<?php echo $p['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Tem certeza que deseja apagar?');">
                            <i class="bi bi-trash-fill"></i>
                        </a>
                    </div>

                    <div class="service-img" style="<?php echo $p['foto'] ? "background-image: url('../../{$p['foto']}')" : ""; ?>">
                        <?php if (!$p['foto']): ?>
                            <i class="bi bi-box-seam" style="font-size: 2.2rem;"></i>
                        <?php endif; ?>
                    </div>

                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($p['nome']); ?></div>
                        <div class="service-meta">
                            <i class="bi bi-clock"></i>
                            <?php echo $p['duracao']; ?> min
                        </div>
                        <div class="service-price">
                            R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<!-- MODAL -->
<div class="modal-overlay" id="modalServico">
    <div class="modal-box">
        <div class="modal-header-bar"></div>

        <div class="modal-header-row">
            <h3 id="modalTitle" class="modal-title">Novo Serviço</h3>
            <button type="button" class="modal-close-btn" onclick="fecharModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" id="inputAcao" value="create">
            <input type="hidden" name="id_servico" id="inputIdServico" value="">
            <input type="hidden" name="tipo" id="inputTipo" value="unico">
            <input type="hidden" name="foto_atual" id="inputFotoAtual" value="">

            <div class="form-group">
                <label class="form-label">Nome do serviço / pacote</label>
                <input type="text" name="nome" id="inputNome" class="form-control" placeholder="Ex: Corte Degrade + Barba" required>
            </div>

            <div class="form-group">
                <label>Cálculo de custos vinculado (opcional)</label>
                <select name="calculo_servico_id" class="form-control">
                    <option value="">Nenhum (preencher manual)</option>
                    <?php foreach ($calculos as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                            <?php if (!empty($servicoEdicao['calculo_servico_id']) && $servicoEdicao['calculo_servico_id'] == $c['id']) echo 'selected'; ?>
                        >
                            <?php echo htmlspecialchars($c['nome_servico']); ?> - R$ <?php echo number_format($c['valor_cobrado'], 2, ',', '.'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="areaSelecaoItens" style="display:none;">
                <label class="form-label">Itens do pacote</label>
                <div class="checkbox-list">
                    <?php if(count($listaServicos) > 0): ?>
                        <?php foreach($listaServicos as $s): ?>
                            <div class="check-item">
                                <label style="display:flex; align-items:center; gap:8px; flex:1;">
                                    <input type="checkbox"
                                           name="itens_selecionados[]"
                                           value="<?php echo $s['id']; ?>"
                                           class="chk-servico"
                                           data-price="<?php echo $s['preco']; ?>"
                                           data-time="<?php echo $s['duracao']; ?>"
                                           onchange="calcularPacote()">
                                    <?php echo htmlspecialchars($s['nome']); ?>
                                </label>
                                <span>R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="font-size:0.78rem; color:var(--text-gray);">
                            Cadastre ao menos um serviço para montar pacotes.
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dual-input-row">
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="preco" id="inputPreco" class="form-control" placeholder="0,00" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label class="form-label">Tempo total (min)</label>
                    <input type="number" name="duracao" id="inputTempo" class="form-control" placeholder="30" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Foto de capa</label>
                <input type="file" name="foto" class="form-control">
                <small style="color:#94a3b8; font-size:0.75rem;">
                    Imagens horizontais ficam mais bonitas no card (JPG, PNG ou WEBP).
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Descrição (opcional)</label>
                <textarea name="obs" id="inputObs" class="form-control" rows="2" placeholder="Detalhes, condições ou observações do serviço."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="btnSalvar">
                <i class="bi bi-check-circle-fill"></i>
                Salvar
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    const modal         = document.getElementById('modalServico');
    const inputAcao     = document.getElementById('inputAcao');
    const inputId       = document.getElementById('inputIdServico');
    const inputTipo     = document.getElementById('inputTipo');
    const inputNome     = document.getElementById('inputNome');
    const inputPreco    = document.getElementById('inputPreco');
    const inputTempo    = document.getElementById('inputTempo');
    const inputObs      = document.getElementById('inputObs');
    const inputFotoAtual= document.getElementById('inputFotoAtual');
    const modalTitle    = document.getElementById('modalTitle');
    const btnSalvar     = document.getElementById('btnSalvar');
    const areaItens     = document.getElementById('areaSelecaoItens');

    // 1. ABRIR MODAL (NOVO)
    function abrirModal(tipo) {
        inputAcao.value   = 'create';
        inputId.value     = '';
        inputFotoAtual.value = '';
        inputNome.value   = '';
        inputPreco.value  = '';
        inputTempo.value  = '';
        inputObs.value    = '';
        btnSalvar.innerText = 'Salvar';
        document.querySelectorAll('.chk-servico').forEach(chk => chk.checked = false);

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
        inputAcao.value   = 'update';
        inputId.value     = dados.id;
        inputTipo.value   = dados.tipo;
        inputNome.value   = dados.nome;
        inputPreco.value  = dados.preco;
        inputTempo.value  = dados.duracao;
        inputObs.value    = dados.observacao;
        inputFotoAtual.value = dados.foto;
        btnSalvar.innerText = 'Atualizar';

        if (dados.tipo === 'pacote') {
            modalTitle.innerText = 'Editar Pacote';
            areaItens.style.display = 'block';

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
            let nome = card.getAttribute('data-nome') || '';
            if (nome.includes(termo)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // 4. CÁLCULO AUTOMÁTICO DE PACOTE
    function calcularPacote() {
        if (inputTipo.value !== 'pacote') return;

        let totalPreco = 0;
        let totalTempo = 0;

        document.querySelectorAll('.chk-servico:checked').forEach(chk => {
            totalPreco += parseFloat(chk.getAttribute('data-price')) || 0;
            totalTempo += parseInt(chk.getAttribute('data-time')) || 0;
        });

        if (inputAcao.value === 'create') {
            inputPreco.value = totalPreco.toFixed(2);
            inputTempo.value = totalTempo;
        }
    }

    // 5. TROCA DE ABAS
    function showTab(tabName, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        const target = document.getElementById('tab-' + tabName);
        if (target) target.style.display = 'block';
        if (btn) btn.classList.add('active');

        // Atualiza botão principal no topo
        const headerBtn = document.querySelector('.btn-chip');
        if (headerBtn) {
            if (tabName === 'pacotes') {
                headerBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Novo pacote';
                headerBtn.setAttribute('onclick', "abrirModal('pacote')");
            } else {
                headerBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Novo serviço';
                headerBtn.setAttribute('onclick', "abrirModal('unico')");
            }
        }
    }
</script>
