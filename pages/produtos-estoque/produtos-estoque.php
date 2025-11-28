<?php
$pageTitle = 'Produtos & Estoque';
include '../../includes/db.php';

// Simulação de Login
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- 1. PROCESSAMENTO DE AÇÕES ---

// A. Excluir
if (isset($_GET['delete'])) {
    $idDel = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE id=? AND user_id=?");
    $stmt->execute([$idDel, $userId]);
    header("Location: produtos-estoque.php?status=deleted");
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

    // Calcula o estoque base (quantidade de embalagens * tamanho de cada uma)
    $estoqueBase = $quantidade * $tamanho;

    if ($id > 0) {
        $sql = "UPDATE produtos 
                   SET nome=?, marca=?, quantidade=?, tamanho_embalagem=?, unidade=?, 
                       custo_unitario=?, preco_venda=?, data_compra=?, data_validade=?, observacoes=?,
                       estoque_atual_base=?
                 WHERE id=? AND user_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nome, $marca, $quantidade, $tamanho, $unidade,
            $custo, $venda, $dataCompra, $dataValidade, $obs,
            $estoqueBase, $id, $userId
        ]);
        $status = 'updated';
    } else {
        $sql = "INSERT INTO produtos 
                    (user_id, nome, marca, quantidade, tamanho_embalagem, unidade, custo_unitario, preco_venda, data_compra, data_validade, observacoes, estoque_atual_base)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId, $nome, $marca, $quantidade, $tamanho, $unidade,
            $custo, $venda, $dataCompra, $dataValidade, $obs,
            $estoqueBase
        ]);
        $status = 'saved';
    }

    header("Location: produtos-estoque.php?status={$status}");
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

include '../../includes/header.php';
?>

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

    <div class="table-container">
        <?php if (count($produtos) > 0): ?>
            <div class="table-scroller">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 38%;">Produto</th>
                            <th>Tamanho Un.</th>
                            <th>Qtd Estoque</th>
                            <th>Custo Pago</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): 
                            $validade = $p['data_validade'] ? strtotime($p['data_validade']) : null;
                            $hoje = strtotime(date('Y-m-d'));
                            
                            $statusBadge = '<span class="badge bg-green">OK</span>';
                            if ($p['quantidade'] <= 2) $statusBadge = '<span class="badge bg-red">Baixo</span>';
                            if ($validade && $validade < $hoje) $statusBadge = '<span class="badge bg-red">Vencido</span>';
                        ?>
                        <tr
                            class="product-row"
                            onclick="openProductSheet(this)"
                            data-id="<?php echo $p['id']; ?>"
                            data-nome="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES); ?>"
                            data-marca="<?php echo htmlspecialchars($p['marca'], ENT_QUOTES); ?>"
                            data-qtd="<?php echo $p['quantidade']; ?>"
                            data-tamanho="<?php echo number_format($p['tamanho_embalagem'], 0, ',', '.'); ?>"
                            data-unidade="<?php echo htmlspecialchars($p['unidade']); ?>"
                            data-custo="<?php echo number_format($p['custo_unitario'], 2, ',', '.'); ?>"
                            data-validade="<?php echo $validade ? date('d/m/Y', $validade) : ''; ?>"
                            data-obs="<?php echo htmlspecialchars($p['observacoes'] ?? '', ENT_QUOTES); ?>"
                            data-status="<?php echo strip_tags($statusBadge); ?>"
                        >
                            <td>
                                <div class="prod-name"><?php echo htmlspecialchars($p['nome']); ?></div>
                                <?php if (!empty($p['marca'])): ?>
                                    <div class="prod-brand"><?php echo htmlspecialchars($p['marca']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="prod-volume">
                                    <?php echo number_format($p['tamanho_embalagem'], 0, ',', '.'); ?>
                                    <span><?php echo htmlspecialchars($p['unidade']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-gray"><?php echo $p['quantidade']; ?> un</span>
                            </td>
                            <td class="price-mono">
                                R$ <?php echo number_format($p['custo_unitario'], 2, ',', '.'); ?>
                            </td>
                            <td>
                                <?php echo $statusBadge; ?>
                                <div class="status-date">
                                    <?php echo $validade ? date('d/m/Y', $validade) : '-'; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>Nenhum produto cadastrado ainda. Comece adicionando seu primeiro item de estoque.</p>
            </div>
        <?php endif; ?>
    </div>
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
    // ---- Modal de cadastro/edição ----
    function openModal(id, title) {
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('produtoId').value = id;
        document.getElementById('productModal').classList.add('active');
        
        // Se clicou em editar mas ainda não veio do PHP com o produto carregado,
        // recarrega a página com o ?edit=ID para preencher os campos
        if (id > 0 && !<?php echo json_encode((bool)$produtoEdicao); ?>) {
            window.location.href = 'produtos-estoque.php?edit=' + id;
            return;
        }
        atualizarDica();
    }

    function closeModal() {
        document.getElementById('productModal').classList.remove('active');
        if (window.location.search.includes('edit=')) {
            const url = new URL(window.location);
            url.searchParams.delete('edit');
            window.history.pushState({}, '', url);
        }
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
