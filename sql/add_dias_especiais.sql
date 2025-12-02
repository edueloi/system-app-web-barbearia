-- Tabela para armazenar feriados e dias especiais de fechamento
CREATE TABLE IF NOT EXISTS dias_especiais_fechamento (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    data DATE NOT NULL,
    tipo VARCHAR(50) NOT NULL, -- 'feriado_fixo', 'feriado_nacional', 'data_especial'
    nome VARCHAR(255) NOT NULL,
    recorrente BOOLEAN DEFAULT 0, -- Se repete todo ano (ex: Natal sempre em 25/12)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Índice para busca rápida por usuário e data
CREATE INDEX IF NOT EXISTS idx_dias_especiais_user_data ON dias_especiais_fechamento(user_id, data);

-- Inserir feriados nacionais brasileiros padrão (recorrentes)
INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-01-01', 'feriado_nacional', 'Ano Novo', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Ano Novo' AND recorrente = 1);

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-12-25', 'feriado_nacional', 'Natal', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Natal' AND recorrente = 1);

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-09-07', 'feriado_nacional', 'Independência do Brasil', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Independência do Brasil' AND recorrente = 1);

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-10-12', 'feriado_nacional', 'Nossa Senhora Aparecida', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Nossa Senhora Aparecida' AND recorrente = 1);

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-11-02', 'feriado_nacional', 'Finados', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Finados' AND recorrente = 1);

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-11-15', 'feriado_nacional', 'Proclamação da República', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Proclamação da República' AND recorrente = 1);

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-11-20', 'feriado_nacional', 'Consciência Negra', 1
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE nome = 'Consciência Negra' AND recorrente = 1);

-- Feriados móveis 2025 (não recorrentes - precisam ser atualizados anualmente)
INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-03-03', 'feriado_nacional', 'Carnaval', 0
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE data = '2025-03-03' AND nome = 'Carnaval');

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-03-04', 'feriado_nacional', 'Carnaval', 0
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE data = '2025-03-04' AND nome = 'Carnaval');

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-04-18', 'feriado_nacional', 'Sexta-feira Santa', 0
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE data = '2025-04-18' AND nome = 'Sexta-feira Santa');

INSERT OR IGNORE INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) 
SELECT 1, '2025-06-19', 'feriado_nacional', 'Corpus Christi', 0
WHERE NOT EXISTS (SELECT 1 FROM dias_especiais_fechamento WHERE data = '2025-06-19' AND nome = 'Corpus Christi');
