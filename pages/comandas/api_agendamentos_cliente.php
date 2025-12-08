<?php
// pages/comandas/api_agendamentos_cliente.php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Garante login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$uid        = (int)$_SESSION['user_id'];
$cliente_id = (int)($_GET['cliente_id'] ?? 0);

if ($cliente_id <= 0) {
    echo json_encode(['agendamentos' => []]);
    exit;
}

// Ajuste os nomes dos campos da tabela "agendamentos" conforme seu banco
// Aqui estou assumindo: data (Y-m-d), hora (HH:ii), status, servico_id, cliente_id, user_id
$sql = "
    SELECT a.id,
           a.data,
           a.hora,
           s.nome AS servico_nome
    FROM agendamentos a
    LEFT JOIN servicos s ON s.id = a.servico_id
    WHERE a.user_id    = :uid
      AND a.cliente_id = :cid
      AND a.status IN ('marcado', 'confirmado')
    ORDER BY a.data ASC, a.hora ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':uid' => $uid,
    ':cid' => $cliente_id
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formata data BR para exibir na tela
foreach ($rows as &$r) {
    if (!empty($r['data'])) {
        $r['data_br'] = date('d/m/Y', strtotime($r['data']));
    } else {
        $r['data_br'] = '';
    }
}

echo json_encode(['agendamentos' => $rows]);
