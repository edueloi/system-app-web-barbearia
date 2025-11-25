<?php
// includes/db.php

// Caminho para o ficheiro do banco de dados (ficará na raiz numa pasta 'db' ou na raiz)
// Vamos colocar na raiz do projeto com o nome 'banco_salao.sqlite'
$dbPath = __DIR__ . '/../banco_salao.sqlite';

try {
    // Conecta ao SQLite
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // CRIAÇÃO AUTOMÁTICA DA TABELA (Se não existir)
    // Repara que temos 'user_id' para separar os dados de cada pessoa
    $query = "CREATE TABLE IF NOT EXISTS agendamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cliente_nome TEXT NOT NULL,
        servico TEXT NOT NULL,
        data_agendamento DATE NOT NULL,
        horario TIME NOT NULL,
        status TEXT DEFAULT 'Pendente',
        observacoes TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($query);

} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
}
?>