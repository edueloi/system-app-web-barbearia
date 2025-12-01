-- Adicionar coluna intervalo_minutos na tabela horarios_atendimento
-- Padrão: 30 minutos (mantém compatibilidade com sistema atual)

ALTER TABLE horarios_atendimento ADD COLUMN intervalo_minutos INTEGER DEFAULT 30;

-- Índice para melhorar performance
CREATE INDEX IF NOT EXISTS idx_horarios_user_dia ON horarios_atendimento(user_id, dia_semana);
