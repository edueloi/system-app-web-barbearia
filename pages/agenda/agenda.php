<?php
// pages/agenda/agenda.php

// =========================================================
// 1. CONFIGURAÇÃO E SESSÃO
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// Tenta incluir o DB com verificação de caminho
$dbPath = '../../includes/db.php';
if (!file_exists($dbPath)) {
    // Fallback se a estrutura de pastas for diferente
    $dbPath = 'includes/db.php'; 
    if (!file_exists($dbPath)) die("Erro: db.php não encontrado.");
}
require_once $dbPath;

// Parâmetros de Entrada
$dataExibida = $_GET['data'] ?? date('Y-m-d');
$viewType    = $_GET['view'] ?? 'day'; // 'day', 'week', 'month'
$hoje        = date('Y-m-d');

// Função Auxiliar de Redirecionamento
function redirect($data, $view) {
    header("Location: agenda.php?data=" . urlencode($data) . "&view=" . $view);
    exit;
}

// =========================================================
// 2. AÇÕES DO BACKEND (POST/GET)
// =========================================================

try {
    // 2.1 Excluir Agendamento
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        redirect($dataExibida, $viewType);
    }

    // 2.2 Mudar Status
    if (isset($_GET['status'], $_GET['id'])) {
        $stmt = $pdo->prepare("UPDATE agendamentos SET status=? WHERE id=? AND user_id=?");
        $stmt->execute([$_GET['status'], (int)$_GET['id'], $userId]);
        redirect($dataExibida, $viewType);
    }

    // 2.3 Novo Agendamento (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_agendamento'])) {
        $cliente   = trim($_POST['cliente']);
        $servicoId = $_POST['servico_id'];
        $valor     = str_replace(',', '.', $_POST['valor']);
        $horario   = $_POST['horario'];
        $obs       = trim($_POST['obs']);
        $dataAg    = $_POST['data_agendamento'] ?? $dataExibida;

        // Validação de segurança: Impede data passada no backend
        if ($dataAg < $hoje) { 
            $dataAg = $hoje; 
        }

        // Busca nome do serviço
        $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id=? AND user_id=?");
        $stmt->execute([$servicoId, $userId]);
        $nomeServico = $stmt->fetchColumn() ?: 'Serviço';

        if ($cliente && $horario) {
            $sql = "INSERT INTO agendamentos (user_id, cliente_nome, servico, valor, data_agendamento, horario, observacoes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmado')";
            $pdo->prepare($sql)->execute([$userId, $cliente, $nomeServico, $valor, $dataAg, $horario, $obs]);
        }
        // Redireciona para o dia que foi agendado para o usuário ver
        redirect($dataAg, 'day');
    }

} catch (PDOException $e) {
    // Captura erros de banco (como o Locked) e mostra amigável
    die("Erro no banco de dados (Tente novamente em alguns segundos): " . $e->getMessage());
}

// =========================================================
// 3. CONSULTA DE DADOS (VIEW LOGIC)
// =========================================================

$agendamentos = [];
$faturamento = 0;
$diasComAgendamento = []; // Para marcar pontinhos no calendário mensal

// Define intervalo de datas baseado na View
if ($viewType === 'month') {
    $start = date('Y-m-01', strtotime($dataExibida));
    $end   = date('Y-m-t', strtotime($dataExibida));
    
    // Título do Mês
    setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
    $meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $tituloData = $meses[(int)date('m', strtotime($dataExibida))] . ' ' . date('Y', strtotime($dataExibida));

} elseif ($viewType === 'week') {
    $ts = strtotime($dataExibida);
    $diaSemana = date('w', $ts);
    $start = date('Y-m-d', strtotime("-$diaSemana days", $ts));
    $end   = date('Y-m-d', strtotime("+6 days", strtotime($start)));
    $tituloData = date('d/m', strtotime($start)) . ' a ' . date('d/m', strtotime($end));

} else {
    // Dia
    $start = $dataExibida;
    $end   = $dataExibida;
    $diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    $tituloData = $diasSemana[date('w', strtotime($dataExibida))] . ', ' . date('d/m', strtotime($dataExibida));
}

// Busca principal
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE user_id = ? AND data_agendamento BETWEEN ? AND ? ORDER BY data_agendamento ASC, horario ASC");
$stmt->execute([$userId, $start, $end]);
$raw = $stmt->fetchAll();

// Listas para os Modais
$servicos = $pdo->query("SELECT * FROM servicos WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();
$clientes = $pdo->query("SELECT nome, telefone FROM clientes WHERE user_id=$userId ORDER BY nome ASC")->fetchAll();

// Processamento dos dados (Correção de Preço 0 e Organização)
foreach ($raw as &$r) {
    // Se o valor for 0, tenta pegar o preço padrão do serviço
    if ((float)$r['valor'] <= 0) {
        foreach ($servicos as $s) {
            if ($s['nome'] === $r['servico']) {
                $r['valor'] = $s['preco']; 
                break;
            }
        }
    }
}
unset($r); // Limpa referência

// Organiza array final baseado na View
if ($viewType === 'day') {
    $agendamentos = $raw;
    foreach ($raw as $ag) if(($ag['status']??'')!=='Cancelado') $faturamento += $ag['valor'];

} elseif ($viewType === 'week') {
    for($i=0; $i<=6; $i++) {
        $d = date('Y-m-d', strtotime("+$i days", strtotime($start)));
        $agendamentos[$d] = [];
    }
    foreach ($raw as $ag) {
        $agendamentos[$ag['data_agendamento']][] = $ag;
        if(($ag['status']??'')!=='Cancelado') $faturamento += $ag['valor'];
    }

} elseif ($viewType === 'month') {
    foreach ($raw as $ag) {
        $diasComAgendamento[$ag['data_agendamento']] = true;
        if(($ag['status']??'')!=='Cancelado') $faturamento += $ag['valor'];
    }
}

// Datas para navegação (setas < >)
$mod = ($viewType==='month') ? 'month' : (($viewType==='week') ? 'week' : 'day');
$dataAnt = date('Y-m-d', strtotime($dataExibida . " -1 $mod"));
$dataPro = date('Y-m-d', strtotime($dataExibida . " +1 $mod"));

// =========================================================
// 4. ESTRUTURA HTML E CSS
// =========================================================
$pageTitle = 'Minha Agenda';
include '../../includes/header.php';
include '../../includes/menu.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* --- ESTILOS GERAIS --- */
    :root {
        --primary: #6366f1;
        --bg-body: #f8fafc;
        --text-main: #0f172a;
        --text-light: #64748b;
        --white: #ffffff;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
        --radius: 16px;
    }
    body { background-color: var(--bg-body); font-family: 'Inter', sans-serif; padding-bottom: 100px; margin: 0; }

    /* --- HEADER FLUTUANTE (GLASSMORPHISM) --- */
    .app-header {
        position: sticky; top: 60px; z-index: 50;
        background: rgba(248, 250, 252, 0.95); backdrop-filter: blur(10px);
        padding: 10px 16px; border-bottom: 1px solid #e2e8f0;
    }

    /* Botões de Visualização (Dia/Semana/Mês) */
    .view-control {
        display: flex; background: #e2e8f0; padding: 4px; border-radius: 12px; margin-bottom: 16px;
    }
    .view-opt {
        flex: 1; text-align: center; padding: 8px; border-radius: 9px;
        font-size: 0.85rem; font-weight: 600; color: var(--text-light);
        text-decoration: none; transition: 0.2s;
    }
    .view-opt.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

    /* Navegador de Datas */
    .date-nav-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .btn-circle {
        width: 40px; height: 40px; border-radius: 50%; background: var(--white);
        border: 1px solid #e2e8f0; color: var(--text-main); display: flex;
        align-items: center; justify-content: center; cursor: pointer; text-decoration: none;
    }
    .date-picker-trigger { position: relative; text-align: center; }
    .current-date-label { font-size: 1.1rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 6px; }
    .hidden-date-input { position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor: pointer; }

    /* Card Faturamento */
    .finance-card {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white; padding: 12px 20px; border-radius: var(--radius);
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 8px 20px -5px rgba(99, 102, 241, 0.4);
    }
    .fin-value { font-size: 1.1rem; font-weight: 700; }

    /* --- CONTEÚDO E CARDS --- */
    .content-area { padding: 16px; }

    .appt-card {
        background: var(--white); border-radius: var(--radius); padding: 16px;
        margin-bottom: 12px; position: relative; display: flex; gap: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;
    }
    .time-col { display: flex; flex-direction: column; align-items: center; min-width: 50px; border-right: 1px solid #f1f5f9; padding-right: 14px; justify-content: center; }
    .time-val { font-size: 1.1rem; font-weight: 800; color: var(--text-main); line-height: 1; }
    .time-min { font-size: 0.8rem; color: var(--text-light); font-weight: 500; }
    
    .info-col { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .client-name { font-weight: 700; color: var(--text-main); font-size: 1rem; margin-bottom: 4px; }
    .service-row { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: var(--text-light); }
    .price-tag { background: #eef2ff; color: var(--primary); font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; }

    /* Status (Bolinhas) */
    .status-badge { position: absolute; top: 12px; right: 12px; width: 10px; height: 10px; border-radius: 50%; }
    .st-Confirmado { background: #10b981; box-shadow: 0 0 0 3px #d1fae5; }
    .st-Pendente { background: #f59e0b; box-shadow: 0 0 0 3px #fef3c7; }
    .st-Cancelado { background: #ef4444; box-shadow: 0 0 0 3px #fee2e2; }

    /* --- CALENDÁRIO MENSAL --- */
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-top: 10px; }
    .week-day-name { text-align: center; font-size: 0.75rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; margin-bottom: 5px; }
    .day-cell { aspect-ratio: 1; background: var(--white); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 600; color: var(--text-main); text-decoration: none; border: 1px solid transparent; position: relative; }
    .day-cell.today { border-color: var(--primary); color: var(--primary); background: #eef2ff; }
    .day-cell.empty { background: transparent; }
    .has-event-dot { width: 5px; height: 5px; background: var(--primary); border-radius: 50%; position: absolute; bottom: 6px; }

    /* --- SEMANAL --- */
    .week-header { font-size: 0.9rem; font-weight: 700; color: var(--text-light); margin: 20px 0 8px 0; display: flex; align-items: center; gap: 8px; }
    .week-header::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }

    /* --- BOTÃO FLUTUANTE (FAB) --- */
    .fab-add { position: fixed; bottom: 24px; right: 24px; width: 56px; height: 56px; background: var(--text-main); color: white; border-radius: 18px; border: none; font-size: 1.8rem; box-shadow: 0 10px 20px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; z-index: 100; cursor: pointer; transition: 0.2s; }
    .fab-add:active { transform: scale(0.9); }

    /* --- MODAIS E FORMULÁRIOS --- */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: none; align-items: flex-end; justify-content: center; backdrop-filter: blur(2px); }
    .modal-overlay.active { display: flex; animation: fadeIn 0.2s; }
    .sheet-modal { background: white; width: 100%; max-width: 500px; border-radius: 24px 24px 0 0; padding: 24px; animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); max-height: 90vh; overflow-y: auto; }
    @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .form-group { margin-bottom: 16px; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 6px; display: block; }
    .form-input { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; background: #f8fafc; outline: none; box-sizing: border-box; }
    .form-input:focus { border-color: var(--primary); background: white; }
    .btn-main { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 14px; font-weight: 700; font-size: 1rem; margin-top: 10px; cursor: pointer; }
    
    .empty-state { text-align: center; padding: 40px 20px; color: var(--text-light); }
    .action-list { list-style: none; padding: 0; }
    .action-item { display: flex; align-items: center; gap: 14px; padding: 16px 0; border-bottom: 1px solid #f1f5f9; color: var(--text-main); text-decoration: none; font-size: 1rem; cursor: pointer; }
    .action-item.danger { color: #ef4444; }
</style>

<div class="app-header">
    <div class="view-control">
        <a href="?data=<?php echo $dataExibida; ?>&view=day" class="view-opt <?php echo $viewType==='day'?'active':''; ?>">Dia</a>
        <a href="?data=<?php echo $dataExibida; ?>&view=week" class="view-opt <?php echo $viewType==='week'?'active':''; ?>">Semana</a>
        <a href="?data=<?php echo $dataExibida; ?>&view=month" class="view-opt <?php echo $viewType==='month'?'active':''; ?>">Mês</a>
    </div>

    <div class="date-nav-row">
        <a href="?data=<?php echo $dataAnt; ?>&view=<?php echo $viewType; ?>" class="btn-circle"><i class="bi bi-chevron-left"></i></a>
        <div class="date-picker-trigger">
            <div class="current-date-label">
                <?php echo $tituloData; ?> <i class="bi bi-caret-down-fill" style="font-size:0.7rem; color:var(--primary);"></i>
            </div>
            <input type="date" class="hidden-date-input" value="<?php echo $dataExibida; ?>" onchange="window.location.href='?view=<?php echo $viewType; ?>&data='+this.value">
        </div>
        <a href="?data=<?php echo $dataPro; ?>&view=<?php echo $viewType; ?>" class="btn-circle"><i class="bi bi-chevron-right"></i></a>
    </div>

    <div class="finance-card">
        <span class="fin-label">Faturamento</span>
        <span class="fin-value">R$ <?php echo number_format($faturamento, 2, ',', '.'); ?></span>
    </div>
</div>

<div class="content-area">

    <?php if ($viewType === 'month'): ?>
        <div class="calendar-grid">
            <div class="week-day-name">D</div><div class="week-day-name">S</div><div class="week-day-name">T</div>
            <div class="week-day-name">Q</div><div class="week-day-name">Q</div><div class="week-day-name">S</div><div class="week-day-name">S</div>
            
            <?php
            // Lógica de Renderização do Calendário
            $firstDayMonth = date('Y-m-01', strtotime($dataExibida));
            $daysInMonth   = date('t', strtotime($dataExibida));
            $startPadding  = date('w', strtotime($firstDayMonth));

            // Espaços vazios antes do dia 1
            for($k=0; $k<$startPadding; $k++) { echo '<div class="day-cell empty"></div>'; }

            // Dias
            for($day=1; $day<=$daysInMonth; $day++) {
                $currentDate = date('Y-m-', strtotime($dataExibida)) . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isToday = ($currentDate === $hoje) ? 'today' : '';
                $hasEvent = isset($diasComAgendamento[$currentDate]) ? '<div class="has-event-dot"></div>' : '';

                echo "<a href='?view=day&data={$currentDate}' class='day-cell {$isToday}'>
                        {$day}
                        {$hasEvent}
                      </a>";
            }
            ?>
        </div>
    
    <?php elseif ($viewType === 'week'): ?>
        <?php foreach ($agendamentos as $dia => $lista): ?>
            <div class="week-header">
                <?php echo date('d/m', strtotime($dia)) . ' • ' . ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', strtotime($dia))]; ?>
            </div>
            <?php if(count($lista) > 0): ?>
                <?php foreach($lista as $ag) renderCard($ag, $clientes); ?>
            <?php else: ?>
                <div style="font-size:0.85rem; color:#cbd5e1; font-style:italic; padding-left:10px;">Livre</div>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php else: ?>
        <?php if (count($agendamentos) > 0): ?>
            <?php foreach($agendamentos as $ag) renderCard($ag, $clientes); ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar4-week" style="font-size:3rem; opacity:0.2;"></i>
                <p>Agenda livre neste dia.</p>
                <button onclick="openModal()" style="color:var(--primary); background:none; border:none; font-weight:700;">+ Adicionar Agendamento</button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<button class="fab-add" onclick="openModal()"><i class="bi bi-plus"></i></button>

<div class="modal-overlay" id="modalAdd">
    <div class="sheet-modal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-size:1.2rem;">Novo Agendamento</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem;"><i class="bi bi-x"></i></button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="novo_agendamento" value="1">
            
            <div class="form-group">
                <label class="form-label">Data</label>
                <input type="date" name="data_agendamento" value="<?php echo ($viewType==='day'?$dataExibida:$hoje); ?>" min="<?php echo $hoje; ?>" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Cliente</label>
                <input type="text" name="cliente" list="dlClientes" class="form-input" placeholder="Buscar..." required>
                <datalist id="dlClientes">
                    <?php foreach($clientes as $c) echo "<option value='".htmlspecialchars($c['nome'])."'>"; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label class="form-label">Serviço</label>
                <select name="servico_id" id="selServico" class="form-input" onchange="atualizaPreco()" required>
                    <option value="">Selecione...</option>
                    <?php foreach($servicos as $s) echo "<option value='{$s['id']}' data-preco='{$s['preco']}'>".htmlspecialchars($s['nome'])."</option>"; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Horário</label>
                    <input type="time" name="horario" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor (R$)</label>
                    <input type="number" name="valor" id="inputValor" step="0.01" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Observação</label>
                <textarea name="obs" rows="2" class="form-input"></textarea>
            </div>

            <button type="submit" class="btn-main">Salvar</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="actionSheet" style="align-items:flex-end;">
    <div class="sheet-modal" style="border-radius:24px 24px 0 0; padding-bottom:40px;">
        <h3 id="sheetClientName" style="margin-bottom:20px;">Opções</h3>
        <div class="action-list">
            <a href="#" id="actConfirm" class="action-item"><i class="bi bi-check-circle" style="color:#10b981;"></i> Confirmar</a>
            <a href="#" id="actWhatsapp" target="_blank" class="action-item"><i class="bi bi-whatsapp" style="color:#25D366;"></i> WhatsApp</a>
            <a href="#" id="actNota" class="action-item"><i class="bi bi-receipt" style="color:#6366f1;"></i> Emitir Nota</a>
            <a href="#" id="actCancel" class="action-item"><i class="bi bi-x-circle" style="color:#f59e0b;"></i> Cancelar</a>
            <a href="#" id="actDelete" class="action-item danger"><i class="bi bi-trash"></i> Excluir</a>
        </div>
        <button onclick="document.getElementById('actionSheet').classList.remove('active')" class="btn-main" style="background:#f1f5f9; color:#0f172a; margin-top:20px;">Fechar</button>
    </div>
</div>

<?php
// Função para renderizar o Card HTML
function renderCard($ag, $clientes) {
    $stClass = 'st-' . ($ag['status'] ?? 'Pendente');
    $tel = '';
    // Encontra telefone do cliente
    foreach($clientes as $c) if($c['nome']===$ag['cliente_nome']) { $tel=$c['telefone']; break; }
    
    // Valor formatado
    $valStr = number_format($ag['valor'], 2, ',', '.');
    
    // Dados para o JavaScript
    $jsonData = htmlspecialchars(json_encode([
        'id'=>$ag['id'], 
        'cliente'=>$ag['cliente_nome'], 
        'status'=>$ag['status'],
        'tel'=>$tel, 
        'serv'=>$ag['servico'], 
        'val'=>$valStr,
        'data'=>date('d/m', strtotime($ag['data_agendamento'])), 
        'hora'=>date('H:i', strtotime($ag['horario']))
    ]));

    echo "
    <div class='appt-card'>
        <div class='status-badge {$stClass}'></div>
        <div class='time-col'>
            <span class='time-val'>".date('H', strtotime($ag['horario']))."</span>
            <span class='time-min'>".date('i', strtotime($ag['horario']))."</span>
        </div>
        <div class='info-col'>
            <div class='client-name'>{$ag['cliente_nome']}</div>
            <div class='service-row'>
                {$ag['servico']}
                <span class='price-tag'>R$ {$valStr}</span>
            </div>
        </div>
        <button onclick='openActions($jsonData)' style='background:none; border:none; font-size:1.2rem; color:var(--text-light); padding:0 0 0 10px;'><i class='bi bi-three-dots-vertical'></i></button>
    </div>";
}
?>

<script>
    // --- LÓGICA DE ABERTURA DE MODAIS ---
    function openModal() { document.getElementById('modalAdd').classList.add('active'); }
    function closeModal() { document.getElementById('modalAdd').classList.remove('active'); }
    
    // Atualiza preço automático ao escolher serviço
    function atualizaPreco() {
        const sel = document.getElementById('selServico');
        const opt = sel.options[sel.selectedIndex];
        if(opt && opt.dataset.preco) document.getElementById('inputValor').value = opt.dataset.preco;
    }

    // --- LÓGICA DO MENU DE AÇÕES ---
    function openActions(data) {
        document.getElementById('sheetClientName').innerText = data.cliente;
        const base = `agenda.php?data=<?php echo $dataExibida; ?>&view=<?php echo $viewType; ?>&id=${data.id}`;
        
        // Ação: Confirmar
        document.getElementById('actConfirm').onclick = () => {
            if(data.tel) {
                const msg = `Olá ${data.cliente.split(' ')[0]}, confirmando seu horário de ${data.serv} dia ${data.data} às ${data.hora}. Valor: R$ ${data.val}. Tudo certo?`;
                window.open(`https://wa.me/55${data.tel.replace(/\D/g,'')}?text=${encodeURIComponent(msg)}`, '_blank');
            }
            window.location.href = base + '&status=Confirmado';
        };
        
        // Ação: Links simples
        document.getElementById('actCancel').href = base + '&status=Cancelado';
        document.getElementById('actDelete').onclick = () => { if(confirm('Tem certeza que deseja excluir?')) window.location.href = base + '&delete=1'; };
        document.getElementById('actNota').href = `nota.php?id=${data.id}`;
        
        // Ação: WhatsApp avulso
        if(data.tel) {
            document.getElementById('actWhatsapp').href = `https://wa.me/55${data.tel.replace(/\D/g,'')}?text=Olá ${data.cliente.split(' ')[0]}`;
            document.getElementById('actWhatsapp').style.display = 'flex';
        } else {
            document.getElementById('actWhatsapp').style.display = 'none';
        }

        document.getElementById('actionSheet').classList.add('active');
    }
</script>