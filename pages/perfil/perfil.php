<?php
$pageTitle = 'Meu Perfil';
include '../../includes/header.php';
include '../../includes/menu.php';
include '../../includes/db.php';

// Cria pasta uploads se não existir
if (!is_dir('../../uploads')) { mkdir('../../uploads', 0777, true); }

// ID Fixo (Simulação)
if (!isset($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'];

// --- 1. SALVAR DADOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $bio = $_POST['biografia'];
    
    // Endereço
    $cep = $_POST['cep'];
    $endereco = $_POST['endereco'];
    $numero = $_POST['numero'];
    $bairro = $_POST['bairro'];
    $cidade = $_POST['cidade'];
    $estado = $_POST['estado'];

    // Upload Foto Perfil
    $fotoPath = $_POST['foto_atual'];
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $novoNome = "avatar_" . $userId . "_" . uniqid() . "." . $ext;
        $destino = '../../uploads/' . $novoNome;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
            $fotoPath = 'uploads/' . $novoNome;
            // Atualiza sessão para o menu mostrar a foto nova imediatamente
            $_SESSION['user']['avatar'] = $fotoPath; 
        }
    }

    // Atualizar no Banco
    $sql = "UPDATE usuarios SET 
            nome=?, email=?, telefone=?, foto=?, biografia=?, 
            cep=?, endereco=?, numero=?, bairro=?, cidade=?, estado=? 
            WHERE id=?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $email, $telefone, $fotoPath, $bio, $cep, $endereco, $numero, $bairro, $cidade, $estado, $userId]);
    
    // Atualiza nome na sessão
    $_SESSION['user']['name'] = $nome;

    echo "<script>alert('Perfil atualizado com sucesso!'); window.location.href='perfil.php';</script>";
}

// --- 2. BUSCAR DADOS ATUAIS ---
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Se não tiver foto, usa uma padrão
$fotoUrl = ($user['foto'] && file_exists('../../' . $user['foto'])) ? '../../' . $user['foto'] : 'https://ui-avatars.com/api/?name='.urlencode($user['nome']).'&background=6366f1&color=fff';
?>

<style>
    /* Layout Geral */
    .profile-container { max-width: 900px; margin: 0 auto; }
    
    /* Header do Perfil (Capa + Avatar) */
    .profile-header {
        background: linear-gradient(135deg, var(--primary), #4338ca);
        height: 150px;
        border-radius: 16px 16px 0 0;
        position: relative;
        margin-bottom: 60px;
    }

    .avatar-wrapper {
        position: absolute;
        bottom: -50px;
        left: 40px;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: white;
        padding: 4px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
        bottom: 0;
        right: 0;
        background: var(--text-dark);
        color: white;
        border: 2px solid white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-upload-foto:hover { background: var(--primary); transform: scale(1.1); }

    .header-info {
        position: absolute;
        bottom: -40px;
        left: 180px;
    }
    .header-name { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin: 0; }
    .header-role { color: var(--text-gray); font-size: 0.9rem; }

    /* Cards de Formulário */
    .form-section {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: var(--shadow);
        border: 1px solid #f1f5f9;
        margin-bottom: 25px;
    }
    .section-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
    
    /* Grid de Inputs */
    .input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .full-width { grid-column: 1 / -1; }

    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: #475569; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 1rem; box-sizing: border-box; transition: 0.2s; }
    .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
    textarea.form-control { resize: vertical; min-height: 100px; }

    .btn-save {
        background: var(--primary); color: white; border: none; padding: 15px 30px;
        border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer;
        display: flex; align-items: center; gap: 8px; margin-left: auto;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); transition: 0.2s;
    }
    .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); }

    @media (max-width: 768px) {
        .input-grid { grid-template-columns: 1fr; }
        .header-info { left: 20px; bottom: -90px; }
        .profile-header { margin-bottom: 100px; }
        .avatar-wrapper { left: 50%; transform: translateX(-50%); bottom: -60px; }
        .header-info { text-align: center; width: 100%; left: 0; }
    }
</style>

<main class="main-content">
    <div class="profile-container">

        <form method="POST" enctype="multipart/form-data">
            
            <div class="profile-header">
                <div class="avatar-wrapper">
                    <img src="<?php echo $fotoUrl; ?>" alt="Avatar" class="avatar-img" id="previewAvatar">
                    
                    <label for="uploadFoto" class="btn-upload-foto" title="Alterar foto">
                        <i class="bi bi-camera-fill"></i>
                    </label>
                    <input type="file" name="foto" id="uploadFoto" style="display:none;" accept="image/*" onchange="previewImage(this)">
                    <input type="hidden" name="foto_atual" value="<?php echo $user['foto']; ?>">
                </div>

                <div class="header-info">
                    <h1 class="header-name"><?php echo htmlspecialchars($user['nome'] ?? 'Profissional'); ?></h1>
                    <span class="header-role">Gerencie as suas informações públicas</span>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title"><i class="bi bi-person-badge"></i> Dados Pessoais</div>
                <div class="input-grid">
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email de Contato</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone / WhatsApp</label>
                        <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($user['telefone']); ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group full-width">
                        <label>Biografia / Sobre Mim</label>
                        <textarea name="biografia" class="form-control" placeholder="Conte um pouco sobre a sua experiência..."><?php echo htmlspecialchars($user['biografia']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title"><i class="bi bi-geo-alt"></i> Endereço do Salão</div>
                <div class="input-grid">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" class="form-control" value="<?php echo htmlspecialchars($user['cep']); ?>" onblur="buscarCep(this.value)">
                    </div>
                    <div class="form-group">
                        <label>Estado (UF)</label>
                        <input type="text" name="estado" id="uf" class="form-control" value="<?php echo htmlspecialchars($user['estado']); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Endereço (Rua, Av.)</label>
                        <input type="text" name="endereco" id="rua" class="form-control" value="<?php echo htmlspecialchars($user['endereco']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" name="numero" class="form-control" value="<?php echo htmlspecialchars($user['numero']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" id="bairro" class="form-control" value="<?php echo htmlspecialchars($user['bairro']); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Cidade</label>
                        <input type="text" name="cidade" id="cidade" class="form-control" value="<?php echo htmlspecialchars($user['cidade']); ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-save">
                <i class="bi bi-check-circle-fill"></i> Salvar Alterações
            </button>

        </form>

    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<script>
    // Pré-visualizar a imagem ao selecionar
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewAvatar').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Busca automática de CEP (ViaCEP API)
    function buscarCep(cep) {
        cep = cep.replace(/\D/g, '');
        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('rua').value = data.logradouro;
                        document.getElementById('bairro').value = data.bairro;
                        document.getElementById('cidade').value = data.localidade;
                        document.getElementById('uf').value = data.uf;
                    }
                });
        }
    }
</script>