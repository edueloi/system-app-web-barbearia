# 📋 Atualização: Tipos de Estabelecimento e Ícones Personalizados

## 🎯 O que foi implementado

### 1. **Campo de Tipo de Estabelecimento no Perfil**
- Adicionado campo `tipo_estabelecimento` na tabela `usuarios`
- Opções disponíveis:
  - 💇 **Salão de Beleza** (padrão)
  - 💈 **Barbearia**
  - 💅 **Nail Art / Manicure**
  - ✨ **Clínica de Estética**
  - 🧖 **Spa**
  - 🎨 **Studio de Beleza**

### 2. **Ícones Dinâmicos na Página de Agendamento**
- Os ícones dos serviços agora mudam automaticamente baseado no tipo de estabelecimento
- Mapeamento de ícones:
  - **Salão de Beleza** → `bi-scissors` (tesoura)
  - **Barbearia** → `bi-brush` (pincel/máquina)
  - **Nail Art** → `bi-gem` (diamante/esmalte)
  - **Estética** → `bi-stars` (estrelas)
  - **Spa** → `bi-droplet-half` (gota d'água)
  - **Studio** → `bi-palette` (paleta)

### 3. **Badge Dinâmico no Perfil**
- O badge no header do perfil agora mostra o tipo de estabelecimento selecionado
- Exibe o emoji correspondente ao tipo

## 📁 Arquivos Modificados

### `includes/db.php`
```php
// Linha adicionada após ALTER TABLE usuarios ADD COLUMN estabelecimento
try { 
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN tipo_estabelecimento TEXT DEFAULT 'Salão de Beleza'"); 
} catch (Exception $e) {}
```

### `pages/perfil/perfil.php`
- **Formulário de Salvamento**: Adicionado campo `tipo_estabelecimento`
- **Query SQL UPDATE**: Incluído novo campo na atualização
- **Campo no Formulário**: Select com 6 opções de tipo
- **Badge Dinâmico**: Mostra emoji + tipo selecionado

### `agendar.php`
- **Busca do Tipo**: Recupera `tipo_estabelecimento` do profissional
- **Mapeamento de Ícones**: Array associativo com ícones Bootstrap Icons
- **Ícone Dinâmico**: Substitui `bi-scissors` fixo por ícone variável

### `sql/add_tipo_estabelecimento.sql`
- Script de migração para produção
- Define valor padrão "Salão de Beleza"
- Atualiza registros existentes

## 🚀 Como Usar

### Para o Profissional:
1. Acesse **Meu Perfil** no menu
2. No campo "Tipo de Estabelecimento", selecione a opção correta
3. Clique em **Salvar Perfil**
4. O ícone será atualizado automaticamente na página de agendamento

### Para Clientes:
- Ao acessar o link de agendamento, verão ícones personalizados:
  - Se for barbearia → ícone de pincel/máquina
  - Se for nail art → ícone de diamante
  - E assim por diante...

## 🔧 Instalação em Produção

Execute o script de migração:
```sql
-- Via phpMyAdmin ou linha de comando
source sql/add_tipo_estabelecimento.sql;
```

Ou simplesmente acesse qualquer página do sistema (a migração roda automaticamente via `db.php`).

## ✅ Benefícios

1. **Personalização**: Cada estabelecimento tem identidade visual adequada
2. **UX Melhorada**: Clientes identificam rapidamente o tipo de serviço
3. **Profissionalismo**: Interface mais refinada e contextual
4. **Escalabilidade**: Fácil adicionar novos tipos no futuro

## 📝 Notas Técnicas

- Valor padrão: "Salão de Beleza" (mantém compatibilidade com registros antigos)
- Ícones: Bootstrap Icons 1.11+ (já incluído no projeto)
- Fallback: Se tipo não reconhecido, usa `bi-scissors`
- Compatibilidade: Funciona em produção (salao.develoi.com) e localhost

---

**Desenvolvido por**: Equipe Develoi  
**Data**: Dezembro 2025
