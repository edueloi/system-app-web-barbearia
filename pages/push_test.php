<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/push_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo invalido']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$payload = [
    'headings' => ['pt' => 'Teste de notificacao'],
    'contents' => ['pt' => 'Se voce recebeu isso, o push esta funcionando.'],
    'url' => getAbsoluteUrl('/agenda'),
    'data' => ['tag' => 'teste-push'],
];

$ok = sendOneSignalToUser($userId, $payload);
echo json_encode(['success' => $ok]);
