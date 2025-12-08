<?php
// pages/comandas/api_sessoes_pendentes.php
require_once __DIR__ . '/../../includes/db.php';

// Define que a resposta é JSON
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['comanda_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros ausentes.']);
    exit;
}

$uid = $_SESSION['user_id'];
$comanda_id = (int)$_GET['comanda_id'];

try {
    // JOIN para garantir que a comanda pertence ao usuário logado
    $sql = "
        SELECT ci.id, ci.numero, ci.data_prevista, ci.data_realizada, ci.valor_sessao, ci.status 
        FROM comanda_itens ci
        JOIN comandas c ON ci.comanda_id = c.id
        WHERE ci.comanda_id = ? 
          AND c.user_id = ? 
        ORDER BY ci.numero ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$comanda_id, $uid]);
    $sessoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sessoes' => $sessoes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar sessões']);
}