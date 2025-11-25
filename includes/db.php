<?php
// includes/db.php

$dbPath = __DIR__ . '/../banco_salao.sqlite';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- CRIAÇÃO DAS TABELAS (Estrutura Base) ---

    // 1. Agendamentos
    // Já incluímos os campos novos aqui para instalações novas
    $pdo->exec("CREATE TABLE IF NOT EXISTS agendamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cliente_id INTEGER,   -- Link com a tabela clientes
        cliente_nome TEXT NOT NULL,
        cliente_cpf TEXT,     -- CPF do cliente
        servico TEXT NOT NULL,
        valor REAL DEFAULT 0, -- Valor do serviço (Novo)
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
        cpf TEXT,             -- CPF
        email TEXT,
        data_nascimento DATE,
        observacoes TEXT, 
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Horários de Atendimento
    $pdo->exec("CREATE TABLE IF NOT EXISTS horarios_atendimento (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        dia_semana INTEGER NOT NULL,
        inicio TEXT NOT NULL,
        fim TEXT NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Usuários (Perfil do Profissional)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        email TEXT,
        telefone TEXT,
        foto TEXT,
        biografia TEXT,
        cep TEXT,
        endereco TEXT,
        numero TEXT,
        bairro TEXT,
        cidade TEXT,
        estado TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // --- ATUALIZAÇÃO AUTOMÁTICA (MIGRATION) ---
    // Isto garante que quem já tem o banco criado receba as novas colunas sem perder dados.

    // 1. Atualizar Tabela Clientes (Adicionar CPF)
    try { 
        $pdo->exec("ALTER TABLE clientes ADD COLUMN cpf TEXT"); 
    } catch (Exception $e) { /* Ignora se já existir */ }

    // 2. Atualizar Tabela Agendamentos (Adicionar CPF, ID e Valor)
    try { 
        $pdo->exec("ALTER TABLE agendamentos ADD COLUMN cliente_cpf TEXT"); 
    } catch (Exception $e) { }

    try { 
        $pdo->exec("ALTER TABLE agendamentos ADD COLUMN cliente_id INTEGER"); 
    } catch (Exception $e) { }

    try { 
        $pdo->exec("ALTER TABLE agendamentos ADD COLUMN valor REAL DEFAULT 0"); 
    } catch (Exception $e) { }


    // --- DADOS PADRÃO (SEEDS) ---
    
    // Cria usuário padrão se a tabela estiver vazia
    $check = $pdo->query("SELECT count(*) FROM usuarios WHERE id = 1")->fetchColumn();
    if ($check == 0) {
        $pdo->exec("INSERT INTO usuarios (id, nome, email) VALUES (1, 'Profissional', 'admin@salao.com')");
    }

} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
}
?>