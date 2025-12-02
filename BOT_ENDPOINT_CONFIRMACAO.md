# 🔔 Endpoint para Confirmação de Agendamento

## Adicionar no bot-secretario.js

Adicione este endpoint APÓS o webhook de novo agendamento (depois da linha do `/webhook/novo-agendamento`):

```javascript
// =============================
// WEBHOOK: AGENDAMENTO CONFIRMADO
// =============================

// Endpoint que o PHP chama quando o profissional CONFIRMA um agendamento
app.post('/webhook/agendamento-confirmado', async (req, res) => {
  try {
    console.log('\n✅ Webhook recebido: Agendamento CONFIRMADO!');
    
    if (!clientGlobal) {
      console.log('   ❌ Cliente WhatsApp ainda não está pronto');
      return res.status(500).json({ 
        success: false, 
        message: 'Cliente WhatsApp ainda não está pronto' 
      });
    }

    const {
      telefone_cliente,
      cliente_nome,
      profissional_nome,
      estabelecimento,
      servico,
      data,
      horario,
      valor,
      observacoes
    } = req.body || {};

    console.log('   Dados recebidos:', req.body);

    // Normaliza o número do CLIENTE (quem VAI RECEBER a confirmação)
    const numeroWhats = normalizarNumeroWhats(telefone_cliente);

    if (!numeroWhats) {
      console.log('   ⚠️ Telefone cliente inválido:', telefone_cliente);
      return res.status(400).json({ 
        success: false, 
        message: 'Telefone cliente inválido' 
      });
    }

    // Formata data e horário para ficar mais legível
    let dataFormatada = data;
    if (data && data.includes('-')) {
      // Converte YYYY-MM-DD para DD/MM/YYYY
      const partes = data.split('-');
      if (partes.length === 3) {
        dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
      }
    }

    let horaFormatada = horario;
    if (horario && horario.length >= 5) {
      horaFormatada = horario.substring(0, 5); // HH:MM
    }

    // Monta mensagem de CONFIRMAÇÃO para o CLIENTE
    const msg =
      '✅ *AGENDAMENTO CONFIRMADO!*\n\n' +
      `Olá *${cliente_nome}*! 👋\n\n` +
      `Seu agendamento foi confirmado com sucesso!\n\n` +
      `📍 *${estabelecimento || 'Salão'}*\n` +
      `👤 *Profissional:* ${profissional_nome || 'Não informado'}\n` +
      `✂️ *Serviço:* ${servico || 'Não informado'}\n` +
      `📅 *Data:* ${dataFormatada || 'Não informada'}\n` +
      `⏰ *Horário:* ${horaFormatada || 'Não informado'}\n` +
      (valor ? `💰 *Valor:* R$ ${Number(valor).toFixed(2)}\n` : '') +
      (observacoes ? `\n📝 *Observações:* ${observacoes}\n` : '') +
      `\n` +
      `_Estamos te esperando! Se precisar remarcar ou cancelar, entre em contato._\n\n` +
      `Até logo! 😊`;

    // Envia mensagem de confirmação para o WhatsApp do CLIENTE
    await clientGlobal.sendText(numeroWhats, msg);

    console.log(`   ✅ Confirmação enviada para cliente ${numeroWhats}`);
    
    return res.json({ 
      success: true,
      message: 'Confirmação enviada ao cliente com sucesso'
    });
    
  } catch (err) {
    console.error('   ❌ Erro no webhook de confirmação:', err);
    return res.status(500).json({ 
      success: false, 
      message: 'Erro interno no bot' 
    });
  }
});
```

---

## 📍 Onde adicionar no código

Procure no `bot-secretario.js` a parte que tem:

```javascript
app.post('/webhook/novo-agendamento', async (req, res) => {
  // ... código existente ...
});

// Status do bot
app.get('/status', (req, res) => {
```

Adicione o novo endpoint ENTRE esses dois blocos.

---

## 🔄 Fluxo Completo Atualizado

### 1️⃣ Cliente agenda pelo site:
- PHP grava no banco → `INSERT INTO agendamentos`
- PHP chama → `notificarBotNovoAgendamento()`
- Bot envia WhatsApp para **PROFISSIONAL**: "🔔 Novo agendamento recebido!"

### 2️⃣ Profissional confirma no painel:
- PHP atualiza status → `UPDATE agendamentos SET status = 'Confirmado'`
- PHP chama → `notificarBotAgendamentoConfirmado()`
- Bot envia WhatsApp para **CLIENTE**: "✅ Agendamento confirmado!"

---

## 🧪 Testando a Confirmação

### 1. Testar endpoint manualmente:

```bash
curl -X POST http://localhost:3333/webhook/agendamento-confirmado \
  -H "Content-Type: application/json" \
  -d '{
    "telefone_cliente": "11987654321",
    "cliente_nome": "Maria Silva",
    "profissional_nome": "João",
    "estabelecimento": "Salão Develoi",
    "servico": "Corte Feminino",
    "data": "2025-12-05",
    "horario": "14:30",
    "valor": 80
  }'
```

### 2. Testar no sistema real:
1. Crie um agendamento no site
2. Acesse o painel admin
3. Confirme o agendamento
4. Verifique se o **cliente** recebeu a mensagem no WhatsApp

---

## 📊 Logs Esperados

### Quando cliente agenda:
```
📲 Webhook recebido: Novo agendamento!
   ✅ Notificação enviada para 5515992675429@c.us (PROFISSIONAL)
```

### Quando profissional confirma:
```
✅ Webhook recebido: Agendamento CONFIRMADO!
   ✅ Confirmação enviada para cliente 5511987654321@c.us (CLIENTE)
```

---

## ⚠️ Importante

- **Novo agendamento** → envia para o **PROFISSIONAL** (avisa que tem cliente novo)
- **Confirmação** → envia para o **CLIENTE** (confirma o horário agendado)

---

**Adicione este código no bot-secretario.js e reinicie o bot!**
