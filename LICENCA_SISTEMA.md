# Sistema de Licença e Notificações

## 📋 Visão Geral

Sistema completo de gerenciamento de licenças com alertas visuais e notificações automáticas para usuários do sistema.

## ✨ Funcionalidades

### 1. **Card de Licença no Dashboard**
- 🟣 **Vitalício**: Exibe badge especial em roxo
- 🟢 **Ativo** (>15 dias): Verde, sem alertas
- 🟠 **Alerta** (6-15 dias): Laranja, aviso de renovação
- 🔴 **Crítico** (1-5 dias): Vermelho, urgência máxima
- ⚫ **Expirado**: Vermelho pulsante com chamada para ação

### 2. **Notificações Automáticas**
O sistema cria notificações no banco de dados nos seguintes momentos:
- ✅ 15 dias antes: "Planeje sua renovação"
- ⚠️ 5 dias antes: "Renove para garantir acesso contínuo"
- 🚨 2 dias antes: "Faltam apenas X dias!"
- 🔴 1 dia antes: "Expira AMANHÃ!"
- ⛔ No dia: "Expira HOJE!"

### 3. **Modal de Alerta**
Quando faltarem 2 dias ou menos, um modal é exibido automaticamente:
- Aparece uma vez por dia (controlado por localStorage)
- Design impactante com animações
- Botão direto para WhatsApp
- Pode ser fechado pelo usuário

## 🎨 Cores por Status

| Status | Dias Restantes | Cor | Hex |
|--------|---------------|-----|-----|
| Vitalício | ∞ | 🟣 Roxo | #8b5cf6 |
| Ativo | > 15 | 🟢 Verde | #10b981 |
| Alerta | 6-15 | 🟠 Laranja | #f59e0b |
| Crítico | 1-5 | 🔴 Vermelho | #ef4444 |
| Expirado | 0 | 🔴 Vermelho | #ef4444 |

## 📊 Banco de Dados

### Campos Necessários na Tabela `usuarios`
```sql
- is_vitalicio (BOOLEAN): Define se o usuário tem licença vitalícia
- data_expiracao (DATE): Data de expiração da licença
- data_cadastro (DATETIME): Data de criação da conta
```

### Como Executar a Migração

**Opção 1: Script PHP Automático (RECOMENDADO)**
```
Acesse no navegador:
http://localhost/karen_site/controle-salao/sql/migrate_licencas.php
```

**Opção 2: SQL Manual**
```bash
# Execute o arquivo SQL no seu banco SQLite
sqlite3 seu_banco.db < sql/add_notificacoes.sql
```

O script irá:
- ✅ Adicionar colunas `is_vitalicio` e `data_expiracao` na tabela `usuarios`
- ✅ Criar tabela `notificacoes`
- ✅ Criar índices para performance
- ✅ Configurar usuários existentes com 30 dias de teste padrão

## 🔧 Configuração

### 1. WhatsApp para Contato
Edite o número do WhatsApp nos seguintes locais:

**No dashboard (dashboard.php):**
```php
// Linha ~1711 - Card de licença
href="https://wa.me/5511999999999?text=..."

// Linha ~2534 - Modal de notificação
href="https://wa.me/5511999999999?text=..."
```

### 2. Período de Teste Padrão
No painel admin, ao criar usuário, definir dias de teste.

## 📱 Responsividade

O sistema é 100% responsivo:
- ✅ Desktop: Layout horizontal completo
- ✅ Tablet: Layout adaptativo
- ✅ Mobile: Cards empilhados, botões em coluna

## 🚀 Implementação

### Arquivos Modificados
1. **pages/dashboard.php**
   - Adicionada lógica de cálculo de dias
   - Card visual de licença
   - Modal de notificação
   - CSS completo com animações
   - Sistema de notificações no banco

2. **sql/add_notificacoes.sql**
   - Criação da tabela de notificações
   - Adição de colunas na tabela usuarios
   - Índices para performance

3. **sql/migrate_licencas.php**
   - Script PHP automático para migração
   - Interface visual amigável
   - Tratamento de erros

## 🎯 Fluxo de Uso

1. **Usuário loga no sistema**
2. **Dashboard carrega** e calcula dias restantes
3. **Se < 15 dias**: Cria notificação no banco
4. **Se ≤ 2 dias**: Exibe modal automático (1x por dia)
5. **Card sempre visível** mostrando status atual
6. **Usuário pode renovar** clicando no botão WhatsApp

## 📝 Notas Importantes

- ⚠️ Usuários vitalícios não veem o card
- ⚠️ Modal aparece 1x por dia (localStorage)
- ⚠️ Notificações são criadas 1x por dia
- ⚠️ Sistema funciona em SQLite
- ⚠️ Compatível com produção e localhost

## 🐛 Troubleshooting

### Notificações não aparecem
- Verifique se a tabela `notificacoes` existe
- Execute o SQL: `sql/add_notificacoes.sql`

### Card não aparece
- Verifique se o usuário **não** é vitalício
- Confirme que `data_expiracao` está preenchida

### Modal aparece sempre
- Limpe o localStorage do navegador
- Chave: `licenseModalShown_[userId]_[date]`

## 🎨 Customização

### Alterar Thresholds de Alerta
Edite em `dashboard.php` (linhas ~85-105):
```php
if ($diasRestantes <= 1) {        // Crítico
if ($diasRestantes <= 5) {        // Crítico  
if ($diasRestantes <= 15) {       // Alerta
```

### Mudar Cores
Edite as variáveis de cor:
```php
$corLicenca = '#10b981'; // Verde
$corLicenca = '#f59e0b'; // Laranja
$corLicenca = '#ef4444'; // Vermelho
```

## 📞 Suporte

Para dúvidas ou problemas, contate o desenvolvedor.
