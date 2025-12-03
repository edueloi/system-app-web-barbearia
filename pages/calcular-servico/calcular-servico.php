<?php
// pages/gestao/calcular-servico.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
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

        // 🔹 Descobre se está em produção (salao.develoi.com) ou local
        $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        $calcUrl = $isProd ? '/calcular-servico' : '/karen_site/controle-salao/pages/calcular-servico/calcular-servico.php';
        header("Location: {$calcUrl}");
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

    $materiaisProdutoId   = $_POST['materiais_produto_id']     ?? [];
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

            $produtoId = isset($materiaisProdutoId[$idx]) ? (int)$materiaisProdutoId[$idx] : 0;
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
                'produto_id'  => $produtoId ?: null,
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
                       SET nome_servico   = ?,
                           valor_cobrado  = ?,
                           custo_materiais= ?,
                           custo_taxas    = ?,
                           lucro          = ?
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
                    INSERT INTO calculo_servico
                    (user_id, nome_servico, valor_cobrado, custo_materiais, custo_taxas, lucro, created_at)
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
                    (calculo_id, produto_id, nome_material, quantidade_usada, unidade, preco_produto, quantidade_embalagem, custo_calculado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($itensMateriais as $m) {
                    $stmtMat->execute([
                        $calculoId,
                        $m['produto_id'],
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
    // 🔹 Descobre se está em produção (salao.develoi.com) ou local
    $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
    $calcUrl = $isProd ? '/calcular-servico' : '/karen_site/controle-salao/pages/calcular-servico/calcular-servico.php';
    header("Location: {$calcUrl}");
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

// Se não estiver global, pode incluir aqui:
include_once __DIR__ . '/../../includes/ui-toast.php';
include_once __DIR__ . '/../../includes/ui-confirm.php';
?>

<style>
    :root {
        --primary-color: #4f46e5;
        --primary-dark: #4338ca;
        --accent: #ec4899;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-soft: 0 6px 18px rgba(15,23,42,0.06);
        --shadow-hover: 0 14px 30px rgba(15,23,42,0.10);
    }

    * {
        box-sizing: border-box;
    }

    body {
        background: transparent;
        font-family: -apple-system, BlinkMacSystemFont, "Outfit", "Inter", system-ui, sans-serif;
        font-size: 0.875rem;
        color: var(--text-main);
        min-height: 100vh;
        line-height: 1.5;
    }

    .main-container {
        max-width: 1040px;
        margin: 0 auto;
        padding: 20px 12px 110px 12px;
    }

    .clean-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 18px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
        transition: all 0.25s ease;
    }

    .clean-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-1px);
    }

    .page-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 18px;
        margin-bottom: 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
    }

    .page-title {
        font-size: 1.35rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary-color), var(--accent));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        letter-spacing: -0.03em;
        line-height: 1.2;
    }

    .page-subtitle {
        margin: 6px 0 0;
        color: var(--text-muted);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .page-badge-edit {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(79,70,229,0.08);
        color: var(--primary-color);
        font-weight: 700;
    }

    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 6px;
        letter-spacing: 0.01em;
    }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        background: #f1f5f9;
        border: 1px solid rgba(148,163,184,0.3);
        border-radius: 10px;
        font-size: 0.85rem;
        color: var(--text-main);
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.18);
        background: #ffffff;
    }

    .calc-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.9fr);
        gap: 16px;
        align-items: flex-start;
    }

    .material-item {
        background: #f9fafb;
        border: 1px solid rgba(209,213,219,0.9);
        border-radius: 14px;
        padding: 12px 12px 14px;
        margin-bottom: 10px;
        position: relative;
        transition: all 0.2s ease;
    }

    .material-item:hover {
        background: #ffffff;
        border-color: rgba(79,70,229,0.35);
        box-shadow: 0 3px 10px rgba(15,23,42,0.06);
    }

    .inline-fields {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr 1.5fr;
        gap: 8px;
        margin-top: 8px;
    }

    .single-field {
        margin-top: 8px;
    }

    .helper-text {
        font-size: 0.7rem;
        color: var(--text-muted);
        margin-bottom: 4px;
        font-weight: 600;
    }

    .btn-add {
        background: #f9fafb;
        color: var(--primary-color);
        border: 1px dashed rgba(148,163,184,0.8);
        padding: 10px 12px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.85rem;
        width: 100%;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-add:hover {
        border-color: var(--primary-color);
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(15,23,42,0.05);
    }

    .btn-remove {
        position: absolute;
        top: 8px;
        right: 10px;
        color: #ef4444;
        background: rgba(239,68,68,0.08);
        border: none;
        font-size: 0.7rem;
        cursor: pointer;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-remove:hover {
        background: #fee2e2;
        transform: translateY(-1px);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 11px 18px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        width: 100%;
        box-shadow: 0 4px 10px rgba(79,70,229,0.25);
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(79,70,229,0.35);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .result-box {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 14px 16px 16px;
        border-radius: 14px;
        margin-top: 14px;
        box-shadow: 0 8px 20px rgba(79,70,229,0.35);
    }

    .result-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 0.8rem;
        opacity: 0.95;
        font-weight: 500;
    }

    .result-final {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid rgba(255,255,255,0.25);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
    }

    .lucro-valor {
        font-size: 1.3rem;
        font-weight: 800;
    }

    .alert {
        padding: 12px 14px;
        border-radius: 12px;
        margin-bottom: 14px;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .alert-success {
        background: #dcfce7;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .history-table td {
        padding: 10px 0;
        border-bottom: 1px solid rgba(148,163,184,0.2);
        color: var(--text-main);
    }

    .history-table tr:last-child td {
        border-bottom: none;
    }

    .cs-history-actions a {
        text-decoration: none;
        font-size: 1rem;
        margin-left: 10px;
        transition: transform 0.2s;
        display: inline-block;
    }

    .cs-history-actions a:hover {
        transform: scale(1.15);
    }

    .cs-product-actions {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .cs-chip {
        border-radius: 999px;
        padding: 8px 14px;
        border: 1px solid rgba(148,163,184,0.3);
        background: #fff;
        font-size: 0.78rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        font-weight: 600;
        color: var(--text-main);
        transition: all 0.2s ease;
    }

    .cs-chip-primary {
        background: rgba(79,70,229,0.08);
        border-color: rgba(79,70,229,0.3);
        color: var(--primary-color);
    }

    .cs-chip-primary:hover {
        background: rgba(79,70,229,0.15);
        border-color: var(--primary-color);
        transform: translateY(-1px);
    }

    .cs-chip-outline:hover {
        background: #f9fafb;
        border-color: rgba(148,163,184,0.6);
        transform: translateY(-1px);
    }

    /* Bottom sheet produtos */
    .cs-sheet-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.6);
        display: none;
        align-items: flex-end;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(3px);
    }

    .cs-sheet-overlay.active {
        display: flex;
    }

    .cs-sheet {
        background: #ffffff;
        width: 100%;
        max-width: 520px;
        border-radius: 18px 18px 0 0;
        padding: 16px 16px 20px;
        box-shadow: 0 -8px 24px rgba(15,23,42,0.4);
    }

    .cs-sheet-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(148,163,184,0.25);
        margin-bottom: 12px;
    }

    .cs-sheet-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
    }

    .cs-sheet-close {
        border: none;
        background: rgba(148,163,184,0.1);
        width: 32px;
        height: 32px;
        border-radius: 8px;
        font-size: 1.3rem;
        cursor: pointer;
        color: var(--text-muted);
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cs-sheet-close:hover {
        background: rgba(239,68,68,0.1);
        color: #ef4444;
    }

    .cs-sheet-body {
        max-height: 60vh;
        overflow-y: auto;
        padding-top: 4px;
    }

    .cs-product-row {
        width: 100%;
        text-align: left;
        border-radius: 12px;
        border: 1px solid rgba(148,163,184,0.25);
        background: #f9fafb;
        padding: 10px 12px;
        margin-bottom: 8px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 3px;
        transition: all 0.2s ease;
        font-size: 0.8rem;
    }

    .cs-product-row:hover {
        border-color: var(--primary-color);
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(15,23,42,0.1);
        transform: translateY(-1px);
    }

    .cs-product-row-name {
        font-weight: 700;
        color: var(--text-main);
        font-size: 0.85rem;
    }

    .cs-product-row-meta {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 500;
    }

    /* Responsivo Mobile */
    @media (max-width: 900px) {
        .calc-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 768px) {
        .main-container {
            padding: 18px 10px 120px 10px;
        }

        .page-header {
            padding: 14px 14px;
            border-radius: 14px;
        }

        .page-title {
            font-size: 1.25rem;
        }

        .page-subtitle {
            font-size: 0.8rem;
        }

        .clean-card {
            padding: 14px 14px;
            border-radius: 14px;
        }

        .form-control {
            font-size: 0.82rem;
            padding: 8px 10px;
        }

        .material-item {
            padding: 10px 10px 12px;
            border-radius: 12px;
        }

        .inline-fields {
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .inline-fields .cs-field-full {
            grid-column: 1 / -1;
        }

        .btn-add {
            padding: 9px 10px;
            font-size: 0.82rem;
        }

        .btn-primary {
            padding: 10px 16px;
            font-size: 0.88rem;
        }

        .result-box {
            padding: 12px 14px 14px;
        }

        .result-row {
            font-size: 0.78rem;
        }

        .lucro-valor {
            font-size: 1.15rem;
        }

        .history-table {
            font-size: 0.8rem;
        }

        .history-table td {
            padding: 8px 0;
        }

        .cs-chip {
            font-size: 0.75rem;
            padding: 7px 12px;
        }
    }

    @media (max-width: 600px) {
        .inline-fields {
            grid-template-columns: 1fr;
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

    <?php if ($sucesso): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.AppToast) {
                    AppToast.show(<?php echo json_encode($sucesso); ?>, 'success');
                }
            });
        </script>
    <?php endif; ?>

    <?php if ($erro): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.AppToast) {
                    AppToast.show(<?php echo json_encode($erro); ?>, 'danger');
                }
            });
        </script>
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
                                        <a href="#"
                                           class="cs-delete-link"
                                           data-id="<?php echo (int)$h['id']; ?>"
                                           data-name="<?php echo htmlspecialchars($h['nome_servico']); ?>"
                                           title="Excluir"
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
                    data-id="<?php echo (int)$p['id']; ?>"
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

    function addMaterial(nome = '', unidade = '', qtdEmb = '', preco = '', produtoId = '') {
        const container = document.getElementById('lista-materiais');
        const div = document.createElement('div');
        div.className = 'material-item';
        div.innerHTML = `
            <button type="button" class="btn-remove" onclick="this.closest('.material-item').remove()">remover</button>
            
            <input type="hidden" name="materiais_produto_id[]" value=""> <!-- NOVO -->

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
                <div class="cs-field-full">
                    <label class="helper-text">Tamanho da embalagem</label>
                    <div style="display:flex;gap:4px;">
                        <input type="number" step="0.01" name="materiais_qtd_emb[]" class="form-control" placeholder="Ex: 1000">
                        <select name="materiais_unidade_emb[]" class="form-control" style="max-width:80px;">
                            ${CS_UNIT_OPTIONS}
                        </select>
                    </div>
                </div>
            </div>

            <div class="single-field">
                <label class="helper-text">Valor pago no produto</label>
                <input type="number" step="0.01" name="materiais_preco_prod[]" class="form-control" placeholder="R$ total">
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
        if (produtoId) {
            div.querySelector('input[name="materiais_produto_id[]"]').value = produtoId;
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
        const produtoId = el.dataset.id || '';
        const nome      = el.dataset.nome || '';
        const unidade   = el.dataset.unidade || '';
        const qtdEmb    = el.dataset.tamanho || '';
        const preco     = el.dataset.preco || '';

        addMaterial(nome, unidade, qtdEmb, preco, produtoId);
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

    // DOMContentLoaded: mínimos + delete com AppConfirm
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

        // Botões de excluir histórico usando AppConfirm
        const deleteLinks = document.querySelectorAll('.cs-delete-link');
        deleteLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();

                const id   = this.dataset.id;
                const name = this.dataset.name || '';

                if (window.AppConfirm) {
                    AppConfirm.open({
                        title: 'Excluir cálculo',
                        message: `Tem certeza que deseja excluir o cálculo <strong>${name}</strong>?`,
                        confirmText: 'Sim, excluir',
                        cancelText: 'Cancelar',
                        type: 'danger',
                        onConfirm: function () {
                            window.location.href = 'calcular-servico.php?delete=' + id;
                        }
                    });
                } else {
                    if (confirm('Tem certeza que deseja excluir este cálculo?')) {
                        window.location.href = 'calcular-servico.php?delete=' + id;
                    }
                }
            });
        });
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
