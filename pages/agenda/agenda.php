<?php
$pageTitle = 'Minha Agenda';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Simulação de Login
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- PROCESSAMENTO ---
// C. Confirmar atendimento


// A. Excluir
if (isset($_GET['delete'])) {
    $idDel = $_GET['delete'];
    $pdo->prepare("DELETE FROM agendamentos WHERE id=? AND user_id=?")->execute([$idDel, $userId]);
    $dataAtual = $_GET['data'] ?? date('Y-m-d');
    echo "<script>window.location.href='agenda.php?data=$dataAtual';</script>";
    exit;
}

// B. Salvar Novo Agendamento (CORRIGIDO)
$dataExibida = $_GET['data'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
    $cliente = $_POST['cliente'];
    $servicoId = $_POST['servico_id'];
    $valor = str_replace(',', '.', $_POST['valor']); // Garante formato decimal
    $horario = $_POST['horario'];
    $obs = $_POST['obs'];

    // Busca o nome do serviço pelo ID para salvar no histórico
    $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = ?");
    $stmt->execute([$servicoId]);
    $nomeServico = $stmt->fetchColumn();

    if (!$nomeServico) $nomeServico = 'Serviço Personalizado';

    if (!empty($cliente) && !empty($horario)) {
        // Inserção incluindo o VALOR
        $sql = "INSERT INTO agendamentos (user_id, cliente_nome, servico, valor, data_agendamento, horario, observacoes, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmado')";
        $stmt = $pdo->prepare($sql);
        
        if($stmt->execute([$userId, $cliente, $nomeServico, $valor, $dataExibida, $horario, $obs])) {
            echo "<script>window.location.href='agenda.php?data=$dataExibida';</script>";
            exit;
        } else {
            echo "<script>alert('Erro ao salvar');</script>";
        }
    }
}

// --- BUSCAR DADOS ---
$dataAnt = date('Y-m-d', strtotime($dataExibida . ' -1 day'));
$dataPro = date('Y-m-d', strtotime($dataExibida . ' +1 day'));

// Buscar Agendamentos
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE user_id = ? AND data_agendamento = ? ORDER BY horario ASC");
$stmt->execute([$userId, $dataExibida]);
$agendamentos = $stmt->fetchAll();

// Calcular Faturamento do Dia com valor atualizado do serviço
$faturamentoDia = 0;
foreach($agendamentos as $ag) {
    $valorServico = $ag['valor'];
    $stmtValor = $pdo->prepare("SELECT preco FROM servicos WHERE nome = ? AND user_id = ? LIMIT 1");
    $stmtValor->execute([$ag['servico'], $userId]);
    $precoAtual = $stmtValor->fetchColumn();
    if($precoAtual !== false) $valorServico = $precoAtual;
    $faturamentoDia += $valorServico;
}

// Listas para o Modal
$listaServicos = $pdo->query("SELECT * FROM servicos WHERE user_id = $userId ORDER BY nome ASC")->fetchAll();
$listaClientes = $pdo->query("SELECT nome FROM clientes WHERE user_id = $userId ORDER BY nome ASC")->fetchAll();
?>

<style>
    /* Estilos Simplificados e Bonitos */
    .agenda-header {
        background: linear-gradient(135deg, #6366f1, #4f46e5); color: white;
        padding: 20px; border-radius: 16px; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 4px 15px rgba(99,102,241,0.3);
    }
    .total-val { font-size: 1.8rem; font-weight: 700; }
    
    .nav-box { 
        display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 20px; 
        background: white; padding: 10px; border-radius: 50px; width: fit-content; margin-inline: auto;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .btn-nav { 
        width: 35px; height: 35px; border-radius: 50%; background: #f1f5f9; border:none; 
        display:flex; align-items:center; justify-content:center; color:#333; text-decoration:none;
    }
    
    .event-card {
        background: white; border-radius: 12px; margin-bottom: 10px; display: flex; 
        border: 1px solid #f1f5f9; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }
    .time-col { 
        background: #f8fafc; width: 70px; display: flex; align-items: center; justify-content: center; 
        font-weight: 700; font-size: 1.1rem; border-right: 1px solid #e2e8f0;
    }
    .info-col { flex: 1; padding: 15px; }
    .action-col { 
        padding: 15px; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 5px;
        border-left: 1px solid #f1f5f9; min-width: 100px;
    }
    .valor-txt { color: #16a34a; font-weight: 700; font-size: 1.1rem; }
    
    /* Modal */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; padding: 25px; border-radius: 16px; width: 90%; max-width: 400px; }
    
    .fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; background: #6366f1; color: white; border-radius: 50%; border: none; font-size: 2rem; box-shadow: 0 4px 15px rgba(99,102,241,0.4); cursor: pointer; }
</style>

<main class="main-content">

    <div class="agenda-header">
        <div>
            <h2 style="margin:0;">Agenda</h2>
            <small>Visão diária</small>
        </div>
        <div style="text-align:right;">
            <small>Faturamento Hoje</small>
            <div class="total-val">R$ <?php echo number_format($faturamentoDia, 2, ',', '.'); ?></div>
        </div>
    </div>

    <div class="nav-box">
        <a href="?data=<?php echo $dataAnt; ?>" class="btn-nav"><i class="bi bi-chevron-left"></i></a>
        <div style="text-align:center;">
            <strong><?php echo date('d/m/Y', strtotime($dataExibida)); ?></strong>
        </div>
        <a href="?data=<?php echo $dataPro; ?>" class="btn-nav"><i class="bi bi-chevron-right"></i></a>
    </div>

    <div class="timeline">
        <?php foreach($agendamentos as $ag): ?>
        <?php
            // Buscar valor atualizado do serviço
            $valorServico = $ag['valor']; // valor padrão salvo
            $stmtValor = $pdo->prepare("SELECT preco FROM servicos WHERE nome = ? AND user_id = ? LIMIT 1");
            $stmtValor->execute([$ag['servico'], $userId]);
            $precoAtual = $stmtValor->fetchColumn();
            if($precoAtual !== false) $valorServico = $precoAtual;
        ?>
        <div class="event-card agendamento-card" data-id="<?php echo $ag['id']; ?>" data-status="<?php echo $ag['status']; ?>">
            <div class="time-col"><?php echo date('H:i', strtotime($ag['horario'])); ?></div>
            <div class="info-col">
                <div style="font-weight:700; font-size:1.05rem; "><?php echo htmlspecialchars($ag['cliente_nome']); ?></div>
                <div style="color:#6366f1; font-size:0.9rem; margin-top:2px; "><?php echo htmlspecialchars($ag['servico']); ?></div>
                <?php if($ag['observacoes']): ?>
                    <div style="font-size:0.8rem; color:#94a3b8; margin-top:4px;"><i><?php echo htmlspecialchars($ag['observacoes']); ?></i></div>
                <?php endif; ?>
            </div>
            <div class="action-col">
                <span class="valor-txt">R$ <?php echo number_format($valorServico, 2, ',', '.'); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(count($agendamentos) == 0): ?>
            <p style="text-align:center; color:#ccc; margin-top:30px;">Nenhum agendamento hoje.</p>
        <?php endif; ?>
    </div>

</main>

<button class="fab" onclick="document.getElementById('modalAdd').classList.add('active')">
    <i class="bi bi-plus"></i>
</button>

<div class="modal-overlay" id="modalAdd">
    <div class="modal-box">
        <h3 style="margin-top:0;">Novo Agendamento</h3>
        <form method="POST">
            <input type="hidden" name="novo_agendamento" value="1">
            
            <label style="display:block; margin-bottom:5px; font-weight:600;">Cliente</label>
            <input type="text" name="cliente" list="dlClientes" class="form-control" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;" required placeholder="Nome do cliente">
            <datalist id="dlClientes">
                <?php foreach($listaClientes as $c) echo "<option value='{$c['nome']}'>"; ?>
            </datalist>

            <label style="display:block; margin-bottom:5px; font-weight:600;">Serviço</label>
            <select name="servico_id" id="selServico" class="form-control" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;" onchange="atualizaPreco()" required>
                <option value="">Selecione...</option>
                <?php foreach($listaServicos as $s): ?>
                    <option value="<?php echo $s['id']; ?>" data-preco="<?php echo $s['preco']; ?>"><?php echo $s['nome']; ?></option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Valor</label>
                    <input type="number" name="valor" id="inputValor" step="0.01" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;" required>
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Horário</label>
                    <input type="time" name="horario" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;" required>
                </div>
            </div>

            <label style="display:block; margin-bottom:5px; font-weight:600;">Observação</label>
            <textarea name="obs" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ddd; border-radius:8px;"></textarea>

            <button type="submit" style="width:100%; padding:12px; background:#6366f1; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">Confirmar</button>
            <button type="button" onclick="document.getElementById('modalAdd').classList.remove('active')" style="width:100%; padding:12px; background:transparent; border:none; margin-top:5px; cursor:pointer; color:#666;">Cancelar</button>
        </form>
    </div>
</div>

<script>
function atualizaPreco() {
    var sel = document.getElementById('selServico');
    var opt = sel.options[sel.selectedIndex];
    var preco = opt.getAttribute('data-preco');
    if(preco) {
        document.getElementById('inputValor').value = preco;
    } else {
        document.getElementById('inputValor').value = '';
    }
}

// Preencher valor ao abrir o modal
document.getElementById('selServico').addEventListener('change', atualizaPreco);
document.getElementById('modalAdd').addEventListener('click', function(e) {
    if(e.target.classList.contains('modal-overlay') || e.target.classList.contains('fab')) {
        atualizaPreco();
    }
});

// Preencher valor ao abrir o modal (quando clicar no botão de adicionar)
document.querySelector('.fab').addEventListener('click', function() {
    setTimeout(atualizaPreco, 100); // Aguarda o modal abrir
});
</script>