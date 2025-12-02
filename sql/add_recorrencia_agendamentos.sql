-- =========================================================
-- ADICIONA SISTEMA DE AGENDAMENTOS RECORRENTES
-- =========================================================

-- 1. Adicionar campos de recorrência na tabela servicos
ALTER TABLE servicos ADD COLUMN permite_recorrencia INTEGER DEFAULT 0;
ALTER TABLE servicos ADD COLUMN tipo_recorrencia TEXT DEFAULT 'sem_recorrencia'; -- 'sem_recorrencia', 'diaria', 'semanal', 'quinzenal', 'mensal', 'personalizada'
ALTER TABLE servicos ADD COLUMN intervalo_dias INTEGER DEFAULT 1; -- Usado para recorrência personalizada
ALTER TABLE servicos ADD COLUMN duracao_meses INTEGER DEFAULT 1; -- Por quantos meses a recorrência vai durar
ALTER TABLE servicos ADD COLUMN qtd_ocorrencias INTEGER DEFAULT 1; -- Quantas vezes o serviço vai ocorrer
ALTER TABLE servicos ADD COLUMN dias_semana TEXT; -- JSON com dias da semana: ['1','3','5'] para seg, qua, sex
ALTER TABLE servicos ADD COLUMN dia_fixo_mes INTEGER; -- Dia fixo do mês (1-31) para recorrência mensal

-- 2. Criar tabela para vincular agendamentos recorrentes
CREATE TABLE IF NOT EXISTS agendamentos_recorrentes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    serie_id TEXT NOT NULL, -- UUID único para identificar a série de agendamentos
    cliente_id INTEGER,
    cliente_nome TEXT NOT NULL,
    servico_id INTEGER,
    servico_nome TEXT NOT NULL,
    valor REAL DEFAULT 0,
    horario TIME NOT NULL,
    tipo_recorrencia TEXT NOT NULL, -- 'diaria', 'semanal', 'quinzenal', 'mensal', 'personalizada'
    intervalo_dias INTEGER DEFAULT 1,
    dias_semana TEXT, -- JSON array dos dias da semana
    dia_fixo_mes INTEGER, -- Para recorrência mensal
    data_inicio DATE NOT NULL,
    data_fim DATE, -- Quando a série termina (calculado)
    qtd_total INTEGER NOT NULL, -- Total de ocorrências na série
    observacoes TEXT,
    ativo INTEGER DEFAULT 1, -- Se a série está ativa
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Adicionar campos na tabela agendamentos para vincular com série recorrente
ALTER TABLE agendamentos ADD COLUMN serie_id TEXT; -- Vincula ao agendamentos_recorrentes
ALTER TABLE agendamentos ADD COLUMN indice_serie INTEGER DEFAULT 1; -- Qual posição na série (1ª, 2ª, 3ª ocorrência)
ALTER TABLE agendamentos ADD COLUMN e_recorrente INTEGER DEFAULT 0; -- Flag indicando se é recorrente

-- 4. Criar índices para performance
CREATE INDEX IF NOT EXISTS idx_agendamentos_serie ON agendamentos(serie_id);
CREATE INDEX IF NOT EXISTS idx_agendamentos_recorrentes_serie ON agendamentos_recorrentes(serie_id);
CREATE INDEX IF NOT EXISTS idx_agendamentos_recorrentes_user ON agendamentos_recorrentes(user_id);
