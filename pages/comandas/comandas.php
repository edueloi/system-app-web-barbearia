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
            $frequencia = $_POST['frequencia'];

            $agendamentos = [];

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

            // Agora decide COMO criar os itens
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
                // COMPORTAMENTO ANTIGO: gera por recorrência
                $data_atual = new DateTime($dt_inicio);

                $stmtItem = $pdo->prepare("
                    INSERT INTO comanda_itens (comanda_id, numero, data_prevista, valor_sessao, status)
                    VALUES (?, ?, ?, ?, 'pendente')
                ");

                for ($i = 1; $i <= $qtd; $i++) {
                    $dt_sql = $data_atual->format('Y-m-d');
                    $stmtItem->execute([$comanda_id, $i, $dt_sql, $valor_sessao]);

                    if ($frequencia !== 'unico') {
                        $dias = ($frequencia === 'semanal') ? 7 : (($frequencia === 'quinzenal') ? 15 : 1);
                        $data_atual->modify("+{$dias} days");
                    }
                }
            }

            $pdo->commit();
            header("Location: ?msg=criado"); 
            exit;
        }

        if ($acao === 'confirmar_sessao') {
            $comandaId = (int)$_POST['comanda_id'];
            $dataRealizada = $_POST['data_realizada'];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT i.id FROM comanda_itens i JOIN comandas c ON i.comanda_id = c.id WHERE i.comanda_id = ? AND c.user_id = ? AND i.status = 'pendente' ORDER BY i.data_prevista ASC, i.numero ASC LIMIT 1");
            $stmt->execute([$comandaId, $uid]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $pdo->prepare("UPDATE comanda_itens SET status = 'realizado', data_realizada = ? WHERE id = ?")->execute([$dataRealizada, $item['id']]);
                $pendentes = $pdo->query("SELECT COUNT(*) FROM comanda_itens WHERE comanda_id = $comandaId AND status = 'pendente'")->fetchColumn();
                if ($pendentes == 0) {
                    $pdo->prepare("UPDATE comandas SET status = 'fechada' WHERE id = ?")->execute([$comandaId]);
                }
            }
            $pdo->commit();
            header("Location: ?msg=sessao_ok"); exit;
        }
        
        if ($acao === 'editar') {
            $id = (int)$_POST['id'];
            $titulo = trim($_POST['edit_titulo']);
            $pdo->prepare("UPDATE comandas SET titulo = ? WHERE id = ? AND user_id = ?")->execute([$titulo, $id, $uid]);
            header("Location: ?msg=editado"); exit;
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
    header("Location: ?msg=deletado"); exit;
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
$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE user_id = $uid ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Busca apenas Serviços Avulsos (tipo = unico)
$servicosUnicos = $pdo->query("SELECT id, nome, preco, tipo, qtd_sessoes FROM servicos WHERE user_id = $uid AND tipo = 'unico' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Busca apenas Pacotes Cadastrados (tipo = pacote)
$pacotesCadastrados = $pdo->query("SELECT id, nome, preco, tipo, qtd_sessoes FROM servicos WHERE user_id = $uid AND tipo = 'pacote' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --primary-light: #e0e7ff;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --success: #10b981;
        --success-bg: #dcfce7;
        --danger: #ef4444;
        --danger-bg: #fee2e2;
        
        --radius-card: 24px;
        --radius-btn: 99px;
        --shadow-sm: 0 4px 6px -1px rgba(0,0,0,0.05);
        --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    body { font-family: 'Poppins', sans-serif; background: var(--bg-body); color: var(--text-main); font-size: 0.85rem; margin: 0; }
    .app-container { max-width: 1100px; margin: 0 auto; padding: 20px 16px 100px; }

    /* HEADER */
    .app-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
    .page-title h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0; letter-spacing: -0.5px; }
    .page-title p { color: var(--text-muted); font-size: 0.8rem; margin-top: 4px; }

    /* TABS */
    .tabs-pill { display: inline-flex; background: #fff; padding: 4px; border-radius: var(--radius-btn); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
    .tab-link { padding: 8px 24px; border-radius: var(--radius-btn); text-decoration: none; color: var(--text-muted); font-weight: 600; font-size: 0.8rem; transition: all 0.3s ease; }
    .tab-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(99,102,241,0.3); }

    /* SEARCH BAR */
    .controls-row { display: flex; gap: 12px; margin-bottom: 24px; }
    .search-wrapper { flex: 1; position: relative; }
    .search-wrapper i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem; }
    .search-input { width: 100%; padding: 12px 16px 12px 42px; border-radius: var(--radius-btn); border: 1px solid var(--border); font-size: 0.85rem; outline: none; transition: 0.3s; background: #fff; box-sizing: border-box; }
    .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-light); }

    .btn-gradient { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 0 24px; border-radius: var(--radius-btn); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; height: 44px; box-shadow: 0 4px 15px rgba(99,102,241,0.3); transition: transform 0.2s; white-space: nowrap; }
    .btn-gradient:hover { transform: translateY(-2px); }

    /* CARD LIST (MOBILE) & TABLE (DESKTOP) */
    .desktop-table { display: none; background: #fff; border-radius: var(--radius-card); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow-sm); }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 16px 24px; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; background: #f9fafb; border-bottom: 1px solid var(--border); letter-spacing: 0.5px; }
    td { padding: 14px 24px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text-main); font-weight: 500; }
    tr:last-child td { border-bottom: none; }
    
    .badge-pill { padding: 4px 12px; border-radius: var(--radius-btn); font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-blue { background: var(--primary-light); color: var(--primary); }
    .badge-orange { background: #fff7ed; color: #c2410c; }

    /* CARDS */
    .cards-list { display: grid; grid-template-columns: 1fr; gap: 16px; }
    .card { background: var(--bg-card); border-radius: var(--radius-card); padding: 20px; box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
    .card-top { display: flex; justify-content: space-between; margin-bottom: 12px; align-items: flex-start; }
    .card-title { font-weight: 700; color: var(--text-main); font-size: 1rem; margin-bottom: 2px; }
    
    /* PROGRESSO */
    .prog-bar { height: 6px; background: #f1f5f9; border-radius: var(--radius-btn); overflow: hidden; margin-top: 6px; }
    .prog-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #818cf8); border-radius: var(--radius-btn); width: 0%; transition: width 1s ease; }
    .prog-fill.finished { background: var(--success); }

    /* ACTIONS */
    .btn-icon { width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--border); background: #fff; color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 0.9rem; }
    .btn-icon:hover { color: var(--primary); border-color: var(--primary-light); background: #f8fafc; transform: scale(1.05); }
    .btn-icon.del:hover { color: var(--danger); border-color: #fee2e2; background: #fff1f2; }
    
    /* MODAL */
    .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; display: flex; align-items: flex-end; justify-content: center; }
    .modal-overlay.open { opacity: 1; pointer-events: auto; }
    .modal-box { background: #fff; width: 100%; max-width: 450px; border-radius: 32px 32px 0 0; padding: 24px; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); max-height: 90vh; overflow-y: auto; box-shadow: 0 -10px 40px rgba(0,0,0,0.1); }
    .modal-overlay.open .modal-box { transform: translateY(0); }
    
    @media(min-width: 768px) {
        .modal-overlay { align-items: center; }
        .modal-box { border-radius: 28px; transform: translateY(20px); width: 420px; }
        .desktop-table { display: block; }
        .cards-list { display: none; }
    }

    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-input { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 16px; font-size: 0.9rem; background: #fcfcfc; outline: none; box-sizing: border-box; font-family: inherit; transition: 0.2s; }
    .form-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px var(--primary-light); }
    
    .switch-wrap { background: #f1f5f9; padding: 4px; border-radius: 16px; display: flex; margin-bottom: 20px; }
    .switch-opt { flex: 1; text-align: center; padding: 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
    .switch-opt.active { background: #fff; color: var(--primary); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    .btn-submit { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: var(--radius-btn); font-weight: 600; font-size: 1rem; cursor: pointer; margin-top: 10px; box-shadow: 0 4px 15px rgba(99,102,241,0.3); transition: 0.2s; }
    .btn-submit:active { transform: scale(0.98); }
    
    .btn-cancel-modal { width: 100%; padding: 14px; background: #f1f5f9; color: var(--text-main); border: none; border-radius: var(--radius-btn); font-weight: 600; font-size: 0.9rem; cursor: pointer; margin-top: 12px; }
    .btn-cancel-modal:hover { background: #e2e8f0; }

    /* TOAST */
    .toast-box { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100px); background: #1e293b; color: #fff; padding: 12px 24px; border-radius: var(--radius-btn); display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 2000; font-weight: 500; font-size: 0.9rem; opacity: 0; transition: 0.4s; }
    .toast-box.show { transform: translateX(-50%) translateY(0); opacity: 1; }
</style>

<div id="toast" class="toast-box">
    <i class="bi bi-check-circle-fill" style="color: #4ade80;"></i>
    <span id="toastMsg">Sucesso!</span>
</div>

<div class="app-container">
    
    <div class="app-header">
        <div class="page-title">
            <h1>Comandas</h1>
            <p>Gerencie pacotes e sessões</p>
        </div>
        <div class="tabs-pill">
            <a href="?tab=aberta" class="tab-link <?= $filtro_status=='aberta'?'active':'' ?>">Abertas</a>
            <a href="?tab=fechada" class="tab-link <?= $filtro_status=='fechada'?'active':'' ?>">Concluídas</a>
        </div>
    </div>

    <div class="controls-row">
        <form style="flex:1; display:flex;" method="GET">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($filtro_status) ?>">
            <div class="search-wrapper">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar cliente..." class="search-input">
            </div>
        </form>
        <button type="button" class="btn-gradient" onclick="abrirModal('modalNova')">
            <i class="bi bi-plus-lg"></i> Novo
        </button>
    </div>

    <?php if(count($lista) == 0): ?>
        <div style="text-align:center; padding: 80px 20px; color: var(--text-muted);">
            <i class="bi bi-inbox" style="font-size: 3.5rem; opacity: 0.2; display: block; margin-bottom: 16px;"></i>
            <p>Nenhuma comanda encontrada.</p>
        </div>
    <?php endif; ?>

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
                        <div style="font-weight:700; color:var(--text-main);"><?= htmlspecialchars($c['titulo']) ?></div>
                        <span class="badge-pill badge-<?= $c['tipo']=='pacote'?'orange':'blue' ?>"><?= ucfirst($c['tipo']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($c['c_nome']) ?></td>
                    <td style="width:180px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">
                            <span><?= $c['feitos'] ?> de <?= $c['qtd_total'] ?></span>
                            <span><?= round($percent) ?>%</span>
                        </div>
                        <div class="prog-bar">
                            <div class="prog-fill <?= $percent>=100?'finished':'' ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                    <td><?= $c['proxima'] ? date('d/m/y', strtotime($c['proxima'])) : '<span class="text-muted">--</span>' ?></td>
                    <td style="font-weight:700; color:var(--success);">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                    <td>
                        <div style="display:flex; justify-content:flex-end; gap:8px;">
                            <?php if($c['status'] == 'aberta'): ?>
                            <button onclick="abrirConfirmacao(<?= $c['id'] ?>, '<?= htmlspecialchars($c['c_nome']) ?>', <?= $c['feitos'] ?>, <?= $c['qtd_total'] ?>)" class="btn-icon" style="color:var(--success); border-color:var(--success-bg); background:var(--success-bg);" title="Confirmar">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php endif; ?>
                            <button onclick='editarComanda(<?= $dataJson ?>)' class="btn-icon"><i class="bi bi-pencil"></i></button>
                            <button onclick="excluirComanda(<?= $c['id'] ?>)" class="btn-icon del"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

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
                    <div style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($c['c_nome']) ?></div>
                </div>
                <span class="badge-pill badge-<?= $c['tipo']=='pacote'?'orange':'blue' ?>"><?= ucfirst($c['tipo']) ?></span>
            </div>

            <div style="margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">
                    <span>Progresso</span>
                    <span><?= $c['feitos'] ?>/<?= $c['qtd_total'] ?></span>
                </div>
                <div class="prog-bar">
                    <div class="prog-fill <?= $percent>=100?'finished':'' ?>" style="width: <?= $percent ?>%"></div>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <div style="flex:1; background:#f8fafc; padding:10px; border-radius:16px;">
                    <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:2px;">Próxima</div>
                    <div style="font-weight:600;"><?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '--' ?></div>
                </div>
                <div style="flex:1; background:#f8fafc; padding:10px; border-radius:16px;">
                    <div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:2px;">Valor</div>
                    <div style="font-weight:600; color:var(--success);">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr auto auto; gap:8px; margin-top:16px;">
                <?php if($c['status'] == 'aberta'): ?>
                    <button class="btn-gradient" style="width:100%; justify-content:center; box-shadow:none; font-size:0.85rem; height:38px;" onclick="abrirConfirmacao(<?= $c['id'] ?>, '<?= htmlspecialchars($c['c_nome']) ?>', <?= $c['feitos'] ?>, <?= $c['qtd_total'] ?>)">
                        Confirmar
                    </button>
                <?php else: ?>
                    <button style="width:100%; border:none; background:#f1f5f9; color:var(--success); border-radius:var(--radius-btn); font-weight:600;">Concluído</button>
                <?php endif; ?>
                
                <button class="btn-icon" onclick='editarComanda(<?= $dataJson ?>)'><i class="bi bi-pencil"></i></button>
                <button class="btn-icon del" onclick="excluirComanda(<?= $c['id'] ?>)"><i class="bi bi-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<div id="modalNova" class="modal-overlay">
    <div class="modal-box">
        <div style="width:40px; height:4px; background:#e2e8f0; border-radius:99px; margin:0 auto 20px;"></div>
        <h3 style="font-size:1.2rem; font-weight:700; margin:0 0 20px; text-align:center;">Novo Pacote</h3>
        
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
                    <?php foreach($clientes as $cli) echo "<option value='{$cli['id']}'>{$cli['nome']}</option>"; ?>
                </select>
                
                <div id="boxPacotesAtivos" style="display:none; margin-top:12px; background:#fff7ed; padding:12px; border-radius:16px; border:1px solid #fed7aa;">
                    <label style="font-size:0.75rem; color:#c2410c; font-weight:700; display:block; margin-bottom:6px;">
                        <i class="bi bi-exclamation-circle-fill"></i> REUTILIZAR PACOTE EXISTENTE:
                    </label>
                    <select id="selPacoteAtivo" class="form-input" style="font-size:0.85rem; padding:10px; border-color:#fdba74; color:#c2410c; background:#fff;" onchange="usarPacoteExistente()">
                        <option value="">-- Selecione para copiar --</option>
                    </select>
                </div>

                <div id="agendamentosBox" style="display:none; margin-top:12px; padding:12px; background:#eff6ff; color:#1e40af; border-radius:16px; font-size:0.85rem; border:1px solid #bfdbfe;">
                    <div style="font-weight:700; margin-bottom:6px;">
                        <i class="bi bi-calendar-check"></i> Agendamentos encontrados
                    </div>
                    <div id="agendamentosResumo" style="margin-bottom:8px; font-size:0.8rem;"></div>
                    <div id="agendamentosLista" style="max-height:120px; overflow:auto; margin-bottom:10px; padding-left:4px;"></div>
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="usar_agenda" id="usarAgenda" value="1" checked onchange="onToggleUsarAgenda()">
                        <span style="font-size:0.8rem;">Usar datas da agenda</span>
                    </label>
                </div>
            </div>

            <div class="form-group" id="wrapServico">
                <label class="form-label">Serviço Base</label>
                <select name="servico_id" id="selServico" class="form-input" onchange="atualizarValores()">
                    <option value="" data-preco="0">Selecione...</option>
                    <?php foreach($servicosUnicos as $s): ?>
                        <option value="<?= $s['id'] ?>" data-preco="<?= $s['preco'] ?>" data-qtd="<?= $s['qtd_sessoes'] ?>">
                            <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="wrapPacote" style="display:none;">
                <label class="form-label">Pacote Cadastrado</label>
                <select name="pacote_id" id="selPacote" class="form-input" onchange="atualizarValores()">
                    <option value="" data-preco="0">Selecione...</option>
                    <?php foreach($pacotesCadastrados as $p): ?>
                        <option value="<?= $p['id'] ?>" data-preco="<?= $p['preco'] ?>" data-qtd="<?= $p['qtd_sessoes'] ?>">
                            <?= htmlspecialchars($p['nome']) ?> - R$ <?= number_format($p['preco'], 2, ',', '.') ?> (<?= $p['qtd_sessoes'] ?> sessões)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" id="titulo" class="form-input" placeholder="Ex: Tratamento Facial">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Sessões</label>
                    <input type="number" name="qtd_total" id="qtd" class="form-input" value="1" min="1" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" step="0.01" name="valor_final" id="valorFinal" class="form-input" style="color:var(--primary); font-weight:700;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Início</label>
                    <input type="date" name="data_inicio" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Frequência</label>
                    <select name="frequencia" class="form-input">
                        <option value="semanal">Semanal</option>
                        <option value="quinzenal">Quinzenal</option>
                        <option value="unico">Único</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-submit">Criar Comanda</button>
            <button type="button" class="btn-cancel-modal" onclick="fecharModal('modalNova')">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalConfirmar" class="modal-overlay">
    <div class="modal-box" style="text-align:center;">
        <div style="width:40px; height:4px; background:#e2e8f0; border-radius:999px; margin:0 auto 20px;"></div>
        <div style="width:64px; height:64px; background:var(--success-bg); color:var(--success); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:2rem;">
            <i class="bi bi-check-lg"></i>
        </div>
        <h3 id="confCliente" style="margin:0; font-size:1.3rem; font-weight:700; color:var(--text-main);">Cliente</h3>
        <p id="confProgress" style="color:var(--text-muted); margin:6px 0 24px; font-size:0.95rem;">Sessão 2 de 5</p>
        
        <form method="POST">
            <input type="hidden" name="acao" value="confirmar_sessao">
            <input type="hidden" name="comanda_id" id="confId">
            
            <div class="form-group" style="text-align:left;">
                <label class="form-label">Data Realizada</label>
                <input type="date" name="data_realizada" class="form-input" value="<?= date('Y-m-d') ?>">
            </div>
            
            <button type="submit" class="btn-submit" style="background:var(--success); box-shadow:0 4px 15px rgba(16,185,129,0.3);">Confirmar Presença</button>
            <button type="button" class="btn-cancel-modal" onclick="fecharModal('modalConfirmar')">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalEditar" class="modal-overlay">
    <div class="modal-box">
        <div style="width:40px; height:4px; background:#e2e8f0; border-radius:999px; margin:0 auto 20px;"></div>
        <h3 style="font-size:1.2rem; font-weight:700; margin:0 0 20px; text-align:center;">Editar</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id" id="editId">
            
            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="edit_titulo" id="editTitulo" class="form-input">
            </div>
            
            <div style="background:#f8fafc; padding:16px; border-radius:16px; color:var(--text-muted); font-size:0.8rem; line-height:1.5; margin-bottom:10px;">
                <i class="bi bi-info-circle-fill" style="color:var(--primary);"></i> Para alterar valores ou quantidade de sessões, recomendamos excluir e criar novamente para manter o histórico correto.
            </div>

            <button type="submit" class="btn-submit">Salvar</button>
            <button type="button" class="btn-cancel-modal" onclick="fecharModal('modalEditar')">Cancelar</button>
        </form>
    </div>
</div>

<script>
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toastMsg').innerText = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3000);
    }

    const p = new URLSearchParams(window.location.search);
    if(p.get('msg') === 'criado') showToast('Pacote criado com sucesso!');
    if(p.get('msg') === 'sessao_ok') showToast('Sessão confirmada!');
    if(p.get('msg') === 'deletado') showToast('Comanda removida.');
    if(p.get('msg') === 'editado') showToast('Dados atualizados.');

    function abrirModal(id) { 
        document.getElementById(id).classList.add('open'); 
        document.body.style.overflow = 'hidden'; 
    }
    function fecharModal(id) { 
        document.getElementById(id).classList.remove('open'); 
        document.body.style.overflow = ''; 
    }

    function mudarTipo(tipo, el) {
        document.getElementById('tipoInput').value = tipo;
        document.querySelectorAll('.switch-opt').forEach(e => e.classList.remove('active'));
        el.classList.add('active');
        
        const qtd = document.getElementById('qtd');
        const wrapServico = document.getElementById('wrapServico');
        const wrapPacote = document.getElementById('wrapPacote');
        const selectServico = document.getElementById('selServico');
        const selectPacote = document.getElementById('selPacote');

        if(tipo === 'pacote') {
            wrapServico.style.display = 'none';
            wrapPacote.style.display = 'block';
            selectServico.value = "";
            qtd.setAttribute('readonly', true);
            qtd.style.backgroundColor = '#f1f5f9';
        } else {
            wrapServico.style.display = 'block';
            wrapPacote.style.display = 'none';
            selectPacote.value = "";
            qtd.removeAttribute('readonly');
            qtd.style.backgroundColor = '#fcfcfc';
        }
        atualizarValores();
    }

    function atualizarValores() {
        const tipo = document.getElementById('tipoInput').value;
        let sel, opt;

        if (tipo === 'pacote') {
            sel = document.getElementById('selPacote');
        } else {
            sel = document.getElementById('selServico');
        }

        opt = sel.options[sel.selectedIndex];
        
        if(!opt || !opt.value) return;

        const preco = parseFloat(opt.getAttribute('data-preco')) || 0;
        const qtdServ = parseInt(opt.getAttribute('data-qtd')) || 1;
        let nome = opt.text.split(' - R$')[0].trim();

        if(document.getElementById('titulo').value === '') document.getElementById('titulo').value = nome;

        if(tipo === 'pacote') {
            document.getElementById('qtd').value = qtdServ > 1 ? qtdServ : 4;
        }
        
        calcTotal();
    }

    function calcTotal() {
        const tipo = document.getElementById('tipoInput').value;
        const sel = (tipo === 'pacote') ? document.getElementById('selPacote') : document.getElementById('selServico');
        const opt = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.getAttribute('data-preco')) || 0;
        const qtd = parseInt(document.getElementById('qtd').value) || 1;
        
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
        if(proxima > total) proxima = total;
        document.getElementById('confProgress').innerText = `Confirmando sessão ${proxima} de ${total}`;
        abrirModal('modalConfirmar');
    }

    function editarComanda(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editTitulo').value = data.titulo;
        abrirModal('modalEditar');
    }

    function excluirComanda(id) {
        if(confirm('Tem certeza? Todo histórico será perdido.')) {
            window.location.href = '?del=' + id;
        }
    }

    function verificarDadosCliente() {
        const sel = document.getElementById('clienteSelect');
        const boxPacotes = document.getElementById('boxPacotesAtivos');
        const selPacotes = document.getElementById('selPacoteAtivo');
        const boxAg = document.getElementById('agendamentosBox');
        const agResumo = document.getElementById('agendamentosResumo');
        const agLista = document.getElementById('agendamentosLista');
        const qtdInput = document.getElementById('qtd');
        
        boxPacotes.style.display = 'none';
        selPacotes.innerHTML = '<option value="">-- Selecione para copiar dados --</option>';
        boxAg.style.display = 'none';
        agResumo.innerHTML = '';
        agLista.innerHTML = '';

        if (!sel.value) return;

        fetch('api_pacotes_cliente.php?cliente_id=' + sel.value)
            .then(r => r.json())
            .then(data => {
                if (data.pacotes && data.pacotes.length > 0) {
                    boxPacotes.style.display = 'block';
                    data.pacotes.forEach(p => {
                        let opt = document.createElement('option');
                        opt.value = p.id;
                        opt.text = `${p.titulo} (${p.feitos}/${p.qtd_total} feitos)`;
                        opt.setAttribute('data-titulo', p.titulo);
                        opt.setAttribute('data-qtd', p.qtd_total);
                        opt.setAttribute('data-valor', p.valor_total);
                        opt.setAttribute('data-tipo', p.tipo); 
                        selPacotes.appendChild(opt);
                    });
                }
            })
            .catch(() => {});

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
        const qtd = opt.getAttribute('data-qtd');
        const valor = opt.getAttribute('data-valor');
        const tipo = opt.getAttribute('data-tipo');

        document.getElementById('titulo').value = titulo;
        document.getElementById('qtd').value = qtd;
        document.getElementById('valorFinal').value = parseFloat(valor).toFixed(2);
        
        if (tipo === 'pacote') {
            const switchPacote = document.querySelectorAll('.switch-opt')[1];
            if(switchPacote) mudarTipo('pacote', switchPacote);
        } else {
            const switchNormal = document.querySelectorAll('.switch-opt')[0];
            if(switchNormal) mudarTipo('normal', switchNormal);
        }
        
        showToast('Dados copiados!');
    }

    function onToggleUsarAgenda() { }

    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => {
            if(e.target === el) fecharModal(el.id);
        });
    });
</script>