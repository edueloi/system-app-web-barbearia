<?php
require_once __DIR__ . '/../../includes/config.php';
// --- PROCESSAR SALVAMENTO (Lógica Mantida) ---
include '../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];


// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
$horariosUrl = $isProd
    ? '/horarios' // em produção usa rota amigável
    : '/karen_site/controle-salao/pages/horarios/horarios.php';

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

        header("Location: {$horariosUrl}?status=success");
        exit;
    } catch (Exception $e) {
        header("Location: {$horariosUrl}?status=error");
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
        --primary-soft: #eef2ff;
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --input-bg: #f1f5f9;
        --shadow-soft: 0 10px 25px rgba(15,23,42,0.06);
    }

    body {
        background: radial-gradient(circle at top, #e0e7ff 0, #f8fafc 45%, #f8fafc 100%);
        font-family: -apple-system, BlinkMacSystemFont, "Inter", system-ui, sans-serif;
        font-size: 12px; /* letras menores */
        color: var(--text-main);
    }

    .main-wrapper {
        max-width: 520px;
        margin: 0 auto;
        padding: 18px 14px 90px 14px;
    }

    /* Cabeçalho da Página */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 18px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .page-header h2 {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
        letter-spacing: -0.02em;
    }
    .page-header p {
        margin: 3px 0 0;
        color: var(--text-muted);
        font-size: 0.78rem;
    }

    .btn-auto-fill {
        background: var(--primary-soft);
        color: var(--primary-color);
        border: 1px solid transparent;
        padding: 6px 14px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.78rem;
        cursor: pointer;
        transition: 0.18s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 5px rgba(79,70,229,0.18);
        white-space: nowrap;
    }
    .btn-auto-fill:hover {
        background: #e0e7ff;
        border-color: #c7d2fe;
        transform: translateY(-1px);
    }

    /* Cards dos Dias */
    .day-card {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 12px 12px 12px 12px;
        margin-bottom: 10px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.22);
        transition: all 0.22s ease;
    }

    /* Estado Fechado */
    .day-card.closed {
        background: rgba(248,250,252,0.85);
        border-color: transparent;
        box-shadow: none;
        opacity: 0.9;
    }
    .day-card.closed .day-title {
        color: #94a3b8;
        font-weight: 500;
    }
    
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer; /* Permite clicar no header para ativar */
    }

    .day-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .day-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-main);
    }
    .status-badge {
        font-size: 0.68rem;
        padding: 2px 8px;
        border-radius: 999px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .status-open {
        color: #16a34a;
        background: #dcfce7;
    }
    .status-closed {
        color: #94a3b8;
        background: #e5e7eb;
    }

    /* IOS Switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 22px;
        flex-shrink: 0;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .25s;
        border-radius: 999px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .25s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(15,23,42,0.35);
    }
    input:checked + .slider { background-color: var(--primary-color); }
    input:checked + .slider:before { transform: translateX(18px); }

    /* Área dos Slots (Horários) */
    .day-body {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed var(--border-color);
        animation: slideDown 0.22s ease-out;
    }

    .slots-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    /* O Visual "Cápsula" do Horário */
    .time-slot-row {
        display: flex;
        align-items: center;
        gap: 6px;
        animation: fadeIn 0.22s;
    }

    .time-capsule {
        flex: 1;
        background: var(--input-bg);
        border-radius: 999px;
        padding: 6px 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid transparent;
        transition: 0.18s;
    }
    .time-capsule:focus-within {
        background: #fff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(79,70,229,0.18);
    }

    .time-input {
        background: transparent;
        border: none;
        font-family: inherit;
        font-size: 0.8rem;
        color: var(--text-main);
        width: 100%;
        text-align: center;
        outline: none;
        cursor: pointer;
        padding: 2px 0;
    }
    
    .time-separator {
        color: var(--text-muted);
        font-size: 0.78rem;
        padding: 0 4px;
    }

    .btn-remove {
        width: 32px;
        height: 32px;
        border-radius: 999px;
        border: none;
        background: transparent;
        color: #9ca3af;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.18s;
        flex-shrink: 0;
    }
    .btn-remove:hover {
        background: #fee2e2;
        color: #ef4444;
    }

    .btn-add {
        width: 100%;
        margin-top: 10px;
        padding: 8px;
        background: rgba(248,250,252,0.9);
        border: 1px dashed #cbd5e1;
        border-radius: 999px;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.18s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .btn-add:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        background: #eef2ff;
    }

    /* Barra Flutuante de Salvar */
    .sticky-save-bar {
        position: fixed;
        bottom: 16px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 460px;
        background: #020617;
        color: white;
        padding: 8px 14px;
        border-radius: 999px;
        box-shadow: 0 16px 35px rgba(15,23,42,0.65);
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        animation: floatUp 0.35s ease-out;
        gap: 10px;
    }
    .save-text {
        font-size: 0.8rem;
        font-weight: 500;
        opacity: 0.9;
        white-space: nowrap;
    }
    .btn-save-action {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.18s;
        white-space: nowrap;
    }
    .btn-save-action:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideDown {
        from { opacity: 0; max-height: 0; }
        to { opacity: 1; max-height: 480px; }
    }
    @keyframes floatUp {
        from { transform: translate(-50%, 120%); }
        to { transform: translate(-50%, 0); }
    }

    /* Ajustes Mobile */
    @media(max-width: 480px) {
        .main-wrapper { padding: 14px 10px 80px 10px; }
        .page-header h2 { font-size: 1.1rem; }
        .sticky-save-bar {
            padding-inline: 12px;
            gap: 8px;
        }
        .save-text { font-size: 0.78rem; }
    }
</style>

<div class="main-wrapper">
    
    <div class="page-header">
        <div>
            <h2>Configurar horários</h2>
            <p>Defina sua disponibilidade semanal.</p>
        </div>
        <button type="button" class="btn-auto-fill" onclick="confirmarHorarioComercial()">
            <i class="bi bi-magic"></i> Padrão comercial
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
                                    <span class="time-separator"><i class="bi bi-arrow-right-short"></i></span>
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
                        <i class="bi bi-plus-lg"></i> Adicionar intervalo
                    </button>
                </div>

            </div>
        <?php endforeach; ?>
    </form>
</div>

<div class="sticky-save-bar">
    <span class="save-text">Alterações pendentes</span>
    <button type="submit" form="formHorarios" class="btn-save-action">
        Salvar tudo
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

        const html = `
            <div class="time-slot-row">
                <div class="time-capsule">
                    <input type="time" name="horarios[${diaIndex}][${randId}][inicio]" 
                           class="time-input" value="${inicio}" required>
                    <span class="time-separator"><i class="bi bi-arrow-right-short"></i></span>
                    <input type="time" name="horarios[${diaIndex}][${randId}][fim]" 
                           class="time-input" value="${fim}" required>
                </div>
                <button type="button" class="btn-remove" onclick="removerSlot(this)">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        
        const newInputs = container.lastElementChild.querySelectorAll('input');
        if (newInputs[0] && !inicio) newInputs[0].focus(); 
    }

    function removerSlot(btn) {
        const row = btn.closest('.time-slot-row');
        row.style.opacity = '0';
        row.style.transform = 'translateX(6px)';
        setTimeout(() => row.remove(), 180);
    }

    // Modal Comercial
    function confirmarHorarioComercial() {
        AppConfirm.open({
            title: 'Aplicar padrão comercial',
            message: 'Isso definirá Seg–Sex das 08:00 às 18:00 (com almoço) e fechará o fim de semana. Continuar?',
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
            container.innerHTML = '';
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
