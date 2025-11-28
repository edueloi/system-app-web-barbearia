
<?php
$pageTitle = 'Produtos & Estoque';
include '../../includes/db.php';

// Simulação de Login
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// Variáveis de Sucesso/Erro
$msg = '';

// --- 1. PROCESSAMENTO DE AÇÕES (CRUD) ---

// A. Excluir Produto
if (isset($_GET['delete'])) {
    $idDel = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM produtos WHERE id=? AND user_id=?")->execute([$idDel, $userId]);
    echo "<script>window.location.href='produtos-estoque.php';</script>";
    exit;
}

// B. Salvar Produto (Novo ou Edição)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_produto') {
    $id = (int)$_POST['produto_id'];
    $nome = $_POST['nome'];
    $marca = $_POST['marca'];
    $quantidade = (int)$_POST['quantidade'];
    $unidade = $_POST['unidade'];
    $custo = str_replace(',', '.', $_POST['custo_unitario']);
    $venda = str_replace(',', '.', $_POST['preco_venda']);
    $dataCompra = $_POST['data_compra'] ?: null;
    $dataValidade = $_POST['data_validade'] ?: null;
    $obs = $_POST['observacoes'];
    
    // Tratamento de VAZIO para evitar erro de float em SQLite
    $custo = empty($custo) ? 0.00 : (float)$custo;
    $venda = empty($venda) ? 0.00 : (float)$venda;

    if ($id > 0) {
        // Atualizar
        $sql = "UPDATE produtos SET nome=?, marca=?, quantidade=?, unidade=?, custo_unitario=?, preco_venda=?, data_compra=?, data_validade=?, observacoes=? WHERE id=? AND user_id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $marca, $quantidade, $unidade, $custo, $venda, $dataCompra, $dataValidade, $obs, $id, $userId]);
    } else {
        // Novo
        $sql = "INSERT INTO produtos (user_id, nome, marca, quantidade, unidade, custo_unitario, preco_venda, data_compra, data_validade, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $nome, $marca, $quantidade, $unidade, $custo, $venda, $dataCompra, $dataValidade, $obs]);
    }
    header("Location: produtos-estoque.php?status=saved");
    exit;
}

// --- 2. BUSCAR DADOS ---

// Buscar todos os produtos
$produtos = $pdo->query("SELECT * FROM produtos WHERE user_id = {$userId} ORDER BY nome ASC")->fetchAll();

// Inclui header e menu só depois do processamento de POST/GET
include '../../includes/header.php';
include '../../includes/menu.php';

// Calcular Valor Total de Custo em Estoque
$custoTotalEstoque = 0;
foreach($produtos as $p) {
    $custoTotalEstoque += ($p['quantidade'] * $p['custo_unitario']);
}

// Buscar produto para edição (se houver ID na URL)
$produtoEdicao = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND user_id = ?");
    $stmt->execute([$idEdit, $userId]);
    $produtoEdicao = $stmt->fetch();
}
?>

<style>
    /* Estilos Específicos */
    .header-info {
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
        padding: 20px;
        border-radius: var(--radius);
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #cbd5e1;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    .stock-value { font-size: 1.8rem; font-weight: 700; color: #16a34a; }

    /* Botões de Ação */
    .btn-add {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none; /* Para usar em <a> */
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
    }
    .btn-add:hover { background: var(--primary-hover); }

    /* Tabela de Produtos */
    .products-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    .products-table thead th {
        text-align: left;
        color: var(--text-gray);
        font-size: 0.8rem;
        font-weight: 600;
        padding: 10px 15px;
    }
    .products-table tbody td {
        background: var(--white);
        padding: 15px;
        font-size: 0.95rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .products-table tbody tr:last-child td { border-bottom: none; }
    
    .products-table tbody tr {
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border-radius: 12px;
    }
    .products-table tbody tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .products-table tbody tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

    /* Tags de Status */
    .stock-tag { 
        padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.8rem;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .tag-low { background: #fee2e2; color: var(--danger); } /* Estoque Baixo */
    .tag-ok { background: #dcfce7; color: var(--success); } /* Estoque OK */
    .tag-expired { background: #ffedd5; color: #f59e0b; } /* Expirando */

    /* Coluna de Ações */
    .action-btns a {
        color: var(--text-gray);
        margin-left: 10px;
        font-size: 1.1rem;
        transition: color 0.2s;
    }
    .action-btns a:hover { color: var(--primary); }
    .action-btns a.delete-btn:hover { color: var(--danger); }

    /* Modal Form */
    .modal-overlay {
        display: none;
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;
        backdrop-filter: blur(2px);
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
        background: white; padding: 25px; border-radius: 20px; width: 90%; max-width: 500px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-gray); display: block; margin-bottom: 5px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
</style>

<main class="main-content">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">Produtos & Estoque</h2>
    </div>

    <div class="header-info">
        <div>
            <span style="font-size: 0.9rem; color: var(--text-gray);">Custo Total em Estoque</span>
            <div class="stock-value">R$ <?php echo number_format($custoTotalEstoque, 2, ',', '.'); ?></div>
        </div>
        <a href="#" class="btn-add" onclick="openModal(0, 'Adicionar Novo Produto')">
            <i class="bi bi-box-fill"></i> Novo Produto
        </a>
    </div>

    <div class="app-card" style="padding: 0;">
        <?php if (count($produtos) > 0): ?>
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">PRODUTO / MARCA</th>
                        <th style="width: 15%;">ESTOQUE</th>
                        <th style="width: 15%;">CUSTO UN.</th>
                        <th style="width: 15%;">VALIDADE</th>
                        <th style="width: 15%;">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): 
                        $validade = strtotime($p['data_validade']);
                        $hoje = strtotime(date('Y-m-d'));
                        $diasRestantes = $validade ? floor(($validade - $hoje) / (60 * 60 * 24)) : 999;
                        
                        $tagClass = 'tag-ok';
                        $tagText = 'OK';
                        
                        if ($p['quantidade'] < 5) {
                            $tagClass = 'tag-low';
                            $tagText = 'Baixo';
                        }
                        if ($validade && $diasRestantes < 30 && $diasRestantes >= 0) {
                            $tagClass = 'tag-expired';
                            $tagText = 'Expira em breve';
                        } elseif ($validade && $diasRestantes < 0) {
                             $tagClass = 'tag-low';
                            $tagText = 'Vencido!';
                        }
                    ?>
                    <tr>
                        <td>
                            <strong style="display:block;"><?php echo htmlspecialchars($p['nome']); ?></strong>
                            <small style="color:var(--text-gray);"><?php echo htmlspecialchars($p['marca']); ?></small>
                        </td>
                        <td>
                            <div class="stock-tag <?php echo $tagClass; ?>">
                                <i class="bi bi-box-seam"></i> 
                                <?php echo $p['quantidade']; ?> <?php echo htmlspecialchars($p['unidade']); ?>
                            </div>
                        </td>
                        <td>R$ <?php echo number_format($p['custo_unitario'], 2, ',', '.'); ?></td>
                        <td>
                            <?php echo $p['data_validade'] ? date('d/m/Y', $validade) : '-'; ?>
                        </td>
                        <td class="action-btns">
                            <a href="#" title="Editar Produto" onclick="openModal(<?php echo $p['id']; ?>, 'Editar Produto')">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="delete-btn" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este produto do estoque?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align:center; padding:50px; color:var(--text-gray);">
                <i class="bi bi-box-seam" style="font-size:3rem; opacity:0.3; display:block; margin-bottom:10px;"></i>
                <p>Nenhum produto cadastrado no estoque.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal-overlay <?php echo $produtoEdicao ? 'active' : ''; ?>" id="productModal">
    <div class="modal-box">
        <h3 id="modalTitle">
            <?php echo $produtoEdicao ? 'Editar Produto' : 'Adicionar Novo Produto'; ?>
        </h3>
        <button onclick="closeModal()" style="float:right; margin-top:-35px; background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_produto">
            <input type="hidden" name="produto_id" id="produtoId" value="<?php echo $produtoEdicao['id'] ?? 0; ?>">

            <div class="form-grid">
                
                <div class="form-group full-width">
                    <label>Nome do Produto (Shampoo, Tintura, etc.)</label>
                    <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($produtoEdicao['nome'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" class="form-control" value="<?php echo htmlspecialchars($produtoEdicao['marca'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Quantidade em Estoque</label>
                    <input type="number" name="quantidade" class="form-control" required value="<?php echo $produtoEdicao['quantidade'] ?? 1; ?>">
                </div>
                
                <div class="form-group">
                    <label>Unidade de Medida</label>
                    <input type="text" name="unidade" class="form-control" placeholder="Ex: ml, litro, unidade" value="<?php echo htmlspecialchars($produtoEdicao['unidade'] ?? 'unidade'); ?>">
                </div>

                <div class="form-group">
                    <label>Custo Unitário (Valor Pago)</label>
                    <input type="number" step="0.01" name="custo_unitario" class="form-control" required value="<?php echo $produtoEdicao['custo_unitario'] ?? 0.00; ?>">
                </div>
                
                <div class="form-group">
                    <label>Preço de Venda (Opcional)</label>
                    <input type="number" step="0.01" name="preco_venda" class="form-control" value="<?php echo $produtoEdicao['preco_venda'] ?? 0.00; ?>">
                </div>

                <div class="form-group">
                    <label>Data da Compra</label>
                    <input type="date" name="data_compra" class="form-control" value="<?php echo $produtoEdicao['data_compra'] ?? date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Data de Validade</label>
                    <input type="date" name="data_validade" class="form-control" value="<?php echo $produtoEdicao['data_validade'] ?? ''; ?>">
                </div>

                <div class="form-group full-width">
                    <label>Observações</label>
                    <textarea name="observacoes" class="form-control" rows="2"><?php echo htmlspecialchars($produtoEdicao['observacoes'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <button type="submit" class="btn-add" style="width:100%;">Salvar Produto</button>
                </div>
            </div>
        </form>
    </div>
</div>


<?php include '../../includes/footer.php'; ?>

<script>
    // Se a página foi carregada com ?edit=ID, o modal já está ativo (ver PHP)

    function openModal(id, title) {
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('produtoId').value = id;
        document.getElementById('productModal').classList.add('active');
        
        // Se for edição, recarrega a página com o parâmetro de edição para preencher os campos.
        if (id > 0 && !<?php echo json_encode((bool)$produtoEdicao); ?>) {
             window.location.href = 'produtos-estoque.php?edit=' + id;
        }
    }

    function closeModal() {
        document.getElementById('productModal').classList.remove('active');
        
        // Limpa o parâmetro da URL se estava editando para não reabrir
        if (window.location.search.includes('edit=')) {
            window.history.pushState({}, document.title, "produtos-estoque.php");
        }
    }
</script>