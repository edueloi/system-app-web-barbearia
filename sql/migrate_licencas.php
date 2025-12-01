<?php
/**
 * Script de Migração: Licenças e Notificações
 * Execute este arquivo uma vez para configurar o sistema
 * 
 * LOCAL: http://localhost/karen_site/controle-salao/sql/migrate_licencas.php
 * PRODUÇÃO: https://salao.develoi.com/sql/migrate_licencas.php
 * 
 * IMPORTANTE: Delete este arquivo após executar em produção!
 */

// 🔒 PROTEÇÃO POR SENHA
$SENHA_MIGRACAO = 'SeuaSenhaSegura2024'; // ALTERE ESTA SENHA!

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    if ($_POST['senha'] !== $SENHA_MIGRACAO) {
        die('❌ Senha incorreta!');
    }
} else {
    // Mostra formulário de senha
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Migração - Autenticação</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 100%;
                padding: 40px;
                text-align: center;
            }
            h1 { color: #1f2937; margin-bottom: 10px; font-size: 1.8rem; }
            .subtitle { color: #6b7280; margin-bottom: 30px; font-size: 0.95rem; }
            input {
                width: 100%;
                padding: 14px;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                font-size: 1rem;
                margin-bottom: 20px;
            }
            input:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                width: 100%;
                padding: 14px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
            }
            button:hover {
                background: #5568d3;
                transform: translateY(-2px);
            }
            .warning {
                background: #fef3c7;
                padding: 12px;
                border-radius: 8px;
                color: #92400e;
                font-size: 0.85rem;
                margin-top: 20px;
                border-left: 4px solid #f59e0b;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔒 Migração Protegida</h1>
            <p class="subtitle">Insira a senha para continuar</p>
            <form method="POST">
                <input type="password" name="senha" placeholder="Digite a senha" required autofocus>
                <button type="submit">🚀 Executar Migração</button>
            </form>
            <div class="warning">
                ⚠️ <strong>Atenção:</strong> Delete este arquivo após executar!
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração - Sistema de Licenças</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        .status {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }
        .status.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .status.info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        .icon {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .code {
            background: #f3f4f6;
            padding: 12px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #374151;
            margin: 10px 0;
            word-wrap: break-word;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Migração do Sistema</h1>
        <p class="subtitle">Configurando sistema de licenças e notificações...</p>

        <?php
        $erros = [];
        $sucesso = [];

        try {
            // 1. Adicionar coluna is_vitalicio
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN is_vitalicio INTEGER DEFAULT 0");
                $sucesso[] = "Coluna 'is_vitalicio' adicionada com sucesso";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'duplicate column') !== false) {
                    $sucesso[] = "Coluna 'is_vitalicio' já existe (OK)";
                } else {
                    throw $e;
                }
            }

            // 2. Adicionar coluna data_expiracao
            try {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN data_expiracao DATE");
                $sucesso[] = "Coluna 'data_expiracao' adicionada com sucesso";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'duplicate column') !== false) {
                    $sucesso[] = "Coluna 'data_expiracao' já existe (OK)";
                } else {
                    throw $e;
                }
            }

            // 3. Criar tabela de notificações
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notificacoes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    usuario_id INTEGER NOT NULL,
                    tipo VARCHAR(50) NOT NULL,
                    mensagem TEXT NOT NULL,
                    icone VARCHAR(50) DEFAULT 'bi-bell-fill',
                    link TEXT,
                    lida INTEGER DEFAULT 0,
                    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                    lida_em DATETIME,
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
                )
            ");
            $sucesso[] = "Tabela 'notificacoes' criada/verificada com sucesso";

            // 4. Criar índices
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario ON notificacoes(usuario_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notificacoes_tipo ON notificacoes(tipo)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notificacoes_lida ON notificacoes(lida)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notificacoes_criado ON notificacoes(criado_em)");
            $sucesso[] = "Índices criados com sucesso";

            // 5. Configurar usuários existentes (30 dias de teste padrão)
            $result = $pdo->exec("
                UPDATE usuarios 
                SET data_expiracao = date('now', '+30 days')
                WHERE (data_expiracao IS NULL OR data_expiracao = '') 
                AND (is_vitalicio IS NULL OR is_vitalicio = 0)
            ");
            $sucesso[] = "Configurados {$result} usuário(s) com 30 dias de teste";

        } catch (Exception $e) {
            $erros[] = "Erro: " . $e->getMessage();
        }

        // Exibir resultados
        foreach ($sucesso as $msg) {
            echo '<div class="status success"><span class="icon">✅</span><span>' . htmlspecialchars($msg) . '</span></div>';
        }

        foreach ($erros as $msg) {
            echo '<div class="status error"><span class="icon">❌</span><span>' . htmlspecialchars($msg) . '</span></div>';
        }

        if (empty($erros)) {
            echo '<div class="status info"><span class="icon">ℹ️</span><span><strong>Migração concluída!</strong> O sistema de licenças está pronto para uso.</span></div>';
            
            // Mostrar exemplo de uso
            echo '<div class="code">';
            echo '// Exemplo: Definir usuário ID 1 como vitalício<br>';
            echo 'UPDATE usuarios SET is_vitalicio = 1, data_expiracao = NULL WHERE id = 1;';
            echo '</div>';
            
            echo '<div class="code">';
            echo '// Exemplo: Definir 5 dias de teste para usuário ID 2<br>';
            echo "UPDATE usuarios SET is_vitalicio = 0, data_expiracao = date('now', '+5 days') WHERE id = 2;";
            echo '</div>';
            
            // Botão para deletar arquivo (apenas em produção)
            if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com') {
                echo '<div style="margin-top: 30px; padding: 20px; background: #fef2f2; border-radius: 12px; border: 2px solid #ef4444;">';
                echo '<p style="color: #991b1b; font-weight: 600; margin-bottom: 15px;">🔒 <strong>SEGURANÇA:</strong> Delete este arquivo agora!</p>';
                echo '<form method="POST" action="?delete=1" onsubmit="return confirm(\'Tem certeza que deseja deletar este arquivo? Esta ação não pode ser desfeita.\')">';
                echo '<button type="submit" style="background: #ef4444; width: 100%; padding: 14px; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; font-size: 1rem;">🗑️ Deletar migrate_licencas.php</button>';
                echo '</form>';
                echo '</div>';
            }
        }
        
        // Processar deleção do arquivo
        if (isset($_GET['delete']) && $_GET['delete'] == '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $arquivo = __FILE__;
            if (unlink($arquivo)) {
                echo '<div class="status success"><span class="icon">✅</span><span>Arquivo deletado com sucesso! Redirecionando...</span></div>';
                echo '<script>setTimeout(() => window.location.href="../pages/dashboard.php", 2000);</script>';
            } else {
                echo '<div class="status error"><span class="icon">❌</span><span>Erro ao deletar arquivo. Delete manualmente via FTP.</span></div>';
            }
            exit;
        }
        ?>

        <a href="../pages/dashboard.php" class="btn">📊 Acessar Dashboard</a>
    </div>
</body>
</html>
