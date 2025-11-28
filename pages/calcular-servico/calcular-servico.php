<?php
// pages/gestao/calcular-servico.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
$userId = $_SESSION['user_id'];

require_once __DIR__ . '/../../includes/db.php';

$pageTitle = 'Calcular Serviço';


$erro = null;
$sucesso = null;
$resultado = null;

// Recupera resultado de sessão após redirect (PRG)
if (isset($_SESSION['calcular_servico_resultado'])) {
    $resultado = $_SESSION['calcular_servico_resultado'];
    unset($_SESSION['calcular_servico_resultado']);
}
if (isset($_SESSION['calcular_servico_sucesso'])) {
    $sucesso = $_SESSION['calcular_servico_sucesso'];
    unset($_SESSION['calcular_servico_sucesso']);
}
if (isset($_SESSION['calcular_servico_erro'])) {
    $erro = $_SESSION['calcular_servico_erro'];
    unset($_SESSION['calcular_servico_erro']);
}

/**
 * Converte quantidade para uma unidade base e retorna [quantidadeConvertida, unidadeBase]
 * - massa: base = g  (kg → g)
 * - volume: base = ml (L → ml)
 * - comprimento: base = mm (m, cm → mm)
 * - unidade: base = un
 */
function cs_convert_to_base(float $qtd, string $unit): array
{
    $u = strtolower(trim($unit));

    switch ($u) {
        case 'kg':
            return [$qtd * 1000, 'g'];
        case 'g':
            return [$qtd, 'g'];

        case 'l':
        case 'lt':
        case 'litro':
        case 'litros':
            return [$qtd * 1000, 'ml'];
        case 'ml':
            return [$qtd, 'ml'];

        case 'm':
            return [$qtd * 1000, 'mm'];
        case 'cm':
            return [$qtd * 10, 'mm'];
        case 'mm':
            return [$qtd, 'mm'];

        default:
            // unidade simples de contagem
            return [$qtd, 'un'];
    }
}

/**
 * Tenta unificar duas quantidades (usada e embalagem) na mesma unidade base.
 * Se não forem compatíveis (ex: kg x ml), cai num fallback simples.
 */
function cs_normalizar_medidas(
    float $qtdUsada,
    string $unUsada,
    float $qtdEmb,
    string $unEmb
): array {
    list($qUsadaBase, $uBase1) = cs_convert_to_base($qtdUsada, $unUsada);
    list($qEmbBase, $uBase2)   = cs_convert_to_base($qtdEmb, $unEmb);

    if ($uBase1 === $uBase2) {
        return [$qUsadaBase, $qEmbBase, $uBase1];
    }

    // fallback: não dá pra unificar (ex.: kg x ml), usa valores "crus"
    if ($qtdEmb > 0) {
        return [$qtdUsada, $qtdEmb, strtolower(trim($unUsada ?: $unEmb)) ?: 'un'];
    }

    return [$qtdUsada, max($qtdEmb, 1), strtolower(trim($unUsada ?: $unEmb)) ?: 'un'];
}

// --- PROCESSAMENTO DO PHP ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeServico   = trim($_POST['nome_servico'] ?? '');
    $valorCobrado  = str_replace(',', '.', $_POST['valor_cobrado'] ?? '0');

    $materiaisNome        = $_POST['materiais_nome']           ?? [];
    $materiaisQtd         = $_POST['materiais_qtd']            ?? [];
    $materiaisUnidadeUsed = $_POST['materiais_unidade_usada']  ?? [];
    $materiaisPrecoProd   = $_POST['materiais_preco_prod']     ?? [];
    $materiaisQtdEmb      = $_POST['materiais_qtd_emb']        ?? [];
    $materiaisUnidEmb     = $_POST['materiais_unidade_emb']    ?? [];

    $taxasNome  = $_POST['taxa_nome']  ?? [];
    $taxasValor = $_POST['taxa_valor'] ?? [];

    if ($nomeServico === '' || $valorCobrado <= 0) {
        $_SESSION['calcular_servico_erro'] = 'Informe o nome do serviço e o valor que deseja cobrar.';
    } else {
        $custoMateriaisTotal = 0;
        $custoTaxasTotal     = 0;
        $itensMateriais      = [];
        $itensTaxas          = [];

        // Materiais
        foreach ($materiaisNome as $idx => $nomeMat) {
            $nomeMat = trim($nomeMat);
            if ($nomeMat === '') continue;

            $qtdUsada   = (float) str_replace(',', '.', $materiaisQtd[$idx]        ?? 0);
            $unUsed     = trim($materiaisUnidadeUsed[$idx] ?? '');
            $precoProd  = (float) str_replace(',', '.', $materiaisPrecoProd[$idx]  ?? 0);
            $qtdEmb     = (float) str_replace(',', '.', $materiaisQtdEmb[$idx]     ?? 0);
            $unEmb      = trim($materiaisUnidEmb[$idx]     ?? '');

            if ($qtdUsada <= 0 || $precoProd <= 0 || $qtdEmb <= 0) {
                continue;
            }

            // Normaliza unidades (ex.: 1L e 200ml → base em ml)
            list($qUsadaBase, $qEmbBase, $unBase) = cs_normalizar_medidas(
                $qtdUsada,
                $unUsed,
                $qtdEmb,
                $unEmb
            );

            $custoUnitario = $precoProd / $qEmbBase;   // R$ por unidade base
            $custoUsado    = $custoUnitario * $qUsadaBase;

            $custoMateriaisTotal += $custoUsado;

            $itensMateriais[] = [
                'nome'        => $nomeMat,
                'qtd'         => $qUsadaBase,
                'unidadeBase' => $unBase,
                'precoProd'   => $precoProd,
                'qtdEmbBase'  => $qEmbBase,
                'custoUsado'  => $custoUsado
            ];
        }

        // Taxas / Custos extras
        foreach ($taxasNome as $idx => $nomeTx) {
            $nomeTx = trim($nomeTx);
            if ($nomeTx === '') continue;

            $valorTx = (float) str_replace(',', '.', $taxasValor[$idx] ?? 0);
            if ($valorTx <= 0) continue;

            $custoTaxasTotal += $valorTx;

            $itensTaxas[] = [
                'nome'  => $nomeTx,
                'valor' => $valorTx
            ];
        }

        $custoTotal = $custoMateriaisTotal + $custoTaxasTotal;
        $lucro      = $valorCobrado - $custoTotal;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO calculo_servico (user_id, nome_servico, valor_cobrado, custo_materiais, custo_taxas, lucro, created_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $userId,
                $nomeServico,
                $valorCobrado,
                $custoMateriaisTotal,
                $custoTaxasTotal,
                $lucro
            ]);

            $calculoId = $pdo->lastInsertId();

            if (!empty($itensMateriais)) {
                $stmtMat = $pdo->prepare("
                    INSERT INTO calculo_servico_materiais
                    (calculo_id, nome_material, quantidade_usada, unidade, preco_produto, quantidade_embalagem, custo_calculado)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($itensMateriais as $m) {
                    $stmtMat->execute([
                        $calculoId,
                        $m['nome'],
                        $m['qtd'],
                        $m['unidadeBase'],
                        $m['precoProd'],
                        $m['qtdEmbBase'],
                        $m['custoUsado']
                    ]);
                }
            }

            if (!empty($itensTaxas)) {
                $stmtTx = $pdo->prepare("
                    INSERT INTO calculo_servico_taxas
                    (calculo_id, nome_taxa, valor)
                    VALUES (?, ?, ?)
                ");
                foreach ($itensTaxas as $t) {
                    $stmtTx->execute([
                        $calculoId,
                        $t['nome'],
                        $t['valor']
                    ]);
                }
            }

            $pdo->commit();

            $_SESSION['calcular_servico_sucesso'] = 'Cálculo salvo com sucesso!';
            $_SESSION['calcular_servico_resultado'] = [
                'nome'            => $nomeServico,
                'valorCobrado'    => $valorCobrado,
                'custoMateriais'  => $custoMateriaisTotal,
                'custoTaxas'      => $custoTaxasTotal,
                'custoTotal'      => $custoTotal,
                'lucro'           => $lucro,
                'materiais'       => $itensMateriais,
                'taxas'           => $itensTaxas
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['calcular_servico_erro'] = 'Erro ao salvar cálculo. Tente novamente.';
        }
    }
    // Redireciona para evitar reenvio do formulário (PRG)
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Histórico recente
$stmtHist = $pdo->prepare("
    SELECT * FROM calculo_servico
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtHist->execute([$userId]);
$historico = $stmtHist->fetchAll();

// Produtos cadastrados (para usar no cálculo)
$stmtProdCalc = $pdo->prepare("
    SELECT id, nome, marca, quantidade, unidade, custo_unitario
    FROM produtos
    WHERE user_id = ?
    ORDER BY nome ASC
");
$stmtProdCalc->execute([$userId]);
$produtosCalc = $stmtProdCalc->fetchAll();

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/menu.php';
?>

<style>
    :root {
        --app-bg: #f3f4f6;
        --app-card: #ffffff;
        --app-primary: #4f46e5;
        --app-text: #1f2937;
        --app-muted: #6b7280;
        --app-border: #e5e7eb;
        --app-danger: #ef4444;
        --app-success: #10b981;
    }

    body {
        background-color: var(--app-bg);
        color: var(--app-text);
        font-family: 'Inter', -apple-system, sans-serif;
        font-size: 13px;
    }

    .main-container {
        max-width: 900px;
        margin: 30px auto;
        padding: 0 20px 60px;
    }

    .clean-card {
        background: var(--app-card);
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 4px 8px rgba(15,23,42,0.04);
        border: 1px solid var(--app-border);
        margin-bottom: 20px;
    }

    .page-header {
        margin-bottom: 20px;
    }
    .page-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .page-subtitle {
        color: var(--app-muted);
        font-size: 0.9rem;
        margin-top: 4px;
    }

    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
    }

    .form-control {
        width: 100%;
        padding: 9px 10px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-size: 0.85rem;
        color: #111827;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--app-primary);
        box-shadow: 0 0 0 2px rgba(79,70,229,0.15);
    }

    .calc-grid {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 20px;
    }
    @media (max-width: 768px) {
        .calc-grid { grid-template-columns: 1fr; }
    }

    .material-item {
        background: #f9fafb;
        border: 1px solid var(--app-border);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 10px;
        position: relative;
    }

    .inline-fields {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr 1.1fr 1.5fr;
        gap: 8px;
        margin-top: 8px;
    }
    @media (max-width: 600px) {
        .inline-fields {
            grid-template-columns: 1fr 1fr;
        }
    }

    .helper-text {
        font-size: 0.72rem;
        color: var(--app-muted);
        margin-top: 2px;
    }

    .btn-add {
        background: #fff;
        color: var(--app-primary);
        border: 1px dashed var(--app-primary);
        padding: 7px 12px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.8rem;
        width: 100%;
        transition: 0.2s;
    }
    .btn-add:hover {
        background: #eef2ff;
    }

    .btn-remove {
        position: absolute;
        top: 8px;
        right: 10px;
        color: var(--app-danger);
        background: none;
        border: none;
        font-size: 0.78rem;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-primary {
        background: var(--app-primary);
        color: white;
        border: none;
        padding: 12px 18px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        width: 100%;
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
        transition: 0.18s;
    }
    .btn-primary:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    .result-box {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: white;
        padding: 18px;
        border-radius: 14px;
        margin-top: 18px;
    }
    .result-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 0.85rem;
        opacity: 0.9;
    }
    .result-final {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.25);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .lucro-valor {
        font-size: 1.3rem;
        font-weight: 800;
    }

    .alert {
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 0.85rem;
    }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .history-table td {
        padding: 8px 0;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
    }
    .history-table tr:last-child td { border-bottom: none; }
</style>

<div class="main-container">
    <div class="page-header">
        <h1 class="page-title">Calculadora de Lucro</h1>
        <p class="page-subtitle">Use seus produtos cadastrados, informe quanto gastou e veja o lucro real de cada serviço.</p>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>

    <form method="post" id="form-calculo">
        <div class="calc-grid">
            <!-- COLUNA ESQUERDA -->
            <div>
                <div class="clean-card">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label">Nome do Serviço</label>
                        <input type="text" name="nome_servico" class="form-control"
                               placeholder="Ex: Progressiva longa, Luzes, Corte + Barba"
                               required
                               value="<?php echo isset($_POST['nome_servico']) ? htmlspecialchars($_POST['nome_servico']) : ''; ?>">
                    </div>

                    <?php if ($produtosCalc): ?>
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label class="form-label">Puxar de um produto do estoque</label>
                            <select class="form-control" onchange="addMaterialFromProduto(this)">
                                <option value="">Selecionar produto...</option>
                                <?php foreach ($produtosCalc as $p): ?>
                                    <option
                                        value="<?php echo $p['id']; ?>"
                                        data-nome="<?php
                                            echo htmlspecialchars($p['nome'] . ($p['marca'] ? ' - ' . $p['marca'] : ''));
                                        ?>"
                                        data-unidade="<?php echo htmlspecialchars($p['unidade']); ?>"
                                        data-quantidade="<?php echo (float)$p['quantidade']; ?>"
                                        data-preco="<?php echo (float)$p['custo_unitario']; ?>"
                                    >
                                        <?php echo htmlspecialchars($p['nome']); ?>
                                        <?php if ($p['marca']) echo ' (' . htmlspecialchars($p['marca']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="helper-text">Ao selecionar, ele preenche nome, quantidade da embalagem e custo. Você só coloca quanto usou no serviço.</p>
                        </div>
                    <?php endif; ?>

                    <label class="form-label">Materiais Utilizados</label>
                    <div id="lista-materiais"></div>
                    <button type="button" class="btn-add" onclick="addMaterial()">+ Adicionar Material</button>

                    <div style="margin-top: 24px;">
                        <label class="form-label">Custos Extras (Taxas por atendimento)</label>
                        <div id="lista-taxas">
                            <div class="material-item" style="display:flex;gap:10px;align-items:center;">
                                <div style="flex:2;">
                                    <input type="text" name="taxa_nome[]" class="form-control" placeholder="Ex: Luz, Água, Aluguel">
                                </div>
                                <div style="flex:1;">
                                    <input type="number" step="0.01" name="taxa_valor[]" class="form-control" placeholder="R$">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-add" onclick="addTaxa()">+ Adicionar Taxa</button>
                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA -->
            <div>
                <div class="clean-card">
                    <label class="form-label" style="font-size:1.05rem;">Preço de Venda</label>
                    <p class="helper-text" style="margin-bottom: 6px;">Quanto você cobra do cliente nesse serviço?</p>

                    <div style="position: relative; margin-bottom: 10px;">
                        <span style="position:absolute;left:12px;top:10px;font-weight:600;color:#6b7280;">R$</span>
                        <input
                            type="number"
                            step="0.01"
                            name="valor_cobrado"
                            class="form-control"
                            style="padding-left:38px;font-size:1.1rem;font-weight:700;color:var(--app-primary);"
                            required
                            placeholder="0,00"
                            value="<?php echo isset($_POST['valor_cobrado']) ? htmlspecialchars($_POST['valor_cobrado']) : ''; ?>"
                        >
                    </div>

                    <div class="result-box">
                        <div class="result-row">
                            <span>Custos de materiais</span>
                            <span>R$ <?php echo $resultado ? number_format($resultado['custoMateriais'], 2, ',', '.') : '0,00'; ?></span>
                        </div>
                        <div class="result-row">
                            <span>Taxas / custos extras</span>
                            <span>R$ <?php echo $resultado ? number_format($resultado['custoTaxas'], 2, ',', '.') : '0,00'; ?></span>
                        </div>
                        <div class="result-row" style="font-weight:600;">
                            <span>Custo total</span>
                            <span>- R$ <?php echo $resultado ? number_format($resultado['custoTotal'], 2, ',', '.') : '0,00'; ?></span>
                        </div>
                        <div class="result-final">
                            <span>LUCRO LÍQUIDO</span>
                            <span class="lucro-valor">
                                R$ <?php echo $resultado ? number_format($resultado['lucro'], 2, ',', '.') : '0,00'; ?>
                            </span>
                        </div>
                        <?php if ($resultado && $resultado['valorCobrado'] > 0): ?>
                            <div style="text-align:right;font-size:0.8rem;margin-top:4px;opacity:0.85;">
                                Margem: <?php echo number_format(($resultado['lucro'] / $resultado['valorCobrado']) * 100, 1, ',', '.'); ?>%
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-primary" style="margin-top:18px;">Calcular agora</button>
                </div>

                <?php if ($historico && count($historico) > 0): ?>
                    <div class="clean-card">
                        <label class="form-label">Últimos cálculos</label>
                        <table class="history-table">
                            <tbody>
                            <?php foreach ($historico as $h): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($h['nome_servico']); ?></strong><br>
                                        <small style="color:#9ca3af;"><?php echo date('d/m H:i', strtotime($h['created_at'])); ?></small>
                                    </td>
                                    <td style="text-align:right;color:#10b981;font-weight:600;">
                                        R$ <?php echo number_format($h['lucro'], 2, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
    // Opções de unidade usadas nos selects
    const CS_UNIT_OPTIONS = `
        <option value="ml">ml</option>
        <option value="l">L</option>
        <option value="g">g</option>
        <option value="kg">kg</option>
        <option value="un">unidade</option>
        <option value="m">m</option>
        <option value="cm">cm</option>
        <option value="mm">mm</option>
    `;

    function addMaterial(nome = '', unidade = '', qtdEmb = '', preco = '') {
        const container = document.getElementById('lista-materiais');
        const div = document.createElement('div');
        div.className = 'material-item';
        div.innerHTML = `
            <button type="button" class="btn-remove" onclick="this.closest('.material-item').remove()">remover</button>
            <label class="form-label" style="margin-bottom:4px;">Nome do Produto</label>
            <input type="text" name="materiais_nome[]" class="form-control" placeholder="Ex: Progressiva, Tintura, Botox">

            <div class="inline-fields">
                <div>
                    <label class="helper-text">Quantidade usada</label>
                    <input type="number" step="0.01" name="materiais_qtd[]" class="form-control" placeholder="Ex: 60">
                </div>
                <div>
                    <label class="helper-text">Unidade usada</label>
                    <select name="materiais_unidade_usada[]" class="form-control">
                        ${CS_UNIT_OPTIONS}
                    </select>
                </div>
                <div>
                    <label class="helper-text">Valor pago no produto</label>
                    <input type="number" step="0.01" name="materiais_preco_prod[]" class="form-control" placeholder="R$ total">
                </div>
                <div>
                    <label class="helper-text">Tamanho embalagem</label>
                    <div style="display:flex;gap:4px;">
                        <input type="number" step="0.01" name="materiais_qtd_emb[]" class="form-control" placeholder="Ex: 1000">
                        <select name="materiais_unidade_emb[]" class="form-control" style="max-width:80px;">
                            ${CS_UNIT_OPTIONS}
                        </select>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);

        // Preenche dados se vieram de produto
        if (nome) {
            div.querySelector('input[name="materiais_nome[]"]').value = nome;
        }
        if (preco) {
            div.querySelector('input[name="materiais_preco_prod[]"]').value = preco;
        }
        if (qtdEmb) {
            div.querySelector('input[name="materiais_qtd_emb[]"]').value = qtdEmb;
        }
        if (unidade) {
            const selUsada = div.querySelector('select[name="materiais_unidade_usada[]"]');
            const selEmb   = div.querySelector('select[name="materiais_unidade_emb[]"]');
            selUsada.value = unidade.toLowerCase();
            selEmb.value   = unidade.toLowerCase();
        }
    }

    function addMaterialFromProduto(select) {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return;

        const nome     = opt.getAttribute('data-nome') || '';
        const unidade  = opt.getAttribute('data-unidade') || '';
        const qtdEmb   = opt.getAttribute('data-quantidade') || '';
        const preco    = opt.getAttribute('data-preco') || '';

        addMaterial(nome, unidade, qtdEmb, preco);

        // volta para opção vazia
        select.value = '';
    }

    function addTaxa() {
        const container = document.getElementById('lista-taxas');
        const div = document.createElement('div');
        div.className = 'material-item';
        div.style.display = 'flex';
        div.style.gap = '10px';
        div.style.alignItems = 'center';
        div.innerHTML = `
            <div style="flex:2;">
                <input type="text" name="taxa_nome[]" class="form-control" placeholder="Nome da taxa">
            </div>
            <div style="flex:1;">
                <input type="number" step="0.01" name="taxa_valor[]" class="form-control" placeholder="R$">
            </div>
            <button type="button" class="btn-remove" onclick="this.closest('.material-item').remove()">x</button>
        `;
        container.appendChild(div);
    }

    // Garante pelo menos um material ao abrir a página
    document.addEventListener('DOMContentLoaded', () => {
        if (!document.querySelector('#lista-materiais .material-item')) {
            addMaterial();
        }
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
