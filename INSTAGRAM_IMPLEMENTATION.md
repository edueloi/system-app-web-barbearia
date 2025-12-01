# 📸 Campo Instagram Adicionado

## ✅ Arquivos Modificados:

### 1. **agendar.php**
- Adicionada variável `$instagram` para pegar do banco
- Link clicável do Instagram exibido abaixo do telefone
- Hover com gradiente Instagram (rosa/roxo)
- Abre em nova aba direto no perfil

### 2. **pages/perfil/perfil.php**
- Campo Instagram no formulário de perfil
- Validação automática: apenas letras, números, ponto e underscore
- Prefixo `@` fixo (usuário digita só o nome)
- Atualização no banco incluindo instagram

### 3. **sql/add_instagram.sql**
- Script SQL para adicionar coluna `instagram` na tabela `usuarios`

---

## 🔧 Como Executar:

### **Passo 1: Adicionar a coluna no banco**

Execute o arquivo SQL no phpMyAdmin ou MySQL:

```bash
# No terminal MySQL:
mysql -u root -p salao_db < sql/add_instagram.sql

# OU copie e cole no phpMyAdmin:
```

```sql
ALTER TABLE usuarios 
ADD COLUMN instagram VARCHAR(100) NULL AFTER telefone;
```

### **Passo 2: Testar**

1. Acesse o **Perfil** no painel
2. Preencha o campo Instagram (ex: `seuperfil`)
3. Salve
4. Acesse a página de agendamento público
5. Veja o ícone do Instagram aparecer
6. Clique e veja abrir: `https://instagram.com/seuperfil`

---

## 🎨 Visual:

**No Perfil:**
```
📸 Instagram
@ [seuperfil_______________]
ℹ️ Digite apenas o nome do perfil (sem @)
```

**No Agendar (público):**
```
📞 (11) 98765-4321
📸 @seuperfil          ← clicável, hover com gradiente Instagram
📍 Rua Exemplo, 123
```

---

## 💡 Recursos:

✅ Validação em tempo real (apenas caracteres válidos)  
✅ Remove @ automático se o usuário digitar  
✅ Link direto para o Instagram  
✅ Hover animado com cores do Instagram  
✅ Responsivo mobile  

Tudo pronto! 🚀
