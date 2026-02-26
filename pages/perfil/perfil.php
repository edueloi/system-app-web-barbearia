<?php
require_once __DIR__ . '/../../includes/config.php';
include '../../includes/db.php';
/** @var PDO $pdo */

// Verifica se está logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isProdTemp = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . ($isProdTemp ? '/login' : '../../login.php'));
    exit;
}
$userId = $_SESSION['user_id'];

// --- 1. SALVAR DADOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estabelecimento = $_POST['estabelecimento'] ?? '';
    $tipoEstabelecimento = $_POST['tipo_estabelecimento'] ?? 'Salão de Beleza';
    $nome            = $_POST['nome'] ?? '';
    $email           = $_POST['email'] ?? '';
    $telefone  = trim($_POST['telefone'] ?? '');
    $telefone  = $telefone !== '' ? $telefone : null;
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $cpf = $cpf !== '' ? $cpf : null;
    $instagram = trim($_POST['instagram'] ?? '');
    $instagram = $instagram !== '' ? ltrim($instagram, '@') : null;
    $bio             = $_POST['biografia'] ?? '';
    // Endereço
    $cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
    $cep = $cep !== '' ? $cep : null;
    $endereco = $_POST['endereco'] ?? '';
    $numero   = $_POST['numero'] ?? '';
    $bairro   = $_POST['bairro'] ?? '';
    $cidade   = $_POST['cidade'] ?? '';
    $estado   = $_POST['estado'] ?? '';

    // Upload Foto Perfil
    $fotoPath = $_POST['foto_atual'] ?? null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ext      = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $novoNome = "avatar_" . $userId . "_" . uniqid() . "." . $ext;
        $destino  = '../../uploads/' . $novoNome;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
            $fotoPath = 'uploads/' . $novoNome;
            // Atualiza sessão para o menu mostrar a foto nova imediatamente
            $_SESSION['user']['avatar'] = $fotoPath;
        }
    }

    // Atualizar no Banco
    $sql = "UPDATE usuarios SET 
                estabelecimento=?, tipo_estabelecimento=?, nome=?, email=?, telefone=?, cpf=?, instagram=?, foto=?, biografia=?, 
                cep=?, endereco=?, numero=?, bairro=?, cidade=?, estado=? 
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $estabelecimento, $tipoEstabelecimento, $nome, $email, $telefone, $cpf, $instagram, $fotoPath, $bio,
        $cep, $endereco, $numero, $bairro, $cidade, $estado, $userId
    ]);

    // Atualiza nome na sessão
    $_SESSION['user']['name'] = $nome;
    $_SESSION['perfil_msg']   = 'Perfil atualizado com sucesso!';

    // 🔹 Descobre se está em produção (salao.develoi.com) ou local
    $isProd = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'salao.develoi.com';
    $perfilUrl = $isProd
        ? '/perfil' // em produção usa rota amigável
        : '/karen_site/controle-salao/pages/perfil/perfil.php';
    // PRG: redireciona para evitar repost em F5
    header("Location: {$perfilUrl}?status=updated");
    exit;
}

$pageTitle = 'Meu Perfil';
include '../../includes/header.php';
include '../../includes/menu.php';

// Cria pasta uploads se não existir
if (!is_dir('../../uploads')) {
    mkdir('../../uploads', 0777, true);
}

// --- 2. BUSCAR DADOS ATUAIS ---
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Se não tiver foto, usa uma padrão
$fotoUrl = (!empty($user['foto']) && file_exists('../../' . $user['foto']))
    ? '../../' . $user['foto']
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['nome'] ?? 'Profissional') . '&background=6366f1&color=fff';

// Guarda mensagem de perfil (para toast) e limpa da sessão
$perfilMsg = $_SESSION['perfil_msg'] ?? null;
unset($_SESSION['perfil_msg']);
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
        max-width: 960px;
        margin: 0 auto;
        padding: 20px 12px 110px 12px;
    }

    /* Card principal do perfil */
    .profile-card {
        background: var(--bg-card);
        border-radius: 18px;
        box-shadow: var(--shadow-soft);
        overflow: hidden;
        margin-bottom: 16px;
        border: 1px solid rgba(148,163,184,0.18);
        transition: all 0.25s ease;
    }

    .profile-card:hover {
        box-shadow: var(--shadow-hover);
    }

    .profile-header {
        background: linear-gradient(135deg, var(--primary-color), var(--accent));
        height: 160px;
        position: relative;
    }

    /* Avatar flutuando */
    .avatar-wrapper {
        position: absolute;
        bottom: -56px;
        left: 24px;
        width: 112px;
        height: 112px;
        border-radius: 999px;
        background: white;
        padding: 4px;
        box-shadow: 0 10px 25px rgba(15,23,42,0.30);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 999px;
        object-fit: cover;
        background-color: #e2e8f0;
    }

    /* Botão da câmera menor e mais “pra fora” pra não tampar o logo */
    .btn-upload-foto {
        position: absolute;
        bottom: 0;
        right: 0;
        transform: translate(40%, 40%);
        background: #020617;
        color: white;
        border: 2px solid white;
        width: 26px;
        height: 26px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.8rem;
        box-shadow: 0 6px 14px rgba(15,23,42,0.4);
    }
    .btn-upload-foto:hover {
        background: var(--primary);
        transform: translate(40%, 40%) scale(1.05);
    }

    /* Texto do topo */
    .profile-header-text {
        position: absolute;
        bottom: 20px;
        left: 140px;
        color: white;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .profile-title {
        font-size: 1.15rem;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.02em;
        text-shadow: 0 2px 8px rgba(15,23,42,0.2);
    }

    .profile-subtitle {
        font-size: 0.75rem;
        opacity: 0.92;
        font-weight: 500;
    }

    .profile-meta-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }

    .badge-soft {
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.3);
        color: #ffffff;
        font-weight: 600;
    }

    .profile-body {
        padding: 58px 16px 18px 16px;
    }

    /* Seções do formulário */
    .form-section {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 16px 16px 12px 16px;
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(148,163,184,0.18);
        margin-bottom: 14px;
        transition: all 0.2s ease;
    }

    .form-section:hover {
        border-color: rgba(79,70,229,0.25);
        box-shadow: 0 8px 24px rgba(15,23,42,0.08);
    }

    .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 14px;
        color: var(--text-main);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(79,70,229,0.08);
        color: var(--primary-color);
    }

    .section-title i {
        font-size: 0.95rem;
    }

    .input-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 14px;
    }

    .full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
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
        border-radius: 10px;
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

    textarea.form-control {
        resize: vertical;
        min-height: 90px;
        max-height: 240px;
        line-height: 1.6;
    }

    .btn-save-wrapper {
        display: flex;
        justify-content: flex-end;
        margin-top: 12px;
    }

    .btn-save {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border: none;
        padding: 11px 24px;
        border-radius: 999px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 10px rgba(79,70,229,0.25);
        transition: all 0.25s ease;
        letter-spacing: 0.01em;
    }

    .btn-save i {
        font-size: 1.05rem;
    }

    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(79,70,229,0.35);
    }

    .btn-save:active {
        transform: translateY(0);
    }

    /* Responsivo Mobile */
    @media (max-width: 768px) {
        .main-content {
            padding: 18px 10px 120px 10px;
        }

        .profile-card {
            border-radius: 14px;
        }

        .profile-header {
            height: 180px; /* dá mais respiro pra cima */
        }

        .avatar-wrapper {
            left: 50%;
            bottom: -45px;
            transform: translateX(-50%);
            width: 90px;
            height: 90px;
        }

        .btn-upload-foto {
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
            transform: translate(30%, 30%);
        }

        .btn-upload-foto:hover {
            transform: translate(30%, 30%) scale(1.05);
        }

        /* AQUI é o ajuste principal: sobe o bloco de texto/badges */
        .profile-header-text {
            width: 100%;
            left: 0;
            text-align: center;
            bottom: 70px;          /* antes era 12px */
            align-items: center;
        }

        .profile-meta-badges {
            justify-content: center;
            max-width: 260px;
            margin-inline: auto;
        }

        .profile-title {
            font-size: 1.05rem;
        }

        .profile-subtitle {
            font-size: 0.72rem;
        }

        .profile-body {
            padding: 54px 14px 16px 14px;
        }

        .form-section {
            padding: 14px 14px 10px 14px;
            border-radius: 12px;
        }

        .section-title {
            font-size: 0.82rem;
            padding: 5px 10px;
        }

        .input-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .form-control {
            font-size: 0.82rem;
            padding: 8px 10px;
        }

        .btn-save-wrapper {
            justify-content: center;
        }

        .btn-save {
            width: 100%;
            justify-content: center;
            padding: 10px 20px;
            font-size: 0.88rem;
        }
    }

    @media (min-width: 769px) {
        .input-grid {
            gap: 14px 16px;
        }

        .form-section {
            padding: 18px 20px 14px 20px;
        }

        .profile-body {
            padding: 58px 24px 20px 24px;
        }
    }
</style>

<main class="main-content">
    <form id="formPerfil" method="POST" enctype="multipart/form-data">

        <div class="profile-card">
            <div class="profile-header">
                <div class="avatar-wrapper">
                    <img src="<?php echo $fotoUrl; ?>" alt="Avatar" class="avatar-img" id="previewAvatar">

                    <label for="uploadFoto" class="btn-upload-foto" title="Alterar foto">
                        <i class="bi bi-camera-fill"></i>
                    </label>
                    <input type="file" name="foto" id="uploadFoto" style="display:none;" accept="image/*" onchange="previewImage(this)">
                    <input type="hidden" name="foto_atual" value="<?php echo htmlspecialchars($user['foto'] ?? ''); ?>">
                </div>

                <div class="profile-header-text">
                    <h1 class="profile-title"><?php echo htmlspecialchars($user['nome'] ?? 'Profissional'); ?></h1>
                    <div class="profile-subtitle">Essas informações aparecem para seus clientes.</div>
                    <div class="profile-meta-badges">
                        <span class="badge-soft"><i class="bi bi-person-badge"></i> Perfil profissional</span>
                        <?php
                        $tipoAtual = $user['tipo_estabelecimento'] ?? 'Salão de Beleza';
                        $iconesTipo = [
                            'Salão de Beleza' => '💇',
                            'Barbearia' => '💈',
                            'Nail Art' => '💅',
                            'Estética' => '✨',
                            'Spa' => '🧖',
                            'Studio' => '🎨'
                        ];
                        $icone = $iconesTipo[$tipoAtual] ?? '💇';
                        ?>
                        <span class="badge-soft"><?php echo $icone; ?> <?php echo htmlspecialchars($tipoAtual); ?></span>
                    </div>
                </div>
            </div>

            <div class="profile-body">

                <div class="form-section">
                    <div class="section-title">
                        <i class="bi bi-person-badge"></i> Dados do profissional
                    </div>
                    <div class="input-grid">
                        <div class="form-group full-width">
                            <label>Nome do Estabelecimento</label>
                            <input type="text" name="estabelecimento" class="form-control"
                                   value="<?php echo htmlspecialchars($user['estabelecimento'] ?? ''); ?>"
                                   placeholder="Ex: Salão Develoi Hair" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Tipo de Estabelecimento</label>
                            <select name="tipo_estabelecimento" class="form-control" required>
                                <option value="Salão de Beleza" <?php echo ($user['tipo_estabelecimento'] ?? '') === 'Salão de Beleza' ? 'selected' : ''; ?>>💇 Salão de Beleza</option>
                                <option value="Barbearia" <?php echo ($user['tipo_estabelecimento'] ?? '') === 'Barbearia' ? 'selected' : ''; ?>>💈 Barbearia</option>
                                <option value="Nail Art" <?php echo ($user['tipo_estabelecimento'] ?? '') === 'Nail Art' ? 'selected' : ''; ?>>💅 Nail Art / Manicure</option>
                                <option value="Estética" <?php echo ($user['tipo_estabelecimento'] ?? '') === 'Estética' ? 'selected' : ''; ?>>✨ Clínica de Estética</option>
                                <option value="Spa" <?php echo ($user['tipo_estabelecimento'] ?? '') === 'Spa' ? 'selected' : ''; ?>>🧖 Spa</option>
                                <option value="Studio" <?php echo ($user['tipo_estabelecimento'] ?? '') === 'Studio' ? 'selected' : ''; ?>>🎨 Studio de Beleza</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nome Completo</label>
                            <input type="text" name="nome" class="form-control"
                                   value="<?php echo htmlspecialchars($user['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email de Contato</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                   placeholder="Seu melhor e-mail para contato">
                        </div>
                        <div class="form-group">
                            <label>Telefone / WhatsApp</label>
                            <input
                                type="text"
                                name="telefone"
                                id="inputTelefone"
                                class="form-control"
                                value="<?php echo htmlspecialchars($user['telefone'] ?? ''); ?>"
                                placeholder="(00) 00000-0000"
                                maxlength="15"
                                oninput="mascaraTelefone(this)"
                            >
                        </div>
                        <div class="form-group">
                            <label>
                                <i class="bi bi-shield-lock"></i> CPF
                                <small style="color:#64748b; font-size:0.75rem; font-weight:400;">(Apenas para Consulta Bot)</small>
                            </label>
                            <input
                                type="text"
                                name="cpf"
                                id="inputCpf"
                                class="form-control"
                                value="<?php echo htmlspecialchars($user['cpf'] ?? ''); ?>"
                                placeholder="000.000.000-00"
                                maxlength="14"
                                oninput="mascaraCpf(this)"
                            >
                            <small style="color:#64748b; font-size:0.75rem; display:block; margin-top:4px;">
                                <i class="bi bi-info-circle"></i> (não visível para clientes)
                            </small>
                        </div>
                        <div class="form-group">
                            <label>
                                <i class="bi bi-instagram" style="color:#E1306C;"></i> Instagram
                            </label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="color:#64748b; font-weight:600; font-size:1.1rem;">@</span>
                                <input
                                    type="text"
                                    name="instagram"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars(ltrim($user['instagram'] ?? '', '@')); ?>"
                                    placeholder="seuperfil"
                                    style="flex:1;"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z0-9._]/g, '')"
                                >
                            </div>
                            <small style="color:#64748b; font-size:0.8rem; display:block; margin-top:4px;">
                                <i class="bi bi-info-circle"></i> Digite apenas o nome do perfil (sem @)
                            </small>
                        </div>
                        <div class="form-group full-width">
                            <label>Biografia / Sobre Mim</label>
                            <textarea name="biografia" class="form-control"
                                      placeholder="Conte um pouco sobre sua experiência, especialidades e estilo de atendimento."><?php
                                echo htmlspecialchars($user['biografia'] ?? '');
                            ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="bi bi-geo-alt"></i> Endereço do salão
                    </div>
                    <div class="input-grid">
                        <div class="form-group">
                            <label>CEP</label>
                            <input type="text" name="cep" class="form-control"
                                   value="<?php echo htmlspecialchars($user['cep'] ?? ''); ?>"
                                   onblur="buscarCep(this.value)" placeholder="Somente números">
                        </div>
                        <div class="form-group">
                            <label>Estado (UF)</label>
                            <input type="text" name="estado" id="uf" class="form-control"
                                   value="<?php echo htmlspecialchars($user['estado'] ?? ''); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>Endereço (Rua, Av.)</label>
                            <input type="text" name="endereco" id="rua" class="form-control"
                                   value="<?php echo htmlspecialchars($user['endereco'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="numero" class="form-control"
                                   value="<?php echo htmlspecialchars($user['numero'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Bairro</label>
                            <input type="text" name="bairro" id="bairro" class="form-control"
                                   value="<?php echo htmlspecialchars($user['bairro'] ?? ''); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>Cidade</label>
                            <input type="text" name="cidade" id="cidade" class="form-control"
                                   value="<?php echo htmlspecialchars($user['cidade'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="btn-save-wrapper">
                    <!-- botão "fake" que abre o AppConfirm -->
                    <button type="button" class="btn-save" id="btnSalvarPerfil">
                        <i class="bi bi-check-circle-fill"></i>
                        Salvar alterações
                    </button>
                </div>

            </div>
        </div>

        <!-- botão real de submit, disparado via JS -->
        <button type="submit" id="btnSubmitReal" style="display:none;"></button>

    </form>
</main>

<?php
// Usa teus componentes globais
include '../../includes/ui-confirm.php';
include '../../includes/ui-toast.php';
include '../../includes/footer.php';
?>

<script>
    // Pré-visualizar a imagem ao selecionar
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewAvatar').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Máscara BR de telefone: (11) 1234-5678 ou (11) 91234-5678
    function mascaraTelefone(el) {
        let v = el.value || "";

        // remove tudo que não é número
        v = v.replace(/\D/g, "");

        // limita a 11 dígitos (2 DDD + 9)
        if (v.length > 11) v = v.substring(0, 11);

        if (v.length > 6) {
            // com traço
            if (v.length === 11) {
                // celular: (11) 91234-5678
                v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, "($1) $2-$3");
            } else {
                // fixo: (11) 1234-5678
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, "($1) $2-$3");
            }
        } else if (v.length > 2) {
            // só DDD + começo do número: (11) 9...
            v = v.replace(/^(\d{2})(\d{0,5}).*/, "($1) $2");
        } else if (v.length > 0) {
            // digitando DDD
            v = v.replace(/^(\d{0,2}).*/, "($1");
        }

        el.value = v;
    }

    // Máscara de CPF: 000.000.000-00
    function mascaraCpf(el) {
        let v = el.value || "";
        
        // remove tudo que não é número
        v = v.replace(/\D/g, "");
        
        // limita a 11 dígitos
        if (v.length > 11) v = v.substring(0, 11);
        
        // aplica a máscara
        if (v.length > 9) {
            v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, "$1.$2.$3-$4");
        } else if (v.length > 6) {
            v = v.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, "$1.$2.$3");
        } else if (v.length > 3) {
            v = v.replace(/^(\d{3})(\d{0,3}).*/, "$1.$2");
        }
        
        el.value = v;
    }

    // Aplica máscara no valor que vem do banco ao carregar a página
    document.addEventListener('DOMContentLoaded', function () {
        const telInput = document.getElementById('inputTelefone');
        if (telInput && telInput.value) {
            mascaraTelefone(telInput);
        }

        const cpfInput = document.getElementById('inputCpf');
        if (cpfInput && cpfInput.value) {
            mascaraCpf(cpfInput);
        }

        // Toast de sucesso após redirect (PRG)
        <?php if ($perfilMsg): ?>
        if (window.AppToast) {
            AppToast.show('<?php echo addslashes($perfilMsg); ?>', 'success');
        }
        <?php endif; ?>
    });

    // Busca automática de CEP (ViaCEP API)
    function buscarCep(cep) {
        cep = (cep || '').replace(/\D/g, '');
        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('rua').value    = data.logradouro || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.localidade || '';
                        document.getElementById('uf').value     = data.uf || '';
                    }
                })
                .catch(() => {});
        }
    }

    // Confirmação usando AppConfirm antes de enviar o formulário
    const btnSalvar  = document.getElementById('btnSalvarPerfil');
    const formPerfil = document.getElementById('formPerfil');
    const btnReal    = document.getElementById('btnSubmitReal');

    if (btnSalvar && formPerfil && btnReal) {
        btnSalvar.addEventListener('click', function (e) {
            e.preventDefault();

            if (window.AppConfirm) {
                AppConfirm.open({
                    title: 'Salvar alterações',
                    message: 'Deseja salvar as alterações do seu perfil?',
                    confirmText: 'Salvar',
                    cancelText: 'Cancelar',
                    type: 'success',
                    onConfirm: function () {
                        btnReal.click();
                    }
                });
            } else {
                btnReal.click();
            }
        });
    }
</script>
