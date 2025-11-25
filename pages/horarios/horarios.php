<?php
$pageTitle = 'Meus Horários';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Simulação de User ID
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- PROCESSAR SALVAMENTO ---
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
    0 => 'Domingo',
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado'
];

$toastStatus = $_GET['status'] ?? null;
?>

<style>
    .main-content {
        max-width: 480px;
        margin: 0 auto;
        padding: 16px 12px 100px 12px;
    }

    .actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .actions-bar h2 {
        margin: 0;
        font-size: 1.2rem;
    }
    .actions-bar p {
        margin: 3px 0 0 0;
        font-size: 0.85rem;
        color: var(--text-gray);
    }
    .btn-commercial {
        background: #e0e7ff;
        color: var(--primary);
        border: none;
        padding: 8px 16px;
        border-radius: 999px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
    }
    .btn-commercial i { font-size: 1rem; }

    /* switch */
    .switch { position: relative; display: inline-block; width: 42px; height: 22px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 22px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 2px; background-color: white; transition: .3s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--primary); }
    input:checked + .slider:before { transform: translateX(18px); }

    /* cards */
    .day-card {
        background: white;
        border-radius: 16px;
        padding: 12px 12px 14px 12px;
        margin-bottom: 10px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 8px 18px rgba(15,23,42,0.05);
        transition: .2s;
    }
    .day-card.closed {
        opacity: 0.6;
        background: #f8fafc;
    }
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .day-title {
        font-weight: 700;
        font-size: 0.98rem;
        color: var(--text-dark);
    }
    .day-status {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-gray);
    }

    .slots-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .time-slot {
        display: flex;
        align-items: center;
        gap: 6px;
        animation: fadeIn .25s;
    }
    .time-input {
        padding: 7px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.85rem;
        color: var(--text-dark);
        background: #fff;
        flex: 1;
        min-width: 0;
    }
    .separator {
        color: var(--text-gray);
        font-weight: 600;
        font-size: 0.8rem;
    }
    .btn-remove-slot {
        color: #ef4444;
        background: #fee2e2;
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: .2s;
        font-size: 0.9rem;
    }
    .btn-remove-slot:hover {
        background: #ef4444;
        color: white;
    }
    .btn-add-slot {
        background: none;
        border: 1px dashed #cbd5e1;
        color: var(--primary);
        padding: 8px;
        width: 100%;
        border-radius: 10px;
        cursor: pointer;
        margin-top: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: .2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .btn-add-slot i { font-size: 1rem; }
    .btn-add-slot:hover {
        background: #e0e7ff;
        border-color: var(--primary);
    }

    /* barra de salvar fixa */
    .save-bar {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 10px 12px 16px 12px;
        background: linear-gradient(to top, rgba(248,250,252,0.98), rgba(248,250,252,0.9));
        z-index: 900;
        display: flex;
        justify-content: center;
    }
    .fab-save {
        width: 100%;
        max-width: 480px;
        background: var(--success);
        color: #fff;
        padding: 12px 16px;
        border-radius: 999px;
        border: none;
        font-size: 0.95rem;
        font-weight: 700;
        box-shadow: 0 6px 16px rgba(34,197,94,0.45);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: transform .15s;
    }
    .fab-save:hover { transform: translateY(-1px); background: #16a34a; }
    .fab-save i { font-size: 1.1rem; }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>

<main class="main-content">
    <form method="POST" id="formHorarios">
        <div class="actions-bar">
            <div>
                <h2>Meus Horários</h2>
                <p>Defina os dias e intervalos em que você atende.</p>
            </div>
            <button type="button" class="btn-commercial" onclick="confirmarHorarioComercial()">
                <i class="bi bi-briefcase"></i>
                Comercial (seg–sex)
            </button>
        </div>

        <?php foreach ($diasSemana as $diaIndex => $diaNome): ?>
            <?php 
                $temHorarios = count($agenda[$diaIndex]) > 0;
                $isChecked   = $temHorarios ? 'checked' : '';
                $cardClass   = $temHorarios ? '' : 'closed';
                $statusText  = $temHorarios ? 'Aberto' : 'Fechado';
            ?>
            <div class="day-card <?php echo $cardClass; ?>" id="card-<?php echo $diaIndex; ?>">
                <div class="day-header">
                    <div class="day-title"><?php echo $diaNome; ?></div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="day-status" id="status-<?php echo $diaIndex; ?>"><?php echo $statusText; ?></span>
                        <label class="switch">
                            <input type="checkbox"
                                   name="dia_ativo[<?php echo $diaIndex; ?>]"
                                   id="toggle-<?php echo $diaIndex; ?>"
                                   onchange="toggleDia(<?php echo $diaIndex; ?>)"
                                   <?php echo $isChecked; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="day-body" id="body-<?php echo $diaIndex; ?>" style="<?php echo $temHorarios ? '' : 'display:none;'; ?>">
                    <div class="slots-container" id="slots-<?php echo $diaIndex; ?>">
                        <?php foreach ($agenda[$diaIndex] as $i => $horario): ?>
                            <div class="time-slot">
                                <input type="time" name="horarios[<?php echo $diaIndex; ?>][<?php echo $i; ?>][inicio]" class="time-input" value="<?php echo $horario['inicio']; ?>" required>
                                <span class="separator">até</span>
                                <input type="time" name="horarios[<?php echo $diaIndex; ?>][<?php echo $i; ?>][fim]" class="time-input" value="<?php echo $horario['fim']; ?>" required>
                                <button type="button" class="btn-remove-slot" onclick="removerSlot(this)"><i class="bi bi-trash"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add-slot" onclick="adicionarSlot(<?php echo $diaIndex; ?>)">
                        <i class="bi bi-plus-circle"></i> Adicionar intervalo
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </form>
</main>

<div class="save-bar">
    <button type="submit" form="formHorarios" class="fab-save">
        <i class="bi bi-check-lg"></i>
        Salvar horários
    </button>
</div>

<?php
// componentes reutilizáveis
include '../../includes/ui-confirm.php';
include '../../includes/ui-toast.php';
include '../../includes/footer.php';
?>

<script>
    function toggleDia(diaIndex) {
        const checkbox = document.getElementById(`toggle-${diaIndex}`);
        const card     = document.getElementById(`card-${diaIndex}`);
        const body     = document.getElementById(`body-${diaIndex}`);
        const status   = document.getElementById(`status-${diaIndex}`);
        const slots    = document.getElementById(`slots-${diaIndex}`);

        if (checkbox.checked) {
            card.classList.remove('closed');
            body.style.display = 'block';
            status.innerText = 'Aberto';

            if (slots.children.length === 0) {
                adicionarSlot(diaIndex, '09:00', '18:00');
            }
        } else {
            card.classList.add('closed');
            body.style.display = 'none';
            status.innerText = 'Fechado';
        }
    }

    function adicionarSlot(diaIndex, inicio = '', fim = '') {
        const container = document.getElementById(`slots-${diaIndex}`);
        const randId = Math.floor(Math.random() * 10000);

        const html = `
            <div class="time-slot">
                <input type="time" name="horarios[${diaIndex}][${randId}][inicio]" class="time-input" value="${inicio}" required>
                <span class="separator">até</span>
                <input type="time" name="horarios[${diaIndex}][${randId}][fim]" class="time-input" value="${fim}" required>
                <button type="button" class="btn-remove-slot" onclick="removerSlot(this)"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }

    function removerSlot(btn) {
        btn.closest('.time-slot').remove();
    }

    // abre modal de confirmação usando componente genérico
    function confirmarHorarioComercial() {
        AppConfirm.open({
            title: 'Aplicar horário comercial',
            message: 'Isso vai substituir seus horários atuais por:<br><strong>Segunda a sexta</strong> 08:00–12:00 e 13:00–18:00.<br><br>Sábado e domingo ficarão <strong>fechados</strong>.<br><br>Deseja continuar?',
            confirmText: 'Sim, aplicar',
            cancelText: 'Cancelar',
            type: 'success',
            onConfirm: aplicarHorarioComercial
        });
    }

    // aplica padrão comercial (somente depois do OK no modal)
    function aplicarHorarioComercial() {
        for (let i = 1; i <= 5; i++) {
            const checkbox = document.getElementById(`toggle-${i}`);
            if (!checkbox.checked) {
                checkbox.checked = true;
            }
            toggleDia(i);

            const container = document.getElementById(`slots-${i}`);
            container.innerHTML = '';
            adicionarSlot(i, '08:00', '12:00');
            adicionarSlot(i, '13:00', '18:00');
        }

        [0, 6].forEach(i => {
            const checkbox = document.getElementById(`toggle-${i}`);
            if (checkbox.checked) {
                checkbox.checked = false;
            }
            toggleDia(i);
        });

        AppToast.show('Padrão comercial aplicado. Não esqueça de salvar.', 'info');
    }

    // TOAST pós-salvamento
    <?php
    if ($toastStatus) {
        $msg  = '';
        $type = 'success';
        switch ($toastStatus) {
            case 'success':
                $msg  = 'Horários atualizados com sucesso.';
                $type = 'success';
                break;
            case 'error':
                $msg  = 'Erro ao salvar horários. Tente novamente.';
                $type = 'danger';
                break;
        }
        if ($msg):
    ?>
    window.addEventListener('DOMContentLoaded', function () {
        AppToast.show(<?php echo json_encode($msg); ?>, <?php echo json_encode($type); ?>);
    });
    <?php endif; } ?>
</script>
