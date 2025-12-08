<?php
// pages/comandas/comandas.php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garantir login
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
$uid = (int)$_SESSION['user_id'];

// =========================================================
// LÓGICA PHP (MANTIDA)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'salvar') {
            $pdo->beginTransaction();
            $cliente_id = (int)($_POST['cliente_id'] ?? 0);
            $servico_id = !empty($_POST['servico_id']) ? (int)$_POST['servico_id'] : null;
            $titulo     = trim($_POST['titulo'] ?? 'Serviço Avulso');
            $tipo       = $_POST['tipo'] ?? 'normal';
            $qtd        = max(1, (int)($_POST['qtd_total'] ?? 1));
            $valor_tot  = (float)($_POST['valor_final'] ?? 0);
            $dt_inicio  = $_POST['data_inicio'] ?? date('Y-m-d');
            $frequencia = $_POST['frequencia'] ?? 'unico';

            $stmt = $pdo->prepare("INSERT INTO comandas (user_id, cliente_id, servico_id, titulo, tipo, status, valor_total, qtd_total, data_inicio, frequencia) VALUES (?, ?, ?, ?, ?, 'aberta', ?, ?, ?, ?)");
            $stmt->execute([$uid, $cliente_id, $servico_id, $titulo, $tipo, $valor_tot, $qtd, $dt_inicio, $frequencia]);
            $comanda_id = $pdo->lastInsertId();

            $valor_sessao = $qtd > 0 ? $valor_tot / $qtd : 0;
            $data_atual   = new DateTime($dt_inicio);

            for ($i = 1; $i <= $qtd; $i++) {
                $dt_sql = $data_atual->format('Y-m-d');
                $pdo->prepare("INSERT INTO comanda_itens (comanda_id, numero, data_prevista, valor_sessao, status) VALUES (?, ?, ?, ?, 'pendente')")
                    ->execute([$comanda_id, $i, $dt_sql, $valor_sessao]);

                if ($frequencia !== 'unico') {
                    $dias = ($frequencia === 'semanal') ? 7 : (($frequencia === 'quinzenal') ? 15 : 1);
                    $data_atual->modify("+{$dias} days");
                }
            }
            $pdo->commit();
            header("Location: ?msg=criado"); exit;
        }

        if ($acao === 'editar') {
            $id = (int)$_POST['id'];
            $titulo = trim($_POST['edit_titulo']);
            $pdo->prepare("UPDATE comandas SET titulo = ? WHERE id = ? AND user_id = ?")->execute([$titulo, $id, $uid]);
            header("Location: ?msg=editado"); exit;
        }

        if ($acao === 'confirmar_sessao') {
            $comandaId = (int)$_POST['comanda_id'];
            $dataRealizada = $_POST['data_realizada'] ?? date('Y-m-d');
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

$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE user_id = $uid ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$servicos = $pdo->query("SELECT id, nome, preco, tipo, qtd_sessoes FROM servicos WHERE user_id = $uid ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    :root {
        --primary: #6366f1;
        --primary-soft: #eef2ff;
        --text-dark: #334155;
        --text-gray: #94a3b8;
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        --border: #e2e8f0;
        --success: #10b981;
        --danger: #ef4444;
        --radius-lg: 20px;
        --radius-pill: 99px;
    }

    body { font-family: 'Inter', sans-serif; background: var(--bg-page); color: var(--text-dark); margin: 0; font-size: 14px; }
    .main-wrapper { max-width: 1000px; margin: 0 auto; padding: 20px 16px 100px; }

    /* HEADER */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .page-title h1 { font-size: 1.4rem; font-weight: 700; margin: 0; color: #0f172a; letter-spacing: -0.03em; }
    .page-title span { color: var(--text-gray); font-size: 0.8rem; }

    /* TABS REDONDINHAS */
    .nav-tabs { display: inline-flex; background: #fff; padding: 3px; border-radius: var(--radius-pill); border: 1px solid var(--border); }
    .nav-link { padding: 6px 18px; border-radius: var(--radius-pill); text-decoration: none; color: var(--text-gray); font-weight: 600; font-size: 0.75rem; transition: 0.2s; }
    .nav-link.active { background: var(--primary); color: white; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3); }

    /* BARRA DE AÇÃO */
    .action-bar { display: flex; gap: 10px; margin-bottom: 20px; }
    .search-wrap { flex: 1; position: relative; }
    .search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-gray); font-size: 0.9rem; }
    .search-input { width: 100%; padding: 10px 14px 10px 38px; border-radius: var(--radius-pill); border: 1px solid var(--border); font-size: 0.85rem; outline: none; transition: 0.2s; box-sizing: border-box; background: #fff; }
    .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
    
    .btn-create { background: var(--primary); color: white; border: none; padding: 0 18px; border-radius: var(--radius-pill); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 0.8rem; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25); white-space: nowrap; height: 38px; }
    .btn-create:hover { transform: scale(1.02); }

    /* TABELA DESKTOP (Minimalista) */
    .table-container { background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: 0 4px 6px -2px rgba(0,0,0,0.03); overflow: hidden; display: none; border: 1px solid var(--border); }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 14px 18px; color: var(--text-gray); font-size: 0.7rem; text-transform: uppercase; font-weight: 700; background: #fcfcfc; border-bottom: 1px solid var(--border); }
    td { padding: 12px 18px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 0.85rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover { background: #f8fafc; }

    /* CARDS MOBILE (App Style) */
    .cards-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
    .card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; position: relative; }
    
    .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .card-title { font-weight: 700; color: #0f172a; font-size: 0.95rem; margin-bottom: 2px; }
    .card-subtitle { font-size: 0.8rem; color: var(--text-gray); }
    
    .badge { padding: 3px 10px; border-radius: var(--radius-pill); font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
    .badge-normal { background: var(--primary-soft); color: var(--primary); }
    .badge-pacote { background: #fff7ed; color: #c2410c; }
    
    .progress-wrap { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: var(--text-gray); margin-bottom: 12px; }
    .progress-track { flex: 1; background: #f1f5f9; height: 6px; border-radius: var(--radius-pill); overflow: hidden; }
    .progress-fill { background: var(--primary); height: 100%; border-radius: var(--radius-pill); transition: width 0.5s ease; }
    .progress-fill.done { background: var(--success); }
    
    .card-actions { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; margin-top: 14px; border-top: 1px dashed var(--border); padding-top: 12px; }
    
    /* BOTÕES REDONDINHOS */
    .btn-circle { width: 36px; height: 36px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 0.9rem; }
    .btn-soft { background: #f8fafc; color: var(--text-dark); }
    .btn-danger-soft { background: #fef2f2; color: var(--danger); }
    .btn-primary-pill { background: var(--primary); color: white; border: none; border-radius: var(--radius-pill); font-weight: 600; font-size: 0.8rem; padding: 0 16px; height: 36px; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25); }
    .btn-cancel { background: #f1f5f9; color: var(--text-dark); border: none; border-radius: var(--radius-pill); font-weight: 600; font-size: 0.85rem; padding: 12px; cursor: pointer; text-align: center; width: 100%; margin-top: 10px; }

    /* MODAL BOTTOM SHEET */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(3px); z-index: 999; opacity: 0; pointer-events: none; transition: 0.3s; display: flex; align-items: flex-end; justify-content: center; }
    .modal-overlay.open { opacity: 1; pointer-events: auto; }
    
    /* Estilo "Gaveta" no Mobile */
    .modal-content { background: #fff; width: 100%; max-width: 450px; border-radius: 24px 24px 0 0; padding: 24px 20px 30px; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); transform: translateY(100%); transition: 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); max-height: 85vh; overflow-y: auto; margin: 0 auto; position: relative; }
    .modal-overlay.open .modal-content { transform: translateY(0); }
    
    /* Desktop ajuste */
    @media(min-width: 768px) {
        .modal-overlay { align-items: center; }
        .modal-content { border-radius: 24px; transform: translateY(20px); width: 400px; }
        .modal-overlay.open .modal-content { transform: translateY(0); }
        .table-container { display: block; }
        .cards-grid { display: none; }
    }

    .drag-handle { width: 40px; height: 4px; background: #e2e8f0; border-radius: var(--radius-pill); margin: 0 auto 20px; }
    .modal-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 16px; color: #0f172a; text-align: center; }

    /* FORMS */
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 6px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.04em; }
    .form-control { width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 12px; font-size: 0.9rem; box-sizing: border-box; font-family: inherit; background: #fcfcfc; outline: none; }
    .form-control:focus { border-color: var(--primary); background: #fff; }
    
    .modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px; }
    .btn-submit { background: var(--primary); color: white; border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 600; font-size: 0.9rem; cursor: pointer; width: 100%; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }

    .toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100px); background: #1e293b; color: white; padding: 10px 20px; border-radius: var(--radius-pill); display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.15); opacity: 0; transition: 0.4s; z-index: 2000; font-size: 0.85rem; font-weight: 500; }
    .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
</style>

<div id="toast" class="toast">
    <i class="bi bi-check-circle-fill" style="color: #4ade80;"></i>
    <span id="toastMsg">Sucesso!</span>
</div>

<div class="main-wrapper">
    <div class="page-header">
        <div class="page-title">
            <h1>Comandas</h1>
            <span>Gerencie seus pacotes</span>
        </div>
        <nav class="nav-tabs">
            <a href="?tab=aberta" class="nav-link <?= $filtro_status=='aberta'?'active':'' ?>">Abertas</a>
            <a href="?tab=fechada" class="nav-link <?= $filtro_status=='fechada'?'active':'' ?>">Concluídas</a>
        </nav>
    </div>

    <form class="action-bar" method="GET">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($filtro_status) ?>">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar..." class="search-input">
        </div>
        <button type="button" class="btn-create" onclick="abrirModal('modalNova')">
            <i class="bi bi-plus-lg"></i> Novo
        </button>
    </form>

    <?php if(count($lista) == 0): ?>
        <div style="text-align:center; padding: 50px 20px; color: var(--text-gray);">
            <i class="bi bi-inbox" style="font-size: 2.5rem; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
            <span style="font-size:0.9rem;">Nada por aqui.</span>
        </div>
    <?php endif; ?>

    <div class="table-container">
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
                        <div style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($c['titulo']) ?></div>
                        <span class="badge badge-<?= $c['tipo']=='pacote'?'pacote':'normal' ?>"><?= ucfirst($c['tipo']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($c['c_nome']) ?></td>
                    <td style="width:150px;">
                        <div style="font-size:0.7rem; margin-bottom:4px; color:var(--text-gray); display:flex; justify-content:space-between;">
                            <span><?= $c['feitos'] ?> feitas</span>
                            <span><?= $c['qtd_total'] ?></span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill <?= $percent>=100?'done':'' ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                    <td><?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '--' ?></td>
                    <td style="font-weight:700; color:var(--success);">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                    <td>
                        <div style="display:flex; gap:6px; justify-content:flex-end;">
                            <?php if($c['status'] == 'aberta'): ?>
                            <button type="button" class="btn-circle btn-primary-pill" style="width:auto; padding:0 12px;" onclick="abrirConfirmacao(<?= $c['id'] ?>, '<?= htmlspecialchars($c['c_nome']) ?>', <?= $c['feitos'] ?>, <?= $c['qtd_total'] ?>)">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn-circle btn-soft" onclick='editarComanda(<?= $dataJson ?>)'><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn-circle btn-danger-soft" onclick="excluirComanda(<?= $c['id'] ?>)"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cards-grid">
        <?php foreach($lista as $c): 
            $percent = ($c['qtd_total'] > 0) ? ($c['feitos'] / $c['qtd_total']) * 100 : 0;
            $percent = min(100, max(0, $percent));
            $dataJson = htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title"><?= htmlspecialchars($c['titulo']) ?></div>
                    <div class="card-subtitle"><?= htmlspecialchars($c['c_nome']) ?></div>
                </div>
                <span class="badge badge-<?= $c['tipo']=='pacote'?'pacote':'normal' ?>"><?= ucfirst($c['tipo']) ?></span>
            </div>
            
            <div class="progress-wrap">
                <span style="font-weight:600; color:var(--text-dark);"><?= $c['feitos'] ?>/<?= $c['qtd_total'] ?></span>
                <div class="progress-track">
                    <div class="progress-fill <?= $percent>=100?'done':'' ?>" style="width: <?= $percent ?>%"></div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem;">
                <div style="background:#f1f5f9; padding:4px 10px; border-radius:var(--radius-pill); color:var(--text-gray);">
                    <i class="bi bi-calendar-event"></i> <?= $c['proxima'] ? date('d/m', strtotime($c['proxima'])) : '--' ?>
                </div>
                <div style="font-weight:700; color:var(--success);">
                    R$ <?= number_format($c['valor_total'], 2, ',', '.') ?>
                </div>
            </div>

            <div class="card-actions">
                <?php if($c['status'] == 'aberta'): ?>
                    <button type="button" class="btn-primary-pill" onclick="abrirConfirmacao(<?= $c['id'] ?>, '<?= htmlspecialchars($c['c_nome']) ?>', <?= $c['feitos'] ?>, <?= $c['qtd_total'] ?>)">
                        Confirmar Sessão
                    </button>
                <?php else: ?>
                    <div style="color:var(--success); font-weight:600; font-size:0.8rem; align-self:center;">
                        <i class="bi bi-check-all"></i> Concluído
                    </div>
                <?php endif; ?>
                
                <button type="button" class="btn-circle btn-soft" onclick='editarComanda(<?= $dataJson ?>)'>
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <button type="button" class="btn-circle btn-danger-soft" onclick="excluirComanda(<?= $c['id'] ?>)">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="modalNova" class="modal-overlay">
    <div class="modal-content">
        <div class="drag-handle"></div>
        <h3 class="modal-title">Novo Pacote</h3>
        
        <form method="POST" autocomplete="off">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="tipo" id="tipoInput" value="normal">

            <div style="background:#f1f5f9; padding:4px; border-radius:12px; display:flex; margin-bottom:16px;">
                <div class="switch-item active" style="flex:1; text-align:center; padding:8px; border-radius:10px; font-size:0.8rem; font-weight:600; cursor:pointer;" onclick="mudarTipo('normal', this)">Comum</div>
                <div class="switch-item" style="flex:1; text-align:center; padding:8px; border-radius:10px; font-size:0.8rem; font-weight:600; cursor:pointer; color:#94a3b8;" onclick="mudarTipo('pacote', this)">Pacote</div>
            </div>

            <div class="form-group">
                <label class="form-label">Cliente</label>
                <select name="cliente_id" id="clienteSelect" class="form-control" required onchange="verificarPacotesExistentes()">
                    <option value="">Selecione...</option>
                    <?php foreach($clientes as $cli) echo "<option value='{$cli['id']}'>{$cli['nome']}</option>"; ?>
                </select>
                <div id="avisoPacote" style="display:none; color:#d97706; font-size:0.75rem; margin-top:4px;">
                    <i class="bi bi-info-circle"></i> Possui pacotes ativos.
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Serviço Base</label>
                <select id="selServico" name="servico_id" class="form-control" onchange="atualizarValores()" required>
                    <option value="" data-preco="0">Selecione...</option>
                    <?php foreach($servicos as $s): ?>
                        <option value="<?= $s['id'] ?>" data-preco="<?= $s['preco'] ?>" data-qtd="<?= $s['qtd_sessoes'] ?>">
                            <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" id="titulo" class="form-control" placeholder="Ex: Tratamento Completo">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Sessões</label>
                    <input type="number" name="qtd_total" id="qtd" class="form-control" value="1" min="1" onchange="calcTotal()">
                </div>
                <div class="form-group">
                    <label class="form-label">Valor Total</label>
                    <input type="number" step="0.01" name="valor_final" id="valorFinal" class="form-control" style="font-weight:700; color:var(--primary);">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Frequência</label>
                    <select name="frequencia" class="form-control">
                        <option value="semanal">Semanal</option>
                        <option value="quinzenal">Quinzenal</option>
                        <option value="unico">Único</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="fecharModal('modalNova')">Cancelar</button>
                <button type="submit" class="btn-submit">Criar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalConfirmar" class="modal-overlay">
    <div class="modal-content">
        <div class="drag-handle"></div>
        <div style="text-align:center; margin-bottom:20px;">
            <div style="width:50px; height:50px; background:var(--success-soft); color:var(--success); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.5rem;">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 id="confCliente" style="margin:0; font-size:1.1rem; font-weight:700;">Nome Cliente</h3>
            <p id="confProgress" style="color:var(--text-gray); margin:4px 0 0; font-size:0.85rem;">Sessão 2 de 5</p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="acao" value="confirmar_sessao">
            <input type="hidden" name="comanda_id" id="confId">
            
            <div class="form-group">
                <label class="form-label">Data Realizada</label>
                <input type="date" name="data_realizada" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="fecharModal('modalConfirmar')">Cancelar</button>
                <button type="submit" class="btn-submit" style="background:var(--success);">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEditar" class="modal-overlay">
    <div class="modal-content">
        <div class="drag-handle"></div>
        <h3 class="modal-title">Editar</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="editar">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="edit_titulo" id="editTitulo" class="form-control">
            </div>
            <p style="background:#f8fafc; padding:10px; border-radius:12px; color:var(--text-gray); font-size:0.8rem; line-height:1.4; margin-bottom:0;">
                Para alterar valores ou sessões, recomendamos excluir e criar novamente.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="fecharModal('modalEditar')">Cancelar</button>
                <button type="submit" class="btn-submit">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toastMsg').innerText = msg;
        t.classList.add('show');
        setTimeout(()=>t.classList.remove('show'), 3000);
    }

    const p = new URLSearchParams(window.location.search);
    if(p.get('msg')=='criado') showToast('Pacote criado!');
    if(p.get('msg')=='sessao_ok') showToast('Sessão confirmada!');
    if(p.get('msg')=='deletado') showToast('Removido.');
    if(p.get('msg')=='editado') showToast('Salvo.');

    function abrirModal(id) { document.getElementById(id).classList.add('open'); }
    function fecharModal(id) { document.getElementById(id).classList.remove('open'); }

    function mudarTipo(tipo, el) {
        document.getElementById('tipoInput').value = tipo;
        document.querySelectorAll('.switch-item').forEach(e => {
            e.style.background = 'transparent';
            e.style.color = '#94a3b8';
            e.style.boxShadow = 'none';
        });
        el.style.background = '#fff';
        el.style.color = '#4f46e5';
        el.style.boxShadow = '0 2px 8px rgba(0,0,0,0.05)';
        
        const qtd = document.getElementById('qtd');
        if(tipo === 'pacote') {
            qtd.setAttribute('readonly', true);
            qtd.style.background = '#f1f5f9';
        } else {
            qtd.removeAttribute('readonly');
            qtd.style.background = '#fff';
        }
        atualizarValores();
    }

    function atualizarValores() {
        const sel = document.getElementById('selServico');
        const opt = sel.options[sel.selectedIndex];
        if(!opt || !opt.value) return;

        const preco = parseFloat(opt.getAttribute('data-preco')) || 0;
        const qtdServ = parseInt(opt.getAttribute('data-qtd')) || 1;
        const nome = opt.text.split('-')[0].trim();

        if(document.getElementById('titulo').value === '') document.getElementById('titulo').value = nome;

        const tipo = document.getElementById('tipoInput').value;
        if(tipo === 'pacote') {
            document.getElementById('qtd').value = qtdServ > 1 ? qtdServ : 4;
        }
        calcTotal();
    }

    function calcTotal() {
        const sel = document.getElementById('selServico');
        const opt = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.getAttribute('data-preco')) || 0;
        const qtd = parseInt(document.getElementById('qtd').value) || 1;
        document.getElementById('valorFinal').value = (preco * qtd).toFixed(2);
    }

    function abrirConfirmacao(id, cliente, feitos, total) {
        document.getElementById('confId').value = id;
        document.getElementById('confCliente').innerText = cliente;
        let proxima = feitos + 1;
        if(proxima > total) proxima = total;
        document.getElementById('confProgress').innerText = `Sessão ${proxima} de ${total}`;
        abrirModal('modalConfirmar');
    }

    function editarComanda(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editTitulo').value = data.titulo;
        abrirModal('modalEditar');
    }

    function excluirComanda(id) {
        if(confirm('Tem certeza?')) window.location.href = '?del=' + id;
    }

    function verificarPacotesExistentes() {
        const sel = document.getElementById('clienteSelect');
        const box = document.getElementById('avisoPacote');
        box.style.display = 'none';
        if(!sel.value) return;
        
        fetch('api_pacotes_cliente.php?cliente_id=' + sel.value)
            .then(r => r.json())
            .then(data => {
                if(data.pacotes && data.pacotes.length > 0) box.style.display = 'block';
            });
    }
    
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => {
            if(e.target === el) el.classList.remove('open');
        });
    });
</script>