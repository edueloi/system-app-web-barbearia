<?php
// api/confirmar_agendamento.php
// API para confirmar agendamentos via AJAX

session_start();
header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

// Incluir conexão com banco
require_once __DIR__ . '/../includes/db.php';

// Verificar se ID foi enviado
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
    exit;
}

$agendamentoId = (int)$_POST['id'];

try {
    // Atualizar status para Confirmado
    $stmt = $pdo->prepare("
        UPDATE agendamentos 
        SET status = 'Confirmado' 
        WHERE id = ? 
        AND user_id = ? 
        AND status = 'Pendente'
    ");
    
    $stmt->execute([$agendamentoId, $userId]);
    
    // Verificar se atualizou
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Agendamento confirmado com sucesso!'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Agendamento não encontrado ou já foi confirmado'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao confirmar: ' . $e->getMessage()
    ]);
}
