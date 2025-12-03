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
        --primary-color: #4f46e5;
        --primary-dark: #4338ca;
        --accent: #ec4899;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --shadow-soft: 0 6px 18px rgba(15,23,42,0.06);
        --shadow-hover: 0 14px 30px rgba(15,23,42,0.10);
        --radius-xl: 18px;
        --radius-lg: 16px;
        --radius-md: 14px;
        --radius-sm: 10px;
        --radius-pill: 999px;
    }

    * {
        box-sizing: border-box;
    }

    body {
        background: transparent;
        font-family: -apple-system, BlinkMacSystemFont, "Outfit", "Inter", system-ui, sans-serif;
        font-size: 0.875rem;
        color: var(--text-main);
        min-height: 100vh;
        line-height: 1.5;
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
        margin: 0 0 24px 0;
        text-align: left;
    }
    .config-header h2 {
        margin: 0 0 6px 0;
        font-size: 1.65rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.02em;
    }
    .config-header p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.85rem;
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
        background: var(--bg-card);
        border-radius: var(--radius-xl);
        padding: 20px 18px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
        transition: all 0.25s ease;
        position: relative;
    }
    
    .card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-1px);
        border-color: rgba(79,70,229,0.25);
    }

    .card-title {
        margin: 0 0 10px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text-main);
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    .card-title i {
        font-size: 1.15rem;
    }
    .card-desc {
        color: var(--text-muted);
        font-size: 0.8rem;
        margin-bottom: 16px;
        line-height: 1.6;
    }

    .form-group { 
        margin-bottom: 14px; 
    }
    .form-label {
        display: block;
        margin-bottom: 6px;
        font-weight: 700;
        font-size: 0.8rem;
        color: var(--text-main);
        letter-spacing: 0.01em;
    }
    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid rgba(148,163,184,0.3);
        border-radius: var(--radius-sm);
        box-sizing: border-box;
        font-size: 0.85rem;
        background: #f1f5f9;
        transition: all 0.2s ease;
        font-family: inherit;
        color: var(--text-main);
    }
    .form-control:focus {
        border-color: var(--primary-color);
        outline: none;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(79,70,229,0.18);
    }
    .form-control:hover {
        border-color: rgba(148,163,184,0.5);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 11px 24px;
        border-radius: var(--radius-pill);
        cursor: pointer;
        font-weight: 700;
        width: 100%;
        transition: all 0.25s ease;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 10px rgba(79,70,229,0.25);
        letter-spacing: 0.01em;
    }
    .btn-primary i {
        font-size: 1.05rem;
    }
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(79,70,229,0.35);
    }
    .btn-primary:active {
        transform: translateY(0);
    }

    /* Alertas */
    .alert {
        padding: 12px 16px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        box-shadow: var(--shadow-soft);
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
        font-size: 1.1rem;
    }
    .alert.success {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #166534;
        border: 1px solid #86efac;
    }
    .alert.error {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    /* Color Picker + Paletas */
    .color-picker-wrapper {
        display: flex;
        align-items: center;
        gap: 14px;
        border: 1px solid rgba(148,163,184,0.3);
        padding: 14px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        background: #f1f5f9;
        transition: all 0.25s ease;
    }
    .color-picker-wrapper:hover {
        border-color: var(--primary-color);
        background: #ffffff;
    }
    input[type="color"] {
        border: 2px solid rgba(148,163,184,0.3);
        width: 50px;
        height: 50px;
        cursor: pointer;
        background: transparent;
        padding: 0;
        border-radius: var(--radius-sm);
        transition: all 0.25s ease;
        box-shadow: var(--shadow-soft);
    }
    input[type="color"]:hover {
        transform: scale(1.04);
        border-color: var(--primary-color);
    }
    .preview-area {
        flex: 1;
        min-width: 0;
    }
    .preview-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .preview-btn {
        padding: 9px 18px;
        border-radius: var(--radius-pill);
        color: white;
        font-weight: 700;
        border: none;
        font-size: 0.85rem;
        pointer-events: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        box-shadow: var(--shadow-soft);
        white-space: nowrap;
        transition: all 0.25s ease;
    }

    .hex-helper {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 6px;
        font-weight: 500;
    }
    .hex-helper code {
        background: #e0e7ff;
        padding: 3px 7px;
        border-radius: 6px;
        font-family: 'Courier New', monospace;
        color: var(--primary-color);
        font-weight: 700;
    }

    .theme-palettes-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 14px 0 10px 0;
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: space-between;
        flex-wrap: wrap;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .theme-palettes-title span {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-muted);
        text-transform: none;
        letter-spacing: normal;
    }

    .theme-grid-wrapper {
        overflow-x: auto;
        padding-bottom: 6px;
        margin-bottom: 6px;
    }

    .theme-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
        gap: 10px;
        min-width: 260px;
    }

    .theme-card {
        border-radius: var(--radius-md);
        border: 1px solid rgba(148,163,184,0.25);
        padding: 12px 10px;
        cursor: pointer;
        transition: all 0.25s ease;
        background: var(--bg-card);
        display: flex;
        flex-direction: column;
        gap: 7px;
        position: relative;
        box-shadow: var(--shadow-soft);
    }
    .theme-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
        border-color: var(--primary-color);
    }
    .theme-card-header {
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }
    .theme-dot {
        width: 22px;
        height: 22px;
        border-radius: 7px;
        border: 2px solid rgba(148,163,184,0.3);
        box-shadow: var(--shadow-soft);
        flex-shrink: 0;
        transition: all 0.25s ease;
    }
    .theme-card:hover .theme-dot {
        transform: scale(1.08);
        border-color: white;
    }
    .theme-name {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-main);
        line-height: 1.3;
    }
    .theme-meta {
        font-size: 0.7rem;
        color: var(--text-muted);
        line-height: 1.4;
        margin-top: 1px;
    }

    .theme-swatches {
        display: flex;
        gap: 5px;
        margin-top: 5px;
    }
    .theme-swatch {
        width: 16px;
        height: 16px;
        border-radius: 5px;
        border: 2px solid rgba(255,255,255,0.8);
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        transition: all 0.25s ease;
    }
    .theme-card:hover .theme-swatch {
        transform: scale(1.08);
    }

    .theme-preview-bar {
        height: 6px;
        border-radius: 999px;
        background: rgba(148,163,184,0.2);
        overflow: hidden;
        position: relative;
        margin-top: 5px;
    }
    .theme-preview-bar-inner {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, rgba(15,23,42,0.1), rgba(15,23,42,0.25));
        mix-blend-mode: multiply;
    }

    .theme-card.active {
        border-color: var(--primary-color);
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        box-shadow: 0 0 0 3px rgba(79,70,229,0.2);
        transform: translateY(-2px);
    }
    .theme-card.active .theme-name {
        color: var(--primary-color);
    }

    /* Download / link */
    .btn-download {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
        text-decoration: none;
        padding: 11px 24px;
        border-radius: var(--radius-pill);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 700;
        transition: all 0.25s ease;
        font-size: 0.9rem;
        box-shadow: 0 4px 10px rgba(15,23,42,0.25);
    }
    .btn-download:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(15,23,42,0.35);
    }
    .btn-download i {
        font-size: 1.05rem;
    }

    .copy-box {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }
    .btn-copy {
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        color: var(--primary-color);
        border: 1px solid #c7d2fe;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 700;
        font-size: 0.8rem;
        white-space: nowrap;
        transition: all 0.25s ease;
        box-shadow: var(--shadow-soft);
    }
    .btn-copy:hover {
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        transform: translateY(-1px);
        box-shadow: var(--shadow-hover);
        border-color: var(--primary-color);
    }
    .btn-copy:active {
        transform: translateY(0);
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 18px 10px 120px 10px;
        }

        .config-header h2 {
            font-size: 1.4rem;
        }

        .config-header p {
            font-size: 0.8rem;
        }

        .card {
            padding: 16px 14px;
            border-radius: 14px;
        }

        .card-title {
            font-size: 0.95rem;
        }

        .card-title i {
            font-size: 1.05rem;
        }

        .card-desc {
            font-size: 0.77rem;
        }

        .copy-box {
            flex-direction: column;
        }

        .btn-copy {
            width: 100%;
            text-align: center;
            padding: 10px 0;
        }

        .form-control {
            font-size: 0.82rem;
            padding: 8px 10px;
        }

        .btn-primary {
            font-size: 0.88rem;
            padding: 10px 20px;
        }

        .theme-grid {
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .color-picker-wrapper {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        input[type="color"] {
            width: 44px;
            height: 44px;
        }
    }

    @media (min-width: 769px) {
        .card {
            padding: 22px 20px;
        }
    }

    /* Botão encurtar link */
    .btn-shorten {
        width: 100%;
        background: #f1f5f9;
        border: 1px dashed rgba(148,163,184,0.4);
        color: var(--text-muted);
        padding: 10px 18px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        transition: all 0.25s ease;
    }
    .btn-shorten:hover {
        background: #ffffff;
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-1px);
        box-shadow: var(--shadow-soft);
    }
    .btn-shorten:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .btn-shorten i {
        font-size: 0.95rem;
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
