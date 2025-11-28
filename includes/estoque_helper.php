<?php
// ARQUIVO: includes/estoque_helper.php

/**
 * Consome produtos do estoque baseado na receita (cálculo) vinculada ao serviço.
 * @param PDO $pdo Conexão PDO
 * @param int $userId ID do profissional
 * @param int $servicoId ID do serviço agendado
 */
function consumirEstoquePorServico($pdo, $userId, $servicoId) {
    try {
        // 1. Verificar se o serviço tem um cálculo vinculado
        $stmt = $pdo->prepare("SELECT calculo_servico_id FROM servicos WHERE id = ? AND user_id = ?");
        $stmt->execute([$servicoId, $userId]);
        $servico = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$servico || empty($servico['calculo_servico_id'])) {
            return; // Serviço não usa estoque
        }

        $calculoId = $servico['calculo_servico_id'];

        // 2. Buscar materiais da receita que estão ligados a um produto real
        $stmtMat = $pdo->prepare("
            SELECT produto_id, quantidade_usada 
            FROM calculo_servico_materiais 
            WHERE calculo_id = ? AND produto_id IS NOT NULL AND produto_id > 0
        ");
        $stmtMat->execute([$calculoId]);
        $materiais = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

        if (empty($materiais)) {
            return;
        }

        // 3. Baixar o estoque de cada material
        foreach ($materiais as $mat) {
            $prodId = $mat['produto_id'];
            $qtdBaixar = (float)$mat['quantidade_usada']; // Ex: 50 (ml)

            // Buscar dados atuais do produto
            $stmtProd = $pdo->prepare("SELECT estoque_atual_base, tamanho_embalagem, nome FROM produtos WHERE id = ?");
            $stmtProd->execute([$prodId]);
            $produto = $stmtProd->fetch(PDO::FETCH_ASSOC);

            if ($produto) {
                // Abate do volume total (ml/g)
                // Se estoque_base for null, assume 0
                $estoqueAtual = (float)($produto['estoque_atual_base'] ?? 0);
                $novoEstoqueBase = $estoqueAtual - $qtdBaixar;
                
                // Recalcula visualmente quantos frascos restam
                $tamanho = ($produto['tamanho_embalagem'] > 0) ? (float)$produto['tamanho_embalagem'] : 1;
                $novaQtdVisual = floor($novoEstoqueBase / $tamanho);
                
                // Evita números negativos
                if ($novaQtdVisual < 0) $novaQtdVisual = 0;
                if ($novoEstoqueBase < 0) $novoEstoqueBase = 0;

                // Atualiza no banco
                $upd = $pdo->prepare("UPDATE produtos SET estoque_atual_base = ?, quantidade = ? WHERE id = ?");
                $upd->execute([$novoEstoqueBase, $novaQtdVisual, $prodId]);
            }
        }

    } catch (Exception $e) {
        // Apenas loga o erro, não para o sistema
        error_log("Erro no Estoque: " . $e->getMessage());
    }
}
?>