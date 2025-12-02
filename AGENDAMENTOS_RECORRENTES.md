# Sistema de Agendamentos Recorrentes

## 📋 Visão Geral

O sistema de agendamentos recorrentes permite criar séries de agendamentos automáticos para clientes, eliminando a necessidade de agendar manualmente cada sessão de um pacote ou serviço contínuo.

## ✨ Funcionalidades Principais

### 1. Configuração de Serviços/Pacotes Recorrentes

**Localização:** Painel > Serviços > Novo Pacote/Editar Pacote

Ao criar ou editar um **pacote**, você encontrará a seção **"Agendamento Recorrente"** com as seguintes opções:

#### Tipos de Recorrência Disponíveis:

- **Diária**: Agendamento todos os dias
- **Semanal**: Mesmos dias da semana (ex: toda segunda e quarta)
- **Quinzenal**: A cada 15 dias
- **Mensal (dia fixo)**: Mesmo dia do mês (ex: todo dia 10)
- **Mensal (semana)**: Mesma semana e dia da semana (ex: toda 2ª segunda-feira)
- **Personalizada**: Defina intervalo personalizado e dias específicos

#### Configurações:

1. **Permitir agendamento recorrente**: Marque o checkbox para ativar
2. **Tipo de recorrência**: Escolha o padrão de repetição
3. **Dias da semana**: Para recorrências semanais/personalizadas
4. **Dia fixo do mês**: Para recorrências mensais
5. **Intervalo em dias**: Para recorrências personalizadas
6. **Duração (meses)**: Por quantos meses o serviço se repetirá
7. **Nº de ocorrências**: Quantidade total de agendamentos a criar

### 2. Criando Agendamentos Recorrentes

**Localização:** Painel > Agenda > Novo Agendamento

1. **Selecione a DATA** primeiro
   - O sistema mostrará o dia da semana
   - Serviços serão filtrados automaticamente
   
2. **Escolha o serviço/pacote**
   - ⚠️ **Importante**: Apenas serviços configurados para esse dia da semana aparecerão!
   - Se configurou um pacote para "segundas e quartas", ele só aparecerá nesses dias
   - Serviços sem recorrência aparecem em todos os dias
   
3. **Complete o agendamento**
   - Escolha o cliente
   - Defina o horário
   - Confirme

✅ **O sistema criará automaticamente** todos os agendamentos futuros conforme a configuração!

#### Exemplo Prático:

**Cenário:** Cliente faz pacote de 12 depilações, toda semana às quartas-feiras

1. **Configure o pacote** (Serviços):
   - Nome: Pacote Depilação 12x
   - Tipo: Pacote
   - ✅ Permitir recorrência: SIM
   - Tipo: Semanal
   - Dias: ☑️ Quarta-feira
   - Ocorrências: 12
   - Duração: 3 meses
   - Valor: R$ 600,00

2. **Ao agendar** (Agenda):
   - Selecione uma **quarta-feira** no calendário (ex: 04/12/2024)
   - Sistema mostra: "🗓️ Quarta-feira"
   - O pacote aparece na lista de serviços
   - ⚠️ Se selecionar terça ou quinta, o pacote NÃO aparecerá!
   
3. **Complete**:
   - Cliente: Maria Silva
   - Horário: 14:00
   - Confirmar
   
4. **Resultado**:
   - Sistema cria 12 agendamentos automaticamente:
     - 04/12, 11/12, 18/12, 25/12, 01/01, 08/01... (12 quartas-feiras seguidas)
   - Todos às 14:00
   - Badge "🔁 Recorrente" em cada agendamento

## 🗑️ Cancelando Agendamentos Recorrentes

### Na Agenda

Quando você clicar para **excluir** um agendamento recorrente, aparecerá um modal com 3 opções:

1. **Apenas esta ocorrência**
   - Remove somente o agendamento selecionado
   - Os demais continuam agendados
   - Use quando o cliente faltar apenas uma vez

2. **Esta e as próximas**
   - Remove este agendamento e todos os futuros
   - Mantém o histórico dos já realizados
   - Use quando o cliente desistir do pacote no meio

3. **Toda a série**
   - Remove TODOS os agendamentos (passados e futuros)
   - Use apenas se precisar apagar completamente

### No Painel do Cliente

**Localização:** Painel > Clientes > [Cliente] > Botão 🔁 (Recorrências)

Nesta tela você pode:

- Ver todas as séries recorrentes ativas do cliente
- Visualizar detalhes (horário, período, próximo agendamento)
- Cancelar série completa
- Acessar a agenda para ver todos os agendamentos

## 📊 Indicadores Visuais

### Na Agenda

Agendamentos recorrentes são marcados com:

- Badge **"🔁 Recorrente"** em azul
- Aparecem automaticamente em todos os dias configurados

### Nos Serviços

Pacotes com recorrência habilitada mostram:

- Informações de configuração na criação/edição
- Aviso sobre criação automática de agendamentos

## 🔄 Fluxo Completo de Uso

### Exemplo Real: Pacote de Barba Mensal

**Passo 1: Configurar Serviço**
```
Nome: Pacote Barba Premium (12x)
Tipo: Pacote
Recorrência: ✅ Ativa
Tipo recorrência: Semanal
Dias: Segunda e Quinta
Ocorrências: 12
Duração: 2 meses
Valor: R$ 240,00
```

**Passo 2: Agendar para Cliente**
```
Cliente: João Silva
Data início: 02/12/2024
Horário: 10:00
```

**Resultado:**
- Sistema cria 12 agendamentos automáticos
- Datas: 02/12 (seg), 05/12 (qui), 09/12 (seg), 12/12 (qui)...
- Todos às 10:00
- Cliente recebe série completa de uma vez

**Passo 3: Gestão**
- Cliente visualiza todos na agenda
- Pode remarcar horário individual sem afetar série
- Pode cancelar ocorrências específicas
- Sistema mantém controle de série_id

## 🎯 Benefícios

✅ **Economia de tempo**: Crie vários agendamentos de uma vez
✅ **Organização**: Visualize pacotes inteiros de forma clara
✅ **Flexibilidade**: Cancele ou ajuste ocorrências individuais
✅ **Controle**: Acompanhe progresso do pacote do cliente
✅ **Profissionalismo**: Cliente vê compromisso de longo prazo

## 📝 Notas Importantes

- Apenas **pacotes** podem ter recorrência (não serviços únicos)
- **Filtro automático**: Serviços só aparecem nos dias configurados
  - Pacote de segunda/quarta NÃO aparece na terça
  - Serviços sem recorrência aparecem todos os dias
- Recorrências são criadas na **data do primeiro agendamento**
- Horário é o mesmo para todas as ocorrências
- Valor é replicado para cada agendamento
- Série mantém vínculo através do `serie_id`
- Cancelar apenas uma ocorrência não afeta as demais
- Sistema respeita limites de dias do mês (ex: dia 31 em fevereiro = dia 28)
- **Dica**: Configure os dias da semana no pacote ANTES de agendar

## 🔧 Estrutura Técnica

### Tabelas de Banco de Dados

**`servicos`** - Campos adicionados:
- `permite_recorrencia` (0/1)
- `tipo_recorrencia` (diaria, semanal, quinzenal, etc)
- `intervalo_dias` (para personalizadas)
- `duracao_meses`
- `qtd_ocorrencias`
- `dias_semana` (JSON array)
- `dia_fixo_mes`

**`agendamentos_recorrentes`** - Nova tabela:
- `serie_id` (identificador único da série)
- `user_id`
- `cliente_id`
- `servico_id`
- `tipo_recorrencia`
- `data_inicio` / `data_fim`
- `qtd_total`
- `ativo` (0/1)

**`agendamentos`** - Campos adicionados:
- `serie_id` (vincula à série)
- `indice_serie` (posição: 1ª, 2ª, 3ª...)
- `e_recorrente` (0/1 flag)

### Arquivos Principais

- `includes/recorrencia_helper.php` - Funções de criação e gestão
- `pages/servicos/servicos.php` - Interface de configuração
- `pages/agenda/agenda.php` - Criação e cancelamento
- `pages/clientes/recorrencias.php` - Visualização de séries

## ⚠️ Perguntas Frequentes (FAQ)

### Por que meu pacote não aparece ao agendar?

**Resposta:** O sistema filtra pacotes baseado no **dia da semana** selecionado!

**Exemplo:**
- Você configurou: "Pacote Barba - Segundas e Quartas"
- Ao tentar agendar numa **terça-feira**: ❌ Pacote NÃO aparece
- Ao tentar agendar numa **segunda-feira**: ✅ Pacote aparece!

**Solução:** Selecione uma data compatível com os dias configurados no pacote.

### Como fazer um pacote disponível em todos os dias?

**Resposta:** Configure como **"Diária"** ou **desmarque a recorrência**.

### Posso mudar os dias depois de criar agendamentos?

**Resposta:** Sim, mas os agendamentos já criados não serão alterados. A mudança afeta apenas novos agendamentos.

### O que acontece se eu não configurar recorrência?

**Resposta:** O serviço/pacote funcionará normalmente, mas você precisará agendar cada sessão manualmente.

## 🚀 Próximas Melhorias Sugeridas

- [ ] Notificações automáticas antes de cada ocorrência
- [ ] Relatório de pacotes em andamento
- [ ] Reagendamento em massa de séries
- [ ] Templates de pacotes recorrentes populares
- [ ] Dashboard com estatísticas de recorrência
- [ ] Exportar série para PDF/Excel
- [ ] Permitir exceções (pular feriados específicos)

---

**Versão:** 1.1  
**Data:** Dezembro 2024  
**Desenvolvido para:** Sistema de Gestão de Salão
