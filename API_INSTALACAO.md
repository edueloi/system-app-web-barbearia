# 🚀 Instalação e Configuração da API REST

## 📋 Pré-requisitos

- PHP 7.4 ou superior
- SQLite3
- Servidor web (Apache/Nginx)
- Sistema Salão Develoi já instalado

---

## 🔧 Passo 1: Atualizar o Banco de Dados

Execute o script SQL para adicionar a coluna CPF na tabela usuarios:

```bash
# No terminal, navegue até a pasta sql
cd sql/

# Execute o script de migração
sqlite3 ../includes/database.db < add_cpf_usuarios.sql
```

Ou execute manualmente no SQLite:

```sql
ALTER TABLE usuarios ADD COLUMN cpf TEXT;
CREATE UNIQUE INDEX idx_usuarios_cpf ON usuarios(cpf);
```

---

## 📝 Passo 2: Cadastrar CPF do Profissional

1. Acesse o sistema como profissional
2. Vá em **"Meu Perfil"**
3. Preencha o campo **CPF** (com ou sem máscara)
4. Clique em **"Salvar alterações"**

> ⚠️ **Importante:** Sem o CPF cadastrado, a API não funcionará!

---

## 🌐 Passo 3: Testar a API

### Opção 1: Interface Web (Recomendado)

Acesse no navegador:

**Produção:**
```
https://salao.develoi.com/api/teste.html
```

**Local:**
```
http://localhost/karen_site/controle-salao/api/teste.html
```

### Opção 2: cURL (Terminal)

```bash
# Substitua 12345678900 pelo CPF cadastrado
curl -X GET "https://salao.develoi.com/api/?action=profissional" \
  -H "Authorization: Bearer 12345678900"
```

### Opção 3: Postman / Insomnia

1. Crie uma nova requisição GET
2. URL: `https://salao.develoi.com/api/?action=profissional`
3. Adicione header:
   - **Key:** `Authorization`
   - **Value:** `Bearer 12345678900` (substitua pelo CPF real)

---

## 📊 Estrutura dos Arquivos Criados

```
controle-salao/
├── api/
│   ├── index.php          # API principal (endpoints)
│   └── teste.html         # Interface de teste
├── sql/
│   └── add_cpf_usuarios.sql  # Migração SQL
├── pages/
│   └── perfil/
│       └── perfil.php     # Atualizado com campo CPF
└── API_DOCUMENTACAO.md    # Documentação completa
```

---

## 🔐 Segurança

### ✅ Recursos Implementados

- **Autenticação obrigatória** via CPF
- **Validação completa de CPF** (formato + dígitos verificadores)
- **Prepared Statements** (proteção contra SQL Injection)
- **Logs de acesso** (registra todas as consultas)
- **Headers de segurança** (CORS, Content-Type)
- **CPF único** por usuário (índice único no banco)

### 🛡️ Boas Práticas

1. **Nunca exponha o CPF** em URLs públicas ou logs
2. **Use HTTPS** em produção (já configurado em salao.develoi.com)
3. **Monitore os logs** regularmente via `api_logs` table
4. **Rotacione CPFs** se suspeitar de vazamento

---

## 📖 Endpoints Disponíveis

| Endpoint | Descrição | Parâmetros |
|----------|-----------|------------|
| `?action=agendamentos` | Lista agendamentos | `data_inicio`, `data_fim`, `status`, `limite`, `offset` |
| `?action=horarios_livres` | Horários disponíveis | `data`, `duracao` |
| `?action=clientes` | Lista clientes | `busca`, `limite`, `offset` |
| `?action=servicos` | Lista serviços | `tipo` (simples/pacote) |
| `?action=profissional` | Dados do estabelecimento | - |

> 📘 **Documentação completa:** Veja `API_DOCUMENTACAO.md`

---

## 🐛 Solução de Problemas

### Erro 401: "CPF inválido"

**Causa:** CPF não passa na validação  
**Solução:** Verifique se o CPF tem 11 dígitos e é válido

### Erro 403: "CPF não autorizado"

**Causa:** CPF não está cadastrado no banco  
**Solução:** Acesse "Meu Perfil" e cadastre o CPF

### Erro 404: "Endpoint não encontrado"

**Causa:** Parâmetro `action` incorreto  
**Solução:** Use um dos 5 endpoints válidos

### Erro 500: "Internal Server Error"

**Causa:** Erro no servidor ou banco de dados  
**Solução:** Verifique os logs do PHP e se o banco está acessível

---

## 📊 Consultando Logs de Acesso

```sql
-- Ver últimos 20 acessos
SELECT 
    u.nome as profissional,
    al.endpoint,
    al.ip_address,
    al.created_at
FROM api_logs al
JOIN usuarios u ON al.user_id = u.id
ORDER BY al.created_at DESC
LIMIT 20;

-- Contar acessos por endpoint
SELECT 
    endpoint,
    COUNT(*) as total_acessos
FROM api_logs
GROUP BY endpoint
ORDER BY total_acessos DESC;
```

---

## 🔄 Atualizações Futuras

Recursos que podem ser adicionados:

- [ ] Rate limiting (limite de requisições por minuto)
- [ ] Autenticação JWT (mais segura que CPF)
- [ ] Versionamento da API (v1, v2)
- [ ] Webhooks para notificações em tempo real
- [ ] Filtros avançados e ordenação
- [ ] Exportação em CSV/PDF

---

## 📞 Suporte

Problemas? Entre em contato:

- 📧 Email: contato@salao.develoi.com
- 💬 WhatsApp: (11) 99999-8888
- 📖 Documentação: `API_DOCUMENTACAO.md`

---

**Desenvolvido com ❤️ pela equipe Develoi**  
**Versão:** 1.0 | **Data:** Dezembro 2024
