<?php
// includes/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbPath = __DIR__ . '/../banco_salao.sqlite';

try {
    // 1. CONEXÃO
    $pdo = new PDO("sqlite:$dbPath");

    // 2. CONFIGURAÇÕES ANTI-TRAVAMENTO (CRÍTICO)
    // Lança exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Retorna arrays associativos (padrão)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Aumenta o tempo de espera para 15 segundos antes de dar erro "database locked"
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 15);

    // evita "database is locked"
$pdo->exec('PRAGMA busy_timeout = 5000;'); // 5 segundos
$pdo->exec('PRAGMA journal_mode = WAL;');  // melhor para concorrência

    // ATIVA O MODO WAL (Write-Ahead Logging)
    // Isso é o segredo para não travar no Windows: permite leitura e escrita simultâneas
    $pdo->exec('PRAGMA journal_mode = WAL;');
    
    // Garante um timeout interno do SQLite também
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    // =========================================================
    // CRIAÇÃO DAS TABELAS PRINCIPAIS
    // =========================================================

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

    // 4. Horários de atendimento
    $pdo->exec("CREATE TABLE IF NOT EXISTS horarios_atendimento (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        dia_semana INTEGER NOT NULL,
        inicio TEXT NOT NULL,
        fim TEXT NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Usuários (profissionais)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        email TEXT,
        telefone TEXT,
        senha TEXT,
        foto TEXT,
        biografia TEXT,
        cep TEXT,
        endereco TEXT,
        numero TEXT,
        bairro TEXT,
        cidade TEXT,
        estado TEXT,
        estabelecimento TEXT,
        cor_tema TEXT DEFAULT '#4f46e5',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Produtos / Estoque
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        nome TEXT NOT NULL,
        marca TEXT,
        quantidade INTEGER DEFAULT 0,
        unidade TEXT DEFAULT 'unidade', -- ml, kg, unidade, etc
        custo_unitario REAL,
        preco_venda REAL,
        data_compra DATE,
        data_validade DATE,
        observacoes TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. Notificações / Alertas
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- agendamento, produto, etc
        message TEXT NOT NULL,
        link TEXT,
        is_read INTEGER DEFAULT 0, -- 0=não lido, 1=lido
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // =========================================================
    // MIGRAÇÕES (para bancos antigos já existentes)
    // =========================================================

    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cor_tema TEXT DEFAULT '#4f46e5'"); } catch (Exception $e) {}
    // Clientes
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN cpf TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN telefone TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN email TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN data_nascimento DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE clientes ADD COLUMN observacoes TEXT"); } catch (Exception $e) {}

    // Agendamentos
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN cliente_cpf TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN cliente_id INTEGER"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN valor REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN observacoes TEXT"); } catch (Exception $e) {}

    // Usuários
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN senha TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN biografia TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cep TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN endereco TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN numero TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN bairro TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cidade TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN estado TEXT"); } catch (Exception $e) {}
    // Adiciona o campo estabelecimento se não existir
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN estabelecimento TEXT"); } catch (Exception $e) {}

    // Produtos
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN marca TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN quantidade INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN unidade TEXT DEFAULT 'unidade'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN custo_unitario REAL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN preco_venda REAL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN data_compra DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN data_validade DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN observacoes TEXT"); } catch (Exception $e) {}

    // =========================================================
    // SEED BÁSICO (Usuário padrão id=1)
    // =========================================================

    $check = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id = 1")->fetchColumn();
    $senhaPadrao = password_hash('123456', PASSWORD_DEFAULT);

    if ($check == 0) {
        $stmtSeed = $pdo->prepare("
            INSERT INTO usuarios (id, nome, email, senha, cidade, estado)
            VALUES (1, 'Profissional', 'admin@salao.com', ?, 'Tatuí', 'SP')
        ");
        $stmtSeed->execute([$senhaPadrao]);
    } else {
        $user = $pdo->query("SELECT senha FROM usuarios WHERE id = 1")->fetch();
        if (empty($user['senha'])) {
            $stmtUpd = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = 1");
            $stmtUpd->execute([$senhaPadrao]);
        }
    }

} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
}
