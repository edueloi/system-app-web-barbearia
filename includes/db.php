<?php
// includes/db.php

$dbPath = __DIR__ . '/../banco_salao.sqlite';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- CRIAÇÃO DAS TABELAS ---

    // 1. Agendamentos
    $pdo->exec("CREATE TABLE IF NOT EXISTS agendamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cliente_id INTEGER,
        cliente_nome TEXT NOT NULL,
        cliente_cpf TEXT,
        servico TEXT NOT NULL,
        valor REAL DEFAULT 0,
        data_agendamento DATE NOT NULL,
        horario TIME NOT NULL,
        status TEXT DEFAULT 'Pendente',
        observacoes TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Serviços
    $pdo->exec("CREATE TABLE IF NOT EXISTS servicos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        nome TEXT NOT NULL,
        preco REAL NOT NULL,
        duracao INTEGER NOT NULL,
        foto TEXT,
        observacao TEXT,
        tipo TEXT DEFAULT 'unico', 
        itens_pacote TEXT, 
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Clientes
    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        nome TEXT NOT NULL,
        telefone TEXT,
        cpf TEXT,
        email TEXT,
        data_nascimento DATE,
        observacoes TEXT, 
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Horários
    $pdo->exec("CREATE TABLE IF NOT EXISTS horarios_atendimento (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        dia_semana INTEGER NOT NULL,
        inicio TEXT NOT NULL,
        fim TEXT NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Usuários (COM SENHA)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT, email TEXT, telefone TEXT, 
        senha TEXT, -- Campo Essencial para Login
        foto TEXT, biografia TEXT,
        cep TEXT, endereco TEXT, numero TEXT, bairro TEXT, cidade TEXT, estado TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. NOVA TABELA: Produtos e Estoque
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        nome TEXT NOT NULL,
        marca TEXT,
        quantidade INTEGER DEFAULT 0,
        unidade TEXT DEFAULT 'unidade', -- Ex: ml, kg, unidade
        custo_unitario REAL,           -- Valor pago ao fornecedor
        preco_venda REAL,              -- Valor sugerido para venda (se aplicável)
        data_compra DATE,
        data_validade DATE,
        observacoes TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. Tabela de Notificações/Alertas
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- agendamento, produto, etc
        message TEXT NOT NULL,
        link TEXT, -- link para ação
        is_read INTEGER DEFAULT 0, -- 0=não lido, 1=lido
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- MIGRATIONS (Atualiza bancos antigos) ---
    
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN cpf TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN cliente_cpf TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN cliente_id INTEGER"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN valor REAL DEFAULT 0"); } catch (Exception $e) { }
    // Importante: Adiciona a coluna senha se não existir
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN senha TEXT"); } catch (Exception $e) { }

    // --- SEEDS (Dados Iniciais) ---
    
    // Verifica se usuário existe
    $check = $pdo->query("SELECT count(*) FROM usuarios WHERE id = 1")->fetchColumn();
    
    // Senha padrão hash: 123456
    $senhaPadrao = password_hash('123456', PASSWORD_DEFAULT);

    if ($check == 0) {
        // Cria usuário novo com senha
        $pdo->prepare("INSERT INTO usuarios (id, nome, email, senha) VALUES (1, 'Profissional', 'admin@salao.com', ?)")
            ->execute([$senhaPadrao]);
    } else {
        // Se usuário já existe mas não tem senha (migração), define a padrão
        $user = $pdo->query("SELECT senha FROM usuarios WHERE id = 1")->fetch();
        if (empty($user['senha'])) {
            $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = 1")->execute([$senhaPadrao]);
        }
    }

} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
}
?>