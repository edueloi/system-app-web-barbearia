<?php
// pages/comandas/api_pacotes_cliente.php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['cliente_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros ausentes.']);
    exit;
}

$uid = $_SESSION['user_id'];
$cliente_id = (int)$_GET['cliente_id'];

try {
    $sql = "
        SELECT c.id, c.titulo, c.tipo, c.qtd_total, c.valor_total, c.status,
        (SELECT COUNT(*) FROM comanda_itens WHERE comanda_id = c.id AND status = 'realizado') as feitos
        FROM comandas c
        WHERE c.user_id = ? 
          AND c.cliente_id = ? 
          AND c.status = 'aberta' 
        ORDER BY c.data_inicio DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid, $cliente_id]);
    $pacotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['pacotes' => $pacotes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar pacotes']);
}