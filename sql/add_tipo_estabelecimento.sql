-- =========================================================
-- Migração: Adicionar tipo de estabelecimento
-- Data: 2025-12-01
-- Descrição: Adiciona coluna tipo_estabelecimento para 
--            identificar se é Salão, Barbearia, Nail Art, etc
-- =========================================================

-- Adiciona a coluna tipo_estabelecimento com valor padrão
ALTER TABLE usuarios ADD COLUMN tipo_estabelecimento TEXT DEFAULT 'Salão de Beleza';

-- Atualiza usuários existentes que já tem estabelecimento cadastrado
-- (mantém o valor padrão "Salão de Beleza")
UPDATE usuarios 
SET tipo_estabelecimento = 'Salão de Beleza' 
WHERE tipo_estabelecimento IS NULL OR tipo_estabelecimento = '';

-- =========================================================
-- Tipos disponíveis:
-- - Salão de Beleza (ícone: bi-scissors / 💇)
-- - Barbearia (ícone: bi-brush / 💈)
-- - Nail Art (ícone: bi-gem / 💅)
-- - Estética (ícone: bi-stars / ✨)
-- - Spa (ícone: bi-droplet-half / 🧖)
-- - Studio (ícone: bi-palette / 🎨)
-- =========================================================
