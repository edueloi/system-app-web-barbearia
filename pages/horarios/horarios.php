<?php
// --- PROCESSAR SALVAMENTO (Lógica Mantida) ---
include '../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->prepare("DELETE FROM horarios_atendimento WHERE user_id = ?")->execute([$userId]);

        if (isset($_POST['horarios']) && is_array($_POST['horarios'])) {
            $stmt = $pdo->prepare("INSERT INTO horarios_atendimento (user_id, dia_semana, inicio, fim) VALUES (?, ?, ?, ?)");
            foreach ($_POST['horarios'] as $dia => $slots) {
                if (isset($_POST['dia_ativo'][$dia])) {
                    foreach ($slots as $slot) {
                        if (!empty($slot['inicio']) && !empty($slot['fim'])) {
                            $stmt->execute([$userId, $dia, $slot['inicio'], $slot['fim']]);
                        }
                    }
                }
            }
        }

        header('Location: horarios.php?status=success');
        exit;
    } catch (Exception $e) {
        header('Location: horarios.php?status=error');
        exit;
    }
}

// --- BUSCAR HORÁRIOS ATUAIS ---
$stmt = $pdo->prepare("SELECT * FROM horarios_atendimento WHERE user_id = ? ORDER BY dia_semana ASC, inicio ASC");
$stmt->execute([$userId]);
$registros = $stmt->fetchAll();

$agenda = array_fill(0, 7, []);
foreach ($registros as $reg) {
    $agenda[$reg['dia_semana']][] = ['inicio' => $reg['inicio'], 'fim' => $reg['fim']];
}

$diasSemana = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    0 => 'Domingo'
];

$toastStatus = $_GET['status'] ?? null;
$pageTitle = 'Configurar Horários';

include '../../includes/header.php';
include '../../includes/menu.php';
?>

<style>
    :root {
        --primary-color: #4f46e5; /* Indigo */
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --input-bg: #f1f5f9;
    }

    body { background-color: var(--bg-page); }

    .main-wrapper {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px 16px 100px 16px;
    }

    /* Cabeçalho da Página */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .page-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
    }
    .page-header p {
        margin: 4px 0 0;
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .btn-auto-fill {
        background: #eef2ff;
        color: var(--primary-color);
        border: 1px solid transparent;
        padding: 8px 16px;
        border-radius: 99px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-auto-fill:hover { background: #e0e7ff; border-color: #c7d2fe; }

    /* Cards dos Dias */
    .day-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    /* Estado Fechado */
    .day-card.closed {
        background: #f8fafc;
        border-color: transparent;
        box-shadow: none;
        opacity: 0.8;
    }
    .day-card.closed .day-title { color: #94a3b8; font-weight: 500; }
    
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer; /* Permite clicar no header para ativar */
    }

    .day-info { display: flex; align-items: center; gap: 10px; }
    .day-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-main);
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 6px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-open { color: #16a34a; background: #dcfce7; }
    .status-closed { color: #94a3b8; background: #f1f5f9; }

    /* IOS Switch */
    .switch {
        position: relative; display: inline-block; width: 44px; height: 24px;
        flex-shrink: 0;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1; transition: .3s; border-radius: 24px;
    }
    .slider:before {
        position: absolute; content: ""; height: 20px; width: 20px; left: 2px; bottom: 2px;
        background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    input:checked + .slider { background-color: var(--primary-color); }
    input:checked + .slider:before { transform: translateX(20px); }

    /* Área dos Slots (Horários) */
    .day-body {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px dashed var(--border-color);
        animation: slideDown 0.3s ease-out;
    }

    .slots-list { display: flex; flex-direction: column; gap: 10px; }

    /* O Visual "Cápsula" do Horário */
    .time-slot-row {
        display: flex;
        align-items: center;
        gap: 8px;
        animation: fadeIn 0.3s;
    }

    .time-capsule {
        flex: 1;
        background: var(--input-bg);
        border-radius: 10px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid transparent;
        transition: 0.2s;
    }
    .time-capsule:focus-within {
        background: #fff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .time-input {
        background: transparent;
        border: none;
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--text-main);
        width: 100%;
        text-align: center;
        outline: none;
        cursor: pointer;
    }
    
    /* Separador visual (seta ou traço) */
    .time-separator { color: var(--text-muted); font-size: 0.8rem; padding: 0 6px; }

    .btn-remove {
        width: 36px; height: 36px;
        border-radius: 10px;
        border: none;
        background: transparent;
        color: var(--text-muted);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-remove:hover { background: #fee2e2; color: #ef4444; }

    .btn-add {
        width: 100%;
        margin-top: 12px;
        padding: 10px;
        background: transparent;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: 0.2s;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .btn-add:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        background: #eef2ff;
    }

    /* Barra Flutuante de Salvar */
    .sticky-save-bar {
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        width: 80%; max-width: 500px;
        background: #1e293b;
        color: white;
        padding: 12px 24px;
        border-radius: 99px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        display: flex; justify-content: space-between; align-items: center;
        z-index: 1000;
        animation: floatUp 0.5s ease-out;
    }
    .save-text { font-size: 0.9rem; font-weight: 500; opacity: 0.9; }
    .btn-save-action {
        background: #4f46e5; color: white; border: none;
        padding: 8px 20px; border-radius: 99px; font-weight: 700;
        cursor: pointer; transition: 0.2s;
    }
    .btn-save-action:hover { background: #4338ca; transform: scale(1.05); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideDown { from { opacity: 0; max-height: 0; } to { opacity: 1; max-height: 500px; } }
    @keyframes floatUp { from { transform: translate(-50%, 100%); } to { transform: translate(-50%, 0); } }

    /* Ajuste Mobile para Inputs de Hora */
    @media(max-width: 400px) {
        .time-input { font-size: 0.85rem; }
        .time-capsule { padding: 8px; }
    }
</style>

<div class="main-wrapper">
    
    <div class="page-header">
        <div>
            <h2>Configurar Horários</h2>
            <p>Defina sua disponibilidade semanal.</p>
        </div>
        <button type="button" class="btn-auto-fill" onclick="confirmarHorarioComercial()">
            <i class="bi bi-magic"></i> Padrão Comercial
        </button>
    </div>

    <form method="POST" id="formHorarios">
        <?php foreach ($diasSemana as $diaIndex => $diaNome): ?>
            <?php 
                $temHorarios = count($agenda[$diaIndex]) > 0;
                $isChecked   = $temHorarios ? 'checked' : '';
                $cardClass   = $temHorarios ? '' : 'closed';
            ?>
            
            <div class="day-card <?php echo $cardClass; ?>" id="card-<?php echo $diaIndex; ?>">
                
                <div class="day-header">
                    <div class="day-info" onclick="triggerToggle(<?php echo $diaIndex; ?>)">
                        <div class="day-title"><?php echo $diaNome; ?></div>
                        <span id="badge-<?php echo $diaIndex; ?>" class="status-badge <?php echo $temHorarios ? 'status-open' : 'status-closed'; ?>">
                            <?php echo $temHorarios ? 'Aberto' : 'Fechado'; ?>
                        </span>
                    </div>

                    <label class="switch">
                        <input type="checkbox" 
                               name="dia_ativo[<?php echo $diaIndex; ?>]" 
                               id="toggle-<?php echo $diaIndex; ?>"
                               onchange="toggleDia(<?php echo $diaIndex; ?>)"
                               <?php echo $isChecked; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="day-body" id="body-<?php echo $diaIndex; ?>" style="<?php echo $temHorarios ? '' : 'display:none;'; ?>">
                    
                    <div class="slots-list" id="slots-<?php echo $diaIndex; ?>">
                        <?php foreach ($agenda[$diaIndex] as $i => $horario): ?>
                            <div class="time-slot-row">
                                <div class="time-capsule">
                                    <input type="time" name="horarios[<?php echo $diaIndex; ?>][<?php echo $i; ?>][inicio]" 
                                           class="time-input" value="<?php echo $horario['inicio']; ?>" required>
                                    <i class="bi bi-arrow-right-short time-separator"></i>
                                    <input type="time" name="horarios[<?php echo $diaIndex; ?>][<?php echo $i; ?>][fim]" 
                                           class="time-input" value="<?php echo $horario['fim']; ?>" required>
                                </div>
                                <button type="button" class="btn-remove" onclick="removerSlot(this)" title="Remover intervalo">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add" onclick="adicionarSlot(<?php echo $diaIndex; ?>)">
                        <i class="bi bi-plus-lg"></i> Adicionar Intervalo
                    </button>
                </div>

            </div>
        <?php endforeach; ?>
    </form>
</div>

<div class="sticky-save-bar">
    <span class="save-text">Alterações pendentes?</span>
    <button type="submit" form="formHorarios" class="btn-save-action">
        Salvar Tudo
    </button>
</div>

<?php
include '../../includes/ui-confirm.php';
include '../../includes/ui-toast.php';
include '../../includes/footer.php';
?>

<script>
    // Permite clicar no texto "Segunda-feira" para ativar o switch
    function triggerToggle(idx) {
        const toggle = document.getElementById(`toggle-${idx}`);
        toggle.click(); 
    }

    function toggleDia(diaIndex) {
        const checkbox = document.getElementById(`toggle-${diaIndex}`);
        const card     = document.getElementById(`card-${diaIndex}`);
        const body     = document.getElementById(`body-${diaIndex}`);
        const badge    = document.getElementById(`badge-${diaIndex}`);
        const slots    = document.getElementById(`slots-${diaIndex}`);

        if (checkbox.checked) {
            card.classList.remove('closed');
            body.style.display = 'block';
            badge.innerText = 'Aberto';
            badge.className = 'status-badge status-open';

            // Se não houver slots ao abrir, cria um padrão
            if (slots.children.length === 0) {
                adicionarSlot(diaIndex, '09:00', '18:00');
            }
        } else {
            card.classList.add('closed');
            body.style.display = 'none';
            badge.innerText = 'Fechado';
            badge.className = 'status-badge status-closed';
        }
    }

    function adicionarSlot(diaIndex, inicio = '', fim = '') {
        const container = document.getElementById(`slots-${diaIndex}`);
        const randId = Math.floor(Math.random() * 100000);

        // HTML do Slot com visual "Cápsula"
        const html = `
            <div class="time-slot-row">
                <div class="time-capsule">
                    <input type="time" name="horarios[${diaIndex}][${randId}][inicio]" 
                           class="time-input" value="${inicio}" required>
                    <i class="bi bi-arrow-right-short time-separator"></i>
                    <input type="time" name="horarios[${diaIndex}][${randId}][fim]" 
                           class="time-input" value="${fim}" required>
                </div>
                <button type="button" class="btn-remove" onclick="removerSlot(this)">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        
        // Foca no primeiro input recém criado para agilizar
        const newInputs = container.lastElementChild.querySelectorAll('input');
        if(newInputs[0] && !inicio) newInputs[0].focus(); 
    }

    function removerSlot(btn) {
        const row = btn.closest('.time-slot-row');
        row.style.opacity = '0';
        row.style.transform = 'translateX(10px)';
        setTimeout(() => row.remove(), 200);
    }

    // Modal Comercial (igual anterior)
    function confirmarHorarioComercial() {
        AppConfirm.open({
            title: 'Aplicar Padrão Comercial',
            message: 'Isso definirá Seg-Sex das 08:00 às 18:00 (com almoço) e fechará o fim de semana. Continuar?',
            confirmText: 'Aplicar',
            type: 'info',
            onConfirm: aplicarHorarioComercial
        });
    }

    function aplicarHorarioComercial() {
        // Seg a Sex (1 a 5)
        for (let i = 1; i <= 5; i++) {
            const checkbox = document.getElementById(`toggle-${i}`);
            if (!checkbox.checked) checkbox.checked = true;
            toggleDia(i);

            const container = document.getElementById(`slots-${i}`);
            container.innerHTML = ''; // Limpa
            adicionarSlot(i, '08:00', '12:00');
            adicionarSlot(i, '13:00', '18:00');
        }

        // Sab e Dom (6 e 0)
        [0, 6].forEach(i => {
            const checkbox = document.getElementById(`toggle-${i}`);
            if (checkbox.checked) checkbox.checked = false;
            toggleDia(i);
        });

        AppToast.show('Horário comercial aplicado!', 'success');
    }

    // Mensagens de Sucesso/Erro PHP
    <?php if ($toastStatus): ?>
        window.addEventListener('DOMContentLoaded', () => {
            const msg = "<?php echo ($toastStatus === 'success') ? 'Horários salvos com sucesso!' : 'Erro ao salvar.'; ?>";
            const type = "<?php echo ($toastStatus === 'success') ? 'success' : 'danger'; ?>";
            AppToast.show(msg, type);
        });
    <?php endif; ?>
</script>