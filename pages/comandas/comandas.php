<?php
// pages/comandas/comandas.php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
$uid = (int)$_SESSION['user_id'];

function normalizeWeekdays($days) {
    $days = array_values(array_unique(array_map('intval', (array)$days)));
    sort($days);
    return $days;
}

function nextDateByWeekdays(DateTime $date, array $days, $inclusive = false) {
    if (empty($days)) return $date;
    $days = normalizeWeekdays($days);
    $dow = (int)$date->format('w');
    $target = null;
    foreach ($days as $d) {
        if ($inclusive ? $d >= $dow : $d > $dow) {
            $target = $d;
            break;
        }
    }
    if ($target === null) $target = $days[0];
    $diff = $target - $dow;
    if ($inclusive) {
        if ($diff < 0) $diff += 7;
    } else {
        if ($diff <= 0) $diff += 7;
    }
    $date->modify("+{$diff} days");
    return $date;
}

function addMonthSameDay(DateTime $date, $day) {
    $date->modify('first day of next month');
    $daysInMonth = (int)$date->format('t');
    $day = min((int)$day, $daysInMonth);
    $date->setDate((int)$date->format('Y'), (int)$date->format('m'), $day);
    return $date;
}

function getNthWeekdayInMonth($year, $month, $weekday, $nth) {
    $first = new DateTime();
    $first->setDate((int)$year, (int)$month, 1);
    $first->setTime(0, 0, 0);

    $firstDow = (int)$first->format('w');
    $offset = $weekday - $firstDow;
    if ($offset < 0) $offset += 7;

    $day = 1 + $offset + 7 * ((int)$nth - 1);
    $daysInMonth = (int)$first->format('t');

    if ($day > $daysInMonth) {
        $last = new DateTime();
        $last->setDate((int)$year, (int)$month, $daysInMonth);
        $last->setTime(0, 0, 0);
        $lastDow = (int)$last->format('w');
        $diff = $lastDow - $weekday;
        if ($diff < 0) $diff += 7;
        $day = $daysInMonth - $diff;
    }

    $first->setDate((int)$year, (int)$month, (int)$day);
    return $first;
}

// =========================================================
// LÓGICA PHP
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'salvar') {
            $pdo->beginTransaction();

            $cliente_id = (int)$_POST['cliente_id'];

            // Pega o ID do serviço OU do pacote selecionado
            $servico_id = !empty($_POST['servico_id'])
                ? (int)$_POST['servico_id']
                : (!empty($_POST['pacote_id']) ? (int)$_POST['pacote_id'] : null);

            $titulo     = trim($_POST['titulo'] ?: 'Serviço Avulso');
            $tipo       = $_POST['tipo'] ?? 'normal'; // normal ou pacote

            // Flag: usar agendamentos do cliente para montar as sessões
            $usarAgenda = !empty($_POST['usar_agenda']);

            // Quantidade informada no formulário (padrão)
            $qtdForm    = max(1, (int)$_POST['qtd_total']);
            $qtd        = $qtdForm;

            $valor_tot  = (float)$_POST['valor_final'];
            $dt_inicio  = $_POST['data_inicio'];
            $frequencia = $_POST['frequencia'] ?? 'diaria';
            $diasSemana = isset($_POST['dias_semana']) ? $_POST['dias_semana'] : [];
            $intervaloPersonalizado = isset($_POST['intervalo_personalizado'])
                ? max(1, (int)$_POST['intervalo_personalizado'])
                : 1;

            $agendamentos = [];
            $diasSemana = normalizeWeekdays($diasSemana);

            if ($usarAgenda) {
                // Busca agendamentos futuros (mesmo critério da API)
                $stmtAg = $pdo->prepare("
                    SELECT id, data_agendamento as data, horario as hora
                    FROM agendamentos
                    WHERE user_id    = ?
                      AND cliente_id = ?
                      AND status IN ('Pendente','Confirmado')
                    ORDER BY data_agendamento ASC, horario ASC
                ");
                $stmtAg->execute([$uid, $cliente_id]);
                $agendamentos = $stmtAg->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($agendamentos)) {
                    // Força a quantidade de sessões = total de agendamentos
                    $qtd = count($agendamentos);
                }
            }

            // INSERE A COMANDA
            $stmt = $pdo->prepare("
                INSERT INTO comandas
                    (user_id, cliente_id, servico_id, titulo, tipo, status, valor_total, qtd_total, data_inicio, frequencia)
                VALUES
                    (?, ?, ?, ?, ?, 'aberta', ?, ?, ?, ?)
            ");
            $stmt->execute([$uid, $cliente_id, $servico_id, $titulo, $tipo, $valor_tot, $qtd, $dt_inicio, $frequencia]);
            $comanda_id = $pdo->lastInsertId();

            // Calcula valor por sessão
            $valor_sessao = $qtd > 0 ? $valor_tot / $qtd : 0;

            // Decide COMO criar os itens
            if ($usarAgenda && !empty($agendamentos)) {
                // MONTA ITENS USANDO AS DATAS DA AGENDA
                $numero = 1;
                $stmtItem = $pdo->prepare("
                    INSERT INTO comanda_itens (comanda_id, numero, data_prevista, valor_sessao, status)
                    VALUES (?, ?, ?, ?, 'pendente')
                ");

                foreach ($agendamentos as $ag) {
                    $dt_sql = $ag['data']; // já está em Y-m-d no banco
                    $stmtItem->execute([$comanda_id, $numero, $dt_sql, $valor_sessao]);
                    $numero++;
                }

            } else {
                // Gera itens por recorrencia simples quando nao usa a agenda
                $data_inicio = new DateTime($dt_inicio);
                $data_inicio->setTime(0, 0, 0);
                $data_atual = clone $data_inicio;

                $diaMesBase = (int)$data_inicio->format('d');
                $weekdayBase = (int)$data_inicio->format('w');
                $weekIndexBase = (int)floor(($diaMesBase - 1) / 7) + 1;
                $weekdayRef = !empty($diasSemana) ? (int)$diasSemana[0] : $weekdayBase;

                if (($frequencia === 'semanal' || $frequencia === 'mensal_semana') && !empty($diasSemana)) {
                    $dow = (int)$data_atual->format('w');
                    if (!in_array($dow, $diasSemana, true)) {
                        $data_atual = nextDateByWeekdays($data_atual, $diasSemana, true);
                    }
                }

                $stmtItem = $pdo->prepare("
                    INSERT INTO comanda_itens (comanda_id, numero, data_prevista, valor_sessao, status)
                    VALUES (?, ?, ?, ?, 'pendente')
                ");

                for ($i = 1; $i <= $qtd; $i++) {
                    $dt_sql = $data_atual->format('Y-m-d');
                    $stmtItem->execute([$comanda_id, $i, $dt_sql, $valor_sessao]);

                    if ($frequencia === 'unico') {
                        continue;
                    }

                    if ($frequencia === 'diaria') {
                        $data_atual->modify('+1 day');
                    } elseif ($frequencia === 'semanal') {
                        if (!empty($diasSemana)) {
                            $data_atual = nextDateByWeekdays($data_atual, $diasSemana, false);
                        } else {
                            $data_atual->modify('+7 days');
                        }
                    } elseif ($frequencia === 'quinzenal') {
                        $data_atual->modify('+15 days');
                    } elseif ($frequencia === 'mensal_dia') {
                        $data_atual = addMonthSameDay($data_atual, $diaMesBase);
                    } elseif ($frequencia === 'mensal_semana') {
                        $data_atual->modify('first day of next month');
                        $y = (int)$data_atual->format('Y');
                        $m = (int)$data_atual->format('m');
                        $data_atual = getNthWeekdayInMonth($y, $m, $weekdayRef, $weekIndexBase);
                    } elseif ($frequencia === 'personalizada') {
                        $data_atual->modify("+{$intervaloPersonalizado} days");
                    } else {
                        $data_atual->modify('+1 day');
                    }
                }
            }

            $pdo->commit();
            header("Location: ?msg=criado");
            exit;
        }

        if ($acao === 'confirmar_sessao') {
            $comandaId     = (int)$_POST['comanda_id'];
            $dataRealizada = $_POST['data_realizada'];

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT i.id 
                FROM comanda_itens i 
                JOIN comandas c ON i.comanda_id = c.id 
                WHERE i.comanda_id = ? 
                  AND c.user_id = ? 
                  AND i.status = 'pendente' 
                ORDER BY i.data_prevista ASC, i.numero ASC 
                LIMIT 1
            ");
            $stmt->execute([$comandaId, $uid]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $pdo->prepare("
                    UPDATE comanda_itens 
                    SET status = 'realizado', data_realizada = ? 
                    WHERE id = ?
                ")->execute([$dataRealizada, $item['id']]);

                $pendentes = $pdo->query("
                    SELECT COUNT(*) 
                    FROM comanda_itens 
                    WHERE comanda_id = $comandaId 
                      AND status = 'pendente'
                ")->fetchColumn();

                if ($pendentes == 0) {
                    $pdo->prepare("UPDATE comandas SET status = 'fechada' WHERE id = ?")
                        ->execute([$comandaId]);
                }
            }
            $pdo->commit();
            header("Location: ?msg=sessao_ok");
            exit;
        }

        if ($acao === 'editar') {
            $pdo->beginTransaction();
            
            $id          = (int)$_POST['id'];
            $titulo      = trim($_POST['edit_titulo']);
            $qtd_total   = max(1, (int)$_POST['edit_qtd_total']);
            $valor_total = (float)$_POST['edit_valor_total'];
            $data_inicio = $_POST['edit_data_inicio'];
            $frequencia  = $_POST['edit_frequencia'];
            
            // Atualizar comanda
            $pdo->prepare("UPDATE comandas SET titulo = ?, qtd_total = ?, valor_total = ?, data_inicio = ?, frequencia = ? WHERE id = ? AND user_id = ?")
                ->execute([$titulo, $qtd_total, $valor_total, $data_inicio, $frequencia, $id, $uid]);
            
            // Verificar se mudou a quantidade de sessões
            $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM comanda_itens WHERE comanda_id = ?");
            $stmtCount->execute([$id]);
            $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
            $qtdAtual = $countResult['total'];
            
            if ($qtd_total != $qtdAtual) {
                // Deletar itens existentes
                $pdo->prepare("DELETE FROM comanda_itens WHERE comanda_id = ?")->execute([$id]);
                
                // Recriar itens com nova quantidade
                $valor_sessao = $qtd_total > 0 ? $valor_total / $qtd_total : 0;
                $data_atual = new DateTime($data_inicio);
                
                for ($i = 1; $i <= $qtd_total; $i++) {
                    $pdo->prepare("
                        INSERT INTO comanda_itens (comanda_id, numero, data_prevista, valor, status)
                        VALUES (?, ?, ?, ?, 'pendente')
                    ")->execute([$id, $i, $data_atual->format('Y-m-d'), $valor_sessao]);
                    
                    // Avançar data conforme frequência
                    if ($i < $qtd_total) {
                        if ($frequencia === 'semanal') {
                            $data_atual->modify('+7 days');
                        } elseif ($frequencia === 'quinzenal') {
                            $data_atual->modify('+15 days');
                        } elseif ($frequencia === 'mensal_dia') {
                            $data_atual->modify('+1 month');
                        } else {
                            $data_atual->modify('+1 day');
                        }
                    }
                }
            }
            
            $pdo->commit();
            header("Location: ?msg=editado");
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Erro: " . $e->getMessage());
    }
}

if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $pdo->prepare("DELETE FROM comanda_itens WHERE comanda_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM comandas WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
    header("Location: ?msg=deletado");
    exit;
}

$filtro_status = $_GET['tab'] ?? 'aberta';
$busca         = trim($_GET['q'] ?? '');

$sql = "SELECT c.*, cli.nome AS c_nome, s.nome AS servico_nome,
        (SELECT COUNT(*) FROM comanda_itens WHERE comanda_id = c.id AND status = 'realizado') AS feitos,
        (SELECT data_prevista FROM comanda_itens WHERE comanda_id = c.id AND status = 'pendente' ORDER BY data_prevista ASC LIMIT 1) AS proxima
        FROM comandas c
        JOIN clientes cli ON c.cliente_id = cli.id
        LEFT JOIN servicos s ON c.servico_id = s.id
        WHERE c.user_id = :uid AND c.status = :status";

if ($busca) $sql .= " AND (cli.nome LIKE :busca OR c.titulo LIKE :busca)";
$sql .= " ORDER BY c.data_inicio DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uid', $uid);
$stmt->bindValue(':status', $filtro_status);
if ($busca) $stmt->bindValue(':busca', "%$busca%");
$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- SEPARAÇÃO DE DADOS PARA OS SELECTS ---
$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE user_id = $uid ORDER BY nome")
    ->fetchAll(PDO::FETCH_ASSOC);

// Serviços avulsos
$servicosUnicos = $pdo->query("
    SELECT id, nome, preco, tipo, qtd_sessoes 
    FROM servicos 
    WHERE user_id = $uid AND tipo = 'unico' 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

// Pacotes cadastrados
$pacotesCadastrados = $pdo->query("
    SELECT id, nome, preco, tipo, qtd_sessoes 
    FROM servicos 
    WHERE user_id = $uid AND tipo = 'pacote' 
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    :root {
        --primary: #0f2f66;
        --primary-dark: #1e3a8a;
        --primary-light: #dbeafe;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --success: #16a34a;
        --success-bg: #dcfce7;
        --danger: #ef4444;
        --danger-bg: #fee2e2;

        --radius-card: 20px;
        --radius-btn: 999px;
        --shadow-sm: 0 4px 10px rgba(15,23,42,0.06);
        --shadow-lg: 0 18px 45px -12px rgba(15,23,42,0.18);
    }

    body {
        font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        background: var(--bg-body);
        color: var(--text-main);
        font-size: 0.8rem;
        margin: 0;
    }

    .text-muted {
        color: var(--text-muted);
    }

    .app-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 16px 12px 80px;
    }

    /* HEADER */
    .app-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 16px;
        gap: 12px;
    }

    .page-title h1 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
        letter-spacing: -0.04em;
    }

    .page-title p {
        color: var(--text-muted);
        font-size: 0.78rem;
        margin-top: 4px;
    }

    /* TABS */
    .tabs-pill {
        display: inline-flex;
        background: #ffffff;
        padding: 3px;
        border-radius: var(--radius-btn);
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(148,163,184,0.25);
    }

    .tab-link {
        padding: 7px 18px;
        border-radius: var(--radius-btn);
        text-decoration: none;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.74rem;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .tab-link.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        box-shadow: 0 4px 12px rgba(15,47,102,0.28);
    }

    /* CONTROLS */
    .controls-row {
        display: flex;
        gap: 8px;
        margin-bottom: 14px;
        align-items: center;
    }

    .search-wrapper {
        flex: 1;
        position: relative;
    }

    .search-wrapper i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .search-input {
        width: 100%;
        padding: 9px 14px 9px 32px;
        border-radius: 999px;
        border: 1px solid rgba(148,163,184,0.7);
        font-size: 0.8rem;
        outline: none;
        transition: 0.2s;
        background: #ffffff;
        box-sizing: border-box;
    }

    .search-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(15,47,102,0.18);
        background: #fff;
    }

    .btn-gradient {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        padding: 0 16px;
        border-radius: 999px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        height: 36px;
        box-shadow: 0 8px 20px rgba(15,47,102,0.28);
        transition: transform 0.15s, box-shadow 0.15s;
        white-space: nowrap;
    }

    .btn-gradient i {
        font-size: 0.78rem;
    }

    .btn-gradient:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(15,47,102,0.35);
    }

    .btn-gradient:active {
        transform: translateY(0);
        box-shadow: 0 5px 14px rgba(15,47,102,0.22);
    }

    /* LIST / TABLE WRAPPERS */
    .desktop-table {
        display: none;
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid rgba(226,232,240,0.9);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 10px 16px;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 700;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        letter-spacing: 0.06em;
    }

    td {
        padding: 10px 16px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
        color: var(--text-main);
        font-weight: 500;
        font-size: 0.78rem;
    }

    tbody tr:hover td {
        background: #f8fafc;
    }

    tr:last-child td {
        border-bottom: none;
    }

    .badge-pill {
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .badge-blue {
        background: #e0f2fe;
        color: #1e3a8a;
    }

    .badge-next {
        background: #eef2ff;
        color: #1e3a8a;
        border: 1px solid #dbeafe;
        font-weight: 700;
    }

    .badge-orange {
        background: #fff7ed;
        color: #c2410c;
    }

    /* CARDS (MOBILE) */
    .cards-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .card {
        background: #ffffff;
        border-radius: 18px;
        padding: 14px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(226,232,240,0.9);
        position: relative;
        overflow: hidden;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .card:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(15,23,42,0.12);
    }

    .card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(37,99,235,0.12), transparent 55%);
        pointer-events: none;
    }

    .card-top {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        align-items: flex-start;
    }

    .card-title {
        font-weight: 600;
        color: var(--text-main);
        font-size: 0.9rem;
        margin-bottom: 2px;
    }

    .card-top .card-sub {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .card-top .top-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
    }

    .next-pill {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.64rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        background: #eef2ff;
        color: #1e3a8a;
        border: 1px solid #dbeafe;
    }

    /* PROGRESSO */
    .prog-bar {
        height: 5px;
        background: #edf2f7;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 4px;
    }

    .prog-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), #2563eb);
        border-radius: 999px;
        width: 0%;
        transition: width 0.8s ease;
    }

    .prog-fill.finished {
        background: var(--success);
    }

    /* ACTIONS */
    .btn-icon {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        border: 1px solid rgba(226,232,240,0.9);
        background: #fff;
        color: var(--text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.15s;
        font-size: 0.78rem;
    }

    .btn-icon:hover {
        color: var(--primary);
        border-color: var(--primary-light);
        background: #f8fafc;
        transform: translateY(-1px);
    }

    .btn-icon.del:hover {
        color: var(--danger);
        border-color: #fecaca;
        background: #fef2f2;
    }

    /* MODAL */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(6px);
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        transition: 0.28s ease;
        display: flex;
        align-items: flex-end;
        justify-content: center;
    }

    .modal-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }

    .modal-box {
        background: #f8fafc;
        width: 100%;
        max-width: 430px;
        border-radius: 22px 22px 0 0;
        padding: 18px 18px 20px;
        transform: translateY(100%);
        transition: transform 0.25s cubic-bezier(0.18, 0.89, 0.32, 1.08);
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 -12px 40px rgba(15,23,42,0.35);
        box-sizing: border-box;
        border: 1px solid rgba(148,163,184,0.25);
        position: relative;
    }

    .modal-overlay.open .modal-box {
        transform: translateY(0);
    }

    .modal-box::before {
        content: "";
        position: absolute;
        inset: 0 0 auto 0;
        height: 64px;
        border-radius: 22px 22px 0 0;
        background: linear-gradient(135deg, rgba(15,47,102,0.14), rgba(37,99,235,0.08));
        pointer-events: none;
    }

    .modal-box > * {
        position: relative;
        z-index: 1;
    }

    .modal-box h3 {
        color: var(--text-main);
        font-weight: 700;
    }

    @media (min-width: 768px) {
        .modal-overlay {
            align-items: center;
        }

        .modal-box {
            border-radius: 22px;
            max-width: 420px;
            transform: translateY(20px);
        }

        .desktop-table {
            display: block;
        }

        .cards-list {
            display: none;
        }

        .app-container {
            padding: 18px 16px 90px;
        }
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-label {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .form-input {
        width: 100%;
        padding: 9px 11px;
        border: 1px solid rgba(226,232,240,0.95);
        border-radius: 14px;
        font-size: 0.78rem;
        background: #f9fafb;
        outline: none;
        box-sizing: border-box;
        font-family: inherit;
        transition: 0.18s;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(15,47,102,0.18);
    }

    .switch-wrap {
        background: #f1f5f9;
        padding: 4px;
        border-radius: 16px;
        display: flex;
        margin-bottom: 14px;
    }

    .switch-opt {
        flex: 1;
        text-align: center;
        padding: 7px 4px;
        border-radius: 12px;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        transition: 0.18s;
    }

    .switch-opt.active {
        background: #fff;
        color: var(--primary);
        box-shadow: 0 3px 10px rgba(15,23,42,0.08);
    }

    .weekday-options {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .weekday-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
    }

    .weekday-chip input {
        display: none;
    }

    .weekday-chip span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.15s ease;
    }

    .weekday-chip input:checked + span {
        background: #dbeafe;
        color: #1e3a8a;
        border-color: #bfdbfe;
        box-shadow: 0 6px 14px rgba(15,47,102,0.15);
    }

    .btn-submit {
        width: 100%;
        padding: 11px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        margin-top: 6px;
        box-shadow: 0 10px 26px rgba(15,47,102,0.35);
        transition: 0.18s;
    }

    .btn-submit:active {
        transform: translateY(1px);
        box-shadow: 0 6px 16px rgba(15,47,102,0.28);
    }

    .btn-cancel-modal {
        width: 100%;
        padding: 10px;
        background: #e2e8f0;
        color: var(--text-main);
        border: none;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        margin-top: 8px;
    }

    .btn-cancel-modal:hover {
        background: #dbeafe;
    }

    /* TOAST */
    .toast-box {
        position: fixed;
        top: 14px;
        left: 50%;
        transform: translateX(-50%) translateY(-80px);
        background: #0f172a;
        color: #fff;
        padding: 8px 16px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 14px 40px rgba(15,23,42,0.55);
        z-index: 2000;
        font-weight: 500;
        font-size: 0.78rem;
        opacity: 0;
        transition: 0.35s ease;
        pointer-events: none;
        white-space: nowrap;
    }

    .toast-box.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }

    /* EMPTY STATE */
    .empty-box {
        text-align: center;
        padding: 56px 18px;
        color: var(--text-muted);
        font-size: 0.82rem;
    }

    .empty-box i {
        font-size: 3rem;
        opacity: 0.16;
        display: block;
        margin-bottom: 12px;
    }

    @media (min-width: 992px) {
        .app-header {
            margin-bottom: 20px;
        }
    }
</style>

<div id="toast" class="toast-box">
    <i class="bi bi-check-circle-fill" style="color: #4ade80;"></i>
    <span id="toastMsg">Sucesso!</span>
</div>

<div class="app-container">

    <div class="app-header">
        <div class="page-title">
            <h1>Comandas</h1>
            <p>Controle prático de pacotes e sessões</p>
        </div>
        <div class="tabs-pill">
            <a href="?tab=aberta" class="tab-link <?= $filtro_status=='aberta'?'active':'' ?>">
                <i class="bi bi-circle-half"></i> Abertas
            </a>
            <a href="?tab=fechada" class="tab-link <?= $filtro_status=='fechada'?'active':'' ?>">
                <i class="bi bi-check-circle"></i> Concluídas
            </a>
        </div>
    </div>

    <div class="controls-row">
        <form style="flex:1; display:flex;" method="GET">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($filtro_status) ?>">
            <div class="search-wrapper">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por cliente ou título..." class="search-input">
            </div>
        </form>
        <button type="button" class="btn-gradient" onclick="abrirModal('modalNova')">
            <i class="bi bi-plus-lg"></i> Novo
        </button>
    </div>

    <?php if(count($lista) == 0): ?>
        <div class="empty-box">
            <i class="bi bi-inbox"></i>
            <p>Nenhuma comanda encontrada nesta aba.</p>
        </div>
    <?php endif; ?>

    <!-- DESKTOP TABLE -->
    <div class="desktop-table">
        <table>
            <thead>
                <tr>
                    <th>Pacote</th>
                    <th>Cliente</th>
                    <th>Progresso</th>
                    <th>Próxima</th>
                    <th>Valor</th>
                    <th style="text-align:right">Opções</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lista as $c):
                    $percent = ($c['qtd_total'] > 0) ? ($c['feitos'] / $c['qtd_total']) * 100 : 0;
                    $percent = min(100, max(0, $percent));
                    $dataJson = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600; color:var(--text-main); font-size:0.8rem;"><?= htmlspecialchars($c['titulo']) ?></div>
                        <div style="display:flex; gap:6px; align-items:center; margin-top:4px; flex-wrap:wrap;">
                            <span class="badge-pill badge-<?= $c['tipo']=='pacote'?'orange':'blue' ?>"><?= ucfirst($c['tipo']) ?></span>
                            <span class="badge-pill badge-next">
                                Próxima: <?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '--' ?>
                            </span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($c['c_nome']) ?></td>
                    <td style="width:180px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:var(--text-muted); margin-bottom:3px;">
                            <span><?= $c['feitos'] ?> de <?= $c['qtd_total'] ?></span>
                            <span><?= round($percent) ?>%</span>
                        </div>
                        <div class="prog-bar">
                            <div class="prog-fill <?= $percent>=100?'finished':'' ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <?= $c['proxima'] ? date('d/m/y', strtotime($c['proxima'])) : '<span class="text-muted">--</span>' ?>
                    </td>
                    <td style="font-weight:700; color:var(--success);">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                    <td>
                        <div style="display:flex; justify-content:flex-end; gap:6px;">
                            <?php if($c['status'] == 'aberta'): ?>
                                <button
                                    onclick="abrirConfirmacao(<?= $c['id'] ?>, '<?= htmlspecialchars($c['c_nome'], ENT_QUOTES) ?>', <?= $c['feitos'] ?>, <?= $c['qtd_total'] ?>)"
                                    class="btn-icon"
                                    style="color:var(--success); border-color:var(--success-bg); background:var(--success-bg);"
                                    title="Confirmar próxima sessão">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            <?php endif; ?>
                            <button onclick='editarComanda(<?= $dataJson ?>)' class="btn-icon" title="Editar título">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button onclick="excluirComanda(<?= $c['id'] ?>)" class="btn-icon del" title="Excluir comanda">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="cards-list">
        <?php foreach($lista as $c):
            $percent = ($c['qtd_total'] > 0) ? ($c['feitos'] / $c['qtd_total']) * 100 : 0;
            $percent = min(100, max(0, $percent));
            $dataJson = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="card">
            <div class="card-top">
                <div>
                    <div class="card-title"><?= htmlspecialchars($c['titulo']) ?></div>
                    <div class="card-sub"><?= htmlspecialchars($c['c_nome']) ?></div>
                </div>
                <div class="top-right">
                    <span class="badge-pill badge-<?= $c['tipo']=='pacote'?'orange':'blue' ?>"><?= ucfirst($c['tipo']) ?></span>
                    <span class="next-pill">
                        Próxima: <?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '--' ?>
                    </span>
                </div>
            </div>

            <div style="margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; font-size:0.7rem; color:var(--text-muted); margin-bottom:3px;">
                    <span>Progresso</span>
                    <span><?= $c['feitos'] ?>/<?= $c['qtd_total'] ?></span>
                </div>
                <div class="prog-bar">
                    <div class="prog-fill <?= $percent>=100?'finished':'' ?>" style="width: <?= $percent ?>%"></div>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-bottom:8px;">
                <div style="flex:1; background:#f8fafc; padding:8px; border-radius:14px;">
                    <div style="font-size:0.68rem; color:var(--text-muted); margin-bottom:2px;">Próxima sessão</div>
                    <div style="font-weight:600; font-size:0.8rem;">
                        <?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '--' ?>
                    </div>
                </div>
                <div style="flex:1; background:#f8fafc; padding:8px; border-radius:14px;">
                    <div style="font-size:0.68rem; color:var(--text-muted); margin-bottom:2px;">Valor total</div>
                    <div style="font-weight:600; color:var(--success); font-size:0.8rem;">
                        R$ <?= number_format($c['valor_total'], 2, ',', '.') ?>
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr auto auto; gap:6px; margin-top:6px;">
                <?php if($c['status'] == 'aberta'): ?>
                    <button
                        class="btn-gradient"
                        style="width:100%; justify-content:center; box-shadow:none; font-size:0.8rem; height:32px;"
                        onclick="abrirConfirmacao(<?= $c['id'] ?>, '<?= htmlspecialchars($c['c_nome'], ENT_QUOTES) ?>', <?= $c['feitos'] ?>, <?= $c['qtd_total'] ?>)">
                        Confirmar sessão
                    </button>
                <?php else: ?>
                    <button
                        style="width:100%; border:none; background:#ecfdf5; color:var(--success); border-radius:999px; font-weight:600; font-size:0.78rem; height:32px;">
                        Concluído
                    </button>
                <?php endif; ?>

                <button class="btn-icon" onclick='editarComanda(<?= $dataJson ?>)' title="Editar">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn-icon del" onclick="excluirComanda(<?= $c['id'] ?>)" title="Excluir">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- MODAL NOVA COMANDA -->
<div id="modalNova" class="modal-overlay">
    <div class="modal-box">
        <div style="width:40px; height:4px; background:#e2e8f0; border-radius:999px; margin:0 auto 14px;"></div>
        <h3 style="font-size:1rem; font-weight:700; margin:0 0 14px; text-align:center;">Nova comanda</h3>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="tipo" id="tipoInput" value="normal">

            <div class="switch-wrap">
                <div class="switch-opt active" onclick="mudarTipo('normal', this)">Serviço</div>
                <div class="switch-opt" onclick="mudarTipo('pacote', this)">Pacote</div>
            </div>

            <div class="form-group">
                <label class="form-label">Cliente</label>
                <select name="cliente_id" id="clienteSelect" class="form-input" required onchange="verificarDadosCliente()">
                    <option value="">Selecione...</option>
                    <?php foreach($clientes as $cli): ?>
                        <option value="<?= $cli['id'] ?>"><?= htmlspecialchars($cli['nome']) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Pacotes ativos para copiar -->
                <div id="boxPacotesAtivos" style="display:none; margin-top:8px; background:#fff7ed; padding:10px; border-radius:14px; border:1px solid #fed7aa;">
                    <label style="font-size:0.7rem; color:#c2410c; font-weight:700; display:block; margin-bottom:4px;">
                        <i class="bi bi-exclamation-circle-fill"></i> Reutilizar pacote existente:
                    </label>
                    <select id="selPacoteAtivo" class="form-input" style="font-size:0.78rem; padding:8px; border-color:#fdba74; color:#c2410c; background:#fff;" onchange="usarPacoteExistente()">
                        <option value="">-- Selecione para copiar dados --</option>
                    </select>
                </div>

                <!-- Agendamentos do cliente -->
                <div id="agendamentosBox" style="display:none; margin-top:8px; padding:10px; background:#eff6ff; color:#1e40af; border-radius:14px; font-size:0.78rem; border:1px solid #bfdbfe;">
                    <div style="font-weight:700; margin-bottom:4px;">
                        <i class="bi bi-calendar-check"></i> Agendamentos futuros
                    </div>
                    <div id="agendamentosResumo" style="margin-bottom:4px; font-size:0.75rem;"></div>
                    <div id="agendamentosLista" style="max-height:110px; overflow:auto; margin-bottom:6px; padding-left:4px;"></div>
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.74rem; margin-top:4px;">
                        <input type="checkbox" name="usar_agenda" id="usarAgenda" value="1" checked onchange="onToggleUsarAgenda()">
                        <span>Montar com base nas datas da agenda</span>
                    </label>
                    <div id="agendaAviso" style="margin-top:6px; font-size:0.72rem; color:#64748b;">
                        Quando usar a agenda, a quantidade e datas seguem os agendamentos.
                    </div>
                </div>
            </div>

            <div class="form-group" id="wrapServico">
                <label class="form-label">Serviço base</label>
                <select name="servico_id" id="selServico" class="form-input" onchange="atualizarValores()">
                    <option value="" data-preco="0">Selecione...</option>
                    <?php foreach($servicosUnicos as $s): ?>
                        <option value="<?= $s['id'] ?>"
                                data-preco="<?= $s['preco'] ?>"
                                data-qtd="<?= $s['qtd_sessoes'] ?>">
                            <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="wrapPacote" style="display:none;">
                <label class="form-label">Pacote cadastrado</label>
                <select name="pacote_id" id="selPacote" class="form-input" onchange="atualizarValores()">
                    <option value="" data-preco="0">Selecione...</option>
                    <?php foreach($pacotesCadastrados as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                data-preco="<?= $p['preco'] ?>"
                                data-qtd="<?= $p['qtd_sessoes'] ?>">
                            <?= htmlspecialchars($p['nome']) ?> - R$ <?= number_format($p['preco'], 2, ',', '.') ?> (<?= $p['qtd_sessoes'] ?> sessões)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" id="titulo" class="form-input" placeholder="Ex: Tratamento facial completo">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <div class="form-group">
                    <label class="form-label">Sessões</label>
                    <input type="number" name="qtd_total" id="qtd" class="form-input" value="1" min="1" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="valor_final" id="valorFinal" class="form-input" style="color:var(--primary); font-weight:700;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <div class="form-group">
                    <label class="form-label">Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Frequência</label>
                    <select name="frequencia" class="form-input" id="frequenciaSelect">
                        <option value="diaria">Diária</option>
                        <option value="semanal">Semanal</option>
                        <option value="quinzenal">Quinzenal</option>
                        <option value="mensal_dia">Mensal (dia fixo)</option>
                        <option value="mensal_semana">Mensal (semana)</option>
                        <option value="unico">Único</option>
                        <option value="personalizada">Personalizada</option>
                    </select>
                    <div id="diasSemanaBox" style="display:none; margin-top:6px;">
                        <label class="form-label">Dia(s) da semana</label>
                        <div class="weekday-options">
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="1"><span>Seg</span></label>
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="2"><span>Ter</span></label>
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="3"><span>Qua</span></label>
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="4"><span>Qui</span></label>
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="5"><span>Sex</span></label>
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="6"><span>Sáb</span></label>
                            <label class="weekday-chip"><input type="checkbox" name="dias_semana[]" value="0"><span>Dom</span></label>
                        </div>
                    </div>
                    <div id="intervaloBox" style="display:none; margin-top:6px;">
                        <label class="form-label">Intervalo (dias)</label>
                        <input type="number" name="intervalo_personalizado" class="form-input" min="1" value="1">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Criar comanda</button>
            <button type="button" class="btn-cancel-modal" onclick="fecharModal('modalNova')">Cancelar</button>
        </form>
    </div>
</div>

<!-- MODAL CONFIRMAR SESSÃO -->
<div id="modalConfirmar" class="modal-overlay">
    <div class="modal-box" style="text-align:center;">
        <div style="width:40px; height:4px; background:#e2e8f0; border-radius:999px; margin:0 auto 14px;"></div>
        <div style="width:60px; height:60px; background:var(--success-bg); color:var(--success); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:1.8rem;">
            <i class="bi bi-check-lg"></i>
        </div>
        <h3 id="confCliente" style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text-main);">Cliente</h3>
        <p id="confProgress" style="color:var(--text-muted); margin:5px 0 18px; font-size:0.85rem;">Sessão 2 de 5</p>

        <form method="POST">
            <input type="hidden" name="acao" value="confirmar_sessao">
            <input type="hidden" name="comanda_id" id="confId">

            <div class="form-group" style="text-align:left;">
                <label class="form-label">Data realizada</label>
                <input type="date" name="data_realizada" class="form-input" value="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn-submit" style="background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 10px 24px rgba(16,185,129,0.45);">
                Confirmar presença
            </button>
            <button type="button" class="btn-cancel-modal" onclick="fecharModal('modalConfirmar')">Cancelar</button>
        </form>
    </div>
</div>

<!-- MODAL EDITAR COMANDA -->
<div id="modalEditar" class="modal-overlay">
    <div class="modal-box">
        <div style="width:40px; height:4px; background:#e2e8f0; border-radius:999px; margin:0 auto 14px;"></div>
        <h3 style="font-size:1rem; font-weight:700; margin:0 0 14px; text-align:center;">
            <i class="bi bi-pencil-square"></i> Editar comanda
        </h3>
        <form method="POST">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id" id="editId">

            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-tag-fill"></i> Título
                </label>
                <input type="text" name="edit_titulo" id="editTitulo" class="form-input" required>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-list-ol"></i> Sessões
                    </label>
                    <input type="number" name="edit_qtd_total" id="editQtdTotal" class="form-input" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-currency-dollar"></i> Valor Total (R$)
                    </label>
                    <input type="number" step="0.01" name="edit_valor_total" id="editValorTotal" class="form-input" style="color:var(--primary); font-weight:700;" required>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-calendar-event"></i> Data Início
                    </label>
                    <input type="date" name="edit_data_inicio" id="editDataInicio" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-arrow-repeat"></i> Frequência
                    </label>
                    <select name="edit_frequencia" id="editFrequencia" class="form-input" required>
                        <option value="diaria">Diária</option>
                        <option value="semanal">Semanal</option>
                        <option value="quinzenal">Quinzenal</option>
                        <option value="mensal_dia">Mensal (dia fixo)</option>
                        <option value="mensal_semana">Mensal (semana)</option>
                        <option value="unico">Único</option>
                        <option value="personalizada">Personalizada</option>
                    </select>
                </div>
            </div>

            <div style="background:#fff7ed; padding:12px; border-radius:14px; color:#c2410c; font-size:0.74rem; line-height:1.4; margin-bottom:8px; border:1px solid #fed7aa;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Atenção:</strong> Ao alterar a quantidade de sessões, todas as sessões atuais serão recriadas. Sessões já confirmadas serão perdidas.
            </div>

            <button type="submit" class="btn-submit">Salvar alterações</button>
            <button type="button" class="btn-cancel-modal" onclick="fecharModal('modalEditar')">Cancelar</button>
        </form>
    </div>
</div>

<script>
    // TOAST
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toastMsg').innerText = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2600);
    }

    const p = new URLSearchParams(window.location.search);
    const msg = p.get('msg');
    if (msg === 'criado')   showToast('Comanda criada com sucesso!');
    if (msg === 'sessao_ok') showToast('Sessão confirmada!');
    if (msg === 'deletado') showToast('Comanda removida.');
    if (msg === 'editado')  showToast('Dados atualizados.');

    // MODAIS
    function abrirModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function fecharModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        document.body.style.overflow = '';
    }

    // TIPO (serviço / pacote)
    function mudarTipo(tipo, el) {
        document.getElementById('tipoInput').value = tipo;
        document.querySelectorAll('.switch-opt').forEach(e => e.classList.remove('active'));
        el.classList.add('active');

        const qtd          = document.getElementById('qtd');
        const wrapServico  = document.getElementById('wrapServico');
        const wrapPacote   = document.getElementById('wrapPacote');
        const selectServico = document.getElementById('selServico');
        const selectPacote  = document.getElementById('selPacote');

        if (tipo === 'pacote') {
            wrapServico.style.display = 'none';
            wrapPacote.style.display  = 'block';
            if (selectServico) selectServico.value = "";
            qtd.setAttribute('readonly', true);
            qtd.style.backgroundColor = '#e5e7eb';
        } else {
            wrapServico.style.display = 'block';
            wrapPacote.style.display  = 'none';
            if (selectPacote) selectPacote.value = "";
            qtd.removeAttribute('readonly');
            qtd.style.backgroundColor = '#f9fafb';
        }
        atualizarValores();
    }

    function atualizarValores() {
        const tipo = document.getElementById('tipoInput').value;
        let sel;

        if (tipo === 'pacote') {
            sel = document.getElementById('selPacote');
        } else {
            sel = document.getElementById('selServico');
        }
        if (!sel) return;

        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;

        const preco  = parseFloat(opt.getAttribute('data-preco')) || 0;
        const qtdServ = parseInt(opt.getAttribute('data-qtd')) || 1;
        let nome     = opt.text.split(' - R$')[0].trim();

        const tituloInput = document.getElementById('titulo');
        if (tituloInput && tituloInput.value === '') {
            tituloInput.value = nome;
        }

        if (tipo === 'pacote') {
            document.getElementById('qtd').value = qtdServ > 1 ? qtdServ : 4;
        }

        calcTotal();
    }

    function calcTotal() {
        const tipo = document.getElementById('tipoInput').value;
        const sel  = (tipo === 'pacote') ? document.getElementById('selPacote') : document.getElementById('selServico');
        if (!sel) return;

        const opt   = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.getAttribute('data-preco')) || 0;
        const qtd   = parseInt(document.getElementById('qtd').value) || 1;

        if (tipo === 'pacote') {
            document.getElementById('valorFinal').value = preco.toFixed(2);
        } else {
            document.getElementById('valorFinal').value = (preco * qtd).toFixed(2);
        }
    }

    function abrirConfirmacao(id, cliente, feitos, total) {
        document.getElementById('confId').value = id;
        document.getElementById('confCliente').innerText = cliente;
        let proxima = feitos + 1;
        if (proxima > total) proxima = total;
        document.getElementById('confProgress').innerText = `Confirmando sessão ${proxima} de ${total}`;
        abrirModal('modalConfirmar');
    }

    function editarComanda(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editTitulo').value = data.titulo;
        document.getElementById('editQtdTotal').value = data.qtd_total;
        document.getElementById('editValorTotal').value = data.valor_total;
        document.getElementById('editDataInicio').value = data.data_inicio;
        document.getElementById('editFrequencia').value = data.frequencia || 'semanal';
        abrirModal('modalEditar');
    }

    function excluirComanda(id) {
        if (confirm('Tem certeza? Todo o histórico desta comanda será perdido.')) {
            window.location.href = '?del=' + id;
        }
    }

    // CLIENTE: busca pacotes e agendamentos
    function verificarDadosCliente() {
        const sel        = document.getElementById('clienteSelect');
        const boxPacotes = document.getElementById('boxPacotesAtivos');
        const selPacotes = document.getElementById('selPacoteAtivo');
        const boxAg      = document.getElementById('agendamentosBox');
        const agResumo   = document.getElementById('agendamentosResumo');
        const agLista    = document.getElementById('agendamentosLista');
        const qtdInput   = document.getElementById('qtd');

        boxPacotes.style.display = 'none';
        selPacotes.innerHTML = '<option value="">-- Selecione para copiar dados --</option>';
        boxAg.style.display   = 'none';
        agResumo.innerHTML    = '';
        agLista.innerHTML     = '';

        if (!sel.value) return;

        // Pacotes ativos
        fetch('api_pacotes_cliente.php?cliente_id=' + sel.value)
            .then(r => r.json())
            .then(data => {
                if (data.pacotes && data.pacotes.length > 0) {
                    boxPacotes.style.display = 'block';
                    data.pacotes.forEach(p => {
                        let opt = document.createElement('option');
                        opt.value = p.id;
                        opt.text  = `${p.titulo} (${p.feitos}/${p.qtd_total} feitos)`;
                        opt.setAttribute('data-titulo', p.titulo);
                        opt.setAttribute('data-qtd', p.qtd_total);
                        opt.setAttribute('data-valor', p.valor_total);
                        opt.setAttribute('data-tipo', p.tipo);
                        selPacotes.appendChild(opt);
                    });
                }
            })
            .catch(() => {});

        // Agendamentos futuros
        fetch('api_agendamentos_cliente.php?cliente_id=' + sel.value)
            .then(r => r.json())
            .then(data => {
                if (data.agendamentos && data.agendamentos.length > 0) {
                    boxAg.style.display = 'block';
                    agResumo.innerText = `${data.agendamentos.length} agendamento(s) futuro(s) encontrados.`;

                    let html = '';
                    data.agendamentos.forEach(a => {
                        const serv = a.servico_nome ? ` • ${a.servico_nome}` : '';
                        html += `<div>• ${a.data_br} • ${a.hora}${serv}</div>`;
                    });
                    agLista.innerHTML = html;

                    if (qtdInput) {
                        qtdInput.value = data.agendamentos.length;
                        calcTotal();
                    }
                }
            })
            .catch(() => {});
    }

    function usarPacoteExistente() {
        const sel = document.getElementById('selPacoteAtivo');
        const opt = sel.options[sel.selectedIndex];

        if (!opt || !opt.value) return;

        const titulo = opt.getAttribute('data-titulo');
        const qtd    = opt.getAttribute('data-qtd');
        const valor  = opt.getAttribute('data-valor');
        const tipo   = opt.getAttribute('data-tipo');

        document.getElementById('titulo').value     = titulo;
        document.getElementById('qtd').value        = qtd;
        document.getElementById('valorFinal').value = parseFloat(valor).toFixed(2);

        if (tipo === 'pacote') {
            const switchPacote = document.querySelectorAll('.switch-opt')[1];
            if (switchPacote) mudarTipo('pacote', switchPacote);
        } else {
            const switchNormal = document.querySelectorAll('.switch-opt')[0];
            if (switchNormal) mudarTipo('normal', switchNormal);
        }

        showToast('Dados do pacote copiados!');
    }

    function onFrequenciaChange() {
        const freq           = document.getElementById('frequenciaSelect')?.value;
        const diasSemanaBox  = document.getElementById('diasSemanaBox');
        const intervaloBox   = document.getElementById('intervaloBox');
        if (!diasSemanaBox || !intervaloBox || !freq) return;

        diasSemanaBox.style.display = (freq === 'semanal' || freq === 'mensal_semana') ? 'block' : 'none';
        intervaloBox.style.display  = (freq === 'personalizada') ? 'block' : 'none';
    }

    function onToggleUsarAgenda() {
        const usarAgenda = document.getElementById('usarAgenda');
        const qtdInput   = document.getElementById('qtd');
        const freqSelect = document.getElementById('frequenciaSelect');
        const aviso      = document.getElementById('agendaAviso');

        if (!usarAgenda || !qtdInput) return;

        const disabled = usarAgenda.checked;

        qtdInput.readOnly       = disabled;
        qtdInput.style.backgroundColor = disabled ? '#e5e7eb' : '#f9fafb';
        if (freqSelect) {
            freqSelect.disabled = false;
            freqSelect.style.backgroundColor = '#f9fafb';
        }
        if (aviso) aviso.style.display = disabled ? 'block' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const freqSelect = document.getElementById('frequenciaSelect');
        if (freqSelect) {
            freqSelect.addEventListener('change', onFrequenciaChange);
            onFrequenciaChange();
        }
        onToggleUsarAgenda();
    });

    // FECHAR MODAL AO CLICAR FORA
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => {
            if (e.target === el) fecharModal(el.id);
        });
    });
</script>
