<?php 
// includes/db.php 

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

$dbPath = __DIR__ . '/../banco_salao.sqlite'; 

try { 
    // 1. CONEXÃO 
    $pdo = new PDO("sqlite:$dbPath"); 

    // 2. CONFIGURAÇÕES ANTI-TRAVAMENTO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 15); 

    // evita "database is locked" / melhora concorrência
    $pdo->exec('PRAGMA journal_mode = WAL;');  
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
        calculo_servico_id INTEGER, 
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
        intervalo_minutos INTEGER DEFAULT 30,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP 
    )");

    // 5. Usuários (profissionais) 
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT,
        email TEXT,
        telefone TEXT,
        cpf TEXT,                         -- CPF para autenticação na API REST
        instagram TEXT,                   -- Instagram do profissional
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
        tipo_estabelecimento TEXT DEFAULT 'Salão de Beleza',
        cor_tema TEXT DEFAULT '#4f46e5',
        ativo INTEGER DEFAULT 1,
        ultimo_login DATETIME,
        data_expiracao DATE,              -- Data que vence a conta
        is_vitalicio INTEGER DEFAULT 0,   -- 1 = Vitalício (não expira / sem contador)
        indicado_por TEXT,                -- QUEM INDICOU (Kátia, Luciana, etc)
        valor_mensal REAL DEFAULT 19.90,  -- Valor que esse cliente paga por mês
        token_recuperacao TEXT,           -- Token para resetar senha
        token_validade DATETIME,          -- Validade do token
        -- Campos de lembrete por e-mail
        lembrete_email_ativo INTEGER DEFAULT 0,
        lembrete_email_tempo INTEGER DEFAULT 4,
        lembrete_email_unidade TEXT DEFAULT 'horas',
        lembrete_email_cliente INTEGER DEFAULT 1,
        lembrete_email_confirmar INTEGER DEFAULT 0,
        lembrete_email_outro TEXT,
        lembrete_email_copia INTEGER DEFAULT 0,
        -- Campos de antecedência mínima para agendamento
        agendamento_min_antecedencia INTEGER DEFAULT 4,
        agendamento_min_unidade TEXT DEFAULT 'horas',
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Produtos / Estoque 
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos ( 
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        user_id INTEGER NOT NULL, 
        nome TEXT NOT NULL, 
        marca TEXT, 
        quantidade INTEGER DEFAULT 0,     
        tamanho_embalagem REAL DEFAULT 0, 
        unidade TEXT DEFAULT 'unidade',   
        custo_unitario REAL, 
        preco_venda REAL, 
        data_compra DATE, 
        data_validade DATE, 
        observacoes TEXT, 
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP 
    )"); 

    // Garantir campos de lembrete por e-mail e antecedência mínima em usuarios (migração)
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_ativo INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_tempo INTEGER DEFAULT 4"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_unidade TEXT DEFAULT 'horas'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_cliente INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_confirmar INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_outro TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN lembrete_email_copia INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN agendamento_min_antecedencia INTEGER DEFAULT 4"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN agendamento_min_unidade TEXT DEFAULT 'horas'"); } catch (Exception $e) {}

    // 7. Notificações / Alertas 
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications ( 
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        user_id INTEGER NOT NULL, 
        type TEXT NOT NULL, 
        message TEXT NOT NULL, 
        link TEXT, 
        is_read INTEGER DEFAULT 0, 
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP 
    )"); 

    // 8. Comandas (pacotes / comandas recorrentes do paciente)
    $pdo->exec("CREATE TABLE IF NOT EXISTS comandas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cliente_id INTEGER NOT NULL,
        titulo TEXT NOT NULL,
        tipo TEXT DEFAULT 'normal',           -- normal | pacote
        status TEXT DEFAULT 'aberta',         -- aberta | fechada
        valor_total REAL DEFAULT 0,
        valor_pago REAL DEFAULT 0,
        qtd_total INTEGER DEFAULT 1,          -- quantidade total de sessões
        data_inicio DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Itens / sessões da comanda
    $pdo->exec("CREATE TABLE IF NOT EXISTS comanda_itens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        comanda_id INTEGER NOT NULL,
        numero INTEGER,                       -- 1, 2, 3...
        data_prevista DATE,
        data_realizada DATE,
        status TEXT DEFAULT 'pendente',       -- pendente | realizado | cancelado
        valor_sessao REAL DEFAULT 0,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(comanda_id) REFERENCES comandas(id) ON DELETE CASCADE
    )");

    // 9. Cálculo de serviços (custos e lucro)
    $pdo->exec("CREATE TABLE IF NOT EXISTS calculo_servico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        nome_servico TEXT NOT NULL,
        valor_cobrado REAL NOT NULL,
        custo_materiais REAL NOT NULL,
        custo_taxas REAL NOT NULL,
        lucro REAL NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS calculo_servico_materiais (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        calculo_id INTEGER NOT NULL,
        produto_id INTEGER,
        nome_material TEXT NOT NULL,
        quantidade_usada REAL,
        unidade TEXT,
        preco_produto REAL,
        quantidade_embalagem REAL,
        custo_calculado REAL,
        FOREIGN KEY (calculo_id) REFERENCES calculo_servico(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS calculo_servico_taxas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        calculo_id INTEGER NOT NULL,
        nome_taxa TEXT NOT NULL,
        valor REAL NOT NULL,
        FOREIGN KEY (calculo_id) REFERENCES calculo_servico(id) ON DELETE CASCADE
    )"); 

    // 9. Configurações gerais do sistema
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chave VARCHAR(255) UNIQUE NOT NULL,
        valor TEXT
    )");

    // ========================================================= 
    // MIGRAÇÕES (para bancos antigos já existentes) 
    // ========================================================= 

    // Horários – novo campo de intervalo
    try { $pdo->exec("ALTER TABLE horarios_atendimento ADD COLUMN intervalo_minutos INTEGER DEFAULT 30"); } catch (Exception $e) {}

    try { 
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_horarios_user_dia 
                    ON horarios_atendimento(user_id, dia_semana)");
    } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN calculo_servico_id INTEGER"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE calculo_servico_materiais ADD COLUMN produto_id INTEGER"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN tamanho_embalagem REAL DEFAULT 0"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cor_tema TEXT DEFAULT '#4f46e5'"); } catch (Exception $e) {}
    
    // Campos de pacotes (quantidade de sessões e desconto)
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN qtd_sessoes INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN desconto_tipo TEXT DEFAULT 'percentual'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN desconto_valor REAL DEFAULT 0.00"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN preco_original REAL DEFAULT 0.00"); } catch (Exception $e) {} 

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

    // Usuários (migrando para colunas novas caso não existam)
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN senha TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN biografia TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cep TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN endereco TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN numero TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN bairro TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cidade TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN estado TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN estabelecimento TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN tipo_estabelecimento TEXT DEFAULT 'Salão de Beleza'"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN instagram TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN cpf TEXT"); } catch (Exception $e) {} 

    // ⬇️ Novas colunas para recuperação de senha
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN token_recuperacao TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN token_validade DATETIME"); } catch (Exception $e) {}

    // Migrações Comandas (caso tabela antiga exista com outra estrutura)
    try { $pdo->exec("ALTER TABLE comandas ADD COLUMN titulo TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comandas ADD COLUMN valor_total REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comandas ADD COLUMN valor_pago REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comandas ADD COLUMN qtd_total INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comandas ADD COLUMN data_inicio DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comandas ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}

    // Migrações comanda_itens
    try { $pdo->exec("ALTER TABLE comanda_itens ADD COLUMN numero INTEGER"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comanda_itens ADD COLUMN data_prevista DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comanda_itens ADD COLUMN data_realizada DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comanda_itens ADD COLUMN status TEXT DEFAULT 'pendente'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comanda_itens ADD COLUMN valor_sessao REAL DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE comanda_itens ADD COLUMN criado_em DATETIME DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}

    // Índices para comandas / comanda_itens
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comandas_user_status ON comandas(user_id, status)"); } catch (Exception $e) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comanda_itens_comanda ON comanda_itens(comanda_id)"); } catch (Exception $e) {}

    // ========================================================= 
    // ÍNDICES E TABELAS PARA API REST
    // ========================================================= 
    
    // Índice único para CPF (usado na autenticação da API)
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_usuarios_cpf ON usuarios(cpf)");
    } catch (Exception $e) {}
    
    // Tabela de logs de acesso à API
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        endpoint TEXT NOT NULL,
        ip_address TEXT,
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"); 

    // Produtos (migra extra, se veio de versões mais antigas)
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN marca TEXT"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN quantidade INTEGER DEFAULT 0"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN unidade TEXT DEFAULT 'unidade'"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN custo_unitario REAL"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN preco_venda REAL"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN data_compra DATE"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN data_validade DATE"); } catch (Exception $e) {} 
    try { $pdo->exec("ALTER TABLE produtos ADD COLUMN observacoes TEXT"); } catch (Exception $e) {} 

    // NOVAS COLUNAS DE VALIDADE
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN data_expiracao DATE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN is_vitalicio INTEGER DEFAULT 0"); } catch (Exception $e) {}

    // NOVAS COLUNAS FINANCEIRAS
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN indicado_por TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN valor_mensal REAL DEFAULT 19.90"); } catch (Exception $e) {}

    // ========================================================= 
    // RECORRÊNCIA DE AGENDAMENTOS
    // ========================================================= 
    
    // Campos de recorrência na tabela servicos
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN permite_recorrencia INTEGER DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN tipo_recorrencia TEXT DEFAULT 'sem_recorrencia'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN intervalo_dias INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN duracao_meses INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN qtd_ocorrencias INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN dias_semana TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE servicos ADD COLUMN dia_fixo_mes INTEGER"); } catch (Exception $e) {}
    
    // Tabela de séries de agendamentos recorrentes
    $pdo->exec("CREATE TABLE IF NOT EXISTS agendamentos_recorrentes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        serie_id TEXT NOT NULL UNIQUE,
        cliente_id INTEGER,
        cliente_nome TEXT NOT NULL,
        servico_id INTEGER,
        servico_nome TEXT NOT NULL,
        valor REAL DEFAULT 0,
        horario TIME NOT NULL,
        tipo_recorrencia TEXT NOT NULL,
        intervalo_dias INTEGER DEFAULT 1,
        dias_semana TEXT,
        dia_fixo_mes INTEGER,
        data_inicio DATE NOT NULL,
        data_fim DATE,
        qtd_total INTEGER NOT NULL,
        observacoes TEXT,
        ativo INTEGER DEFAULT 1,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Campos para vincular agendamentos com séries recorrentes
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN serie_id TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN indice_serie INTEGER DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN e_recorrente INTEGER DEFAULT 0"); } catch (Exception $e) {}
    
    // Campo para controlar envio de lembretes automáticos
    try { $pdo->exec("ALTER TABLE agendamentos ADD COLUMN lembrete_enviado INTEGER DEFAULT 0"); } catch (Exception $e) {}
    
    // Índices para performance
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agendamentos_serie ON agendamentos(serie_id)"); } catch (Exception $e) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agendamentos_recorrentes_serie ON agendamentos_recorrentes(serie_id)"); } catch (Exception $e) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agendamentos_recorrentes_user ON agendamentos_recorrentes(user_id)"); } catch (Exception $e) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agendamentos_lembrete ON agendamentos(lembrete_enviado, data_agendamento, horario)"); } catch (Exception $e) {}

    // =========================================================
    // DIAS ESPECIAIS DE FECHAMENTO (FERIADOS)
    // =========================================================
    
    // Tabela para armazenar feriados e dias especiais de fechamento
    $pdo->exec("CREATE TABLE IF NOT EXISTS dias_especiais_fechamento (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        data DATE NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        nome VARCHAR(255) NOT NULL,
        recorrente INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )");
    
    // Índice para busca rápida por usuário e data
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_dias_especiais_user_data ON dias_especiais_fechamento(user_id, data)"); } catch (Exception $e) {}

    // =========================================================
    // SEED ADMIN (usuário ID 1, vitalício)
    // =========================================================
    $check = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id = 1")->fetchColumn();
    $senhaPadrao = password_hash('123456', PASSWORD_DEFAULT);

    if ($check == 0) {
        $stmtSeed = $pdo->prepare("
            INSERT INTO usuarios (id, nome, email, senha, cidade, estado, ativo, is_vitalicio)
            VALUES (1, 'Admin Padrão', 'admin@salao.com', ?, 'Tatuí', 'SP', 1, 1)
        ");
        $stmtSeed->execute([$senhaPadrao]);
    } else {
        // Garante que o usuário 1 tenha senha definida
        $user = $pdo->query("SELECT senha FROM usuarios WHERE id = 1")->fetch();
        if ($user && empty($user['senha'])) {
            $stmtUpd = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = 1");
            $stmtUpd->execute([$senhaPadrao]);
        }
    }

} catch (PDOException $e) { 
    die("Erro na base de dados: " . $e->getMessage()); 
}
?>
