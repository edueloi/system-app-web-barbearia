-- Tabela para armazenar configurações gerais do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chave VARCHAR(255) UNIQUE NOT NULL,
    valor TEXT
);

-- Exemplo de configuração inicial
INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('lembrete_email_ativo', '1');
