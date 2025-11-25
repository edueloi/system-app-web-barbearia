<?php
// Marcar notificação como lida
include '../../includes/db.php';
if (!isset($_SESSION['user_id'])) exit;
$userId = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $userId]);
}
http_response_code(204); // No Content
