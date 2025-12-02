# 📡 API REST - Sistema Salão Develoi

## 🔐 Autenticação

Todas as requisições exigem autenticação via **CPF do profissional** no header `Authorization`.

```
Authorization: Bearer 12345678900
```

> ⚠️ **Importante:** Use apenas números no CPF (sem pontos ou traços)

---

## 🌐 Base URL

### Produção
```
https://salao.develoi.com/api/
```

### Local (desenvolvimento)
```
http://localhost/karen_site/controle-salao/api/
```

---

## 📋 Endpoints Disponíveis

### 1️⃣ Listar Agendamentos

```http
GET /api/?action=agendamentos
```

**Parâmetros opcionais:**

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `data_inicio` | string | Filtrar a partir desta data | `2024-12-01` |
| `data_fim` | string | Filtrar até esta data | `2024-12-31` |
| `status` | string | Filtrar por status | `Confirmado`, `Pendente`, `Cancelado` |
| `limite` | int | Quantidade máxima de resultados | `50` (padrão: 100) |
| `offset` | int | Paginação (deslocamento) | `0` (padrão: 0) |

**Exemplo de requisição:**
```bash
curl -X GET "https://salao.develoi.com/api/?action=agendamentos&data_inicio=2024-12-01&status=Confirmado" \
  -H "Authorization: Bearer 12345678900"
```

**Resposta (200 OK):**
```json
{
  "success": true,
  "message": "Agendamentos recuperados com sucesso",
  "data": {
    "total": 15,
    "limite": 100,
    "offset": 0,
    "agendamentos": [
      {
        "id": 123,
        "user_id": 1,
        "cliente_id": 45,
        "cliente_nome": "Maria Silva",
        "cliente_nome_completo": "Maria Silva Santos",
        "cliente_telefone": "(11) 98765-4321",
        "cliente_nascimento": "1990-05-15",
        "servico": "Corte Feminino",
        "valor": 80.00,
        "data_agendamento": "2024-12-05",
        "data_agendamento_br": "05/12/2024",
        "horario": "14:30:00",
        "horario_formatado": "14:30",
        "status": "Confirmado",
        "observacoes": "Cliente prefere tesoura",
        "created_at": "2024-12-01 10:30:00"
      }
    ]
  },
  "timestamp": "2024-12-01 15:45:30"
}
```

---

### 2️⃣ Consultar Horários Livres

```http
GET /api/?action=horarios_livres
```

**Parâmetros:**

| Parâmetro | Tipo | Obrigatório | Descrição | Exemplo |
|-----------|------|-------------|-----------|---------|
| `data` | string | Não | Data para consulta | `2024-12-05` (padrão: hoje) |
| `duracao` | int | Não | Duração do serviço em minutos | `60` (padrão: 60) |

**Exemplo de requisição:**
```bash
curl -X GET "https://salao.develoi.com/api/?action=horarios_livres&data=2024-12-05&duracao=90" \
  -H "Authorization: Bearer 12345678900"
```

**Resposta (200 OK):**
```json
{
  "success": true,
  "message": "Horários livres calculados com sucesso",
  "data": {
    "data": "2024-12-05",
    "dia_semana": 4,
    "duracao_servico": 90,
    "total_slots": 8,
    "horarios_livres": [
      "09:00",
      "10:30",
      "12:00",
      "14:00",
      "15:30",
      "17:00",
      "18:30",
      "20:00"
    ]
  },
  "timestamp": "2024-12-01 15:45:30"
}
```

---

### 3️⃣ Listar Clientes

```http
GET /api/?action=clientes
```

**Parâmetros opcionais:**

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `busca` | string | Buscar por nome ou telefone | `Maria` |
| `limite` | int | Quantidade máxima de resultados | `50` (padrão: 100) |
| `offset` | int | Paginação (deslocamento) | `0` (padrão: 0) |

**Exemplo de requisição:**
```bash
curl -X GET "https://salao.develoi.com/api/?action=clientes&busca=Silva" \
  -H "Authorization: Bearer 12345678900"
```

**Resposta (200 OK):**
```json
{
  "success": true,
  "message": "Clientes recuperados com sucesso",
  "data": {
    "total": 25,
    "limite": 100,
    "offset": 0,
    "clientes": [
      {
        "id": 45,
        "user_id": 1,
        "nome": "Maria Silva Santos",
        "telefone": "(11) 98765-4321",
        "data_nascimento": "1990-05-15",
        "data_nascimento_br": "15/05/1990",
        "created_at": "2024-01-15 10:30:00",
        "total_agendamentos": 12
      }
    ]
  },
  "timestamp": "2024-12-01 15:45:30"
}
```

---

### 4️⃣ Listar Serviços

```http
GET /api/?action=servicos
```

**Parâmetros opcionais:**

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `tipo` | string | Filtrar por tipo | `simples` ou `pacote` |

**Exemplo de requisição:**
```bash
curl -X GET "https://salao.develoi.com/api/?action=servicos&tipo=simples" \
  -H "Authorization: Bearer 12345678900"
```

**Resposta (200 OK):**
```json
{
  "success": true,
  "message": "Serviços recuperados com sucesso",
  "data": {
    "total": 8,
    "servicos": [
      {
        "id": 1,
        "user_id": 1,
        "nome": "Corte Feminino",
        "tipo": "simples",
        "preco": 80.00,
        "duracao_minutos": 60,
        "descricao": "Corte completo com lavagem e finalização",
        "created_at": "2024-01-10 09:00:00"
      },
      {
        "id": 5,
        "user_id": 1,
        "nome": "Pacote Noiva Completo",
        "tipo": "pacote",
        "preco": 450.00,
        "duracao_minutos": 240,
        "descricao": "Cabelo, maquiagem e unhas",
        "itens_pacote": "1,3,7",
        "itens_detalhados": [
          {
            "id": 1,
            "nome": "Corte Feminino",
            "preco": 80.00
          },
          {
            "id": 3,
            "nome": "Maquiagem Profissional",
            "preco": 200.00
          },
          {
            "id": 7,
            "nome": "Manicure Completa",
            "preco": 50.00
          }
        ]
      }
    ]
  },
  "timestamp": "2024-12-01 15:45:30"
}
```

---

### 5️⃣ Dados do Profissional

```http
GET /api/?action=profissional
```

Retorna todos os dados do estabelecimento/profissional autenticado (exceto senha e CPF).

**Exemplo de requisição:**
```bash
curl -X GET "https://salao.develoi.com/api/?action=profissional" \
  -H "Authorization: Bearer 12345678900"
```

**Resposta (200 OK):**
```json
{
  "success": true,
  "message": "Dados do profissional recuperados com sucesso",
  "data": {
    "id": 1,
    "nome": "João Silva",
    "estabelecimento": "Salão Develoi Hair",
    "tipo_estabelecimento": "Salão de Beleza",
    "email": "contato@salaodeveloi.com.br",
    "telefone": "(11) 99999-8888",
    "instagram": "salaodeveloi",
    "biografia": "Especialistas em cortes modernos e coloração",
    "foto": "uploads/avatar_1_abc123.jpg",
    "cep": "01310-100",
    "endereco": "Avenida Paulista",
    "numero": "1578",
    "bairro": "Bela Vista",
    "cidade": "São Paulo",
    "estado": "SP",
    "cor_tema": "#6366f1"
  },
  "timestamp": "2024-12-01 15:45:30"
}
```

---

## ❌ Códigos de Erro

| Código | Descrição |
|--------|-----------|
| `400` | Bad Request - Parâmetros inválidos |
| `401` | Unauthorized - CPF não fornecido ou inválido |
| `403` | Forbidden - CPF não autorizado |
| `404` | Not Found - Endpoint não encontrado |
| `500` | Internal Server Error - Erro no servidor |

**Exemplo de erro (401):**
```json
{
  "success": false,
  "message": "CPF inválido",
  "data": null,
  "timestamp": "2024-12-01 15:45:30"
}
```

---

## 🔒 Segurança Implementada

✅ **Autenticação por CPF** - Apenas o dono dos dados pode acessar  
✅ **Validação completa de CPF** - Verifica formato e dígitos verificadores  
✅ **Logs de acesso** - Registra todas as consultas realizadas  
✅ **Prepared Statements** - Proteção contra SQL Injection  
✅ **CORS habilitado** - Permite requisições de qualquer origem  
✅ **Headers de segurança** - Content-Type e encoding UTF-8  

---

## 📊 Logs de Acesso

Todos os acessos à API são registrados na tabela `api_logs` com:

- ID do usuário
- Endpoint acessado
- Endereço IP
- User-Agent
- Data/hora

Para consultar os logs (via SQL):
```sql
SELECT * FROM api_logs 
WHERE user_id = 1 
ORDER BY created_at DESC 
LIMIT 50;
```

---

## 🧪 Testando a API

### Com cURL (Terminal)

```bash
# Agendamentos do mês
curl -X GET "https://salao.develoi.com/api/?action=agendamentos&data_inicio=2024-12-01&data_fim=2024-12-31" \
  -H "Authorization: Bearer 12345678900"

# Horários livres para amanhã
curl -X GET "https://salao.develoi.com/api/?action=horarios_livres&data=2024-12-06&duracao=60" \
  -H "Authorization: Bearer 12345678900"

# Todos os clientes
curl -X GET "https://salao.develoi.com/api/?action=clientes" \
  -H "Authorization: Bearer 12345678900"
```

### Com JavaScript (Frontend)

```javascript
const CPF = '12345678900';
const API_URL = 'https://salao.develoi.com/api/';

async function buscarAgendamentos() {
    try {
        const response = await fetch(`${API_URL}?action=agendamentos&data_inicio=2024-12-01`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${CPF}`,
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Agendamentos:', data.data.agendamentos);
        } else {
            console.error('Erro:', data.message);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

buscarAgendamentos();
```

### Com Python

```python
import requests

CPF = '12345678900'
API_URL = 'https://salao.develoi.com/api/'

headers = {
    'Authorization': f'Bearer {CPF}',
    'Content-Type': 'application/json'
}

# Buscar serviços
response = requests.get(
    f'{API_URL}?action=servicos',
    headers=headers
)

data = response.json()

if data['success']:
    print('Serviços:', data['data']['servicos'])
else:
    print('Erro:', data['message'])
```

---

## 📝 Notas Importantes

1. **CPF é sensível** - Nunca exponha o CPF em URLs ou logs públicos
2. **Cache** - Considere implementar cache para otimizar performance
3. **Rate Limiting** - Atualmente não há limite, mas pode ser adicionado se necessário
4. **Paginação** - Use `limite` e `offset` para grandes volumes de dados
5. **Timezone** - Todos os horários estão em horário local do servidor

---

## 🆘 Suporte

Para dúvidas ou problemas:
- 📧 Email: contato@salao.develoi.com
- 📱 WhatsApp: (11) 99999-8888

---

**Versão da API:** 1.0  
**Última atualização:** Dezembro 2024
