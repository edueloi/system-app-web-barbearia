-- ============================================
-- SETUP COMPLETO: LICENÇAS E NOTIFICAÇÕES
-- ============================================
-- Execute este SQL no seu banco de dados SQLite

-- IMPORTANTE: Para executar automaticamente, acesse:
-- http://localhost/karen_site/controle-salao/sql/migrate_licencas.php

-- ============================================
-- 1. ADICIONAR COLUNAS NA TABELA USUARIOS
-- ============================================

-- Coluna: is_vitalicio (define se é licença vitalícia)
-- Se der erro "duplicate column", significa que já existe (OK)
ALTER TABLE usuarios ADD COLUMN is_vitalicio INTEGER DEFAULT 0;

-- Coluna: data_expiracao (data de expiração da licença)
ALTER TABLE usuarios ADD COLUMN data_expiracao DATE;

-- ============================================
-- 2. CRIAR TABELA DE NOTIFICAÇÕES
-- ============================================

CREATE TABLE IF NOT EXISTS notificacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- 'licenca_expiracao', 'agendamento', 'sistema', etc.
    mensagem TEXT NOT NULL,
    icone VARCHAR(50) DEFAULT 'bi-bell-fill',
    link TEXT, -- Link opcional para ação (ex: WhatsApp, página específica)
    lida INTEGER DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    lida_em DATETIME,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================
-- 3. ÍNDICES PARA PERFORMANCE
-- ============================================

CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario ON notificacoes(usuario_id);
CREATE INDEX IF NOT EXISTS idx_notificacoes_tipo ON notificacoes(tipo);
CREATE INDEX IF NOT EXISTS idx_notificacoes_lida ON notificacoes(lida);
CREATE INDEX IF NOT EXISTS idx_notificacoes_criado ON notificacoes(criado_em);

-- ============================================
-- 4. CONFIGURAR USUÁRIOS EXISTENTES
-- ============================================

-- Define 30 dias de teste para todos os usuários não vitalícios
UPDATE usuarios 
SET data_expiracao = date('now', '+30 days')
WHERE (data_expiracao IS NULL OR data_expiracao = '') 
AND (is_vitalicio IS NULL OR is_vitalicio = 0);

-- ============================================
-- 5. EXEMPLOS DE USO (DESCOMENTE PARA TESTAR)
-- ============================================

-- Definir usuário ID 1 como vitalício:
-- UPDATE usuarios SET is_vitalicio = 1, data_expiracao = NULL WHERE id = 1;

-- Definir usuário ID 2 com 5 dias de teste (testar alertas):
-- UPDATE usuarios SET is_vitalicio = 0, data_expiracao = date('now', '+5 days') WHERE id = 2;

-- Definir usuário ID 3 com 60 dias de teste:
-- UPDATE usuarios SET is_vitalicio = 0, data_expiracao = date('now', '+60 days') WHERE id = 3;

-- ============================================
-- FIM DO SETUP
-- ============================================
