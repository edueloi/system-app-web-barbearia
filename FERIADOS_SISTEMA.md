# Sistema de Feriados e Dias Especiais

## 📅 Visão Geral

O sistema de Feriados e Dias Especiais permite que você configure datas específicas em que o estabelecimento **não atenderá**, como:

- 🎄 Feriados nacionais (Natal, Ano Novo, etc)
- 🎂 Aniversário do proprietário
- 🏖️ Férias e folgas planejadas
- 🎉 Eventos especiais
- ⛪ Feriados religiosos
- 📅 Emendas de feriados

## 🚀 Instalação

### 1. Executar Migração do Banco de Dados

```bash
cd c:\xampp\htdocs\karen_site\controle-salao\sql
php migrate_dias_especiais.php
```

Ou executar o SQL diretamente:

```bash
sqlite3 ../banco_salao.sqlite < add_dias_especiais.sql
```

### 2. Verificar Instalação

Acesse: **Painel Admin → Configurações → Horários**

Você deve ver a seção **"🎉 Feriados e Dias Especiais"** no final da página.

## 📱 Como Usar

### Adicionar Dia Especial

1. Acesse **Horários** no menu
2. Role até **Feriados e Dias Especiais**
3. Preencha:
   - **Data**: Selecione o dia
   - **Nome**: Ex: "Natal", "Meu Aniversário"
   - **Tipo**: Escolha entre:
     - Dia Especial
     - Feriado Fixo
     - Feriado Nacional
   - **Recorrente**: Marque se repete todo ano

4. Clique em **"Adicionar Data"**

### Tipos de Data

| Tipo | Descrição | Exemplo |
|------|-----------|---------|
| **Dia Especial** | Data única pessoal | "Casamento da minha filha" |
| **Feriado Fixo** | Feriado local/estadual | "Aniversário da Cidade" |
| **Feriado Nacional** | Feriado nacional | "Natal", "Independência" |

### Datas Recorrentes

✅ **Marcado**: Repete todo ano na mesma data
- Exemplo: Natal (25/12) repete todo 25 de dezembro

❌ **Desmarcado**: Acontece apenas uma vez
- Exemplo: "Férias em 2025-07-15" só fecha esse dia específico

## 🎯 Funcionamento

### No Painel Admin (horarios.php)

- Lista todas as datas especiais cadastradas
- Permite adicionar/remover datas
- Sugestão automática para feriados comuns
- Indicação visual de datas recorrentes vs únicas

### No Agendamento Público (agendar.php)

Os clientes verão no calendário:

- 🚫 **Dias fechados** (com listras vermelhas e ✕)
  - Inclui dias sem expediente
  - Inclui feriados e dias especiais
  
- 🔒 **Lotado** (amarelo com cadeado)
  - Todos horários ocupados
  
- ⚡ **Poucos horários** (laranja com raio)
  - Até 3 horários disponíveis
  
- ✅ **Disponível** (branco)
  - Muitos horários livres

## 💡 Recursos Inteligentes

### 1. Auto-sugestão de Nome

Ao selecionar uma data comum, o sistema sugere automaticamente:

```javascript
Datas reconhecidas:
- 01/01 → "Ano Novo"
- 25/12 → "Natal"
- 24/12 → "Véspera de Natal"
- 31/12 → "Réveillon"
- 07/09 → "Independência do Brasil"
- 12/10 → "Nossa Senhora Aparecida"
- 02/11 → "Finados"
- 15/11 → "Proclamação da República"
- 20/11 → "Consciência Negra"
```

### 2. Validação de Datas

- Não permite datas passadas
- Verifica conflitos com horários já agendados
- Alerta ao remover data com agendamentos

### 3. Sincronização Automática

- Calendário de agendamento atualiza instantaneamente
- API `verificar_mes` inclui dias especiais
- Suporte a datas recorrentes anuais

## 🗄️ Estrutura do Banco

### Tabela: `dias_especiais_fechamento`

```sql
CREATE TABLE dias_especiais_fechamento (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,              -- Proprietário
    data DATE NOT NULL,                     -- Data do fechamento
    tipo VARCHAR(50) NOT NULL,              -- Tipo de fechamento
    nome VARCHAR(255) NOT NULL,             -- Nome descritivo
    recorrente BOOLEAN DEFAULT 0,           -- Se repete anualmente
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
```

### Índice para Performance

```sql
CREATE INDEX idx_dias_especiais_user_data 
ON dias_especiais_fechamento(user_id, data);
```

## 🔧 API Endpoints

### Adicionar Dia Especial

**POST** `horarios.php`

```javascript
FormData:
  action: 'adicionar_dia_especial'
  data: '2025-12-25'
  nome: 'Natal'
  tipo: 'feriado_nacional'
  recorrente: '1'

Response:
{
  "success": true,
  "id": 15
}
```

### Remover Dia Especial

**POST** `horarios.php`

```javascript
FormData:
  action: 'remover_dia_especial'
  id: 15

Response:
{
  "success": true
}
```

### Verificar Mês (Integrado)

**GET** `agendar.php?action=verificar_mes&ano=2025&mes=12&duracao=30`

```json
{
  "1": "disponivel",
  "2": "poucos",
  "3": "lotado",
  "25": "fechado",  // ← Natal (dia especial)
  "31": "fechado"   // ← Réveillon
}
```

## 🎨 Design e UX

### Cores e Estilo

```css
Background: Linear gradient laranja (#fff7ed → #fed7aa)
Border: 2px solid #fb923c
Cards: Branco com borda laranja clara
Badges: 
  - Recorrente: Verde (#dcfce7)
  - Único: Azul (#dbeafe)
Botões: Gradiente laranja (#fb923c → #f97316)
```

### Animações

- `slideIn`: Entrada suave dos itens (0.3s)
- `fadeOut`: Remoção com opacidade (0.3s)
- Hover: Scale e shadow nos cards

## 📋 Exemplos de Uso

### Exemplo 1: Férias de Verão

```
Data: 2025-12-20 até 2026-01-05
Solução: Adicionar cada dia individualmente
Tipo: Dia Especial
Recorrente: ❌ Não (são datas específicas de 2025/2026)
```

### Exemplo 2: Aniversário Recorrente

```
Data: 2025-03-15
Nome: "Meu Aniversário"
Tipo: Dia Especial
Recorrente: ✅ Sim (repete todo 15 de março)
```

### Exemplo 3: Emenda de Feriado

```
Data: 2025-11-03
Nome: "Emenda de Finados"
Tipo: Feriado Fixo
Recorrente: ❌ Não (decisão anual)
```

## ⚠️ Considerações Importantes

1. **Feriados Móveis**: Carnaval e Páscoa mudam todo ano
   - Adicione como **não recorrentes**
   - Atualize anualmente
   
2. **Múltiplos Usuários**: Cada profissional tem seus próprios dias especiais
   - `user_id` separa os dados
   
3. **Agendamentos Existentes**: 
   - Sistema **não cancela** agendamentos ao adicionar feriado
   - Verifique conflitos manualmente
   
4. **Performance**: 
   - Índice otimizado para busca rápida
   - Cache do calendário no frontend

## 🐛 Troubleshooting

### Dias não aparecem no calendário

1. Verificar se a migração foi executada
2. Conferir `user_id` correto
3. Inspecionar Network → API `verificar_mes`
4. Conferir console JavaScript

### Erro ao adicionar data

1. Verificar permissões do banco SQLite
2. Conferir formato da data (YYYY-MM-DD)
3. Verificar se tabela existe: `sqlite3 banco_salao.sqlite ".tables"`

### Auto-sugestão não funciona

1. Verificar se JavaScript está carregado
2. Abrir DevTools → Console para erros
3. Confirmar evento `focus` no campo nome

## 🔄 Futuras Melhorias

- [ ] Importação de calendário (ICS/Google Calendar)
- [ ] Range de datas (selecionar intervalo)
- [ ] Categorias de fechamento (Férias, Feriado, Pessoal)
- [ ] Notificação prévia aos clientes
- [ ] Histórico de fechamentos passados
- [ ] Exportação de relatório anual

## 📞 Suporte

Para dúvidas ou problemas:
1. Consulte este documento
2. Verifique os logs do servidor
3. Inspecione o banco de dados SQLite
4. Contate o suporte técnico

---

**Última atualização**: Dezembro 2024
**Versão**: 1.0.0
