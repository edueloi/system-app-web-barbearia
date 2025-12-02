# 🔔 Sistema de Lembretes Automáticos para Agendamentos

Sistema completo para enviar lembretes automáticos via WhatsApp para clientes e profissionais sobre agendamentos próximos.

---

## 📋 Funcionalidades Implementadas

### 1. **Endpoint API - Agendamentos Próximos**
- **URL**: `GET /api/?action=agendamentos_proximos`
- **Parâmetros**:
  - `minutos` (opcional): Tempo de antecedência em minutos (padrão: 60)
- **Exemplo**:
  ```
  GET /api/?action=agendamentos_proximos&minutos=60
  Authorization: Bearer CPF_SEM_MASCARA
  ```

**Resposta**:
```json
{
  "success": true,
  "message": "Agendamentos próximos recuperados com sucesso",
  "data": {
    "total": 3,
    "tempo_antecedencia_minutos": 60,
    "agendamentos": [
      {
        "id": 123,
        "cliente_nome": "João Silva",
        "cliente_telefone": "11999999999",
        "telefone_profissional": "11988888888",
        "profissional_nome": "Maria Barbosa",
        "estabelecimento": "Salão Beleza Pura",
        "servico": "Corte + Barba",
        "data_agendamento": "2025-12-02",
        "data_agendamento_br": "02/12/2025",
        "horario": "14:30:00",
        "horario_formatado": "14:30",
        "valor": 85.00,
        "status": "Confirmado",
        "minutos_ate_agendamento": 45,
        "tempo_restante": "45 minutos",
        "lembrete_enviado": 0
      }
    ]
  },
  "timestamp": "2025-12-02 13:45:00"
}
```

---

### 2. **Função de Lembrete no Bot**

Nova função no arquivo `includes/notificar_bot.php`:

```php
notificarBotLembreteAgendamento($pdo, $agendamentoId, $minutosAntes = 60);
```

**Payload enviado ao bot**:
```json
{
  "agendamento_id": 123,
  "telefone_profissional": "11988888888",
  "telefone_cliente": "11999999999",
  "cliente_nome": "João Silva",
  "profissional_nome": "Maria Barbosa",
  "estabelecimento": "Salão Beleza Pura",
  "servico": "Corte + Barba",
  "data": "2025-12-02",
  "horario": "14:30:00",
  "valor": 85.00,
  "observacoes": null,
  "minutos_restantes": 45,
  "minutos_antes_configurado": 60
}
```

**Webhook do Bot**: `http://bot.develoi.com:3333/webhook/lembrete-agendamento`

---

### 3. **CRON Job Automático**

Arquivo criado: `cron_lembretes.php`

**Execução manual para teste**:
```bash
php cron_lembretes.php
```

**Via navegador (com token de segurança)**:
```
http://localhost/karen_site/controle-salao/cron_lembretes.php?token=seu_token_secreto_aqui_123456&minutos=60
```

---

## 🚀 Configuração do CRON Job

### **Opção 1: cPanel (Hospedagem compartilhada)**

1. Acesse **cPanel** → **Cron Jobs**
2. Adicione novo cron job:
   - **Comando**: `/usr/bin/php /home/usuario/public_html/cron_lembretes.php`
   - **Frequência**: A cada 10 minutos
   - **Formato cron**: `*/10 * * * *`

### **Opção 2: VPS/Servidor Linux**

Edite o crontab:
```bash
crontab -e
```

Adicione a linha:
```
*/10 * * * * /usr/bin/php /var/www/html/controle-salao/cron_lembretes.php >> /var/log/cron_lembretes.log 2>&1
```

### **Opção 3: XAMPP Local (Testes)**

**Windows (Task Scheduler)**:
1. Abra **Agendador de Tarefas**
2. Criar Tarefa Básica
3. Gatilho: A cada 10 minutos
4. Ação: Iniciar programa
5. Programa: `C:\xampp\php\php.exe`
6. Argumentos: `C:\xampp\htdocs\karen_site\controle-salao\cron_lembretes.php`

**Linux/Mac (crontab)**:
```bash
*/10 * * * * /usr/bin/php /Applications/XAMPP/htdocs/karen_site/controle-salao/cron_lembretes.php
```

---

## 🔐 Segurança

### **Token Secreto**

No arquivo `cron_lembretes.php`, linha 34:
```php
$tokenSecreto = 'seu_token_secreto_aqui_123456'; // 🔐 TROCAR POR TOKEN REAL
```

**Gerar token seguro**:
```php
echo bin2hex(random_bytes(32)); // Gera token de 64 caracteres
```

### **Acesso via CLI ou Token**

O script só pode ser executado de duas formas:
1. **Via CLI** (linha de comando) - Sem necessidade de token
2. **Via HTTP** - Requer token na URL: `?token=seu_token_aqui`

---

## 📊 Banco de Dados

### **Campo Adicionado**

Tabela `agendamentos`:
- `lembrete_enviado` INTEGER DEFAULT 0

**Índice criado**:
```sql
CREATE INDEX idx_agendamentos_lembrete 
ON agendamentos(lembrete_enviado, data_agendamento, horario);
```

---

## 🤖 Integração com o Bot

### **Webhook a Implementar no Bot**

**Endpoint**: `POST /webhook/lembrete-agendamento`

**Exemplo de resposta do bot**:
```javascript
// server.js do bot
app.post('/webhook/lembrete-agendamento', async (req, res) => {
  const {
    telefone_cliente,
    telefone_profissional,
    cliente_nome,
    profissional_nome,
    estabelecimento,
    servico,
    data,
    horario,
    minutos_restantes
  } = req.body;

  // Formatar mensagem para o CLIENTE
  const mensagemCliente = `
🔔 *Lembrete de Agendamento*

Olá *${cliente_nome}*! 👋

Você tem um agendamento marcado:
📅 Data: ${formatarData(data)}
🕐 Horário: ${horario}
✂️ Serviço: ${servico}
📍 Local: ${estabelecimento}

⏰ Seu atendimento é em *${minutos_restantes} minutos*!

Nos vemos em breve! 😊
  `.trim();

  // Formatar mensagem para o PROFISSIONAL
  const mensagemProfissional = `
🔔 *Lembrete de Atendimento*

${profissional_nome}, você tem um atendimento em breve:

👤 Cliente: *${cliente_nome}*
✂️ Serviço: ${servico}
🕐 Horário: ${horario}
⏰ Faltam *${minutos_restantes} minutos*

Prepare-se! 💼
  `.trim();

  // Enviar para o cliente
  if (telefone_cliente) {
    await client.sendText(`${telefone_cliente}@c.us`, mensagemCliente);
  }

  // Enviar para o profissional
  if (telefone_profissional) {
    await client.sendText(`${telefone_profissional}@c.us`, mensagemProfissional);
  }

  res.json({ success: true, message: 'Lembretes enviados' });
});
```

---

## 📝 Logs

Todos os eventos são registrados no `error_log` do PHP:

```
[BOT] Processando lembretes automáticos (60 minutos antes)...
[BOT] Lembrete enviado com sucesso para agendamento 123
[BOT] Processamento concluído: 3 lembrete(s) enviado(s).
[CRON] Lembretes automáticos: 3 enviado(s) em 2.45s
```

---

## 🧪 Testando o Sistema

### **1. Criar agendamento de teste**

Crie um agendamento para daqui a 50 minutos.

### **2. Executar manualmente**

```bash
php cron_lembretes.php 60
```

Ou via navegador:
```
http://localhost/karen_site/controle-salao/cron_lembretes.php?token=seu_token&minutos=60
```

### **3. Verificar logs**

Abra o `error_log` do PHP para ver os resultados.

### **4. Testar API diretamente**

```bash
curl -X GET "http://localhost/karen_site/controle-salao/api/?action=agendamentos_proximos&minutos=60" \
  -H "Authorization: Bearer SEU_CPF_AQUI"
```

---

## ⚙️ Configurações Personalizadas

### **Alterar tempo de antecedência**

**Via CRON**:
```bash
# Enviar 30 minutos antes
*/10 * * * * /usr/bin/php /caminho/cron_lembretes.php 30
```

**Via URL**:
```
?token=xxx&minutos=30
```

### **Múltiplos lembretes**

Configure múltiplos cron jobs com tempos diferentes:

```bash
# Lembrete 24 horas antes
0 */6 * * * /usr/bin/php /caminho/cron_lembretes.php 1440

# Lembrete 1 hora antes
*/10 * * * * /usr/bin/php /caminho/cron_lembretes.php 60
```

---

## 🎯 Resumo

✅ **API pronta** para consultar agendamentos próximos  
✅ **Função de lembrete** integrada com o bot  
✅ **CRON job** para execução automática  
✅ **Campo no banco** para controlar envios  
✅ **Segurança** via token ou CLI  
✅ **Logs detalhados** para monitoramento  

**O bot agora pode:**
- Avisar cliente 1h antes do horário
- Avisar profissional 1h antes do atendimento
- Processar automaticamente a cada 10 minutos
- Evitar envios duplicados
