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

$erro      = null;
$sucesso   = null;
$resultado = null;
$editData  = null;

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
    list($qEmbBase,  $uBase2)  = cs_convert_to_base($qtdEmb,   $unEmb);

    if ($uBase1 === $uBase2) {
        return [$qUsadaBase, $qEmbBase, $uBase1];
    }

    // fallback: não dá pra unificar (ex.: kg x ml), usa valores "crus"
    if ($qtdEmb > 0) {
        return [$qtdUsada, $qtdEmb, strtolower(trim($unUsada ?: $unEmb)) ?: 'un'];
    }

    return [$qtdUsada, max($qtdEmb, 1), strtolower(trim($unUsada ?: $unEmb)) ?: 'un'];
}

/* =========================================================
 *  EDITAR / EXCLUIR (GET)
 * =======================================================*/

// EDITAR CÁLCULO DE SERVIÇO
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    $stmtEdit = $pdo->prepare("SELECT * FROM calculo_servico WHERE id=? AND user_id=? LIMIT 1");
    $stmtEdit->execute([$idEdit, $userId]);
    $editData = $stmtEdit->fetch(PDO::FETCH_ASSOC);

    if ($editData) {
        // Materiais
        $stmtMat = $pdo->prepare("SELECT * FROM calculo_servico_materiais WHERE calculo_id=?");
        $stmtMat->execute([$idEdit]);
        $editData['materiais'] = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

        // Taxas
        $stmtTx = $pdo->prepare("SELECT * FROM calculo_servico_taxas WHERE calculo_id=?");
        $stmtTx->execute([$idEdit]);
        $editData['taxas'] = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

        // Preenche $resultado para já mostrar os valores na caixinha roxa
        $resultado = [
            'nome'           => $editData['nome_servico'],
            'valorCobrado'   => (float)$editData['valor_cobrado'],
            'custoMateriais' => (float)$editData['custo_materiais'],
            'custoTaxas'     => (float)$editData['custo_taxas'],
            'custoTotal'     => (float)$editData['custo_materiais'] + (float)$editData['custo_taxas'],
            'lucro'          => (float)$editData['lucro'],
        ];
    }
}

// EXCLUIR CÁLCULO DE SERVIÇO
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $idDel = (int)$_GET['delete'];

    // Segurança: só apaga se pertencer ao usuário
    $stmtCheck = $pdo->prepare("SELECT id FROM calculo_servico WHERE id=? AND user_id=?");
    $stmtCheck->execute([$idDel, $userId]);
    if ($stmtCheck->fetch()) {
        // Remove materiais e taxas vinculados
        $pdo->prepare("DELETE FROM calculo_servico_materiais WHERE calculo_id=?")->execute([$idDel]);
        $pdo->prepare("DELETE FROM calculo_servico_taxas WHERE calculo_id=?")->execute([$idDel]);
        // Remove o cálculo
        $pdo->prepare("DELETE FROM calculo_servico WHERE id=? AND user_id=?")->execute([$idDel, $userId]);
        $_SESSION['calcular_servico_sucesso'] = 'Cálculo excluído com sucesso!';
    }

    header('Location: calcular-servico.php');
    exit;
}

/* =========================================================
 *  FLASH MESSAGES
 * =======================================================*/

if (isset($_SESSION['calcular_servico_resultado'])) {
    // Só sobrescreve $resultado se não estiver em modo edição
    if (!$editData) {
        $resultado = $_SESSION['calcular_servico_resultado'];
    }
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

/* =========================================================
 *  PROCESSAMENTO DO POST (CRIAR / EDITAR)
 * =======================================================*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeServico   = trim($_POST['nome_servico'] ?? '');
    $valorCobrado  = str_replace(',', '.', $_POST['valor_cobrado'] ?? '0');
    $editId        = isset($_POST['edit_id']) && is_numeric($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $isEdit        = $editId > 0;

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

            if ($isEdit) {
                // Atualiza cálculo existente
                $stmt = $pdo->prepare("
                    UPDATE calculo_servico
                       SET nome_servico = ?,
                           valor_cobrado = ?,
                           custo_materiais = ?,
                           custo_taxas = ?,
                           lucro = ?
                     WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $nomeServico,
                    $valorCobrado,
                    $custoMateriaisTotal,
                    $custoTaxasTotal,
                    $lucro,
                    $editId,
                    $userId
                ]);

                // Limpa materiais e taxas antigos
                $pdo->prepare("DELETE FROM calculo_servico_materiais WHERE calculo_id=?")->execute([$editId]);
                $pdo->prepare("DELETE FROM calculo_servico_taxas     WHERE calculo_id=?")->execute([$editId]);

                $calculoId = $editId;
            } else {
                // Novo cálculo
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
            }

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

            $_SESSION['calcular_servico_sucesso'] = $isEdit
                ? 'Cálculo atualizado com sucesso!'
                : 'Cálculo salvo com sucesso!';

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

    // PRG – sempre volta para a tela "limpa" (sem ?edit)
    header('Location: calcular-servico.php');
    exit;
}

/* =========================================================
 *  HISTÓRICO + PRODUTOS
 * =======================================================*/

$stmtHist = $pdo->prepare("
    SELECT * FROM calculo_servico
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtHist->execute([$userId]);
$historico = $stmtHist->fetchAll();

$stmtProdCalc = $pdo->prepare("
    SELECT id, nome, marca, tamanho_embalagem, unidade, custo_unitario
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
        --app-primary-soft: #eef2ff;
        --app-text: #1f2937;
        --app-muted: #6b7280;
        --app-border: #e5e7eb;
        --app-danger: #ef4444;
        --app-success: #10b981;
    }

    body {
        background-color: var(--app-bg);
        color: var(--app-text);
        font-family: 'Inter', -apple-system, system-ui, sans-serif;
        font-size: 13px;
    }

    .main-container {
        max-width: 1040px;
        margin: 24px auto 60px;
        padding: 0 16px;
    }

    .clean-card {
        background: var(--app-card);
        border-radius: 20px;
        padding: 18px 18px 20px;
        box-shadow: 0 10px 28px rgba(148,163,184,0.20);
        border: 1px solid rgba(148,163,184,0.25);
        margin-bottom: 18px;
    }

    .page-header {
        margin-bottom: 18px;
    }
    .page-title {
        font-size: 1.45rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
        letter-spacing: .03em;
    }
    .page-subtitle {
        color: var(--app-muted);
        font-size: 0.86rem;
        margin-top: 4px;
    }
    .page-badge-edit {
        display:inline-flex;
        align-items:center;
        gap:6px;
        margin-top:6px;
        font-size:0.75rem;
        padding:4px 10px;
        border-radius:999px;
        background:#eef2ff;
        color:#4f46e5;
        font-weight:600;
    }

    .form-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
        letter-spacing: .01em;
    }

    .form-control {
        width: 100%;
        padding: 9px 11px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 999px;
        font-size: 0.86rem;
        color: #111827;
        transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--app-primary);
        box-shadow: 0 0 0 2px rgba(79,70,229,0.16);
        background: #fff;
    }

    .calc-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.9fr);
        gap: 18px;
        align-items: flex-start;
    }
    @media (max-width: 900px) {
        .calc-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .material-item {
        background: #f9fafb;
        border: 1px solid var(--app-border);
        border-radius: 16px;
        padding: 12px 12px 14px;
        margin-bottom: 8px;
        position: relative;
    }

    .inline-fields {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr 1.1fr 1.5fr;
        gap: 8px;
        margin-top: 6px;
    }
    @media (max-width: 600px) {
        .inline-fields {
            grid-template-columns: 1fr 1fr;
        }
    }

    .helper-text {
        font-size: 0.7rem;
        color: var(--app-muted);
        margin-top: 2px;
    }

    .btn-add {
        background: #f9fafb;
        color: var(--app-primary);
        border: 1px dashed var(--app-primary);
        padding: 7px 12px;
        border-radius: 999px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.8rem;
        width: 100%;
        transition: 0.18s;
        text-align: center;
    }
    .btn-add:hover {
        background: #eef2ff;
    }

    .btn-remove {
        position: absolute;
        top: 7px;
        right: 10px;
        color: var(--app-danger);
        background: none;
        border: none;
        font-size: 0.75rem;
        cursor: pointer;
        font-weight: 600;
        text-transform: lowercase;
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
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.45);
        transition: 0.18s;
    }
    .btn-primary:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    .result-box {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: white;
        padding: 16px 16px 14px;
        border-radius: 16px;
        margin-top: 16px;
    }
    .result-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        font-size: 0.83rem;
        opacity: 0.95;
    }
    .result-final {
        margin-top: 10px;
        padding-top: 9px;
        border-top: 1px solid rgba(255,255,255,0.28);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .lucro-valor {
        font-size: 1.25rem;
        font-weight: 800;
    }

    .alert {
        padding: 10px 14px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: 0.83rem;
    }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.84rem;
    }
    .history-table td {
        padding: 7px 0;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
    }
    .history-table tr:last-child td { border-bottom: none; }

    .cs-history-actions a {
        text-decoration:none;
        font-size:0.85rem;
        margin-left:8px;
    }

    .cs-product-actions {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }
    .cs-chip {
        border-radius: 999px;
        padding: 7px 12px;
        border: 1px solid var(--app-border);
        background: #fff;
        font-size: 0.78rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        font-weight: 600;
        color: #111827;
        transition: 0.18s;
    }
    .cs-chip-primary {
        background: var(--app-primary-soft);
        border-color: rgba(79,70,229,0.4);
        color: var(--app-primary);
    }
    .cs-chip-primary:hover {
        background: #e0e7ff;
    }
    .cs-chip-outline:hover {
        background: #f3f4ff;
        border-color: rgba(148,163,184,0.8);
    }

    /* bottom sheet produtos (app mobile) */
    .cs-sheet-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.55);
        display: none;
        align-items: flex-end;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(2px);
    }
    .cs-sheet-overlay.active {
        display: flex;
    }
    .cs-sheet {
        background: #f9fafb;
        width: 100%;
        max-width: 480px;
        border-radius: 18px 18px 0 0;
        padding: 14px 14px 18px;
        box-shadow: 0 -8px 24px rgba(15,23,42,0.35);
    }
    .cs-sheet-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 6px;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 8px;
    }
    .cs-sheet-title {
        font-size: 0.95rem;
        font-weight: 600;
    }
    .cs-sheet-close {
        border: none;
        background: transparent;
        font-size: 1.1rem;
        cursor: pointer;
        color: #6b7280;
    }
    .cs-sheet-body {
        max-height: 55vh;
        overflow-y: auto;
        padding-top: 4px;
    }
    .cs-product-row {
        width: 100%;
        text-align: left;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 10px 11px;
        margin-bottom: 8px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 2px;
        transition: 0.16s;
        font-size: 0.82rem;
    }
    .cs-product-row:hover {
        border-color: var(--app-primary);
        box-shadow: 0 4px 10px rgba(15,23,42,0.08);
    }
    .cs-product-row-name {
        font-weight: 600;
        color: #111827;
    }
    .cs-product-row-meta {
        color: #6b7280;
        font-size: 0.75rem;
    }

    @media (min-width: 901px) {
        .main-container {
            margin-top: 32px;
        }
    }
</style>

<div class="main-container">
    <div class="page-header">
        <h1 class="page-title">Calculadora de Lucro</h1>
        <p class="page-subtitle">Use seus produtos do estoque, informe quanto gastou e veja o lucro real de cada serviço.</p>
        <?php if ($editData): ?>
            <div class="page-badge-edit">
                ✏️ Editando cálculo salvo (ID #<?php echo (int)$editData['id']; ?>)
            </div>
        <?php endif; ?>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>

    <form method="post" id="form-calculo">
        <?php if ($editData): ?>
            <input type="hidden" name="edit_id" value="<?php echo (int)$editData['id']; ?>">
        <?php endif; ?>

        <div class="calc-grid">
            <!-- COLUNA ESQUERDA -->
            <div>
                <div class="clean-card">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">Nome do Serviço</label>
                        <input type="text"
                               name="nome_servico"
                               class="form-control"
                               placeholder="Ex: Progressiva longa, Luzes, Corte + Barba"
                               required
                               value="<?php 
                                if ($editData) echo htmlspecialchars($editData['nome_servico']);
                                elseif (isset($_POST['nome_servico'])) echo htmlspecialchars($_POST['nome_servico']);
                                else echo '';
                               ?>">
                    </div>

                    <div class="cs-product-actions">
                        <?php if ($produtosCalc): ?>
                            <button type="button" class="cs-chip cs-chip-primary" onclick="openProdutoSheet()">
                                <span>📦</span> Usar produto do estoque
                            </button>
                            <button type="button" class="cs-chip cs-chip-outline" onclick="addMaterial()">
                                <span>➕</span> Adicionar manual
                            </button>
                        <?php else: ?>
                            <button type="button" class="cs-chip cs-chip-primary" onclick="addMaterial()">
                                <span>➕</span> Adicionar material
                            </button>
                        <?php endif; ?>
                    </div>

                    <label class="form-label" style="margin-top:10px;">Materiais Utilizados</label>
                    <div id="lista-materiais"></div>
                    <button type="button" class="btn-add" onclick="addMaterial()">+ Adicionar Material</button>

                    <?php if ($editData && !empty($editData['materiais'])): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const mats = <?php echo json_encode($editData['materiais']); ?>;
                                const cont = document.getElementById('lista-materiais');
                                cont.innerHTML = '';
                                mats.forEach(function(m) {
                                    addMaterial(m.nome_material, m.unidade, m.quantidade_embalagem, m.preco_produto);
                                    const last = document.querySelectorAll('#lista-materiais .material-item');
                                    const item = last[last.length - 1];
                                    if (item) {
                                        item.querySelector('input[name="materiais_qtd[]"]').value = m.quantidade_usada;
                                        item.querySelector('select[name="materiais_unidade_usada[]"]').value = m.unidade;
                                    }
                                });
                            });
                        </script>
                    <?php endif; ?>

                    <div style="margin-top: 20px;">
                        <label class="form-label">Custos Extras (Taxas por atendimento)</label>
                        <div id="lista-taxas"></div>

                        <?php if ($editData && !empty($editData['taxas'])): ?>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const taxas = <?php echo json_encode($editData['taxas']); ?>;
                                    const cont = document.getElementById('lista-taxas');
                                    cont.innerHTML = '';
                                    taxas.forEach(function(t) {
                                        addTaxa();
                                        const last = document.querySelectorAll('#lista-taxas .material-item');
                                        const item = last[last.length - 1];
                                        if (item) {
                                            item.querySelector('input[name="taxa_nome[]"]').value = t.nome_taxa;
                                            item.querySelector('input[name="taxa_valor[]"]').value = t.valor;
                                        }
                                    });
                                });
                            </script>
                        <?php endif; ?>

                        <button type="button" class="btn-add" onclick="addTaxa()">+ Adicionar Taxa</button>
                    </div>
                </div>
            </div>

            <!-- COLUNA DIREITA -->
            <div>
                <div class="clean-card">
                    <label class="form-label" style="font-size:0.95rem;">Preço de Venda</label>
                    <p class="helper-text" style="margin-bottom: 6px;">Quanto você cobra do cliente nesse serviço?</p>

                    <div style="position: relative; margin-bottom: 8px;">
                        <span style="position:absolute;left:12px;top:9px;font-weight:600;color:#6b7280;">R$</span>
                        <input
                            type="number"
                            step="0.01"
                            name="valor_cobrado"
                            class="form-control"
                            style="padding-left:38px;font-size:1.1rem;font-weight:700;color:var(--app-primary);"
                            required
                            placeholder="0,00"
                            value="<?php 
                                if ($editData) echo htmlspecialchars($editData['valor_cobrado']);
                                elseif (isset($_POST['valor_cobrado'])) echo htmlspecialchars($_POST['valor_cobrado']);
                                else echo '';
                            ?>"
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

                    <button type="submit" class="btn-primary" style="margin-top:16px;">
                        <?php echo $editData ? 'Atualizar cálculo' : 'Calcular agora'; ?>
                    </button>
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
                                    <td class="cs-history-actions" style="text-align:right;white-space:nowrap;">
                                        <a href="?edit=<?php echo $h['id']; ?>" title="Editar" style="color:#4f46e5;">✏️</a>
                                        <a href="?delete=<?php echo $h['id']; ?>"
                                           title="Excluir"
                                           onclick="return confirm('Tem certeza que deseja excluir este cálculo?');"
                                           style="color:#ef4444;">🗑️</a>
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

<?php if ($produtosCalc): ?>
<div class="cs-sheet-overlay" id="produtoSheet">
    <div class="cs-sheet">
        <div class="cs-sheet-header">
            <div class="cs-sheet-title">Escolher produto do estoque</div>
            <button type="button" class="cs-sheet-close" onclick="closeProdutoSheet()">&times;</button>
        </div>
        <div class="cs-sheet-body">
            <?php foreach ($produtosCalc as $p): ?>
                <?php
                    $nomeExibir = $p['nome'] . ($p['marca'] ? ' - ' . $p['marca'] : '');
                    $tamanhoEmb = (float)($p['tamanho_embalagem'] ?? 0);
                    $unMedida   = $p['unidade'] ?: 'un';
                ?>
                <button
                    type="button"
                    class="cs-product-row"
                    data-nome="<?php echo htmlspecialchars($nomeExibir); ?>"
                    data-unidade="<?php echo htmlspecialchars(strtolower($unMedida)); ?>"
                    data-tamanho="<?php echo $tamanhoEmb; ?>"
                    data-preco="<?php echo (float)$p['custo_unitario']; ?>"
                    onclick="selectProdutoFromSheet(this)"
                >
                    <span class="cs-product-row-name"><?php echo htmlspecialchars($p['nome']); ?></span>
                    <?php if ($p['marca']): ?>
                        <span class="cs-product-row-meta"><?php echo htmlspecialchars($p['marca']); ?></span>
                    <?php endif; ?>
                    <span class="cs-product-row-meta">
                        Embalagem: <?php echo $tamanhoEmb; ?> <?php echo htmlspecialchars($unMedida); ?> •
                        Custo pago: R$ <?php echo number_format($p['custo_unitario'], 2, ',', '.'); ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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
            <label class="form-label" style="margin-bottom:2px;">Nome do Produto</label>
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
                    <label class="helper-text">Tamanho da embalagem</label>
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

        // Preenche dados se vieram de produto do estoque / edição
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

    // Bottom sheet – produtos do estoque
    function openProdutoSheet() {
        const sheet = document.getElementById('produtoSheet');
        if (sheet) sheet.classList.add('active');
    }
    function closeProdutoSheet() {
        const sheet = document.getElementById('produtoSheet');
        if (sheet) sheet.classList.remove('active');
    }

    // Quando clica em um produto do estoque
    function selectProdutoFromSheet(el) {
        const nome    = el.dataset.nome || '';
        const unidade = el.dataset.unidade || '';
        const qtdEmb  = el.dataset.tamanho || '';
        const preco   = el.dataset.preco || '';

        addMaterial(nome, unidade, qtdEmb, preco);
        closeProdutoSheet();
    }

    // Fecha sheet ao clicar fora
    document.addEventListener('click', (e) => {
        const overlay = document.getElementById('produtoSheet');
        if (!overlay) return;
        if (e.target === overlay) {
            closeProdutoSheet();
        }
    });

    // Garante pelo menos um material/taxa ao abrir a página NOVA (sem edição)
    document.addEventListener('DOMContentLoaded', () => {
        const hasEdit = <?php echo $editData ? 'true' : 'false'; ?>;
        if (!hasEdit) {
            if (!document.querySelector('#lista-materiais .material-item')) {
                addMaterial();
            }
            if (!document.querySelector('#lista-taxas .material-item')) {
                addTaxa();
            }
        }
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
