<?php
// pages/agenda/api_comandas_cliente.php
// Retorna comandas abertas de um cliente para vinculação no agendamento
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['cliente_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Parâmetros ausentes.']);
    exit;
}

$uid        = (int)$_SESSION['user_id'];
$cliente_id = (int)$_GET['cliente_id'];

if ($cliente_id <= 0) {
    echo json_encode(['ok' => true, 'comandas' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.titulo,
            c.tipo,
            c.qtd_total,
            c.valor_total,
            c.status,
            s.nome AS servico_nome,
            (SELECT COUNT(*) FROM comanda_itens WHERE comanda_id = c.id AND status = 'realizado') AS feitos,
            (SELECT COUNT(*) FROM comanda_itens WHERE comanda_id = c.id AND status = 'pendente') AS pendentes
        FROM comandas c
        LEFT JOIN servicos s ON s.id = c.servico_id
        WHERE c.user_id = ?
          AND c.cliente_id = ?
          AND c.status = 'aberta'
        ORDER BY c.data_inicio DESC
    ");
    $stmt->execute([$uid, $cliente_id]);
    $comandas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'comandas' => $comandas]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao buscar comandas']);
}
