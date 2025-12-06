<?php
require_once __DIR__ . '/../../includes/config.php';

// =========================================================
// 1. CONFIGURAÇÃO E SESSÃO
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    // Redireciona para login se não estiver logado
    $isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// Tenta incluir o DB com verificação de caminho
$dbPath = '../../includes/db.php';
if (!file_exists($dbPath)) {
    // Fallback se a estrutura de pastas for diferente
    $dbPath = 'includes/db.php';
    if (!file_exists($dbPath)) die("Erro: db.php não encontrado.");
}
require_once $dbPath;

// Tenta incluir o helper do BOT (notificações)
$botPath = __DIR__ . '/../../includes/notificar_bot.php';
if (file_exists($botPath)) {
    require_once $botPath;
}

// Parâmetros de Entrada
$dataExibida = $_GET['data'] ?? date('Y-m-d');
$viewType    = $_GET['view'] ?? 'day'; // 'day', 'week', 'month'
$hoje        = date('Y-m-d');

// 🔹 Descobre se está em produção ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$agendaUrl = $isProd
    ? '/agenda'                    // em produção usa rota amigável
    : $_SERVER['PHP_SELF'];        // local usa o próprio arquivo

// Buscar estabelecimento
$stmtUser = $pdo->prepare("SELECT estabelecimento FROM usuarios WHERE id = ?");
$stmtUser->execute([$userId]);
$userInfo = $stmtUser->fetch();
$nomeEstabelecimento = $userInfo['estabelecimento'] ?? 'Meu Salão';

// Link de agendamento online
$linkAgendamento = $isProd
    ? "https://salao.develoi.com/agendar?user={$userId}"
    : "http://localhost/karen_site/controle-salao/agendar.php?user={$userId}";

// Função Auxiliar de Redirecionamento
function redirect($data, $view)
{
    global $agendaUrl;
    header("Location: {$agendaUrl}?data=" . urlencode($data) . "&view=" . $view);
    exit;
}

// =========================================================
// 2. AÇÕES DO BACKEND (POST/GET)
// =========================================================

try {
    // 2.1 Excluir Agendamento
    if (isset($_GET['delete'])) {
        require_once __DIR__ . '/../../includes/recorrencia_helper.php';

        $id = (int)$_GET['delete'];
        $tipoExclusao = $_GET['tipo_exclusao'] ?? 'unico'; // 'unico', 'proximos', 'serie'

        // Verificar se é agendamento recorrente
        $stmt = $pdo->prepare("SELECT serie_id, e_recorrente FROM agendamentos WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        $agendamento = $stmt->fetch();

        if ($agendamento && $agendamento['e_recorrente'] && !empty($agendamento['serie_id'])) {
            // É recorrente - aplicar lógica de exclusão
            if ($tipoExclusao === 'serie') {
                cancelarSerieCompleta($pdo, $agendamento['serie_id'], $userId);
            } elseif ($tipoExclusao === 'proximos') {
                cancelarOcorrenciaEProximas($pdo, $id, $userId);
            } else {
                cancelarOcorrencia($pdo, $id, $userId);
            }
        } else {
            // Agendamento único - exclusão simples
            $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id=? AND user_id=?");
            $stmt->execute([$id, $userId]);
        }

        redirect($dataExibida, $viewType);
    }

    // 2.2 Mudar Status
    if (isset($_GET['status'], $_GET['id'])) {
        $novoStatus = $_GET['status'];
        $agendamentoId = (int)$_GET['id'];

        $stmt = $pdo->prepare("UPDATE agendamentos SET status=? WHERE id=? AND user_id=?");
        $stmt->execute([$novoStatus, $agendamentoId, $userId]);

        // Se confirmou, notifica o bot (case-insensitive, ignora espaços)
        if (mb_strtolower(trim($novoStatus)) === 'confirmado' && function_exists('notificarBotAgendamentoConfirmado')) {
            notificarBotAgendamentoConfirmado($pdo, $agendamentoId);
        }

        redirect($dataExibida, $viewType);
    }

    // 2.3 Novo Agendamento (POST) - LÓGICA + BOT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
                // Garante que temos um cliente_id válido:
                // - se digitou um nome que já existe em clientes, usamos o id existente
                // - se não existir e tiver telefone, criamos o cliente automaticamente
                if ($cliente && !$clienteId) {
                    // tenta encontrar pelo nome
                    $stmtCli = $pdo->prepare("SELECT id, telefone FROM clientes WHERE user_id = ? AND nome = ? LIMIT 1");
                    $stmtCli->execute([$userId, $cliente]);
                    $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);

                    if ($cli) {
                        $clienteId = (int)$cli['id'];
                        // se o cliente já existe mas estava sem telefone, atualiza
                        if ($telefone && empty($cli['telefone'])) {
                            $upd = $pdo->prepare("UPDATE clientes SET telefone = ? WHERE id = ?");
                            $upd->execute([$telefone, $clienteId]);
                        }
                    } elseif ($telefone) {
                        // cliente novo: cria cadastro básico
                        $ins = $pdo->prepare("INSERT INTO clientes (user_id, nome, telefone) VALUES (?, ?, ?)");
                        $ins->execute([$userId, $cliente, $telefone]);
                        $clienteId = (int)$pdo->lastInsertId();
                    }
                }
        require_once __DIR__ . '/../../includes/recorrencia_helper.php';

        $cliente   = trim($_POST['cliente'] ?? '');
        $horario   = $_POST['horario'] ?? '';
        $obs       = trim($_POST['obs'] ?? '');
        $dataAg    = $_POST['data_agendamento'] ?? $dataExibida;
        $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
        // Garante que o telefone venha de qualquer campo
        $telefone  = trim($_POST['telefone'] ?? ($_POST['telefone_mobile'] ?? ''));

        // VÁRIOS SERVIÇOS
        $servicosIds = isset($_POST['servicos_ids']) ? (array)$_POST['servicos_ids'] : [];
        $servicosIds = array_map('intval', $servicosIds);

        // Valor digitado manualmente (pode ser sobrescrito pela soma)
        $valor = isset($_POST['valor']) ? str_replace(',', '.', $_POST['valor']) : '0';
        $valor = (float)$valor;

        // Nome "texto" que vai ficar salvo no agendamento
        $servicoNome = '';
        $servicoId   = null;

        if (!empty($servicosIds)) {
            $placeholders = implode(',', array_fill(0, count($servicosIds), '?'));
            $params = array_merge([$userId], $servicosIds);
            $stmtServ = $pdo->prepare("
                SELECT id, nome, preco 
                FROM servicos 
                WHERE user_id = ? 
                  AND id IN ($placeholders)
                ORDER BY nome ASC
            ");
            $stmtServ->execute($params);
            $servs = $stmtServ->fetchAll();
            $nomes = [];
            $totalServicos = 0;
            foreach ($servs as $s) {
                $nomes[] = $s['nome'];
                $totalServicos += (float)$s['preco'];
            }
            $servicoNome = implode(' + ', $nomes);
            if ($valor <= 0 && $totalServicos > 0) {
                $valor = $totalServicos;
            }
            $servicoId = $servicosIds[0];
        }

        // Recorrência
        $recorrenciaAtiva     = isset($_POST['recorrencia_ativa']) && $_POST['recorrencia_ativa'] == '1';
        $recorrenciaIntervalo = isset($_POST['recorrencia_intervalo']) ? (int)$_POST['recorrencia_intervalo'] : 0;
        $recorrenciaQtd       = isset($_POST['recorrencia_qtd']) ? (int)$_POST['recorrencia_qtd'] : 1;
        if ($recorrenciaIntervalo < 1) $recorrenciaIntervalo = 1;
        if ($recorrenciaQtd < 1) $recorrenciaQtd = 1;

        if ($cliente && $horario) {
            $dadosAgendamento = [
                'cliente_id'    => $clienteId,
                'cliente_nome'  => $cliente,
                'servico_id'    => $servicoId,
                'servico_nome'  => $servicoNome,
                'valor'         => $valor,
                'horario'       => $horario,
                'data_inicio'   => $dataAg,
                'observacoes'   => $obs,
                'recorrencia_ativa'     => $recorrenciaAtiva,
                'recorrencia_intervalo' => $recorrenciaIntervalo,
                'recorrencia_qtd'       => $recorrenciaQtd
            ];

            // Tenta usar helper de recorrência
            if (function_exists('criarAgendamentosRecorrentes')) {
                $resultado = criarAgendamentosRecorrentes($pdo, $userId, $dadosAgendamento);

                if (!empty($resultado['sucesso'])) {
                    if (!empty($resultado['serie_id'])) {
                        $_SESSION['mensagem_sucesso'] =
                            "Série criada com {$resultado['qtd_criados']} agendamentos!";
                    } else {
                        $_SESSION['mensagem_sucesso'] = 'Agendamento criado com sucesso!';
                    }

                    // 🔔 Notifica o BOT sobre os novos agendamentos
                    if (function_exists('notificarBotNovoAgendamento')) {
                        // Se o helper já devolver IDs criados, usamos
                        if (!empty($resultado['ids_criados']) && is_array($resultado['ids_criados'])) {
                            foreach ($resultado['ids_criados'] as $novoId) {
                                notificarBotNovoAgendamento($pdo, (int)$novoId);
                            }
                        }
                        // Senão, se tiver serie_id, buscamos todos da série
                        elseif (!empty($resultado['serie_id'])) {
                            $stmtIds = $pdo->prepare("
                                SELECT id 
                                FROM agendamentos 
                                WHERE user_id = ? AND serie_id = ?
                            ");
                            $stmtIds->execute([$userId, $resultado['serie_id']]);
                            while ($idAg = $stmtIds->fetchColumn()) {
                                notificarBotNovoAgendamento($pdo, (int)$idAg);
                            }
                        }
                    }
                } else {
                    // Se helper falhar, faz fallback em 1 agendamento simples
                    $_SESSION['mensagem_erro'] = $resultado['erro'] ?? 'Erro ao criar agendamento recorrente. Salvando apenas este horário.';

                    $stmt = $pdo->prepare("
                        INSERT INTO agendamentos 
                            (user_id, cliente_id, cliente_nome, servico, valor, data_agendamento, horario, status, observacoes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)
                    ");
                    $stmt->execute([$userId, $clienteId, $cliente, $servicoNome, $valor, $dataAg, $horario, $obs]);

                    // 🔔 Notifica o BOT mesmo no fallback
                    if (function_exists('notificarBotNovoAgendamento')) {
                        $novoId = (int)$pdo->lastInsertId();
                        if ($novoId > 0) {
                            notificarBotNovoAgendamento($pdo, $novoId);
                        }
                    }
                }
            } else {
                // Se helper não existir, insere um único agendamento
                $_SESSION['mensagem_erro'] = 'Função de recorrência não encontrada. Salvando agendamento simples.';
                $stmt = $pdo->prepare("
                    INSERT INTO agendamentos 
                        (user_id, cliente_id, cliente_nome, servico, valor, data_agendamento, horario, status, observacoes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)
                ");
                $stmt->execute([$userId, $clienteId, $cliente, $servicoNome, $valor, $dataAg, $horario, $obs]);

                // 🔔 Notifica o BOT no modo simples
                if (function_exists('notificarBotNovoAgendamento')) {
                    $novoId = (int)$pdo->lastInsertId();
                    if ($novoId > 0) {
                        notificarBotNovoAgendamento($pdo, $novoId);
                    }
                }
            }
        }

        redirect($dataAg, 'day');
    }
} catch (PDOException $e) {
    die("Erro no banco de dados (Tente novamente em alguns segundos): " . $e->getMessage());
}

// =========================================================
// 3. CONSULTA DE DADOS
// =========================================================

$agendamentos = [];
$faturamento = 0;
$diasComAgendamento = [];
$agendamentosPorDia = [];

if ($viewType === 'month') {
    $start = date('Y-m-01', strtotime($dataExibida));
    $end   = date('Y-m-t', strtotime($dataExibida));
    setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
    $meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
    $tituloData = $meses[(int)date('m', strtotime($dataExibida))] . ' ' . date('Y', strtotime($dataExibida));
} elseif ($viewType === 'week') {
    $ts = strtotime($dataExibida);
    $diaSemana = date('w', $ts);
    $start = date('Y-m-d', strtotime("-$diaSemana days", $ts));
    $end   = date('Y-m-d', strtotime("+6 days", strtotime($start)));
    $tituloData = date('d/m', strtotime($start)) . ' a ' . date('d/m', strtotime($end));
} else {
    $start = $dataExibida;
    $end   = $dataExibida;
    $diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $tituloData = $diasSemana[date('w', strtotime($dataExibida))] . ', ' . date('d/m', strtotime($dataExibida));
}

$stmt = $pdo->prepare("
    SELECT 
        a.*,
        s.tipo_recorrencia,
        s.dias_semana as recorrencia_dias_semana,
        s.intervalo_dias
    FROM agendamentos a
    LEFT JOIN servicos s ON s.nome = a.servico AND s.user_id = a.user_id
    WHERE a.user_id = ? 
      AND a.data_agendamento BETWEEN ? AND ? 
    ORDER BY a.data_agendamento ASC, a.horario ASC
");
$stmt->execute([$userId, $start, $end]);
$raw = $stmt->fetchAll();

// Listas para os Modais
$servicos = $pdo->query("SELECT id, nome, preco, duracao, permite_recorrencia, tipo_recorrencia, dias_semana FROM servicos WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();
$clientes = $pdo->query("SELECT id, nome, telefone FROM clientes WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();

// corrige valor 0 usando preço do serviço
foreach ($raw as &$r) {
    if ((float)$r['valor'] <= 0) {
        foreach ($servicos as $s) {
            if ($s['nome'] === $r['servico']) {
                $r['valor'] = $s['preco'];
                break;
            }
        }
    }
}
unset($r);

// Organiza dados por view
if ($viewType === 'day') {
    $agendamentos = array_filter($raw, function ($ag) use ($dataExibida) {
        return $ag['data_agendamento'] === $dataExibida;
    });
    foreach ($agendamentos as $ag) {
        if (($ag['status'] ?? '') !== 'Cancelado') $faturamento += $ag['valor'];
    }
} elseif ($viewType === 'week') {
    for ($i = 0; $i <= 6; $i++) {
        $d = date('Y-m-d', strtotime("+$i days", strtotime($start)));
        $agendamentos[$d] = [];
    }
    foreach ($raw as $ag) {
        $agendamentos[$ag['data_agendamento']][] = $ag;
        if (($ag['status'] ?? '') !== 'Cancelado') $faturamento += $ag['valor'];
    }
} elseif ($viewType === 'month') {
    foreach ($raw as $ag) {
        $data = $ag['data_agendamento'];
        if (!isset($agendamentosPorDia[$data])) $agendamentosPorDia[$data] = 0;
        $agendamentosPorDia[$data]++;
        $diasComAgendamento[$data] = true;
        if (($ag['status'] ?? '') !== 'Cancelado') $faturamento += $ag['valor'];
    }
}

// Datas para navegação
$mod = ($viewType === 'month') ? 'month' : (($viewType === 'week') ? 'week' : 'day');
$dataAnt = date('Y-m-d', strtotime($dataExibida . " -1 $mod"));
$dataPro = date('Y-m-d', strtotime($dataExibida . " +1 $mod"));

// Includes visuais
$pageTitle = 'Minha Agenda';
include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-color: #4f46e5;
        --primary-dark: #4338ca;
        --secondary: #8b5cf6;
        --bg-page: #f8fafc;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
        --shadow-card: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
        --radius-md: 12px;
        --radius-sm: 8px;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
    }

    * { box-sizing: border-box; }

    body {
        background: var(--bg-page);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        margin: 0;
        padding-bottom: 90px;
        font-size: 0.875rem;
        color: var(--text-main);
    }

    .app-header {
        background: #ffffff;
        padding: 1rem 1.125rem;
        border-bottom: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
    }

    .agenda-title {
        font-size: 1.125rem;
        font-weight: 700;
        margin: 0 0 0.875rem 0;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .view-control {
        display: flex;
        background: #f1f5f9;
        padding: 0.25rem;
        border-radius: var(--radius-sm);
        margin-bottom: 0.875rem;
        gap: 0.25rem;
    }
    .view-opt {
        flex: 1;
        text-align: center;
        padding: 0.5rem 0;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .view-opt.active {
        background: var(--primary-color);
        color: #fff;
        box-shadow: var(--shadow-sm);
    }

    .date-nav-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.875rem;
        gap: 0.75rem;
    }
    .btn-circle {
        width: 2.25rem; height: 2.25rem;
        border-radius: 50%;
        background: #ffffff;
        border: 1px solid var(--border-color);
        color: var(--text-main);
        display:flex; align-items:center; justify-content:center;
        text-decoration:none; font-size:0.875rem;
        cursor:pointer; transition:all .2s ease;
    }
    .btn-circle:hover {
        background:var(--primary-color);
        color:#fff;
        border-color:var(--primary-color);
    }
    .date-picker-trigger { position: relative; text-align: center; }
    .current-date-label {
        font-size:0.875rem; font-weight:600; color:var(--text-main);
        display:inline-flex; align-items:center; gap:.375rem;
        padding:.375rem .75rem; border-radius:var(--radius-sm); background:#f1f5f9;
    }
    .hidden-date-input {
        position:absolute; top:0; left:0; width:100%; height:100%;
        opacity:0; cursor:pointer;
    }

    .finance-card {
        margin-top:0.75rem;
        background:linear-gradient(135deg,#10b981 0%,#059669 100%);
        color:#fff; padding:1rem; border-radius:var(--radius-md);
        display:flex; justify-content:space-between; align-items:center;
        box-shadow:0 4px 12px rgba(16,185,129,0.3);
    }
    .fin-label { font-size:.75rem; font-weight:600; text-transform:uppercase; }
    .fin-value { font-size:1.125rem; font-weight:700; }

    .link-card {
        margin-top:0.75rem;
        background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        color:#fff; padding:1rem; border-radius:var(--radius-md);
        box-shadow:0 4px 12px rgba(99,102,241,0.3);
    }
    .link-input-group { display:flex; gap:0.5rem; align-items:center; }
    .link-input {
        flex:1; background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3);
        border-radius:var(--radius-sm); padding:.5rem .75rem; color:#fff; font-size:.75rem;
    }
    .btn-copy-link,.btn-share-link{
        background:rgba(255,255,255,0.95); border:none; padding:.5rem .875rem;
        border-radius:var(--radius-sm); color:#6366f1; font-weight:600; font-size:.75rem;
        cursor:pointer; display:flex; align-items:center; gap:.375rem; transition:all .2s ease;
    }
    .btn-copy-link:hover,.btn-share-link:hover{background:#fff; transform:translateY(-1px);}

    .content-area { padding:0.75rem 0.875rem 1rem; }

    .appt-card {
        background:#fff;
        border-radius:var(--radius-md);
        padding:0.875rem 1rem;
        margin-bottom:0.75rem;
        position:relative;
        display:flex;
        gap:0.875rem;
        box-shadow:var(--shadow-card);
        border:1px solid var(--border-color);
        transition:all 0.2s ease;
    }
    .appt-card:hover{
        box-shadow:0 4px 14px rgba(15,23,42,.12);
        border-color:var(--primary-color);
    }
    .time-col{
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        min-width:3rem; background:#f8fafc; border-radius:var(--radius-sm); padding:.5rem;
    }
    .time-val{font-size:.9375rem;font-weight:700;color:var(--text-main);}
    .time-min{font-size:.6875rem;color:var(--text-muted);font-weight:500;margin-top:.125rem;}

    .info-col{flex:1;display:flex;flex-direction:column;justify-content:center;gap:.25rem;}
    .client-name{
        font-weight:600;color:var(--text-main);font-size:.875rem;
        white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
    }
    .service-row{display:flex;align-items:center;gap:.5rem;font-size:.75rem;color:var(--text-muted);flex-wrap:wrap;}
    .price-tag{
        background:#eef2ff;color:var(--primary-color);font-size:.6875rem;font-weight:600;
        padding:.1875rem .5625rem;border-radius:var(--radius-sm);
    }

    .status-badge{
        position:absolute;top:.75rem;right:.75rem;width:.5rem;height:.5rem;border-radius:50%;
    }
    .st-Confirmado{background:var(--success);}
    .st-Pendente{background:var(--warning);}
    .st-Cancelado{background:var(--danger);}

    .appt-card button{
        background:#f8fafc;
        border:1px solid var(--border-color);
        color:var(--text-muted);
        width:38px;height:38px;
        border-radius:var(--radius-sm);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;align-self:center;
        transition:all .2s ease;
        z-index:10;
    }
    .appt-card button:hover{
        background:var(--primary-color);
        color:#fff;
        border-color:var(--primary-color);
    }

    .calendar-grid{
        display:grid;
        grid-template-columns:repeat(7,1fr);
        gap:.5rem;
    }
    .week-day-name{
        text-align:center;font-size:.6875rem;font-weight:600;color:var(--text-muted);
    }
    .day-cell{
        aspect-ratio:1;
        background:#fff;border-radius:var(--radius-sm);
        border:1px solid var(--border-color);
        display:flex;align-items:center;justify-content:center;
        font-size:.8125rem;font-weight:600;
        text-decoration:none;color:var(--text-main);
        position:relative;box-shadow:var(--shadow-sm);transition:all .2s ease;
    }
    .day-cell.today{
        background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        color:#fff;border-color:var(--primary-color);
        box-shadow:0 4px 14px rgba(99,102,241,.4);
    }
    .day-cell.has-events{
        background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);
        border-color:#93c5fd;
    }
    .day-cell.empty{
        background:transparent;border:none;box-shadow:none;pointer-events:none;
    }
    .event-count-badge{
        position:absolute;top:2px;right:2px;
        background:#6366f1;color:#fff;
        font-size:.6rem;padding:1px 4px;border-radius:4px;
    }

    .week-header{
        font-size:.75rem;font-weight:600;color:#64748b;
        margin:1rem 0 .5rem;border-bottom:1px solid #e2e8f0;padding-bottom:4px;
    }

    .empty-state{
        text-align:center;padding:2rem;color:#94a3b8;
    }

    .fab-add{
        position:fixed;bottom:1.5rem;right:1.5rem;
        width:3.5rem;height:3.5rem;
        background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        color:#fff;border-radius:50%;border:none;font-size:1.5rem;
        box-shadow:0 8px 24px rgba(99,102,241,.4);
        display:flex;align-items:center;justify-content:center;
        z-index:100;cursor:pointer;transition:all .25s ease;
    }
    .fab-add:hover{transform:scale(1.08) rotate(90deg);box-shadow:0 12px 32px rgba(99,102,241,.5);}

    .modal-overlay{
        position:fixed;inset:0;background:rgba(15,23,42,.6);
        z-index:2000;display:none;align-items:flex-end;justify-content:center;
    }
    .modal-overlay.active{display:flex;animation:fadeIn .2s ease-out;}
    .sheet-modal{
        background:#fff;width:100%;max-width:500px;
        border-radius:16px 16px 0 0;
        padding:1.5rem 1.25rem 1.75rem;
        max-height:85vh;overflow-y:auto;
        animation:slideUp .3s ease-out;
    }
    /* ============================================
       MODAL "NOVO AGENDAMENTO" - VISUAL PREMIUM
       ============================================ */
    .sheet-modal-form {
        border-radius: 22px 22px 0 0;
        padding: 1.35rem 1.2rem 1.5rem;
        box-shadow: 0 -16px 40px rgba(15,23,42,0.32);
    }

    /* Cabeçalho do modal (ícone + título + x) */
    .sheet-modal-form .sheet-header {
        margin: -0.25rem -0.25rem 1rem;
        padding-bottom: 0.85rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .sheet-avatar-form {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        box-shadow: 0 4px 14px rgba(34,197,94,0.35);
    }

    .sheet-header-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: 0.15rem;
    }

    .sheet-header-tag i {
        font-size: 0.8rem;
    }

    .sheet-close {
        margin-left: auto;
        background: transparent;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: #94a3b8;
        padding: 0;
        cursor: pointer;
        transition: all .18s ease;
    }

    .sheet-close:hover {
        color: #0f172a;
        transform: scale(1.06);
    }

    /* Blocos do formulário */
    .form-section {
        background: #f8fafc;
        border-radius: var(--radius-md);
        padding: 0.8rem 0.85rem 0.9rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 0.75rem;
    }

    .form-section-title {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .form-section-title i {
        font-size: 0.85rem;
    }

    /* Pequenas fileiras dentro das sections */
    .form-row {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 0.65rem;
    }

    .form-row-3 {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 0.65rem;
    }

    /* Caixinha dos serviços */
    .services-box {
        max-height: 170px;
        overflow-y: auto;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f9fafb;
        padding: 0.4rem 0.5rem;
    }

    .service-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.3rem 0.15rem;
        font-size: 0.8rem;
    }

    .service-item input[type="checkbox"] {
        accent-color: #6366f1;
    }

    /* Toggle de recorrência tipo "pill" */
    .toggle-group {
        display: flex;
        gap: 0.4rem;
        background: #e5e7eb;
        padding: 0.25rem;
        border-radius: 999px;
    }

    .toggle-pill {
        position: relative;
        flex: 1;
    }

    .toggle-pill input {
        display: none;
    }

    .toggle-pill span {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        padding: 0.38rem 0.1rem;
        border-radius: 999px;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
        transition: all .18s ease;
    }

    .toggle-pill input:checked + span {
        background: linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        color: #fff;
        box-shadow: 0 3px 10px rgba(99,102,241,0.4);
    }

    /* Área de recorrência aberta */
    #divRec {
        margin-top: 8px;
        display: none;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    /* Botões principais do modal novo */
    .modal-actions {
        margin-top: 0.9rem;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }

    @media (max-width: 768px) {
        .form-row, .form-row-3 {
            grid-template-columns: 1fr;
        }
    }
    @keyframes slideUp{from{transform:translateY(100%);opacity:0;}to{transform:translateY(0);opacity:1;}}
    @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}

    .form-group{margin-bottom:1rem;}
    .form-label{font-size:.75rem;font-weight:600;display:block;margin-bottom:.375rem;}
    .form-input{
        width:100%;padding:.625rem .75rem;border:1px solid var(--border-color);
        border-radius:var(--radius-sm);font-size:.875rem;outline:none;
    }
    .form-input:focus{
        border-color:var(--primary-color);
        box-shadow:0 0 0 3px rgba(79,70,229,.1);
    }
    textarea.form-input{min-height:4rem;resize:vertical;}

    .btn-main{
        width:100%;padding:.75rem;
        background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        color:#fff;border:none;border-radius:var(--radius-sm);
        font-weight:600;cursor:pointer;margin-top:.75rem;
        box-shadow:0 4px 12px rgba(99,102,241,.3);
    }
    .btn-cancel{
        width:100%;padding:.75rem;
        background:#fff;border:1px solid var(--border-color);
        border-radius:var(--radius-sm);font-weight:600;
        cursor:pointer;margin-top:.625rem;
    }

    .free-slots-section{
        background:#f0f9ff;border-radius:var(--radius-md);
        padding:1rem;margin-top:1.25rem;border:1px solid #93c5fd;
    }
    .free-slots-grid{
        display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;
    }
    .slot-chip{
        background:#fff;padding:.625rem;border-radius:var(--radius-sm);
        text-align:center;font-size:.75rem;font-weight:600;
        border:1px solid #bfdbfe;cursor:pointer;transition:all .2s ease;
    }
    .slot-chip:hover{
        border-color:#6366f1;color:#6366f1;
        box-shadow:0 4px 12px rgba(99,102,241,.3);
    }

    .action-list{list-style:none;padding:0;margin:0;}
    .action-item{
        display:flex;align-items:center;gap:.75rem;
        padding:.75rem 0;border-bottom:1px solid var(--border-color);
        color:var(--text-main);text-decoration:none;font-size:.875rem;
        cursor:pointer;
    }
    .action-item span.action-text-main{
        font-weight:600;font-size:.85rem;
    }
    .action-item span.action-text-sub{
        display:block;font-size:.72rem;color:#94a3b8;margin-top:2px;
    }
    .action-item.danger{color:var(--danger);}

    /* Header do ActionSheet mais bonito */
    .sheet-header {
        display:flex;
        align-items:center;
        gap:0.75rem;
        padding-bottom:0.75rem;
        margin-bottom:0.75rem;
        border-bottom:1px solid #e2e8f0;
    }
    .sheet-avatar{
        width:40px;height:40px;border-radius:999px;
        background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-weight:700;font-size:.9rem;
        box-shadow:0 4px 12px rgba(99,102,241,0.35);
    }
    .sheet-header-info-title{
        font-weight:700;font-size:.95rem;color:#0f172a;margin-bottom:2px;
    }
    .sheet-header-info-sub{
        font-size:.78rem;color:#64748b;
    }

    @media (max-width:768px){
        .link-input-group{flex-direction:column;}
        .link-input,.btn-copy-link,.btn-share-link{width:100%;}
        .free-slots-grid{grid-template-columns:repeat(3,1fr);}
        .fab-add{bottom:1.25rem;right:1.25rem;width:3rem;height:3rem;font-size:1.25rem;}
        .sheet-modal{max-height:92vh;padding:1.25rem 1rem 1.5rem;}
    }
</style>

<div class="app-header">
    <h1 class="agenda-title">📅 Minha Agenda</h1>

    <div class="view-control">
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataExibida; ?>&view=day" class="view-opt <?php echo $viewType === 'day' ? 'active' : ''; ?>">Dia</a>
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataExibida; ?>&view=week" class="view-opt <?php echo $viewType === 'week' ? 'active' : ''; ?>">Semana</a>
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataExibida; ?>&view=month" class="view-opt <?php echo $viewType === 'month' ? 'active' : ''; ?>">Mês</a>
    </div>

    <div class="date-nav-row">
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataAnt; ?>&view=<?php echo $viewType; ?>" class="btn-circle">
            <i class="bi bi-chevron-left"></i>
        </a>
        <div class="date-picker-trigger">
            <div class="current-date-label">
                <?php echo $tituloData; ?> <i class="bi bi-caret-down-fill" style="font-size:0.65rem;"></i>
            </div>
            <input type="date" class="hidden-date-input"
                value="<?php echo $dataExibida; ?>"
                onchange="window.location.href='<?php echo $agendaUrl; ?>?view=<?php echo $viewType; ?>&data='+this.value">
        </div>
        <a href="<?php echo $agendaUrl; ?>?data=<?php echo $dataPro; ?>&view=<?php echo $viewType; ?>" class="btn-circle">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    <div class="finance-card">
        <span class="fin-label">💰 Faturamento</span>
        <span class="fin-value">R$ <?php echo number_format($faturamento, 2, ',', '.'); ?></span>
    </div>

    <div class="link-card">
        <div class="link-input-group">
            <input type="text" class="link-input" id="linkAgendamento" value="<?php echo htmlspecialchars($linkAgendamento); ?>" readonly>
            <button class="btn-copy-link" onclick="copiarLink(event)"><i class="bi bi-clipboard"></i>Copiar</button>
            <button class="btn-share-link" onclick="compartilharLink()"><i class="bi bi-share"></i>Compartilhar</button>
        </div>
    </div>
</div>

<div class="content-area">
    <?php if ($viewType === 'month'): ?>
        <div class="calendar-grid">
            <div class="week-day-name">DOM</div><div class="week-day-name">SEG</div><div class="week-day-name">TER</div>
            <div class="week-day-name">QUA</div><div class="week-day-name">QUI</div><div class="week-day-name">SEX</div><div class="week-day-name">SÁB</div>
            <?php
            $firstDay = date('Y-m-01', strtotime($dataExibida));
            $daysInMonth = date('t', strtotime($dataExibida));
            $startPad = date('w', strtotime($firstDay));
            for($k=0; $k<$startPad; $k++) echo '<div class="day-cell empty"></div>';
            for($day=1; $day<=$daysInMonth; $day++){
                $date = date('Y-m-', strtotime($dataExibida)) . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isToday = ($date === $hoje) ? 'today' : '';
                $hasEv = isset($diasComAgendamento[$date]) ? 'has-events' : '';
                $badge = isset($agendamentosPorDia[$date]) ? "<div class='event-count-badge'>{$agendamentosPorDia[$date]}</div>" : '';
                echo "<a href='{$agendaUrl}?view=day&data={$date}' class='day-cell {$isToday} {$hasEv}'>{$day}{$badge}</a>";
            }
            ?>
        </div>

    <?php elseif ($viewType === 'week'): ?>
        <?php foreach ($agendamentos as $dia => $lista): ?>
            <div class="week-header">
                <?php echo date('d/m', strtotime($dia)) . ' • ' . ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', strtotime($dia))]; ?>
            </div>
            <?php if (count($lista) > 0): ?>
                <?php foreach ($lista as $ag) renderCard($ag, $clientes); ?>
            <?php else: ?>
                <small style="color:#cbd5e1;">Livre</small>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php else: ?>
        <?php if (count($agendamentos) > 0): ?>
            <?php foreach ($agendamentos as $ag) renderCard($ag, $clientes); ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar4-week" style="font-size:2rem; opacity:0.3;"></i>
                <p>Nenhum agendamento hoje.</p>
                <button onclick="openModal()" style="color:#4f46e5; background:none; border:none; font-weight:600;">+ Adicionar</button>
            </div>
        <?php endif; ?>

        <?php
        // Horários disponíveis do dia
        $diaSemana = date('w', strtotime($dataExibida));
        $stmtH = $pdo->prepare("SELECT * FROM horarios_atendimento WHERE user_id = ? AND dia_semana = ? ORDER BY inicio ASC");
        $stmtH->execute([$userId, $diaSemana]);
        $horariosConfig = $stmtH->fetchAll();

        if(!empty($horariosConfig)):
            $ocupados = array_map(function($a){ return date('H:i', strtotime($a['horario'])); }, $agendamentos);
            $livres = [];
            foreach($horariosConfig as $cfg){
                $ini = strtotime($cfg['inicio']);
                $fim = strtotime($cfg['fim']);
                $int = ($cfg['intervalo_minutos']??30)*60;
                while($ini < $fim){
                    $h = date('H:i', $ini);
                    if(!in_array($h, $ocupados)) $livres[] = $h;
                    $ini += $int;
                }
            }
            if(!empty($livres)):
        ?>
            <div class="free-slots-section">
                <h3 style="margin:0 0 10px; font-size:0.9rem;">Horários Disponíveis (<?php echo count($livres); ?>)</h3>
                <p style="font-size:0.75rem; color:#64748b; margin:0 0 10px;">Clique em um horário para criar um agendamento rápido</p>
                <div class="free-slots-grid">
                    <?php foreach($livres as $h) echo "<div class='slot-chip' onclick=\"abrirModalComHorario('$dataExibida','$h')\">$h</div>"; ?>
                </div>
            </div>
        <?php
            endif;
        endif;
        ?>
    <?php endif; ?>
</div>

<button class="fab-add" onclick="openModal()"><i class="bi bi-plus"></i></button>

<!-- MODAL NOVO AGENDAMENTO -->
<!-- MODAL NOVO AGENDAMENTO -->
<div class="modal-overlay" id="modalAdd">
    <div class="sheet-modal sheet-modal-form">
        <div class="sheet-header">
            <div class="sheet-avatar sheet-avatar-form">
                <i class="bi bi-calendar-plus" style="font-size:1.1rem;"></i>
            </div>
            <div>
                <div class="sheet-header-tag">
                    <i class="bi bi-lightning-charge-fill"></i>
                    novo horário
                </div>
                <div class="sheet-header-info-title">Novo agendamento</div>
                <div class="sheet-header-info-sub">
                    Cadastre rápido o horário do cliente e já deixe tudo pronto na agenda.
                </div>
            </div>
        </div>

        <form method="POST" id="formNovo" autocomplete="off">
            <input type="hidden" name="novo_agendamento" value="1">

            <!-- 1. Data e horário -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-clock-history"></i>
                    Data e horário
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Data</label>
                        <input
                            type="date"
                            name="data_agendamento"
                            value="<?php echo $viewType==='day' ? $dataExibida : $hoje; ?>"
                            class="form-input"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label">Horário</label>
                        <input type="time" name="horario" class="form-input" required>
                    </div>
                </div>
            </div>

            <!-- 2. Dados do cliente -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-person-circle"></i>
                    Cliente
                </div>
                <div class="form-group">
                    <label class="form-label">Nome do cliente</label>
                    <input
                        type="text"
                        name="cliente"
                        id="inputNome"
                        list="dlClientes"
                        class="form-input"
                        placeholder="Digite o nome ou escolha da lista"
                        autocomplete="off"
                        onchange="preencherTel()"
                    >
                    <datalist id="dlClientes">
                        <?php foreach($clientes as $c) echo "<option value='".htmlspecialchars($c['nome'])."'>"; ?>
                    </datalist>
                    <input type="hidden" name="cliente_id" id="clienteIdHidden">
                </div>

                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input
                        type="tel"
                        name="telefone"
                        id="inputTel"
                        class="form-input"
                        placeholder="(11) 99999-9999"
                    >
                </div>
            </div>

            <!-- 3. Serviços e valor -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-scissors"></i>
                    Serviços e valor
                </div>

                <div class="form-group">
                    <label class="form-label">Serviços</label>
                    <div class="services-box">
                        <?php foreach($servicos as $s): ?>
                            <div class="service-item">
                                <input
                                    type="checkbox"
                                    name="servicos_ids[]"
                                    value="<?php echo $s['id']; ?>"
                                    data-preco="<?php echo $s['preco']; ?>"
                                    onchange="calcTotal()"
                                >
                                <span>
                                    <?php echo htmlspecialchars($s['nome']) . ' (R$ ' . number_format($s['preco'],2,',','.'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Valor total</label>
                        <input
                            type="number"
                            name="valor"
                            id="inputValor"
                            step="0.01"
                            class="form-input"
                            placeholder="Será somado automaticamente pelos serviços"
                        >
                    </div>
                </div>
            </div>

            <!-- 4. Recorrência -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-arrow-repeat"></i>
                    Recorrência
                </div>

                <div class="form-group">
                    <label class="form-label" style="margin-bottom:0.35rem;">Repetir este agendamento?</label>
                    <div class="toggle-group">
                        <label class="toggle-pill">
                            <input
                                type="radio"
                                name="recorrencia_ativa"
                                value="0"
                                checked
                                onclick="toggleRec(false)"
                            >
                            <span>Não repetir</span>
                        </label>
                        <label class="toggle-pill">
                            <input
                                type="radio"
                                name="recorrencia_ativa"
                                value="1"
                                onclick="toggleRec(true)"
                            >
                            <span>Repetir</span>
                        </label>
                    </div>

                    <div id="divRec">
                        <div class="form-group">
                            <label class="form-label">Intervalo (dias)</label>
                            <input
                                type="number"
                                name="recorrencia_intervalo"
                                value="15"
                                class="form-input"
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label">Qtd. Sessões</label>
                            <input
                                type="number"
                                name="recorrencia_qtd"
                                value="4"
                                class="form-input"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Observações -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-journal-text"></i>
                    Observações
                </div>
                <div class="form-group">
                    <label class="form-label">Anotações importantes</label>
                    <textarea
                        name="obs"
                        class="form-input"
                        placeholder="Ex.: prefere água com gás, está em transição capilar, lembrar de oferecer tratamento X..."
                    ></textarea>
                </div>
            </div>

            <!-- Ações -->
        </form>
        <div class="modal-actions modal-actions-fixed">
            <button type="submit" form="formNovo" class="btn-main">
                <i class="bi bi-check2-circle" style="margin-right:4px;"></i>
                Salvar agendamento
            </button>
            <button type="button" class="btn-cancel" onclick="closeModal()">
                Cancelar
            </button>
        </div>
        <style>
        .modal-actions-fixed {
                position: sticky;
                bottom: -24px;
                left: 0;
                right: 0;
                background: #fff;
                z-index: 10;
                /* box-shadow: 0 -2px 16px rgba(15, 23, 42, 0.08); */
                padding-bottom: 1rem;
                padding-top: 0.5rem;
        }
        </style>
        </form>
    </div>
</div>

<!-- ACTION SHEET (3 PONTINHOS) -->
<div class="modal-overlay" id="actionSheet" style="align-items:flex-end;">
    <div class="sheet-modal" style="border-radius:22px 22px 0 0; padding-bottom:30px;">
        <div class="sheet-header">
            <div class="sheet-avatar" id="sheetAvatar">C</div>
            <div>
                <div class="sheet-header-info-title" id="sheetClientName">Cliente</div>
                <div class="sheet-header-info-sub" id="sheetClientInfo">Data • Horário • Valor</div>
            </div>
        </div>

        <div class="action-list">
            <a href="#" id="actConfirm" class="action-item">
                <i class="bi bi-check-circle" style="color:#10b981; font-size:1.1rem;"></i>
                <div>
                    <span class="action-text-main">Confirmar Presença</span>
                    <span class="action-text-sub">Marca o agendamento como confirmado</span>
                </div>
            </a>

            <a href="#" id="actWhatsapp" target="_blank" class="action-item">
                <i class="bi bi-whatsapp" style="color:#25D366; font-size:1.1rem;"></i>
                <div>
                    <span class="action-text-main">Chamar no WhatsApp</span>
                    <span class="action-text-sub">Enviar mensagem rápida para o cliente</span>
                </div>
            </a>

            <a href="#" id="actNota" class="action-item">
                <i class="bi bi-receipt" style="color:#6366f1; font-size:1.1rem;"></i>
                <div>
                    <span class="action-text-main">Emitir Nota</span>
                    <span class="action-text-sub">Gerar recibo / nota para este atendimento</span>
                </div>
            </a>

            <a href="#" id="actCancel" class="action-item">
                <i class="bi bi-x-circle" style="color:#f59e0b; font-size:1.1rem;"></i>
                <div>
                    <span class="action-text-main">Cancelar Agendamento</span>
                    <span class="action-text-sub">Não será excluído, apenas marcado como cancelado</span>
                </div>
            </a>

            <a href="#" id="actDelete" class="action-item danger">
                <i class="bi bi-trash" style="font-size:1.1rem;"></i>
                <div>
                    <span class="action-text-main">Excluir</span>
                    <span class="action-text-sub">Remover este agendamento da agenda</span>
                </div>
            </a>
        </div>

        <button onclick="fecharActionSheet()" class="btn-cancel" style="margin-top:1rem;">Fechar</button>
    </div>
</div>

<!-- MODAL EXCLUIR RECORRÊNCIA -->
<div class="modal-overlay" id="deleteRecorrenteModal" style="align-items:center;">
    <div class="sheet-modal" style="border-radius:16px; max-width:400px;">
        <h3 style="text-align:center;">Excluir Recorrência</h3>
        <p style="text-align:center; font-size:0.9rem; color:#64748b;">Este é um agendamento recorrente.</p>
        <div style="display:flex; flex-direction:column; gap:10px; margin-top:1rem;">
            <button class="btn-main" onclick="confirmarDelete('unico')" style="background:#f1f5f9; color:#0f172a;">Apenas este</button>
            <button class="btn-main" onclick="confirmarDelete('proximos')" style="background:#f1f5f9; color:#0f172a;">Este e próximos</button>
            <button class="btn-main" onclick="confirmarDelete('serie')" style="background:#ef4444; color:white;">Toda a série</button>
        </div>
        <button onclick="document.getElementById('deleteRecorrenteModal').classList.remove('active')" class="btn-cancel">Cancelar</button>
    </div>
</div>

<?php
// === FUNÇÃO RENDER CARD (3 PONTINHOS + JSON) ===
function renderCard($ag, $clientes) {
    $stClass = 'st-' . ($ag['status'] ?? 'Pendente');
    $tel = '';
    foreach ($clientes as $c) {
        if ($c['nome'] === $ag['cliente_nome']) {
            $tel = $c['telefone'];
            break;
        }
    }

    $dados = [
        'id' => $ag['id'],
        'cliente' => $ag['cliente_nome'],
        'status' => $ag['status'],
        'tel' => $tel,
        'serv' => $ag['servico'],
        'val' => number_format($ag['valor'], 2, ',', '.'),
        'data' => date('d/m', strtotime($ag['data_agendamento'])),
        'hora' => date('H:i', strtotime($ag['horario'])),
        'e_recorrente' => !empty($ag['e_recorrente']),
        'serie_id' => $ag['serie_id'] ?? null
    ];
    $jsonInfo = htmlspecialchars(json_encode($dados, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

    $badgeRec = !empty($ag['e_recorrente'])
        ? "<span style='font-size:0.7rem; background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:4px; margin-left:4px;'>Recorrente</span>"
        : "";

    echo "
    <div class='appt-card' data-status='".htmlspecialchars($ag['status'])."'>
        <div class='status-badge {$stClass}'></div>
        <div class='time-col'>
            <span class='time-val'>".date('H', strtotime($ag['horario']))."</span>
            <span class='time-min'>".date('i', strtotime($ag['horario']))."</span>
        </div>
        <div class='info-col'>
            <div class='client-name'>".htmlspecialchars($ag['cliente_nome'])." {$badgeRec}</div>
            <div class='service-row'>".htmlspecialchars($ag['servico'])." <span class='price-tag'>R$ {$dados['val']}</span></div>
        </div>
        <button type='button' onclick='openActions(this)' data-info='{$jsonInfo}'>
            <i class=\"bi bi-three-dots-vertical\"></i>
        </button>
    </div>";
}
?>

<script>
    // MODAL NOVO
    function openModal(){ document.getElementById('modalAdd').classList.add('active'); }
    function closeModal(){ document.getElementById('modalAdd').classList.remove('active'); }

    function abrirModalComHorario(data,hora){
        openModal();
        document.querySelector('input[name="data_agendamento"]').value = data;
        document.querySelector('input[name="horario"]').value = hora;
    }

    // Clientes -> telefone
    var clientesDB = <?php echo json_encode($clientes); ?>;
    function preencherTel(){
        let nome = document.getElementById('inputNome').value;
        let c = clientesDB.find(x => x.nome === nome);
        const telInput = document.getElementById('inputTel');
        const hiddenId = document.getElementById('clienteIdHidden');
        if (c) {
            telInput.value = c.telefone || '';
            hiddenId.value = c.id || '';
        } else {
            hiddenId.value = '';
        }
    }

    // Soma serviços
    let valorEditadoManualmente = false;
    document.addEventListener('DOMContentLoaded', function() {
        const inputValor = document.getElementById('inputValor');
        if (inputValor) {
            inputValor.addEventListener('input', function() {
                valorEditadoManualmente = true;
            });
        }
    });
    function calcTotal(){
        let checks = document.querySelectorAll('input[name="servicos_ids[]"]:checked');
        let tot = 0;
        checks.forEach(c => tot += parseFloat(c.dataset.preco));
        if(!valorEditadoManualmente){
            document.getElementById('inputValor').value = tot.toFixed(2);
        }
    }

    function toggleRec(active){
        document.getElementById('divRec').style.display = active ? 'grid' : 'none';
    }

    // ACTION SHEET
    let currentAgId = null;
    let currentIsRec = false;

    function openActions(btn){
        const infoStr = btn.getAttribute('data-info');
        if(!infoStr) return;
        let data = null;
        try {
            data = JSON.parse(infoStr);
        } catch(e){
            console.error('Erro ao ler dados do agendamento', e);
            return;
        }

        currentAgId = data.id;
        currentIsRec = !!data.e_recorrente;

        // Avatar com iniciais
        const avatarEl = document.getElementById('sheetAvatar');
        const nomeCli = (data.cliente || 'C').trim();
        const partes = nomeCli.split(' ');
        let iniciais = partes[0]?.charAt(0) || 'C';
        if(partes.length > 1){
            iniciais += partes[partes.length-1].charAt(0);
        }
        avatarEl.textContent = iniciais.toUpperCase();

        document.getElementById('sheetClientName').innerText = data.cliente || 'Cliente';
        document.getElementById('sheetClientInfo').innerText = `${data.data} às ${data.hora} • R$ ${data.val}`;

        // monta URL base limpando parâmetros velhos
        let basePath = window.location.pathname;
        let qs = window.location.search
            .replace(/&?id=\d+/,'')
            .replace(/&?status=\w+/,'')
            .replace(/&?delete=\d+/,'')
            .replace(/&?tipo_exclusao=\w+/,'');
        let urlBase = basePath + qs;
        let sep = urlBase.includes('?') ? '&' : '?';
        let finalUrl = urlBase + sep + 'id=' + data.id;

        // ações de status
        document.getElementById('actConfirm').href = finalUrl + '&status=Confirmado';
        document.getElementById('actCancel').href  = finalUrl + '&status=Cancelado';
        document.getElementById('actNota').href    = (window.location.hostname==='salao.develoi.com'?'/nota':'nota.php') + '?id=' + data.id;

        // WhatsApp
        let whatsBtn = document.getElementById('actWhatsapp');
        if(data.tel){
            let tel = String(data.tel).replace(/\D/g,'');
            let msg = `Olá ${data.cliente.split(' ')[0]}, confirmo seu horário dia ${data.data} às ${data.hora}?`;
            let link = (window.innerWidth < 768) ? 'api.whatsapp.com' : 'web.whatsapp.com';
            whatsBtn.href = `https://${link}/send?phone=55${tel}&text=${encodeURIComponent(msg)}`;
            whatsBtn.style.display = 'flex';
        } else {
            whatsBtn.style.display = 'none';
        }

        // delete
        document.getElementById('actDelete').onclick = function(e){
            e.preventDefault();
            fecharActionSheet();
            if(currentIsRec){
                document.getElementById('deleteRecorrenteModal').classList.add('active');
            } else {
                if(confirm('Excluir agendamento?')){
                    window.location.href = finalUrl + '&delete=' + data.id;
                }
            }
        };

        const sheet = document.getElementById('actionSheet');
        sheet.classList.add('active');
        sheet.style.display = 'flex';
    }

    function fecharActionSheet(){
        const sheet = document.getElementById('actionSheet');
        sheet.classList.remove('active');
        setTimeout(()=> sheet.style.display='none', 200);
    }

    function confirmarDelete(tipo){
        let basePath = window.location.pathname;
        let qs = window.location.search
            .replace(/&?delete=\d+/,'')
            .replace(/&?tipo_exclusao=\w+/,'')
            .replace(/&?id=\d+/,'');
        let urlBase = basePath + qs;
        let sep = urlBase.includes('?') ? '&' : '?';
        window.location.href = urlBase + sep + 'delete=' + currentAgId + '&tipo_exclusao=' + tipo;
    }

    // Fechar modais clicando fora
    document.addEventListener('click', function(e){
        if(e.target.classList.contains('modal-overlay')){
            const id = e.target.id;
            if(id === 'modalAdd'){ closeModal(); }
            if(id === 'actionSheet'){ fecharActionSheet(); }
            if(id === 'deleteRecorrenteModal'){ e.target.classList.remove('active'); }
        }
    });

    // Link Helper
    function copiarLink(e){
        let input = document.getElementById('linkAgendamento');
        input.select();
        document.execCommand('copy');
        alert('Link copiado!');
    }
    function compartilharLink(){
        let url = document.getElementById('linkAgendamento').value;
        if(navigator.share) navigator.share({title:'Agendamento', url:url});
        else window.open('https://wa.me/?text='+encodeURIComponent(url));
    }
</script>
