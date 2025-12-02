<?php
/**
 * ========================================================================
 * API REST - Sistema Salão Develoi
 * ========================================================================
 * 
 * API segura para consulta de dados do estabelecimento
 * Autenticação por CPF do profissional
 * 
 * Endpoints disponíveis:
 * - GET /api/?action=agendamentos
 * - GET /api/?action=horarios_livres
 * - GET /api/?action=clientes
 * - GET /api/?action=servicos
 * - GET /api/?action=profissional
 * 
 * Autenticação: Header Authorization: Bearer CPF_SEM_MASCARA
 * ========================================================================
 */

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responde OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carrega banco de dados
require_once __DIR__ . '/../includes/db.php';

// ========================================================================
// FUNÇÕES AUXILIARES
// ========================================================================

/**
 * Retorna resposta JSON padronizada
 */
function jsonResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Valida CPF (formato e dígitos verificadores)
 */
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : (11 - $resto);
    
    if (intval($cpf[9]) !== $digito1) {
        return false;
    }
    
    // Valida segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : (11 - $resto);
    
    if (intval($cpf[10]) !== $digito2) {
        return false;
    }
    
    return true;
}

/**
 * Autentica usuário por CPF
 */
function autenticarPorCPF($pdo) {
    // Busca CPF no header Authorization
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        jsonResponse(false, null, 'Header Authorization não fornecido', 401);
    }
    
    // Remove "Bearer " se existir
    $cpf = str_replace('Bearer ', '', $authHeader);
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Valida formato do CPF
    if (!validarCPF($cpf)) {
        jsonResponse(false, null, 'CPF inválido', 401);
    }
    
    // Busca usuário pelo CPF
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE cpf = ? LIMIT 1");
    $stmt->execute([$cpf]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        jsonResponse(false, null, 'CPF não autorizado', 403);
    }
    
    // Log de acesso
    logAcesso($pdo, $usuario['id'], $_GET['action'] ?? 'unknown');
    
    return $usuario;
}

/**
 * Registra log de acesso à API
 */
function logAcesso($pdo, $userId, $endpoint) {
    try {
        // Cria tabela de logs se não existir
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS api_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                endpoint TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (user_id, endpoint, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $endpoint,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Não interrompe a execução se o log falhar
        error_log('Erro ao registrar log de API: ' . $e->getMessage());
    }
}

/**
 * Formata data para padrão brasileiro
 */
function formatarDataBR($data) {
    if (empty($data)) return '';
    $timestamp = strtotime($data);
    return $timestamp ? date('d/m/Y', $timestamp) : $data;
}

/**
 * Formata horário
 */
function formatarHorario($horario) {
    if (empty($horario)) return '';
    $timestamp = strtotime($horario);
    return $timestamp ? date('H:i', $timestamp) : $horario;
}

// ========================================================================
// AUTENTICAÇÃO
// ========================================================================

$usuario = autenticarPorCPF($pdo);
$userId = $usuario['id'];

// ========================================================================
// ROTEAMENTO DE ENDPOINTS
// ========================================================================

$action = $_GET['action'] ?? '';

switch ($action) {
    
    // ====================================================================
    // ENDPOINT: Listar todos os agendamentos
    // ====================================================================
    case 'agendamentos':
        $dataInicio = $_GET['data_inicio'] ?? null;
        $dataFim = $_GET['data_fim'] ?? null;
        $status = $_GET['status'] ?? null;
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $sql = "SELECT 
                    a.*,
                    c.nome as cliente_nome_completo,
                    c.telefone as cliente_telefone,
                    c.data_nascimento as cliente_nascimento
                FROM agendamentos a
                LEFT JOIN clientes c ON a.cliente_id = c.id
                WHERE a.user_id = ?";
        
        $params = [$userId];
        
        if ($dataInicio) {
            $sql .= " AND a.data_agendamento >= ?";
            $params[] = $dataInicio;
        }
        
        if ($dataFim) {
            $sql .= " AND a.data_agendamento <= ?";
            $params[] = $dataFim;
        }
        
        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY a.data_agendamento DESC, a.horario DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formata datas para o padrão brasileiro
        foreach ($agendamentos as &$ag) {
            $ag['data_agendamento_br'] = formatarDataBR($ag['data_agendamento']);
            $ag['horario_formatado'] = formatarHorario($ag['horario']);
        }
        
        jsonResponse(true, [
            'total' => count($agendamentos),
            'limite' => $limite,
            'offset' => $offset,
            'agendamentos' => $agendamentos
        ], 'Agendamentos recuperados com sucesso');
        break;
    
    // ====================================================================
    // ENDPOINT: Horários livres para uma data específica
    // ====================================================================
    case 'horarios_livres':
        $data = $_GET['data'] ?? date('Y-m-d');
        $duracaoServico = isset($_GET['duracao']) ? (int)$_GET['duracao'] : 60;
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            jsonResponse(false, null, 'Data inválida. Use formato YYYY-MM-DD', 400);
        }
        
        $diaSemana = date('w', strtotime($data));
        
        // Busca turnos de atendimento
        $stmt = $pdo->prepare("
            SELECT inicio, fim, intervalo_minutos 
            FROM horarios_atendimento 
            WHERE user_id = ? AND dia_semana = ?
        ");
        $stmt->execute([$userId, $diaSemana]);
        $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca horários ocupados
        $stmt = $pdo->prepare("
            SELECT horario 
            FROM agendamentos 
            WHERE user_id = ? AND data_agendamento = ? AND status != 'Cancelado'
        ");
        $stmt->execute([$userId, $data]);
        $ocupados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcula minutos ocupados
        $minutosOcupados = [];
        foreach ($ocupados as $ag) {
            $hm = explode(':', $ag['horario']);
            $inicioMin = ((int)$hm[0] * 60) + (int)$hm[1];
            for ($m = $inicioMin; $m < ($inicioMin + $duracaoServico); $m++) {
                $minutosOcupados[$m] = true;
            }
        }
        
        // Calcula slots disponíveis
        $slots = [];
        foreach ($turnos as $turno) {
            $ini = explode(':', $turno['inicio']);
            $fim = explode(':', $turno['fim']);
            $start = ($ini[0] * 60) + $ini[1];
            $end = ($fim[0] * 60) + $fim[1];
            $intervalo = !empty($turno['intervalo_minutos']) ? (int)$turno['intervalo_minutos'] : 30;
            
            for ($time = $start; $time <= ($end - $duracaoServico); $time += $intervalo) {
                $livre = true;
                for ($check = $time; $check < ($time + $duracaoServico); $check++) {
                    if (isset($minutosOcupados[$check])) {
                        $livre = false;
                        break;
                    }
                }
                if ($livre) {
                    $slots[] = sprintf('%02d:%02d', floor($time / 60), $time % 60);
                }
            }
        }
        
        jsonResponse(true, [
            'data' => $data,
            'dia_semana' => $diaSemana,
            'duracao_servico' => $duracaoServico,
            'total_slots' => count($slots),
            'horarios_livres' => $slots
        ], 'Horários livres calculados com sucesso');
        break;
    
    // ====================================================================
    // ENDPOINT: Listar clientes
    // ====================================================================
    case 'clientes':
        $busca = $_GET['busca'] ?? null;
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $sql = "SELECT * FROM clientes WHERE user_id = ?";
        $params = [$userId];
        
        if ($busca) {
            $sql .= " AND (nome LIKE ? OR telefone LIKE ?)";
            $params[] = "%$busca%";
            $params[] = "%$busca%";
        }
        
        $sql .= " ORDER BY nome ASC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formata datas
        foreach ($clientes as &$cliente) {
            $cliente['data_nascimento_br'] = formatarDataBR($cliente['data_nascimento']);
            
            // Conta agendamentos do cliente
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM agendamentos 
                WHERE cliente_id = ? AND user_id = ?
            ");
            $stmtCount->execute([$cliente['id'], $userId]);
            $count = $stmtCount->fetch(PDO::FETCH_ASSOC);
            $cliente['total_agendamentos'] = $count['total'];
        }
        
        jsonResponse(true, [
            'total' => count($clientes),
            'limite' => $limite,
            'offset' => $offset,
            'clientes' => $clientes
        ], 'Clientes recuperados com sucesso');
        break;
    
    // ====================================================================
    // ENDPOINT: Listar serviços
    // ====================================================================
    case 'servicos':
        $tipo = $_GET['tipo'] ?? null; // 'simples' ou 'pacote'
        
        $sql = "SELECT * FROM servicos WHERE user_id = ?";
        $params = [$userId];
        
        if ($tipo) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo;
        }
        
        $sql .= " ORDER BY nome ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Para pacotes, busca os itens
        foreach ($servicos as &$servico) {
            if ($servico['tipo'] === 'pacote' && !empty($servico['itens_pacote'])) {
                $ids = explode(',', $servico['itens_pacote']);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $stmtItens = $pdo->prepare("SELECT * FROM servicos WHERE id IN ($placeholders)");
                $stmtItens->execute($ids);
                $servico['itens_detalhados'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        jsonResponse(true, [
            'total' => count($servicos),
            'servicos' => $servicos
        ], 'Serviços recuperados com sucesso');
        break;
    
    // ====================================================================
    // ENDPOINT: Dados do profissional
    // ====================================================================
    case 'profissional':
        // Remove dados sensíveis
        $dadosProfissional = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'estabelecimento' => $usuario['estabelecimento'],
            'tipo_estabelecimento' => $usuario['tipo_estabelecimento'],
            'email' => $usuario['email'],
            'telefone' => $usuario['telefone'],
            'instagram' => $usuario['instagram'],
            'biografia' => $usuario['biografia'],
            'foto' => $usuario['foto'],
            'cep' => $usuario['cep'],
            'endereco' => $usuario['endereco'],
            'numero' => $usuario['numero'],
            'bairro' => $usuario['bairro'],
            'cidade' => $usuario['cidade'],
            'estado' => $usuario['estado'],
            'cor_tema' => $usuario['cor_tema'],
            // CPF e senha NÃO são retornados
        ];
        
        jsonResponse(true, $dadosProfissional, 'Dados do profissional recuperados com sucesso');
        break;
    
    // ====================================================================
    // ENDPOINT INVÁLIDO
    // ====================================================================
    default:
        jsonResponse(false, null, 'Endpoint não encontrado. Ações disponíveis: agendamentos, horarios_livres, clientes, servicos, profissional', 404);
        break;
}
