<?php
// pages/configuracoes/configuracoes.php

// =========================================================
// 1. PROCESSAMENTO DE POST (DEVE VIR PRIMEIRO)
// =========================================================

include '../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
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
        header('Location: configuracoes.php');
        exit;
    }

    // 2. SALVAR APARÊNCIA (COR DO TEMA)
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_aparencia') {
        $corTema = $_POST['cor_tema'] ?? '#4f46e5';

        $pdo->prepare("UPDATE usuarios SET cor_tema = ? WHERE id = ?")->execute([$corTema, $userId]);

        $_SESSION['config_msg']     = 'Cor do agendamento atualizada!';
        $_SESSION['config_msgType'] = 'success';
        header('Location: configuracoes.php');
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
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$appRoot   = rtrim(dirname(dirname($scriptDir)), '/');
$linkFinal = $protocol . '://' . $host . $appRoot . '/agendar.php?user=' . $userId;

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
        --bg-page: #f8fafc;
        --text-dark: #0f172a;
        --text-gray: #64748b;
        --border-soft: #e2e8f0;
        --shadow-soft: 0 12px 30px rgba(15,23,42,0.10);
        --radius-lg: 20px;
        --radius-md: 14px;
        --radius-pill: 999px;
    }

    body {
        font-family: 'Inter', sans-serif;
        font-size: 14px; /* menor, cara de app */
        background-color: var(--bg-page);
    }

    .main-content {
        width: 100%;
        max-width: 960px;
        margin: 0 auto;
        padding: 16px 14px 90px 14px;
        box-sizing: border-box;
    }

    @media (min-width: 768px) {
        .main-content {
            padding-inline: 20px;
        }
    }

    .config-header {
        margin: 6px 0 18px 0;
    }
    .config-header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-dark);
        letter-spacing: -0.01em;
    }
    .config-header p {
        margin: 4px 0 0 0;
        color: var(--text-gray);
        font-size: 0.86rem;
    }

    .config-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }
    @media(min-width: 900px) {
        .config-grid {
            grid-template-columns: 2fr 1.3fr;
            align-items: flex-start;
        }
        .card-full {
            grid-column: 1 / -1;
        }
    }

    .card {
        background: #ffffff;
        border-radius: 22px;
        padding: 18px 16px 18px 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid #f1f5f9;
    }
    @media (min-width: 768px) {
        .card {
            padding: 20px 20px 18px 20px;
        }
    }

    .card-title {
        margin-top: 0;
        display: flex;
        align-items: center;
        gap: 9px;
        color: var(--text-dark);
        font-size: 0.98rem;
        font-weight: 700;
    }
    .card-title i {
        font-size: 1.05rem;
    }
    .card-desc {
        color: var(--text-gray);
        font-size: 0.86rem;
        margin-bottom: 14px;
        line-height: 1.5;
    }

    .form-group { margin-bottom: 11px; }
    .form-label {
        display: block;
        margin-bottom: 4px;
        font-weight: 600;
        font-size: 0.8rem;
        color: #475569;
    }
    .form-control {
        width: 100%;
        padding: 9px 11px;
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-md);
        box-sizing: border-box;
        font-size: 0.88rem;
        background: #f8fafc;
        transition: 0.15s;
    }
    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        background: #ffffff;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.16);
    }

    .btn-primary {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: var(--radius-pill);
        cursor: pointer;
        font-weight: 600;
        width: 100%;
        transition: 0.18s;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        box-shadow: 0 10px 22px rgba(99,102,241,0.35);
        letter-spacing: 0.01em;
    }
    .btn-primary i {
        font-size: 1rem;
    }
    .btn-primary:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }
    .btn-primary:active {
        transform: translateY(1px) scale(0.98);
        box-shadow: 0 6px 16px rgba(79,70,229,0.4);
    }

    /* Alertas */
    .alert {
        padding: 9px 11px;
        border-radius: 999px;
        margin-bottom: 14px;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .alert.success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .alert.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    /* Color Picker + Paletas */
    .color-picker-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #e2e8f0;
        padding: 8px 9px;
        border-radius: 16px;
        margin-bottom: 9px;
        background: #ffffff;
    }
    input[type="color"] {
        border: none;
        width: 40px;
        height: 40px;
        cursor: pointer;
        background: transparent;
        padding: 0;
    }
    .preview-area {
        flex: 1;
        min-width: 0;
    }
    .preview-label {
        font-size: 0.76rem;
        color: #64748b;
        margin-bottom: 3px;
    }
    .preview-btn {
        padding: 7px 16px;
        border-radius: 999px;
        color: white;
        font-weight: 600;
        border: none;
        font-size: 0.8rem;
        pointer-events: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 8px 18px rgba(15,23,42,0.25);
        white-space: nowrap;
    }

    .hex-helper {
        font-size: 0.74rem;
        color: #94a3b8;
        margin-top: 3px;
    }

    .theme-palettes-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin: 9px 0 4px 0;
        display: flex;
        align-items: center;
        gap: 6px;
        justify-content: space-between;
        flex-wrap: wrap;
    }
    .theme-palettes-title span {
        font-size: 0.72rem;
        font-weight: 500;
        color: #9ca3af;
    }

    .theme-grid-wrapper {
        overflow-x: auto;
        padding-bottom: 3px;
    }

    .theme-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
        gap: 8px;
        min-width: 260px;
    }

    .theme-card {
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 7px 8px;
        cursor: pointer;
        transition: 0.18s;
        background: #f9fafb;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .theme-card-header {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .theme-dot {
        width: 16px;
        height: 16px;
        border-radius: 999px;
        border: 2px solid #e5e7eb;
        box-shadow: 0 0 0 1px rgba(15,23,42,0.12);
        flex-shrink: 0;
    }
    .theme-name {
        font-size: 0.78rem;
        font-weight: 600;
        color: #0f172a;
    }
    .theme-meta {
        font-size: 0.72rem;
        color: #6b7280;
    }

    .theme-swatches {
        display: flex;
        gap: 4px;
        margin-top: 3px;
    }
    .theme-swatch {
        width: 14px;
        height: 14px;
        border-radius: 999px;
        border: 1px solid rgba(15,23,42,0.08);
        flex-shrink: 0;
    }

    .theme-preview-bar {
        height: 6px;
        border-radius: 999px;
        background: #e5e7eb;
        overflow: hidden;
        position: relative;
        margin-top: 3px;
    }
    .theme-preview-bar-inner {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(90deg, rgba(15,23,42,0.10), rgba(15,23,42,0.22));
        mix-blend-mode: multiply;
    }

    .theme-card.active {
        border-color: #6366f1;
        background: #eef2ff;
        box-shadow: 0 0 0 1px rgba(99,102,241,0.45);
    }

    /* Download / link */
    .btn-download {
        background: #0f172a;
        color: white;
        text-decoration: none;
        padding: 10px 18px;
        border-radius: var(--radius-pill);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 600;
        transition: 0.18s;
        font-size: 0.9rem;
        box-shadow: 0 10px 20px rgba(15,23,42,0.4);
    }
    .btn-download:hover {
        background: #111827;
        transform: translateY(-1px);
    }

    .copy-box {
        display: flex;
        gap: 8px;
    }
    .btn-copy {
        background: #e0e7ff;
        color: #4f46e5;
        border: none;
        padding: 0 16px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.8rem;
        white-space: nowrap;
        transition: 0.15s;
    }
    .btn-copy:hover {
        background: #c7d2fe;
    }

    @media (max-width: 640px) {
        .copy-box {
            flex-direction: column;
        }
        .btn-copy {
            width: 100%;
            text-align: center;
            padding: 9px 0;
        }
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
            <p class="card-desc">Envie este link para seus clientes agendarem sozinhos online.</p>

            <label class="form-label">Link público de agendamento</label>
            <div class="copy-box">
                <input
                    type="text"
                    class="form-control"
                    value="<?php echo htmlspecialchars($linkFinal); ?>"
                    id="linkInput"
                    readonly
                >
                <button class="btn-copy" type="button" onclick="copiarLink()">Copiar</button>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">
                <i class="bi bi-shield-lock-fill" style="color:#10b981;"></i>
                Alterar Senha
            </h3>
            <p class="card-desc">Mantenha sua conta segura atualizando a senha periodicamente.</p>

            <form method="POST">
                <input type="hidden" name="acao" value="nova_senha">
                <div class="form-group">
                    <label class="form-label">Senha atual</label>
                    <input type="password" name="senha_atual" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="nova_senha" class="form-control" required placeholder="Min. 6 caracteres">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="confirmar_senha" class="form-control" required>
                </div>
                <button type="submit" class="btn-primary" style="background:#0f172a;">
                    <i class="bi bi-key-fill"></i>
                    Atualizar senha
                </button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-title">
                <i class="bi bi-database-down" style="color:#f59e0b;"></i>
                Backup
            </h3>
            <p class="card-desc">Faça o download de uma cópia do banco de dados com seus agendamentos, clientes e informações.</p>
            <a href="?download_backup=1" class="btn-download">
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

        const btn = document.querySelector('.btn-copy');
        if (btn) {
            btn.innerText = 'Copiado!';
            setTimeout(() => { btn.innerText = 'Copiar'; }, 2000);
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
