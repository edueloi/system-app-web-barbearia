<?php
$pageTitle = 'Minha Agenda';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Simulação de Login
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// DATA ATUAL EXIBIDA
$dataExibida = $_GET['data'] ?? date('Y-m-d');

// --- AÇÕES ---
// A. Excluir agendamento
if (isset($_GET['delete'])) {
    $idDel = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM agendamentos WHERE id = ? AND user_id = ?")
        ->execute([$idDel, $userId]);

    echo "<script>window.location.href='agenda.php?data={$dataExibida}';</script>";
    exit;
}

// B. Atualizar status (Confirmado / Pendente / Cancelado)
if (isset($_GET['update_status'], $_GET['id'])) {
    $idUp   = (int)$_GET['id'];
    $status = $_GET['update_status'];

    $permitidos = ['Pendente','Confirmado','Cancelado'];
    if (in_array($status, $permitidos, true)) {
        $stmt = $pdo->prepare("UPDATE agendamentos SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $idUp, $userId]);
    }
    echo "<script>window.location.href='agenda.php?data={$dataExibida}';</script>";
    exit;
}

// C. Salvar novo agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
    $cliente   = trim($_POST['cliente'] ?? '');
    $servicoId = $_POST['servico_id'] ?? null;
    $valor     = str_replace(',', '.', $_POST['valor'] ?? '0');
    $horario   = $_POST['horario'] ?? '';
    $obs       = trim($_POST['obs'] ?? '');

    // Busca o nome do serviço
    $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = ? AND user_id = ?");
    $stmt->execute([$servicoId, $userId]);
    $nomeServico = $stmt->fetchColumn() ?: 'Serviço';

    if (!empty($cliente) && !empty($horario)) {
        $sql = "INSERT INTO agendamentos 
                    (user_id, cliente_nome, servico, valor, data_agendamento, horario, observacoes, status) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, 'Confirmado')";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$userId, $cliente, $nomeServico, $valor, $dataExibida, $horario, $obs])) {
            echo "<script>window.location.href='agenda.php?data={$dataExibida}';</script>";
            exit;
        } else {
            echo "<script>alert('Erro ao salvar agendamento');</script>";
        }
    } else {
        echo "<script>alert('Preencha cliente e horário');</script>";
    }
}

// --- BUSCAR DADOS ---
// Navegação de datas
$dataAnt = date('Y-m-d', strtotime($dataExibida . ' -1 day'));
$dataPro = date('Y-m-d', strtotime($dataExibida . ' +1 day'));

// Agendamentos do dia
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE user_id = ? AND data_agendamento = ? ORDER BY horario ASC");
$stmt->execute([$userId, $dataExibida]);
$agendamentos = $stmt->fetchAll();

// Faturamento do dia (usando valor salvo ou preço atual do serviço)
$faturamentoDia = 0;
foreach ($agendamentos as $ag) {
    $valorServico = $ag['valor'] ?? 0;

    // tenta pegar preço atual do serviço pelo nome
    $stmtValor = $pdo->prepare("SELECT preco FROM servicos WHERE nome = ? AND user_id = ? LIMIT 1");
    $stmtValor->execute([$ag['servico'], $userId]);
    $precoAtual = $stmtValor->fetchColumn();
    if ($precoAtual !== false) {
        $valorServico = $precoAtual;
    }
    $faturamentoDia += $valorServico;
}

// Listas p/ modal
$listaServicos = $pdo->query("SELECT * FROM servicos WHERE user_id = {$userId} ORDER BY nome ASC")->fetchAll();
$listaClientes = $pdo->query("SELECT nome FROM clientes WHERE user_id = {$userId} ORDER BY nome ASC")->fetchAll();
?>

<style>
    .agenda-header {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
        padding: 20px;
        border-radius: 16px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 15px rgba(99,102,241,0.3);
    }
    .agenda-header h2 { margin:0; }
    .agenda-header small { opacity:.9; }

    .total-val { font-size: 1.8rem; font-weight: 700; }

    .nav-box {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        background: white;
        padding: 8px 14px;
        border-radius: 999px;
        width: fit-content;
        margin-inline: auto;
        box-shadow: 0 2px 10px rgba(15,23,42,0.06);
    }
    .btn-nav {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #f1f5f9;
        border:none;
        display:flex;
        align-items:center;
        justify-content:center;
        color:#111827;
        text-decoration:none;
        font-size: 1.1rem;
    }
    .nav-date-label {
        font-weight:600;
        font-size:0.95rem;
    }
    .nav-today {
        border-radius:999px;
        padding:4px 10px;
        font-size:0.8rem;
        background:#e0f2fe;
        color:#0369a1;
        text-decoration:none;
    }

    .timeline { display:flex; flex-direction:column; gap:10px; }

    .event-card {
        background:white;
        border-radius:12px;
        display:flex;
        border:1px solid #f1f5f9;
        overflow:hidden;
        box-shadow:0 2px 6px rgba(15,23,42,0.04);
        position:relative;
    }
    .event-card.st-Confirmado { border-left:4px solid #22c55e; }
    .event-card.st-Pendente   { border-left:4px solid #facc15; }
    .event-card.st-Cancelado  { border-left:4px solid #ef4444; }

    .time-col {
        background:#f8fafc;
        width:72px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:700;
        font-size:1.05rem;
        border-right:1px solid #e2e8f0;
    }
    .info-col { flex:1; padding:12px 14px; }
    .action-col {
        padding:10px 10px 10px 0;
        display:flex;
        flex-direction:column;
        align-items:flex-end;
        justify-content:space-between;
        min-width:140px;
        gap:6px;
    }
    .cliente-nome { font-weight:700; font-size:1rem; }
    .servico-txt { color:#6366f1; font-size:0.86rem; margin-top:2px; }
    .obs-txt {
        font-size:0.78rem;
        color:#94a3b8;
        margin-top:4px;
        max-width:260px;
    }
    .valor-txt { color:#16a34a; font-weight:700; font-size:1.05rem; }

    .badge-status {
        font-size:0.7rem;
        padding:3px 9px;
        border-radius:999px;
        font-weight:600;
        white-space:nowrap;
    }
    .badge-Confirmado { background:#dcfce7; color:#166534; }
    .badge-Pendente   { background:#fef9c3; color:#854d0e; }
    .badge-Cancelado  { background:#fee2e2; color:#b91c1c; }

    .mini-actions {
        display:flex;
        gap:4px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }
    .mini-btn {
        border-radius:999px;
        padding:3px 8px;
        font-size:0.72rem;
        border:none;
        text-decoration:none;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        gap:3px;
    }
    .mini-btn.green { background:#dcfce7; color:#166534; }
    .mini-btn.amber { background:#fef3c7; color:#92400e; }
    .mini-btn.red   { background:#fee2e2; color:#b91c1c; }
    .mini-btn.gray  { background:#f1f5f9; color:#4b5563; }
    .mini-btn.purple{ background:#ede9fe; color:#6d28d9; } /* Nota PDF */

    .fab {
        position: fixed;
        bottom: 28px;
        right: 28px;
        width: 58px;
        height: 58px;
        background:#6366f1;
        color:white;
        border-radius:50%;
        border:none;
        font-size:2rem;
        box-shadow:0 10px 25px rgba(79,70,229,0.5);
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:center;
        z-index: 950;
    }

    .modal-overlay {
        display:none;
        position:fixed;
        inset:0;
        background:rgba(15,23,42,0.45);
        z-index:999;
        justify-content:center;
        align-items:center;
        padding:16px;
    }
    .modal-overlay.active { display:flex; }
    .modal-box {
        background:white;
        padding:22px;
        border-radius:18px;
        width:100%;
        max-width:420px;
    }

    .modal-box h3 {
        margin-top:0;
        margin-bottom:8px;
    }
    .modal-sub { font-size:0.8rem; color:#6b7280; margin-bottom:14px; }

    .form-control-modal {
        width:100%;
        padding:10px;
        border-radius:10px;
        border:1px solid #e2e8f0;
        font-size:0.9rem;
        margin-bottom:10px;
    }
    .form-control-modal:focus {
        outline:none;
        border-color:#6366f1;
        box-shadow:0 0 0 1px rgba(99,102,241,0.35);
    }
    .label-modal {
        font-size:0.8rem;
        font-weight:600;
        color:#475569;
        margin-bottom:3px;
        display:block;
    }
    .row-inline {
        display:flex;
        gap:8px;
    }
    @media (max-width:500px){
        .row-inline { flex-direction:column; }
    }

    .btn-primary-modal {
        width:100%;
        padding:11px;
        border-radius:999px;
        border:none;
        background:#6366f1;
        color:white;
        font-weight:600;
        cursor:pointer;
        margin-top:5px;
    }
    .btn-secondary-modal {
        width:100%;
        padding:10px;
        border-radius:999px;
        border:none;
        background:transparent;
        color:#6b7280;
        cursor:pointer;
        margin-top:2px;
        font-size:0.9rem;
    }
</style>

<main class="main-content">

    <div class="agenda-header">
        <div>
            <h2>Agenda</h2>
            <small>Visão diária de atendimentos</small>
        </div>
        <div style="text-align:right;">
            <small>Faturamento do dia</small>
            <div class="total-val">
                R$ <?php echo number_format($faturamentoDia, 2, ',', '.'); ?>
            </div>
        </div>
    </div>

    <div class="nav-box">
        <a href="?data=<?php echo $dataAnt; ?>" class="btn-nav"><i class="bi bi-chevron-left"></i></a>
        <div style="text-align:center;">
            <div class="nav-date-label"><?php echo date('d/m/Y', strtotime($dataExibida)); ?></div>
        </div>
        <a href="?data=<?php echo $dataPro; ?>" class="btn-nav"><i class="bi bi-chevron-right"></i></a>
        <a href="agenda.php?data=<?php echo date('Y-m-d'); ?>" class="nav-today">Hoje</a>
    </div>

    <div class="timeline">
        <?php if (count($agendamentos) > 0): ?>
            <?php foreach ($agendamentos as $ag): ?>
                <?php
                    $valorServico = $ag['valor'] ?? 0;
                    $stmtValor = $pdo->prepare("SELECT preco FROM servicos WHERE nome = ? AND user_id = ? LIMIT 1");
                    $stmtValor->execute([$ag['servico'], $userId]);
                    $precoAtual = $stmtValor->fetchColumn();
                    if ($precoAtual !== false) $valorServico = $precoAtual;

                    $status = $ag['status'] ?? 'Pendente';
                    $statusClass = 'badge-' . $status;
                    $cardClass   = 'st-' . $status;
                ?>
                <div class="event-card <?php echo $cardClass; ?> agendamento-card"
                     data-id="<?php echo $ag['id']; ?>"
                     data-status="<?php echo htmlspecialchars($status); ?>">
                    <div class="time-col">
                        <?php echo date('H:i', strtotime($ag['horario'])); ?>
                    </div>
                    <div class="info-col">
                        <div class="cliente-nome"><?php echo htmlspecialchars($ag['cliente_nome']); ?></div>
                        <div class="servico-txt"><?php echo htmlspecialchars($ag['servico']); ?></div>
                        <?php if (!empty($ag['observacoes'])): ?>
                            <div class="obs-txt"><i><?php echo htmlspecialchars($ag['observacoes']); ?></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="action-col">
                        <div>
                            <span class="valor-txt">
                                R$ <?php echo number_format($valorServico, 2, ',', '.'); ?>
                            </span>
                        </div>
                        <div class="mini-actions">
                            <span class="badge-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </div>
                        <div class="mini-actions">
                            <a class="mini-btn green"
                               href="agenda.php?data=<?php echo $dataExibida; ?>&update_status=Confirmado&id=<?php echo $ag['id']; ?>">
                                <i class="bi bi-check2"></i> Conf.
                            </a>
                            <a class="mini-btn amber"
                               href="agenda.php?data=<?php echo $dataExibida; ?>&update_status=Pendente&id=<?php echo $ag['id']; ?>">
                                <i class="bi bi-clock"></i> Pend.
                            </a>
                            <a class="mini-btn red"
                               href="agenda.php?data=<?php echo $dataExibida; ?>&update_status=Cancelado&id=<?php echo $ag['id']; ?>">
                                <i class="bi bi-x-circle"></i> Canc.
                            </a>
                            <a class="mini-btn gray"
                               href="agenda.php?data=<?php echo $dataExibida; ?>&delete=<?php echo $ag['id']; ?>"
                               onclick="return confirm('Excluir este agendamento?');">
                                <i class="bi bi-trash"></i>
                            </a>
                            <a class="mini-btn purple"
                               href="nota.php?id=<?php echo $ag['id']; ?>"
                               target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> Nota
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; color:#9ca3af; margin-top:30px;">
                Nenhum agendamento para esta data.
            </p>
        <?php endif; ?>
    </div>

</main>

<!-- Botão Flutuante Novo -->
<button class="fab" onclick="openModalAdd()">
    <i class="bi bi-plus"></i>
</button>

<!-- MODAL NOVO AGENDAMENTO -->
<div class="modal-overlay" id="modalAdd">
    <div class="modal-box">
        <h3>Novo agendamento</h3>
        <p class="modal-sub">Data selecionada: <?php echo date('d/m/Y', strtotime($dataExibida)); ?></p>
        <form method="POST">
            <input type="hidden" name="novo_agendamento" value="1">

            <label class="label-modal">Cliente</label>
            <input type="text" name="cliente" list="dlClientes" class="form-control-modal"
                   placeholder="Nome do cliente" required>
            <datalist id="dlClientes">
                <?php foreach ($listaClientes as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['nome']); ?>">
                <?php endforeach; ?>
            </datalist>

            <label class="label-modal">Serviço</label>
            <select name="servico_id" id="selServico" class="form-control-modal" onchange="atualizaPreco()" required>
                <option value="">Selecione...</option>
                <?php foreach ($listaServicos as $s): ?>
                    <option value="<?php echo $s['id']; ?>"
                            data-preco="<?php echo htmlspecialchars($s['preco']); ?>">
                        <?php echo htmlspecialchars($s['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="row-inline">
                <div style="flex:1;">
                    <label class="label-modal">Valor</label>
                    <input type="number" name="valor" id="inputValor" step="0.01"
                           class="form-control-modal" required>
                </div>
                <div style="flex:1;">
                    <label class="label-modal">Horário</label>
                    <input type="time" name="horario" class="form-control-modal" required>
                </div>
            </div>

            <label class="label-modal">Observação</label>
            <textarea name="obs" rows="2" class="form-control-modal"
                      placeholder="Opcional"></textarea>

            <button type="submit" class="btn-primary-modal">
                Confirmar agendamento
            </button>
            <button type="button" class="btn-secondary-modal" onclick="closeModalAdd()">
                Cancelar
            </button>
        </form>
    </div>
</div>

<script>
    function openModalAdd() {
        document.getElementById('modalAdd').classList.add('active');
        setTimeout(atualizaPreco, 50);
    }
    function closeModalAdd() {
        document.getElementById('modalAdd').classList.remove('active');
    }

    // Fecha modal clicando fora
    document.getElementById('modalAdd').addEventListener('click', function (e) {
        if (e.target.id === 'modalAdd') {
            closeModalAdd();
        }
    });
    // ESC fecha modal
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeModalAdd();
    });

    function atualizaPreco() {
        var sel = document.getElementById('selServico');
        if (!sel) return;
        var opt = sel.options[sel.selectedIndex];
        if (!opt) return;
        var preco = opt.getAttribute('data-preco');
        document.getElementById('inputValor').value = preco ? preco : '';
    }
</script>
