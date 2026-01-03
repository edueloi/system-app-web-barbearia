<?php
require_once __DIR__ . '/../../includes/config.php';
// --- PROCESSAR SALVAMENTO (Lógica Mantida) ---
include '../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔹 Descobre se está em produção (salao.develoi.com) ou local
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProd ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];
$horariosUrl = $isProd
    ? '/horarios' // em produção usa rota amigável
    : '/karen_site/controle-salao/pages/horarios/horarios.php';

// 🔹 PROCESSAR ADIÇÃO DE DIA ESPECIAL (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'adicionar_dia_especial') {
    header('Content-Type: application/json');
    try {
        $dataInicio = $_POST['data_inicio'];
        $dataFim = isset($_POST['data_fim']) && !empty($_POST['data_fim']) ? $_POST['data_fim'] : $dataInicio;
        $nome = $_POST['nome'];
        $tipo = $_POST['tipo'] ?? 'data_especial';
        $recorrente = isset($_POST['recorrente']) && $_POST['recorrente'] === '1' ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO dias_especiais_fechamento (user_id, data, tipo, nome, recorrente) VALUES (?, ?, ?, ?, ?)");
        
        // Se for range de datas, adiciona cada dia
        $inicio = new DateTime($dataInicio);
        $fim = new DateTime($dataFim);
        $fim->modify('+1 day'); // Inclui o último dia
        
        $interval = new DateInterval('P1D');
        $periodo = new DatePeriod($inicio, $interval, $fim);
        
        $idsInseridos = [];
        foreach ($periodo as $data) {
            $dataStr = $data->format('Y-m-d');
            $stmt->execute([$userId, $dataStr, $tipo, $nome, $recorrente]);
            $idsInseridos[] = $pdo->lastInsertId();
        }

        echo json_encode([
            'success' => true, 
            'ids' => $idsInseridos,
            'total' => count($idsInseridos)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 🔹 PROCESSAR REMOÇÃO DE DIA ESPECIAL (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'remover_dia_especial') {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM dias_especiais_fechamento WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->prepare("DELETE FROM horarios_atendimento WHERE user_id = ?")->execute([$userId]);

        if (isset($_POST['horarios']) && is_array($_POST['horarios'])) {
            $stmt = $pdo->prepare("INSERT INTO horarios_atendimento (user_id, dia_semana, inicio, fim, intervalo_minutos) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['horarios'] as $dia => $slots) {
                if (isset($_POST['dia_ativo'][$dia])) {
                    foreach ($slots as $slot) {
                        if (!empty($slot['inicio']) && !empty($slot['fim'])) {
                            $intervalo = !empty($slot['intervalo']) ? (int)$slot['intervalo'] : 30;
                            $stmt->execute([$userId, $dia, $slot['inicio'], $slot['fim'], $intervalo]);
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
    $agenda[$reg['dia_semana']][] = [
        'inicio' => $reg['inicio'], 
        'fim' => $reg['fim'],
        'intervalo_minutos' => $reg['intervalo_minutos'] ?? 30
    ];
}

// --- BUSCAR DIAS ESPECIAIS DE FECHAMENTO ---
$stmt = $pdo->prepare("SELECT * FROM dias_especiais_fechamento WHERE user_id = ? ORDER BY data ASC");
$stmt->execute([$userId]);
$diasEspeciais = $stmt->fetchAll();

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
$pageTitle = 'Seu Expediente';

include '../../includes/header.php';
include '../../includes/menu.php';
?>

<style>
    :root {
        --primary-color: #0f2f66;
        --primary-dark: #1e3a8a;
        --primary-soft: #e0e7ff;
        --accent: #0ea5e9;
        --bg-page: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --input-bg: #f1f5f9;
        --shadow-soft: 0 6px 18px rgba(15,23,42,0.06);
        --shadow-hover: 0 14px 30px rgba(15,23,42,0.10);
    }

    * {
        box-sizing: border-box;
    }

    /* SEM GRADIENTE – usa o fundo padrão do sistema */
    body {
        background: transparent;
        font-family: -apple-system, BlinkMacSystemFont, "Outfit", "Inter", system-ui, sans-serif;
        font-size: 14px;
        color: var(--text-main);
        min-height: 100vh;
        line-height: 1.5;
    }

    .main-wrapper {
        max-width: 980px;
        margin: 0 auto;
        padding: 24px 16px 120px;
    }

    /* Cabeçalho da Página */
    .page-header {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 18px 20px;
        margin-bottom: 18px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
    }
    
    .page-header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .page-title-wrapper {
        flex: 1;
        min-width: 180px;
    }
    
    .page-header h2 {
        font-size: 1.35rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        letter-spacing: -0.03em;
        line-height: 1.2;
    }
    
    .page-header p {
        margin: 6px 0 0;
        color: var(--text-muted);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .btn-auto-fill {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 9px 14px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 10px rgba(15,47,102,0.25);
        white-space: nowrap;
    }
    
    .btn-auto-fill i {
        font-size: 0.95rem;
    }

    .btn-auto-fill:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(79,70,229,0.35);
    }
    
    .btn-auto-fill:active {
        transform: translateY(0);
    }

    .days-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    @media (min-width: 1024px) {
        .days-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* Cards dos Dias */
    .day-card {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 16px 16px 14px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.20);
        transition: all 0.25s ease;
    }
    
    .day-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-1px);
        border-color: rgba(15,47,102,0.25);
    }

    /* Estado Fechado */
    .day-card.closed {
        background: #f9fafb;
        border-color: rgba(148,163,184,0.28);
        box-shadow: 0 4px 12px rgba(15,23,42,0.04);
        opacity: 0.9;
    }
    
    .day-card.closed:hover {
        transform: translateY(0);
        box-shadow: 0 4px 12px rgba(15,23,42,0.04);
    }
    
    .day-card.closed .day-title {
        color: #94a3b8;
        font-weight: 600;
    }
    
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        margin-bottom: 2px;
    }

    .day-info {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }
    
    .day-icon {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
        color: #fff;
    }
    
    .day-card.closed .day-icon {
        background: linear-gradient(135deg, #cbd5e1, #94a3b8);
    }
    
    .day-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
    }
    
    .status-badge {
        font-size: 0.65rem;
        padding: 3px 9px;
        border-radius: 999px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    
    .status-open {
        color: #16a34a;
        background: #dcfce7;
    }
    
    .status-closed {
        color: #6b7280;
        background: #e5e7eb;
    }

    /* Switch estilo iOS */
    .switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
        flex-shrink: 0;
    }
    
    .switch input { 
        opacity: 0; 
        width: 0; 
        height: 0; 
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; 
        left: 0; 
        right: 0; 
        bottom: 0;
        background: linear-gradient(135deg, #cbd5e1, #94a3b8);
        transition: all 0.25s ease;
        border-radius: 999px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 2px;
        bottom: 2px;
        background: #ffffff;
        transition: all 0.25s ease;
        border-radius: 50%;
        box-shadow: 0 2px 6px rgba(15,23,42,0.25);
    }
    
    input:checked + .slider { 
        background: linear-gradient(135deg, var(--primary-color), var(--accent));
    }
    
    input:checked + .slider:before { 
        transform: translateX(22px);
    }

    /* Área dos Slots */
    .day-body {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid rgba(148,163,184,0.3);
        animation: slideDown 0.25s ease-out;
    }

    .slots-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .time-slot-row {
        display: flex;
        align-items: center;
        gap: 6px;
        animation: fadeIn 0.25s;
    }

    .time-capsule {
        flex: 1;
        background: #f9fafb;
        border-radius: 14px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid rgba(209,213,219,0.9);
        transition: all 0.2s ease;
    }
    
    .time-capsule:hover {
        background: #ffffff;
        border-color: rgba(15,47,102,0.35);
        box-shadow: 0 3px 10px rgba(15,23,42,0.06);
    }
    
    .time-capsule:focus-within {
        background: #ffffff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(15,47,102,0.18);
    }

    .time-input {
        background: transparent;
        border: none;
        font-family: inherit;
        font-size: 0.94rem;
        font-weight: 600;
        color: var(--text-main);
        width: 100%;
        text-align: center;
        outline: none;
        cursor: pointer;
        padding: 3px 0;
    }
    
    .time-separator {
        color: var(--primary-color);
        font-size: 0.95rem;
        padding: 0 4px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
    }

    .time-separator i {
        font-size: 1.1rem;
    }

    .interval-select {
        background: rgba(15,47,102,0.06);
        border: none;
        font-family: inherit;
        font-size: 0.8rem;
        color: var(--primary-color);
        outline: none;
        cursor: pointer;
        padding: 4px 8px;
        font-weight: 700;
        border-radius: 999px;
        transition: all 0.2s;
    }
    
    .interval-select:hover {
        background: rgba(15,47,102,0.12);
    }

    .btn-remove {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: none;
        background: rgba(239,68,68,0.08);
        color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
        font-size: 0.95rem;
    }
    
    .btn-remove:hover {
        background: #fee2e2;
        transform: translateY(-1px);
    }
    
    .btn-remove:active {
        transform: scale(0.96);
    }

    .btn-add {
        width: 100%;
        margin-top: 10px;
        padding: 10px 12px;
        background: #f9fafb;
        border: 1px dashed rgba(148,163,184,0.8);
        border-radius: 12px;
        color: var(--primary-color);
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .btn-add:hover {
        border-color: var(--primary-color);
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(15,23,42,0.05);
    }

    /* Barra Flutuante de Salvar */
    .sticky-save-bar {
        position: fixed;
        bottom: 14px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 24px);
        max-width: 640px;
        background: #ffffff;
        color: var(--text-main);
        padding: 10px 14px;
        border-radius: 14px;
        box-shadow: 0 16px 40px rgba(15,23,42,0.18);
        border: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .save-text {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .save-text::before {
        content: '⚡';
        font-size: 1rem;
    }
    
    .btn-save-action {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(15,47,102,0.35);
    }
    
    .btn-save-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(15,47,102,0.45);
    }
    
    .btn-save-action:active {
        transform: translateY(0);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideDown {
        from { opacity: 0; max-height: 0; padding-top: 0; }
        to { opacity: 1; max-height: 600px; padding-top: 14px; }
    }

    /* === SEÇÃO DIAS ESPECIAIS === */
    .special-days-section {
        margin-top: 20px;
        background: var(--bg-card);
        border-radius: 16px;
        padding: 14px 16px 16px;
        border: 1px solid #dbeafe;
        box-shadow: var(--shadow-soft);
    }

    .special-days-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }

    .special-days-header h3 {
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f2f66;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .special-days-header .icon {
        font-size: 1.1rem;
    }

    .special-days-description {
        color: #1e3a8a;
        font-size: 0.75rem;
        margin-bottom: 10px;
        opacity: 0.9;
    }

    .add-special-day-form {
        background: #eff6ff;
        border-radius: 12px;
        padding: 10px 10px 12px;
        margin-bottom: 10px;
        border: 1px dashed #93c5fd;
    }

    .form-row {
        display: flex;
        gap: 6px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }

    .form-input {
        flex: 1;
        min-width: 130px;
        padding: 6px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 0.8rem;
        font-family: inherit;
        background: #f9fafb;
        transition: 0.15s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: #1e3a8a;
        background: #ffffff;
        box-shadow: 0 0 0 2px rgba(30,58,138,0.18);
    }

    .form-select {
        padding: 6px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        font-size: 0.8rem;
        font-family: inherit;
        background: #f9fafb;
        cursor: pointer;
        transition: 0.15s ease;
    }

    .form-select:focus {
        outline: none;
        border-color: #1e3a8a;
        background: #ffffff;
    }

    .checkbox-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        font-size: 0.75rem;
        color: #6b7280;
        flex-wrap: wrap;
    }

    .checkbox-row input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #1e3a8a;
    }

    .btn-add-special {
        width: 100%;
        background: linear-gradient(135deg, #0f2f66, #1e3a8a);
        color: white;
        border: none;
        padding: 8px 10px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-add-special:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(30,58,138,0.3);
    }

    .special-days-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 4px;
    }

    .special-day-item {
        background: #ffffff;
        border-radius: 12px;
        padding: 9px 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #dbeafe;
        transition: 0.2s ease;
    }

    .special-day-item:hover {
        border-color: #93c5fd;
        box-shadow: 0 3px 10px rgba(30,58,138,0.15);
    }

    .special-day-info {
        flex: 1;
    }

    .special-day-name {
        font-weight: 700;
        color: #0f2f66;
        font-size: 0.85rem;
        margin-bottom: 2px;
    }

    .special-day-date {
        color: #1e3a8a;
        font-size: 0.72rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .special-day-badge {
        font-size: 0.6rem;
        padding: 2px 7px;
        border-radius: 999px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-left: 4px;
    }

    .badge-recorrente {
        background: #dbeafe;
        color: #1e3a8a;
    }

    .badge-unico {
        background: #e0f2fe;
        color: #0ea5e9;
    }

    .btn-remove-special {
        background: #fee2e2;
        color: #dc2626;
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s ease;
        flex-shrink: 0;
        font-size: 0.85rem;
    }

    .btn-remove-special:hover {
        background: #fecaca;
        transform: scale(1.05);
    }

    .empty-state {
        text-align: center;
        padding: 18px 10px;
        color: #1e3a8a;
        opacity: 0.65;
        font-size: 0.8rem;
    }

    /* === RESPONSIVO MOBILE === */
    @media (max-width: 768px) {
        .main-wrapper { 
            padding: 18px 10px 120px 10px; 
        }
        
        .page-header {
            padding: 14px 14px;
            border-radius: 14px;
        }
        
        .page-header h2 { 
            font-size: 1.25rem;
        }
        
        .page-header p {
            font-size: 0.8rem;
        }
        
        .btn-auto-fill {
            width: 100%;
            justify-content: center;
            font-size: 0.82rem;
        }

        .day-card {
            padding: 14px 14px 12px;
            border-radius: 14px;
        }

        .day-icon {
            width: 38px;
            height: 38px;
            font-size: 1rem;
        }
        
        .day-title {
            font-size: 0.95rem;
        }
        
        .status-badge {
            font-size: 0.62rem;
        }

        .switch {
            width: 44px;
            height: 22px;
        }

        .slider:before {
            height: 18px;
            width: 18px;
        }

        .time-capsule {
            padding: 9px 10px;
            border-radius: 12px;
        }
        
        .time-input {
            font-size: 0.9rem;
        }
        
        .interval-select {
            font-size: 0.78rem;
        }

        .btn-remove {
            width: 30px;
            height: 30px;
            font-size: 0.85rem;
        }
        
        .btn-add {
            padding: 9px 10px;
            font-size: 0.82rem;
        }

        .sticky-save-bar {
            padding: 9px 12px;
            border-radius: 12px;
            max-width: calc(100% - 18px);
            flex-direction: column;
            align-items: stretch;
        }
        
        .save-text {
            font-size: 0.8rem;
        }
        
        .btn-save-action {
            width: 100%;
            text-align: center;
            padding: 9px 14px;
        }

        .form-row {
            flex-direction: column;
        }
        
        .form-input, .form-select {
            min-width: 100%;
        }
    }
</style>


<div class="main-wrapper">
    
    <div class="page-header">
        <div class="page-header-top">
            <div class="page-title-wrapper">
                <h2>⏰ Seu Expediente</h2>
                <p>Configure seus horários de atendimento semanal</p>
            </div>
            <button type="button" class="btn-auto-fill" onclick="confirmarHorarioComercial()">
                <i class="bi bi-magic"></i> Padrão comercial
            </button>
        </div>
    </div>

    <form method="POST" id="formHorarios">
        <?php 
        $iconesDias = [
            1 => '🗓️', // Segunda
            2 => '🗓️', // Terça
            3 => '🗓️', // Quarta
            4 => '🗓️', // Quinta
            5 => '🗓️', // Sexta
            6 => '🎉', // Sábado
            0 => '🌟'  // Domingo
        ];
        
        foreach ($diasSemana as $diaIndex => $diaNome): 
            $temHorarios = count($agenda[$diaIndex]) > 0;
            $isChecked   = $temHorarios ? 'checked' : '';
            $cardClass   = $temHorarios ? '' : 'closed';
            $icone = $iconesDias[$diaIndex];
        ?>
            
            <div class="day-card <?php echo $cardClass; ?>" id="card-<?php echo $diaIndex; ?>">
                
                <div class="day-header">
                    <div class="day-info" onclick="triggerToggle(<?php echo $diaIndex; ?>)">
                        <div class="day-icon"><?php echo $icone; ?></div>
                        <div>
                            <div class="day-title"><?php echo $diaNome; ?></div>
                            <span id="badge-<?php echo $diaIndex; ?>" class="status-badge <?php echo $temHorarios ? 'status-open' : 'status-closed'; ?>">
                                <?php echo $temHorarios ? 'Aberto' : 'Fechado'; ?>
                            </span>
                        </div>
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
                                    <span class="time-separator">•</span>
                                    <select name="horarios[<?php echo $diaIndex; ?>][<?php echo $i; ?>][intervalo]" class="interval-select" title="Intervalo entre horários">
                                        <option value="15" <?php echo ($horario['intervalo_minutos'] == 15) ? 'selected' : ''; ?>>15min</option>
                                        <option value="30" <?php echo ($horario['intervalo_minutos'] == 30) ? 'selected' : ''; ?>>30min</option>
                                        <option value="45" <?php echo ($horario['intervalo_minutos'] == 45) ? 'selected' : ''; ?>>45min</option>
                                        <option value="60" <?php echo ($horario['intervalo_minutos'] == 60) ? 'selected' : ''; ?>>60min</option>
                                        <option value="90" <?php echo ($horario['intervalo_minutos'] == 90) ? 'selected' : ''; ?>>90min</option>
                                        <option value="120" <?php echo ($horario['intervalo_minutos'] == 120) ? 'selected' : ''; ?>>120min</option>
                                    </select>
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

    <!-- === SEÇÃO DIAS ESPECIAIS / FERIADOS === -->
    <div class="special-days-section">
        <div class="special-days-header">
            <h3>
                <span class="icon">🎉</span>
                Feriados e Dias Especiais
            </h3>
        </div>
        <p class="special-days-description">
            Marque datas específicas em que você não atenderá: feriados, aniversário, férias, etc.
        </p>

        <!-- Formulário para adicionar -->
        <div class="add-special-day-form">
            <div class="form-row">
                <input type="text" 
                       id="special-name" 
                       class="form-input" 
                       placeholder="Nome (ex: Natal, Férias de Verão)" 
                       maxlength="100"
                       required>
                <select id="special-type" class="form-select">
                    <option value="data_especial">Dia Especial</option>
                    <option value="feriado_fixo">Feriado Fixo</option>
                    <option value="feriado_nacional">Feriado Nacional</option>
                </select>
            </div>
            
            <div class="form-row">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.8rem; color: #9a3412; margin-bottom: 6px; font-weight: 600;">
                        📅 Data Início
                    </label>
                    <input type="date" 
                           id="special-date-inicio" 
                           class="form-input" 
                           min="<?php echo date('Y-m-d'); ?>"
                           onchange="toggleRangeMode()"
                           required>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.8rem; color: #9a3412; margin-bottom: 6px; font-weight: 600;">
                        📅 Data Fim (opcional)
                    </label>
                    <input type="date" 
                           id="special-date-fim" 
                           class="form-input"
                           min="<?php echo date('Y-m-d'); ?>"
                           placeholder="Deixe vazio para 1 dia">
                </div>
            </div>
            
            <div id="range-info" style="display: none; background: rgba(251,146,60,0.1); padding: 10px; border-radius: 10px; margin-bottom: 10px; font-size: 0.85rem; color: #9a3412;">
                <i class="bi bi-info-circle"></i>
                <span id="range-text">Será cadastrado 1 dia</span>
            </div>
            
            <div class="checkbox-row" id="recorrente-checkbox">
                <input type="checkbox" id="special-recorrente">
                <label for="special-recorrente">
                    🔄 Repete todo ano (exemplo: aniversário sempre em 15/03)
                </label>
            </div>
            
            <button type="button" class="btn-add-special" onclick="adicionarDiaEspecial()">
                <i class="bi bi-plus-circle"></i>
                <span id="btn-add-text">Adicionar Data</span>
            </button>
        </div>

        <!-- Lista de dias especiais -->
        <div class="special-days-list" id="special-days-list">
            <?php if (empty($diasEspeciais)): ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                    Nenhum dia especial cadastrado
                </div>
            <?php else: ?>
                <?php foreach ($diasEspeciais as $dia): ?>
                    <div class="special-day-item" data-id="<?php echo $dia['id']; ?>">
                        <div class="special-day-info">
                            <div class="special-day-name">
                                <?php echo htmlspecialchars($dia['nome']); ?>
                                <?php if ($dia['recorrente']): ?>
                                    <span class="special-day-badge badge-recorrente">Anual</span>
                                <?php else: ?>
                                    <span class="special-day-badge badge-unico">Único</span>
                                <?php endif; ?>
                            </div>
                            <div class="special-day-date">
                                <i class="bi bi-calendar-event"></i>
                                <?php 
                                    $dataObj = new DateTime($dia['data']);
                                    echo $dataObj->format('d/m/Y');
                                    if ($dia['recorrente']) {
                                        echo ' • ' . $dataObj->format('d/m') . ' de cada ano';
                                    }
                                ?>
                            </div>
                        </div>
                        <button type="button" 
                                class="btn-remove-special" 
                                onclick="removerDiaEspecial(<?php echo $dia['id']; ?>)"
                                title="Remover">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

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

    function adicionarSlot(diaIndex, inicio = '', fim = '', intervalo = 30) {
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
                    <span class="time-separator">•</span>
                    <select name="horarios[${diaIndex}][${randId}][intervalo]" class="interval-select" title="Intervalo entre horários">
                        <option value="15" ${intervalo == 15 ? 'selected' : ''}>15min</option>
                        <option value="30" ${intervalo == 30 ? 'selected' : ''}>30min</option>
                        <option value="45" ${intervalo == 45 ? 'selected' : ''}>45min</option>
                        <option value="60" ${intervalo == 60 ? 'selected' : ''}>60min</option>
                        <option value="90" ${intervalo == 90 ? 'selected' : ''}>90min</option>
                        <option value="120" ${intervalo == 120 ? 'selected' : ''}>120min</option>
                    </select>
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

    // === GERENCIAR DIAS ESPECIAIS ===
    
    // Calcula e mostra range de dias
    function toggleRangeMode() {
        const dataInicio = document.getElementById('special-date-inicio').value;
        const dataFim = document.getElementById('special-date-fim').value;
        const rangeInfo = document.getElementById('range-info');
        const rangeText = document.getElementById('range-text');
        const btnText = document.getElementById('btn-add-text');
        const recorrenteCheckbox = document.getElementById('recorrente-checkbox');
        
        if (dataInicio) {
            // Define data mínima do fim como a data de início
            document.getElementById('special-date-fim').min = dataInicio;
        }
        
        if (dataInicio && dataFim && dataFim > dataInicio) {
            // Calcula diferença de dias
            const inicio = new Date(dataInicio + 'T00:00:00');
            const fim = new Date(dataFim + 'T00:00:00');
            const diffTime = Math.abs(fim - inicio);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            rangeInfo.style.display = 'block';
            rangeText.textContent = `Serão cadastrados ${diffDays} dias (${dataInicio.split('-').reverse().join('/')} até ${dataFim.split('-').reverse().join('/')})`;
            btnText.textContent = `Adicionar ${diffDays} Dias`;
            
            // Desabilita recorrência em range
            recorrenteCheckbox.style.opacity = '0.5';
            recorrenteCheckbox.style.pointerEvents = 'none';
            document.getElementById('special-recorrente').checked = false;
            document.getElementById('special-recorrente').disabled = true;
        } else {
            rangeInfo.style.display = 'none';
            btnText.textContent = 'Adicionar Data';
            
            // Reabilita recorrência
            recorrenteCheckbox.style.opacity = '1';
            recorrenteCheckbox.style.pointerEvents = 'auto';
            document.getElementById('special-recorrente').disabled = false;
        }
    }
    
    // Monitora mudanças nas datas
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('special-date-inicio')?.addEventListener('change', toggleRangeMode);
        document.getElementById('special-date-fim')?.addEventListener('change', toggleRangeMode);
    });
    
    async function adicionarDiaEspecial() {
        const dataInicio = document.getElementById('special-date-inicio').value;
        const dataFim = document.getElementById('special-date-fim').value;
        const nome = document.getElementById('special-name').value.trim();
        const tipo = document.getElementById('special-type').value;
        const recorrente = document.getElementById('special-recorrente').checked;

        if (!dataInicio || !nome) {
            AppToast.show('Preencha a data inicial e o nome!', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'adicionar_dia_especial');
        formData.append('data_inicio', dataInicio);
        if (dataFim && dataFim !== dataInicio) {
            formData.append('data_fim', dataFim);
        }
        formData.append('nome', nome);
        formData.append('tipo', tipo);
        formData.append('recorrente', recorrente ? '1' : '0');

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                const msg = result.total > 1 
                    ? `${result.total} datas adicionadas com sucesso!` 
                    : 'Data especial adicionada!';
                AppToast.show(msg, 'success');
                // Recarregar página para atualizar lista
                setTimeout(() => location.reload(), 800);
            } else {
                AppToast.show('Erro ao adicionar: ' + result.error, 'danger');
            }
        } catch (error) {
            AppToast.show('Erro de comunicação com servidor', 'danger');
            console.error(error);
        }
    }

    async function removerDiaEspecial(id) {
        AppConfirm.open({
            title: 'Remover data especial',
            message: 'Tem certeza que deseja remover esta data? Esta ação não pode ser desfeita.',
            confirmText: 'Remover',
            type: 'danger',
            onConfirm: async () => {
                const formData = new FormData();
                formData.append('action', 'remover_dia_especial');
                formData.append('id', id);

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        AppToast.show('Data removida com sucesso!', 'success');
                        // Remover elemento da lista com animação
                        const item = document.querySelector(`[data-id="${id}"]`);
                        if (item) {
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(-20px)';
                            setTimeout(() => {
                                item.remove();
                                // Se lista ficou vazia, mostrar estado vazio
                                const list = document.getElementById('special-days-list');
                                if (list.children.length === 0) {
                                    list.innerHTML = `
                                        <div class="empty-state">
                                            <i class="bi bi-calendar-x" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                                            Nenhum dia especial cadastrado
                                        </div>
                                    `;
                                }
                            }, 300);
                        }
                    } else {
                        AppToast.show('Erro ao remover: ' + result.error, 'danger');
                    }
                } catch (error) {
                    AppToast.show('Erro de comunicação com servidor', 'danger');
                    console.error(error);
                }
            }
        });
    }

    // Atalho para feriados comuns - ATUALIZADO para novo layout
    function sugerirNomeFeriado() {
        const nomeInput = document.getElementById('special-name');
        const dataInicioInput = document.getElementById('special-date-inicio');
        
        // Se campo de nome estiver vazio e data selecionada, sugerir nome
        if (!nomeInput.value && dataInicioInput.value) {
            const dataSelecionada = new Date(dataInicioInput.value + 'T12:00:00');
            const mes = dataSelecionada.getMonth() + 1;
            const dia = dataSelecionada.getDate();
            
            const feriadosComuns = {
                '01-01': { nome: 'Ano Novo', tipo: 'feriado_nacional', recorrente: true },
                '12-25': { nome: 'Natal', tipo: 'feriado_nacional', recorrente: true },
                '12-24': { nome: 'Véspera de Natal', tipo: 'feriado_nacional', recorrente: true },
                '12-31': { nome: 'Réveillon', tipo: 'feriado_nacional', recorrente: true },
                '09-07': { nome: 'Independência do Brasil', tipo: 'feriado_nacional', recorrente: true },
                '10-12': { nome: 'Nossa Senhora Aparecida', tipo: 'feriado_nacional', recorrente: true },
                '11-02': { nome: 'Finados', tipo: 'feriado_nacional', recorrente: true },
                '11-15': { nome: 'Proclamação da República', tipo: 'feriado_nacional', recorrente: true },
                '11-20': { nome: 'Consciência Negra', tipo: 'feriado_nacional', recorrente: true }
            };
            
            const chave = String(mes).padStart(2, '0') + '-' + String(dia).padStart(2, '0');
            if (feriadosComuns[chave]) {
                const feriado = feriadosComuns[chave];
                nomeInput.value = feriado.nome;
                document.getElementById('special-type').value = feriado.tipo;
                document.getElementById('special-recorrente').checked = feriado.recorrente;
            }
        }
    }
    
    // Adiciona listeners após carregar página
    document.addEventListener('DOMContentLoaded', () => {
        const nomeInput = document.getElementById('special-name');
        const dataInicioInput = document.getElementById('special-date-inicio');
        
        if (nomeInput && dataInicioInput) {
            nomeInput.addEventListener('focus', sugerirNomeFeriado);
            dataInicioInput.addEventListener('change', () => {
                // Auto-preenche se nome estiver vazio
                if (!nomeInput.value) {
                    sugerirNomeFeriado();
                }
            });
        }
    });

    // Mensagens de Sucesso/Erro PHP
    <?php if ($toastStatus): ?>
        window.addEventListener('DOMContentLoaded', () => {
            const msg = "<?php echo ($toastStatus === 'success') ? 'Horários salvos com sucesso!' : 'Erro ao salvar.'; ?>";
            const type = "<?php echo ($toastStatus === 'success') ? 'success' : 'danger'; ?>";
            AppToast.show(msg, type);
        });
    <?php endif; ?>
</script>
