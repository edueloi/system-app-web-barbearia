<?php
include '../../includes/db.php';

// ID Fixo (Simulação)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
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

    // PRG: redireciona para evitar repost em F5
    header('Location: perfil.php?status=updated');
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
        --shadow-soft: 0 10px 30px rgba(15,23,42,0.10);
    }

    body {
        background-color: var(--bg-page);
        font-family: 'Inter', sans-serif;
    }

    .main-content {
        width: 100%;
        max-width: 900px;
        margin: 0 auto;
        padding: 16px 16px 90px 16px;
        box-sizing: border-box;
    }

    @media (min-width: 1024px) {
        .main-content {
            padding-top: 24px;
        }
    }

    /* Header do Perfil */
    .profile-card {
        background: white;
        border-radius: 24px;
        box-shadow: var(--shadow-soft);
        overflow: hidden;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
    }

    .profile-header {
        background: linear-gradient(135deg, var(--primary), #4338ca);
        height: 160px;
        position: relative;
    }

    .avatar-wrapper {
        position: absolute;
        bottom: -50px;
        left: 24px;
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: white;
        padding: 4px;
        box-shadow: 0 8px 20px rgba(15,23,42,0.35);
    }

    .avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        background-color: #e2e8f0;
    }

    .btn-upload-foto {
        position: absolute;
        bottom: 2px;
        right: 2px;
        background: var(--text-dark);
        color: white;
        border: 2px solid white;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        font-size: 1rem;
    }
    .btn-upload-foto:hover {
        background: var(--primary);
        transform: scale(1.1);
    }

    .profile-header-text {
        position: absolute;
        bottom: 18px;
        left: 150px;
        color: white;
    }

    .profile-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0 0 4px 0;
    }
    .profile-subtitle {
        font-size: 0.9rem;
        opacity: 0.85;
    }

    @media (max-width: 640px) {
        .profile-header {
            height: 140px;
        }
        .avatar-wrapper {
            left: 50%;
            bottom: -55px;
            transform: translateX(-50%);
        }
        .profile-header-text {
            width: 100%;
            left: 0;
            text-align: center;
            bottom: 12px;
        }
    }

    .profile-body {
        padding: 70px 20px 22px 20px;
    }

    @media (min-width: 768px) {
        .profile-body {
            padding: 70px 28px 26px 28px;
        }
    }

    /* Seções do formulário */
    .form-section {
        background: white;
        border-radius: 20px;
        padding: 22px 20px 8px 20px;
        box-shadow: var(--shadow-soft);
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 16px;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-title i {
        color: var(--primary);
    }

    .input-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 20px;
    }
    .full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #475569;
    }
    .form-control {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        font-size: 0.95rem;
        box-sizing: border-box;
        transition: 0.2s;
        background: #f8fafc;
    }
    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        background: white;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.15);
    }
    textarea.form-control {
        resize: vertical;
        min-height: 90px;
    }

    @media (max-width: 768px) {
        .input-grid {
            grid-template-columns: 1fr;
        }
    }

    .btn-save-wrapper {
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
    }

    .btn-save {
        background: var(--primary);
        color: white;
        border: none;
        padding: 13px 26px;
        border-radius: 999px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 25px rgba(99,102,241,0.35);
        transition: 0.15s;
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
                                   placeholder="Ex: Salão Top Hair" required>
                        </div>
                        <div class="form-group">
                            <label>Nome Completo</label>
                            <input type="text" name="nome" class="form-control"
                                   value="<?php echo htmlspecialchars($user['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email de Contato</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
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
                                   onblur="buscarCep(this.value)">
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
