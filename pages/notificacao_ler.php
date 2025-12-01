<?php
// notificacao_ler.php

// Usa a mesma lógica de includes do menu.php para garantir mesmo banco
$dbFile1 = __DIR__ . '/includes/db.php';
$dbFile2 = __DIR__ . '/../../includes/db.php';
$dbFile3 = dirname(__DIR__) . '/includes/db.php';

if (file_exists($dbFile1)) {
    require_once $dbFile1;
} elseif (file_exists($dbFile2)) {
    require_once $dbFile2;
} elseif (file_exists($dbFile3)) {
    require_once $dbFile3;
} else {
    http_response_code(500);
    error_log('ERRO notificacao_ler.php: Arquivo de conexão não encontrado');
    echo 'Erro: Banco de dados não encontrado';
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garante que está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    error_log('ERRO notificacao_ler.php: Usuário não autenticado');
    echo 'Não autorizado';
    exit;
}

$userId = (int) $_SESSION['user_id'];
$notifId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($notifId <= 0) {
    http_response_code(400);
    error_log('ERRO notificacao_ler.php: ID inválido - ' . $notifId);
    echo 'ID inválido';
    exit;
}

// Log para debug
error_log("DEBUG notificacao_ler.php: Marcando notif ID={$notifId} como lida para user={$userId}");

// Marca como lida só a notificação do usuário logado
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$affected = $stmt->execute([$notifId, $userId]);

if ($affected) {
    error_log("DEBUG notificacao_ler.php: Notificação {$notifId} marcada como lida com SUCESSO");
} else {
    error_log("AVISO notificacao_ler.php: Nenhuma linha afetada ao marcar notif {$notifId}");
}

http_response_code(204); // sem conteúdo, mas sucesso
exit;
