<?php
require_once __DIR__ . '/../../includes/config.php';
include '../../includes/db.php';

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
    $nome            = $_POST['nome'] ?? '';
    $email           = $_POST['email'] ?? '';
    $telefone        = $_POST['telefone'] ?? '';
    $bio             = $_POST['biografia'] ?? '';
    // Endereço
    $cep      = $_POST['cep'] ?? '';
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
                estabelecimento=?, nome=?, email=?, telefone=?, foto=?, biografia=?, 
                cep=?, endereco=?, numero=?, bairro=?, cidade=?, estado=? 
            WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $estabelecimento, $nome, $email, $telefone, $fotoPath, $bio,
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

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
        background-color: var(--bg-page);
        font-family: 'Inter', sans-serif;
        font-size: 14px; /* levemente menor, mais cara de app */
    }

    .main-content {
        width: 100%;
        max-width: 960px;
        margin: 0 auto;
        padding: 16px 14px 90px 14px;
        box-sizing: border-box;
    }

    @media (min-width: 1024px) {
        .main-content {
            padding-top: 24px;
        }
    }

    /* Card principal do perfil */
    .profile-card {
        background: #ffffff;
        border-radius: 24px;
        box-shadow: var(--shadow-soft);
        overflow: hidden;
        margin-bottom: 18px;
        border: 1px solid var(--border-soft);
    }

    .profile-header {
        background: radial-gradient(circle at top left, #818cf8, var(--primary));
        height: 170px; /* mais alto pra dar respiro pro avatar */
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
        bottom: 24px;
        left: 150px;
        color: white;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .profile-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.01em;
    }
    .profile-subtitle {
        font-size: 0.78rem;
        opacity: 0.9;
    }

    .profile-meta-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 4px;
    }

    .badge-soft {
        font-size: 0.72rem;
        padding: 4px 9px;
        border-radius: 999px;
        background: rgba(15,23,42,0.20);
        border: 1px solid rgba(148,163,184,0.45);
        color: #e2e8f0;
    }

    @media (max-width: 640px) {
        .profile-header {
            height: 155px;
        }
        .avatar-wrapper {
            left: 50%;
            z-index: 10;
            bottom: -60px;
            transform: translateX(-50%);
        }
        .btn-upload-foto {
            transform: translate(30%, 30%);
        }
        .profile-header-text {
            width: 100%;
            left: 0;
            text-align: center;
            bottom: 14px;
            align-items: center;
        }
        .profile-body {
            padding: 78px 16px 18px 16px; /* mais espaço pro avatar não encostar no form */
        }
    }

    .profile-body {
        padding: 64px 16px 18px 16px;
    }

    @media (min-width: 768px) {
        .profile-body {
            padding: 64px 24px 22px 24px;
        }
    }

    /* Seções do formulário */
    .form-section {
        background: #ffffff;
        border-radius: var(--radius-lg);
        padding: 18px 16px 10px 16px;
        box-shadow: 0 10px 24px rgba(15,23,42,0.06);
        border: 1px solid var(--border-soft);
        margin-bottom: 16px;
    }

    @media (min-width: 768px) {
        .form-section {
            padding: 20px 20px 12px 20px;
        }
    }

    .section-title {
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--text-dark);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 4px 10px;
        border-radius: var(--radius-pill);
        background: #eef2ff;
        color: #312e81;
    }
    .section-title i {
        color: #4f46e5;
        font-size: 1rem;
    }

    .input-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 16px;
    }
    .full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: 600;
        font-size: 0.78rem;
        color: #475569;
    }
    .form-control {
        width: 100%;
        padding: 9px 11px;
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-md);
        font-size: 0.9rem;
        box-sizing: border-box;
        transition: 0.15s;
        background: #f8fafc;
    }
    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        background: white;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.16);
    }
    textarea.form-control {
        resize: vertical;
        min-height: 80px;
        max-height: 220px;
    }

    @media (max-width: 768px) {
        .input-grid {
            grid-template-columns: 1fr;
        }
    }

    .btn-save-wrapper {
        display: flex;
        justify-content: flex-end;
        margin-top: 8px;
    }

    .btn-save {
        background: var(--primary);
        color: white;
        border: none;
        padding: 11px 22px;
        border-radius: var(--radius-pill);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 10px 24px rgba(99,102,241,0.35);
        transition: 0.15s;
        letter-spacing: 0.01em;
    }
    .btn-save i {
        font-size: 1rem;
    }
    .btn-save:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }
    .btn-save:active {
        transform: translateY(1px) scale(0.98);
        box-shadow: 0 6px 18px rgba(79,70,229,0.4);
    }

    @media (max-width: 640px) {
        .btn-save-wrapper {
            justify-content: center;
        }
        .btn-save {
            width: 100%;
            justify-content: center;
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
                        <span class="badge-soft"><i class="bi bi-scissors"></i> Salão / Studio</span>
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

    // Aplica máscara no valor que vem do banco ao carregar a página
    document.addEventListener('DOMContentLoaded', function () {
        const telInput = document.getElementById('inputTelefone');
        if (telInput && telInput.value) {
            mascaraTelefone(telInput);
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
