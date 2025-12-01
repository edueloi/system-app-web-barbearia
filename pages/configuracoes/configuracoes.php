<?php
require_once __DIR__ . '/../../includes/config.php';
// pages/configuracoes/configuracoes.php

// =========================================================
// 1. PROCESSAMENTO DE POST (DEVE VIR PRIMEIRO)
// =========================================================

include '../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// --- PROCESSAR FORMULÁRIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. ALTERAR SENHA
    if (isset($_POST['acao']) && $_POST['acao'] === 'nova_senha') {
        $senhaAtual     = $_POST['senha_atual'] ?? '';
        $novaSenha      = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($senhaAtual, $user['senha'])) {
            if ($novaSenha === $confirmarSenha && strlen($novaSenha) >= 6) {
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $userId]);
                $_SESSION['config_msg']     = 'Senha atualizada com sucesso!';
                $_SESSION['config_msgType'] = 'success';
            } else {
                $_SESSION['config_msg']     = 'Senhas não coincidem ou muito curtas.';
                $_SESSION['config_msgType'] = 'error';
            }
        } else {
            $_SESSION['config_msg']     = 'Senha atual incorreta.';
            $_SESSION['config_msgType'] = 'error';
        }
        // 🔹 Descobre se está em produção (salao.develoi.com) ou local
        $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        $configUrl = $isProd ? '/configuracoes' : '/karen_site/controle-salao/pages/configuracoes/configuracoes.php';
        header("Location: {$configUrl}");
        exit;
    }

    // 2. SALVAR APARÊNCIA (COR DO TEMA)
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_aparencia') {
        $corTema = $_POST['cor_tema'] ?? '#4f46e5';

        $pdo->prepare("UPDATE usuarios SET cor_tema = ? WHERE id = ?")->execute([$corTema, $userId]);

        $_SESSION['config_msg']     = 'Cor do agendamento atualizada!';
        $_SESSION['config_msgType'] = 'success';
        // 🔹 Descobre se está em produção (salao.develoi.com) ou local
        $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
        $configUrl = $isProd ? '/configuracoes' : '/karen_site/controle-salao/pages/configuracoes/configuracoes.php';
        header("Location: {$configUrl}");
        exit;
    }
}

// =========================================================
// 2. LÓGICA DE DADOS (APÓS POST)
// =========================================================

// Mensagens Flash
$msg     = $_SESSION['config_msg']     ?? '';
$msgType = $_SESSION['config_msgType'] ?? '';
unset($_SESSION['config_msg'], $_SESSION['config_msgType']);

// Download Backup
if (isset($_GET['download_backup'])) {
    $file = '../../banco_salao.sqlite';
    if (file_exists($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup_salao_' . date('Y-m-d') . '.sqlite"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Link Público
$isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';

if ($isProd) {
    // Produção: usa rota amigável
    $linkFinal = 'https://salao.develoi.com/agendar?user=' . $userId;
} else {
    // Local: usa caminho completo
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'];
    $linkFinal = $protocol . '://' . $host . '/karen_site/controle-salao/agendar.php?user=' . $userId;
}

// Cor atual
$stmt = $pdo->prepare("SELECT cor_tema FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$dadosUser = $stmt->fetch();
$corAtual  = $dadosUser['cor_tema'] ?? '#4f46e5';

// =========================================================
// 3. INCLUSÃO DE HTML E APRESENTAÇÃO
// =========================================================
$pageTitle = 'Configurações';
include '../../includes/header.php';
include '../../includes/menu.php';
?>

<style>
    :root {
        --primary: #6366f1;
        --primary-hover: #4f46e5;
        --primary-light: #eef2ff;
        --bg-page: #f1f5f9;
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --text-light: #94a3b8;
        --border-soft: #e2e8f0;
        --shadow-card: 0 1px 3px rgba(15,23,42,0.08), 0 8px 24px rgba(15,23,42,0.06);
        --shadow-hover: 0 4px 12px rgba(15,23,42,0.10), 0 16px 40px rgba(15,23,42,0.08);
        --radius-xl: 24px;
        --radius-lg: 20px;
        --radius-md: 16px;
        --radius-sm: 12px;
        --radius-pill: 999px;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: 14px;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: 100vh;
    }

    .main-content {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 24px 16px 100px 16px;
        box-sizing: border-box;
    }

    @media (min-width: 768px) {
        .main-content {
            padding: 32px 24px 100px 24px;
        }
    }

    .config-header {
        margin: 0 0 28px 0;
        text-align: left;
    }
    .config-header h2 {
        margin: 0 0 8px 0;
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-dark);
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .config-header p {
        margin: 0;
        color: var(--text-gray);
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .config-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }
    @media(min-width: 900px) {
        .config-grid {
            grid-template-columns: 1.8fr 1fr;
            align-items: flex-start;
        }
        .card-full {
            grid-column: 1 / -1;
        }
    }

    .card {
        background: #ffffff;
        border-radius: var(--radius-xl);
        padding: 28px 24px;
        box-shadow: var(--shadow-card);
        border: 1px solid rgba(241,245,249,0.8);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary) 0%, #ec4899 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
        border-color: rgba(99,102,241,0.2);
    }
    
    .card:hover::before {
        opacity: 1;
    }

    .card-title {
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--text-dark);
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    .card-title i {
        font-size: 1.25rem;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
    }
    .card-desc {
        color: var(--text-gray);
        font-size: 0.9rem;
        margin-bottom: 20px;
        line-height: 1.65;
    }

    .form-group { 
        margin-bottom: 18px; 
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        color: #334155;
        letter-spacing: -0.01em;
    }
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: var(--radius-md);
        box-sizing: border-box;
        font-size: 0.92rem;
        background: #f8fafc;
        transition: all 0.2s ease;
        font-family: inherit;
    }
    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        transform: translateY(-1px);
    }
    .form-control:hover {
        border-color: #cbd5e1;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
        color: white;
        border: none;
        padding: 14px 24px;
        border-radius: var(--radius-pill);
        cursor: pointer;
        font-weight: 700;
        width: 100%;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 4px 16px rgba(99,102,241,0.3), 0 12px 32px rgba(99,102,241,0.2);
        letter-spacing: 0.01em;
        position: relative;
        overflow: hidden;
    }
    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
    }
    .btn-primary:hover::before {
        left: 100%;
    }
    .btn-primary i {
        font-size: 1.1rem;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99,102,241,0.4), 0 16px 40px rgba(99,102,241,0.25);
    }
    .btn-primary:active {
        transform: translateY(0) scale(0.98);
        box-shadow: 0 2px 8px rgba(79,70,229,0.3);
    }

    /* Alertas */
    .alert {
        padding: 16px 20px;
        border-radius: var(--radius-md);
        margin-bottom: 24px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .alert i {
        font-size: 1.2rem;
    }
    .alert.success {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #166534;
        border: 2px solid #86efac;
    }
    .alert.error {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 2px solid #fca5a5;
    }

    /* Color Picker + Paletas */
    .color-picker-wrapper {
        display: flex;
        align-items: center;
        gap: 16px;
        border: 2px solid #e2e8f0;
        padding: 16px;
        border-radius: var(--radius-lg);
        margin-bottom: 24px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
        transition: all 0.3s ease;
    }
    .color-picker-wrapper:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(99,102,241,0.15);
    }
    input[type="color"] {
        border: 3px solid #e2e8f0;
        width: 56px;
        height: 56px;
        cursor: pointer;
        background: transparent;
        padding: 0;
        border-radius: var(--radius-md);
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    input[type="color"]:hover {
        transform: scale(1.05);
        border-color: var(--primary);
    }
    .preview-area {
        flex: 1;
        min-width: 0;
    }
    .preview-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .preview-btn {
        padding: 10px 20px;
        border-radius: var(--radius-pill);
        color: white;
        font-weight: 700;
        border: none;
        font-size: 0.9rem;
        pointer-events: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(15,23,42,0.2), 0 8px 24px rgba(15,23,42,0.15);
        white-space: nowrap;
        transition: all 0.3s ease;
    }

    .hex-helper {
        font-size: 0.8rem;
        color: #94a3b8;
        margin-top: 8px;
        font-weight: 500;
    }
    .hex-helper code {
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 6px;
        font-family: 'Courier New', monospace;
        color: var(--primary);
        font-weight: 700;
    }

    .theme-palettes-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #334155;
        margin: 16px 0 12px 0;
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: space-between;
        flex-wrap: wrap;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .theme-palettes-title span {
        font-size: 0.75rem;
        font-weight: 500;
        color: #94a3b8;
        text-transform: none;
        letter-spacing: normal;
    }

    .theme-grid-wrapper {
        overflow-x: auto;
        padding-bottom: 8px;
        margin-bottom: 8px;
    }

    .theme-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        min-width: 260px;
    }

    .theme-card {
        border-radius: var(--radius-md);
        border: 2px solid #e2e8f0;
        padding: 14px 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        display: flex;
        flex-direction: column;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }
    .theme-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary) 0%, #ec4899 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .theme-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        border-color: var(--primary);
    }
    .theme-card:hover::before {
        opacity: 1;
    }
    .theme-card-header {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .theme-dot {
        width: 24px;
        height: 24px;
        border-radius: 8px;
        border: 3px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(15,23,42,0.15);
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    .theme-card:hover .theme-dot {
        transform: rotate(360deg);
        border-color: white;
    }
    .theme-name {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
    }
    .theme-meta {
        font-size: 0.75rem;
        color: #64748b;
        line-height: 1.4;
        margin-top: 2px;
    }

    .theme-swatches {
        display: flex;
        gap: 6px;
        margin-top: 6px;
    }
    .theme-swatch {
        width: 18px;
        height: 18px;
        border-radius: 6px;
        border: 2px solid rgba(255,255,255,0.8);
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    .theme-card:hover .theme-swatch {
        transform: scale(1.1);
    }

    .theme-preview-bar {
        height: 8px;
        border-radius: 999px;
        background: #e5e7eb;
        overflow: hidden;
        position: relative;
        margin-top: 6px;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }
    .theme-preview-bar-inner {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, rgba(15,23,42,0.15), rgba(15,23,42,0.3));
        mix-blend-mode: multiply;
    }

    .theme-card.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        box-shadow: 0 0 0 3px rgba(99,102,241,0.2), 0 8px 24px rgba(99,102,241,0.25);
        transform: translateY(-4px);
    }
    .theme-card.active::before {
        opacity: 1;
    }
    .theme-card.active .theme-name {
        color: var(--primary);
    }

    /* Download / link */
    .btn-download {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
        text-decoration: none;
        padding: 14px 24px;
        border-radius: var(--radius-pill);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-weight: 700;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        box-shadow: 0 4px 16px rgba(15,23,42,0.3), 0 12px 32px rgba(15,23,42,0.2);
        position: relative;
        overflow: hidden;
    }
    .btn-download::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
    }
    .btn-download:hover::before {
        left: 100%;
    }
    .btn-download:hover {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(15,23,42,0.4), 0 16px 40px rgba(15,23,42,0.25);
    }
    .btn-download i {
        font-size: 1.1rem;
    }

    .copy-box {
        display: flex;
        gap: 10px;
        align-items: stretch;
    }
    .btn-copy {
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        color: var(--primary);
        border: 2px solid #c7d2fe;
        padding: 12px 20px;
        border-radius: var(--radius-md);
        cursor: pointer;
        font-weight: 700;
        font-size: 0.85rem;
        white-space: nowrap;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(99,102,241,0.15);
    }
    .btn-copy:hover {
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99,102,241,0.25);
        border-color: var(--primary);
    }
    .btn-copy:active {
        transform: translateY(0) scale(0.98);
    }

    @media (max-width: 640px) {
        .copy-box {
            flex-direction: column;
        }
        .btn-copy {
            width: 100%;
            text-align: center;
            padding: 12px 0;
        }
    }
    
    /* Divider decorativo */
    .section-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
        margin: 32px 0;
    }
    
    /* Botão encurtar link */
    .btn-shorten {
        width: 100%;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 2px dashed #cbd5e1;
        color: #64748b;
        padding: 12px 20px;
        border-radius: var(--radius-md);
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .btn-shorten:hover {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99,102,241,0.15);
    }
    .btn-shorten:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .btn-shorten i {
        font-size: 1rem;
    }
</style>

<main class="main-content">

    <div class="config-header">
        <h2>Configurações</h2>
        <p>Gerencie segurança, aparência e backups do seu painel e agendamentos.</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert <?php echo $msgType; ?>">
            <i class="bi <?php echo $msgType === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?>"></i>
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="config-grid">

        <!-- PERSONALIZAR AGENDAMENTO -->
        <div class="card card-full">
            <h3 class="card-title">
                <i class="bi bi-palette-fill" style="color:#ec4899;"></i>
                Personalizar Agendamento
            </h3>
            <p class="card-desc">
                Defina a cor principal da sua página pública de agendamento. Essa cor será usada em botões,
                destaques e detalhes para combinar com a identidade visual do seu salão.
            </p>

            <form method="POST">
                <input type="hidden" name="acao" value="salvar_aparencia">

                <label class="form-label">Cor da marca</label>
                <div class="color-picker-wrapper">
                    <input
                        type="color"
                        name="cor_tema"
                        id="colorInput"
                        value="<?php echo htmlspecialchars($corAtual); ?>"
                        oninput="updatePreview(this.value)"
                    >

                    <div class="preview-area">
                        <div class="preview-label">Prévia do botão principal</div>
                        <button
                            type="button"
                            id="btnPreview"
                            class="preview-btn"
                            style="background-color: <?php echo htmlspecialchars($corAtual); ?>;"
                        >
                            <i class="bi bi-calendar-check"></i>
                            Agendar horário
                        </button>
                        <div class="hex-helper">
                            Cor atual: <code id="hexLabel"><?php echo htmlspecialchars($corAtual); ?></code>
                        </div>
                    </div>
                </div>

                <div class="theme-palettes-title">
                    Temas prontos
                    <span>Toque em um tema ou ajuste a cor manualmente</span>
                </div>

                <div class="theme-grid-wrapper">
                    <div class="theme-grid" id="themeGrid">
                        <!-- Tema Roxo -->
                        <div class="theme-card" data-color="#6366f1">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#6366f1;"></div>
                                <div>
                                    <div class="theme-name">Roxo Clássico</div>
                                    <div class="theme-meta">Moderno para salões e barbearias</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#4338ca;"></span>
                                <span class="theme-swatch" style="background:#6366f1;"></span>
                                <span class="theme-swatch" style="background:#818cf8;"></span>
                                <span class="theme-swatch" style="background:#c7d2fe;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Rosa -->
                        <div class="theme-card" data-color="#ec4899">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#ec4899;"></div>
                                <div>
                                    <div class="theme-name">Rosa Beauty</div>
                                    <div class="theme-meta">Estética, cílios, sobrancelhas</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#be185d;"></span>
                                <span class="theme-swatch" style="background:#ec4899;"></span>
                                <span class="theme-swatch" style="background:#f472b6;"></span>
                                <span class="theme-swatch" style="background:#f9a8d4;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Verde -->
                        <div class="theme-card" data-color="#22c55e">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#22c55e;"></div>
                                <div>
                                    <div class="theme-name">Verde Spa</div>
                                    <div class="theme-meta">Calmo, cara de bem-estar</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#15803d;"></span>
                                <span class="theme-swatch" style="background:#22c55e;"></span>
                                <span class="theme-swatch" style="background:#4ade80;"></span>
                                <span class="theme-swatch" style="background:#bbf7d0;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Laranja -->
                        <div class="theme-card" data-color="#f97316">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#f97316;"></div>
                                <div>
                                    <div class="theme-name">Laranja Energia</div>
                                    <div class="theme-meta">Clínicas, estúdios e vibe jovem</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#ea580c;"></span>
                                <span class="theme-swatch" style="background:#f97316;"></span>
                                <span class="theme-swatch" style="background:#fb923c;"></span>
                                <span class="theme-swatch" style="background:#fed7aa;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Dark Luxo -->
                        <div class="theme-card" data-color="#0f172a">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#0f172a;"></div>
                                <div>
                                    <div class="theme-name">Dark Luxo</div>
                                    <div class="theme-meta">Salões premium, barbearia clássica</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#020617;"></span>
                                <span class="theme-swatch" style="background:#0f172a;"></span>
                                <span class="theme-swatch" style="background:#1e293b;"></span>
                                <span class="theme-swatch" style="background:#e5e7eb;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Dourado -->
                        <div class="theme-card" data-color="#eab308">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#eab308;"></div>
                                <div>
                                    <div class="theme-name">Dourado Glam</div>
                                    <div class="theme-meta">Unhas, noivas, maquiagem</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#854d0e;"></span>
                                <span class="theme-swatch" style="background:#eab308;"></span>
                                <span class="theme-swatch" style="background:#facc15;"></span>
                                <span class="theme-swatch" style="background:#fef3c7;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Azul Claro -->
                        <div class="theme-card" data-color="#0ea5e9">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#0ea5e9;"></div>
                                <div>
                                    <div class="theme-name">Azul Fresh</div>
                                    <div class="theme-meta">Limpeza, clínica, visual leve</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#0369a1;"></span>
                                <span class="theme-swatch" style="background:#0ea5e9;"></span>
                                <span class="theme-swatch" style="background:#38bdf8;"></span>
                                <span class="theme-swatch" style="background:#e0f2fe;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                        <!-- Tema Vermelho -->
                        <div class="theme-card" data-color="#ef4444">
                            <div class="theme-card-header">
                                <div class="theme-dot" style="background:#ef4444;"></div>
                                <div>
                                    <div class="theme-name">Vermelho Impacto</div>
                                    <div class="theme-meta">Marca forte e marcante</div>
                                </div>
                            </div>
                            <div class="theme-swatches">
                                <span class="theme-swatch" style="background:#b91c1c;"></span>
                                <span class="theme-swatch" style="background:#ef4444;"></span>
                                <span class="theme-swatch" style="background:#f97373;"></span>
                                <span class="theme-swatch" style="background:#fee2e2;"></span>
                            </div>
                            <div class="theme-preview-bar">
                                <div class="theme-preview-bar-inner"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <div style="margin-top: 14px;">
                    <button type="submit" class="btn-primary">
                        <i class="bi bi-check-circle-fill"></i> Salvar cor do agendamento
                    </button>
                </div>
            </form>
        </div>

        <!-- COLUNA DIREITA -->
        <div class="card">
            <h3 class="card-title">
                <i class="bi bi-share-fill" style="color:#6366f1;"></i>
                Link para Clientes
            </h3>
            <p class="card-desc">Compartilhe este link para que seus clientes possam agendar online de forma autônoma.</p>

            <label class="form-label">📋 Link público de agendamento</label>
            <div class="copy-box">
                <input
                    type="text"
                    class="form-control"
                    value="<?php echo htmlspecialchars($linkFinal); ?>"
                    id="linkInput"
                    readonly
                    style="flex: 1; font-family: 'Courier New', monospace; font-size: 0.85rem;"
                >
                <button class="btn-copy" type="button" onclick="copiarLink()">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
            </div>
            
            <div style="margin-top: 12px;">
                <button type="button" class="btn-shorten" onclick="encurtarLink()" id="btnShorten">
                    <i class="bi bi-scissors"></i>
                    <span id="shortenText">Gerar link encurtado</span>
                </button>
            </div>
            
            <div id="shortLinkResult" style="display: none; margin-top: 12px;">
                <label class="form-label">🔗 Link encurtado (mais fácil de compartilhar)</label>
                <div class="copy-box">
                    <input
                        type="text"
                        class="form-control"
                        id="shortLinkInput"
                        readonly
                        style="flex: 1; font-family: 'Courier New', monospace; font-size: 0.85rem;"
                    >
                    <button class="btn-copy" type="button" onclick="copiarLinkEncurtado()">
                        <i class="bi bi-clipboard"></i> Copiar
                    </button>
                </div>
            </div>
            
            <div style="margin-top: 16px; padding: 12px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: var(--radius-md); border: 2px solid #86efac;">
                <div style="display: flex; align-items: center; gap: 8px; color: #166534; font-size: 0.85rem; font-weight: 600;">
                    <i class="bi bi-lightbulb-fill"></i>
                    <span>Dica profissional</span>
                </div>
                <p style="margin: 6px 0 0 0; color: #15803d; font-size: 0.8rem; line-height: 1.5;">
                    Adicione este link no Instagram, WhatsApp ou em sua bio para receber agendamentos 24/7.
                </p>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">
                <i class="bi bi-shield-lock-fill" style="color:#10b981;"></i>
                Segurança da Conta
            </h3>
            <p class="card-desc">Mantenha sua conta protegida atualizando sua senha regularmente.</p>

            <form method="POST">
                <input type="hidden" name="acao" value="nova_senha">
                <div class="form-group">
                    <label class="form-label">🔒 Senha atual</label>
                    <input type="password" name="senha_atual" class="form-control" required placeholder="Digite sua senha atual">
                </div>
                <div class="form-group">
                    <label class="form-label">🔑 Nova senha</label>
                    <input type="password" name="nova_senha" class="form-control" required placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label class="form-label">✅ Confirmar nova senha</label>
                    <input type="password" name="confirmar_senha" class="form-control" required placeholder="Digite novamente">
                </div>
                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 16px rgba(16,185,129,0.3), 0 12px 32px rgba(16,185,129,0.2);">
                    <i class="bi bi-shield-check"></i>
                    Atualizar senha
                </button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-title">
                <i class="bi bi-database-fill" style="color:#f59e0b;"></i>
                Backup de Dados
            </h3>
            <p class="card-desc">Baixe uma cópia completa do seu banco de dados incluindo agendamentos, clientes e todas as configurações.</p>
            
            <div style="margin-bottom: 16px; padding: 12px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: var(--radius-md); border: 2px solid #fbbf24;">
                <div style="display: flex; align-items: center; gap: 8px; color: #92400e; font-size: 0.85rem; font-weight: 600;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Importante</span>
                </div>
                <p style="margin: 6px 0 0 0; color: #b45309; font-size: 0.8rem; line-height: 1.5;">
                    Realize backups semanais para garantir a segurança dos seus dados.
                </p>
            </div>
            
            <a href="?download_backup=1" class="btn-download" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow: 0 4px 16px rgba(245,158,11,0.3), 0 12px 32px rgba(245,158,11,0.2);">
                <i class="bi bi-download"></i> Baixar banco de dados
            </a>
        </div>

    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
    function updatePreview(color) {
        var btn = document.getElementById('btnPreview');
        var hexLabel = document.getElementById('hexLabel');
        if (btn) {
            btn.style.backgroundColor = color;
        }
        if (hexLabel) {
            hexLabel.textContent = color;
        }
        highlightActiveTheme(color);
    }

    function copiarLink() {
        var copyText = document.getElementById("linkInput");
        if (!copyText) return;

        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);

        const btn = event.target.closest('.btn-copy');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Copiado!';
            setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
        }
    }
    
    function copiarLinkEncurtado() {
        var copyText = document.getElementById("shortLinkInput");
        if (!copyText) return;

        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);

        const btn = event.target.closest('.btn-copy');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Copiado!';
            setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
        }
    }
    
    async function encurtarLink() {
        const linkOriginal = document.getElementById('linkInput').value;
        const btnShorten = document.getElementById('btnShorten');
        const shortenText = document.getElementById('shortenText');
        const shortLinkResult = document.getElementById('shortLinkResult');
        const shortLinkInput = document.getElementById('shortLinkInput');
        
        // Desabilita o botão
        btnShorten.disabled = true;
        shortenText.innerHTML = '<i class="bi bi-hourglass-split"></i> Gerando...';
        
        try {
            // Tenta primeiro com TinyURL (mais confiável via GET)
            let response = await fetch(`https://tinyurl.com/api-create.php?url=${encodeURIComponent(linkOriginal)}`);
            
            if (!response.ok) {
                // Fallback para v.gd
                response = await fetch(`https://v.gd/create.php?format=simple&url=${encodeURIComponent(linkOriginal)}`);
            }
            
            if (response.ok) {
                const shortUrl = await response.text();
                
                // Verifica se não é uma mensagem de erro
                if (shortUrl.includes('Error') || shortUrl.includes('error') || shortUrl.length > 100) {
                    throw new Error('Resposta inválida');
                }
                
                // Exibe o resultado
                shortLinkInput.value = shortUrl.trim();
                shortLinkResult.style.display = 'block';
                
                // Atualiza o botão
                btnShorten.style.background = 'linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)';
                btnShorten.style.borderColor = '#86efac';
                btnShorten.style.color = '#166534';
                shortenText.innerHTML = '<i class="bi bi-check-circle-fill"></i> Link encurtado gerado!';
                
                // Scroll suave até o resultado
                setTimeout(() => {
                    shortLinkResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            } else {
                throw new Error('Erro ao encurtar link');
            }
        } catch (error) {
            console.error('Erro:', error);
            shortenText.innerHTML = '<i class="bi bi-exclamation-circle"></i> Erro ao encurtar';
            btnShorten.style.background = 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)';
            btnShorten.style.borderColor = '#fca5a5';
            btnShorten.style.color = '#991b1b';
            
            setTimeout(() => {
                btnShorten.disabled = false;
                btnShorten.style.background = '';
                btnShorten.style.borderColor = '';
                btnShorten.style.color = '';
                shortenText.innerHTML = '<i class="bi bi-scissors"></i> Gerar link encurtado';
            }, 3000);
        }
    }

    function highlightActiveTheme(color) {
        const cards = document.querySelectorAll('.theme-card');
        cards.forEach(card => {
            const c = (card.getAttribute('data-color') || '').toLowerCase();
            if (c === (color || '').toLowerCase()) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const themeGrid  = document.getElementById('themeGrid');
        const colorInput = document.getElementById('colorInput');

        if (themeGrid && colorInput) {
            themeGrid.querySelectorAll('.theme-card').forEach(card => {
                card.addEventListener('click', function () {
                    const color = card.getAttribute('data-color');
                    if (!color) return;
                    colorInput.value = color;
                    updatePreview(color);
                });
            });

            // Marca o tema ativo se coincidir com algum preset
            highlightActiveTheme(colorInput.value);
        }
    });
</script>
