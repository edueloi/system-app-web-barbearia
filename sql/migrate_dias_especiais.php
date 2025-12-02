<?php
/**
 * Script de Migração: Adicionar Tabela de Dias Especiais
 * 
 * Este script cria a tabela dias_especiais_fechamento para gerenciar
 * feriados e datas especiais em que o estabelecimento não atenderá.
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== MIGRAÇÃO: Dias Especiais de Fechamento ===\n\n";

try {
    // Verifica se a tabela já existe
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='dias_especiais_fechamento'");
    if ($check->fetch()) {
        echo "⚠️  Tabela 'dias_especiais_fechamento' já existe!\n";
        echo "Deseja recriar? (s/N): ";
        $resposta = trim(fgets(STDIN));
        
        if (strtolower($resposta) !== 's') {
            echo "Migração cancelada.\n";
            exit(0);
        }
        
        echo "Removendo tabela antiga...\n";
        $pdo->exec("DROP TABLE IF EXISTS dias_especiais_fechamento");
    }
    
    echo "Criando tabela 'dias_especiais_fechamento'...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS dias_especiais_fechamento (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        data DATE NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        nome VARCHAR(255) NOT NULL,
        recorrente BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );
    
    CREATE INDEX IF NOT EXISTS idx_dias_especiais_user_data 
    ON dias_especiais_fechamento(user_id, data);
    ";
    
    $pdo->exec($sql);
    echo "✅ Tabela criada com sucesso!\n\n";
    
    // Adicionar feriados nacionais padrão
    echo "Deseja adicionar feriados nacionais brasileiros? (S/n): ";
    $resposta = trim(fgets(STDIN));
    
    if (strtolower($resposta) !== 'n') {
        echo "Qual user_id deve receber os feriados? (digite o ID ou deixe em branco para todos): ";
        $userId = trim(fgets(STDIN));
        
        if (empty($userId)) {
            // Busca todos os usuários
            $users = $pdo->query("SELECT id FROM usuarios")->fetchAll();
            $userIds = array_column($users, 'id');
        } else {
            $userIds = [(int)$userId];
        }
        
        $feriadosFixos = [
            ['01-01', 'Ano Novo', 'feriado_nacional'],
            ['12-25', 'Natal', 'feriado_nacional'],
            ['09-07', 'Independência do Brasil', 'feriado_nacional'],
            ['10-12', 'Nossa Senhora Aparecida', 'feriado_nacional'],
            ['11-02', 'Finados', 'feriado_nacional'],
            ['11-15', 'Proclamação da República', 'feriado_nacional'],
            ['11-20', 'Consciência Negra', 'feriado_nacional']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $anoAtual = date('Y');
        foreach ($userIds as $uid) {
            foreach ($feriadosFixos as $feriado) {
                $data = $anoAtual . '-' . $feriado[0];
                $stmt->execute([$uid, $data, $feriado[2], $feriado[1]]);
                echo "  ✓ {$feriado[1]} adicionado para user_id {$uid}\n";
            }
        }
        
        echo "\n✅ Feriados nacionais adicionados!\n";
    }
    
    echo "\n=== Migração concluída com sucesso! ===\n";
    echo "\nAgora você pode:\n";
    echo "1. Acessar Configurações > Horários no painel admin\n";
    echo "2. Adicionar datas especiais de fechamento\n";
    echo "3. Os clientes verão no calendário de agendamento\n\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
