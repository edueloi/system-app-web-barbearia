-- ============================================
-- SETUP COMPLETO: LICENÇAS E NOTIFICAÇÕES
-- ============================================
-- Execute este arquivo no seu banco de dados
-- para configurar o sistema de licenças

-- 1. ADICIONA COLUNAS NA TABELA USUARIOS (se não existirem)
-- ============================================

-- Verifica e adiciona coluna is_vitalicio
-- Para SQLite, precisamos verificar antes
-- Se der erro "duplicate column name", ignore (já existe)

-- Coluna: is_vitalicio
ALTER TABLE usuarios ADD COLUMN is_vitalicio INTEGER DEFAULT 0;

-- Coluna: data_expiracao  
ALTER TABLE usuarios ADD COLUMN data_expiracao DATE;

-- 2. CRIA TABELA DE NOTIFICAÇÕES
-- ============================================

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
);

-- 3. ÍNDICES PARA PERFORMANCE
-- ============================================

CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario ON notificacoes(usuario_id);
CREATE INDEX IF NOT EXISTS idx_notificacoes_tipo ON notificacoes(tipo);
CREATE INDEX IF NOT EXISTS idx_notificacoes_lida ON notificacoes(lida);
CREATE INDEX IF NOT EXISTS idx_notificacoes_criado ON notificacoes(criado_em);

-- 4. DADOS EXEMPLO (OPCIONAL - DESCOMENTE SE QUISER TESTAR)
-- ============================================

-- Define um usuário específico como teste (30 dias)
-- UPDATE usuarios SET is_vitalicio = 0, data_expiracao = date('now', '+30 days') WHERE id = 1;

-- Define um usuário como vitalício
-- UPDATE usuarios SET is_vitalicio = 1, data_expiracao = NULL WHERE id = 2;

-- Define um usuário com 5 dias restantes (para testar alertas)
-- UPDATE usuarios SET is_vitalicio = 0, data_expiracao = date('now', '+5 days') WHERE id = 3;

-- ============================================
-- FIM DO SETUP
-- ============================================
-- Após executar, reinicie o servidor PHP
-- e acesse o dashboard para ver o sistema funcionando
