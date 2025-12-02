# 🤖 Integração Bot WhatsApp Secretário

## 📋 Resumo da Integração

O sistema PHP notifica automaticamente o Bot WhatsApp sempre que um novo agendamento é criado.

---

## 🔄 Fluxo Completo

```
Cliente agenda → agendar.php 
    ↓
Grava no banco (INSERT agendamentos)
    ↓
Pega ID do agendamento ($idAgendamento)
    ↓
Chama notificarBotNovoAgendamento($pdo, $idAgendamento)
    ↓
Busca dados completos (cliente, profissional, serviço)
    ↓
Faz POST HTTP para bot Node.js (porta 3333)
    ↓
Bot recebe webhook e envia WhatsApp para profissional
```

---

## 📁 Arquivos da Integração

### 1. `includes/notificar_bot.php`
**Função:** Faz o POST HTTP para o bot Node.js

**Detecta automaticamente o ambiente:**
- **LOCAL:** `http://localhost:3333/webhook/novo-agendamento`
- **PRODUÇÃO:** `http://SEU_IP_VPS:3333/webhook/novo-agendamento`

### 2. `agendar.php`
**Linha ~360:** Chama a função após criar o agendamento

```php
$idAgendamento = $pdo->lastInsertId();
require_once __DIR__ . '/includes/notificar_bot.php';
notificarBotNovoAgendamento($pdo, $idAgendamento);
```

### 3. `bot-secretario.js` (Node.js - VPS)
**Webhook:** `POST /webhook/novo-agendamento`

**Recebe payload:**
```json
{
  "telefone_profissional": "15992675429",
  "cliente_nome": "Maria Silva",
  "cliente_telefone": "(11) 98765-4321",
  "servico": "Corte Feminino",
  "data": "2025-12-05",
  "horario": "14:30",
  "valor": 80.00,
  "observacoes": "Cliente prefere tesoura"
}
```

---

## ⚙️ Configuração para Produção

### Editar `includes/notificar_bot.php` linha 25:

```php
// Trocar de:
$WEBHOOK_PROD = 'http://SEU_IP_OU_DOMINIO_AQUI:3333/webhook/novo-agendamento';

// Para (exemplo com IP):
$WEBHOOK_PROD = 'http://185.123.45.67:3333/webhook/novo-agendamento';

// Ou (exemplo com subdomínio):
$WEBHOOK_PROD = 'http://bot.salao.develoi.com:3333/webhook/novo-agendamento';
```

---

## ⚠️ Requisitos

### No Servidor PHP (HostGator):
- ✅ Telefone do profissional em `usuarios.telefone` (formato: `15992675429`)
- ✅ Extensão cURL habilitada
- ✅ Arquivo `notificar_bot.php` na pasta `includes/`

### No Servidor Node.js (VPS):
- ✅ Bot rodando na porta 3333
- ✅ Porta 3333 aberta no firewall
- ✅ Endpoint `/webhook/novo-agendamento` ativo

---

## 🧪 Testando a Integração

### 1. Verificar se o bot está online:

```bash
curl http://SEU_IP_VPS:3333/status
```

**Resposta esperada:**
```json
{
  "status": "online",
  "profissionais_vinculados": 0,
  "timestamp": "2025-12-01T15:30:00.000Z"
}
```

### 2. Testar webhook manualmente:

```bash
curl -X POST http://SEU_IP_VPS:3333/webhook/novo-agendamento \
  -H "Content-Type: application/json" \
  -d '{
    "telefone_profissional": "15992675429",
    "cliente_nome": "Teste",
    "servico": "Corte",
    "data": "2025-12-05",
    "horario": "14:30",
    "valor": 80
  }'
```

### 3. Criar agendamento real:

Acesse: `https://salao.develoi.com/agendar?user=1`

Faça um agendamento e verifique:
- ✅ Logs do PHP (`error_log`)
- ✅ Logs do bot Node.js (terminal)
- ✅ WhatsApp do profissional

---

## 📊 Logs de Debug

### PHP (`error_log`):
```
[BOT] Webhook http://localhost:3333/webhook/novo-agendamento HTTP 200 - Resp: {"success":true}
```

### Bot Node.js (terminal):
```
📲 Webhook recebido: Novo agendamento!
   Dados recebidos: { telefone_profissional: '15992675429', ... }
   ✅ Notificação enviada para 5515992675429@c.us
```

---

## 🔧 Troubleshooting

### ❌ Erro: "Profissional sem telefone cadastrado"
**Solução:** Acesse "Meu Perfil" e preencha o campo Telefone

### ❌ Erro: "Erro cURL ao notificar bot"
**Solução:** Verificar se:
- Bot está rodando na VPS
- Porta 3333 está aberta
- URL do webhook está correta

### ❌ Bot não envia mensagem
**Solução:** Verificar se:
- QR Code foi escaneado
- WhatsApp está conectado
- Número está no formato correto (ex: `15992675429`)

---

## 📱 Comandos do Bot para Profissionais

Após vincular CPF no WhatsApp:

- `Agendamentos hoje` - Ver agendamentos de hoje
- `Agendamentos amanhã` - Ver agendamentos de amanhã
- `Próximos agendamentos` - Ver próximos agendamentos
- `Todos os agendamentos` - Listar todos
- `Ajuda` - Ver menu de comandos

---

## 🚀 Deploy em Produção

1. Subir `notificar_bot.php` para HostGator: `public_html/includes/`
2. Editar linha 25 com IP/domínio da VPS
3. Garantir que bot está rodando na VPS: `node bot-secretario.js`
4. Testar criando um agendamento real

---

**Versão:** 1.0  
**Data:** Dezembro 2025  
**Desenvolvido por:** Develoi
