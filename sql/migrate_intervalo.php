<?php
/**
 * Migração: Adicionar campo intervalo_minutos
 * Execute este arquivo uma vez via navegador
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Verificar se a coluna já existe
    $stmt = $pdo->query("PRAGMA table_info(horarios_atendimento)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasInterval = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'intervalo_minutos') {
            $hasInterval = true;
            break;
        }
    }
    
    if (!$hasInterval) {
        // Adicionar coluna
        $pdo->exec("ALTER TABLE horarios_atendimento ADD COLUMN intervalo_minutos INTEGER DEFAULT 30");
        
        // Criar índice
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_horarios_user_dia ON horarios_atendimento(user_id, dia_semana)");
        
        echo "✅ Migração executada com sucesso!<br>";
        echo "- Coluna 'intervalo_minutos' adicionada (padrão: 30 minutos)<br>";
        echo "- Índice criado para melhor performance<br>";
    } else {
        echo "ℹ️ Coluna 'intervalo_minutos' já existe. Migração não necessária.";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao executar migração: " . $e->getMessage();
}
?>
