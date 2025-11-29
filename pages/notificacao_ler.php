<?php
// notificacao_ler.php

require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garante que está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Não autorizado';
    exit;
}

$userId = (int) $_SESSION['user_id'];
$notifId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($notifId <= 0) {
    http_response_code(400);
    echo 'ID inválido';
    exit;
}

// Marca como lida só a notificação do usuário logado
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$notifId, $userId]);

http_response_code(204); // sem conteúdo, mas sucesso
exit;
