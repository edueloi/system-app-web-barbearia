# 🕐 Sistema de Intervalo Personalizado - Guia de Instalação

## 📋 O que foi implementado?

Agora você pode configurar **intervalos personalizados** para cada período de atendimento:
- ✅ 15 minutos
- ✅ 30 minutos (padrão)
- ✅ 45 minutos
- ✅ 60 minutos (1 hora)
- ✅ 90 minutos (1h30)
- ✅ 120 minutos (2 horas)

## 🚀 Como instalar?

### Passo 1: Executar a Migração do Banco de Dados

Acesse pelo navegador:
```
http://localhost/karen_site/controle-salao/sql/migrate_intervalo.php
```

**OU** em produção:
```
https://salao.develoi.com/sql/migrate_intervalo.php
```

Você verá a mensagem: ✅ **Migração executada com sucesso!**

### Passo 2: Configurar os Horários

1. Acesse **Horários de Atendimento** no menu
2. Para cada período de atendimento, você verá um **dropdown** com as opções de intervalo
3. Selecione o intervalo desejado (ex: 30min, 45min, 60min)
4. Clique em **Salvar tudo**

## 💡 Como funciona?

### Exemplo Prático:

**Cenário 1: Serviço Rápido (30 minutos)**
- Horário: 08:00 às 12:00
- Intervalo: **30min**
- Slots disponíveis: 08:00, 08:30, 09:00, 09:30, 10:00, 10:30, 11:00, 11:30

**Cenário 2: Serviço Médio (60 minutos)**
- Horário: 13:00 às 18:00
- Intervalo: **60min**
- Slots disponíveis: 13:00, 14:00, 15:00, 16:00, 17:00

**Cenário 3: Procedimentos Longos (90 minutos)**
- Horário: 09:00 às 18:00
- Intervalo: **90min**
- Slots disponíveis: 09:00, 10:30, 12:00, 13:30, 15:00, 16:30

## 🎯 Vantagens

✅ **Flexibilidade Total**: Configure intervalos diferentes para manhã e tarde
✅ **Otimização**: Evite slots desnecessários entre serviços longos
✅ **Experiência do Cliente**: Mostre apenas horários relevantes
✅ **Compatibilidade**: Sistema mantém 30min como padrão se não configurado

## 📱 Onde aparece?

- **Página de Agendamento** (`agendar.php`): Os clientes veem apenas os slots conforme seu intervalo
- **Agenda Interna**: Os horários respeitam o intervalo configurado
- **Notificações**: Funcionam normalmente com os novos intervalos

## 🔧 Arquivos Modificados

1. ✅ `sql/add_intervalo_minutos.sql` - Script SQL
2. ✅ `sql/migrate_intervalo.php` - Migração automática
3. ✅ `pages/horarios/horarios.php` - Interface de configuração
4. ✅ `agendar.php` - Lógica de busca de horários

## ⚠️ Importante

- Execute a migração **UMA VEZ** apenas
- Após executar, delete ou mova o arquivo `migrate_intervalo.php` para fora da pasta `sql/`
- Os horários já cadastrados continuarão funcionando com 30min por padrão
- Você pode alterar o intervalo de cada período a qualquer momento

## 🎨 Visual

No **Horários de Atendimento**, cada slot agora mostra:
```
[08:00] → [12:00] • [30min ▼]  🗑️
```

Onde:
- `08:00` = Início
- `12:00` = Fim
- `30min ▼` = Intervalo (dropdown)
- `🗑️` = Remover

## 📞 Suporte

Desenvolvido por **Develoi**
🌐 https://develoi.com/
