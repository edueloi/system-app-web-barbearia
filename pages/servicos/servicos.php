<?php
$pageTitle = 'Meus Serviços';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Simulação de User ID (Remover quando tiver login real)
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- 1. PROCESSAR FORMULÁRIO (SALVAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $preco = str_replace(',', '.', $_POST['preco']); // Aceita 10,50 ou 10.50
    $duracao = $_POST['duracao']; // em minutos
    $obs = $_POST['obs'];
    $tipo = $_POST['tipo']; // 'unico' ou 'pacote'
    $itens = isset($_POST['itens_selecionados']) ? implode(',', $_POST['itens_selecionados']) : '';

    // Upload de Imagem
    $fotoPath = ''; // Padrão vazio
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $novoNome = uniqid() . "." . $ext;
        $destino = '../../uploads/' . $novoNome;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
            $fotoPath = 'uploads/' . $novoNome;
        }
    }

    if (!empty($nome) && !empty($preco) && !empty($duracao)) {
        $sql = "INSERT INTO servicos (user_id, nome, preco, duracao, foto, observacao, tipo, itens_pacote) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $nome, $preco, $duracao, $fotoPath, $obs, $tipo, $itens]);
        
        // Refresh para limpar POST
        echo "<script>window.location.href='servicos.php';</script>";
        exit;
    }
}

// --- 2. BUSCAR DADOS ---
// Busca todos para listar na tela
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$todosRegistros = $stmt->fetchAll();

// Separa em dois arrays para as abas
$listaServicos = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'unico'; });
$listaPacotes = array_filter($todosRegistros, function($item){ return $item['tipo'] == 'pacote'; });

?>

<style>
    /* Tabs (Abas) */
    .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
    .tab-btn {
        padding: 10px 20px; background: none; border: none; font-size: 1rem; color: var(--text-gray);
        cursor: pointer; border-bottom: 3px solid transparent; font-weight: 600;
    }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

    /* Grid de Cards */
    .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; }
    
    .service-card {
        background: white; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow);
        border: 1px solid #f1f5f9; display: flex; flex-direction: column;
    }
    .service-img {
        height: 120px; background-color: #e2e8f0; background-size: cover; background-position: center;
        display: flex; align-items: center; justify-content: center; color: #94a3b8;
    }
    .service-body { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
    .service-title { font-weight: 700; color: var(--text-dark); margin-bottom: 5px; font-size: 1rem; }
    .service-meta { font-size: 0.85rem; color: var(--text-gray); margin-bottom: 10px; display: flex; justify-content: space-between; }
    .service-price { color: var(--primary); font-weight: 700; font-size: 1.1rem; margin-top: auto; }

    /* Modal Styles (Reaproveitado) */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; padding: 25px; border-radius: 20px; width: 95%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    
    .form-group { margin-bottom: 15px; }
    .form-label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box; }
    .btn-submit { width: 100%; background: var(--primary); color: white; padding: 14px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; margin-top: 10px; }
    .btn-submit:hover { background: var(--primary-hover); }

    /* Checkbox Lista para Pacotes */
    .checkbox-list { max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
    .check-item { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #f8fafc; }
    .check-item:last-child { border-bottom: none; }
    .check-item label { display: flex; align-items: center; gap: 10px; cursor: pointer; width: 100%; font-size: 0.9rem; }
</style>

<main class="main-content">
    
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('servicos')">Serviços</button>
        <button class="tab-btn" onclick="showTab('pacotes')">Pacotes</button>
    </div>

    <div id="tab-servicos" class="tab-content">
        <button class="btn-submit" onclick="abrirModal('unico')" style="margin-bottom: 20px; width: auto; padding: 10px 20px;">
            + Novo Serviço
        </button>

        <div class="services-grid">
            <?php foreach ($listaServicos as $servico): ?>
                <div class="service-card">
                    <div class="service-img" style="<?php echo $servico['foto'] ? "background-image: url('../../{$servico['foto']}')" : ""; ?>">
                        <?php if(!$servico['foto']): ?><i class="bi bi-scissors" style="font-size: 2rem;"></i><?php endif; ?>
                    </div>
                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($servico['nome']); ?></div>
                        <div class="service-meta">
                            <span><i class="bi bi-clock"></i> <?php echo $servico['duracao']; ?> min</span>
                        </div>
                        <?php if($servico['observacao']): ?>
                            <small style="color:#94a3b8; display:block; margin-bottom:5px; line-height:1.2"><?php echo htmlspecialchars($servico['observacao']); ?></small>
                        <?php endif; ?>
                        <div class="service-price">R$ <?php echo number_format($servico['preco'], 2, ',', '.'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="tab-pacotes" class="tab-content" style="display: none;">
        <button class="btn-submit" onclick="abrirModal('pacote')" style="margin-bottom: 20px; width: auto; padding: 10px 20px;">
            + Novo Pacote
        </button>

        <div class="services-grid">
            <?php foreach ($listaPacotes as $pacote): ?>
                <div class="service-card" style="border-color: var(--primary);">
                    <div class="service-img" style="<?php echo $pacote['foto'] ? "background-image: url('../../{$pacote['foto']}')" : ""; ?>">
                        <?php if(!$pacote['foto']): ?><i class="bi bi-box-seam" style="font-size: 2rem;"></i><?php endif; ?>
                    </div>
                    <div class="service-body">
                        <div class="service-title"><?php echo htmlspecialchars($pacote['nome']); ?></div>
                        <div class="service-meta">
                            <span><i class="bi bi-clock"></i> <?php echo $pacote['duracao']; ?> min</span>
                            <span style="color:var(--primary); font-weight:bold; font-size:0.7rem; border:1px solid var(--primary); padding:2px 6px; border-radius:4px;">PACOTE</span>
                        </div>
                        <div class="service-price">R$ <?php echo number_format($pacote['preco'], 2, ',', '.'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<div class="modal-overlay" id="modalServico">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 id="modalTitle" style="margin:0;">Novo Serviço</h3>
            <button onclick="fecharModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="tipo" id="inputTipo" value="unico">
            
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Corte Degrade" required>
            </div>

            <div class="form-group" id="areaSelecaoItens" style="display:none;">
                <label class="form-label">Selecione os Serviços incluídos:</label>
                <div class="checkbox-list">
                    <?php if(count($listaServicos) > 0): ?>
                        <?php foreach($listaServicos as $s): ?>
                            <div class="check-item">
                                <label>
                                    <input type="checkbox" name="itens_selecionados[]" 
                                           value="<?php echo $s['id']; ?>"
                                           class="chk-servico"
                                           data-price="<?php echo $s['preco']; ?>"
                                           data-time="<?php echo $s['duracao']; ?>"
                                           onchange="calcularPacote()">
                                    <?php echo htmlspecialchars($s['nome']); ?>
                                </label>
                                <span style="font-size:0.8rem; color:#64748b;">R$ <?php echo $s['preco']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small style="padding:10px; display:block;">Cadastre serviços individuais primeiro.</small>
                    <?php endif; ?>
                </div>
                <small style="color:var(--primary); font-size:0.8rem;">*O sistema soma automático, mas você pode alterar o valor final abaixo para dar desconto.</small>
            </div>

            <div style="display:flex; gap:15px;">
                <div class="form-group" style="flex:1">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="preco" id="inputPreco" class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">Tempo (min)</label>
                    <input type="number" name="duracao" id="inputTempo" class="form-control" placeholder="Ex: 30" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Foto (Opcional)</label>
                <input type="file" name="foto" class="form-control" accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label">Observações / Descrição</label>
                <textarea name="obs" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn-submit">Salvar</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    // Controle das Abas
    function showTab(tabName) {
        // Esconde todos
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        // Mostra o atual
        document.getElementById('tab-' + tabName).style.display = 'block';
        // Acha o botão clicado (aproximação simples)
        event.target.classList.add('active');
    }

    // Controle do Modal
    const modal = document.getElementById('modalServico');
    const inputTipo = document.getElementById('inputTipo');
    const areaItens = document.getElementById('areaSelecaoItens');
    const modalTitle = document.getElementById('modalTitle');

    function abrirModal(tipo) {
        modal.classList.add('active');
        inputTipo.value = tipo;

        if (tipo === 'pacote') {
            modalTitle.innerText = 'Criar Novo Pacote';
            areaItens.style.display = 'block';
        } else {
            modalTitle.innerText = 'Novo Serviço Individual';
            areaItens.style.display = 'none';
        }
    }

    function fecharModal() {
        modal.classList.remove('active');
    }

    // Matemática do Pacote
    function calcularPacote() {
        // Só calcula se estivermos no modo pacote
        if (inputTipo.value !== 'pacote') return;

        let totalPreco = 0;
        let totalTempo = 0;

        const checkboxes = document.querySelectorAll('.chk-servico:checked');
        
        checkboxes.forEach(chk => {
            totalPreco += parseFloat(chk.getAttribute('data-price'));
            totalTempo += parseInt(chk.getAttribute('data-time'));
        });

        // Preenche os inputs automaticamente
        document.getElementById('inputPreco').value = totalPreco.toFixed(2);
        document.getElementById('inputTempo').value = totalTempo;
    }
</script>